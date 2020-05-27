<?php

/**
 * Plugin Name: Vipps Recurring Payments Gateway for WooCommerce
 * Description: Offer recurring payments with Vipps for WooCommerce Subscriptions
 * Author: Vipps AS
 * Author URI: https://vipps.no
 * Version: 1.3.2
 * Requires at least: 4.4
 * Tested up to: 5.4.1
 * WC tested up to: 4.1.0
 * Text Domain: woo-vipps-recurring
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce requirement notice
 *
 * @return void
 * @since 4.1.2
 */
function woocommerce_vipps_recurring_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Vipps recurring payments requires WooCommerce to be installed and active. You can download %s here.', 'woo-vipps-recurring' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce Subscriptions requirement notice
 *
 * @return void
 * @since 4.1.2
 */
function woocommerce_vipps_recurring_missing_wc_subscriptions_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Vipps recurring payments requires WooCommerce Subscriptions to be installed and active. You can download %s here.', 'woo-vipps-recurring' ), '<a href="https://woocommerce.com/products/woocommerce-subscriptions/" target="_blank">WooCommerce Subscriptions</a>' ) . '</strong></p></div>';
}

add_action( 'plugins_loaded', 'woocommerce_gateway_vipps_recurring_init' );

/**
 * Pollyfills
 */
if ( ! function_exists( 'array_key_first' ) ) {
	function array_key_first( array $arr ) {
		foreach ( $arr as $key => $unused ) {
			return $key;
		}

		return null;
	}
}

/**
 * Initialize our plugin
 */
