<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring {
    /**
     * The reference the *Singleton* instance of this class
     */
    private static ?WC_Vipps_Recurring $instance = null;

    public WC_Vipps_Recurring_Admin_Notices $notices;

    public array $ajax_config = [];

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return WC_Vipps_Recurring
     */
    public static function get_instance(): WC_Vipps_Recurring {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function register_hooks() {
        $instance = WC_Vipps_Recurring::get_instance();

        // No longer handled directly here
        // register_activation_hook( WC_VIPPS_MAIN_FILE, [ $instance, 'activate' ] );
        // register_deactivation_hook( WC_VIPPS_MAIN_FILE, [ $instance, 'deactivate' ] );

        if ( is_admin() ) {
            add_action( 'admin_init', [ $instance, 'admin_init' ] );
            add_action( 'admin_menu', [ $instance, 'admin_menu' ] );
            add_action( 'wp_ajax_vipps_recurring_force_check_charge_statuses', [
                $instance,
                'wp_ajax_vipps_recurring_force_check_charge_statuses'
            ] );
        }

        add_action( 'plugins_loaded', [ $instance, 'plugins_loaded' ] );
        // Declare compatibility with the WooCommerce checkout block
        add_action( 'woocommerce_blocks_loaded', [ $instance, 'woocommerce_blocks_loaded' ] );
        add_action( 'init', [ $instance, 'init' ] );
    }

    public function activate() {
        global $wp_rewrite;

        $this->install();

        $wp_rewrite->flush_rules();
        add_option( 'woo-vipps-recurring-version', WC_VIPPS_RECURRING_VERSION );
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone() {
    }

    /**
     * Private un-serialize method to prevent un-serializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    public function __wakeup() {
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    private function __construct() {
    }

    /**
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     * @throws WC_Vipps_Recurring_Config_Exception
     */
    public static function deactivate() {
        WC_Vipps_Recurring::get_instance()->gateway()->webhook_teardown();
    }

    public function plugins_loaded() {
        add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );

        // Add custom product settings for Vipps Recurring.
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'woocommerce_product_data_tabs' ] );
        add_filter( 'woocommerce_product_data_panels', [ $this, 'woocommerce_product_data_panels' ] );
        add_filter( 'woocommerce_process_product_meta', [ $this, 'woocommerce_process_product_meta' ] );

        // Disable this gateway unless we're purchasing at least one subscription product.
        add_filter( 'woocommerce_available_payment_gateways', [ $this, 'maybe_disable_gateway' ] );

        add_action( 'woocommerce_api_wc_gateway_vipps_recurring', [ $this, 'handle_webhook_callback' ] );

        // Create a real page for Vipps Checkout
        add_filter( 'woocommerce_create_pages', [ $this, 'woocommerce_create_pages' ], 50 );

    }

    // Runs possibly before plugins_loaded
    public function woocommerce_blocks_loaded() {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            require_once 'wc-vipps-recurring-blocks-support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new WC_Vipps_Recurring_Blocks_Support() );
                }
            );
        }
    }

    /**
     * Init the plugin after plugins_loaded so environment variables are set.
     *
     * @since 1.0.0
     * @version 4.0.0
     */
    public function init() {
        require_once __DIR__ . '/wc-vipps-recurring-rest-api.php';
        WC_Vipps_Recurring_Rest_Api::get_instance();

        $this->notices = WC_Vipps_Recurring_Admin_Notices::get_instance( WC_VIPPS_RECURRING_MAIN_FILE );

        add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );

        add_filter( 'plugin_action_links_' . plugin_basename( WC_VIPPS_MAIN_FILE ), [
            $this,
            'plugin_action_links'
        ] );

        // Add custom cron schedules for Vipps/MobilePay charge polling
        add_filter( 'cron_schedules', [
            $this,
            'woocommerce_vipps_recurring_add_cron_schedules'
        ] );

        // schedule recurring payment charge status checking event
        if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_check_order_statuses' ) ) {
            wp_schedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_check_order_statuses' );
        }

        add_action( 'woocommerce_vipps_recurring_check_order_statuses', [
            $this,
            'check_order_statuses'
        ] );

        // Schedule checking if gateway change went through
        if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_check_gateway_change_request' ) ) {
            wp_schedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_check_gateway_change_request' );
        }

        add_action( 'woocommerce_vipps_recurring_check_gateway_change_request', [
            $this,
            'check_gateway_change_agreement_statuses'
        ] );

        // Schedule checking for updating payment details
        if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_update_subscription_details_in_app' ) ) {
            wp_schedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_update_subscription_details_in_app' );
        }

        add_action( 'woocommerce_vipps_recurring_update_subscription_details_in_app', [
            $this,
            'update_subscription_details_in_app'
        ] );

        // Schedule cleaning up orders that are marked for deletion
        if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_check_orders_marked_for_deletion' ) ) {
            wp_schedule_event( time(), 'hourly', 'woocommerce_vipps_recurring_check_orders_marked_for_deletion' );
        }

        add_action( 'woocommerce_vipps_recurring_check_orders_marked_for_deletion', [
            $this,
            'check_orders_marked_for_deletion'
        ] );

        // Add our own ajax actions
        add_action( 'wp_ajax_wc_vipps_recurring_order_action', [
            $this,
            'order_handle_vipps_recurring_action'
        ] );

        // Custom actions
        add_filter( 'generate_rewrite_rules', [ $this, 'custom_action_endpoints' ] );
        add_filter( 'query_vars', [ $this, 'custom_action_query_vars' ] );
        add_filter( 'template_include', [ $this, 'custom_action_page_template' ] );
    }

    /**
     * Admin only dashboard
     */
    public function admin_init() {
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

        add_action( 'admin_head', [ $this, 'admin_head' ] );

        // Add capture button if order is not captured
        add_action( 'woocommerce_order_item_add_action_buttons', [
            $this,
            'order_item_add_action_buttons'
        ] );

        if ( $this->gateway()->test_mode ) {
            $notice = __( 'Vipps/MobilePay Recurring Payments is currently in test mode - no real transactions will occur. Disable test mode when you are ready to go live!', 'woo-vipps' );
            $this->notices->warning( $notice );
        }

        // Load correct list table classes for current screen.
        add_action( 'current_screen', [ $this, 'setup_screen' ] );

        if ( isset( $_REQUEST['statuses_checked'] ) ) {
            $this->notices->success( __( 'Successfully checked the status of these charges', 'woo-vipps' ) );
        }

        // Initialize webhooks if we haven't already
        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
            if ( WC_Vipps_Recurring_Helper::is_connected() ) {
                if ( empty( get_option( WC_Vipps_Recurring_Helper::OPTION_WEBHOOKS ) ) ) {
                    $this->gateway()->webhook_initialize();
                } else {
                    $ok = $this->gateway()->webhook_ensure_this_site();

                    if ( ! $ok ) {
                        $this->gateway()->webhook_initialize();
                    }
                }
            }
        }

