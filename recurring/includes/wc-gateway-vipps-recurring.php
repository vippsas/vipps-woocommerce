<?php

defined( 'ABSPATH' ) || exit;

require_once( __DIR__ . '/wc-vipps-models.php' );
require_once( __DIR__ . '/wc-vipps-recurring-api.php' );

/**
 * WC_Gateway_Vipps class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Vipps_Recurring extends WC_Payment_Gateway {
    /**
     * Which brand to use, will be one of: vipps, mobilepay
     */
    public string $brand;

    /**
     * Vipps MobilePay merchant serial number
     */
    public string $merchant_serial_number;

    /**
     * Whether Vipps MobilePay Checkout is enabled
     */
    public bool $checkout_enabled;

    public string $order_prefix;

    /**
     * Is test mode active?
     */
    public bool $test_mode;

    /**
     * The default status to give pending renewals
     */
    public string $default_renewal_status;

    /**
     * The default status pending orders that have yet to be captured (reserved charges in Vipps/MobilePay) should be given
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
    public bool $transition_renewals_to_completed;

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

    public WC_Vipps_Recurring_Api $api;

    public ?bool $use_high_performance_order_storage = null;

    public bool $auto_capture_mobilepay = false;

    public ?string $continue_shopping_link_page = null;

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
        $this->method_title       = __( 'Vipps/MobilePay Recurring Payments', 'woo-vipps' );
        $this->method_description = __( 'Vipps/MobilePay Recurring Payments works by redirecting your customers to the Vipps MobilePay portal for confirmation. It creates a payment plan and charges your users on the intervals you specify.', 'woo-vipps' );
        $this->has_fields         = true;

        /*
         * Do not add 'multiple_subscriptions' to $supports.
         * Vipps/MobilePay Recurring API does not have any concept of multiple line items at the time of writing this.
         * It could technically be possible to support this, but it's very confusing for a customer in the Vipps/MobilePay app.
         * There are a lot of edge cases to think about in order to support this functionality too,
         * 'process_payment' would have to be rewritten entirely.
         */
        $this->supports = [
            'products',
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

        $this->brand                            = $this->get_option( 'brand' );
        $this->title                            = $this->get_form_fields()['brand']['options'][ $this->brand ];
        $this->description                      = str_replace( '{brand}', $this->title, $this->get_option( 'description' ) );
        $this->enabled                          = $this->get_option( 'enabled' );
        $this->test_mode                        = $this->get_option( 'test_mode' ) === "yes";
        $this->merchant_serial_number           = $this->get_option( 'merchant_serial_number' );
        $this->checkout_enabled                 = $this->get_option( 'checkout_enabled' ) === "yes";
        $this->order_prefix                     = $this->get_option( 'order_prefix' );
        $this->default_renewal_status           = $this->get_option( 'default_renewal_status' );
        $this->default_reserved_charge_status   = $this->get_option( 'default_reserved_charge_status' );
        $this->transition_renewals_to_completed = $this->get_option( 'transition_renewals_to_completed' ) === "yes";
        $this->check_charges_amount             = $this->get_option( 'check_charges_amount' );
        $this->check_charges_sort_order         = $this->get_option( 'check_charges_sort_order' );
        $this->auto_capture_mobilepay           = $this->get_option( 'auto_capture_mobilepay' ) === "yes";
        $this->continue_shopping_link_page      = $this->get_option( 'continue_shopping_link_page' );

        if ( WC_VIPPS_RECURRING_TEST_MODE ) {
            $this->test_mode = true;
        }

        if ( $this->test_mode ) {
            $this->merchant_serial_number = $this->get_option( 'test_merchant_serial_number' );
        }

        // translators: %s: brand name, Vipps or MobilePay
        $this->order_button_text = sprintf( __( 'Pay with %s', 'woo-vipps' ), $this->title );

        $this->api = new WC_Vipps_Recurring_Api( $this );

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
         * prematurely capturing this reserved Vipps/MobilePay charge
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

        add_action( 'woocommerce_order_status_pending_to_cancelled', [ $this, 'maybe_delete_order' ], 99999 );
        add_action( 'woocommerce_new_order', [ $this, 'maybe_delete_order_later' ] );
        add_action( 'woocommerce_vipps_recurring_delete_pending_order', [ $this, 'maybe_delete_order' ] );

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

        add_action( 'woocommerce_subscription_cancelled_' . $this->id, [
            $this,
            'cancel_subscription',
        ] );

        add_action( 'woocommerce_before_thankyou', [ $this, 'maybe_process_redirect_order' ], 1 );

        add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, [
            $this,
            'update_failing_payment_method'
        ], 10, 2 );

        /*
         * When changing the payment method for a WooCommerce Subscription to Vipps MobilePay, let WooCommerce Subscription
         * know that the payment method for that subscription should not be changed immediately. Instead, it should
         * wait for the go ahead in cron, after the user confirmed the payment method change with Vipps MobilePay.
         */
        add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', [
            $this,
            'indicate_async_payment_method_update'
        ], 10, 2 );

        // Tell WooCommerce about our custom payment meta fields
        add_action( 'woocommerce_subscription_payment_meta', [ $this, 'add_subscription_payment_meta' ], 10, 2 );

        // Validate custom payment meta fields
        add_action( 'woocommerce_subscription_validate_payment_meta', [
            $this,
            'validate_subscription_payment_meta'
        ], 10, 2 );

        // Handle subscription switches (free upgrades & downgrades)
        add_action( 'woocommerce_subscriptions_switched_item', [ $this, 'handle_subscription_switch_completed' ] );

        // If we are performing a subscription switch to Vipps Recurring we need to take a payment
        // todo: Figure out how to force a redirect to Vipps here. We do need to sign a new agreement in a lot of cases...
        // todo: Vipps has a 10x multiplier limit for the agreement pricing.
        add_filter( 'woocommerce_cart_needs_payment', [ $this, 'cart_needs_payment' ], 100, 2 );

        /*
         * Handle in app updates when a subscription status changes, typically when status transitions to
         * 'pending-cancel', 'cancelled' or 'pending-cancel' to any other status
         */
        add_action( 'woocommerce_subscription_status_updated', [
            $this,
            'maybe_handle_subscription_status_transitions'
        ], 10, 3 );

        // Delete idempotency key when renewal/resubscribe happens
        add_action( 'wcs_resubscribe_order_created', [ $this, 'delete_resubscribe_meta' ] );
        add_action( 'wcs_renewal_order_created', [ $this, 'delete_renewal_meta' ] );

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

        add_action( 'woocommerce_order_after_calculate_totals', [ $this, 'update_agreement_price_in_app' ], 10, 2 );

        // Woo Subscriptions uses `wp_safe_redirect()` during a gateway change, which will not allow us to redirect to the Vipps MobilePay API
        // Unless we whitelist the domains specifically
        // https://developer.vippsmobilepay.com/docs/knowledge-base/servers/
        add_filter( 'allowed_redirect_hosts', function ( $hosts ) {
            return array_merge( $hosts, [
                // Production servers
                'api.vipps.no',
                'api.mobilepay.dk',
                'api.mobilepay.fi',
                'pay.vipps.no',
                'pay.mobilepay.dk',
                'pay.mobilepay.fi',
                // MT servers
                'apitest.vipps.no',
                'pay-mt.vipps.no',
                'pay-mt.mobilepay.dk',
                'pay-mt.mobilepay.fi'
            ] );
        } );
    }

    /**
     * Indicate to WooCommerce Subscriptions that the payment method change for Vipps/MobilePay Recurring Payments
     * should be asynchronous.
     *
     * WC_Subscriptions_Change_Payment_Gateway::change_payment_method_via_pay_shortcode uses the
     * result to decide whether to change the payment method information on the subscription
     * right away or not.
     *
     * In our case, the payment method will not be updated until after the user confirms the
     * payment method change with Vipps MobilePay. Once that's done, we'll take care of finishing
     * the payment method update with the subscription.
     *
     * @param bool $should_update Current value of whether the payment method should be updated immediately.
     * @param string $new_payment_method The new payment method name.
     *
     * @return bool Whether the subscription's payment method should be updated on checkout or async when a response is returned.
     */
    public function indicate_async_payment_method_update( bool $should_update, string $new_payment_method ): bool {
        if ( $this->id === $new_payment_method ) {
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

        $order = wc_get_order( $order_id );
        if ( $order->get_payment_method() !== $this->id ) {
            return;
        }

        $this->process_redirect_payment( $order_id );
    }

    /**
     * Check if we are using the new HPOS feature from WooCommerce
     * This function is used for backwards compatibility in certain places
     */
    public function use_high_performance_order_storage(): bool {
        if ( $this->use_high_performance_order_storage === null ) {
            $this->use_high_performance_order_storage = function_exists( 'wc_get_container' ) &&  // 4.4.0
                                                        function_exists( 'wc_get_page_screen_id' ) && // Exists in the HPOS update
                                                        class_exists( "Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController" ) &&
                                                        wc_get_container()->get( Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
        }

        return $this->use_high_performance_order_storage;
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
     * @param bool $skip_lock
     *
     * @return string
     * @throws WC_Vipps_Recurring_Config_Exception
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     */
    public function check_charge_status( $order_id, $skip_lock = false ): string {
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
        if ( ! $this->use_high_performance_order_storage() ) {
            clean_post_cache( WC_Vipps_Recurring_Helper::get_id( $order ) );
        }

        // hold on to the lock for 30 seconds
        $lock = (int) WC_Vipps_Recurring_Helper::get_meta( $order, '_vipps_recurring_locked_for_update_time' );
        if ( ( $lock && $lock > time() - 30 ) && ! $skip_lock ) {
            return 'SUCCESS';
        }

        // lock the order
        WC_Vipps_Recurring_Helper::update_meta_data( $order, '_vipps_recurring_locked_for_update_time', time() );
        $order->save();

        $agreement = $this->get_agreement_from_order( $order );
        if ( ! $agreement ) {
            // If there is no agreement we can't complete Checkout orders. Let Checkout deal with this through an action.
            $order_initial = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_INITIAL );

            if ( $order_initial && $order->get_payment_method() === $this->id ) {
                do_action( 'wc_vipps_recurring_check_charge_status_no_agreement', $order );
            }

            return 'INVALID';
        }

        $is_renewal = wcs_order_contains_renewal( $order );

        // logic for zero amounts when a charge does not exist
        if ( WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_ZERO_AMOUNT ) && ! $is_renewal ) {
            // if there's a campaign with a price of 0 we can complete the order immediately
            if ( $agreement->status === WC_Vipps_Agreement::STATUS_ACTIVE ) {
                $this->complete_order( $order, $agreement->id );

                $order->add_order_note( __( 'The subtotal is zero, the order is free for this subscription period.', 'woo-vipps' ) );
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

        $initial        = empty( WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_INITIAL ) )
                          && ! wcs_order_contains_renewal( $order );
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
            ], true ) && $agreement->status === WC_Vipps_Agreement::STATUS_ACTIVE;

        // If the brand is MobilePay, we should capture the payment now if it is not already captured.
        // This is because MobilePay auto-releases and refunds payments after 7 days. Vipps will keep a reservation for a lot longer.
        if ( $this->brand === WC_Vipps_Recurring_Helper::BRAND_MOBILEPAY
             && ! $is_captured
             && $this->auto_capture_mobilepay ) {
            $order->save();

            if ( $this->maybe_capture_payment( $order_id ) ) {
                $order->add_order_note( __( 'MobilePay payments are automatically captured to prevent the payment reservation from automatically getting cancelled after 14 days.', 'woo-vipps' ) );

                return 'SUCCESS';
            }
        }

        $is_direct_capture = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_DIRECT_CAPTURE );
        if ( $is_direct_capture && ! $is_captured ) {
            $order->save();

            $this->maybe_capture_payment( $order_id );

            return 'SUCCESS';
        }

        WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_CAPTURED, $is_captured );

        if ( (int) $initial ) {
            WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_INITIAL, true );
            WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, true );
        }

        $this->unlock_order( $order );

        $order->save();

        if ( ! $this->use_high_performance_order_storage() ) {
            clean_post_cache( WC_Vipps_Recurring_Helper::get_id( $order ) );
        }

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
        $order->update_status( 'cancelled', __( 'The agreement was cancelled or expired in Vipps/MobilePay', 'woo-vipps' ) );

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
        if ( $this->transition_renewals_to_completed && wcs_order_contains_renewal( $order ) && $order->get_status() !== 'completed' ) {
            $order->update_status( 'completed' );
        }

        // Unlock the order and make sure we tell our cronjob to stop periodically checking the status of this order
        WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, false );
        $this->unlock_order( $order );

        if ( is_callable( [ $order, 'save' ] ) ) {
            $order->save();
        }

        do_action( 'wc_vipps_recurring_after_payment_complete', $order );
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

            /* translators: Vipps/MobilePay Charge ID */
            $message = sprintf( __( 'Charge completed (Charge ID: %s)', 'woo-vipps' ), $charge->id );
            $order->add_order_note( $message );

            WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Completed order for charge: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id ) );
        }

        // status: RESERVED
        // not auto captured, so we need to put the order status to `$this->default_reserved_charge_status`
        if ( ! $transaction_id && $charge->status === WC_Vipps_Charge::STATUS_RESERVED
             && ! wcs_order_contains_renewal( $order ) ) {
            WC_Vipps_Recurring_Helper::set_transaction_id_for_order( $order, $charge->id );

            $message = __( 'Waiting for you to capture the payment', 'woo-vipps' );
            $order->update_status( $this->default_reserved_charge_status, $message );
            WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge reserved: %s (%s)', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id, $charge->status ) );
        }

        // status: DUE or PENDING
        // when DUE, we need to check that it becomes another status in a cron
        $initial = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_INITIAL )
                   && ! wcs_order_contains_renewal( $order );

        if ( ! $initial && ! $transaction_id && ( $charge->status === WC_Vipps_Charge::STATUS_DUE
                                                  || ( $charge->status === WC_Vipps_Charge::STATUS_PENDING
                                                       && wcs_order_contains_renewal( $order ) ) ) ) {
            WC_Vipps_Recurring_Helper::set_transaction_id_for_order( $order, $charge->id );

            WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_CAPTURED, true );

            $order->update_status( $this->default_renewal_status, $this->get_due_charge_note( $charge ) );

            WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge due or pending: %s (%s)', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id, $charge->status ) );
        }

        // status: CANCELLED
        if ( $charge->status === WC_Vipps_Charge::STATUS_CANCELLED ) {
            $order->update_status( 'cancelled', __( 'Vipps/MobilePay payment cancelled.', 'woo-vipps' ) );
            WC_Vipps_Recurring_Helper::set_order_as_not_pending( $order );

            WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge cancelled: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id ) );
        }

        // status: FAILED
        if ( $charge->status === WC_Vipps_Charge::STATUS_FAILED ) {
            $order->update_status( 'failed', __( 'Vipps/MobilePay payment failed.', 'woo-vipps' ) );
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

        $order->save();
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
                'DKK',
                'EUR',
                'SEK'
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
            $order->update_status( 'failed' );

            /* translators: Error message */
            $message = sprintf( __( 'Failed creating a charge: %s', 'woo-vipps' ), $e->getMessage() );
            $order->add_order_note( $message );

            WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Error in process_subscription_payment: %s', $order->get_id(), $e->getMessage() ) );

            return false;
        }
    }

    /**
     * Triggered when a subscription is cancelled
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

        // Prevent temporary cancellations from reaching this code
        $new_status = $subscription->get_status();
        if ( $new_status !== 'cancelled' ) {
            return;
        }

        $subscription_id = WC_Vipps_Recurring_Helper::get_id( $subscription );

        if ( get_transient( 'cancel_subscription_lock' . $subscription_id ) ) {
            return;
        }

        set_transient( 'cancel_subscription_lock' . $subscription_id, uniqid( '', true ), 30 );

        $agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $subscription );
        if ( $agreement_id === null ) {
            return;
        }

        $agreement = $this->api->get_agreement( $agreement_id );

        if ( $agreement->status === WC_Vipps_Agreement::STATUS_ACTIVE ) {
            $this->maybe_handle_subscription_status_transitions( $subscription, $new_status, 'active' );
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
                $err = sprintf( __( 'You cannot refund a charge that was made more than 365 days ago. This order was created %s days ago.', 'woo-vipps' ), $diff->days );
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
            $msg = __( 'A temporary error occurred when refunding a payment through Vipps MobilePay. Please ensure the order is refunded manually or reset the order to "Processing" and try again.', 'woo-vipps' );
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
                $err = __( 'You can not partially refund a pending or due charge. Please wait till the payment clears first or refund the full amount instead.', 'woo-vipps' );
                throw new \RuntimeException( $err );
            }

            $err = __( 'An unexpected error occurred while refunding a payment in Vipps/MobilePay.', 'woo-vipps' );
            throw new \RuntimeException( $err );
        }
    }

    /**
     * @param $order
     *
     * @return mixed|string
     */
    public function get_idempotency_key( $order ) {
        $idempotency_key = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_IDEMPOTENCY_KEY );

        if ( ! $idempotency_key ) {
            $idempotency_key = $this->generate_idempotency_key( $order );
        }

        return $idempotency_key;
    }

    /**
     * @param $order
     *
     * @return string
     */
    protected function generate_idempotency_key( $order ): string {
        $idempotency_key = $this->api->generate_idempotency_key();

        WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_IDEMPOTENCY_KEY, $idempotency_key );
        $order->save();

        return $idempotency_key;
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

        if ( WC_Vipps_Recurring_Helper::order_locked( $renewal_order ) ) {
            return true;
        }

        WC_Vipps_Recurring_Helper::lock_order( $renewal_order );

        WC_Vipps_Recurring_Logger::log( sprintf( '[%s] process_subscription_payment attempting to create charge', $renewal_order->get_id() ) );

        $agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $renewal_order );

        if ( ! $agreement_id ) {
            throw new WC_Vipps_Recurring_Exception( 'Fatal error: Vipps/MobilePay agreement id does not exist.' );
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
            if ( ! $this->use_high_performance_order_storage() ) {
                clean_post_cache( $renewal_order_id );
            }
        }

        $idempotency_key = $this->get_idempotency_key( $renewal_order );
        $charge          = $this->api->create_charge( $agreement, $idempotency_key, $amount );

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

        // translators: Vipps/MobilePay Charge ID, human diff timestamp
        return sprintf( __( 'Vipps/MobilePay charge created: %1$s. The charge will be complete %2$s.', 'woo-vipps' ), $charge->id, strtolower( $date_to_display ) );
    }

    /**
     * Maybe capture a payment if it has not already been captured
     *
     * @param $order_id
     *
     * @return bool
     * @throws WC_Vipps_Recurring_Exception
     */
    public function maybe_capture_payment( $order_id ): bool {
        $order = wc_get_order( $order_id );

        if ( ! WC_Vipps_Recurring_Helper::can_capture_charge_for_order( $order ) ) {
            return false;
        }

        $this->capture_payment( $order );

        return true;
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
        $charge_id    = WC_Vipps_Recurring_Helper::get_charge_id_from_order( $order );

        try {
            $agreement = new WC_Vipps_Agreement( [
                'id' => $agreement_id
            ] );

            WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Fetching charge to prepare for capture', WC_Vipps_Recurring_Helper::get_id( $order ) ) );
            $charge = $this->api->get_charge( $agreement->id, $charge_id );

            $idempotency_key = $this->get_idempotency_key( $order );

            if ( $charge->status ) {
                if ( ! in_array( $charge->status, [
                    WC_Vipps_Charge::STATUS_RESERVED,
                    WC_Vipps_Charge::STATUS_PARTIALLY_CAPTURED
                ] ) ) {
                    WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Charge does not have the status RESERVED or PARTIALLY_CAPTURED for agreement: %s in capture_payment. Found status: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $agreement_id, $charge->status ) );
                    WC_Vipps_Recurring_Helper::set_order_as_not_pending( $order );

                    /* translators: %s: The charge's status */
                    $order->add_order_note( sprintf( __( 'Could not capture charge because the status is not RESERVED or PARTIALLY_CAPTURED. Found status: %s', 'woo-vipps' ), $charge->status ) );
                    $order->save();

                    return false;
                }
            }

            $captured_charge = $this->capture_reserved_charge( $charge, $agreement, $order, $idempotency_key );

            WC_Vipps_Recurring_Helper::set_order_as_pending( $order, $captured_charge->id );
            $order->save();

            $this->process_order_charge( $order, $captured_charge );

            if ( ! $this->use_high_performance_order_storage() ) {
                clean_post_cache( WC_Vipps_Recurring_Helper::get_id( $order ) );
            }

            WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Finished running capture_payment successfully', WC_Vipps_Recurring_Helper::get_id( $order ) ) );

            return true;
        } catch ( WC_Vipps_Recurring_Temporary_Exception $e ) {
            WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Temporary error in capture_payment: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $e->getMessage() ) );
            $this->admin_error( __( 'Vipps/MobilePay is temporarily unavailable.', 'woo-vipps' ) );

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
     */
    public function capture_reserved_charge( WC_Vipps_Charge $charge, WC_Vipps_Agreement $agreement, $order, string $idempotency_key ): WC_Vipps_Charge {
        WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Attempting to capture reserve charge: %s for agreement: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id, $agreement->id ) );

        $this->api->capture_reserved_charge( $agreement, $charge, $idempotency_key );

        // Set the charge status manually as we can safely assume it was charged here. This avoids making another API request.
        $charge->set_status( WC_Vipps_Charge::STATUS_CHARGED );

        WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Captured reserve charge: %s for agreement: %s', WC_Vipps_Recurring_Helper::get_id( $order ), $charge->id, $agreement->id ) );

        return $charge;
    }

    /**
     * @param $order
     *
     * @return array
     */
    public function get_subscriptions_for_order( $order ): array {
        return WC_Vipps_Recurring_Helper::get_subscriptions_for_order( $order );
    }

    private function end_gateway_change_checking( $subscription ) {
        $subscription->delete_meta_data( WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_WAITING_FOR_GATEWAY_CHANGE );
        $subscription->delete_meta_data( '_new_agreement_id' );
        $subscription->delete_meta_data( '_old_agreement_id' );
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
                    WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Cancelling old agreement id: %s in Vipps/MobilePay due to gateway change', WC_Vipps_Recurring_Helper::get_id( $subscription ), $old_agreement_id ) );

                    $idempotency_key = $this->get_idempotency_key( $subscription );
                    $this->api->cancel_agreement( $old_agreement_id, $idempotency_key );
                }

                WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, $new_agreement_id );

                WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Subscription gateway change completed', WC_Vipps_Recurring_Helper::get_id( $subscription ) ) );
                WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $subscription, $this->id );
                $this->end_gateway_change_checking( $subscription );
            }

            if ( in_array( $agreement->status, [ WC_Vipps_Agreement::STATUS_STOPPED, 'EXPIRED' ], true ) ) {
                $subscription->add_order_note( __( 'Payment gateway change request cancelled in Vipps/MobilePay', 'woo-vipps' ) );
            }

            if ( in_array( $agreement->status, [
                WC_Vipps_Agreement::STATUS_STOPPED,
                WC_Vipps_Agreement::STATUS_EXPIRED
            ], true ) ) {
                $this->end_gateway_change_checking( $subscription );
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
     * @param $order
     * @param $subscription
     * @param bool $is_gateway_change
     *
     * @return WC_Vipps_Agreement
     * @throws WC_Vipps_Recurring_Invalid_Value_Exception
     */
    public function create_vipps_agreement_from_order( $order, $subscription = null, bool $is_gateway_change = false ): WC_Vipps_Agreement {
        $order_id = WC_Vipps_Recurring_Helper::get_id( $order );

        if ( ! $subscription ) {
            $subscription = $order;
        }

        // This supports not yet having a subscription, purely because of Express and Checkout orders
        if ( is_a( $subscription, 'WC_Subscription' ) ) {
            $subscription_period   = $subscription->get_billing_period();
            $subscription_interval = $subscription->get_billing_interval();
        } else {
            $subscription_groups = $this->create_partial_subscription_groups_from_order( $order );
            $items               = array_pop( $subscription_groups );
            $product             = $items[0]->get_product();

            $subscription_period   = WC_Subscriptions_Product::get_period( $product );
            $subscription_interval = WC_Subscriptions_Product::get_interval( $product );
        }

        $items = array_reverse( $order->get_items() );

        $has_more_products = count( $items ) > 1;

        // we can only ever have one subscription as long as 'multiple_subscriptions' is disabled, so we can fetch the first subscription
        $subscription_items = array_filter( $items, static function ( $item ) {
            return apply_filters( 'wc_vipps_recurring_item_is_subscription', WC_Subscriptions_Product::is_subscription( $item['product_id'] ), $item );
        } );

        $subscription_item = array_pop( $subscription_items );
        $product           = $subscription_item->get_product();
        $parent_product    = wc_get_product( $subscription_item->get_product_id() );

        $extra_initial_charge_description = '';

        if ( $has_more_products ) {
            $other_items = array_filter( $items, static function ( $other_item ) use ( $subscription_item ) {
                return $subscription_item['product_id'] !== $other_item['product_id'];
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

        $redirect_url = WC_Vipps_Recurring_Helper::get_payment_redirect_url( $order, $is_gateway_change );

        // total no longer returns the order amount when gateway is being changed
        $agreement_total = $subscription->get_total( 'code' );

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

        $has_trial           = WC_Subscriptions_Product::get_trial_length( $product ) !== 0;
        $is_zero_amount      = (int) $order->get_total() === 0 || $is_gateway_change;
        $capture_immediately = $is_virtual || $direct_capture;
        $has_synced_product  = WC_Subscriptions_Synchroniser::subscription_contains_synced_product( $subscription );

        $sign_up_fee       = WC_Subscriptions_Order::get_sign_up_fee( $order );
        $has_campaign      = $has_trial || $has_synced_product || $is_zero_amount || $order->get_total_discount() !== 0.00 || $is_subscription_switch || $sign_up_fee;
        $has_free_campaign = $is_subscription_switch || $sign_up_fee || $has_synced_product || $has_trial;

        // when Prorate First Renewal is set to "Never (charge the full recurring amount at sign-up)" we don't want to have a campaign
        // also not when the order total is the same as the agreement total
        if ( $has_free_campaign && $has_synced_product && $order->get_total() === $agreement_total ) {
            $has_campaign = false;
        }

        $agreement = ( new WC_Vipps_Agreement() )
            ->set_external_id( $order_id )
            ->set_pricing(
                ( new WC_Vipps_Agreement_Pricing() )
                    ->set_type( WC_Vipps_Agreement_Pricing::TYPE_LEGACY )
                    ->set_currency( $order->get_currency() )
                    ->set_amount( apply_filters( 'wc_vipps_recurring_agreement_pricing_amount', WC_Vipps_Recurring_Helper::get_vipps_amount( $agreement_total ), $order ) )
            )
            ->set_interval(
                ( new WC_Vipps_Agreement_Interval() )
                    ->set_unit( strtoupper( $subscription_period ) )
                    ->set_count( (int) $subscription_interval )
            )
            ->set_product_name( $subscription_item->get_name() )
            ->set_merchant_agreement_url( apply_filters( 'wc_vipps_recurring_merchant_agreement_url', $agreement_url ) )
            ->set_merchant_redirect_url( apply_filters( 'wc_vipps_recurring_merchant_redirect_url', $redirect_url ) );

        $product_description = WC_Vipps_Recurring_Helper::get_product_description( $product );
        if ( $product_description ) {
            $agreement = $agreement->set_product_description( $product_description );
        }

        // validate phone number and only add it if it's up to Vipps' standard to avoid errors
        if ( WC_Vipps_Recurring_Helper::is_valid_phone_number( $order->get_billing_phone() ) ) {
            $agreement = $agreement->set_phone_number( $order->get_billing_phone() );
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
                    ->set_amount( apply_filters( 'wc_vipps_recurring_agreement_initial_charge_amount', WC_Vipps_Recurring_Helper::get_vipps_amount( $order->get_total() ), $order ) )
                    ->set_description( empty( $initial_charge_description ) ? $subscription_item->get_name() : $initial_charge_description )
                    ->set_transaction_type( $capture_immediately ? WC_Vipps_Agreement_Initial_Charge::TRANSACTION_TYPE_DIRECT_CAPTURE : WC_Vipps_Agreement_Initial_Charge::TRANSACTION_TYPE_RESERVE_CAPTURE )
            );

            WC_Vipps_Recurring_Helper::update_meta_data( $order, $capture_immediately ? WC_Vipps_Recurring_Helper::META_ORDER_DIRECT_CAPTURE : WC_Vipps_Recurring_Helper::META_ORDER_RESERVED_CAPTURE, true );
        }

        if ( $has_campaign ) {
            $campaign_price = $has_free_campaign ? $sign_up_fee : $order->get_total();

            $campaign_type   = WC_Vipps_Agreement_Campaign::TYPE_PRICE_CAMPAIGN;
            $campaign_period = null;

            if ( $has_trial ) {
                $campaign_type     = WC_Vipps_Agreement_Campaign::TYPE_PERIOD_CAMPAIGN;
                $campaign_end_date = null;
                $campaign_period   = ( new WC_Vipps_Agreement_Campaign_Period() )
                    ->set_count( WC_Subscriptions_Product::get_trial_length( $product ) )
                    ->set_unit( strtoupper( WC_Subscriptions_Product::get_trial_period( $product ) ) );
            } else {
                $next_payment = WC_Subscriptions_Product::get_first_renewal_payment_date( $product );
                $end_date     = WC_Subscriptions_Product::get_expiration_date( $product );

                $campaign_end_date = $end_date === 0 ? $next_payment : $end_date;
            }

            $agreement = $agreement->set_campaign(
                ( new WC_Vipps_Agreement_Campaign() )
                    ->set_type( $campaign_type )
                    ->set_price( WC_Vipps_Recurring_Helper::get_vipps_amount( $campaign_price ) )
                    ->set_end( $campaign_end_date )
                    ->set_period( $campaign_period )
            );
        }

        $order->save();

        return apply_filters( 'wc_vipps_recurring_process_payment_agreement', $agreement, $subscription, $order );
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

        $subscription = null;

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

            // We need to update the gateway to Vipps/MobilePay after the payment is completed if an agreement is active
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
             * if this order has a PENDING or ACTIVE agreement in Vipps/MobilePay we should not allow checkout anymore
             * this will prevent duplicate transactions
             */
            $agreement_id              = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );
            $already_swapping_to_vipps = WC_Vipps_Recurring_Helper::get_meta( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_WAITING_FOR_GATEWAY_CHANGE );

            if ( $agreement_id && ( ! $is_gateway_change || $already_swapping_to_vipps ) && ! $is_failed_renewal_order ) {
                if ( ! $already_swapping_to_vipps ) {
                    $existing_agreement = $this->get_agreement_from_order( $order );
                } else {
                    $new_agreement_id   = WC_Vipps_Recurring_Helper::get_meta( $subscription, '_new_agreement_id' );
                    $existing_agreement = $this->api->get_agreement( $new_agreement_id );
                }

                if ( $existing_agreement->status === WC_Vipps_Agreement::STATUS_ACTIVE ) {
                    throw new WC_Vipps_Recurring_Temporary_Exception( __( 'This subscription is already active in Vipps/MobilePay. You can leave this page.', 'woo-vipps' ) );
                }
            }

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
                        // translators: %s: brand (Vipps or MobilePay)
                        wc_add_notice( sprintf( __( 'Different subscription products can not be purchased at the same time using %s.', 'woo-vipps' ), $this->title ), 'error' );

                        return [
                            'result'   => 'fail',
                            'redirect' => ''
                        ];
                    }

                    $counter ++;
                }
            }

            $agreement = $this->create_vipps_agreement_from_order( $order, $subscription, $is_gateway_change );

            $idempotency_key = $this->get_idempotency_key( $order );
            if ( $is_gateway_change ) {
                $idempotency_key = $this->api->generate_idempotency_key();
            }

            $response = $this->api->create_agreement( $agreement, $idempotency_key );

            $is_subscription_switch = wcs_order_contains_switch( $order );
            $is_zero_amount         = (int) $order->get_total() === 0 || $is_gateway_change;

            // mark the old agreement for cancellation to leave no dangling agreements in Vipps
            $should_cancel_old = $is_gateway_change || $is_subscription_switch || $is_failed_renewal_order;
            if ( $should_cancel_old ) {
                if ( $is_gateway_change ) {
                    /* translators: Vipps/MobilePay Agreement ID */
                    $message = sprintf( __( 'Request to change gateway to Vipps/MobilePay with agreement ID: %s.', 'woo-vipps' ), $response['agreementId'] );
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

                /* translators: Vipps/MobilePay Agreement ID */
                $message = sprintf( __( 'Agreement created: %s. Customer sent to Vipps/MobilePay for confirmation.', 'woo-vipps' ), $response['agreementId'] );
                $order->add_order_note( $message );
            }

            $debug_msg .= sprintf( 'Created agreement with agreement ID: %s', $response['agreementId'] ) . "\n";

            if ( isset( $response['chargeId'] ) ) {
                WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID, $response['chargeId'] );
            }

            WC_Vipps_Recurring_Helper::delete_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_IS_CHECKOUT );
            WC_Vipps_Recurring_Helper::delete_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_IS_EXPRESS );
            WC_Vipps_Recurring_Helper::delete_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_ORDER_IS_CHECKOUT );
            WC_Vipps_Recurring_Helper::delete_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_ORDER_IS_EXPRESS );

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
                'vippsmobilepay' => '<img src="' . WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/images/' . $this->brand . '-mark.svg" class="vipps-recurring-icon" alt="' . $this->title . '" />',
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

        return apply_filters( 'woocommerce_gateway_icon', $icons['vippsmobilepay'], $this->id );
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

        // Set some options and default values
        $this->form_fields['brand']['default'] = $this->detect_default_brand();

        $this->form_fields['order_prefix']['default'] = WC_Vipps_Recurring::get_instance()->generate_order_prefix();

        if ( $this->get_option( 'test_mode' ) === "yes" || WC_VIPPS_RECURRING_TEST_MODE ) {
            $this->form_fields['title_test_api'] = [
                'type'  => 'title',
                'title' => __( 'Test API settings', 'woo-vipps' ),
            ];

            $this->form_fields['test_merchant_serial_number'] = $this->form_fields['merchant_serial_number'];
            $this->form_fields['test_client_id']              = $this->form_fields['client_id'];
            $this->form_fields['test_secret_key']             = $this->form_fields['secret_key'];
            $this->form_fields['test_subscription_key']       = $this->form_fields['subscription_key'];
        }
    }

    function validate_text_field( $key, $value ) {
        if ( $key !== 'order_prefix' ) {
            return parent::validate_text_field( $key, $value );
        }

        return preg_replace( '![^a-zA-Z0-9\-]!', '', $value );
    }

    public function detect_default_brand(): string {
        $locale         = get_locale();
        $store_location = wc_get_base_location();
        $store_country  = $store_location['country'] ?? '';
        $currency       = get_woocommerce_currency();

        $default_brand = "mobilepay";

        // If store location, locale, or currency is Norwegian, use Vipps
        if ( $store_country == "NO" || preg_match( "/_NO/", $locale ) || $currency == "NOK" ) {
            $default_brand = "vipps";
        }

        return $default_brand;
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
    private function admin_notify( $what, $type = "info" ): void {
        add_action( 'admin_notices', static function () use ( $what, $type ) {
            echo "<div class='notice notice-$type is-dismissible'><p>$what</p></div>";
        } );
    }

    public function process_admin_options(): bool {
        $saved = parent::process_admin_options();
        delete_transient( '_vipps_keyset' ); // Same transient as for the payment api IOK 2024-12-03

        $this->init_form_fields();

        if ( $this->get_option( 'enabled' ) === "yes" ) {
            $this->webhook_initialize();

            try {
                $this->api->get_access_token( true );
                update_option( WC_Vipps_Recurring_Helper::OPTION_CONFIGURED, 1, true );

                $this->admin_notify( __( 'Successfully authenticated with the Vipps/MobilePay API', 'woo-vipps' ) );
            } catch ( Exception $e ) {
                update_option( WC_Vipps_Recurring_Helper::OPTION_CONFIGURED, 0, true );

                /* translators: %s: the error message returned from Vipps/MobilePay */
                $this->admin_error( sprintf( __( 'Could not authenticate with the Vipps/MobilePay API: %s', 'woo-vipps' ), $e->getMessage() ) );
            }
        }

        $checkout_enabled = $this->get_option( 'checkout_enabled' ) === "yes";
        $test_mode        = $this->get_option( 'test_mode' ) === "yes" || WC_VIPPS_RECURRING_TEST_MODE;

        // Validate that we have an MSN set, as it is required in Checkout.
        $merchant_serial_number = $test_mode ? $this->get_option( 'test_merchant_serial_number' ) : $this->get_option( 'merchant_serial_number' );
        if ( empty( $merchant_serial_number ) ) {
            $this->admin_notify( __( 'You need to provide a Merchant Serial Number before you can enable Checkout.', 'woo-vipps' ), "error" );

            // Disable checkout if we do not have an MSN value.
            $this->update_option( 'checkout_enabled', 'no' );
            update_option( WC_Vipps_Recurring_Helper::OPTION_CHECKOUT_ENABLED, false, true );

            return $saved;
        }

        update_option( WC_Vipps_Recurring_Helper::OPTION_CHECKOUT_ENABLED, $checkout_enabled, true );

        if ( $checkout_enabled ) {
            WC_Vipps_Recurring::get_instance()->maybe_create_checkout_page();
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
                    'value' => WC_Vipps_Recurring_Helper::get_meta( $subscription, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID ),
                    'label' => __( 'Vipps/MobilePay Agreement ID', 'woo-vipps' ),
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
            throw new \RuntimeException( __( 'This Vipps/MobilePay agreement ID is invalid.', 'woo-vipps' ) );
        }
    }

    public function cart_needs_payment( $needs_payment, $cart ) {
        $cart_switch_items = wcs_cart_contains_switches();

        if ( false === $needs_payment && 0 == $cart->total && false !== $cart_switch_items && ! wcs_is_manual_renewal_required() ) {
            foreach ( $cart_switch_items as $cart_switch_details ) {
                $subscription = wcs_get_subscription( $cart_switch_details['subscription_id'] );

                if ( $this->id === $subscription->get_payment_method() ) {
                    $needs_payment = true;
                    break;
                }
            }
        }

        return $needs_payment;
    }

    /**
     * @param $subscription
     */
    public function handle_subscription_switch_completed( $subscription ): void {
        $payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $subscription );
        if ( $this->id !== $payment_method ) {
            return;
        }

        WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Subscription switch completed", WC_Vipps_Recurring_Helper::get_id( $subscription ) ) );

        WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP, 1 );
        WC_Vipps_Recurring_Helper::delete_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP_DESCRIPTION_PREFIX );
        $subscription->save();

        try {
            $this->maybe_update_subscription_details_in_app( WC_Vipps_Recurring_Helper::get_id( $subscription ) );
        } catch ( Exception $exception ) {
            // do nothing, we don't want to throw an error in the user's face
        }
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
                    __( 'Pending cancellation', 'woo-vipps' )
                );

                $order->save();
            }

            if ( $new_status === 'cancelled' ) {
                WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP, 1 );
                WC_Vipps_Recurring_Helper::update_meta_data(
                    $order,
                    WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP_DESCRIPTION_PREFIX,
                    __( 'Cancelled', 'woo-vipps' )
                );

                $order->save();
            }

            if ( $new_status === 'on-hold' ) {
                WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP, 1 );
                WC_Vipps_Recurring_Helper::update_meta_data(
                    $order,
                    WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP_DESCRIPTION_PREFIX,
                    __( 'On hold', 'woo-vipps' )
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
     * Don't transfer Vipps/MobilePay idempotency key or any other keys unique to a charge or order to resubscribe orders.
     *
     * @param mixed $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
     */
    public function delete_resubscribe_meta( $resubscribe_order ): void {
        WC_Vipps_Recurring_Helper::delete_meta_data( $resubscribe_order, WC_Vipps_Recurring_Helper::META_CHARGE_ID );
        WC_Vipps_Recurring_Helper::delete_meta_data( $resubscribe_order, WC_Vipps_Recurring_Helper::META_CHARGE_CAPTURED );

        WC_Vipps_Recurring_Helper::delete_meta_data( $resubscribe_order, WC_Vipps_Recurring_Helper::META_ORDER_INITIAL );
        WC_Vipps_Recurring_Helper::delete_meta_data( $resubscribe_order, WC_Vipps_Recurring_Helper::META_ORDER_IS_EXPRESS );
        WC_Vipps_Recurring_Helper::delete_meta_data( $resubscribe_order, WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN );

        $this->delete_renewal_meta( $resubscribe_order );
    }

    /**
     * Don't transfer Vipps/MobilePay idempotency key or any other keys unique to a charge or order to renewal orders.
     *
     * @param mixed $renewal_order The renewal order
     */
    public function delete_renewal_meta( WC_Order $renewal_order ) {
        // Do not delete the idempotency key if the order has failed previously
        $has_failed_previously = WC_Vipps_Recurring_Helper::get_meta( $renewal_order, '_failed_renewal_order' );
        if ( $has_failed_previously !== "yes" ) {
            WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_ORDER_IDEMPOTENCY_KEY );
        }

        WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_CHARGE_FAILED );
        WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_CHARGE_FAILED_DESCRIPTION );
        WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_CHARGE_FAILED_REASON );
        WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_CHARGE_LATEST_STATUS );
        WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP );
        WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP_DESCRIPTION_PREFIX );
        WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_CHARGE_CAPTURED );

        WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_ORDER_INITIAL );
        WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_ORDER_IS_EXPRESS );
        WC_Vipps_Recurring_Helper::delete_meta_data( $renewal_order, WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN );

        $renewal_order->save();

        return $renewal_order;
    }

    /**
     * @param $order_id
     */
    public function maybe_cancel_due_charge( $order_id ): void {
        $order = wc_get_order( $order_id );

        $payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $order );
        if ( $this->id !== $payment_method ) {
            return;
        }

        $agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );
        $charge_id    = WC_Vipps_Recurring_Helper::get_charge_id_from_order( $order );

        if ( $agreement_id === null || $charge_id === null ) {
            WC_Vipps_Recurring_Helper::delete_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING );

            return;
        }

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
                    $order->add_order_note( __( 'Cancelled due charge in Vipps/MobilePay.', 'woo-vipps' ) );
                    WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Cancelled DUE charge with ID: %s for agreement with ID: %s', $order_id, $charge_id, $agreement_id ) );
                }
            } catch ( Exception $e ) {
                $order->add_order_note( __( 'Could not cancel charge in Vipps/MobilePay. Please manually check the status of this order if you plan to process a new renewal order!', 'woo-vipps' ) );
                WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Failed cancelling DUE charge with ID: %s for agreement with ID: %s. Error: %s', $order_id, $charge_id, $agreement_id, $e->getMessage() ) );
            }
        }
    }

    /**
     * When renewing early from a different gateway WooCommerce does not update the gateway for you.
     * If we detect that you've renewed early with Vipps/MobilePay and the gateway is not set to Vipps/MobilePay we will
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

    public function update_agreement_price_in_app( $and_taxes, $subscription ) {
        if ( ! wcs_is_subscription( $subscription ) ) {
            return;
        }

        $payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $subscription );
        if ( $this->id !== $payment_method ) {
            return;
        }

        WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Totals were recalculated. Marking subscription for update in the Vipps/MobilePay app.', WC_Vipps_Recurring_Helper::get_id( $subscription ) ) );
        WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP, 1 );
        $subscription->save();
    }

    /**
     * Initialize webhooks if we don't already have one
     *
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     * @throws WC_Vipps_Recurring_Config_Exception
     */
    private function webhook_initialize_initial() {
        $msn = $this->merchant_serial_number;

        // We cannot use webhooks without the MSN being set.
        if ( empty( $msn ) ) {
            return;
        }

        $webhooks       = $this->api->get_webhooks();
        $local_webhooks = $this->webhook_get_local();
        $callback_url   = $this->webhook_callback_url();

        // Delete webhooks that are not for our MSN + hostname combination. This prevents us from conflicting with Pay with WooCommerce for Vipps and MobilePay
        $deletion_tracker = [];

        $webhook_initialized = false;

        foreach ( $webhooks['webhooks'] as $webhook ) {
            if ( ! isset( $local_webhooks[ $msn ][ $webhook['id'] ] ) ) {
                // this webhook is not created by us, so we can continue
                continue;
            }

            // If we have multiple webhooks for our MSN we can delete the other ones. We do not want duplicates.
            if ( $webhook_initialized ) {
                $deletion_tracker[] = $webhook['id'];
            }

            $webhook_initialized = true;
        }

        // Create webhook and save it to WC_Vipps_Recurring_Helper::OPTION_WEBHOOKS
        if ( ! $webhook_initialized ) {
            $response = $this->api->register_webhook();

            $local_webhooks[ $msn ][ $response['id'] ] = [
                'secret' => $response['secret'],
                'url'    => $callback_url
            ];
        }

        // Delete superfluous webhooks
        if ( ! empty( $deletion_tracker ) ) {
            foreach ( $deletion_tracker as $id ) {
                try {
                    $this->api->delete_webhook( $id );
                    unset( $local_webhooks[ $msn ][ $id ] );
                } catch ( Exception $e ) {
                    WC_Vipps_Recurring_Logger::log( sprintf( 'Failed to delete webhook %s. Error: %s', $id, $e->getMessage() ) );
                }
            }
        }

        update_option( WC_Vipps_Recurring_Helper::OPTION_WEBHOOKS, $local_webhooks );
    }

    /**
     * Check that our local webhooks point to this site.
     * If they don't we should delete them, otherwise we end up with hitting the limit of 5.
     *
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     * @throws WC_Vipps_Recurring_Config_Exception
     */
    public function webhook_ensure_this_site(): bool {
        $msn = $this->merchant_serial_number;

        // We cannot use webhooks without the MSN being set.
        if ( empty( $msn ) ) {
            return false;
        }

        $local_webhooks    = $this->webhook_get_local();
        $callback_url      = $this->webhook_callback_url();
        $callback_url_base = strtok( $callback_url, "?" );

        // Make sure we don't do this more than once every 30 minutes, and only if the callback url base changes.
        $webhook_transient = "vipps-recurring-webhooks-site";
        if ( get_transient( $webhook_transient ) === $callback_url_base ) {
            return true;
        }

        set_transient( $webhook_transient, $callback_url_base, 1800 );

        if ( empty( $local_webhooks[ $msn ] ) ) {
            return false;
        }

        $remote_webhooks = $this->api->get_webhooks();

        $ok = true;
        foreach ( $local_webhooks[ $msn ] as $webhook_id => $webhook ) {
            $webhook_base_url = strtok( $webhook['url'], "?" );

            if ( $webhook_base_url === $callback_url_base ) {
                continue;
            }

            // Check that this webhookId indeed exists in $remote_webhooks before we attempt to delete it
            $webhook_exists = ! empty( array_filter( $remote_webhooks['webhooks'], function ( $remote_webhook ) use ( $webhook_id ) {
                return $remote_webhook['id'] === $webhook_id;
            } ) );

            if ( $webhook_exists ) {
                $this->api->delete_webhook( $webhook_id );
            }

            unset( $local_webhooks[ $msn ][ $webhook_id ] );

            $ok = false;
        }

        update_option( WC_Vipps_Recurring_Helper::OPTION_WEBHOOKS, $local_webhooks );

        return $ok;
    }

    /**
     * Delete all webhooks during uninstall for this MSN
     *
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     * @throws WC_Vipps_Recurring_Config_Exception
     */
    public function webhook_teardown() {
        $msn = $this->merchant_serial_number;

        if ( empty( $msn ) ) {
            return;
        }

        $local_webhooks = $this->webhook_get_local();

        if ( empty( $local_webhooks[ $msn ] ) ) {
            return;
        }

        $remote_webhooks = $this->api->get_webhooks();

        // We can have multiple MSNs, hence the nested loop
        foreach ( $local_webhooks as $webhooks ) {
            foreach ( $webhooks as $webhook_id => $webhook ) {
                // Check that this webhookId indeed exists in $remote_webhooks before we attempt to delete it
                $webhook_exists = ! empty( array_filter( $remote_webhooks['webhooks'], function ( $remote_webhook ) use ( $webhook_id ) {
                    return $remote_webhook['id'] === $webhook_id;
                } ) );

                if ( ! $webhook_exists ) {
                    continue;
                }

                $this->api->delete_webhook( $webhook_id );
            }
        }

        delete_option( WC_Vipps_Recurring_Helper::OPTION_WEBHOOKS );
    }

    public function webhook_get_local(): array {
        return get_option( WC_Vipps_Recurring_Helper::OPTION_WEBHOOKS, [ $this->merchant_serial_number => [] ] );
    }

    public function webhook_initialize() {
        try {
            $this->webhook_ensure_this_site();
            $this->webhook_initialize_initial();
        } catch ( Exception $e ) {
            WC_Vipps_Recurring_Logger::log( sprintf( 'Failed to initialize webhooks. Error: %s', $e->getMessage() ) );
        }
    }

    public function webhook_callback_url(): string {
        $base_url = home_url( '/', 'https' );

        $args   = [ 'callback' => 'webhook' ];
        $action = 'wc_gateway_vipps_recurring';

        if ( ! get_option( 'permalink_structure' ) ) {
            $args['wc-api'] = $action;
        } else {
            $base_url = trailingslashit( home_url( "wc-api/$action", 'https' ) );
        }

        return add_query_arg( $args, $base_url );
    }

    private function maybe_get_subscription_from_agreement_webhook( array $webhook_data ): ?WC_Subscription {
        if ( isset( $webhook_data['agreementId'] ) ) {
            $agreement_id = $webhook_data['agreementId'];

            $subscriptions = wcs_get_subscriptions( [
                'subscriptions_per_page' => 1,
                'subscription_status'    => [ 'active', 'pending', 'on-hold' ],
                'meta_query'             => [
                    [
                        'key'     => WC_Vipps_Recurring_Helper::META_AGREEMENT_ID,
                        'compare' => '=',
                        'value'   => $agreement_id
                    ]
                ]
            ] );

            if ( ! empty( $subscriptions ) ) {
                return array_pop( $subscriptions );
            }
        }

        // Otherwise we were unable to find the subscription
        return null;
    }

    /**
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     * @throws WC_Vipps_Recurring_Config_Exception
     */
    public function handle_webhook_callback( array $webhook_data ): void {
        WC_Vipps_Recurring_Logger::log( sprintf( "Handling webhook with data: %s", json_encode( $webhook_data ) ) );

        $event_type = $webhook_data['eventType'];

        if ( in_array( $event_type, [
            'recurring.charge-reserved.v1',
            'recurring.charge-captured.v1',
            'recurring.charge-canceled.v1',
            'recurring.charge-failed.v1',
        ] ) ) {
			$order_id = null;

			$orders = wc_get_orders( [
				'limit'          => 1,
				'meta_query'     => [
					[
						'key'     => WC_Vipps_Recurring_Helper::META_CHARGE_ID,
						'compare' => '=',
						'value'   => $webhook_data['chargeId']
					]
				],
				'payment_method' => $this->id,
				'order_by'       => 'post_date'
			] );

			$order = array_pop( $orders );

			if ( $order ) {
				$order_id = WC_Vipps_Recurring_Helper::get_id( $order );
			}

            if ( empty( $order_id ) ) {
                return;
            }

            $this->check_charge_status( $order_id );
        }

        // Customers can now cancel their agreements directly from the app.
        if ( $event_type === 'recurring.agreement-stopped.v1' ) {
            // If the initiator of this webhook is ourselves, we must discard it.
            // Otherwise, we risk cancelling payment gateway changes etc.
            if ( $webhook_data['actor'] === 'MERCHANT' ) {
                return;
            }

            $subscription = $this->maybe_get_subscription_from_agreement_webhook( $webhook_data );

            if ( empty( $subscription ) ) {
                return;
            }

            $message = __( 'Subscription cancelled by the customer via the Vipps MobilePay app.', 'woo-vipps' );
            $subscription->set_status( 'cancelled', $message );
            $subscription->save();
        }
    }

    public function cart_supports_checkout( $cart = null ) {
        if ( ! $cart ) {
            $cart = WC()->cart;
        }

        if ( ! $cart ) {
            return false;
        }

        # Not supported by Vipps MobilePay Checkout
        if ( $cart->cart_contents_total <= 0 ) {
            return false;
        }

        $supports = WC_Vipps_Recurring::get_instance()->gateway_should_be_active();

        return apply_filters( 'wc_vipps_recurring_cart_supports_checkout', $supports, $cart );
    }

    public function checkout_is_available() {
        if ( ! $this->checkout_enabled
             || ! $this->is_available()
             || ! $this->cart_supports_checkout() ) {
            return false;
        }

        $checkout_id = wc_get_page_id( 'vipps_recurring_checkout' );
        if ( ! $checkout_id ) {
            return false;
        }

        if ( ! get_post_status( $checkout_id ) ) {
            delete_option( 'woocommerce_vipps_recurring_checkout_page_id' );

            return false;
        }

        return apply_filters( 'wc_vipps_recurring_checkout_available', $checkout_id, $this );
    }

    // Creating a partial order & subscription
    // Heavily based on the single payments plugins logic. The comments are also copied for readability.
    /**
     * @throws WC_Data_Exception
     * @throws Exception
     */
    public function create_partial_order( $checkout = false ): int {
        $order_id = apply_filters( 'woocommerce_create_order', null );
        if ( $order_id ) {
            return $order_id;
        }

        // This is necessary for some plugins, like YITH Dynamic Pricing, that adds filters to get_price depending on whether is_checkout is true.
        // so basically, since we are impersonating WC_Checkout here, we should define this constant too
        wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

        // In *some* cases you may need to actually load classes and reload the cart, because some plugins do not load when DOING_AJAX.
        do_action( 'wc_vipps_recurring_express_checkout_before_calculate_totals' );

        WC()->cart->calculate_fees();
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
        do_action( 'wc_vipps_recurring_before_create_express_checkout_order', WC()->cart );

        $order_id  = absint( WC()->session->get( 'order_awaiting_payment' ) );
        $cart_hash = WC()->cart->get_cart_hash();
        $order     = $order_id ? wc_get_order( $order_id ) : null;

        /**
         * If there is an order pending payment, we can resume it here so
         * long as it has not changed. If the order has changed, i.e.
         * different items or cost, create a new order. We use a hash to
         * detect changes which is based on cart items + order total.
         */
        if ( $order && $order->has_cart_hash( $cart_hash ) && $order->has_status( array( 'pending', 'failed' ) ) ) {
            /**
             * Indicates that we are resuming checkout for an existing order (which is pending payment, and which
             * has not changed since it was added to the current shopping session).
             *
             * @param int $order_id The ID of the order being resumed.
             *
             * @since 3.0.0 or earlier
             *
             */
            do_action( 'woocommerce_resume_order', $order_id );

            // Remove all items - we will re-add them later.
            $order->remove_order_items();
        } else {
            $order = new WC_Order();
        }

        // We store this in the order, so we don't have to access the cart when initiating payment. This allows us to restart orders etc.
        $needs_shipping = WC()->cart->needs_shipping();

        $order->set_payment_method( $this );

        try {
            if ( $checkout ) {
                $order->update_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_IS_CHECKOUT, 1 );
                $order->set_payment_method_title( 'Vipps/MobilePay Recurring Checkout' );
            } else {
                $order->set_payment_method_title( 'Vipps/MobilePay Recurring Express Checkout' );
            }

            // We use 'checkout' as the created_via key as per requests, but allow merchants to use their own.
            $created_via = apply_filters( 'wc_vipps_recurring_express_checkout_created_via', 'checkout', $order, $checkout );

            $order->set_cart_hash( $cart_hash );
            $order->set_created_via( $created_via );

            $order->update_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_IS_EXPRESS, 1 );

            # To help with address fields, scope etc in initiate payment
            $order->update_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_NEEDS_SHIPPING, $needs_shipping );

            $order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
            $order->set_currency( get_woocommerce_currency() );
            $order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
            $order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
            $order->set_customer_user_agent( wc_get_user_agent() );
            $order->set_discount_total( WC()->cart->get_discount_total() );
            $order->set_discount_tax( WC()->cart->get_discount_tax() );
            $order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );

            // Use these methods directly - they should be safe.
            WC()->checkout->create_order_line_items( $order, WC()->cart );
            WC()->checkout->create_order_fee_lines( $order, WC()->cart );
            WC()->checkout->create_order_tax_lines( $order, WC()->cart );
            WC()->checkout->create_order_coupon_lines( $order, WC()->cart );

            if ( $needs_shipping ) {
                WC()->checkout->create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping->get_packages() );
            }

            do_action( 'wc_vipps_recurring_before_calculate_totals_partial_order', $order );
            $order->calculate_totals();

            // Added to support third-party plugins that wants to do stuff with the order before it is saved.
            do_action( 'woocommerce_checkout_create_order', $order, array() );

            $order_id = $order->save();

            do_action( 'wc_vipps_recurring_express_checkout_order_created', $order_id );

            // Normally done by the WC_Checkout::create_order method, so call it here too.
            do_action( 'woocommerce_checkout_update_order_meta', $order_id, array() );

