<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Checkout_Rest_Api {
	public static string $api_namespace = 'vipps-mobilepay-recurring/v1/checkout';

	private static ?WC_Vipps_Recurring_Checkout_Rest_Api $instance = null;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return WC_Vipps_Recurring_Checkout_Rest_Api
	 */
	public static function get_instance(): WC_Vipps_Recurring_Checkout_Rest_Api {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'init' ] );
	}

	public function init() {
		register_rest_route( self::$api_namespace, '/session', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'maybe_create_session' ],
			'permission_callback' => function () {
				// This route does not work if we don't have a cart.
				return ! empty( WC()->cart );
			}
		] );

		register_rest_route( self::$api_namespace, '/session', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'poll_session' ],
			'permission_callback' => function () {
				// This route does not work if we don't have a cart.
				return ! empty( WC()->cart );
			}
		] );
	}

	/**
	 * @return array
	 * @throws WC_Data_Exception
	 */
	public function poll_session(): array {
		$checkout = WC_Vipps_Recurring_Checkout::get_instance();

		$pending_order_id = WC_Vipps_Recurring_Helper::get_checkout_pending_order_id();
		$order            = $pending_order_id ? wc_get_order( $pending_order_id ) : null;
		$return_url       = null;

		if ( $order ) {
			$return_url = WC_Vipps_Recurring_Helper::get_payment_redirect_url( $order );
		}

		$session = $order ? $order->get_meta( WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION ) : false;
		if ( ! $session ) {
			WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ADDRESS_HASH, false );

			return [ 'status' => 'EXPIRED', 'redirect_url' => false ];
		}

		add_filter( 'wc_vipps_recurring_is_vipps_checkout', '__return_true' );
		$status = $checkout->get_checkout_status( $session );

		$failed = $status === 'ERROR' || $status === 'SessionExpired' || $status === 'PaymentTerminated';
		if ( $failed ) {
			WC_Vipps_Recurring_Logger::log( sprintf( "Checkout session %d failed with message %s", $order->get_id(), $status ) );
			$checkout->abandon_checkout_order( $order );

			return [ 'status' => 'FAILED', 'redirect_url' => $return_url ];
		}

		if ( $status === 'PaymentSuccessful' ) {
			// Cancel the session, but do not cancel the order.
			$checkout->abandon_checkout_order( false );

			return [ 'status' => 'COMPLETED', 'redirect_url' => $return_url ];
		}

		// Disallow sessions that go on for too long.
		if ( is_a( $order, "WC_Order" ) ) {
			$created = $order->get_date_created();
			$now     = time();
			try {
				$timestamp = $created->getTimestamp();
			} catch ( Exception $e ) {
				// PHP 8 gives ValueError for certain older versions of WooCommerce here.
				$timestamp = intval( $created->format( 'U' ) );

			}
			$passed  = $now - $timestamp;
			$minutes = ( $passed / 60 );

			// Expire after 50 minutes
			if ( $minutes > 50 ) {
				WC_Vipps_Recurring_Logger::log( sprintf( "Checkout session %s expired after %d minutes (limit 50)", $order->get_id(), $minutes ) );
				$checkout->abandon_checkout_order( $order );

				return [ 'status' => 'EXPIRED', 'redirect_url' => false ];
			}
		}

		// This handles address information data from the poll if present. It is not, currently.
		$change             = false;
		$vipps_address_hash = WC()->session->get( WC_Vipps_Recurring_Helper::SESSION_ADDRESS_HASH );
		if ( isset( $status['billingDetails'] ) || isset( $status['shippingDetails'] ) ) {
			$serialized = sha1( json_encode( @$status['billingDetails'] ) . ':' . json_encode( @$status['shippingDetails'] ) );
			if ( $serialized != $vipps_address_hash ) {
				$change = true;
				WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ADDRESS_HASH, $serialized );
			}
		}

		if ( $change ) {
			$checkout->maybe_update_order_billing_and_shipping( $order, $status );
			$order->save();

			return [ 'status' => 'ORDER_CHANGE', 'redirect_url' => false ];
		}

		return [ 'status' => 'NO_CHANGE', 'redirect_url' => false ];
	}

	/**
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Data_Exception
	 */
	public function maybe_create_session(): array {
		$redirect_url = null;
		$token        = null;
		$url          = null;

		$session = WC_Vipps_Recurring_Checkout::get_instance()->current_pending_session();

		if ( isset( $session['redirect'] ) ) {
			$redirect_url = $session['redirect'];
		}

		if ( isset( $session['session']['token'] ) ) {
			$token = $session['session']['token'];
			$src   = $session['session']['checkoutFrontendUrl'];
			$url   = $src;
		}

		if ( $url ) {
			$pending_order_id = WC_Vipps_Recurring_Helper::get_checkout_pending_order_id();

			return [
				'success'      => true,
				'src'          => $url,
				'redirect_url' => $redirect_url,
				'token'        => $token,
				'order_id'     => $pending_order_id
			];
		}

		$session = null;

		try {
			$partial_order_id = WC_Gateway_Vipps_Recurring::get_instance()->create_partial_order( true );

			$order             = wc_get_order( $partial_order_id );
			$auth_token        = WC_Gateway_Vipps_Recurring::get_instance()->api->generate_idempotency_key();
			$hashed_auth_token = wp_hash_password( $auth_token );

			$order->update_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN, $hashed_auth_token );
			$order->save();

			WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_CHECKOUT_PENDING_ORDER_ID, $partial_order_id );

			// todo: impl static shipping
