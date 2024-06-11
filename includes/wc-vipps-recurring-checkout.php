<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Checkout {
	private static ?WC_Vipps_Recurring_Checkout $instance = null;

	private ?WC_Gateway_Vipps_Recurring $gateway = null;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return WC_Vipps_Recurring_Checkout The *Singleton* instance.
	 */
	public static function get_instance(): WC_Vipps_Recurring_Checkout {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function register_hooks() {
		$instance = WC_Vipps_Recurring_Checkout::get_instance();
		add_action( 'init', [ $instance, 'init' ] );
		add_action( 'woocommerce_loaded', [ $instance, 'woocommerce_loaded' ] );
		add_action( 'template_redirect', [ $instance, 'template_redirect' ] );

		if ( is_admin() ) {
			add_action( 'admin_init', [ $instance, 'admin_init' ] );
		}
	}

	public function gateway(): ?WC_Gateway_Vipps_Recurring {
		if ( $this->gateway ) {
			return $this->gateway;
		}

		$this->gateway = WC_Vipps_Recurring::get_instance()->gateway();

		return $this->gateway;
	}

	public function maybe_load_cart() {
		if ( version_compare( WC_VERSION, '3.6.0', '>=' ) && WC()->is_rest_api_request() ) {
			if ( empty( $_SERVER['REQUEST_URI'] ) ) {
				return;
			}

			$rest_prefix = WC_Vipps_Recurring_Checkout_Rest_Api::$api_namespace;
			$req_uri     = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			$is_my_endpoint = ( false !== strpos( $req_uri, $rest_prefix ) );

			if ( ! $is_my_endpoint ) {
				return;
			}

			require_once WC_ABSPATH . 'includes/wc-cart-functions.php';
			require_once WC_ABSPATH . 'includes/wc-notice-functions.php';

			if ( null === WC()->session ) {
				$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

				// Prefix session class with global namespace if not already namespaced
				if ( false === strpos( $session_class, '\\' ) ) {
					$session_class = '\\' . $session_class;
				}

				WC()->session = new $session_class();
				WC()->session->init();
			}

			/**
			 * For logged in customers, pull data from their account rather than the
			 * session which may contain incomplete data.
			 */
			if ( is_null( WC()->customer ) ) {
				if ( is_user_logged_in() ) {
					WC()->customer = new WC_Customer( get_current_user_id() );
				} else {
					WC()->customer = new WC_Customer( get_current_user_id(), true );
				}

				// Customer should be saved during shutdown.
				add_action( 'shutdown', array( WC()->customer, 'save' ), 10 );
			}

			// Load Cart.
			if ( null === WC()->cart ) {
				WC()->cart = new WC_Cart();
			}
		}
	}

	public function init() {
		require_once __DIR__ . '/wc-vipps-recurring-checkout-rest-api.php';
		WC_Vipps_Recurring_Checkout_Rest_Api::get_instance();

		add_action( 'wp_loaded', [ $this, 'maybe_load_cart' ], 5 );

		add_action( 'wp_loaded', [ $this, 'register_scripts' ] );

		// Prevent previews and prefetches of the Vipps Checkout page starting and creating orders
		add_action( 'wp_head', [ $this, 'wp_head' ] );

		// The Vipps MobilePay Checkout feature which overrides the normal checkout process uses a shortcode
		add_shortcode( 'vipps_recurring_checkout', [ $this, 'shortcode' ] );

		add_action( 'woo_vipps_recurring_checkout_check_order_status', [ $this, 'check_order_status' ] );
		add_action( 'woo_vipps_recurring_checkout_check_order_status_rest_api', [ $this, 'check_order_status' ] );

		// For Checkout, we need to know any time and as soon as the cart changes, so fold all the events into a single one
		add_action( 'woocommerce_add_to_cart', function () {
			do_action( 'vipps_recurring_cart_changed' );
		}, 10, 0 );

		// Cart emptied
		add_action( 'woocommerce_cart_emptied', function () {
			do_action( 'vipps_recurring_cart_changed' );
		}, 10, 0 );

		// After updating quantities
		add_action( 'woocommerce_after_cart_item_quantity_update', function () {
			do_action( 'vipps_recurring_cart_changed' );
		}, 10, 0 );

		// Blocks and ajax
		add_action( 'woocommerce_cart_item_removed', function () {
			do_action( 'vipps_recurring_cart_changed' );
		}, 10, 0 );

		// Restore deleted entry
		add_action( 'woocommerce_cart_item_restored', function () {
			do_action( 'vipps_recurring_cart_changed' );
		}, 10, 0 );

		// Normal cart form update
		add_filter( 'woocommerce_update_cart_action_cart_updated', function ( $updated ) {
			do_action( 'vipps_recurring_cart_changed' );

			return $updated;
		} );

		// Then handle the actual cart change
		add_action( 'vipps_recurring_cart_changed', [ $this, 'cart_changed' ] );

		add_action( 'woo_vipps_recurring_checkout_callback', [ $this, 'handle_callback' ], 10, 2 );
	}

	public function admin_init() {
		// Checkout page
		add_filter( 'woocommerce_settings_pages', array( $this, 'woocommerce_settings_pages' ) );
	}

	public function cart_changed() {
		$pending_order_id = is_a( WC()->session, 'WC_Session' ) ? WC()->session->get( WC_Vipps_Recurring_Helper::SESSION_CHECKOUT_PENDING_ORDER_ID ) : false;
		$order            = $pending_order_id ? wc_get_order( $pending_order_id ) : null;

		if ( ! $order ) {
			return;
		}

		WC_Vipps_Recurring_Logger::log( sprintf( "Checkout cart changed while session %d in progress, attempting to cancel", $order->get_id() ) );
		$this->abandon_checkout_order( $order );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Data_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function check_order_status( $order_id ) {
		$order = wc_get_order( $order_id );

		$session = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION );
		$session = $this->gateway()->api->checkout_poll( $session['pollingUrl'] );

		$this->handle_payment( $order, $session );
	}

	public function woocommerce_settings_pages( $settings ) {
		$checkout_enabled = get_option( WC_Vipps_Recurring_Helper::OPTION_CHECKOUT_ENABLED, false );
		if ( ! $checkout_enabled ) {
			return $settings;
		}

		// Find out where the end of the section advanced_page_options is
		$i     = 0;
		$count = count( $settings );

		for ( ; $i < $count; $i ++ ) {
			if ( $settings[ $i ]['type'] === 'sectionend' && $settings[ $i ]['id'] === 'advanced_page_options' ) {
				break;
			}
		}

		if ( $i < $count ) {
			array_splice( $settings, $i, 0, [
				[
					'title'    => __( 'Vipps/MobilePay Recurring Checkout Page', 'vipps-recurring-payments-gateway-for-woocommerce' ),
					'desc'     => __( 'This page is used for the alternative Vipps/MobilePay checkout page, which you can choose to use instead of the normal WooCommerce checkout page. ', 'vipps-recurring-payments-gateway-for-woocommerce' ) . sprintf( __( 'Page contents: [%1$s]', 'woocommerce' ), 'vipps_recurring_checkout' ),
					'id'       => 'woocommerce_vipps_recurring_checkout_page_id',
					'type'     => 'single_select_page_with_search',
					'default'  => '',
					'class'    => 'wc-page-search',
					'css'      => 'min-width:300px;',
					'args'     => [
						'exclude' =>
							[
								wc_get_page_id( 'myaccount' ),
							],
					],
					'desc_tip' => true,
					'autoload' => false,
				]
			] );
		}

		return $settings;
	}

	public function woocommerce_loaded() {
		// Higher priority than the single payments Vipps plugin
		add_filter( 'woocommerce_get_checkout_page_id', function ( $id ) {
			$checkout_enabled = get_option( WC_Vipps_Recurring_Helper::OPTION_CHECKOUT_ENABLED, false );

			if ( ! $checkout_enabled ) {
				return $id;
			}

			$checkout_id = $this->gateway()->checkout_is_available();
			if ( $checkout_id ) {
				return $checkout_id;
			}

			return $id;
		}, 20 );
	}

	public function template_redirect() {
		global $post;

		if ( $post && is_page() && has_shortcode( $post->post_content, 'vipps_recurring_checkout' ) ) {
			add_filter( 'woocommerce_is_checkout', '__return_true' );

			add_filter( 'body_class', function ( $classes ) {
				$classes[] = 'vipps-recurring-checkout';
				$classes[] = 'woocommerce-checkout'; // Required by Pixel Your Site

				return apply_filters( 'woo_vipps_recurring_checkout_body_class', $classes );
			} );

			// Suppress the title for this page
			$post_to_hide_title_for = $post->ID;
			add_filter( 'the_title', function ( $title, $postid = 0 ) use ( $post_to_hide_title_for ) {
				if ( ! is_admin() && $postid == $post_to_hide_title_for && is_singular() && in_the_loop() ) {
					$title = "";
				}

				return $title;
			}, 10, 2 );

			wc_nocache_headers();
		}
	}

	public function wp_head() {
		// If we have a Vipps MobilePay Checkout page, stop iOS from giving previews of it that
		// starts the session - iOS should use the visibility API of the browser for this, but it doesn't as of 2021-11-11
		$checkout_id = wc_get_page_id( 'vipps_recurring_checkout' );
		if ( $checkout_id ) {
			$url = get_permalink( $checkout_id );
			echo "<style> a[href=\"$url\"] { -webkit-touch-callout: none;  } </style>\n";
		}
	}

	public function register_scripts() {
		$sdk_url = 'https://checkout.vipps.no/vippsCheckoutSDK.js';
		wp_register_script( 'woo-vipps-recurring-sdk', $sdk_url );

		// Register our React component
		wp_enqueue_style(
			'woo-vipps-recurring-checkout',

			WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/build/checkout.css', [],
			filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/checkout.css' )
		);

		$asset = require WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/checkout.asset.php';

		wp_enqueue_script(
			'woo-vipps-recurring-checkout',
			WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/build/checkout.js',
			array_merge( $asset['dependencies'], [ 'woo-vipps-recurring', 'woo-vipps-recurring-sdk' ] ),
			filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/checkout.js' ),
			true
		);
	}

	/**
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 * @throws WC_Vipps_Recurring_Config_Exception
	 */
	public function shortcode() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		// No point in expanding this unless we are actually doing the checkout. IOK 2021-09-03
		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
		add_filter( 'woo_vipps_recurring_is_vipps_checkout', '__return_true' );

		// Defer to the normal code for endpoints IOK 2022-12-09
		if ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
			return do_shortcode( "[woocommerce_checkout]" );
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			$this->abandon_checkout_order( false );
			ob_start();
			wc_get_template( 'cart/cart-empty.php' );

			return ob_get_clean();
		}

		// Previously registered, now enqueue this script which should then appear in the footer.
		wp_enqueue_script( 'vipps-recurring-checkout' );

		do_action( 'vipps_recurring_checkout_before_get_session' );

		// We need to be able to check if we still have a live, good session, in which case
		// we can open the iframe directly. Otherwise, the form we are going to output will
		// create the iframe after a button press which will create a new order.
		$session = $this->current_pending_session();

		// This is the current pending order id, if it exists. Will be used to restart orders etc
		$pending_order_id = WC_Vipps_Recurring_Helper::get_checkout_pending_order_id();

		// Localize script with variables we need to reveal to the frontend
		$data = [
			'pendingOrderId' => $pending_order_id,
			'data'           => $session
		];

		wp_add_inline_script( 'woo-vipps-recurring-checkout', 'window.VippsRecurringCheckout = ' . wp_json_encode( $data ), 'before' );

		return '<div id="vipps-mobilepay-recurring-checkout"></div>';
	}

	public function make_order_summary( $order ): WC_Vipps_Checkout_Session_Transaction_Order_Summary {
		$order_lines = [];

		$bottom_line = ( new WC_Vipps_Checkout_Session_Transaction_Order_Summary_Bottom_Line() )
			->set_currency( $order->get_currency() )
			->set_gift_card_amount( apply_filters( 'woo_vipps_recurring_order_gift_card_amount', 0, $order ) * 100 )
			->set_tip_amount( apply_filters( 'woo_vipps_recurring_order_tip_amount', 0, $order ) * 100 )
			->set_terminal_id( apply_filters( 'woo_vipps_recurring_order_terminal_id', 'woocommerce', $order ) )
			->set_receipt_number( strval( WC_Vipps_Recurring_Helper::get_id( $order ) ) );

		foreach ( $order->get_items() as $order_item ) {
			$order_line      = [];
			$product_id      = $order_item->get_product_id(); // sku can be tricky
			$total_no_tax    = $order_item->get_total();
			$tax             = $order_item->get_total_tax();
			$total           = $tax + $total_no_tax;
			$subtotal_no_tax = $order_item->get_subtotal();
			$subtotal_tax    = $order_item->get_subtotal_tax();
			$subtotal        = $subtotal_no_tax + $subtotal_tax;
			$quantity        = $order_item->get_quantity();
			$unit_price      = $subtotal / $quantity;

			// Must do this to avoid rounding errors, since we get floats instead of money here :(
			$discount = round( 100 * $subtotal ) - round( 100 * $total );
			if ( $discount < 0 ) {
				$discount = 0;
			}

			$product = wc_get_product( $product_id );
			$url     = home_url( "/" );
			if ( $product ) {
				$url = get_permalink( $product_id );
			}

			if ( $subtotal_no_tax == 0 ) {
				$tax_percentage = 0;
			} else {
				$tax_percentage = ( ( $subtotal - $subtotal_no_tax ) / $subtotal_no_tax ) * 100;
			}
			$tax_percentage = abs( round( $tax_percentage ) );

			$unit_info                             = [];
			$order_line['name']                    = $order_item->get_name();
			$order_line['id']                      = strval( $product_id );
			$order_line['totalAmount']             = round( $total * 100 );
			$order_line['totalAmountExcludingTax'] = round( $total_no_tax * 100 );
			$order_line['totalTaxAmount']          = round( $tax * 100 );

			$order_line['taxPercentage'] = $tax_percentage;
			$unit_info['unitPrice']      = round( $unit_price * 100 );
			$unit_info['quantity']       = strval( $quantity );
			$unit_info['quantityUnit']   = 'PCS';
			$order_line['unitInfo']      = $unit_info;
			$order_line['discount']      = $discount;
			$order_line['productUrl']    = $url;
			$order_line['isShipping']    = false;

			$order_lines[] = $order_line;
		}

		foreach ( $order->get_items( 'fee' ) as $order_item ) {
			$order_line                            = [];
			$total_no_tax                          = $order_item->get_total();
			$tax                                   = $order_item->get_total_tax();
			$total                                 = $tax + $total_no_tax;
			$tax_percentage                        = ( ( $total - $total_no_tax ) / $total_no_tax ) * 100;
			$tax_percentage                        = abs( round( $tax_percentage ) );
			$order_line['name']                    = $order_item->get_name();
			$order_line['id']                      = substr( sanitize_title( $order_line['name'] ), 0, 254 );
			$order_line['totalAmount']             = round( $total * 100 );
			$order_line['totalAmountExcludingTax'] = round( $total_no_tax * 100 );
			$order_line['totalTaxAmount']          = round( $tax * 100 );
			$order_line['discount']                = 0;
			$order_line['taxPercentage']           = $tax_percentage;

			$order_lines[] = $order_line;
		}

		// Handle shipping
		foreach ( $order->get_items( 'shipping' ) as $order_item ) {
			$order_line         = [];
			$order_line['name'] = $order_item->get_name();
			$order_line['id']   = strval( $order_item->get_method_id() );
			if ( method_exists( $order_item, 'get_instance_id' ) ) {
				$order_line['id'] .= ":" . $order_item->get_instance_id();
			}

			$total_no_tax    = $order_item->get_total();
			$tax             = $order_item->get_total_tax();
			$total           = $tax + $total_no_tax;
			$subtotal_no_tax = $total_no_tax;
			$subtotal_tax    = $tax;
			$subtotal        = $subtotal_no_tax + $subtotal_tax;

			if ( $subtotal_no_tax == 0 ) {
				$tax_percentage = 0;
			} else {
				$tax_percentage = ( ( $subtotal - $subtotal_no_tax ) / $subtotal_no_tax ) * 100;
			}
			$tax_percentage = abs( round( $tax_percentage ) );

			$order_line['totalAmount']             = round( $total * 100 );
			$order_line['totalAmountExcludingTax'] = round( $total_no_tax * 100 );
			$order_line['totalTaxAmount']          = round( $tax * 100 );
			$order_line['taxPercentage']           = $tax_percentage;

			$unit_info                 = [];
			$unit_info['unitPrice']    = round( $total * 100 );
			$unit_info['quantity']     = strval( 1 );
			$unit_info['quantityUnit'] = 'PCS';

			$order_line['unitInfo']   = $unit_info;
			$discount                 = 0;
			$order_line['discount']   = $discount;
			$order_line['isShipping'] = true;

			$order_lines[] = $order_line;
		}

		return ( new WC_Vipps_Checkout_Session_Transaction_Order_Summary() )
			->set_order_lines( $order_lines )
			->set_order_bottom_line( $bottom_line );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function current_pending_session(): array {
		// If this is set, this is a currently pending order which is maybe still valid
		$pending_order_id = WC_Vipps_Recurring_Helper::get_checkout_pending_order_id();
		$order            = $pending_order_id ? wc_get_order( $pending_order_id ) : null;

		# If we do have an order, we need to check if it is 'pending', and if not, we have to check its payment status
		$payment_status = null;
		$redirect       = null;

		if ( $order ) {
			if ( $order->get_status() === 'pending' ) {
				$payment_status = 'INITIATED'; // Just assume this for now
			} else {
				$payment_status = $this->gateway()->check_charge_status( $pending_order_id ) ?? 'UNKNOWN';
			}

			if ( $payment_status === 'SUCCESS' ) {
				$redirect = apply_filters( 'wc_vipps_recurring_merchant_redirect_url', WC_Vipps_Recurring_Helper::get_payment_redirect_url( $order ) );
			}
		}

		if ( in_array( $payment_status, [ 'authorized', 'complete' ] ) ) {
			$this->abandon_checkout_order( false );
		} elseif ( $payment_status == 'cancelled' ) {
			WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Vipps/MobilePay checkout session cancelled (pending session)", $order->get_id() ) );

			// This will mostly just wipe the session.
			$this->abandon_checkout_order( $order );
		}

		// Now if we don't have an order right now, we should not have a session either, so fix that
		if ( ! $order ) {
			$this->abandon_checkout_order( false );
		}

		// Now check the orders vipps session if it exist
		$session = $order ? $order->get_meta( WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION ) : false;

		// A single word or array containing session data, containing token and frontendFrameUrl
		// ERROR EXPIRED FAILED
		$session_status = $session ? $this->get_checkout_status( $session ) : null;

		// If this is the case, there is no redirect, but the session is gone, so wipe the order and session.
		if ( in_array( $session_status, [ 'ERROR', 'EXPIRED', 'FAILED' ] ) ) {
			WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Vipps/MobilePay checkout session is gone", $order->get_id() ) );
			$this->abandon_checkout_order( $order );
		}

		// This will return either a valid vipps session, nothing, or redirect.
		return [ 'order' => $order ? $order->get_id() : false, 'session' => $session, 'redirect_url' => $redirect ];
	}

	/**
	 * @param $session
	 *
	 * @return string
	 */
	public function get_checkout_status( $session ): string {
		if ( $session && isset( $session['token'] ) ) {
			try {
				$response = $this->gateway()->api->checkout_poll( $session['pollingUrl'] );

				if ( ( $response['sessionState'] ?? "" ) == 'SessionExpired' ) {
					return 'EXPIRED';
				}

				return 'SUCCESS';
			} catch ( WC_Vipps_Recurring_Exception $e ) {
				if ( $e->responsecode == 400 ) {
					return 'INITIATED';
				} else if ( $e->responsecode == 404 ) {
					return 'EXPIRED';
				} else {
					WC_Vipps_Recurring_Logger::log( sprintf( "Error polling status - error message %s", $e->getMessage() ) );

					return 'ERROR';
				}
			} catch ( Exception $e ) {
				WC_Vipps_Recurring_Logger::log( sprintf( "Error polling status - error message %s", $e->getMessage() ) );

				return 'ERROR';
			}
		}

		return "ERROR";
	}

	public function abandon_checkout_order( $order ) {
		if ( WC()->session ) {
			WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_CHECKOUT_PENDING_ORDER_ID, 0 );
			WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ADDRESS_HASH, false );
		}

		if ( is_a( $order, 'WC_Order' ) && $order->get_status() === 'pending' ) {
			// We want to kill orders that have failed, or that the user has abandoned. To do this,
			// we must ensure that no race or other mechanism kills the order while or after being paid.
			// if order is in the process of being finalized, don't kill it
			if ( WC_Vipps_Recurring_Helper::order_locked( $order ) ) {
				return false;
			}

			// Get it again to ensure we have all the info, and check status again
			clean_post_cache( $order->get_id() );
			$order = wc_get_order( $order->get_id() );
			if ( $order->get_status() !== 'pending' ) {
				return false;
			}

			// And to be extra sure, check status at Vipps/MobilePay
			$session       = $order->get_meta( WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION );
			$poll_endpoint = ( $session && isset( $session['pollingUrl'] ) ) ? $session['pollingUrl'] : false;

			if ( $poll_endpoint ) {
				try {
					$poll_data     = $this->gateway()->api->checkout_poll( $poll_endpoint );
					$session_state = ( ! empty( $poll_data ) && is_array( $poll_data ) && isset( $poll_data['sessionState'] ) ) ? $poll_data['sessionState'] : "";
					WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Checking Checkout status on cart/order change: %s", $order->get_id(), $session_state ) );
					if ( $session_state === 'PaymentSuccessful' || $session_state === 'PaymentInitiated' ) {
						// If we have started payment, we do not kill the order.
						WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Checkout payment started - cannot cancel", $order->get_id() ) );

						return false;
					}
				} catch ( Exception $e ) {
					WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Could not get Checkout status for order. Order is still in progress while cancelling', $order->get_id() ) );
				}
			}

			// NB: This can *potentially* be revived by a callback!
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Cancelling Checkout order because order changed', $order->get_id() ) );
			$order->set_status( 'cancelled', __( "Order specification changed - order abandoned by customer in Checkout", 'vipps-recurring-payments-gateway-for-woocommerce' ), false );

			// Also mark for deletion and remove stored session
			$order->delete_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION );

			// todo: make a cron that checks for this value
			$order->update_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_MARKED_FOR_DELETION, 1 );
			$order->save();
		}
	}

	/**
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Data_Exception
	 */
	public function handle_callback( array $body, string $authorization_token ) {
		// Get the order from the authorization token
		WC_Vipps_Recurring_Logger::log( sprintf( "Handling Vipps/MobilePay Checkout callback: %s - authorization token: %s", json_encode( $body ), $authorization_token ) );

		$order_ids = wc_get_orders( [
			'meta_key'     => WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN,
			'meta_value'   => wp_hash_password( $authorization_token ),
			'meta_compare' => '=',
			'return'       => 'ids'
		] );

		if ( empty( $order_ids ) ) {
			WC_Vipps_Recurring_Logger::log( sprintf( "Found no order ids in Vipps/MobilePay Checkout callback for authorization token: %s", $authorization_token ) );

			return;
		}

		$order_id = array_pop( $order_ids );
		$order    = wc_get_order( $order_id );

		$this->handle_payment( $order, $body );
	}

	/**
	 * @param WC_Order $order
	 * @param array $session
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 * @throws Exception
	 */
	public function handle_payment( WC_Order $order, array $session ) {
		$order_id     = WC_Vipps_Recurring_Helper::get_id( $order );
		$agreement_id = $session['subscriptionDetails']['agreementId'];
		$status       = $session['sessionState'];

		WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Handling Vipps/MobilePay Checkout callback for agreement ID %s with status %s", $order_id, $agreement_id, $status ) );

		// This makes sure we are covered by all our normal cron checks as well
		WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, true );
		WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, $agreement_id );

		$order_charge_id = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID );
		if ( empty( $order_charge_id ) ) {
			$charges = $this->gateway()->api->get_charges_for( $agreement_id );
			/** @var WC_Vipps_Charge $charge */
			$charge = array_pop( $charges );

			WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID, $charge->id );
		}

		$order->save();

		// "SessionCreated" "PaymentInitiated" "SessionExpired" "PaymentSuccessful" "PaymentTerminated"
		if ( in_array( $status, [ 'SessionExpired', 'PaymentTerminated' ] ) ) {
			$this->abandon_checkout_order( $order );

			return;
		}

		if ( $status !== 'PaymentSuccessful' ) {
			return;
		}

		// Create a subscription on success, and set all the required values like _charge_id, _agreement_id, and so on.
		// On success, we might have to create a user as well, if they don't already exist, this is because Woo Subscriptions REQUIRE a user.
		if ( ! $order->get_customer_id( 'edit' ) ) {
			$email = $session['billingDetails']['email'];
			$user  = get_user_by( 'email', $email );

			$user_id = $user->ID;
			if ( ! $user_id ) {
				WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Handling Vipps/MobilePay Checkout creating a new customer", $order_id ) );

				$email_parts = explode( '@', $email );
				$username    = $email_parts[0] . wp_generate_password( 4 );
				$password    = wp_generate_password( 24 );
				$user_id     = wp_create_user( $username, $password, $email );

				$user = get_user_by_email( $email );
				do_action( 'retrieve_password', $user->user_login );
			}

			$order->set_customer_id( $user_id );
		}

		$this->maybe_update_order_billing_and_shipping( $order, $session );
		$order->save();

		// Create subscription
		$existing_subscriptions = wcs_get_subscriptions_for_order( $order );

		if ( empty( $existing_subscriptions ) ) {
			$order         = wc_get_order( $order_id );
			$subscriptions = $this->gateway()->create_partial_subscriptions_from_order( $order );

			/** @var WC_Subscription $subscription */
			$subscription = array_pop( $subscriptions );
			WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, $agreement_id );

			WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Handling Vipps/MobilePay Checkout created a new subscription: %s", $order_id, WC_Vipps_Recurring_Helper::get_id( $subscription ) ) );

			$subscription->save();
		}

		$this->gateway()->check_charge_status( $order_id );
	}

	/**
	 * @throws WC_Data_Exception
	 */
	public function maybe_update_order_billing_and_shipping( WC_Order $order, $session ): void {
		if ( isset( $session['billingDetails'] ) ) {
			$contact = $session['billingDetails'];
			$order->set_billing_email( $contact['email'] );
			$order->set_billing_phone( $contact['phoneNumber'] );
			$order->set_billing_first_name( $contact['firstName'] );
			$order->set_billing_last_name( $contact['lastName'] );
			$order->set_billing_address_1( $contact['streetAddress'] );
			$order->set_billing_city( $contact['city'] );
			$order->set_billing_postcode( $contact['postalCode'] );
			$order->set_billing_country( $contact['country'] );
		}

		if ( isset( $session['shippingDetails'] ) ) {
			$contact = $session['shippingDetails'];
			$order->set_shipping_first_name( $contact['firstName'] );
			$order->set_shipping_last_name( $contact['lastName'] );
			$order->set_shipping_address_1( $contact['streetAddress'] );
			$order->set_shipping_city( $contact['city'] );
			$order->set_shipping_postcode( $contact['postalCode'] );
			$order->set_shipping_country( $contact['country'] );
		}
	}
}
