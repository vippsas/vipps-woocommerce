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
			'permission_callback' => '__return_true'
		] );
	}

	/**
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Data_Exception
	 */
	public function maybe_create_session( WP_REST_Request $request ): array {
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

		// todo: logic for creating a new checkout session
		// todo: need logic to create a partial order
		// todo: on success we need to create a subcription as well, but deal with this in the code that handles payment success
		// todo: static shipping
		// todo: after we have partial order support we will also be able to do "express checkout" style payments with subscriptions
		$session = null;

		try {
			[
				$partial_order_id,
				$partial_subscription_id
			] = WC_Gateway_Vipps_Recurring::get_instance()->create_partial_order_and_subscription( true );

			$order      = wc_get_order( $partial_order_id );
			$auth_token = WC_Gateway_Vipps_Recurring::get_instance()->api->generate_idempotency_key();

			$order->update_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN, wp_hash_password( $auth_token ) );
			$order->save();

			WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_CHECKOUT_PENDING_ORDER_ID, $partial_order_id );

			// todo: impl static shipping
//			try {
//				WC_Vipps_Recurring::get_instance()->maybe_add_static_shipping( WC_Gateway_Vipps_Recurring::get_instance(), $order->get_id(), 'checkout' );
//			} catch ( Exception $e ) {
//				// In this case, we just have to continue.
//				WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Error calculating static shipping for order: %s", $order->get_id(), $e->getMessage() ) );
//			}

			WC_Gateway_Vipps_Recurring::get_instance()->save_session_in_order( $order );
			do_action( 'woo_vipps_recurring_checkout_order_created', $order );
		} catch ( Exception $exception ) {
			return [
				'success'      => false,
				'msg'          => $exception->getMessage(),
				'src'          => null,
				'redirect_url' => null,
				'order_id'     => 0
			];
		}

		$order        = wc_get_order( $partial_order_id );
		$subscription = wcs_get_subscription( $partial_subscription_id );

		$request_id = 1;

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
		$customer_info = apply_filters( 'wc_vipps_recurring_customer_info', $customer_info, $order, $subscription );

		try {
			$gateway      = WC_Gateway_Vipps_Recurring::get_instance();
			$order_prefix = $gateway->order_prefix;

			$agreement = $gateway->create_vipps_agreement_from_order( $subscription );

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
//						->set_show_order_summary( true )
				);

			if ( $agreement->initial_charge ) {
				$checkout_transaction = ( new WC_Vipps_Checkout_Session_Transaction() )
					->set_reference( WC_Vipps_Recurring_Helper::generate_vipps_order_id( $order, $order_prefix ) )
					->set_amount(
						( new WC_Vipps_Checkout_Session_Amount() )
							->set_value( $agreement->initial_charge->amount )
							->set_currency( $agreement->pricing->currency )
					)//						->set_order_summary()
				;

				if ( $agreement->initial_charge->description ) {
					$checkout_transaction = $checkout_transaction->set_payment_description( $agreement->initial_charge->description );
				}

				$checkout_session = $checkout_session->set_transaction( $checkout_transaction );
			}

//			die( json_encode( $checkout_session->to_array() ) );

			$checkout_session = apply_filters( 'wc_vipps_recurring_checkout_session', $checkout_session, $order, $subscription );

			$session = WC_Gateway_Vipps_Recurring::get_instance()->api->checkout_initiate( $checkout_session );

			$order = wc_get_order( $partial_order_id );
			$order->update_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION, $session->to_array() );

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