//			try {
//				WC_Vipps_Recurring::get_instance()->maybe_add_static_shipping( WC_Gateway_Vipps_Recurring::get_instance(), $order->get_id(), 'checkout' );
//			} catch ( Exception $e ) {
//				// In this case, we just have to continue.
//				WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Error calculating static shipping for order: %s", $order->get_id(), $e->getMessage() ) );
//			}
			do_action( 'wc_vipps_recurring_checkout_order_created', $order );
		} catch ( Exception $exception ) {
			return [
				'success'      => false,
				'msg'          => $exception->getMessage(),
				'src'          => null,
				'redirect_url' => null,
				'order_id'     => 0
			];
		}

		$order = wc_get_order( $partial_order_id );

		$session_orders = WC()->session->get( WC_Vipps_Recurring_Helper::SESSION_ORDERS );
		if ( ! $session_orders ) {
			$session_orders = [];
		}

		$session_orders[ $partial_order_id ] = 1;
		WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_PENDING_ORDER_ID, $partial_order_id );
		WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ORDERS, $session_orders );

		$customer_id = get_current_user_id();
		if ( $customer_id ) {
			$customer = new WC_Customer( $customer_id );
		} else {
			$customer = WC()->customer;
		}

		if ( $customer ) {
			$customer_info['email']         = $customer->get_billing_email();
			$customer_info['firstName']     = $customer->get_billing_first_name();
			$customer_info['lastName']      = $customer->get_billing_last_name();
			$customer_info['streetAddress'] = $customer->get_billing_address_1();
			$address2                       = trim( $customer->get_billing_address_2() );

			if ( ! empty( $address2 ) ) {
				$customer_info['streetAddress'] = $customer_info['streetAddress'] . ", " . $address2;
			}
			$customer_info['city']       = $customer->get_billing_city();
			$customer_info['postalCode'] = $customer->get_billing_postcode();
			$customer_info['country']    = $customer->get_billing_country();

			// Currently Vipps requires all phone numbers to have area codes and NO +. We can't guarantee that at all, but try for Norway
			$normalized_phone_number = WC_Vipps_Recurring_Helper::normalize_phone_number( $customer->get_billing_phone(), $customer_info['country'] );
			if ( $normalized_phone_number ) {
				$customer_info['phoneNumber'] = $normalized_phone_number;
			}
		}

		$keys = [ 'firstName', 'lastName', 'streetAddress', 'postalCode', 'country', 'phoneNumber' ];
		foreach ( $keys as $k ) {
			if ( empty( $customer_info[ $k ] ) ) {
				$customer_info = [];
				break;
			}
		}
		$customer_info = apply_filters( 'wc_vipps_recurring_customer_info', $customer_info, $order );

		try {
			$checkout = WC_Vipps_Recurring_Checkout::get_instance();
			$gateway  = $checkout->gateway();

			$order_prefix = $gateway->order_prefix;

			$agreement = $gateway->create_vipps_agreement_from_order( $order );

			$checkout_subscription = ( new WC_Vipps_Checkout_Session_Subscription() )
				->set_amount(
					( new WC_Vipps_Checkout_Session_Amount() )
						->set_value( $agreement->pricing->amount )
						->set_currency( $agreement->pricing->currency )
				)
				->set_product_name( $agreement->product_name )
				->set_interval( $agreement->interval )
				->set_merchant_agreement_url( $agreement->merchant_agreement_url );

			if ( $agreement->campaign ) {
				$checkout_subscription = $checkout_subscription->set_campaign( $agreement->campaign );
			}

			if ( $agreement->product_description ) {
				$checkout_subscription = $checkout_subscription->set_product_description( $agreement->product_description );
			}

			$customer = new WC_Vipps_Checkout_Session_Customer( $customer_info );

			// Create a checkout session dto
			$checkout_session = ( new WC_Vipps_Checkout_Session() )
				->set_type( WC_Vipps_Checkout_Session::TYPE_SUBSCRIPTION )
				->set_subscription( $checkout_subscription )
				->set_merchant_info(
					( new WC_Vipps_Checkout_Session_Merchant_Info() )
						->set_callback_url( $gateway->webhook_callback_url() )
						->set_return_url( $agreement->merchant_redirect_url )
						->set_callback_authorization_token( $auth_token )
				)
				->set_prefill_customer( $customer )
				->set_configuration(
					( new WC_Vipps_Checkout_Session_Configuration() )
						->set_user_flow( WC_Vipps_Checkout_Session_Configuration::USER_FLOW_WEB_REDIRECT )
						->set_customer_interaction( WC_Vipps_Checkout_Session_Configuration::CUSTOMER_INTERACTION_NOT_PRESENT )
						->set_elements( WC_Vipps_Checkout_Session_Configuration::ELEMENTS_FULL )
						->set_require_user_info( empty( $customer->email ) )
						->set_show_order_summary( true )
				);

			if ( $agreement->initial_charge ) {
				$reference = WC_Vipps_Recurring_Helper::generate_vipps_order_id( $order, $order_prefix );

				$checkout_transaction = ( new WC_Vipps_Checkout_Session_Transaction() )
					->set_reference( $reference )
					->set_amount(
						( new WC_Vipps_Checkout_Session_Amount() )
							->set_value( $agreement->initial_charge->amount )
							->set_currency( $agreement->pricing->currency )
					)
					->set_order_summary( $checkout->make_order_summary( $order ) );

				if ( $agreement->initial_charge->description ) {
					$checkout_transaction = $checkout_transaction->set_payment_description( $agreement->initial_charge->description );
				}

				$checkout_session = $checkout_session->set_transaction( $checkout_transaction );

				WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID, $reference );
			}

			$checkout_session = apply_filters( 'wc_vipps_recurring_checkout_session', $checkout_session, $order );

			$session = WC_Gateway_Vipps_Recurring::get_instance()->api->checkout_initiate( $checkout_session );

			$order = wc_get_order( $partial_order_id );
			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, true );
			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_INITIAL, true );
			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION, $session->to_array() );

			$session_poll = WC_Gateway_Vipps_Recurring::get_instance()->api->checkout_poll( $session->polling_url );
			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION_ID, $session_poll['sessionId'] );

			$order->add_order_note( __( 'Vipps/MobilePay recurring checkout payment initiated', 'vipps-recurring-payments-gateway-for-woocommerce' ) );
			$order->add_order_note( __( 'Customer passed to Vipps/MobilePay checkout', 'vipps-recurring-payments-gateway-for-woocommerce' ) );
			$order->save();

			$token = $session->token;
			$src   = $session->checkout_frontend_url;
			$url   = $src;
		} catch ( Exception $e ) {
			WC_Vipps_Recurring_Logger::log( sprintf( "Could not initiate Vipps/MobilePay checkout session: %s", $e->getMessage() ) );

			return [
				'success'      => false,
				'msg'          => $e->getMessage(),
				'src'          => null,
				'redirect_url' => null,
				'order_id'     => $partial_order_id
			];
		}

		if ( $url || $redirect_url ) {
			return [
				'success'      => true,
				'msg'          => 'session started',
				'src'          => $url,
				'redirect_url' => $redirect_url,
				'token'        => $token,
				'order_id'     => $partial_order_id
			];
		}

		return [
			'success'      => false,
			'msg'          => __( 'Could not start Vipps/MobilePay checkout session', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'src'          => $url,
			'redirect_url' => $redirect_url,
			'order_id'     => $partial_order_id
		];
	}
}
