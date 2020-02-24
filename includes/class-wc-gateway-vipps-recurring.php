<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( __DIR__ . '/class-wc-vipps-recurring-api.php' );

/**
 * WC_Gateway_Vipps class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Vipps_Recurring extends WC_Payment_Gateway {
	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Api access client id
	 *
	 * @var string
	 */
	public $client_id;

	/**
	 * Api access subscription key
	 *
	 * @var string
	 */
	public $subscription_key;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $api_url;

	/**
	 * @var WC_Gateway_Vipps_Recurring The reference the *Singleton* instance of this class
	 */
	private static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return WC_Gateway_Vipps_Recurring The *Singleton* instance.
	 */
	public static function get_instance(): \WC_Gateway_Vipps_Recurring {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'vipps_recurring';
		$this->method_title       = __( 'Vipps Recurring Payments', 'woo-vipps-recurring' );
		$this->method_description = __( 'Vipps Recurring Payments works by redirecting your customers to the Vipps portal for confirmation. It creates a payment plan and charges your users on the intervals you specify.', 'woo-vipps-recurring' );
		$this->has_fields         = true;

		$this->supports = [
			'subscriptions',
			'refunds',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->title             = $this->get_option( 'title' );
		$this->description       = $this->get_option( 'description' );
		$this->enabled           = $this->get_option( 'enabled' );
		$this->testmode          = 'yes' === $this->get_option( 'testmode' );
		$this->secret_key        = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->client_id         = $this->testmode ? $this->get_option( 'test_client_id' ) : $this->get_option( 'client_id' );
		$this->subscription_key  = $this->testmode ? $this->get_option( 'test_subscription_key' ) : $this->get_option( 'subscription_key' );
		$this->order_button_text = __( 'Pay with Vipps', 'woo-vipps-recurring' );

		$this->api_url = $this->testmode ? 'https://apitest.vipps.no' : 'https://api.vipps.no';
		$this->api     = new VippsRecurringApi( $this );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
			$this,
			'process_admin_options'
		] );
		add_action( 'woocommerce_account_view-order_endpoint', [ $this, 'check_charge_status' ], 1 );
		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [
			$this,
			'scheduled_subscription_payment'
		], 1, 2 );
		add_action( 'woocommerce_subscription_status_cancelled', [
			$this,
			'cancel_subscription',
		] );
		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'maybe_process_redirect_order' ] );
	}

	/**
	 * @param $order_id
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function process_redirect_payment( $order_id ): void {
		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! is_object( $order ) ) {
			return;
		}

		if ( 'processing' === $order->get_status() || 'completed' === $order->get_status() || 'on-hold' === $order->get_status() ) {
			return;
		}

		$payment_method = WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ? $order->payment_method : $order->get_payment_method();
		if ( $payment_method !== $this->id ) {
			// If this is not the payment method, an agreement would not be available.
			return;
		}

		// check latest charge status
		$this->check_charge_status( $order_id );
	}

	/**
	 * @param $order_id
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function maybe_process_redirect_order( $order_id ): void {
		if ( ! is_order_received_page() ) {
			return;
		}

		$this->process_redirect_payment( $order_id );
	}

	/**
	 * Retrieves the latest charge for an order if any. False if none or invalid agreement id
	 *
	 * @param $order
	 *
	 * @return bool|mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function get_latest_charge_from_order( $order ) {
		$order_id = WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

		if ( WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ) {
			$agreement_id = get_post_meta( $order_id, '_agreement_id', true );
		} else {
			$agreement_id = $order->get_meta( '_agreement_id' );
		}

		if ( ! $agreement_id ) {
			return false;
		}

		$charges = $this->api->get_charges_for( $agreement_id );

		// return false if there is no charge
		// this will tell us if this was directly captured or not
		if ( count( $charges ) === 0 ) {
			return false;
		}

		$charge = null;
		if ( $charges ) {
			$charge = array_reverse( $charges )[0];
		}

		return $charge;
	}

	/**
	 * Receives the agreement associated with an order
	 *
	 * @param $order
	 *
	 * @return bool|mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function get_agreement_from_order( $order ) {
		$order_id = WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ? $order->id : $order->get_id();

		if ( WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ) {
			$agreement_id = get_post_meta( $order_id, '_agreement_id', true );
		} else {
			$agreement_id = $order->get_meta( '_agreement_id' );
		}

		if ( ! $agreement_id ) {
			return false;
		}

		return $this->api->get_agreement( $agreement_id );
	}

	/**
	 * @param $order_id
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function check_charge_status( $order_id ): void {
		if ( empty( $order_id ) || absint( $order_id ) <= 0 ) {
			return;
		}

		$order = wc_get_order( absint( $order_id ) );

		$payment_method = WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ? $order->payment_method : $order->get_payment_method();
		if ( $payment_method !== $this->id ) {
			// If this is not the payment method, an agreement would not be available.
			return;
		}

		$agreement = $this->get_agreement_from_order( $order );
		$charge    = $this->get_latest_charge_from_order( $order );

		$initial        = empty( $order->get_meta( '_vipps_recurring_initial' ) );
		$pending_charge = $initial ? 1 : $order->get_meta( '_vipps_recurring_pending_charge' );

		// If payment has already been captured, this function is redundant.
		if ( ! $pending_charge ) {
			return;
		}

		$is_captured = $charge !== false;
		$order->update_meta_data( '_vipps_recurring_captured', $is_captured );

		if ( $initial ) {
			$order->update_meta_data( '_vipps_recurring_initial', true );
			$order->update_meta_data( '_vipps_recurring_pending_charge', true );

			if ( $is_captured ) {
				/* translators: Vipps Charge ID */
				$message = sprintf( __( 'Vipps charge captured instantly (Charge ID: %s)', 'woo-vipps-recurring' ), $charge['id'] );
				$order->add_order_note( $message );
			} else {
				// not auto captured (because item is not virtual), so we need to put the order on hold
				$order->update_status( 'on-hold' );
				$message = __( 'Vipps awaiting manual capture', 'woo-vipps-recurring' );
				$order->add_order_note( $message );
			}
		}

		$order->save();
		clean_post_cache( $order->get_id() );

		// agreement is expired or stopped
		if ( in_array( $agreement['status'], [ 'STOPPED', 'EXPIRED' ] ) ) {
			$order->update_status( 'cancelled', __( 'The agreement was cancelled or expired in Vipps', 'woo-vipps-recurring' ) );

			// cancel charge
			if ( in_array( $charge['status'], [ 'DUE', 'PENDING' ] ) ) {
				$order->update_meta_data( '_vipps_recurring_pending_charge', false );
				$order->save();
				$this->api->cancel_charge( $agreement['id'], $charge['id'] );
			}
		} elseif ( $is_captured ) {
			$this->process_order_charge( $order, $charge );
		}
	}

	/**
	 * @param $order
	 * @param $charge
	 */
	public function process_order_charge( $order, $charge ): void {
		if ( ! $charge ) {
			// No charge
			return;
		}

		// If payment has already been completed, this function is redundant.
		if ( ! $order->get_meta( '_vipps_recurring_pending_charge' ) ) {
			return;
		}

		// check if status is CHARGED
		if ( 'CHARGED' === $charge['status'] ) {
			$order->payment_complete( $charge['id'] );
			$order->update_meta_data( '_vipps_recurring_pending_charge', false );

			/* translators: Vipps Charge ID */
			$message = sprintf( __( 'Vipps charge completed (Charge ID: %s)', 'woo-vipps-recurring' ), $charge['id'] );
			$order->add_order_note( $message );
		}

		// check if status is DUE
		// when DUE we need to check that it becomes another status in a cron
		if ( in_array( $charge['status'], [ 'DUE', 'PENDING' ] ) ) {
			$order_stock_reduced = WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ? get_post_meta( $order->get_id(), '_order_stock_reduced', true ) : $order->get_meta( '_order_stock_reduced', true );

			if ( ! $order_stock_reduced ) {
				WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order->get_id() );
			}

			WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ? update_post_meta( $order->get_id(), '_transaction_id', $charge['id'] ) : $order->set_transaction_id( $charge['id'] );

			/* translators: Vipps Charge ID */
			$order->update_status( 'on-hold', sprintf( __( 'Vipps charge awaiting payment: %s. The amount will be drawn from your customer in 6 days.', 'woo-vipps-recurring' ), $charge['id'] ) );
		}

		// check if CANCELLED
		if ( 'CANCELLED' === $charge['status'] ) {
			$order->update_status( 'cancelled', __( 'Vipps payment cancelled.', 'woo-vipps-recurring' ) );
			$order->update_meta_data( '_vipps_recurring_pending_charge', false );
		}

		// check if FAILED
		if ( 'FAILED' === $charge['status'] ) {
			$order->update_status( 'failed', __( 'Vipps payment failed.', 'woo-vipps-recurring' ) );
			$order->update_meta_data( '_vipps_recurring_pending_charge', false );
		}

		$order->save();
	}

	/**
	 * Proceed with current request using new login session (to ensure consistent nonce).
	 */
	public function set_cookie_on_current_request( $cookie ): void {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	}

	/**
	 * Returns all supported currencies for this payment method.
	 *
	 * @return array
	 * @version 4.0.0
	 * @since 4.0.0
	 */
	public function get_supported_currency(): array {
		return apply_filters(
			'wc_vipps_recurring_supported_currencies',
			[
				'NOK',
			]
		);
	}

	/**
	 * Checks to see if all criteria is met before showing payment method.
	 *
	 * @return bool
	 * @version 4.0.0
	 * @since 4.0.0
	 */
	public function is_available(): bool {
		if ( ! in_array( get_woocommerce_currency(), $this->get_supported_currency(), true ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Triggered when it's time to charge a subscription
	 *
	 * @param $amount_to_charge
	 * @param $order
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order ): void {
		$this->process_subscription_payment( $amount_to_charge, $order, false, true );
	}

	/**
	 * Triggered when cancelled, failed or refunded order
	 *
	 * @param $subscription
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function cancel_subscription( $subscription ): void {
		$payment_method = WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ? $subscription->payment_method : $subscription->get_payment_method();
		if ( $payment_method !== $this->id ) {
			// If this is not the payment method, an agreement would not be available.
			return;
		}

		$agreement_id = get_post_meta( $subscription->get_id(), '_agreement_id' )[0];
		$agreement    = $this->api->get_agreement( $agreement_id );
		$this->api->cancel_agreement( $agreement );

		WC_Vipps_Recurring_Logger::log( 'cancel_subscription - ' . $subscription->get_id() . ' - ' . $agreement_id );
	}

	/**
	 * @param int $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return bool|void
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order        = wc_get_order( $order_id );
		$agreement_id = $order->get_meta( '_agreement_id' );
		$charge_id    = $order->get_transaction_id();

		try {
			if ( $amount !== null ) {
				$amount = WC_Vipps_Recurring_Helper::get_vipps_amount( $amount );
			}

			$this->api->refund_charge( $agreement_id, $charge_id, $amount, $reason );

			WC_Vipps_Recurring_Logger::log( 'process_refund - ' . $order_id . ' - ' . $agreement_id . ' - ' . $charge_id );

			return true;
		} catch ( WC_Vipps_Recurring_Exception $e ) {
			// refund failed, if status in on-hold we can try cancelling the charge instead:
			if ( $order->get_status() === 'on-hold' ) {
				throw new WC_Vipps_Recurring_Exception( __( 'You can not refund a pending or due Vipps charge. Please wait till the payment clears first!', 'woo-vipps-recurring' ) );
			}

			return false;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * @param $order
	 *
	 * @return mixed|void
	 */
	public function get_idempotence_key( $order ) {
		$idempotence_key = $order->get_meta( '_idempotency_key' );

		if ( ! $idempotence_key ) {
			$idempotence_key = wp_generate_password( 12 );
		}

		$order->update_meta_data( '_idempotency_key', $idempotence_key );
		$order->save();

		return $idempotence_key;
	}

	/**
	 * @param $amount
	 * @param $renewal_order
	 */
	public function process_subscription_payment( $amount, $renewal_order ): void {
		try {
			// create charge logic
			$agreement_id = $renewal_order->get_meta( '_agreement_id' );
			$agreement    = $this->api->get_agreement( $agreement_id );
			$amount       = WC_Vipps_Recurring_Helper::get_vipps_amount( $amount );

			// idempotency key
			$idempotence_key = $this->get_idempotence_key( $renewal_order );

			$charge = $this->api->create_charge( $agreement, $renewal_order, $idempotence_key, $amount );
			$charge = $this->api->get_charge( $agreement_id, $charge['chargeId'] );

			$this->process_order_charge( $renewal_order, $charge );

			WC_Vipps_Recurring_Logger::log( 'process_subscription_payment' );
		} catch ( WC_Vipps_Recurring_Exception $e ) {
			WC_Vipps_Recurring_Logger::log( 'Error: ' . $e->getMessage() );

			$renewal_order->update_status( 'failed' );
		}
	}

	/**
	 * Capture an initial payment manually
	 *
	 * @param $order
	 */
	public function capture_payment( $order ): void {
		try {
			$agreement_id = $order->get_meta( '_agreement_id' );
			$agreement    = $this->api->get_agreement( $agreement_id );

			// idempotency key
			$idempotency_key = $this->get_idempotence_key( $order );

			$charge = $this->api->create_charge( $agreement, $order, $idempotency_key );

			// get charge
			$charge = $this->api->get_charge( $agreement_id, $charge['chargeId'] );

			$message = sprintf( __( 'Vipps charge awaiting payment: %s. The amount will be drawn from your customer in 6 days.', 'woo-vipps-recurring' ), $charge['id'] );
			$order->add_order_note( $message );

			$order->update_meta_data( '_vipps_recurring_captured', true );
			$order->save();

			$this->process_order_charge( $order, $charge );
			clean_post_cache( $order->get_id() );

			WC_Vipps_Recurring_Logger::log( 'capture_payment: ' . $order->get_id() );
		} catch ( WC_Vipps_Recurring_Exception $e ) {
			WC_Vipps_Recurring_Logger::log( 'Error: ' . $e->getMessage() );

			// cancel subscription if not an idempotency error
			if ( ! $e->is_idempotent_error ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order );
				foreach ( $subscriptions as $subscription ) {
					$subscription->update_status( 'cancelled', __( 'Subscription cancelled due to failed Vipps payment capture', 'woocommerce-gateways-vipps-recurring' ) );
				}

				$order->update_status( 'failed', __( 'Vipps failed to create charge', 'woo-vipps-recurring' ) );
			}
		}
	}

	/**
	 * @param int $order_id
	 * @param bool $retry
	 * @param bool $previous_error
	 *
	 * @return array|null
	 */
	public function process_payment( $order_id, $retry = true, $previous_error = false ): ?array {
		$order = wc_get_order( $order_id );

		$subscriptions = wcs_get_subscriptions_for_order( $order );
		$subscription  = $subscriptions[ array_key_first( $subscriptions ) ];

		$period = $subscription->get_billing_period();

		try {
			$items = array_reverse( $order->get_items() );

			// there should ever only be one with this gateway
			$item    = array_pop( $items );
			$product = $item->get_product();

			// create Vipps agreement
			$agreement_url = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
			$redirect_url  = $this->get_return_url( $order );

			$agreement_body = [
				'currency'             => $order->get_currency(),
				'price'                => WC_Vipps_Recurring_Helper::get_vipps_amount( $product->get_price() ),
				'interval'             => strtoupper( $period ),
				'intervalCount'        => 1,
				'productName'          => $item->get_name(),
				'productDescription'   => $item->get_name(),
				'isApp'                => false,
				'merchantAgreementUrl' => $agreement_url,
				'merchantRedirectUrl'  => $redirect_url,
			];

			// validate phone number and only add it if it's up to Vipps' standard
			if ( WC_Vipps_Recurring_Helper::is_valid_phone_number( $order->get_billing_phone() ) ) {
				$agreement_body['customerPhoneNumber'] = $order->get_billing_phone();
			}

			// virtual items need to have an initialCharge (DIRECT CAPTURE)
			if ( $product->is_virtual( 'yes' ) ) {
				$agreement_body = array_merge( $agreement_body, [
					'initialCharge' => [
						'amount'          => WC_Vipps_Recurring_Helper::get_vipps_amount( $order->get_total() ),
						'currency'        => $order->get_currency(),
						'description'     => $item->get_name(),
						'transactionType' => 'DIRECT_CAPTURE',
					],
				] );
			}

			$response = $this->api->create_agreement( $agreement_body );

			$subscriptions = wcs_get_subscriptions_for_order( $order );
			update_post_meta( $subscriptions[ array_key_first( $subscriptions ) ]->get_id(), '_agreement_id', $response['agreementId'] );
			$order->update_meta_data( '_agreement_id', $response['agreementId'] );
			$order->save();

			// redirect to Vipps
			return [
				'result'   => 'success',
				'redirect' => $response['vippsConfirmationUrl'],
			];
		} catch ( WC_Vipps_Recurring_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_Vipps_Recurring_Logger::log( 'Error: ' . $e->getMessage() );

			$order->update_status( 'failed' );

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}

	/**
	 * All payment icons that work with Vipps. Some icons references
	 * WC core icons.
	 *
	 * @return array
	 * @since 4.1.0 Changed to using img with svg (colored) instead of fonts.
	 * @since 4.0.0
	 */
	public function payment_icons(): array {
		return apply_filters(
			'wc_vipps_recurring_payment_icons',
			[
				'vipps' => '<img src="' . WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/images/vipps-logo.svg" class="vipps-recurring-icon" alt="Vipps" />',
			]
		);
	}

	/**
	 * Get_icon function.
	 *
	 * @return string
	 * @version 4.0.0
	 * @since 1.0.0
	 */
	public function get_icon(): string {
		$icons = $this->payment_icons();

		$icons_str = $icons['vipps'];

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields(): void {
		$this->form_fields = require( __DIR__ . '/admin/vipps-recurring-settings.php' );
	}
}