//			// It isn't possible to remove the javascript or 'after order notice' actions, because these are added as closures
//			// before anything else is run. But we can disable the hook that saves data.
//			if (WC_Gateway_Vipps::instance()->get_option('vippsorderattribution') != 'yes') {
            remove_all_filters( 'woocommerce_order_save_attribution_data' );
//			}

            do_action( 'woocommerce_checkout_order_created', $order );
        } catch ( Exception $exception ) {
            if ( $order instanceof WC_Order ) {
                $order->get_data_store()->release_held_coupons( $order );
                do_action( 'woocommerce_checkout_order_exception', $order );
            }

            // Any errors gets passed upstream
            throw $exception;
        }

        return $order_id;
    }

    /**
     * @throws Exception
     */
    public function create_or_get_anonymous_system_customer(): WC_Customer {
        $customer_id = get_option( WC_Vipps_Recurring_Helper::OPTION_ANONYMOUS_SYSTEM_CUSTOMER_ID );

        // Create a user if it does not exist
        if ( ! get_user_by( 'ID', $customer_id ) ) {
            $email       = WC_Vipps_Recurring_Helper::FAKE_USER_EMAIL;
            $username    = wc_create_new_customer_username( $email );
            $customer_id = wc_create_new_customer( $email, $username, null, [
                'first_name'    => 'Anonymous Vipps MobilePay Customer',
                'user_nicename' => __( 'Anonymous Vipps MobilePay Customer', 'woo-vipps' ),
            ] );

            update_option( WC_Vipps_Recurring_Helper::OPTION_ANONYMOUS_SYSTEM_CUSTOMER_ID, $customer_id );
        }

        return new WC_Customer( $customer_id );
    }

    public function create_partial_subscription_groups_from_order( WC_Order $order ): array {
        $subscription_groups = [];

        // Group the order items into subscription groups.
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();

            if ( ! WC_Subscriptions_Product::is_subscription( $product ) ) {
                continue;
            }

            $subscription_groups[ wcs_get_subscription_item_grouping_key( $item ) ][] = $item;
        }

        return $subscription_groups;
    }

    /**
     * Based on WC_REST_Subscriptions_Controller::create_subscriptions_from_order
     *
     * @throws Exception
     */
    public function create_partial_subscriptions_from_order( WC_Order $order ) {
        $order_id = WC_Vipps_Recurring_Helper::get_id( $order );

        if ( ! $order->get_customer_id() ) {
            return false;
        }

        if ( wcs_order_contains_subscription( $order, 'any' ) ) {
            return false;
        }

        $subscriptions       = [];
        $subscription_groups = $this->create_partial_subscription_groups_from_order( $order );

        if ( empty( $subscription_groups ) ) {
            return false;
        }

        foreach ( $subscription_groups as $items ) {
            // Get the first item in the group to use as the base for the subscription.
            $product = $items[0]->get_product();

            $start_date   = wcs_get_datetime_utc_string( $order->get_date_created( 'edit' ) );
            $subscription = wcs_create_subscription( [
                'order_id'         => $order_id,
                'created_via'      => $order->get_created_via( 'edit' ),
                'start_date'       => $start_date,
                'status'           => $order->is_paid() ? 'active' : 'pending',
                'billing_period'   => WC_Subscriptions_Product::get_period( $product ),
                'billing_interval' => WC_Subscriptions_Product::get_interval( $product ),
                'customer_note'    => $order->get_customer_note(),
            ] );

            if ( is_wp_error( $subscription ) ) {
                throw new Exception( $subscription->get_error_message() );
            }

            wcs_copy_order_address( $order, $subscription );

            $subscription->update_dates( [
                'trial_end'    => WC_Subscriptions_Product::get_trial_expiration_date( $product, $start_date ),
                'next_payment' => WC_Subscriptions_Product::get_first_renewal_payment_date( $product, $start_date ),
                'end'          => WC_Subscriptions_Product::get_expiration_date( $product, $start_date ),
            ] );

            $subscription->set_payment_method( $order->get_payment_method() );

            wcs_copy_order_meta( $order, $subscription, 'subscription' );

            // Add items.
            $subscription_needs_shipping = false;
            foreach ( $items as $item ) {
                // Create order line item.
                $item_id = wc_add_order_item(
                    $subscription->get_id(),
                    [
                        'order_item_name' => $item->get_name(),
                        'order_item_type' => $item->get_type(),
                    ]
                );

                $subscription_item = $subscription->get_item( $item_id );

                wcs_copy_order_item( $item, $subscription_item );

                // Don't include sign-up fees or $0 trial periods when setting the subscriptions item totals.
                wcs_set_recurring_item_total( $subscription_item );

                $subscription_item->save();

                // Check if this subscription will need shipping.
                if ( ! $subscription_needs_shipping ) {
                    $product = $item->get_product();

                    if ( $product ) {
                        $subscription_needs_shipping = $product->needs_shipping() && ! WC_Subscriptions_Product::needs_one_time_shipping( $product );
                    }
                }
            }

            // Add coupons.
            foreach ( $order->get_coupons() as $coupon_item ) {
                $coupon = new WC_Coupon( $coupon_item->get_code() );

                try {
                    // validate_subscription_coupon_for_order will throw an exception if the coupon cannot be applied to the subscription.
                    WC_Subscriptions_Coupon::validate_subscription_coupon_for_order( true, $coupon, $subscription );

                    $subscription->apply_coupon( $coupon->get_code() );
                } catch ( Exception $e ) {
                    // Do nothing. The coupon will not be applied to the subscription.
                }
            }

            // Add shipping.
            if ( $subscription_needs_shipping ) {
                foreach ( $order->get_shipping_methods() as $shipping_item ) {
                    $rate = new WC_Shipping_Rate( $shipping_item->get_method_id(), $shipping_item->get_method_title(), $shipping_item->get_total(), $shipping_item->get_taxes(), $shipping_item->get_instance_id() );

                    $item = new WC_Order_Item_Shipping();
                    $item->set_order_id( $subscription->get_id() );
                    $item->set_shipping_rate( $rate );

                    $subscription->add_item( $item );
                }
            }

            // Add fees.
            foreach ( $order->get_fees() as $fee_item ) {
                if ( ! apply_filters( 'wcs_should_copy_fee_item_to_subscription', true, $fee_item, $subscription, $order ) ) {
                    continue;
                }

                $item = new WC_Order_Item_Fee();
                $item->set_props(
                    array(
                        'name'      => $fee_item->get_name(),
                        'tax_class' => $fee_item->get_tax_class(),
                        'amount'    => $fee_item->get_amount(),
                        'total'     => $fee_item->get_total(),
                        'total_tax' => $fee_item->get_total_tax(),
                        'taxes'     => $fee_item->get_taxes(),
                    )
                );

                $subscription->add_item( $item );
            }

            // Remove this action to avoid making an unnecessary API request
            remove_action( 'woocommerce_order_after_calculate_totals', [
                $this,
                'update_agreement_price_in_app'
            ] );

            $subscription->calculate_totals();
            $subscription->save();

            $subscriptions[] = wcs_get_subscription( $subscription->get_id() );
        }

        return $subscriptions;
    }

    public function maybe_delete_order_later( $order_id ) {
        if ( $this->get_option( 'checkout_cleanup_abandoned_orders' ) !== 'yes' ) {
            return;
        }

        if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_delete_pending_order', [ $order_id ] ) ) {
            wp_schedule_single_event( time() + 3600, 'woocommerce_vipps_recurring_delete_pending_order', [ $order_id ] );
        }
    }

    public function maybe_delete_order( $order_id ): bool {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        if ( $this->id !== $order->get_payment_method() ) {
            return false;
        }

        $express = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_IS_EXPRESS );
        if ( ! $express ) {
            return false;
        }

        $empty_email = $order->get_billing_email() === WC_Vipps_Recurring_Helper::FAKE_USER_EMAIL || ! $order->get_billing_email();
        if ( ! $empty_email ) {
            return false;
        }

        if ( $this->get_option( 'checkout_cleanup_abandoned_orders' ) !== 'yes' ) {
            return false;
        }

        WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_MARKED_FOR_DELETION, 1 );
        $order->save();

        return true;
    }
}
