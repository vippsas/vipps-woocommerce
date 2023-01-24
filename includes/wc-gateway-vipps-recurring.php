<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payment_Gateway' ) ) {
	require_once( __DIR__ . '/wc-vipps-models.php' );
	require_once( __DIR__ . '/wc-vipps-recurring-api.php' );

	/**
	 * WC_Gateway_Vipps class.
	 *
	 * @extends WC_Payment_Gateway
	 */
	class WC_Gateway_Vipps_Recurring extends WC_Payment_Gateway {
		/**
		 * API access secret key
		 */
		public string $secret_key;

		/**
		 * Api access client id
		 */
		public string $client_id;

		/**
		 * Api access subscription key
		 */
		public string $subscription_key;

		/**
		 * Is test mode active?
		 */
		public bool $test_mode;

		/**
		 * The Vipps API url
		 */
		public string $api_url;

		/**
		 * The page to redirect to for cancelled orders
		 */
		public int $cancelled_order_page;

		/**
		 * The default status to give pending renewals
		 */
		public string $default_renewal_status;

		/**
		 * The default status pending orders that have yet to be captured (reserved charges in Vipps) should be given
		 */
		public string $default_reserved_charge_status;

		/**
		 * Status where when transitioned to we will attempt to capture the payment
		 */
		public array $statuses_to_attempt_capture;

		/**
		 * Transition the order status to 'completed' when a renewal order has been charged successfully
		 * regardless of previous status
		 */
		public string $transition_renewals_to_completed;

		/**
		 * The amount of charges to check in wp-cron at a time
		 */
		public int $check_charges_amount;

		/**
		 * The sort order in which we check charges in wp-cron
		 */
		public string $check_charges_sort_order;

		/**
		 * The reference the *Singleton* instance of this class
		 */
		private static ?WC_Gateway_Vipps_Recurring $instance = null;

		private WC_Vipps_Recurring_Api $api;

		/**
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
				'subscription_payment_method_change_admin',
			];

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			$this->title                            = $this->get_option( 'title' );
			$this->description                      = $this->get_option( 'description' );
			$this->enabled                          = $this->get_option( 'enabled' );
			$this->test_mode                        = WC_VIPPS_RECURRING_TEST_MODE;
			$this->secret_key                       = $this->get_option( 'secret_key' );
			$this->client_id                        = $this->get_option( 'client_id' );
			$this->subscription_key                 = $this->get_option( 'subscription_key' );
			$this->cancelled_order_page             = $this->get_option( 'cancelled_order_page' );
			$this->default_renewal_status           = $this->get_option( 'default_renewal_status' );
			$this->default_reserved_charge_status   = $this->get_option( 'default_reserved_charge_status' );
			$this->transition_renewals_to_completed = $this->get_option( 'transition_renewals_to_completed' );
			$this->check_charges_amount             = $this->get_option( 'check_charges_amount' );
			$this->check_charges_sort_order         = $this->get_option( 'check_charges_sort_order' );
			$this->order_button_text                = __( 'Pay with Vipps', 'woo-vipps-recurring' );

			$this->api_url = $this->test_mode ? 'https://apitest.vipps.no' : 'https://api.vipps.no';
			$this->api     = new WC_Vipps_Recurring_Api( $this );

			/*
			 * When transitioning an order to these statuses we should
			 * automatically try to capture the charge if it's not already captured
			 */
			$capture_statuses = [
				'completed',
				'processing'
			];

			/*
			 * We have to remove the status corresponding to `$this->default_reserved_charge_status` otherwise we end up
			 * prematurely capturing this reserved Vipps charge
			 */
			$capture_status_transition_id = array_search( str_replace( 'wc-', '', $this->default_reserved_charge_status ), $capture_statuses, true );
			if ( $capture_status_transition_id ) {
				unset( $capture_statuses[ $capture_status_transition_id ] );
			}

			$this->statuses_to_attempt_capture = apply_filters( 'wc_vipps_recurring_captured_statuses', $capture_statuses );

			// If we change a status that is currently on-hold to any of the $capture_statuses we should attempt to capture it
			foreach ( $this->statuses_to_attempt_capture as $status ) {
				add_action( 'woocommerce_order_status_' . $status, [ $this, 'maybe_capture_payment' ] );
			}

			add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', [
				$this,
				'append_valid_statuses_for_payment_complete'
			] );

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
				'update_failing_payment_method'
			], 10, 2 );

			/*
			 * When changing the payment method for a WooCommerce Subscription to Vipps, let WooCommerce Subscription
			 * know that the payment method for that subscription should not be changed immediately. Instead, it should
			 * wait for the go ahead in cron, after the user confirmed the payment method change with Vipps.
			 */
			add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', [
				$this,
				'indicate_async_payment_method_update'
			], 10, 2 );

			// Action for updating a subscription's order items
			add_action( 'woocommerce_before_save_order_items', [
				$this,
				'save_subscription_order_items'
			], 10, 1 );

			// Tell WooCommerce about our custom payment meta fields
			add_action( 'woocommerce_subscription_payment_meta', [ $this, 'add_subscription_payment_meta' ], 10, 2 );

			// Validate custom payment meta fields
			add_action( 'woocommerce_subscription_validate_payment_meta', [
				$this,
				'validate_subscription_payment_meta'
			], 10, 2 );

			// Handle subscription switches (free upgrades & downgrades)
			add_action( 'woocommerce_subscriptions_switched_item', [ $this, 'handle_subscription_switches' ], 10, 1 );

			/*
			 * Handle in app updates when a subscription status changes, typically when status transitions to
			 * 'pending-cancel', 'cancelled' or 'pending-cancel' to any other status
			 */
			add_action( 'woocommerce_subscription_status_updated', [
				$this,
				'maybe_handle_subscription_status_transitions'
			], 10, 3 );

			// Delete idempotency key when renewal/resubscribe happens
			add_action( 'wcs_resubscribe_order_created', [ $this, 'delete_resubscribe_meta' ], 10 );
			add_action( 'wcs_renewal_order_created', [ $this, 'delete_renewal_meta' ], 10 );

			// Cancel DUE charge if order transitions to 'cancelled' or 'failed'
			$cancel_due_charge_statuses = apply_filters( 'wc_vipps_recurring_cancel_due_charge_statuses', [
				'cancelled',
				'failed'
			] );

			foreach ( $cancel_due_charge_statuses as $status ) {
				add_action( 'woocommerce_order_status_' . $status, [ $this, 'maybe_cancel_due_charge' ] );
			}

			add_action( 'woocommerce_payment_complete', [ $this, 'after_renew_early_from_another_gateway' ] );

			add_filter( 'woocommerce_payment_complete_order_status', [
				$this,
				'prevent_backwards_transition_on_completed_order'
			], 100, 3 );
		}

		/**
		 * Indicate to WooCommerce Subscriptions that the payment method change for Vipps Recurring Payments
		 * should be asynchronous.
		 *
		 * WC_Subscriptions_Change_Payment_Gateway::change_payment_method_via_pay_shortcode uses the
		 * result to decide whether to change the payment method information on the subscription
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
		public function indicate_async_payment_method_update( bool $should_update, string $new_payment_method ): bool {
			if ( 'vipps_recurring' === $new_payment_method ) {
				$should_update = false;
			}

			return $should_update;
		}

		/**
		 * @param $subscription
		 * @param $renewal_order
		 */
		public function update_failing_payment_method( $subscription, $renewal_order ): void {
			WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $renewal_order ) );
		}

		/**
		 * @param $order_id
		 *
		 * @throws WC_Vipps_Recurring_Exception
		 */
		public function process_redirect_payment( $order_id ): void {
			$order = wc_get_order( $order_id );
			if ( ! is_object( $order ) ) {
				return;
			}

			$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $order );
			if ( $payment_method !== $this->id ) {
				// If this is not the payment method, an agreement would not be available.
				return;
			}

			// check latest charge status
			$status = $this->check_charge_status( $order_id );

			WC_Vipps_Recurring_Logger::log( sprintf( "[%s] process_redirect_payment: charge status is: %s", $order_id, $status ) );

			$this->maybe_redirect_to_cancelled_order_page( $status );
		}

		/**
		 * @param $status
		 */
		public function maybe_redirect_to_cancelled_order_page( $status ): void {
			if ( $status !== 'CANCELLED' ) {
				return;
			}

			// redirect to check out, or cancelled page?
			$page = $this->ensure_cancelled_order_page();
			wp_redirect( $page->guid . '?vipps_recurring_order_cancelled=true' );
			exit;
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

				$this->update_option( 'cancelled_order_page', 0 );
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

			$author_id = $author->ID ?? 0;

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
		 * @return bool|WC_Vipps_Charge
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
					$charge = $this->get_latest_charge_for_agreement( $order, $agreement_id, $charge_id );
				}
			} else {
				$charge = $this->get_latest_charge_for_agreement( $order, $agreement_id, $charge_id );
			}

			return $charge;
		}

		/**
		 * @param object|WC_Order $order
		 * @param string $agreement_id
		 * @param string $charge_id
		 *
		 * @return false|null|WC_Vipps_Charge
		 * @throws WC_Vipps_Recurring_Config_Exception
		 * @throws WC_Vipps_Recurring_Exception
		 * @throws WC_Vipps_Recurring_Temporary_Exception
		 */
		public function get_latest_charge_for_agreement( $order, string $agreement_id, string $charge_id ) {
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

			return $charge;
		}

		/**
		 * Receives the agreement associated with an order
		 *
		 * @param object|WC_Order $order
		 *
		 * @return bool|WC_Vipps_Agreement
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
		 * @param object|WC_Order $order
		 */
		public function unlock_order( $order ): void {
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

			// we need to tell WooCommerce that this is in fact a scheduled payment that should be retried in the case of failure.
			if ( wcs_order_contains_renewal( $order ) ) {
				add_filter( 'wcs_is_scheduled_payment_attempt', '__return_true' );
			}

			// check if order is temporarily locked
			clean_post_cache( WC_Vipps_Recurring_Helper::get_id( $order ) );

			// hold on to the lock for 30 seconds
			$lock = (int) WC_Vipps_Recurring_Helper::get_meta( $order, '_vipps_recurring_locked_for_update_time' );
			if ( $lock && $lock > time() - 30 ) {
				return 'SUCCESS';
			}

			// lock the order
			WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_locked_for_update_time', time() );
			$order->save();

			$agreement = $this->get_agreement_from_order( $order );

			// logic for zero amounts when a charge does not exist
			if ( WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_ZERO_AMOUNT ) && ! wcs_order_contains_renewal( $order ) ) {
				// if there's a campaign with a price of 0 we can complete the order immediately
				if ( $agreement->status === WC_Vipps_Agreement::STATUS_ACTIVE ) {
					$this->complete_order( $order, $agreement->id );

					$order->add_order_note( __( 'The subtotal is zero, the order is free for this subscription period.', 'woo-vipps-recurring' ) );
					$order->save();
				}

				// if EXPIRED or STOPPED we can fail this order
				if ( in_array( $agreement->status, [
					WC_Vipps_Agreement::STATUS_EXPIRED,
					WC_Vipps_Agreement::STATUS_STOPPED
				], true ) ) {
					$this->check_charge_agreement_cancelled( $order, $agreement );

					return 'CANCELLED';
				}

				return 'SUCCESS';
			}

			$charge = $this->get_latest_charge_from_order( $order );

			if ( ! $charge ) {
				// we're being rate limited
				return 'SUCCESS';
			}

			// set _charge_id on order
			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID, $charge->id );

			// set _vipps_recurring_latest_api_status
			WC_Vipps_Recurring_Helper::set_latest_api_status_for_order( $order, $charge->status );

			$initial        = empty( WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_INITIAL ) ) && ! wcs_order_contains_renewal( $order );
			$pending_charge = $initial ? 1 : (int) WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING );
			$did_fail       = WC_Vipps_Recurring_Helper::is_charge_failed_for_order( $order );

			// If payment has already been captured, this function is redundant, unless the charge failed
			if ( ! $pending_charge && ! $did_fail ) {
				$this->unlock_order( $order );

				return 'SUCCESS';
			}

			$is_captured = ! in_array( $charge->status, [
				WC_Vipps_Charge::STATUS_PENDING,
				WC_Vipps_Charge::STATUS_RESERVED
			], true );

			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_CAPTURED, $is_captured );

			if ( (int) $initial ) {
				WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_INITIAL, true );
				WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, true );
			}

			$this->unlock_order( $order );

			$order->save();
			clean_post_cache( WC_Vipps_Recurring_Helper::get_id( $order ) );

			$this->process_order_charge( $order, $charge );

			// agreement is expired or stopped
			if ( in_array( $agreement->status, [
				WC_Vipps_Agreement::STATUS_STOPPED,
				WC_Vipps_Agreement::STATUS_EXPIRED
			], true ) ) {
				$this->check_charge_agreement_cancelled( $order, $agreement, $charge );

				return 'CANCELLED';
			}

			return 'SUCCESS';
		}

		/**
		 * @param $order
		 * @param WC_Vipps_Agreement $agreement
		 * @param bool|WC_Vipps_Charge $charge
		 *
		 * @throws WC_Vipps_Recurring_Config_Exception
		 * @throws WC_Vipps_Recurring_Exception
		 * @throws WC_Vipps_Recurring_Temporary_Exception
		 */
		public function check_charge_agreement_cancelled( $order, WC_Vipps_Agreement $agreement, $charge = false ): void {
			$order->update_status( 'cancelled', __( 'The agreement was cancelled or expired in Vipps', 'woo-vipps-recurring' ) );

			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, false );
			$order->save();

			// cancel charge
			if ( $charge && in_array( $charge->status, [
					WC_Vipps_Charge::STATUS_DUE,
					WC_Vipps_Charge::STATUS_PENDING
				], true ) ) {
				$idempotency_key = $this->get_idempotency_key( $order );

				$this->api->cancel_charge( $agreement->id, $charge->id, $idempotency_key );
			}
		}

		/**
		 * @param $order
		 * @param $transaction_id
		 */
		public function complete_order( $order, $transaction_id ): void {
			$order->payment_complete( $transaction_id );

			// Controlled by the `transition_renewals_to_completed` setting
			// Only applicable to renewal orders
			if ( $this->transition_renewals_to_completed === "yes" && wcs_order_contains_renewal( $order ) ) {
				$order->update_status( 'wc-completed' );
			}

			// Unlock the order and make sure we tell our cronjob to stop periodically checking the status of this order
			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, false );
			$this->unlock_order( $order );
		}

		/**
		 * @param $order
		 * @param WC_Vipps_Charge|null $charge
		 */
		public function process_order_charge( $order, ?WC_Vipps_Charge $charge = null ): void {
			if ( ! $charge ) {
				// No charge
				return;
			}

			// If payment has already been completed, this function is redundant.
			if ( ! WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING ) ) {
				return;
			}

			do_action( 'wc_vipps_recurring_before_process_order_charge', $order );

			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID, $charge->id );
			$transaction_id = WC_Vipps_Recurring_Helper::get_transaction_id_for_order( $order );

			// Reduce stock
			$reduce_stock = $charge->status === WC_Vipps_Charge::STATUS_CHARGED || in_array( $charge->status, [
					WC_Vipps_Charge::STATUS_DUE,
					WC_Vipps_Charge::STATUS_PENDING
				], true );

			if ( $reduce_stock ) {
				$order_stock_reduced = WC_Vipps_Recurring_Helper::is_stock_reduced_for_order( $order );

				if ( ! $order_stock_reduced ) {
					WC_Vipps_Recurring_Helper::reduce_stock_for_order( $order );
				}
			}

			// status: CHARGED
			if ( $charge->status === WC_Vipps_Charge::STATUS_CHARGED ) {
				$this->complete_order( $order, $charge->id );

				/* translators: Vipps Charge ID */
				$message = sprintf( __( 'Vipps charge completed (Charge ID: %s)', 'woo-vipps-recurring' ), $charge->id );
				$order->add_order_note( $message );

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Completed order for charge: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id ) );
			}

			// status: RESERVED
			// not auto captured, so we need to put the order status to `$this->default_reserved_charge_status`
			if ( ! $transaction_id && $charge->status === WC_Vipps_Charge::STATUS_RESERVED
				 && ! wcs_order_contains_renewal( $order ) ) {
				WC_Vipps_Recurring_Helper::set_transaction_id_for_order( $order, $charge->id );

				$message = __( 'Vipps awaiting manual capture', 'woo-vipps-recurring' );
				$order->update_status( $this->default_reserved_charge_status, $message );
				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge reserved: %s (%s)', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id, $charge->status ) );
			}

			// status: DUE or PENDING
			// when DUE, we need to check that it becomes another status in a cron
			if ( ! $transaction_id && ( $charge->status === WC_Vipps_Charge::STATUS_DUE
										|| ( $charge->status === WC_Vipps_Charge::STATUS_PENDING
											 && wcs_order_contains_renewal( $order ) ) ) ) {
				WC_Vipps_Recurring_Helper::set_transaction_id_for_order( $order, $charge->id );

				WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_CAPTURED, true );

				$order->update_status( $this->default_renewal_status, $this->get_due_charge_note( $charge ) );

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge due or pending: %s (%s)', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id, $charge->status ) );
			}

			// status: CANCELLED
			if ( $charge->status === WC_Vipps_Charge::STATUS_CANCELLED ) {
				$order->update_status( 'cancelled', __( 'Vipps payment cancelled.', 'woo-vipps-recurring' ) );
				WC_Vipps_Recurring_Helper::set_order_as_not_pending( $order );

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge cancelled: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id ) );
			}

			// status: FAILED
			if ( $charge->status === WC_Vipps_Charge::STATUS_FAILED ) {
				$order->update_status( 'failed', __( 'Vipps payment failed.', 'woo-vipps-recurring' ) );
				WC_Vipps_Recurring_Helper::set_order_charge_failed( $order, $charge );

				// if subscription status is already pending-cancel, we should cancel it completely
				// this is not WooCommerce Subscription's job as this issue is caused by Vipps' internal retry logic
				$subscriptions = $this->get_subscriptions_for_order( $order );
				foreach ( $subscriptions as $subscription ) {
					if ( $subscription->get_status() !== 'pending-cancel' ) {
						continue;
					}

					$subscription->update_status( 'cancelled' );
				}

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge failed: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id ) );
			}

			// if status was FAILED, but no longer is
			if ( $charge->status !== WC_Vipps_Charge::STATUS_FAILED
				 && WC_Vipps_Recurring_Helper::is_charge_failed_for_order( $order )
				 && in_array( $charge->status, [ WC_Vipps_Charge::STATUS_PROCESSING, 'DUE', 'PENDING' ], true ) ) {
				WC_Vipps_Recurring_Helper::set_order_charge_not_failed( $order, $charge->id );
			}
		}

		/**
		 * Proceed with current request using new login session (to ensure consistent nonce).
		 *
		 * @param $cookie
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
		 * @return bool
		 *
		 * @throws Exception
		 */
		public function scheduled_subscription_payment( $amount_to_charge, $order ): bool {
			try {
				return $this->process_subscription_payment( $amount_to_charge, $order );
			} catch ( Exception $e ) {
				// if we reach this point we consider the error to be completely unrecoverable.
				WC_Vipps_Recurring_Helper::set_order_charge_failed( $order, new WC_Vipps_Charge() );
				$order->update_status( 'failed' );

				/* translators: Error message */
				$message = sprintf( __( 'Failed creating a Vipps charge: %s', 'woo-vipps-recurring' ), $e->getMessage() );
				$order->add_order_note( $message );

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Error in process_subscription_payment: %s', $order->get_id(), $e->getMessage() ) );

				return false;
			}
		}

		/**
		 * Triggered when cancelled, failed or refunded order
		 *
		 * @param $subscription
		 *
		 * @throws WC_Vipps_Recurring_Exception
		 */
		public function cancel_subscription( $subscription ): void {
			$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $subscription );
			if ( $payment_method !== $this->id ) {
				// If this is not the payment method, an agreement would not be available.
				return;
			}

			$subscription_id = WC_Vipps_Recurring_Helper::get_id( $subscription );

			if ( get_transient( 'cancel_subscription_lock' . $subscription_id ) ) {
				return;
			}

			set_transient( 'cancel_subscription_lock' . $subscription_id, uniqid( '', true ), 30 );

			$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $subscription );
			$agreement    = $this->api->get_agreement( $agreement_id );

			if ( $agreement->status === WC_Vipps_Agreement::STATUS_ACTIVE ) {
				$this->maybe_handle_subscription_status_transitions( $subscription, 'cancelled', 'active' );
				$this->maybe_update_subscription_details_in_app( WC_Vipps_Recurring_Helper::get_id( $subscription ) );

				$idempotency_key = $this->get_idempotency_key( $subscription );
				$this->api->cancel_agreement( $agreement_id, $idempotency_key );
			}

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] cancel_subscription for agreement: %s', $subscription_id, $agreement_id ) );
		}

		/**
		 * @param int $order_id
		 * @param null $amount
		 * @param string $reason
		 *
		 * @throws Exception
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ): ?bool {
			$order        = wc_get_order( $order_id );
			$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );
			$charge_id    = WC_Vipps_Recurring_Helper::get_charge_id_from_order( $order );

			$created = $order->get_date_created( null );
			if ( $created ) {
				$diff = ( new DateTime() )->diff( $created );

				if ( $diff->days > 365 ) {
					/* translators: %s is the days as an integer since the order was created */
					$err = sprintf( __( 'You cannot refund a Vipps charge that was made more than 365 days ago. This order was created %s days ago.', 'woo-vipps-recurring' ), $diff->days );
					throw new \RuntimeException( $err );
				}
			}

			try {
				if ( $amount !== null ) {
					$amount = WC_Vipps_Recurring_Helper::get_vipps_amount( $amount );
				}

				$this->api->refund_charge( $agreement_id, $charge_id, $amount, $reason );

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_refund for charge: %s and agreement: %s', $order_id, $charge_id, $agreement_id ) );

				return true;
			} catch ( WC_Vipps_Recurring_Temporary_Exception $e ) {
				$msg = __( 'A temporary error occurred when refunding a payment through Vipps. Please ensure the order is refunded manually or reset the order to "Processing" and try again.', 'woo-vipps-recurring' );
				throw new \RuntimeException( $msg );
			} catch ( WC_Vipps_Recurring_Exception $e ) {
				// attempt to cancel charge instead
				if ( (float) $order->get_remaining_refund_amount() === 0.00 ) {
					$idempotency_key = $this->get_idempotency_key( $order );

					$this->api->cancel_charge( $agreement_id, $charge_id, $idempotency_key );

					WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, false );
					$order->save();

					WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_refund cancelled charge instead of refunding: %s and agreement: %s', $order_id, $charge_id, $agreement_id ) );

					return true;
				}

				// if the remaining refund amount is not equal to the amount we're trying to refund
				// that probably means we are trying to partially refund a charge that hasn't yet cleared
				if ( (float) $order->get_remaining_refund_amount() !== 0.00 ) {
					$err = __( 'You can not partially refund a pending or due Vipps charge. Please wait till the payment clears first or refund the full amount instead.', 'woo-vipps-recurring' );
					throw new \RuntimeException( $err );
				}

				$err = __( 'An unexpected error occurred while refunding a payment in Vipps.', 'woo-vipps-recurring' );
				throw new \RuntimeException( $err );
			}
		}

		/**
		 * @param $order
		 *
		 * @return mixed|string
		 */
		public function get_idempotency_key( $order ) {
			$idempotence_key = WC_Vipps_Recurring_Helper::get_meta( $order, '_idempotency_key' );

			if ( ! $idempotence_key ) {
				$idempotence_key = $this->generate_idempotency_key( $order );
			}

			return $idempotence_key;
		}

		/**
		 * @param $order
		 *
		 * @return string
		 */
		protected function generate_idempotency_key( $order ): string {
			$idempotence_key = $this->api->generate_idempotency_key();

			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_IDEMPOTENCY_KEY, $idempotence_key );
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
			$renewal_order_id = WC_Vipps_Recurring_Helper::get_id( $renewal_order );

			if ( get_transient( 'order_lock_' . $renewal_order_id ) ) {
				return true;
			}

			set_transient( 'order_lock_' . $renewal_order_id, uniqid( '', true ), 30 );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_subscription_payment attempting to create charge', $renewal_order->get_id() ) );

			$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $renewal_order );

			if ( ! $agreement_id ) {
				throw new WC_Vipps_Recurring_Exception( 'Fatal error: Vipps agreement id does not exist.' );
			}

			$agreement = $this->api->get_agreement( $agreement_id );
			$amount    = WC_Vipps_Recurring_Helper::get_vipps_amount( $amount );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_subscription_payment on agreement: %s', $renewal_order->get_id(), json_encode( $agreement->to_array() ) ) );

			/*
			 * if this is triggered by the Woo Subscriptions retry system we need to delete the data related to the old payment
			 * and create an entirely new charge.
			 */
			$charge_has_failed = WC_Vipps_Recurring_Helper::is_charge_failed_for_order( $renewal_order );

			// if the previous charge is 'FAILED' we can assume this is an automatic retry instead of a normal renewal process.
			if ( $charge_has_failed ) {
				// check that the currently attached charge is in fact failed
				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] it looks like the charge on agreement: %s failed. Deleting renewal meta and creating a new charge.', $renewal_order->get_id(), $agreement->id ) );

				// note: delete transaction id as we use this to determine whether to update the order status in check_charge_status.
				$renewal_order->set_transaction_id( 0 );
				$renewal_order = $this->delete_renewal_meta( $renewal_order );

				$this->generate_idempotency_key( $renewal_order );

				// clean the post cache for the renewal order to force Woo to fetch meta again.
				clean_post_cache( $renewal_order_id );
			}

			$idempotency_key = $this->get_idempotency_key( $renewal_order );

			$charge = $this->api->create_charge( $agreement, $idempotency_key, $amount );

			WC_Vipps_Recurring_Helper::update_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_CHARGE_ID, $charge['chargeId'] );
			WC_Vipps_Recurring_Helper::set_order_as_pending( $renewal_order, $charge['chargeId'] );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_subscription_payment created charge: %s', $renewal_order->get_id(), json_encode( $charge ) ) );

			$charge = $this->api->get_charge( $agreement->id, $charge['chargeId'] );

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_subscription_payment fetched charge: %s', $renewal_order->get_id(), json_encode( $charge->to_array() ) ) );

			$this->process_order_charge( $renewal_order, $charge );
			$renewal_order->save();

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_subscription_payment for charge: %s and agreement: %s', $renewal_order->get_id(), $charge->id, $agreement->id ) );

			return true;
		}

		private function get_due_charge_note( WC_Vipps_Charge $charge ): string {
			$timestamp_gmt   = WC_Vipps_Recurring_Helper::rfc_3999_date_to_unix( $charge->due );
			$date_to_display = ucfirst( wcs_get_human_time_diff( $timestamp_gmt ) );

			// translators: Vipps Charge ID, human diff timestamp
			return sprintf( __( 'Vipps charge created: %1$s. The charge will be complete %2$s.', 'woo-vipps-recurring' ), $charge->id, strtolower( $date_to_display ) );
		}

		/**
		 * Maybe capture a payment if it has not already been captured
		 *
		 * @param $order_id
		 *
		 * @throws WC_Vipps_Recurring_Exception
		 */
		public function maybe_capture_payment( $order_id ): void {
			$order = wc_get_order( $order_id );

			if ( wcs_order_contains_renewal( $order )
				 || (int) WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING ) !== 1
				 || (int) WC_Vipps_Recurring_Helper::is_charge_captured_for_order( $order ) === 1
				 || (int) WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_ZERO_AMOUNT ) ) {
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

				$idempotency_key = $this->get_idempotency_key( $order );

				$charges = $this->api->get_charges_for( $agreement->id );

				// if a charge exists and the status is RESERVED we need to simply capture it
				$charges = array_filter( $charges, static function ( $charge ) {
					return $charge->status === WC_Vipps_Charge::STATUS_RESERVED;
				} );

				if ( count( $charges ) === 0 ) {
					WC_Vipps_Recurring_Logger::log( sprintf( '[%s] No RESERVED charges found in capture_payment for agreement: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $agreement->id ) );

					return false;
				}

				$latest_charge = $charges[ array_key_last( $charges ) ];
				$charge        = $this->capture_reserved_charge( $latest_charge, $agreement, $order, $idempotency_key );

				WC_Vipps_Recurring_Helper::set_order_as_pending( $order, $charge->id );
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
		 * @param WC_Vipps_Charge $charge
		 * @param WC_Vipps_Agreement $agreement
		 * @param $order
		 * @param string $idempotency_key
		 *
		 * @return false|WC_Vipps_Charge
		 */
		public function capture_reserved_charge( WC_Vipps_Charge $charge, WC_Vipps_Agreement $agreement, $order, string $idempotency_key ) {
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Attempting to capture reserve charge: %s for agreement: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id, $agreement->id ) );

			// capture reserved charge
			try {
				$this->api->capture_reserved_charge( $agreement, $charge, $idempotency_key );

				// get charge
				$charge = $this->api->get_charge( $agreement->id, $charge->id );

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Captured reserve charge: %s for agreement: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id, $agreement->id ) );

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
		 * @param WC_Vipps_Agreement $agreement
		 * @param $order
		 * @param string $idempotency_key
		 *
		 * @return bool|WC_Vipps_Charge
		 */
		public function create_charge( WC_Vipps_Agreement $agreement, $order, string $idempotency_key ) {
			try {
				$charge = $this->api->create_charge( $agreement, $idempotency_key );

				// add due charge note
				$charge = $this->api->get_charge( $agreement->id, $charge['chargeId'] );

				$order->add_order_note( $this->get_due_charge_note( $charge ) );

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Created charge: %s for agreement: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id, $agreement->id ) );

				return $charge;
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
			return WC_Vipps_Recurring_Helper::get_subscriptions_for_order( $order );
		}

		/**
		 * @param $subscription_id
		 *
		 * @throws WC_Vipps_Recurring_Exception
		 */
		public function maybe_process_gateway_change( $subscription_id ): void {
			$subscription = wcs_get_subscription( $subscription_id );

			if ( $subscription->meta_exists( '_new_agreement_id' ) ) {
				$new_agreement_id = WC_Vipps_Recurring_Helper::get_meta( $subscription, '_new_agreement_id' );
				$agreement        = $this->api->get_agreement( $new_agreement_id );

				if ( $agreement->status === WC_Vipps_Agreement::STATUS_ACTIVE ) {
					$old_agreement_id = WC_Vipps_Recurring_Helper::get_meta( $subscription, '_old_agreement_id' );

					WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Processing subscription gateway change with new agreement id: %s and old agreement id: %s', WC_Vipps_Recurring_Helper::get_id( $subscription ), $new_agreement_id, $old_agreement_id ) );
					if ( ! empty( $old_agreement_id ) && $new_agreement_id !== $old_agreement_id ) {
						WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Cancelling old agreement id: %s in Vipps due to gateway change', WC_Vipps_Recurring_Helper::get_id( $subscription ), $old_agreement_id ) );


						$idempotency_key = $this->get_idempotency_key( $subscription );
						$this->api->cancel_agreement( $old_agreement_id, $idempotency_key );
					}

					WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, $new_agreement_id );

					WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Subscription gateway change completed', WC_Vipps_Recurring_Helper::get_id( $subscription ) ) );
					WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $subscription, $this->id );
				}

				if ( in_array( $agreement->status, [ WC_Vipps_Agreement::STATUS_STOPPED, 'EXPIRED' ], true ) ) {
					$subscription->add_order_note( __( 'Payment gateway change request cancelled in Vipps', 'woo-vipps-recurring' ) );
				}

				if ( in_array( $agreement->status, [
					WC_Vipps_Agreement::STATUS_STOPPED,
					WC_Vipps_Agreement::STATUS_EXPIRED
				], true ) ) {
					$subscription->delete_meta_data( WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_WAITING_FOR_GATEWAY_CHANGE );
					$subscription->delete_meta_data( WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_SWAPPING_GATEWAY_TO_VIPPS );
					$subscription->delete_meta_data( '_new_agreement_id' );
					$subscription->delete_meta_data( '_old_agreement_id' );
				}

				$subscription->save();
			}
		}

		/**
		 * @param $subscription_id
		 *
		 * @throws WC_Vipps_Recurring_Config_Exception
		 * @throws WC_Vipps_Recurring_Exception
		 * @throws WC_Vipps_Recurring_Temporary_Exception
		 */
		public function maybe_update_subscription_details_in_app( $subscription_id ): void {
			$subscription = wcs_get_subscription( $subscription_id );

			$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $subscription );
			if ( $payment_method !== $this->id ) {
				return;
			}

			$agreement = $this->get_agreement_from_order( $subscription );
			if ( $agreement !== false && $agreement->status !== WC_Vipps_Agreement::STATUS_ACTIVE ) {
				WC_Vipps_Recurring_Helper::set_update_in_app_completed( $subscription );

				return;
			}

			$parent_order = $subscription->get_parent();
			$items        = array_reverse( $subscription->get_items() );

			// we can only ever have one subscription as long as 'multiple_subscriptions' is disabled
			$item = array_pop( $items );

			if ( ! $item ) {
				return;
			}

			$item_name           = $item->get_name();
			$parent_product      = wc_get_product( $item->get_product_id() );
			$product_description = WC_Vipps_Recurring_Helper::get_product_description( $parent_product );

			$agreement_description = null;
			if ( $prefix = WC_Vipps_Recurring_Helper::get_meta( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP_DESCRIPTION_PREFIX ) ) {
				$agreement_description = "[$prefix]";
			}

			if ( $product_description ) {
				$agreement_description .= " $product_description";
			}

			$updated_agreement = ( new WC_Vipps_Agreement() )
				->set_pricing( ( new WC_Vipps_Agreement_Pricing() )
					->set_amount( WC_Vipps_Recurring_Helper::get_vipps_amount( $subscription->get_total() ) ) )
				->set_product_name( $item_name );

			if ( $agreement_description ) {
				$updated_agreement = $updated_agreement->set_product_description( $agreement_description );
			}

			$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $subscription );
			if ( empty( $agreement_id ) ) {
				$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $parent_order );
			}

			if ( $agreement_id ) {
				try {
					WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Agreement updated in app for agreement id: %s', $subscription_id, $agreement_id ) );

					$idempotency_key = $this->get_idempotency_key( $subscription );
					$this->api->update_agreement( $agreement_id, $updated_agreement, $idempotency_key );
				} catch ( Exception $e ) {
					// do nothing
				}
			}

			WC_Vipps_Recurring_Helper::set_update_in_app_completed( $subscription );
		}

		/**
		 * Process a payment when checking out in WooCommerce
		 *
		 * https://docs.woocommerce.com/document/subscriptions/develop/payment-gateway-integration/
		 * "Putting it all Together"
		 *
		 * @param int $order_id
		 *
		 * @throws Exception
		 */
		public function process_payment( $order_id, bool $retry = true, bool $previous_error = false ): array {
			$is_gateway_change = wcs_is_subscription( $order_id );
			$subscription      = null;

			$order     = wc_get_order( $order_id );
			$debug_msg = sprintf( '[%s] process_payment (gateway change: %s)', $order_id, $is_gateway_change ? 'Yes' : 'No' ) . "\n";

			$is_failed_renewal_order = wcs_cart_contains_failed_renewal_order_payment() !== false;

			if ( ! $is_gateway_change
				 && ! $is_failed_renewal_order
				 && ! wcs_order_contains_subscription( $order )
				 && ! wcs_order_contains_early_renewal( $order ) ) {
				return [
					'result'   => 'fail',
					'redirect' => ''
				];
			}

			// If we have an early renewal order on our hands we should
			if ( wcs_order_contains_early_renewal( $order ) ) {
				$renewal_order = $order;

				$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order );
				$subscription  = $subscriptions[ array_key_first( $subscriptions ) ];

				$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $subscription );
				if ( $agreement_id ) {
					$existing_agreement = $this->api->get_agreement( $agreement_id );

					if ( $existing_agreement->status === WC_Vipps_Agreement::STATUS_ACTIVE ) {
						WC_Subscriptions_Payment_Gateways::trigger_gateway_renewal_payment_hook( $renewal_order );

						// Trigger the subscription payment complete hooks and reset suspension counts and user roles.
						$subscription->payment_complete();

						wcs_update_dates_after_early_renewal( $subscription, $renewal_order );
						wc_add_notice( __( 'Your early renewal order was successful.', 'woocommerce-subscriptions' ) );

						$renewal_order = wc_get_order( $renewal_order->get_id() );

						return [
							'result'   => 'success',
							'redirect' => $renewal_order->get_view_order_url()
						];
					}
				}

				// We need to update the gateway to Vipps after the payment is completed if an agreement is active
				WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_RENEWING_WITH_VIPPS, true );
				$subscription->save();
			}

			try {
				if ( ! $subscription ) {
					$subscription = $order;
				}

				if ( ! $is_gateway_change ) {
					$subscriptions = $this->get_subscriptions_for_order( $order );

					// we can only ever have one subscription as long as 'multiple_subscriptions' is disabled
					$subscription = $subscriptions[ array_key_first( $subscriptions ) ];
				}

				/*
				 * if this order has a PENDING or ACTIVE agreement in Vipps we should not allow checkout anymore
				 * this will prevent duplicate transactions
				 */
				$agreement_id              = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );
				$already_swapping_to_vipps = WC_Vipps_Recurring_Helper::get_meta( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_SWAPPING_GATEWAY_TO_VIPPS );

				if ( $agreement_id && ( ! $is_gateway_change || $already_swapping_to_vipps ) && ! $is_failed_renewal_order ) {
					if ( ! $already_swapping_to_vipps ) {
						$existing_agreement = $this->get_agreement_from_order( $order );
					} else {
						$new_agreement_id   = WC_Vipps_Recurring_Helper::get_meta( $subscription, '_new_agreement_id' );
						$existing_agreement = $this->api->get_agreement( $new_agreement_id );
					}

					if ( $existing_agreement->status === WC_Vipps_Agreement::STATUS_ACTIVE ) {
						throw new WC_Vipps_Recurring_Temporary_Exception( __( 'This subscription is already active in Vipps. You can leave this page.', 'woo-vipps-recurring' ) );
					}

					// todo: remove this if Idempotency-Key starts working as expected in Vipps' API (ideally confirmation url should be the same as long as the same idempotency key is passed)
					if ( $existing_agreement->status === WC_Vipps_Agreement::STATUS_PENDING ) {
						$confirmation_url = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_AGREEMENT_CONFIRMATION_URL );

						if ( $confirmation_url ) {
							WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Customer has been redirected to an existing confirmation URL', $order_id ) );

							return [
								'result'   => 'success',
								'redirect' => $confirmation_url,
							];
						}

						throw new WC_Vipps_Recurring_Temporary_Exception( __( 'There is a pending agreement on this order. Check the Vipps app or wait and try again in a few minutes.', 'woo-vipps-recurring' ) );
					}
				}

				$subscription_period = $subscription->get_billing_period();

				$subscription_interval = $subscription->get_billing_interval();

				$items = array_reverse( $order->get_items() );

				/*
				 * WooCommerce Subscriptions should really deal with this themselves, but when other gateways with support for `multiple_subscriptions`
				 * are enabled WooCommerce Subscriptions allows this scenario for our plugin too. This is not ideal so let's deny it here.
				 * There's no good way to make this obvious to a customer in the app, especially if the products have different durations
				 * i.e. one yearly and one monthly
				 */
				$has_more_products = count( $items ) > 1;
				if ( $has_more_products ) {
					$counter = 1;

					foreach ( $items as $item ) {
						if ( ! WC_Subscriptions_Product::is_subscription( $item['product_id'] ) ) {
							continue;
						}

						if ( $counter > 1 ) {
							wc_add_notice( __( 'Different subscription products can not be purchased at the same time using Vipps.', 'woo-vipps-recurring' ), 'error' );

							return [
								'result'   => 'fail',
								'redirect' => ''
							];
						}

						$counter ++;
					}
				}

				// we can only ever have one subscription as long as 'multiple_subscriptions' is disabled, so we can fetch the first subscription
				$subscription_items = array_filter( $items, static function ( $item ) {
					return apply_filters( 'wc_vipps_recurring_item_is_subscription', WC_Subscriptions_Product::is_subscription( $item['product_id'] ), $item );
				} );

				$item           = array_pop( $subscription_items );
				$product        = $item->get_product();
				$parent_product = wc_get_product( $item->get_product_id() );

				$extra_initial_charge_description = '';

				if ( $has_more_products ) {
					$other_items = array_filter( $items, static function ( $other_item ) use ( $item ) {
						return $item['product_id'] !== $other_item['product_id'];
					} );

					foreach ( $other_items as $product_item ) {
						$extra_initial_charge_description .= WC_Vipps_Recurring_Helper::get_product_description( $product_item->get_product() ) . ', ';
					}

					$extra_initial_charge_description = rtrim( $extra_initial_charge_description, ', ' );
				}

				$is_virtual     = $product->is_virtual();
				$direct_capture = $parent_product->get_meta( WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE ) === 'yes';

				$agreement_url = filter_var( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ), FILTER_VALIDATE_URL )
					? get_permalink( get_option( 'woocommerce_myaccount_page_id' ) )
					: wc_get_account_endpoint_url( 'dashboard' );

				if ( ! filter_var( $agreement_url, FILTER_VALIDATE_URL ) ) {
					$agreement_url = get_home_url();
				}

				$redirect_url = $this->get_return_url( $order );

				// total no longer returns the order amount when gateway is being changed
				$agreement_total = $is_gateway_change ? $subscription->get_subtotal() : $subscription->get_total();

				// when we're performing a variation switch we need some special logic in Vipps
				$is_subscription_switch = wcs_order_contains_switch( $order );

				if ( $is_subscription_switch ) {
					$subscription_switch_data = WC_Vipps_Recurring_Helper::get_meta( $order, '_subscription_switch_data' );

					if ( isset( $subscription_switch_data[ array_key_first( $subscription_switch_data ) ]['switches'] ) ) {
						$switches    = $subscription_switch_data[ array_key_first( $subscription_switch_data ) ]['switches'];
						$switch_data = $switches[ array_key_first( $switches ) ];
						$direction   = $switch_data['switch_direction'];

						if ( $direction === 'upgrade' ) {
							$agreement_total += $order->get_total();
						}
					}
				}

				$agreement = ( new WC_Vipps_Agreement() )
					->set_pricing(
						( new WC_Vipps_Agreement_Pricing() )
							->set_type( WC_Vipps_Agreement_Pricing::TYPE_LEGACY )
							->set_currency( $order->get_currency() )
							->set_amount( WC_Vipps_Recurring_Helper::get_vipps_amount( $agreement_total ) )
					)
					->set_interval(
						( new WC_Vipps_Agreement_Interval() )
							->set_unit( strtoupper( $subscription_period ) )
							->set_count( (int) $subscription_interval )
					)
					->set_product_name( $item->get_name() )
					->set_product_description( WC_Vipps_Recurring_Helper::get_product_description( $product ) )
					->set_merchant_agreement_url( apply_filters( 'wc_vipps_recurring_merchant_agreement_url', $agreement_url ) )
					->set_merchant_redirect_url( apply_filters( 'wc_vipps_recurring_merchant_redirect_url', $redirect_url ) );

				// validate phone number and only add it if it's up to Vipps' standard to avoid errors
				if ( WC_Vipps_Recurring_Helper::is_valid_phone_number( $order->get_billing_phone() ) ) {
					$agreement = $agreement->set_phone_number( $order->get_billing_phone() );
				}

				$is_zero_amount      = (int) $order->get_total() === 0 || $is_gateway_change;
				$capture_immediately = $is_virtual || $direct_capture;
				$has_synced_product  = WC_Subscriptions_Synchroniser::subscription_contains_synced_product( $subscription );
				$has_trial           = (bool) WC_Subscriptions_Product::get_trial_length( $product );

				$sign_up_fee       = WC_Subscriptions_Order::get_sign_up_fee( $order );
				$has_campaign      = $has_trial || $has_synced_product || $is_zero_amount || $order->get_total_discount() !== 0.00 || $is_subscription_switch || $sign_up_fee;
				$has_free_campaign = $is_subscription_switch || $sign_up_fee || $has_synced_product || $has_trial;

				// when Prorate First Renewal is set to "Never (charge the full recurring amount at sign-up)" we don't want to have a campaign
				// also not when the order total is the same as the agreement total
				if ( $has_free_campaign && $has_synced_product && $order->get_total() === $agreement_total ) {
					$has_campaign = false;
				}

				if ( ! $is_zero_amount ) {
					$initial_charge_description = WC_Vipps_Recurring_Helper::get_product_description( $parent_product );
					if ( ! empty( $extra_initial_charge_description ) ) {
						$initial_charge_description .= ' + ' . $extra_initial_charge_description;

						if ( $has_campaign ) {
							$initial_charge_description = $extra_initial_charge_description;
						}
					}

					$agreement = $agreement->set_initial_charge(
						( new WC_Vipps_Agreement_Initial_Charge() )
							->set_amount( WC_Vipps_Recurring_Helper::get_vipps_amount( $order->get_total() ) )
							->set_description( $initial_charge_description ?? $item->get_name() )
							->set_transaction_type( $capture_immediately ? WC_Vipps_Agreement_Initial_Charge::TRANSACTION_TYPE_DIRECT_CAPTURE : WC_Vipps_Agreement_Initial_Charge::TRANSACTION_TYPE_RESERVE_CAPTURE )
					);

					if ( ! $capture_immediately ) {
						WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_reserved_capture', true );
					}
				}

				if ( $has_campaign ) {
//					$start_date   = new DateTime( '@' . $subscription->get_time( 'start' ) );
					$next_payment = new DateTime( '@' . $subscription->get_time( 'next_payment' ) );
					$end_date     = new DateTime( '@' . $subscription->get_time( 'end' ) );

					$campaign_price    = $has_free_campaign ? 0 : $order->get_total();
					$campaign_end_date = $subscription->get_time( 'end' ) === 0 ? $next_payment : $end_date;

					$agreement = $agreement->set_campaign(
						( new WC_Vipps_Agreement_Campaign() )
							->set_type( WC_Vipps_Agreement_Campaign::TYPE_PRICE_CAMPAIGN )
							->set_price( WC_Vipps_Recurring_Helper::get_vipps_amount( $campaign_price ) )
//							->set_event_date(WC_Vipps_Recurring_Helper::get_rfc_3999_date( $start_date ))
							->set_end( WC_Vipps_Recurring_Helper::get_rfc_3999_date( $campaign_end_date ) )
					);
				}

				$idempotency_key = $this->get_idempotency_key( $order );
				$response        = $this->api->create_agreement( $agreement, $idempotency_key );

				// mark the old agreement for cancellation to leave no dangling agreements in Vipps
				$should_cancel_old = $is_gateway_change || $is_subscription_switch || $is_failed_renewal_order;
				if ( $should_cancel_old ) {
					if ( $is_gateway_change ) {
						/* translators: Vipps Agreement ID */
						$message = sprintf( __( 'Request to change gateway to Vipps with agreement ID: %s.', 'woo-vipps-recurring' ), $response['agreementId'] );
						$subscription->add_order_note( $message );
						$debug_msg .= 'Request to change gateway to Vipps' . "\n";
					} elseif ( $is_subscription_switch ) {
						$debug_msg .= 'Request to switch subscription variation' . "\n";
					} else {
						$debug_msg .= 'Request to pay for a failed renewal order' . "\n";
					}

					WC_Vipps_Recurring_Helper::update_meta_data( $subscription, '_old_agreement_id', WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $subscription ) );
					WC_Vipps_Recurring_Helper::update_meta_data( $subscription, '_new_agreement_id', $response['agreementId'] );
					WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_WAITING_FOR_GATEWAY_CHANGE, true );
					WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_SWAPPING_GATEWAY_TO_VIPPS, true );
				}

				if ( ! $is_gateway_change ) {
					if ( ! $should_cancel_old ) {
						WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, $response['agreementId'] );
					}

					if ( $is_zero_amount ) {
						WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_ZERO_AMOUNT, true );
					}

					WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, $response['agreementId'] );
					WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, true );
				}

				/* translators: Vipps Agreement ID */
				$message = sprintf( __( 'Vipps agreement created: %s. Customer sent to Vipps for confirmation.', 'woo-vipps-recurring' ), $response['agreementId'] );
				$order->add_order_note( $message );

				$debug_msg .= sprintf( 'Created agreement with agreement ID: %s', $response['agreementId'] ) . "\n";

				if ( isset( $response['chargeId'] ) ) {
					WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID, $response['chargeId'] );
				}

				WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_AGREEMENT_CONFIRMATION_URL, $response['vippsConfirmationUrl'] );

				// save meta
				$order->save();
				$subscription->save();

				$debug_msg .= sprintf( 'Debug body: %s', json_encode( $agreement->to_array() ) ) . "\n";
				$debug_msg .= sprintf( 'Debug response: %s', json_encode( array_merge( $response, [ 'vippsConfirmationUrl' => 'redacted' ] ) ) );
				WC_Vipps_Recurring_Logger::log( $debug_msg );

				return [
					'result'   => 'success',
					'redirect' => $response['vippsConfirmationUrl'],
				];
			} catch ( WC_Vipps_Recurring_Temporary_Exception $e ) {
				wc_add_notice( $e->getMessage(), 'error' );

				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Temporary error in process_payment: %s', $order_id, $e->getMessage() ) );

				return [
					'result'   => 'fail',
					'redirect' => '',
				];
			} catch ( WC_Vipps_Recurring_Exception $e ) {
				wc_add_notice( $e->getLocalizedMessage(), 'error' );

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
		public function init_form_fields(): void {
			$this->form_fields = require( __DIR__ . '/admin/vipps-recurring-settings.php' );
		}

		/**
		 * @param $what
		 */
		private function admin_error( $what ): void {
			add_action( 'admin_notices', static function () use ( $what ) {
				echo "<div class='notice notice-error is-dismissible'><p>$what</p></div>";
			} );
		}

		/**
		 * @param $what
		 */
		private function admin_notify( $what ): void {
			add_action( 'admin_notices', static function () use ( $what ) {
				echo "<div class='notice notice-info is-dismissible'><p>$what</p></div>";
			} );
		}

		/**
		 * @param $subscription_id
		 */
		public function save_subscription_order_items( $subscription_id ): void {
			$post = get_post( $subscription_id );

			if ( 'shop_subscription' === get_post_type( $post ) ) {
				$subscription = wcs_get_subscription( $subscription_id );
				WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP, 1 );
				$subscription->save();
			}
		}

		public function process_admin_options(): bool {
			$saved = parent::process_admin_options();

			$this->init_form_fields();

			if ( $this->get_option( 'enabled' ) === "yes" ) {
				try {
					$this->api->get_access_token( true );

					$this->admin_notify( __( 'Successfully authenticated with the Vipps API', 'woo-vipps-recurring' ) );
				} catch ( Exception $e ) {
					/* translators: %s: the error message returned from Vipps */
					$this->admin_error( sprintf( __( 'Could not authenticate with the Vipps API: %s', 'woo-vipps-recurring' ), $e->getMessage() ) );
				}
			}

			return $saved;
		}

		/**
		 * @param $statuses
		 *
		 * @return array
		 */
		public function append_valid_statuses_for_payment_complete( $statuses ): array {
			$statuses = array_merge( $statuses, $this->statuses_to_attempt_capture );

			if ( $this->transition_renewals_to_completed && ! in_array( 'completed', $statuses, true ) ) {
				$statuses[] = 'completed';
			}

			return $statuses;
		}

		/**
		 * @param $payment_meta
		 * @param $subscription
		 *
		 * @return mixed
		 */
		public function add_subscription_payment_meta( $payment_meta, $subscription ) {
			$payment_meta[ $this->id ] = [
				'post_meta' => [
					'_agreement_id' => [
						'value' => get_post_meta( $subscription->get_id(), '_agreement_id', true ),
						'label' => __( 'Vipps Agreement ID', 'woo-vipps-recurring' ),
					]
				],
			];

			return $payment_meta;
		}

		/**
		 * @param $payment_method_id
		 * @param $payment_meta
		 *
		 * @throws Exception
		 */
		public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ): void {
			if ( $this->id !== $payment_method_id ) {
				return;
			}

			$agreement_id = $payment_meta['post_meta']['_agreement_id']['value'];

			try {
				$this->api->get_agreement( $agreement_id );
			} catch ( Exception $e ) {
				throw new \RuntimeException( __( 'This Vipps agreement ID is invalid.', 'woo-vipps-recurring' ) );
			}
		}

		/**
		 * @param $subscription
		 */
		public function handle_subscription_switches( $subscription ): void {
			$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $subscription );
			if ( $this->id !== $payment_method ) {
				return;
			}

			WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP, 1 );
			$subscription->save();
		}

		/**
		 * @param WC_Subscription $subscription
		 * @param $new_status
		 * @param $old_status
		 */
		public function maybe_handle_subscription_status_transitions( WC_Subscription $subscription, $new_status, $old_status ): void {
			$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $subscription );

			/*
			 * note: if we reverse the if statement to reduce nesting I fear we may run into an early stop of code execution issue
			 */
			if ( $this->id === $payment_method ) {
				$order = wc_get_order( WC_Vipps_Recurring_Helper::get_id( $subscription ) );

				if ( $new_status === 'pending-cancel' ) {
					WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP, 1 );
					WC_Vipps_Recurring_Helper::update_meta_data(
						$order,
						WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP_DESCRIPTION_PREFIX,
						__( 'Pending cancellation', 'woo-vipps-recurring' )
					);

					$order->save();
				}

				if ( $new_status === 'cancelled' ) {
					WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP, 1 );
					WC_Vipps_Recurring_Helper::update_meta_data(
						$order,
						WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP_DESCRIPTION_PREFIX,
						__( 'Cancelled', 'woo-vipps-recurring' )
					);

					$order->save();
				}

				if ( $new_status === 'on-hold' ) {
					WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP, 1 );
					WC_Vipps_Recurring_Helper::update_meta_data(
						$order,
						WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP_DESCRIPTION_PREFIX,
						__( 'On hold', 'woo-vipps-recurring' )
					);

					$order->save();
				}

				if ( ( $old_status === 'pending-cancel' || $old_status === 'on-hold' ) && $new_status !== 'cancelled' ) {
					WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP, 1 );
					$order->delete_meta_data( WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP_DESCRIPTION_PREFIX );

					$order->save();
				}
			}
		}

		/**
		 * Don't transfer Vipps idempotency key or any other keys unique to a charge or order to resubscribe orders.
		 *
		 * @param mixed $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
		 */
		public function delete_resubscribe_meta( $resubscribe_order ): void {
			$resubscribe_order_id = WC_Vipps_Recurring_Helper::get_id( $resubscribe_order );

			delete_post_meta( $resubscribe_order_id, WC_Vipps_Recurring_Helper::META_CHARGE_ID );
			delete_post_meta( $resubscribe_order_id, WC_Vipps_Recurring_Helper::META_CHARGE_CAPTURED );

			$this->delete_renewal_meta( $resubscribe_order );
		}

		/**
		 * Don't transfer Vipps idempotency key or any other keys unique to a charge or order to renewal orders.
		 *
		 * @param mixed $renewal_order The renewal order
		 */
		public function delete_renewal_meta( $renewal_order ) {
			$renewal_order_id = WC_Vipps_Recurring_Helper::get_id( $renewal_order );

			delete_post_meta( $renewal_order_id, WC_Vipps_Recurring_Helper::META_CHARGE_FAILED );
			delete_post_meta( $renewal_order_id, WC_Vipps_Recurring_Helper::META_CHARGE_FAILED_DESCRIPTION );
			delete_post_meta( $renewal_order_id, WC_Vipps_Recurring_Helper::META_CHARGE_FAILED_REASON );
			delete_post_meta( $renewal_order_id, WC_Vipps_Recurring_Helper::META_CHARGE_LATEST_STATUS );
			delete_post_meta( $renewal_order_id, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP );
			delete_post_meta( $renewal_order_id, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP_DESCRIPTION_PREFIX );
			delete_post_meta( $renewal_order_id, WC_Vipps_Recurring_Helper::META_ORDER_IDEMPOTENCY_KEY );

			return $renewal_order;
		}

		/**
		 * @param $order_id
		 */
		public function maybe_cancel_due_charge( $order_id ): void {
			$order        = wc_get_order( $order_id );
			$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );
			$charge_id    = WC_Vipps_Recurring_Helper::get_charge_id_from_order( $order );

			$pending_charge = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING );

			if ( $pending_charge ) {
				try {
					if ( get_transient( 'maybe_cancel_due_charge_lock' . $order_id ) ) {
						return;
					}

					set_transient( 'maybe_cancel_due_charge_lock' . $order_id, uniqid( '', true ), 30 );

					$charge = $this->api->get_charge( $agreement_id, $charge_id );
					if ( in_array( $charge->status, [
						WC_Vipps_Charge::STATUS_DUE,
						WC_Vipps_Charge::STATUS_PENDING
					], true ) ) {
						$idempotency_key = $this->get_idempotency_key( $order );
						$this->api->cancel_charge( $agreement_id, $charge_id, $idempotency_key );
						$order->add_order_note( __( 'Cancelled due charge in Vipps.', 'woo-vipps-recurring' ) );
						WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Cancelled DUE charge with ID: %s for agreement with ID: %s', $order_id, $charge_id, $agreement_id ) );
					}
				} catch ( Exception $e ) {
					$order->add_order_note( __( 'Could not cancel charge in Vipps. Please manually check the status of this order if you plan to process a new renewal order!', 'woo-vipps-recurring' ) );
					WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Failed cancelling DUE charge with ID: %s for agreement with ID: %s. Error msg: %s', $order_id, $charge_id, $agreement_id, $e->getMessage() ) );
				}
			}
		}

		/**
		 * When renewing early from a different gateway WooCommerce does not update the gateway for you.
		 * If we detect that you've renewed early with Vipps and the gateway is not set to Vipps we will
		 * update the gateway for you.
		 *
		 * @param $order_id
		 *
		 * @return void
		 * @throws WC_Vipps_Recurring_Exception
		 */
		public function after_renew_early_from_another_gateway( $order_id ): void {
			if ( ! wcs_order_contains_early_renewal( $order_id ) ) {
				return;
			}

			$subscriptions = $this->get_subscriptions_for_order( $order_id );

			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->get_payment_method() === $this->id ) {
					continue;
				}

				if ( ! WC_Vipps_Recurring_Helper::get_meta( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_RENEWING_WITH_VIPPS ) ) {
					continue;
				}

				$agreement = $this->get_agreement_from_order( $subscription );
				if ( ! $agreement || $agreement->status !== WC_Vipps_Agreement::STATUS_ACTIVE ) {
					continue;
				}

				$subscription->set_payment_method( $this->id );
				WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_RENEWING_WITH_VIPPS, false );
				$subscription->save();
			}
		}

		/**
		 * Prevent an order from being transitioned to "processing" when the status is already "completed"
		 *
		 * @param $status
		 * @param $order_id
		 * @param $order
		 *
		 * @return string|null
		 */
		public function prevent_backwards_transition_on_completed_order( $status, $order_id, $order ): ?string {
			if ( $status === 'processing'
				 && $order->has_status( 'completed' )
				 && $order->get_payment_method() === $this->id ) {
				return 'completed';
			}

			return $status;
		}
	}
}
