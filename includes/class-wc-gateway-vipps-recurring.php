<?php

defined( 'ABSPATH' ) || exit;

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
	 * The page to redirect to for cancelled orders
	 *
	 * @var integer
	 */
	public $cancelled_order_page;

	/**
	 * The default status to give pending renewals
	 *
	 * @var string
	 */
	public $default_renewal_status;

	/**
	 * The default status pending orders that have yet to be captured (reserved charges in Vipps) should be given
	 *
	 * @var string
	 */
	public $default_reserved_charge_status;

	/**
	 * Status where when transitioned to we will attempt to capture the payment
	 *
	 * @var array
	 */
	public $statuses_to_attempt_capture;

	/**
	 * Transition the order status to completed when a renewal order has been charged successfully
	 * regardless of previous status
	 *
	 * @var string
	 */
	public $transition_renewals_to_completed;

	/**
	 * @var WC_Gateway_Vipps_Recurring The reference the *Singleton* instance of this class
	 */
	private static $instance;

	/**
	 * @var WC_Vipps_Recurring_Api
	 */
	private $api;

	/**f
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return WC_Gateway_Vipps_Recurring The *Singleton* instance.
	 */
	public static function get_instance(): WC_Gateway_Vipps_Recurring {
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

		/*
		 * Do not add 'multiple_subscriptions' to $supports.
		 * Vipps Recurring API does not have any concept of multiple line items at the time of writing this.
		 * It could technically be possible to support this, but it's very confusing for a customer in the Vipps app.
		 * There are a lot of edge cases to think about in order to support this functionality too,
		 * 'process_payment' would have to be rewritten entirely.
		 */

		$this->supports = [
			'subscriptions',
			'refunds',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$this->title                            = $this->get_option( 'title' );
		$this->description                      = $this->get_option( 'description' );
		$this->enabled                          = $this->get_option( 'enabled' );
		$this->testmode                         = WC_VIPPS_RECURRING_TEST_MODE;
		$this->secret_key                       = $this->get_option( 'secret_key' );
		$this->client_id                        = $this->get_option( 'client_id' );
		$this->subscription_key                 = $this->get_option( 'subscription_key' );
		$this->cancelled_order_page             = $this->get_option( 'cancelled_order_page' );
		$this->default_renewal_status           = $this->get_option( 'default_renewal_status' );
		$this->default_reserved_charge_status   = $this->get_option( 'default_reserved_charge_status' );
		$this->transition_renewals_to_completed = $this->get_option( 'transition_renewals_to_completed' );
		$this->order_button_text                = __( 'Pay with Vipps', 'woo-vipps-recurring' );

		$this->api_url = $this->testmode ? 'https://apitest.vipps.no' : 'https://api.vipps.no';
		$this->api     = new WC_Vipps_Recurring_Api( $this );

		// when transitioning an order to these statuses we should
		// automatically try to capture the charge if it's not already captured
		$capture_statuses = [
			'completed',
			'processing'
		];

		// we have to remove the status corresponding to `$this->default_reserved_charge_status` otherwise we end up
		// prematurely capturing this reserved Vipps charge
		$capture_status_transition_id = array_search( str_replace( 'wc-', '', $this->default_reserved_charge_status ), $capture_statuses, true );
		if ( $capture_status_transition_id ) {
			unset( $capture_statuses[ $capture_status_transition_id ] );
		}

		$this->statuses_to_attempt_capture = apply_filters( 'wc_vipps_recurring_captured_statuses', $capture_statuses );

		// if we change a status that is currently on-hold to any of the $capture_statuses we should attempt to capture it
		foreach ( $this->statuses_to_attempt_capture as $status ) {
			add_action( 'woocommerce_order_status_' . $status, [ $this, 'maybe_capture_payment' ] );
		}

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

		add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'maybe_process_redirect_order' ], 1 );

		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, [
			$this,
			'failing_payment_method'
		], 10, 2 );

		// When changing the payment method for a WooCommerce Subscription to Vipps, let WooCommerce Subscription
		// know that the payment method for that subscription should not be changed immediately. Instead, it should
		// wait for the go ahead in cron, after the user confirmed the payment method change with Vipps.
		add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', [
			$this,
			'indicate_async_payment_method_update'
		], 10, 2 );

		// action for updating a subscription's order items
		add_action( 'woocommerce_before_save_order_items', [
			$this,
			'save_subscription_order_items'
		], 10, 1 );
	}

	/**
	 * Indicate to WooCommerce Subscriptions that the payment method change for Vipps Recurring Payments
	 * should be asynchronous.
	 *
	 * WC_Subscriptions_Change_Payment_Gateway::change_payment_method_via_pay_shortcode uses the
	 * result to decide whether or not to change the payment method information on the subscription
	 * right away or not.
	 *
	 * In our case, the payment method will not be updated until after the user confirms the
	 * payment method change with Vipps. Once that's done, we'll take care of finishing
	 * the payment method update with the subscription.
	 *
	 * @param bool $should_update Current value of whether the payment method should be updated immediately.
	 * @param string $new_payment_method The new payment method name.
	 *
	 * @return bool Whether the subscription's payment method should be updated on checkout or async when a response is returned.
	 */
	public function indicate_async_payment_method_update( $should_update, $new_payment_method ): bool {
		if ( 'vipps_recurring' === $new_payment_method ) {
			$should_update = false;
		}

		return $should_update;
	}

	/**
	 * @param $original_order
	 * @param $new_renewal_order
	 */
	public function failing_payment_method( $original_order, $new_renewal_order ) {
		update_post_meta( $original_order->id, '_agreement_id', get_post_meta( $new_renewal_order->id, '_agreement_id', true ) );
	}

	/**
	 * @param $order_id
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function process_redirect_payment( $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) ) {
			return;
		}

		$order_status = $order->get_status();
		if ( in_array( $order_status, [ 'processing', 'completed' ] ) ) {
			return;
		}

		$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $order );
		if ( $payment_method !== $this->id ) {
			// If this is not the payment method, an agreement would not be available.
			return;
		}

		// check latest charge status
		$status = $this->check_charge_status( $order_id );

		$this->maybe_redirect_to_cancelled_order_page( $status );
	}

	/**
	 * @param $status
	 */
	public function maybe_redirect_to_cancelled_order_page( $status ) {
		if ( $status !== 'CANCELLED' ) {
			return;
		}

		// redirect to checkout or cancelled page?
		$page = $this->ensure_cancelled_order_page();
		wp_redirect( $page->guid . '?vipps_recurring_order_cancelled=true' );
	}

	/**
	 * @return array|WC_Vipps_Recurring_Exception|WP_Post|null
	 */
	public function ensure_cancelled_order_page() {
		if ( $this->cancelled_order_page ) {
			$page = get_post( $this->cancelled_order_page );

			if ( $page ) {
				return $page;
			}

			if ( ! $page ) {
				$this->update_option( 'cancelled_order_page', 0 );
			}
		}

		/**
		 * Create page
		 */

		// Determine what author to use by the currently logged in user
		$author = null;
		if ( current_user_can( 'manage_options' ) ) {
			$author = wp_get_current_user();
		}

		// If author is null it means it was not installed through the UI, wp-cli maybe
		// Set author to random administrator
		if ( ! $author ) {
			$all_admins = get_users( [
				'role' => 'administrator'
			] );

			if ( $all_admins ) {
				$all_admins = array_reverse( $all_admins );
				$author     = $all_admins[0];
			}
		}

		$author_id = 0;
		if ( $author ) {
			$author_id = $author->ID;
		}

		$content = __( 'It looks like you cancelled your order in Vipps. If this was a mistake you can try again by checking out again :)', 'woo-vipps-recurring' );

		$page_data = [
			'post_title'   => __( 'Cancelled Vipps Purchase', 'woo-vipps-recurring' ),
			'post_status'  => 'publish',
			'post_author'  => $author_id,
			'post_type'    => 'page',
			'post_content' => $content
		];

		$post_id = wp_insert_post( $page_data );

		if ( is_wp_error( $post_id ) ) {
			return new WC_Vipps_Recurring_Exception( __( 'Could not create or find the "Cancelled Vipps Purchase" page', 'woo-vipps-recurring' ) . ": " . $post_id->get_error_message() );
		}

		$this->update_option( 'cancelled_order_page', $post_id );

		return get_post( $post_id );
	}

	/**
	 * @param $order_id
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function maybe_process_redirect_order( $order_id ) {
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
		$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );

		if ( ! $agreement_id ) {
			return false;
		}

		$charge_id = WC_Vipps_Recurring_Helper::get_charge_id_from_order( $order );
		$charge    = false;

		if ( $charge_id ) {
			try {
				$charge = $this->api->get_charge( $agreement_id, $charge_id );
			} catch ( WC_Vipps_Recurring_Temporary_Exception $e ) {
				// do nothing, we're just being too quick
			} catch ( Exception $e ) {
				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Failed checking charge directly for charge: %s and agreement: %s. This might mean we have not set the right charge id somewhere. Finding latest charge instead.', WC_Vipps_Recurring_Helper::get_id( $order ), $charge_id, $agreement_id ) );

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
			}
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
		$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );

		if ( ! $agreement_id ) {
			return false;
		}

		return $this->api->get_agreement( $agreement_id );
	}

	/**
	 * @param $order
	 */
	public function unlock_order( $order ) {
		WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_locked_for_update_time', 0 );
		$order->save();
	}

	/**
	 * @param $order_id
	 *
	 * @return string
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function check_charge_status( $order_id ): string {
		if ( empty( $order_id ) || absint( $order_id ) <= 0 ) {
			return 'INVALID';
		}

		$order = wc_get_order( absint( $order_id ) );

		$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $order );
		if ( $payment_method !== $this->id ) {
			// if this is not the payment method, an agreement would not be available.
			return 'INVALID';
		}

		// check if order is temporarily locked
		clean_post_cache( WC_Vipps_Recurring_Helper::get_id( $order ) );

		// hold on to the lock for 15 seconds
		$lock = (int) WC_Vipps_Recurring_Helper::get_meta( $order, '_vipps_recurring_locked_for_update_time' );
		if ( $lock && $lock > time() - 15 ) {
			return 'SUCCESS';
		}

		// lock the order
		WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_locked_for_update_time', time() );
		$order->save();

		$agreement = $this->get_agreement_from_order( $order );
		$charge    = $this->get_latest_charge_from_order( $order );

		if ( ! $charge ) {
			// we're being rate limited
			return 'SUCCESS';
		} else {
			// set _charge_id on order
			WC_Vipps_Recurring_Helper::update_meta_data( $order, '_charge_id', $charge['id'] );
		}

		$initial        = empty( WC_Vipps_Recurring_Helper::get_meta( $order, '_vipps_recurring_initial' ) ) && ! wcs_order_contains_renewal( $order );
		$pending_charge = $initial ? 1 : (int) WC_Vipps_Recurring_Helper::get_meta( $order, '_vipps_recurring_pending_charge' );

		// if there's a campaign with a price of 0 we can complete the order immediately
		if ( $agreement['status'] === 'ACTIVE' && WC_Vipps_Recurring_Helper::get_meta( $order, '_vipps_recurring_zero_amount' ) && ! wcs_order_contains_renewal( $order ) ) {
			$this->complete_order( $order, $agreement['id'] );

			$order->add_order_note( __( 'The subtotal is zero, the order is free for this subscription period.', 'woo-vipps-recurring' ) );
			$order->save();

			return 'SUCCESS';
		}

		// If payment has already been captured, this function is redundant.
		if ( ! $pending_charge ) {
			$this->unlock_order( $order );

			return 'SUCCESS';
		}

		$is_captured = $charge !== false && $charge['status'] !== 'RESERVED';
		WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_captured', $is_captured );

		if ( (int) $initial ) {
			WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_initial', true );
			WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_pending_charge', true );
		}

		$this->unlock_order( $order );

		$order->save();
		clean_post_cache( WC_Vipps_Recurring_Helper::get_id( $order ) );

		// agreement is expired or stopped
		if ( in_array( $agreement['status'], [ 'STOPPED', 'EXPIRED' ] ) ) {
			$order->update_status( 'cancelled', __( 'The agreement was cancelled or expired in Vipps', 'woo-vipps-recurring' ) );

			WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_pending_charge', false );
			$order->save();

			// cancel charge
			if ( $charge && in_array( $charge['status'], [ 'DUE', 'PENDING' ] ) ) {
				$this->api->cancel_charge( $agreement['id'], $charge['id'] );
			}

			return 'CANCELLED';
		}

		$this->process_order_charge( $order, $charge );

		return 'SUCCESS';
	}

	/**
	 * @param $order
	 * @param $transaction_id
	 */
	public function complete_order( $order, $transaction_id ) {
		// $order->payment_complete() does not always assign a transaction id
		// specifically if the order's status is already "processing", so we should make sure it sets it
		WC_Vipps_Recurring_Helper::set_transaction_id_for_order( $order, $transaction_id );

		$order->payment_complete( $transaction_id );

		// controlled by the `transition_renewals_to_completed` setting
		// only applicable to renewal orders
		if ( $this->transition_renewals_to_completed && wcs_order_contains_renewal( $order ) ) {
			$order->update_status( 'wc-completed' );
		}

		// unlock the order and make sure we tell our cronjob to not periodically check the status of this
		// order anymore
		WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_pending_charge', false );
		$this->unlock_order( $order );
	}

	/**
	 * @param $order
	 * @param $charge
	 */
	public function process_order_charge( $order, $charge ) {
		if ( ! $charge ) {
			// No charge
			return;
		}

		// If payment has already been completed, this function is redundant.
		if ( ! WC_Vipps_Recurring_Helper::get_meta( $order, '_vipps_recurring_pending_charge' ) ) {
			return;
		}

		WC_Vipps_Recurring_Helper::update_meta_data( $order, '_charge_id', $charge['id'] );
		$transaction_id = WC_Vipps_Recurring_Helper::get_transaction_id_for_order( $order );

		// Reduce stock
		$reduce_stock = 'CHARGED' === $charge['status'] || in_array( $charge['status'], [ 'DUE', 'PENDING' ] );
		if ( $reduce_stock ) {
			$order_stock_reduced = WC_Vipps_Recurring_Helper::is_stock_reduced_for_order( $order );

			if ( ! $order_stock_reduced ) {
				WC_Vipps_Recurring_Helper::reduce_stock_for_order( $order );
			}
		}

		// status: CHARGED
		if ( 'CHARGED' === $charge['status'] ) {
			$this->complete_order( $order, $charge['id'] );

			/* translators: Vipps Charge ID */
			$message = sprintf( __( 'Vipps charge completed (Charge ID: %s)', 'woo-vipps-recurring' ), $charge['id'] );
			$order->add_order_note( $message );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Completed order for charge: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge['id'] ) );
		}

		// status: RESERVED
		// not auto captured, so we need to put the order status to `$this->default_reserved_charge_status`
		if ( ! $transaction_id && $charge['status'] === 'RESERVED'
			 && ! wcs_order_contains_renewal( $order ) ) {
			WC_Vipps_Recurring_Helper::set_transaction_id_for_order( $order, $charge['id'] );

			$message = __( 'Vipps awaiting manual capture', 'woo-vipps-recurring' );
			$order->update_status( $this->default_reserved_charge_status, $message );
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge reserved: %s (%s)', WC_Vipps_Recurring_Helper::get_id( $order ), $charge['id'], $charge['status'] ) );
		}

		// status: DUE or PENDING
		// when DUE we need to check that it becomes another status in a cron
		if ( ! $transaction_id && ( $charge['status'] === 'DUE'
									|| ( $charge['status'] === 'PENDING'
										 && wcs_order_contains_renewal( $order ) ) ) ) {
			WC_Vipps_Recurring_Helper::set_transaction_id_for_order( $order, $charge['id'] );

			WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_captured', true );

			$order->update_status( $this->default_renewal_status, $this->get_due_charge_note( $charge ) );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge due or pending: %s (%s)', WC_Vipps_Recurring_Helper::get_id( $order ), $charge['id'], $charge['status'] ) );
		}

		// status: CANCELLED
		if ( 'CANCELLED' === $charge['status'] ) {
			$order->update_status( 'cancelled', __( 'Vipps payment cancelled.', 'woo-vipps-recurring' ) );
			WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_pending_charge', false );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge cancelled: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge['id'] ) );
		}

		// status: FAILED
		if ( 'FAILED' === $charge['status'] && $this->can_fail_order( $order ) ) {
			$order->update_status( 'failed', __( 'Vipps payment failed.', 'woo-vipps-recurring' ) );
			WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_pending_charge', false );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge failed: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge['id'] ) );
		}

		$order->save();
	}

	/**
	 * We should only mark an initialCharge as failed if the agreement is "STOPPED" or "EXPIRED"
	 *
	 * An order is also failable if it's a renewal order
	 *
	 * @param $order
	 *
	 * @return bool
	 */
	public function can_fail_order( $order ): bool {
		$agreement = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );

		return wcs_order_contains_renewal( $order )
			   || in_array( $agreement['status'], [ 'STOPPED', 'EXPIRED' ] );
	}

	/**
	 * Proceed with current request using new login session (to ensure consistent nonce).
	 *
	 * @param $cookie
	 */
	public function set_cookie_on_current_request( $cookie ) {
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
	 * @throws Exception
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order ) {
		$this->process_subscription_payment( $amount_to_charge, $order );
	}

	/**
	 * Triggered when cancelled, failed or refunded order
	 *
	 * @param $subscription
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function cancel_subscription( $subscription ) {
		$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $subscription );
		if ( $payment_method !== $this->id ) {
			// If this is not the payment method, an agreement would not be available.
			return;
		}

		$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $subscription );
		$agreement    = $this->api->get_agreement( $agreement_id );

		if ( $agreement['status'] === 'ACTIVE' ) {
			$this->api->cancel_agreement( $agreement_id );
		}

		WC_Vipps_Recurring_Logger::log( sprintf( '[%s] cancel_subscription for agreement: %s', WC_Vipps_Recurring_Helper::get_id( $subscription ), $agreement_id ) );
	}

	/**
	 * @param int $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order        = wc_get_order( $order_id );
		$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );
		$charge_id    = WC_Vipps_Recurring_Helper::get_charge_id_from_order( $order );

		try {
			if ( $amount !== null ) {
				$amount = WC_Vipps_Recurring_Helper::get_vipps_amount( $amount );
			}

			$this->api->refund_charge( $agreement_id, $charge_id, $amount, $reason );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_refund for charge: %s and agreement: %s', $order_id, $charge_id, $agreement_id ) );

			return true;
		} catch ( WC_Vipps_Recurring_Temporary_Exception $e ) {
			$msg = __( 'A temporary error occurred when refunding a payment through Vipps. Please ensure the order is refunded manually or reset the order to "Processing" and try again.', 'woo-vipps-recurring' );
			throw new Exception( $msg );
		} catch ( WC_Vipps_Recurring_Exception $e ) {
			// attempt to cancel charge instead
			if ( (float) $order->get_remaining_refund_amount() === 0.00 ) {
				$this->api->cancel_charge( $agreement_id, $charge_id );

				WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_pending_charge', false );
				$order->save();

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_refund cancelled charge instead of refunding: %s and agreement: %s', $order_id, $charge_id, $agreement_id ) );

				return true;
			}

			// if the remaining refund amount is not equal to the amount we're trying to refund
			// that probably means we are trying to partially refund a charge that hasn't yet cleared
			if ( (float) $order->get_remaining_refund_amount() !== 0.00 ) {
				$err = __( 'You can not partially refund a pending or due Vipps charge. Please wait till the payment clears first or refund the full amount instead.', 'woo-vipps-recurring' );
				throw new Exception( $err );
			}

			$err = __( 'An unexpected error occurred while refunding a payment in Vipps.', 'woo-vipps-recurring' );
			throw new Exception( $err );
		}
	}

	/**
	 * @param $order
	 *
	 * @return mixed|void
	 */
	public function get_idempotence_key( $order ) {
		$idempotence_key = WC_Vipps_Recurring_Helper::get_meta( $order, '_idempotency_key' );

		if ( ! $idempotence_key ) {
			$idempotence_key = $this->api->generate_idempotency_key();
		}

		WC_Vipps_Recurring_Helper::update_meta_data( $order, '_idempotency_key', $idempotence_key );
		$order->save();

		return $idempotence_key;
	}

	/**
	 * @param $amount
	 * @param $renewal_order
	 *
	 * @return bool
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function process_subscription_payment( $amount, $renewal_order ): bool {
		try {
			// create charge logic
			$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $renewal_order );
			$agreement    = $this->api->get_agreement( $agreement_id );
			$amount       = WC_Vipps_Recurring_Helper::get_vipps_amount( $amount );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_subscription_payment agreement: %s', $renewal_order->get_id(), json_encode( $agreement ) ) );

			// idempotency key
			$idempotence_key = $this->get_idempotence_key( $renewal_order );

			$charge = $this->api->create_charge( $agreement, $renewal_order, $idempotence_key, $amount );

			WC_Vipps_Recurring_Helper::set_order_as_pending( $renewal_order, $charge['chargeId'] );
			$renewal_order->save();

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_subscription_payment created charge: %s', $renewal_order->get_id(), json_encode( $charge ) ) );
		} catch ( Exception $e ) {
			// if we reach this point we consider the error the be completely unrecoverable

			$renewal_order->update_status( 'failed' );

			/* translators: Error message */
			$message = sprintf( __( 'Failed creating a Vipps charge: %s', 'woo-vipps-recurring' ), $e->getMessage() );
			$renewal_order->add_order_note( $message );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Error in process_subscription_payment: %s', $renewal_order->get_id(), $e->getMessage() ) );

			throw new $e;
		}

		// if creating a charge was OK in the above code, we can assume we will have no further errors,
		// if we do they will at least not affect the rest of the logic or payment flow as the following is recoverable
		$charge = $this->api->get_charge( $agreement['id'], $charge['chargeId'] );

		WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_subscription_payment fetched charge: %s', $renewal_order->get_id(), json_encode( $charge ) ) );

		$this->process_order_charge( $renewal_order, $charge );

		WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_subscription_payment for charge: %s and agreement: %s', $renewal_order->get_id(), $charge['id'], $agreement['id'] ) );

		return true;
	}

	/**
	 * @param $charge
	 *
	 * @return string
	 */
	private function get_due_charge_note( $charge ): string {
		$timestamp_gmt   = WC_Vipps_Recurring_Helper::rfc_3999_date_to_unix( $charge['due'] );
		$date_to_display = ucfirst( wcs_get_human_time_diff( $timestamp_gmt ) );

		// translators: Vipps Charge ID, human diff timestamp
		return sprintf( __( 'Vipps charge created: %1$s. The charge will be complete %2$s.', 'woo-vipps-recurring' ), $charge['id'], strtolower( $date_to_display ) );
	}

	/**
	 * Maybe capture a payment if it has not already been captured
	 *
	 * @param $order_id
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function maybe_capture_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( wcs_order_contains_renewal( $order )
			 || (int) WC_Vipps_Recurring_Helper::get_meta( $order, '_vipps_recurring_pending_charge' ) !== 1
			 || (int) WC_Vipps_Recurring_Helper::is_charge_captured_for_order( $order ) === 1
			 || (int) WC_Vipps_Recurring_Helper::get_meta( $order, '_vipps_recurring_zero_amount' ) ) {
			return;
		}

		$this->capture_payment( $order );
	}

	/**
	 * Capture an initial payment manually
	 *
	 * @param $order
	 *
	 * @return bool
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function capture_payment( $order ): bool {
		$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );

		try {
			$agreement = $this->api->get_agreement( $agreement_id );

			// idempotency key
			$idempotency_key = $this->get_idempotence_key( $order );

			$charges = $this->api->get_charges_for( $agreement['id'] );

			// if a charge exists and the status is RESERVED we need to simply capture it
			$charges_amount = count( $charges );

			// if there is a charge we should never create_charge
			$charge = $charges_amount > 0;

			if ( $charges_amount > 0 ) {
				$latest_charge = $charges[ array_key_last( $charges ) ];

				if ( $latest_charge['status'] === 'RESERVED' ) {
					$charge = $this->capture_reserved_charge( $latest_charge, $agreement, $order, $idempotency_key );
				}
			}

			// if a charge was never captured we need to create one
			if ( ! $charge ) {
				$charge = $this->create_charge( $agreement, $order, $idempotency_key );
				$charge = $this->api->get_charge( $agreement['id'], $charge['chargeId'] );
			}

			WC_Vipps_Recurring_Helper::set_order_as_pending( $order, $charge['id'] );
			$order->save();

			$this->process_order_charge( $order, $charge );
			clean_post_cache( WC_Vipps_Recurring_Helper::get_id( $order ) );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Finished running capture_payment successfully', WC_Vipps_Recurring_Helper::get_id( $order ) ) );

			return true;
		} catch ( WC_Vipps_Recurring_Temporary_Exception $e ) {
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Temporary error in capture_payment: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $e->getMessage() ) );
			$this->admin_error( __( 'Vipps is temporarily unavailable.', 'woo-vipps-recurring' ) );

			return false;
		} catch ( WC_Vipps_Recurring_Config_Exception $e ) {
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Configuration error in capture_payment: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $e->getMessage() ) );
			$this->admin_error( $e->getMessage() );

			return false;
		}
	}

	/**
	 * @param $charge
	 * @param $agreement
	 * @param $order
	 * @param $idempotency_key
	 *
	 * @return false|mixed|string|null
	 */
	public function capture_reserved_charge( $charge, $agreement, $order, $idempotency_key ) {
		WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Attempting to capture reserve charge: %s for agreement: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge['id'], $agreement['id'] ) );

		// capture reserved charge
		try {
			$this->api->capture_reserved_charge( $agreement, $charge, $idempotency_key );

			// get charge
			$charge = $this->api->get_charge( $agreement['id'], $charge['id'] );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Captured reserve charge: %s for agreement: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge['id'], $agreement['id'] ) );

			return $charge;
		} catch ( WC_Vipps_Recurring_Temporary_Exception $e ) {
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Temporary error when capturing reserved payment in capture_reserved_charge: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $e->getMessage() ) );
			$this->admin_error( __( 'Vipps is temporarily unavailable.', 'woo-vipps-recurring' ) );

			return false;
		} catch ( Exception $e ) {
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Error when capturing reserved payment in capture_reserved_charge: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $e->getMessage() ) );

			/* translators: %s order id */
			$this->admin_error( sprintf( __( 'Could not capture Vipps payment for order id: %s', 'woo-vipps-recurring' ), WC_Vipps_Recurring_Helper::get_id( $order ) ) );

			return false;
		}
	}

	/**
	 * Creates a charge on an agreement, order and idempotency_key
	 *
	 * @param $agreement
	 * @param $order
	 * @param $idempotency_key
	 *
	 * @return bool
	 */
	public function create_charge( $agreement, $order, $idempotency_key ) {
		try {
			$charge = $this->api->create_charge( $agreement, $order, $idempotency_key );

			// add due charge note
			$charge = $this->api->get_charge( $agreement['id'], $charge['chargeId'] );
			$order->add_order_note( $this->get_due_charge_note( $charge ) );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Created charge: %s for agreement: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge['id'], $agreement['id'] ) );

			return true;
		} catch ( Exception $e ) {
			// mark charge as failed
			WC_Vipps_Recurring_Helper::set_order_as_not_pending( $order );
			$order->update_status( 'failed', __( 'Vipps failed to create charge', 'woo-vipps-recurring' ) );
			$order->save();

			/* translators: %s order id */
			$this->admin_error( sprintf( __( 'Could not capture Vipps payment for order id: %s', 'woo-vipps-recurring' ), WC_Vipps_Recurring_Helper::get_id( $order ) ) );
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Error in capture_payment when creating charge: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $e->getMessage() ) );

			return false;
		}
	}

	/**
	 * @param $order
	 *
	 * @return array
	 */
	public function get_subscriptions_for_order( $order ): array {
		return wcs_order_contains_renewal( $order ) ? wcs_get_subscriptions_for_renewal_order( $order ) : wcs_get_subscriptions_for_order( $order );
	}

	/**
	 * @param $subscription_id
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function maybe_process_gateway_change( $subscription_id ) {
		$subscription = wcs_get_subscription( $subscription_id );

		if ( $subscription->meta_exists( '_new_agreement_id' ) ) {
			$new_agreement_id = WC_Vipps_Recurring_Helper::get_meta( $subscription, '_new_agreement_id' );
			$agreement        = $this->api->get_agreement( $new_agreement_id );

			if ( $agreement['status'] === 'ACTIVE' ) {
				$old_agreement_id = WC_Vipps_Recurring_Helper::get_meta( $subscription, '_old_agreement_id' );

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Processing subscription gateway change with new agreement id: %s and old agreement id: %s', WC_Vipps_Recurring_Helper::get_id( $subscription ), $new_agreement_id, $old_agreement_id ) );
				if ( ! empty( $old_agreement_id ) && $new_agreement_id !== $old_agreement_id ) {
					WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Cancelling old agreement id: %s in Vipps due to gateway change', WC_Vipps_Recurring_Helper::get_id( $subscription ), $old_agreement_id ) );
					$this->api->cancel_agreement( $old_agreement_id );
				}

				WC_Vipps_Recurring_Helper::update_meta_data( $subscription, '_agreement_id', $new_agreement_id );

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Subscription gateway change completed', WC_Vipps_Recurring_Helper::get_id( $subscription ) ) );
				WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $subscription, $this->id );
			}

			if ( in_array( $agreement['status'], [ 'STOPPED', 'EXPIRED' ] ) ) {
				$subscription->add_order_note( __( 'Payment gateway change request cancelled in Vipps', 'woo-vipps-recurring' ) );
			}

			if ( in_array( $agreement['status'], [ 'STOPPED', 'EXPIRED', 'ACTIVE' ] ) ) {
				$subscription->delete_meta_data( '_vipps_recurring_waiting_for_gateway_change' );
				$subscription->delete_meta_data( '_new_agreement_id' );
				$subscription->delete_meta_data( '_old_agreement_id' );
			}

			$subscription->save();
		} else {
			// this exists as there was previously an issue where this value was never unset
			$subscription->delete_meta_data( '_vipps_recurring_waiting_for_gateway_change' );
		}
	}

	/**
	 * @param $subscription_id
	 *
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function maybe_update_subscription_details_in_app( $subscription_id ) {
		$subscription = wcs_get_subscription( $subscription_id );

		$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $subscription );
		if ( $payment_method !== $this->id ) {
			// If this is not the payment method, an agreement would not be available.
			return;
		}

		$parent_order = $subscription->get_parent();

		$body = [
			'price' => WC_Vipps_Recurring_Helper::get_vipps_amount( $subscription->get_total() ),
//			'productName'        => $item->get_name(),
//			'productDescription' => $item->get_name()
		];

		$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $subscription );
		if ( empty( $agreement_id ) ) {
			$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $parent_order );
		}

		if ( $agreement_id ) {
			$this->api->update_agreement( $agreement_id, $body );
		}

		$subscription->delete_meta_data( '_vipps_recurring_update_in_app' );
		$subscription->save();
	}

	/**
	 * Process a payment when checking out in WooCommerce
	 *
	 * @param int $order_id
	 * @param bool $retry
	 * @param false $previous_error
	 *
	 * @return array|string[]
	 * @throws Exception
	 */
	public function process_payment( $order_id, $retry = true, $previous_error = false ) {
		$is_gateway_change = wcs_is_subscription( $order_id );

		$order     = wc_get_order( $order_id );
		$debug_msg = sprintf( '[%s] process_payment (gateway change: %s)', $order_id, $is_gateway_change ? 'Yes' : 'No' ) . "\n";

		try {
			$subscription = $order;

			if ( ! $is_gateway_change ) {
				$subscriptions = $this->get_subscriptions_for_order( $order );

				// we can only ever have one subscription as long as 'multiple_subscriptions' is disabled
				$subscription = $subscriptions[ array_key_first( $subscriptions ) ];
			}

			// if this order has a PENDING or ACTIVE agreement in Vipps we should not allow checkout anymore
			// this will prevent duplicate transactions
			if ( $agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order ) ) {
				$agreement = $this->get_agreement_from_order( $order );

				if ( $agreement['status'] === 'ACTIVE' ) {
					throw new WC_Vipps_Recurring_Temporary_Exception( __( 'This subscription is already active in Vipps. You can leave this page.', 'woo-vipps-recurring' ) );
				}

				if ( $agreement['status'] === 'PENDING' ) {
					throw new WC_Vipps_Recurring_Temporary_Exception( __( 'There is a pending agreement on this order. Check the Vipps app or wait and try again in a few minutes.', 'woo-vipps-recurring' ) );
				}
			}

			$period   = $subscription->get_billing_period();
			$interval = $subscription->get_billing_interval();

			$items = array_reverse( $order->get_items() );

			// we can only ever have one subscription as long as 'multiple_subscriptions' is disabled
			$item    = array_pop( $items );
			$product = $item->get_product();

			$is_virtual     = $product->is_virtual();
			$direct_capture = $product->get_meta( '_vipps_recurring_direct_capture' ) === 'yes';

			$agreement_url = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
			$redirect_url  = $this->get_return_url( $order );

			$agreement_body = [
				'currency'             => $order->get_currency(),
				'price'                => WC_Vipps_Recurring_Helper::get_vipps_amount( $subscription->get_total() ),
				'interval'             => strtoupper( $period ),
				'intervalCount'        => (int) $interval,
				'productName'          => $item->get_name(),
				'productDescription'   => $item->get_name(),
				'isApp'                => false,
				'merchantAgreementUrl' => $agreement_url,
				'merchantRedirectUrl'  => $redirect_url,
			];

			// validate phone number and only add it if it's up to Vipps' standard to avoid errors
			if ( WC_Vipps_Recurring_Helper::is_valid_phone_number( $order->get_billing_phone() ) ) {
				$agreement_body['customerPhoneNumber'] = $order->get_billing_phone();
			}

			$is_zero_amount      = (int) $order->get_total() === 0 || $is_gateway_change;
			$capture_immediately = $is_virtual || $direct_capture;

			if ( ! $is_zero_amount ) {
				$agreement_body = array_merge( $agreement_body, [
					'initialCharge' => [
						'amount'          => WC_Vipps_Recurring_Helper::get_vipps_amount( $order->get_total() ),
						'currency'        => $order->get_currency(),
						'description'     => $item->get_name(),
						'transactionType' => $capture_immediately ? 'DIRECT_CAPTURE' : 'RESERVE_CAPTURE',
					],
				] );

				if ( ! $capture_immediately ) {
					WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_reserved_capture', true );
				}
			}

			if ( $is_zero_amount || (float) $order->get_total_discount() !== 0.00 ) {
				$start_date   = new DateTime( '@' . $subscription->get_time( 'start' ) );
				$next_payment = new DateTime( '@' . $subscription->get_time( 'next_payment' ) );

				$agreement_body['campaign'] = [
					'start'         => WC_Vipps_Recurring_Helper::get_rfc_3999_date( $start_date ),
					'end'           => WC_Vipps_Recurring_Helper::get_rfc_3999_date( $next_payment ),
					'campaignPrice' => WC_Vipps_Recurring_Helper::get_vipps_amount( $order->get_total() ),
				];
			}

			$response = $this->api->create_agreement( $agreement_body );

			if ( $is_gateway_change ) {
				/* translators: Vipps Agreement ID */
				$message = sprintf( __( 'Request to change gateway to Vipps with agreement ID: %s. Customer sent to Vipps for confirmation.', 'woo-vipps-recurring' ), $response['agreementId'] );

				$subscription->add_order_note( $message );
				WC_Vipps_Recurring_Helper::update_meta_data( $subscription, '_old_agreement_id', WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $subscription ) );
				WC_Vipps_Recurring_Helper::update_meta_data( $subscription, '_new_agreement_id', $response['agreementId'] );
				WC_Vipps_Recurring_Helper::update_meta_data( $subscription, '_vipps_recurring_waiting_for_gateway_change', true );

				$debug_msg .= 'Request to change gateway to Vipps' . "\n";
			} else {
				WC_Vipps_Recurring_Helper::update_meta_data( $subscription, '_agreement_id', $response['agreementId'] );
				WC_Vipps_Recurring_Helper::update_meta_data( $order, '_agreement_id', $response['agreementId'] );
				WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_pending_charge', true );

				if ( $is_zero_amount ) {
					WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_zero_amount', true );
				}

				/* translators: Vipps Agreement ID */
				$message = sprintf( __( 'Vipps agreement created: %s. Customer sent to Vipps for confirmation.', 'woo-vipps-recurring' ), $response['agreementId'] );
				$order->add_order_note( $message );

				$debug_msg .= sprintf( 'Created agreement with agreement ID: %s', $response['agreementId'] ) . "\n";
			}

			if ( isset( $response['chargeId'] ) ) {
				WC_Vipps_Recurring_Helper::update_meta_data( $order, '_charge_id', $response['chargeId'] );
			}

			// save meta
			$order->save();
			$subscription->save();

			$debug_msg .= sprintf( 'Debug body: %s', json_encode( $agreement_body ) ) . "\n";
			$debug_msg .= sprintf( 'Debug response: %s', json_encode( array_merge( $response, [ 'vippsConfirmationUrl' => 'redacted' ] ) ) );
			WC_Vipps_Recurring_Logger::log( $debug_msg );

			return [
				'result'   => 'success',
				'redirect' => $response['vippsConfirmationUrl'],
			];
		} catch ( WC_Vipps_Recurring_Temporary_Exception $e ) {
			$this->admin_error( $e->getMessage() );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Error in process_payment: %s', $order_id, $e->getMessage() ) );

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		} catch ( WC_Vipps_Recurring_Exception $e ) {
			$this->admin_error( $e->getLocalizedMessage() );

			$order->update_status( 'failed' );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Error in process_payment: %s', $order_id, $e->getMessage() ) );

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
	 * @param $key
	 * @param $data
	 *
	 * @return false|string
	 */
	public function generate_page_dropdown_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = [
			'title'            => '',
			'class'            => '',
			'type'             => 'page_dropdown',
			'desc_tip'         => false,
			'description'      => '',
			'show_option_none' => ''
		];

		$data = wp_parse_args( $data, $defaults );

		$dropdown_pages = wp_dropdown_pages( [
			'echo'             => 0,
			'show_option_none' => $data['show_option_none'],
			'selected'         => $this->get_option( $key ),
			'id'               => $field_key,
			'name'             => $field_key,
			'class'            => $data['class']
		] );

		ob_start();
		?>
		<tr valign="top">
			<th
				scope="row"
				class="titledesc"
			>
				<label
					for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span>
					</legend>
					<?php echo $dropdown_pages; ?>
					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = require( __DIR__ . '/admin/vipps-recurring-settings.php' );
	}

	/**
	 * @param $what
	 */
	private function admin_error( $what ) {
		wc_add_notice( $what, 'error' );
	}

	/**
	 * @param $subscription_id
	 */
	public function save_subscription_order_items( $subscription_id ) {
		$post = get_post( $subscription_id );

		if ( 'shop_subscription' === get_post_type( $post ) ) {
			$subscription = wcs_get_subscription( $subscription_id );
			WC_Vipps_Recurring_Helper::update_meta_data( $subscription, '_vipps_recurring_update_in_app', 1 );
			$subscription->save();
		}
	}
}
