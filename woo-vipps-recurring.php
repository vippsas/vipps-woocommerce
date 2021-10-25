<?php

/**
 * Plugin Name: Vipps Recurring Payments Gateway for WooCommerce
 * Description: Offer recurring payments with Vipps for WooCommerce Subscriptions
 * Author: Everyday AS
 * Author URI: https://everyday.no
 * Version: 1.9.1
 * Requires at least: 4.4
 * Tested up to: 5.8
 * WC tested up to: 5.6.0
 * Text Domain: woo-vipps-recurring
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Files.FileName

define( 'WC_VIPPS_RECURRING_VERSION', '1.9.1' );

add_action( 'plugins_loaded', 'woocommerce_gateway_vipps_recurring_init' );

/**
 * Polyfills
 */
if ( ! function_exists( 'array_key_first' ) ) {
	function array_key_first( array $arr ) {
		foreach ( $arr as $key => $unused ) {
			return $key;
		}

		return null;
	}
}

if ( ! function_exists( 'array_key_last' ) ) {
	function array_key_last( array $array ) {
		end( $array );

		return key( $array );
	}
}

/**
 * Activation hooks
 */
register_activation_hook( __FILE__, 'woocommerce_gateway_vipps_recurring_activate' );

function woocommerce_gateway_vipps_recurring_activate() {
	add_option( 'woo-vipps-recurring-version', WC_VIPPS_RECURRING_VERSION );
}

/**
 * Initialize our plugin
 */
