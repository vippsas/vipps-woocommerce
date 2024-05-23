<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Checkout_Rest_Api {
	private string $api_namespace = 'vipps-mobilepay-recurring/v1/checkout';

	private static ?WC_Vipps_Recurring_Checkout_Rest_Api $instance = null;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return WC_Vipps_Recurring_Checkout_Rest_Api
	 */
	public static function get_instance(): WC_Vipps_Recurring_Checkout_Rest_Api {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'init' ] );
	}

	public function init() {
		register_rest_route( $this->api_namespace, '/session', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_session' ],
			'permission_callback' => '__return_true'
		] );
	}

	public function create_session( WP_REST_Request $request ) {
		// todo: create a checkout session
		// todo: create an order

		return [];
	}
}