//		$test = $this->gateway()->check_charge_status(931);

        // Show Vipps Login notice for a maximum of 10 days
        // 1636066799 = 04-11-2021 23:59:59 UTC
//				if ( ! class_exists( 'VippsWooLogin' ) && time() < 1636066799 ) {
//					$vipps_login_plugin_url = 'https://wordpress.org/plugins/login-with-vipps';
//					if ( get_locale() === 'nb_NO' ) {
//						$vipps_login_plugin_url = 'https://nb.wordpress.org/plugins/login-with-vipps';
//					}
//
//					$this->notices->campaign(
//					/* translators: %1$s URL to login-with-vipps, %2$s translation for "here" */
//						sprintf( __( 'Login with Vipps is available for WooCommerce. Super-easy and safer login for your customers - no more usernames and passwords. Get started <a href="%1$s" target="_blank">%2$s</a>!', 'woo-vipps' ), $vipps_login_plugin_url, __( 'here', 'woo-vipps' ) ),
//						'login_promotion',
//						true,
//						'assets/images/vipps-logg-inn-neg.png',
//						'login-promotion'
//					);
//				}
    }

    public function gateway(): WC_Gateway_Vipps_Recurring {
        require_once( "wc-gateway-vipps-recurring.php" );

        return WC_Gateway_Vipps_Recurring::get_instance();
    }

    /**
     * Upgrade routines
     */
    public function upgrade() {
        global $wpdb, $wp_rewrite;

        $version = get_option( 'woo-vipps-recurring-version' );

        // Update 1.8.1: add back _vipps_recurring_pending_charge and _charge_id
        if ( version_compare( $version, '1.8.1', '<' ) ) {
            $results = $wpdb->get_results( "SELECT wp_posts.id FROM (
						SELECT DISTINCT post_id as id FROM wp_postmeta as m
						WHERE EXISTS (SELECT * FROM wp_postmeta WHERE post_id = m.post_id AND meta_key = '_vipps_recurring_failed_charge_reason')
						AND NOT EXISTS (SELECT * FROM wp_postmeta WHERE post_id = m.post_id AND meta_key = '_vipps_recurring_pending_charge')
					) as lookup
					JOIN wp_posts ON (wp_posts.id = lookup.id)
					ORDER BY wp_posts.post_date DESC", ARRAY_A );

            WC_Vipps_Recurring_Logger::log( sprintf( 'Running 1.8.1 update, affecting orders with IDs: %s', implode( ',', array_map( function ( $item ) {
                return $item['id'];
            }, $results ) ) ) );

            foreach ( $results as $row ) {
                $order = wc_get_order( $row['id'] );
                WC_Vipps_Recurring_Helper::set_order_charge_not_failed( $order, WC_Vipps_Recurring_Helper::get_transaction_id_for_order( $order ) );
                $order->save();
            }
        }

        // Update 1.8.2: migrate failed statuses to subscription too
        if ( version_compare( $version, '1.8.2', '<' ) ) {
            $results = $wpdb->get_results( "SELECT wp_posts.id
						FROM (
						         SELECT DISTINCT post_id as id
						         FROM wp_postmeta as m
						         WHERE EXISTS(SELECT *
						                      FROM wp_postmeta
						                      WHERE post_id = m.post_id
						                        AND meta_key = '_vipps_recurring_failed_charge_reason')
						     ) as lookup
						         JOIN wp_posts ON (wp_posts.id = lookup.id)
						ORDER BY wp_posts.post_date DESC", ARRAY_A );

            WC_Vipps_Recurring_Logger::log( sprintf( 'Running 1.8.2 update, affecting orders with IDs: %s', implode( ',', array_map( function ( $item ) {
                return $item['id'];
            }, $results ) ) ) );

            foreach ( $results as $row ) {
                $order = wc_get_order( $row['id'] );

                $subscriptions = WC_Vipps_Recurring_Helper::get_subscriptions_for_order( $order );
                $subscription  = $subscriptions[ array_key_first( $subscriptions ) ];

                if ( ! $subscription ) {
                    continue;
                }

                $failure_reason = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_FAILED_REASON );
                if ( $failure_reason ) {
                    WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_LATEST_FAILED_CHARGE_REASON, $failure_reason );
                }

                $failure_description = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_FAILED_DESCRIPTION );
                if ( $failure_description ) {
                    WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_LATEST_FAILED_CHARGE_DESCRIPTION, $failure_description );
                }

                $subscription->save();
            }
        }

        // Flush permalinks when updating to version 1.20.2
        if ( version_compare( $version, '1.20.2', '<' ) ) {
            $wp_rewrite->flush_rules();
        }

        // Copy MSN, client id, client secret and subscription key to our new test fields if test mode is enabled.
        if ( $this->gateway()->test_mode && version_compare( $version, '2.0.0', '<' ) ) {
            $this->gateway()->update_option( 'test_merchant_serial_number', $this->gateway()->get_option( 'merchant_serial_number' ) );
            $this->gateway()->update_option( 'test_client_id', $this->gateway()->get_option( 'client_id' ) );
            $this->gateway()->update_option( 'test_secret_key', $this->gateway()->get_option( 'secret_key' ) );
            $this->gateway()->update_option( 'test_subscription_key', $this->gateway()->get_option( 'subscription_key' ) );
        }

        if ( $version !== WC_VIPPS_RECURRING_VERSION ) {
            update_option( 'woo-vipps-recurring-version', WC_VIPPS_RECURRING_VERSION );
        }
    }

    /**
     * Inject admin ahead
     */
    public function admin_head() {
        $icon = plugins_url( 'assets/images/' . $this->gateway()->brand . '-mark-icon.svg', WC_VIPPS_RECURRING_MAIN_FILE );

        ?>
        <style>
            #woocommerce-product-data ul.wc-tabs li.wc_vipps_recurring_options a:before {
                background-image: url( <?php echo $icon ?> );
            }
        </style>
        <?php
    }

    public function gateway_should_be_active( array $methods = [] ) {
        // The only two reasons to not show our gateway is if the cart supports being purchased by the standard Vipps MobilePay gateway
        // Or if the cart does not contain a subscription product
        $active = ! isset( $methods['vipps'] )
                  && ( WC_Subscriptions_Cart::cart_contains_subscription()
                       || wcs_cart_contains_switches()
                       || wcs_cart_contains_failed_renewal_order_payment()
                       || isset( $_GET['change_payment_method'] ) );

        return apply_filters( 'wc_vipps_recurring_cart_has_subscription_product', $active, WC()->cart->get_cart_contents() );
    }

    public function maybe_disable_gateway( $methods ) {
        if ( is_admin() || ! is_checkout() ) {
            return $methods;
        }

        $show_gateway = $this->gateway_should_be_active( $methods );

        if ( ! $show_gateway ) {
            unset( $methods['vipps_recurring'] );
        }

        return $methods;
    }

    /**
     * @return string
     */
    public function handle_check_statuses_bulk_action(): string {
        $sendback = remove_query_arg( [ 'orders' ], wp_get_referer() );

        if ( isset( $_GET['orders'] ) ) {
            $order_ids = $_GET['orders'];

            foreach ( $order_ids as $order_id ) {
                // check charge status
                $this->gateway()->check_charge_status( $order_id, true );
            }

            $sendback = add_query_arg( 'statuses_checked', 1, $sendback );
        }

        return $sendback;
    }

    /**
     * Setup the screen for our special setting and action tables
     */
    public function setup_screen() {
        global $wc_vipps_recurring_list_table_pending_charges,
               $wc_vipps_recurring_list_table_failed_charges;

        $screen_id = false;

        if ( function_exists( 'get_current_screen' ) ) {
            $screen    = get_current_screen();
            $screen_id = isset( $screen, $screen->id ) ? $screen->id : '';
        }

        if ( ! empty( $_REQUEST['screen'] ) ) {
            $screen_id = wc_clean( wp_unslash( $_REQUEST['screen'] ) );
        }

        if ( $screen_id === 'settings_page_woo-vipps-recurring' ) {
            include_once 'admin/list-tables/wc-vipps-recurring-list-table-pending-charges.php';
            include_once 'admin/list-tables/wc-vipps-recurring-list-table-failed-charges.php';

            $wc_vipps_recurring_list_table_pending_charges = new WC_Vipps_Recurring_Admin_List_Pending_Charges( [
                'screen' => $screen_id . '_pending-charges'
            ] );
            $wc_vipps_recurring_list_table_failed_charges  = new WC_Vipps_Recurring_Admin_List_Failed_Charges( [
                'screen' => $screen_id . '_failed-charges'
            ] );
        }

        if ( $wc_vipps_recurring_list_table_pending_charges
             && $wc_vipps_recurring_list_table_pending_charges->current_action()
             && $wc_vipps_recurring_list_table_pending_charges->current_action() === 'check_status' ) {
            $sendback = $this->handle_check_statuses_bulk_action();

            wp_redirect( $sendback );
        }

        if ( $wc_vipps_recurring_list_table_failed_charges
             && $wc_vipps_recurring_list_table_failed_charges->current_action()
             && $wc_vipps_recurring_list_table_failed_charges->current_action() === 'check_status' ) {
            $sendback = $this->handle_check_statuses_bulk_action();

            wp_redirect( $sendback );
        }

        // Ensure the table handler is only loaded once. Prevents multiple loads if a plugin calls check_ajax_referer many times.
        remove_action( 'current_screen', [ $this, 'setup_screen' ] );
    }

    /**
     * Make admin menu entry
     */
    public function admin_menu() {
        add_options_page(
            __( 'Vipps/MobilePay Recurring Payments', 'woo-vipps' ),
            __( 'Vipps/MobilePay Recurring Payments', 'woo-vipps' ),
            'manage_options',
            'woo-vipps-recurring',
            [ $this, 'admin_menu_page_html' ]
        );
    }

    /**
     * Admin menu page HTML
     */
    public function admin_menu_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        include __DIR__ . '/pages/admin/vipps-recurring-admin-menu-page.php';
    }

    public function custom_action_endpoints( $rewrites ) {
        $rewrites->rules = array_merge(
            [ 'vipps-mobilepay-recurring-payment/?$' => 'index.php?vipps_recurring_action=payment-redirect' ],
            $rewrites->rules
        );

        return $rewrites;
    }

    public function custom_action_query_vars( $query_vars ) {
        $query_vars[] = 'vipps_recurring_action';

        return $query_vars;
    }

    public function custom_action_page_template( $original_template ) {
        $custom_action = get_query_var( 'vipps_recurring_action' );

        if ( ! empty ( $custom_action ) ) {
            include WC_VIPPS_RECURRING_PLUGIN_PATH . '/includes/pages/payment-redirect-page.php';
            die;
        }

        return $original_template;
    }

    public function maybe_create_checkout_page() {
        $checkout_page_id    = wc_get_page_id( 'vipps_mobilepay_recurring_checkout' );
        $should_create_pages = ! $checkout_page_id || ! get_post_status( $checkout_page_id );

        if ( ! $should_create_pages ) {
            return;
        }

        // Piggybacks off of WooCommerce's default logic for creating the checkout page.
        // This is possible because we override the shortcode.
        delete_option( 'woocommerce_vipps_recurring_checkout_page_id' );
        WC_Install::create_pages();
    }

    /**
     * Vipps Checkout replaces the default checkout page, and currently uses its own page for this which needs to exist
     */
    public function woocommerce_create_pages( $data ) {
        $checkout_enabled = get_option( WC_Vipps_Recurring_Helper::OPTION_CHECKOUT_ENABLED, false );
        if ( ! $checkout_enabled ) {
            return $data;
        }

        $data['vipps_recurring_checkout'] = array(
            'name'    => _x( 'vipps_recurring_checkout', 'Page slug', 'woo-vipps' ),
            'title'   => _x( 'Vipps MobilePay Recurring Checkout', 'Page title', 'woo-vipps' ),
            'content' => '<!-- wp:shortcode -->[' . 'vipps_recurring_checkout' . ']<!-- /wp:shortcode -->',
        );

        return $data;
    }

    /**
     * Force check status of all pending charges
     */
    public function wp_ajax_vipps_recurring_force_check_charge_statuses(): void {
        try {
            /* translators: amount of orders checked */
            echo sprintf( __( 'Done. Checked the status of %s orders', 'woo-vipps' ), count( $this->check_order_statuses( - 1 ) ) );
        } catch ( Exception $e ) {
            echo __( 'Failed to finish checking the status of all orders. Please try again.', 'woo-vipps' );
        }

        wp_die();
    }

    /**
     * @param $tabs
     *
     * @return mixed
     */
    public function woocommerce_product_data_tabs( $tabs ) {
        $tabs['wc_vipps_recurring'] = [
            'label'    => __( 'Vipps/MobilePay Recurring Payments', 'woo-vipps' ),
            'target'   => 'wc_vipps_recurring_product_data',
            'priority' => 100,
        ];

        return $tabs;
    }

    /**
     * Tab content
     */
    public function woocommerce_product_data_panels(): void {
        echo '<div id="wc_vipps_recurring_product_data" class="panel woocommerce_options_panel hidden">';

        woocommerce_wp_checkbox( [
            'id'          => WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE,
            'value'       => get_post_meta( get_the_ID(), WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE, true ),
            'label'       => __( 'Capture payment instantly', 'woo-vipps' ),
            'description' => __( 'Capture payment instantly even if the product is not virtual. Please make sure you are following the local jurisdiction in your country when using this option.', 'woo-vipps' ),
            'desc_tip'    => true,
        ] );

        woocommerce_wp_select( [
            'id'          => WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_SOURCE,
            'value'       => get_post_meta( get_the_ID(), WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_SOURCE, true ) ?: 'title',
            'label'       => __( 'Description source', 'woo-vipps' ),
            'description' => __( 'Where we should source the agreement description from. Displayed in the Vipps/MobilePay app.', 'woo-vipps' ),
            'desc_tip'    => true,
            'options'     => [
                'none'              => __( 'None', 'woo-vipps' ),
                'short_description' => __( 'Product short description', 'woo-vipps' ),
                'custom'            => __( 'Custom', 'woo-vipps' )
            ]
        ] );

        woocommerce_wp_text_input( [
            'id'          => WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_TEXT,
            'value'       => get_post_meta( get_the_ID(), WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_TEXT, true ),
            'label'       => __( 'Custom description', 'woo-vipps' ),
            'description' => __( 'If the description source is set to "custom" this text will be used.', 'woo-vipps' ),
            'placeholder' => __( 'Max 100 characters', 'woo-vipps' ),
            'desc_tip'    => true,
        ] );

        echo '</div>';
    }

    /**
     * Save our custom fields
     *
     * @param $post_id
     */
    public function woocommerce_process_product_meta( $post_id ) {
        $capture_instantly = isset( $_POST[ WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE ] ) ? 'yes' : 'no';
        update_post_meta( $post_id, WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE, $capture_instantly );

        update_post_meta( $post_id, WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_SOURCE, $_POST[ WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_SOURCE ] );
        update_post_meta( $post_id, WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_TEXT, $_POST[ WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_TEXT ] ?? '' );
    }

    /**
     * @param $order
     */
    public function order_item_add_action_buttons( $order ): void {
        $this->order_item_add_capture_button( $order );
    }

    /**
     * @param $order
     */
    public function order_item_add_capture_button( $order ): void {
        if ( $order->get_type() !== 'shop_order' ) {
            return;
        }

        $payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $order );
        if ( $payment_method !== $this->gateway()->id ) {
            // If this is not the payment method, an agreement would not be available.
            return;
        }

        $show_capture_button = WC_Vipps_Recurring_Helper::can_capture_charge_for_order( $order );

        if ( ! apply_filters( 'wc_vipps_recurring_show_capture_button', $show_capture_button, $order ) ) {
            return;
        }

        $is_captured = WC_Vipps_Recurring_Helper::is_charge_captured_for_order( $order );

        if ( $show_capture_button && ! $is_captured ) {
            $logo = plugins_url( 'assets/images/' . $this->gateway()->brand . '-logo-white.svg', WC_VIPPS_RECURRING_MAIN_FILE );

            print '<button type="button" data-order-id="' . $order->get_id() . '" data-action="capture_payment" class="button generate-items capture-payment-button ' . $this->gateway()->brand . '"><img border="0" style="display:inline;height:2ex;vertical-align:text-bottom" class="inline" alt="0" src="' . $logo . '"/> ' . __( 'Capture payment', 'woo-vipps' ) . '</button>';
        }
    }

    public function order_handle_vipps_recurring_action() {
        check_ajax_referer( 'vipps_recurring_ajax_nonce', 'nonce' );

        $order = wc_get_order( intval( $_REQUEST['orderId'] ) );
        if ( ! is_a( $order, 'WC_Order' ) ) {
            return;
        }

        if ( $order->get_payment_method() != $this->gateway()->id ) {
            return;
        }

        $action = isset( $_REQUEST['do'] ) ? sanitize_title( $_REQUEST['do'] ) : 'none';

        if ( $action == 'capture_payment' ) {
            $this->gateway()->maybe_capture_payment( $order->get_id() );
        }

        print "1";
    }

    /**
     * Check charge statuses scheduled action
     *
     * @param int|null $limit
     *
     * @return array
     */
    public function check_order_statuses( $limit = '' ): array {
        if ( empty( $limit ) ) {
            $limit = $this->gateway()->check_charges_amount;
        }

        $options = [
            'limit'          => $limit,
            'meta_query'     => [
                [
                    'key'     => WC_Vipps_Recurring_Helper::META_CHARGE_PENDING,
                    'compare' => '=',
                    'value'   => 1
                ]
            ],
            'payment_method' => $this->gateway()->id
        ];

        if ( $this->gateway()->check_charges_sort_order === 'rand' ) {
            $options['orderby'] = 'rand';
        } else {
            $options['orderby'] = 'post_date';
            $options['order']   = $this->gateway()->check_charges_sort_order;
        }

        remove_all_filters( 'posts_orderby' );
        $orders = wc_get_orders( $options );

        foreach ( $orders as $order ) {
            $order_id = WC_Vipps_Recurring_Helper::get_id( $order );

            do_action( 'wc_vipps_recurring_before_cron_check_order_status', $order_id );
            $this->gateway()->check_charge_status( $order_id );
            do_action( 'wc_vipps_recurring_after_cron_check_order_status', $order_id );
        }

        return $orders;
    }

    /**
     * Check the status of gateway change requests
     */
    public function check_gateway_change_agreement_statuses() {
        $subscriptions = wcs_get_subscriptions( [
            'subscription_status' => [ 'active', 'pending', 'on-hold' ],
            'meta_query'          => [
                [
                    'key'     => WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_WAITING_FOR_GATEWAY_CHANGE,
                    'compare' => '=',
                    'value'   => 1
                ]
            ]
        ] );

        foreach ( $subscriptions as $subscription ) {
            // check charge status
            $this->gateway()->maybe_process_gateway_change( $subscription->get_id() );
        }
    }

    /**
     * Update a subscription's details in the app
     */
    public function update_subscription_details_in_app() {
        $subscriptions = wcs_get_subscriptions( [
            'subscriptions_per_page' => 5,
            'subscription_status'    => [ 'active', 'pending-cancel', 'cancelled', 'on-hold' ],
            'meta_query'             => [
                [
                    'key'     => WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP,
                    'compare' => '=',
                    'value'   => 1,
                ]
            ]
        ] );

        foreach ( $subscriptions as $subscription ) {
            // check charge status
            $this->gateway()->maybe_update_subscription_details_in_app( $subscription->get_id() );
        }
    }

    public function check_orders_marked_for_deletion() {
        $options = [
            'limit'          => 25,
            'payment_method' => $this->gateway()->id,
            'meta_query'     => [
                [
                    'key'     => WC_Vipps_Recurring_Helper::META_ORDER_MARKED_FOR_DELETION,
                    'compare' => '=',
                    'value'   => 1
                ]
            ]
        ];

        $orders = wc_get_orders( $options );

        WC_Vipps_Recurring_Logger::log( sprintf( "Running check_orders_marked_for_deletion for %s orders", count( $orders ) ) );

        foreach ( $orders as $order ) {
            $order_id = WC_Vipps_Recurring_Helper::get_id( $order );

            // Check the status of this order's charge just in case.
            do_action( 'wc_vipps_recurring_before_cron_check_order_status', $order_id );
            $this->gateway()->check_charge_status( $order_id );
            do_action( 'wc_vipps_recurring_after_cron_check_order_status', $order_id );
            $order = wc_get_order( $order );

            // If this order has been manually updated in the mean-time, we no longer want to delete it.
            // Similarly, if it has a billing email we don't want to delete it.
            $empty_email = $order->get_billing_email() === WC_Vipps_Recurring_Helper::FAKE_USER_EMAIL || ! $order->get_billing_email();

			WC_Vipps_Recurring_Logger::log( sprintf( "Checking if order %s should be deleted (status: %s, empty email: %s, is renewal: %s).", $order_id, $order->get_status(), $empty_email ? 'Yes' : 'No', wcs_order_contains_renewal( $order ) ? 'Yes' : 'No' ) );

            if ( ! in_array( $order->get_status( 'edit' ), [ 'pending', 'cancelled' ] ) || ! $empty_email ) {
                WC_Vipps_Recurring_Logger::log( sprintf( "Removing %s from the deletion queue as it should no longer be deleted.", $order_id ) );

                WC_Vipps_Recurring_Helper::delete_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_MARKED_FOR_DELETION );
                $order->save();

                continue;
            }

            WC_Vipps_Recurring_Logger::log( sprintf( "Deleting %s.", WC_Vipps_Recurring_Helper::get_id( $order ) ) );

            if ( ! wcs_order_contains_renewal( $order ) ) {
                $subscriptions = wcs_get_subscriptions_for_order( $order );
                foreach ( $subscriptions as $subscription ) {
                    // Do not under any circumstances delete active subscriptions.
                    if ( $subscription->get_status() === 'active' ) {
                        continue;
                    }

                    $subscription->delete();
                }
            }

            $order->delete();
        }
    }

    /**
     * Adds plugin action links.
     *
     * @since 1.0.0
     * @version 4.0.0
     */
    public function plugin_action_links( $links ): array {
        // IOK 2025-01-13 temporarily deactivated, because there is currently two
        // sets of settings, and not enough room to disambituate between them (at least in norwegian).
        return $links;

        $plugin_links = [
            '<a href="admin.php?page=wc-settings&tab=checkout&section=vipps_recurring">' . esc_html__( 'Settings', 'woo-vipps' ) . '</a>',
        ];

        return array_merge( $plugin_links, $links );
    }

    /**
     * Handles upgrade routines.
     *
     * @since 3.1.0
     * @version 3.1.0
     */
    public function install() {
        $this->upgrade();
    }

    /**
     * Add the gateways to WooCommerce.
     *
     * @param $methods
     *
     * @return array
     * @since 1.0.0
     * @version 4.0.0
     */
    public function add_gateways( $methods ): array {
        if ( function_exists( 'wcs_create_renewal_order' ) && class_exists( 'WC_Subscriptions_Order' ) ) {
            require_once( dirname( __FILE__ ) . "/wc-gateway-vipps-recurring.php" );

            $methods[] = WC_Gateway_Vipps_Recurring::get_instance();
        }

        return $methods;
    }

    /**
     * Enqueue our CSS and other assets.
     */
    public function wp_enqueue_scripts() {
        wp_enqueue_style(
            'woo-vipps-recurring',

            WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/build/main.css', [],
            filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/main.css' )
        );

        $asset = require WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/main.asset.php';

        wp_enqueue_script(
            'woo-vipps-recurring',
            WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/build/main.js',
            $asset['dependencies'],
            filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/main.js' ),
            true
        );

        $continue_shopping_link_page = ! empty( $this->gateway()->continue_shopping_link_page )
            ? $this->gateway()->continue_shopping_link_page
            : wc_get_page_id( 'shop' );

        $continue_shopping_url = $continue_shopping_link_page ? get_permalink( $continue_shopping_link_page ) : home_url();
        if ( empty( $continue_shopping_link_page ) ) {
            $continue_shopping_url = home_url();
        }

        wp_localize_script( 'woo-vipps-recurring', 'VippsMobilePaySettings', [
            'logo'                => WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/images/' . $this->gateway()->brand . '-logo.svg',
            'continueShoppingUrl' => $continue_shopping_url
        ] );

        wp_set_script_translations( 'woo-vipps-recurring', 'woo-vipps', dirname( WC_VIPPS_MAIN_FILE ) . '/languages' );
    }

    /**
     * Enqueue our CSS and other assets.
     */
    public function admin_enqueue_scripts() {
        wp_enqueue_style( 'woo-vipps-recurring', plugins_url( 'assets/css/vipps-recurring-admin.css', WC_VIPPS_RECURRING_MAIN_FILE ), [],
            filemtime( dirname( WC_VIPPS_RECURRING_MAIN_FILE ) . '/assets/css/vipps-recurring-admin.css' ) );

        $this->ajax_config['nonce']    = wp_create_nonce( 'vipps_recurring_ajax_nonce' );
        $this->ajax_config['currency'] = get_woocommerce_currency();

        wp_enqueue_script(
            'woo-vipps-recurring-admin',
            plugins_url( 'assets/js/vipps-recurring-admin.js', WC_VIPPS_RECURRING_MAIN_FILE ),
            [ 'wp-i18n' ],
            filemtime( dirname( WC_VIPPS_RECURRING_MAIN_FILE ) . "/assets/js/vipps-recurring-admin.js" ),
            true
        );

        wp_localize_script( 'woo-vipps-recurring-admin', 'VippsRecurringConfig', $this->ajax_config );
        wp_set_script_translations( 'woo-vipps-recurring-admin', 'woo-vipps', dirname( WC_VIPPS_MAIN_FILE ) . '/languages' );
    }

    /**
     * @param $schedules
     *
     * @return mixed
     */
    public function woocommerce_vipps_recurring_add_cron_schedules( $schedules ) {
        $schedules['one_minute'] = [
            'interval' => 60,
            'display'  => esc_html__( 'Every One Minute' ),
        ];

        return $schedules;
    }

    /**
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     * @throws WC_Vipps_Recurring_Config_Exception
     */
    public function handle_webhook_callback() {
        wc_nocache_headers();
        status_header( 202, "Accepted" );

        $raw_input = @file_get_contents( 'php://input' );
        $body      = @json_decode( $raw_input, true );

        // If we have a sessionId, this indicates that we are dealing with a Checkout callback.
        // Fire a hook and let Checkout deal with its own logic.
        if ( isset( $body['sessionId'] ) ) {
            $authorization_token = $_SERVER['HTTP_AUTHORIZATION'];

            do_action( 'wc_vipps_recurring_checkout_callback', $body, $authorization_token );

            // Early return to avoid potential errors downstream.
            return;
        }

        $callback = $_REQUEST['callback'] ?? "";

        if ( $callback !== "webhook" ) {
            return;
        }

        if ( ! $body ) {
            $error = json_last_error_msg();
            WC_Vipps_Recurring_Logger::log( sprintf( "Did not understand callback from Vipps/MobilePay with body: %s – error: %s", empty( $raw_input ) ? "(empty string)" : $raw_input, $error ) );

            return;
        }

        $local_webhooks = $this->gateway()->webhook_get_local()[ $this->gateway()->merchant_serial_number ];
        $local_webhook  = array_pop( $local_webhooks );
        $secret         = $local_webhook ? ( $local_webhook['secret'] ?? false ) : false;

        $order_id = $body['chargeId'] ?? $body['agreementId'];
        if ( ! $order_id ) {
            WC_Vipps_Recurring_Logger::log( sprintf( "Could not find order id in webhook with body %s", json_encode( $body ) ) );

            return;
        }

        if ( ! $secret ) {
            WC_Vipps_Recurring_Logger::log( sprintf( "Cannot verify webhook callback for order %s - this shop does not know the secret. You should delete all unwanted webhooks. If you are using the same MSN on several shops, this callback is probably for one of the others.", $order_id ) );
        }

        if ( ! $this->verify_webhook( $raw_input, $secret ) ) {
            return;
        }

        do_action( "wc_vipps_recurring_webhook_callback", $body, $raw_input );

        // We now have a validated webhook
        $this->gateway()->handle_webhook_callback( $body );
    }

    public function verify_webhook( $serialized, $secret ): bool {
        $expected_auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ( $_SERVER['HTTP_X_VIPPS_AUTHORIZATION'] ?? "" );
        $expected_date = $_SERVER['HTTP_X_MS_DATE'] ?? '';

        $hashed_payload = base64_encode( hash( 'sha256', $serialized, true ) );
        $path_and_query = $_SERVER['REQUEST_URI'];
        $host           = $_SERVER['HTTP_HOST'];
        $toSign         = "POST\n{$path_and_query}\n$expected_date;$host;$hashed_payload";
        $signature      = base64_encode( hash_hmac( 'sha256', $toSign, $secret, true ) );
        $auth           = "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=$signature";

        return ( $auth == $expected_auth );
    }

    public function generate_order_prefix(): string {
        $parts = parse_url( site_url() );
        if ( ! $parts ) {
            return 'woo-';
        }

        $domain = explode( ".", $parts['host'] ?? '' );
        if ( empty( $domain ) ) {
            return 'woo-';
        }

        $first  = strtolower( $domain[0] );
        $second = $domain[1] ?? '';

        // Select first part of domain unless that has no content, otherwise second. Default to "woo-" again.
        $key = $first;
        if ( in_array( $first, [ 'www', 'test', 'dev', 'vdev' ] ) && ! empty( $second ) ) {
            $key = $second;
        }

        // Use only 8 chars for the site. Try to make it so by dropping vowels, if that doesn't succeed, just chop it.
        $key = sanitize_title( $key );
        $len = strlen( $key );
        if ( $len <= 8 ) {
            return "woo-$key-";
        }

        $kzk = preg_replace( "/[aeiouæøåüö]/i", "", $key );
        if ( strlen( $kzk ) <= 8 ) {
            return "woo-$kzk-";
        }

        return "woo-" . substr( $key, 0, 8 ) . "-";
    }
}
