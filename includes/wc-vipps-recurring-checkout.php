<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Checkout {
	private static ?WC_Vipps_Recurring_Checkout $instance = null;

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
	}

	public function init() {
		add_action( 'wp_loaded', [ $this, 'register_scripts' ] );

		// Prevent previews and prefetches of the Vipps Checkout page starting and creating orders
		add_action( 'wp_head', [ $this, 'wp_head' ] );

		// The Vipps MobilePay Checkout feature which overrides the normal checkout process uses a shortcode
		add_shortcode( 'vipps_recurring_checkout', [ $this, 'shortcode' ] );
	}

	public function woocommerce_loaded() {

	}

	public function template_redirect() {
		global $post;

		if ( $post && is_page() && has_shortcode( $post->post_content, 'vipps_recurring_checkout' ) ) {
			add_filter( 'woocommerce_is_checkout', '__return_true' );

			add_filter( 'body_class', function ( $classes ) {
				$classes[] = 'vipps-checkout';
				$classes[] = 'woocommerce-checkout'; // Required by Pixel Your Site

				return apply_filters( 'woo_vipps_checkout_body_class', $classes );
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

		// todo: register React component instead?
//		wp_register_script( 'vipps-recurring-checkout', plugins_url( 'js/vipps-recurring-checkout.js', __FILE__ ), [
//			'woo-vipps-recurring',
//			'woo-vipps-recurring-sdk'
//		], filemtime( dirname( __FILE__ ) . "/js/vipps-recurring-checkout.js" ), 'true' );
	}

	public function shortcode() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
	}
}