function woocommerce_gateway_vipps_recurring_init() {
	load_plugin_textdomain( 'woo-vipps-recurring', false, plugin_basename( __DIR__ ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_vipps_recurring_missing_wc_notice' );

		return;
	}

	if ( ! class_exists( 'WC_Subscriptions' ) ) {
		add_action( 'admin_notices', 'woocommerce_vipps_recurring_missing_wc_subscriptions_notice' );

		return;
	}

	if ( ! class_exists( 'WC_Vipps_Recurring' ) ) {
		/*
		 * Required minimums and constants
		 */
		define( 'WC_VIPPS_RECURRING_VERSION', '1.3.2' );
		define( 'WC_VIPPS_RECURRING_MIN_PHP_VER', '7.0.0' );
		define( 'WC_VIPPS_RECURRING_MIN_WC_VER', '3.0.0' );
		define( 'WC_VIPPS_RECURRING_MAIN_FILE', __FILE__ );
		define( 'WC_VIPPS_RECURRING_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'WC_VIPPS_RECURRING_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

		/*
		 * Amount of days to retry a payment when creating a charge in the Vipps API
		 */
		if ( ! defined( 'WC_VIPPS_RECURRING_RETRY_DAYS' ) ) {
			define( 'WC_VIPPS_RECURRING_RETRY_DAYS', 3 );
		}

		/*
		 * Whether or not to put the plugin into test mode. This is only useful for developers.
		 */
		if ( ! defined( 'WC_VIPPS_RECURRING_TEST_MODE' ) ) {
			define( 'WC_VIPPS_RECURRING_TEST_MODE', false );
		}

		class WC_Vipps_Recurring {
			/**
			 * @var WC_Vipps_Recurring The reference the *Singleton* instance of this class
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return WC_Vipps_Recurring The *Singleton* instance.
			 */
			public static function get_instance(): \WC_Vipps_Recurring {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			/**
			 * @return WC_Gateway_Vipps_Recurring
			 */
			public function gateway(): \WC_Gateway_Vipps_Recurring {
				return WC_Gateway_Vipps_Recurring::get_instance();
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
			private function __wakeup() {
			}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			private function __construct() {
				add_action( 'admin_init', [ $this, 'install' ] );
				$this->init();
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function init() {
				require_once __DIR__ . '/includes/class-wc-vipps-recurring-helper.php';
				require_once __DIR__ . '/includes/class-wc-vipps-recurring-logger.php';
				require_once __DIR__ . '/includes/class-wc-gateway-vipps-recurring.php';

				add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ] );

				if ( is_admin() ) {
					add_action( 'admin_init', [ $this, 'admin_init' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );
					add_action( 'wp_ajax_vipps_recurring_force_check_charge_statuses', [
						$this,
						'wp_ajax_vipps_recurring_force_check_charge_statuses'
					] );
				}

				// add our gateway
				add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );

				// we only want our gateway visible on subscriptions
				add_filter( 'woocommerce_available_payment_gateways', [ $this, 'maybe_disable_gateway' ] );

				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [
					$this,
					'plugin_action_links'
				] );

				// add custom cron schedules for Vipps charge polling
				add_filter( 'cron_schedules', [
					$this,
					'woocommerce_vipps_recurring_add_cron_schedules'
				] );

				// testing code
//				if (WC_VIPPS_RECURRING_TEST_MODE) {
//					add_action( 'wp', [
//						$this,
//						'check_order_statuses'
//					] );
//				}
				// end testing code

				// reschedule recurring payment charge status checking event if the occurrence is not one_minute
				$event_schedule = wp_get_schedule( 'woocommerce_vipps_recurring_check_order_statuses' );

				if ( $event_schedule === 'hourly' || $event_schedule === 'five_minutes' ) {
					wp_reschedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_check_order_statuses' );
				}

				// schedule recurring payment charge status checking event
				if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_check_order_statuses' ) ) {
					wp_schedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_check_order_statuses' );
				}

				add_action( 'woocommerce_vipps_recurring_check_order_statuses', [
					$this,
					'check_order_statuses'
				] );

				// Add custom product settings for Vipps Recurring.
				add_filter( 'woocommerce_product_data_tabs', [ $this, 'woocommerce_product_data_tabs' ] );
				add_filter( 'woocommerce_product_data_panels', [ $this, 'woocommerce_product_data_panels' ] );
				add_filter( 'woocommerce_process_product_meta', [ $this, 'woocommerce_process_product_meta' ] );
			}

			/**
			 * Admin only dashboard
			 */
			public function admin_init() {
				$gateway = $this->gateway();

				// add capture button if order is not captured
				add_action( 'woocommerce_order_item_add_action_buttons', [
					$this,
					'order_item_add_action_buttons'
				] );

				add_action( 'save_post', [ $this, 'save_order' ], 10, 3 );

				if ( $gateway->testmode ) {
					add_action( 'admin_notices', static function () {
						$notice = __( 'Vipps Recurring Payments is currently in test mode - no real transactions will occur. Disable this in your wp_config when you are ready to go live!', 'woo-vipps-recurring' );
						echo "<div class='notice notice-info is-dismissible'><p>$notice</p></div>";
					} );
				}

				add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

				// Load correct list table classes for current screen.
				add_action( 'current_screen', [ $this, 'setup_screen' ] );

				if ( isset( $_REQUEST['statuses_checked'] ) ) {
					add_action( 'admin_notices', [
						$this,
						'vipps_recurring_check_statuses_bulk_action_notice'
					] );
				}
			}

			/**
			 * Show bulk update notice for statuses check
			 */
			public function vipps_recurring_check_statuses_bulk_action_notice() {
				echo '<div class="updated"><p>' . __( 'Successfully checked the status of these charges', 'woo-vipps-recurring' ) . '</p></div>';
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
						$this->gateway()->check_charge_status( $order_id );
					}

					$sendback = add_query_arg( 'statuses_checked', 1, $sendback );
				}

				return $sendback;
			}

			/**
			 *
			 */
			public function setup_screen() {
				global $wc_vipps_recurring_list_table;

				$screen_id = false;

				if ( function_exists( 'get_current_screen' ) ) {
					$screen    = get_current_screen();
					$screen_id = isset( $screen, $screen->id ) ? $screen->id : '';
				}

				if ( ! empty( $_REQUEST['screen'] ) ) { // WPCS: input var ok.
					$screen_id = wc_clean( wp_unslash( $_REQUEST['screen'] ) ); // WPCS: input var ok, sanitization ok.
				}

				add_filter( 'handle_bulk_actions-settings_page_woo-vipps-recurring', [
					$this,
					'settings_page_bulk_action_handler'
				], 10, 3 );

				switch ( $screen_id ) {
					case 'settings_page_woo-vipps-recurring':
						include_once 'includes/admin/list-tables/class-wc-vipps-recurring-list-table-pending-charges.php';
						$wc_vipps_recurring_list_table = new WC_Vipps_Recurring_Admin_List_Pending_Charges();
						break;
				}

				if ( $wc_vipps_recurring_list_table
				     && $wc_vipps_recurring_list_table->current_action()
				     && $wc_vipps_recurring_list_table->current_action() === 'check_status' ) {
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
					__( 'Vipps Recurring Payments', 'woo-vipps-recurring' ),
					__( 'Vipps Recurring Payments', 'woo-vipps-recurring' ),
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

				include __DIR__ . '/includes/pages/admin/vipps-recurring-admin-menu-page.php';
			}

			/**
			 * Force check status of all pending charges
			 */
			public function wp_ajax_vipps_recurring_force_check_charge_statuses() {
				try {
					/* translators: amount of orders checked */
					echo sprintf( __( 'Done. Checked the status of %s orders', 'woo-vipps-recurring' ), count( $this->check_order_statuses( - 1 ) ) );
				} catch ( Exception $e ) {
					echo __( 'Failed to finish checking the status of all orders. Please try again.', 'woo-vipps-recurring' );
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
					'label'    => __( 'Vipps Recurring Payments', 'woo-vipps-recurring' ),
					'target'   => 'wc_vipps_recurring_product_data',
					'priority' => 100,
				];

				return $tabs;
			}

			/**
			 * Tab content
			 */
			public function woocommerce_product_data_panels() {
				echo '<div id="wc_vipps_recurring_product_data" class="panel woocommerce_options_panel hidden">';

				woocommerce_wp_checkbox( [
					'id'          => '_vipps_recurring_direct_capture',
					'value'       => get_post_meta( get_the_ID(), '_vipps_recurring_direct_capture', true ),
					'label'       => __( 'Capture payment instantly', 'woo-vipps-recurring' ),
					'description' => __( 'Capture payment instantly even if the product is not virtual. Please make sure you are following Norwegian law when using this option.', 'woo-vipps-recurring' )
				] );

				echo '</div>';
			}

			/**
			 * Save our custom fields
			 *
			 * @param $post_id
			 */
			public function woocommerce_process_product_meta( $post_id ) {
				$capture_instantly = isset( $_POST['_vipps_recurring_direct_capture'] ) ? 'yes' : 'no';
				update_post_meta( $post_id, '_vipps_recurring_direct_capture', $capture_instantly );
			}

			/**
			 * @param $order
			 */
			public function order_item_add_action_buttons( $order ) {
				$this->order_item_add_capture_button( $order );
			}

			/**
			 * @param $order
			 */
			public function order_item_add_capture_button( $order ) {
				$gateway = $this->gateway();

				if ( $order->get_type() !== 'shop_order' ) {
					return;
				}

				$payment_method = WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ? $order->payment_method : $order->get_payment_method();
				if ( $payment_method !== $gateway->id ) {
					// If this is not the payment method, an agreement would not be available.
					return;
				}

				$order_status        = $order->get_status();
				$show_capture_button = ( $order_status === 'on-hold' || $order_status === 'processing' )
				                       && ! (bool) $order->get_transaction_id();

				if ( ! apply_filters( 'wc_vipps_recurring_show_capture_button', $show_capture_button, $order ) ) {
					return;
				}

				$is_captured = $order->get_meta( '_vipps_recurring_captured' );

				if ( $show_capture_button && ! $is_captured ) {
					$logo = plugins_url( 'assets/images/vipps_logo_negative_rgb_transparent.png', __FILE__ );

					print '<button type="button" onclick="document.getElementById(\'docapture\').value=1;document.post.submit();" style="background-color:#ff5b24;border-color:#ff5b24;color:#ffffff" class="button vipps-button generate-items"><img border="0" style="display:inline;height:2ex;vertical-align:text-bottom" class="inline" alt="0" src="' . $logo . '"/> ' . __( 'Capture payment', 'woo-vipps-recurring' ) . '</button>';
					print '<input id="docapture" type="hidden" name="do_capture_vipps_recurring" value="0">';
				}
			}

			/**
			 * @param $postid
			 * @param $post
			 *
			 * @throws Exception
			 */
			public function save_order( $postid, $post ) {
				if ( $post->post_type !== 'shop_order' ) {
					return;
				}

				$gateway = $this->gateway();

				$order          = wc_get_order( $postid );
				$payment_method = WC_Vipps_Recurring_Helper::is_wc_lt( '3.0' ) ? $order->payment_method : $order->get_payment_method();
				if ( $payment_method !== $gateway->id ) {
					// If this is not the payment method, an agreement would not be available.
					return;
				}

				if ( isset( $_POST['do_capture_vipps_recurring'] ) && $_POST['do_capture_vipps_recurring'] ) {
					$gateway->capture_payment( $order );

					$this->store_admin_notices();
				}
			}

			/**
			 * Make admin notices persistent
			 */
			public function store_admin_notices() {
				ob_start();
				do_action( 'admin_notices' );

				$notices = ob_get_clean();
				set_transient( '_vipps_recurring_save_admin_notices', $notices, 5 * 60 );
			}

			/**
			 * Check charge statuses scheduled action
			 * Returns nothing
			 *
			 * @param int $limit
			 *
			 * @return array
			 */
			public function check_order_statuses( $limit = 5 ): array {
				$gateway = $this->gateway();

				$order_ids = wc_get_orders( [
					'limit'          => $limit,
					'order'          => 'rand',
					'type'           => 'shop_order',
					'meta_key'       => '_vipps_recurring_pending_charge',
					'meta_compare'   => '=',
					'meta_value'     => 1,
					'return'         => 'ids',
					'payment_method' => $gateway->id
				] );

				foreach ( $order_ids as $order_id ) {
					// check charge status
					$gateway->check_charge_status( $order_id );
				}

				return $order_ids;
			}

			/**
			 * Adds plugin action links.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function plugin_action_links( $links ): array {
				$plugin_links = [
					'<a href="admin.php?page=wc-settings&tab=checkout&section=vipps_recurring">' . esc_html__( 'Settings', 'woo-vipps-recurring' ) . '</a>',
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
				$this->gateway()->ensure_cancelled_order_page();
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
					$methods[] = 'WC_Gateway_Vipps_Recurring';
				}

				return $methods;
			}

			/**
			 * Maybe disable payment gateway
			 *
			 * @param $methods
			 *
			 * @return mixed
			 */
			public function maybe_disable_gateway( $methods ) {
				if ( is_admin() || ! is_checkout() ) {
					return $methods;
				}

				foreach ( WC()->cart->get_cart_contents() as $key => $values ) {
					$product = wc_get_product( $values['product_id'] );

					if ( ! $product->is_type( [ 'subscription', 'variable-subscription' ] ) ) {
						unset( $methods['vipps_recurring'] );
					}
				}

				return $methods;
			}

			/**
			 * Enqueue our CSS and other assets.
			 */
			public function wp_enqueue_scripts() {
				wp_enqueue_style( 'woo-vipps-recurring', plugins_url( 'assets/css/vipps-recurring.css', __FILE__ ), [],
					filemtime( __DIR__ . '/assets/css/vipps-recurring.css' ) );
			}

			/**
			 * Enqueue our CSS and other assets.
			 */
			public function admin_enqueue_scripts() {
				wp_enqueue_style( 'woo-vipps-recurring', plugins_url( 'assets/css/vipps-recurring-admin.css', __FILE__ ), [],
					filemtime( __DIR__ . '/assets/css/vipps-recurring-admin.css' ) );

				wp_enqueue_script( 'woo-vipps-recurring', plugins_url( 'assets/js/vipps-recurring-admin.js', __FILE__ ), [],
					filemtime( __DIR__ . '/assets/js/vipps-recurring-admin.js' ) );
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
		}

		WC_Vipps_Recurring::get_instance();
	}
}