function woocommerce_gateway_vipps_recurring_init() {
	load_plugin_textdomain( 'woo-vipps-recurring', false, plugin_basename( __DIR__ ) . '/languages' );

	if ( ! class_exists( 'WC_Vipps_Recurring' ) ) {
		/*
		 * Required minimums and constants
		 */
		define( 'WC_VIPPS_RECURRING_MIN_PHP_VER', '7.0.0' );
		define( 'WC_VIPPS_RECURRING_MIN_WC_VER', '3.0.0' );
		define( 'WC_VIPPS_RECURRING_MAIN_FILE', __FILE__ );
		define( 'WC_VIPPS_RECURRING_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'WC_VIPPS_RECURRING_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

		/*
		 * Amount of days to retry a payment when creating a charge in the Vipps API
		 */
		if ( ! defined( 'WC_VIPPS_RECURRING_RETRY_DAYS' ) ) {
			define( 'WC_VIPPS_RECURRING_RETRY_DAYS', 4 );
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
			 * @var WC_Vipps_Recurring_Admin_Notices
			 */
			public $notices;

			/**
			 * @var WC_Gateway_Vipps_Recurring
			 */
			public $gateway;

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
				require_once __DIR__ . '/includes/wc-vipps-recurring-helper.php';
				require_once __DIR__ . '/includes/wc-vipps-recurring-logger.php';
				require_once __DIR__ . '/includes/wc-gateway-vipps-recurring.php';
				require_once __DIR__ . '/includes/wc-vipps-recurring-admin-notices.php';

				$this->notices = WC_Vipps_Recurring_Admin_Notices::get_instance( __FILE__ );
				$this->gateway = WC_Gateway_Vipps_Recurring::get_instance();

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
				if ( WC_VIPPS_RECURRING_TEST_MODE ) {
					add_action( 'wp_loaded', [
						$this,
						'check_order_statuses'
					] );
				}
				// end testing code

				// schedule recurring payment charge status checking event
				if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_check_order_statuses' ) ) {
					wp_schedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_check_order_statuses' );
				}

				add_action( 'woocommerce_vipps_recurring_check_order_statuses', [
					$this,
					'check_order_statuses'
				] );

				// schedule checking if gateway change went through
				if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_check_gateway_change_request' ) ) {
					wp_schedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_check_gateway_change_request' );
				}

				add_action( 'woocommerce_vipps_recurring_check_gateway_change_request', [
					$this,
					'check_gateway_change_agreement_statuses'
				] );

				// schedule checking for updating payment details
				if ( ! wp_next_scheduled( 'woocommerce_vipps_recurring_update_subscription_details_in_app' ) ) {
					wp_schedule_event( time(), 'one_minute', 'woocommerce_vipps_recurring_update_subscription_details_in_app' );
				}

				add_action( 'woocommerce_vipps_recurring_update_subscription_details_in_app', [
					$this,
					'update_subscription_details_in_app'
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
				add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

				// styling
				add_action( 'admin_head', [ $this, 'admin_head' ] );

				if ( ! class_exists( 'WooCommerce' ) ) {
					// translators: %s link to WooCommerce's download page
					$notice = sprintf( esc_html__( 'Vipps recurring payments requires WooCommerce to be installed and active. You can download %s here.', 'woo-vipps-recurring' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' );
					$this->notices->error( $notice );

					return;
				}

				if ( ! class_exists( 'WC_Subscriptions' ) ) {
					// translators: %s link to WooCommerce Subscription's purchase page
					$notice = sprintf( esc_html__( 'Vipps recurring payments requires WooCommerce Subscriptions to be installed and active. You can purchase and download %s here.', 'woo-vipps-recurring' ), '<a href="https://woocommerce.com/products/woocommerce-subscriptions/" target="_blank">WooCommerce Subscriptions</a>' );
					$this->notices->error( $notice );

					return;
				}

				// add capture button if order is not captured
				add_action( 'woocommerce_order_item_add_action_buttons', [
					$this,
					'order_item_add_action_buttons'
				] );

				add_action( 'save_post', [ $this, 'save_order' ], 10, 3 );

				if ( $this->gateway->testmode ) {
					$notice = __( 'Vipps Recurring Payments is currently in test mode - no real transactions will occur. Disable this in your wp_config when you are ready to go live!', 'woo-vipps-recurring' );
					$this->notices->warning( $notice );
				}

				// Load correct list table classes for current screen.
				add_action( 'current_screen', [ $this, 'setup_screen' ] );

				if ( isset( $_REQUEST['statuses_checked'] ) ) {
					$this->notices->success( __( 'Successfully checked the status of these charges', 'woo-vipps-recurring' ) );
				}

				// Show Vipps Login notice for a maximum of 10 days
				// 1636066799 = 04-11-2021 23:59:59 UTC
				// && ! class_exists( 'WC_Gateway_Vipps' )
				if ( ! class_exists( 'VippsWooLogin' ) && time() < 1636066799 ) {
					$vipps_login_plugin_url = 'https://wordpress.org/plugins/login-with-vipps';
					if (get_locale() === 'nb_NO') {
						$vipps_login_plugin_url = 'https://nb.wordpress.org/plugins/login-with-vipps';
					}

					$this->notices->campaign(
					/* translators: %1$s URL to login-with-vipps, %2$s translation for "here" */
						sprintf( __( 'Login with Vipps is available for WooCommerce. Super-easy and safer login for your customers - no more usernames and passwords. Get started <a href="%1$s" target="_blank">%2$s</a>!', 'woo-vipps-recurring' ), $vipps_login_plugin_url, __( 'here', 'woo-vipps-recurring' ) ),
						'login_promotion',
						true,
						'assets/images/vipps-logg-inn-neg.png',
						'login-promotion'
					);
				}
			}

			/**
			 * Upgrade routines
			 */
			public function upgrade() {
				global $wpdb;

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

				if ( $version !== WC_VIPPS_RECURRING_VERSION ) {
					update_option( 'woo-vipps-recurring-version', WC_VIPPS_RECURRING_VERSION );
				}
			}

			/**
			 * Inject admin ahead
			 */
			public function admin_head() {
				$smile_icon = plugins_url( 'assets/images/vipps-icon-smile.png', __FILE__ );

				?>
				<style>
					#woocommerce-product-data ul.wc-tabs li.wc_vipps_recurring_options a:before {
						background-image: url( <?php echo $smile_icon ?> );
					}
				</style>
				<?php
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
						$this->gateway->check_charge_status( $order_id );
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

				switch ( $screen_id ) {
					case 'settings_page_woo-vipps-recurring':
						include_once 'includes/admin/list-tables/wc-vipps-recurring-list-table-pending-charges.php';
						include_once 'includes/admin/list-tables/wc-vipps-recurring-list-table-failed-charges.php';

						$wc_vipps_recurring_list_table_pending_charges = new WC_Vipps_Recurring_Admin_List_Pending_Charges( [
							'screen' => $screen_id . '_pending-charges'
						] );
						$wc_vipps_recurring_list_table_failed_charges  = new WC_Vipps_Recurring_Admin_List_Failed_Charges( [
							'screen' => $screen_id . '_failed-charges'
						] );
						break;
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
					'id'          => WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE,
					'value'       => get_post_meta( get_the_ID(), WC_Vipps_Recurring_Helper::META_PRODUCT_DIRECT_CAPTURE, true ),
					'label'       => __( 'Capture payment instantly', 'woo-vipps-recurring' ),
					'description' => __( 'Capture payment instantly even if the product is not virtual. Please make sure you are following Norwegian law when using this option.', 'woo-vipps-recurring' ),
					'desc_tip'    => true,
				] );

				woocommerce_wp_select( [
					'id'          => WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_SOURCE,
					'value'       => get_post_meta( get_the_ID(), WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_SOURCE, true ) ?: 'title',
					'label'       => __( 'Description source', 'woo-vipps-recurring' ),
					'description' => __( 'Where we should source the agreement description from. Displayed in the Vipps app.', 'woo-vipps-recurring' ),
					'desc_tip'    => true,
					'options'     => [
						'title'             => __( 'Product title', 'woo-vipps-recurring' ),
						'short_description' => __( 'Product short description', 'woo-vipps-recurring' ),
						'custom'            => __( 'Custom', 'woo-vipps-recurring' )
					]
				] );

				woocommerce_wp_text_input( [
					'id'          => WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_TEXT,
					'value'       => get_post_meta( get_the_ID(), WC_Vipps_Recurring_Helper::META_PRODUCT_DESCRIPTION_TEXT, true ),
					'label'       => __( 'Custom description', 'woo-vipps-recurring' ),
					'description' => __( 'If the description source is set to "custom" this text will be used.', 'woo-vipps-recurring' ),
					'placeholder' => __( 'Max 100 characters', 'woo-vipps-recurring' ),
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
			public function order_item_add_action_buttons( $order ) {
				$this->order_item_add_capture_button( $order );
			}

			/**
			 * @param $order
			 */
			public function order_item_add_capture_button( $order ) {
				if ( $order->get_type() !== 'shop_order' ) {
					return;
				}

				$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $order );
				if ( $payment_method !== $this->gateway->id ) {
					// If this is not the payment method, an agreement would not be available.
					return;
				}

				$order_status        = $order->get_status();
				$show_capture_button = ( ! in_array( $order_status, $this->gateway->statuses_to_attempt_capture, true ) )
									   && ! (int) WC_Vipps_Recurring_Helper::is_charge_captured_for_order( $order )
									   && ! (int) WC_Vipps_Recurring_Helper::is_charge_failed_for_order( $order )
									   && ! wcs_order_contains_renewal( $order )
									   && ! (int) WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_ZERO_AMOUNT );

				if ( ! apply_filters( 'wc_vipps_recurring_show_capture_button', $show_capture_button, $order ) ) {
					return;
				}

				$is_captured = WC_Vipps_Recurring_Helper::is_charge_captured_for_order( $order );

				if ( $show_capture_button && ! $is_captured ) {
					$logo = plugins_url( 'assets/images/vipps-logo-negative-rgb-transparent.png', __FILE__ );

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

				$order          = wc_get_order( $postid );
				$payment_method = WC_Vipps_Recurring_Helper::get_payment_method( $order );
				if ( $payment_method !== $this->gateway->id ) {
					// If this is not the payment method, an agreement would not be available.
					return;
				}

				if ( isset( $_POST['do_capture_vipps_recurring'] ) && $_POST['do_capture_vipps_recurring'] ) {
					$this->gateway->capture_payment( $order );
				}
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
					$limit = $this->gateway->check_charges_amount;
				}

				$options = [
					'limit'          => $limit,
					'type'           => 'shop_order',
					'meta_key'       => WC_Vipps_Recurring_Helper::META_CHARGE_PENDING,
					'meta_compare'   => '=',
					'meta_value'     => 1,
					'return'         => 'ids',
					'payment_method' => $this->gateway->id
				];

				if ( $this->gateway->check_charges_sort_order === 'rand' ) {
					$options['orderby'] = 'rand';
				} else {
					$options['orderby'] = 'post_date';
					$options['order']   = $this->gateway->check_charges_sort_order;
				}

				remove_all_filters( 'posts_orderby' );
				$order_ids = wc_get_orders( $options );

				foreach ( $order_ids as $order_id ) {
					// check charge status
					$this->gateway->check_charge_status( $order_id );
				}

				return $order_ids;
			}

			/**
			 * Check the status of gateway change requests
			 */
			public function check_gateway_change_agreement_statuses() {
				$posts = get_posts( [
					'post_type'    => 'shop_subscription',
					'post_status'  => [ 'wc-active', 'wc-pending', 'wc-on-hold' ],
					'meta_key'     => WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_WAITING_FOR_GATEWAY_CHANGE,
					'meta_compare' => '=',
					'meta_value'   => 1,
					'return'       => 'ids',
				] );

				foreach ( $posts as $post ) {
					// check charge status
					$this->gateway->maybe_process_gateway_change( $post->ID );
				}
			}

			/**
			 * Update a subscription's details in the app
			 */
			public function update_subscription_details_in_app() {
				$posts = get_posts( [
					'limit'        => 5,
					'post_type'    => 'shop_subscription',
					'post_status'  => [ 'wc-active', 'wc-pending-cancel', 'wc-cancelled', 'wc-on-hold' ],
					'meta_key'     => WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_UPDATE_IN_APP,
					'meta_compare' => '=',
					'meta_value'   => 1,
					'return'       => 'ids',
				] );

				foreach ( $posts as $post ) {
					// check charge status
					$this->gateway->maybe_update_subscription_details_in_app( $post->ID );
				}
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
				$this->gateway->ensure_cancelled_order_page();
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

				foreach ( WC()->cart->get_cart_contents() as $values ) {
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
