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

	private function gateway(): ?WC_Gateway_Vipps_Recurring {
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
	}

	public function admin_init() {
		// Checkout page
		add_filter( 'woocommerce_settings_pages', array( $this, 'woocommerce_settings_pages' ) );
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

//		if ( $session_info['redirect'] ) {
//			// This is always either the thankyou page or home_url()  IOK 2021-09-03
//			$redir = json_encode( $session_info['redirect'] );
//			$out   .= "<script>window.location.replace($redir);</script>";
//
//			return $out;
//		}
//
//		// Now the normal case.
//		$error_text  = apply_filters( 'woo_vipps_recurring_checkout_error', __( 'An error has occured - please reload the page to restart your transaction, or return to the shop', 'vipps-recurring-payments-gateway-for-woocommerce' ) );
//		$expire_text = apply_filters( 'woo_vipps_recurring_checkout_error', __( 'Your session has expired - please reload the page to restart, or return to the shop', 'vipps-recurring-payments-gateway-for-woocommerce' ) );
//
//		$out .= Vipps::instance()->spinner();
//
//		if ( ! $session_info['session'] ) {
//			$out .= "<div style='visibility:hidden' class='vipps_checkout_startdiv'>";
//
//			// translators: %s is  Vipps or MobilePay depending on what brand we're using
//			$out .= "<h2>" . sprintf( __( 'Press the button to complete your order with %s!', 'vipps-recurring-payments-gateway-for-woocommerce' ), WC_Gateway_Vipps_Recurring::get_instance()->get_method_title() ) . "</h2>";
//
//			// translators: %s is  Vipps or MobilePay depending on what brand we're using
//			$out .= '<div class="vipps_checkout_button_wrapper" ><button type="submit" class="button vipps_checkout_button vippsorange" value="1">' . sprintf( __( '%s Checkout', 'vipps-recurring-payments-gateway-for-woocommerce' ), WC_Gateway_Vipps_Recurring::get_instance()->get_method_title() ) . '</button></div>';
//			$out .= "</div>";
//		}
//
//		// If we have an actual live session right now, add it to the page on load. Otherwise, the session will be started using ajax after the page loads (and is visible)
//		if ( $session_info['session'] ) {
//			$token = $session_info['session']['token'];      // From Vipps
//			$src   = $session_info['session']['checkoutFrontendUrl'];  // From Vipps
//			$out   .= "<script>VippsSessionState = " . json_encode( array(
//					'token'               => $token,
//					'checkoutFrontendUrl' => $src
//				) ) . ";</script>\n";
//		} else {
//			$out .= "<script>VippsSessionState = null;</script>\n";
//		}
//
//		// Check that these exist etc
//		$out .= "<div id='vippscheckoutframe'>";
//
//		$out .= "</div>";
//		$out .= "<div style='display:none' id='vippscheckouterror'><p>$error_text</p></div>";
//		$out .= "<div style='display:none' id='vippscheckoutexpired'><p>$expire_text</p></div>";
//
//		// We impersonate the woocommerce-checkout form here mainly to work with the Pixel Your Site plugin IOK 2022-11-24
//		$out .= "<form id='vippsdata' class='woocommerce-checkout'>";
//		$out .= "<input type='hidden' id='vippsorderid' name='_vippsorder' value='" . intval( $current_pending ) . "' />";
////		// And this is for the order attribution feature of Woo 8.5 IOK 2024-01-09
////		if ( WC_Gateway_Vipps::instance()->get_option( 'vippsorderattribution' ) == 'yes' ) {
////			$out .= '<input type="hidden" id="vippsorderattribution" value="1" />';
////			ob_start();
////			do_action( 'woocommerce_after_order_notes' );
////			$out .= ob_get_clean();
////		}
//		$out .= wp_nonce_field( 'do_vipps_recurring_checkout', 'vipps_recurring_checkout_sec', 1, false );
//		$out .= "</form>";
//
//		return $out;
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
			if ( $order->get_status() == 'pending' ) {
				$payment_status = 'INITIATED'; // Just assume this for now
			} else {
				$payment_status = $this->gateway->check_charge_status( $pending_order_id ) ?? 'UNKNOWN';
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
		return [ 'order' => $order ? $order->get_id() : false, 'session' => $session, 'redirect' => $redirect ];
	}

	/**
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 * @throws WC_Vipps_Recurring_Config_Exception
	 */
	public function get_checkout_status( $session ): string {
		if ( $session && isset( $session['token'] ) ) {
			return $this->gateway->api->checkout_poll( $session['pollingUrl'] );
		}

		return "ERROR";
	}

	public function abandon_checkout_order( $order ) {
		if ( WC()->session ) {
			WC()->session->set( 'vipps_recurring_checkout_current_pending', 0 );
			WC()->session->set( 'vipps_recurring_address_hash', false );
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
			if ( $order->get_status() != 'pending' ) {
				return false;
			}

			// And to be extra sure, check status at Vipps/MobilePay
			$session       = $order->get_meta( WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION );
			$poll_endpoint = ( $session && isset( $session['pollingUrl'] ) ) ? $session['pollingUrl'] : false;

			if ( $poll_endpoint ) {
				try {
					$poll_data     = $this->gateway->api->checkout_poll( $poll_endpoint );
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
			$order->set_status( 'cancelled', __( "Order specification changed - this order abandoned by customer in Checkout  ", 'vipps-recurring-payments-gateway-for-woocommerce' ), false );
			// Also mark for deletion and remove stored session
			$order->delete_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION );

			// todo: make a cron that checks for this value
			$order->update_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_MARKED_FOR_DELETION, 1 );
			$order->save();
		}
	}
}
