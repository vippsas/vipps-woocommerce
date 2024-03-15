<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Rest_Api {
	private string $api_namespace = 'vipps-mobilepay-recurring/v1';

	private static ?WC_Vipps_Recurring_Rest_Api $instance = null;

	private ?WC_Gateway_Vipps_Recurring $gateway = null;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return WC_Vipps_Recurring_Rest_Api
	 */
	public static function get_instance(): WC_Vipps_Recurring_Rest_Api {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$this->gateway = WC_Gateway_Vipps_Recurring::get_instance();

		add_action( 'rest_api_init', [ $this, 'init' ] );
	}

	public function init() {
		register_rest_route( $this->api_namespace, '/orders/status/(?P<order_id>[0-9]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'order_status' ],
			'permission_callback' => function () {
				return current_user_can( '__return_true' );
			}
		] );
	}

	/**
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 * @throws WC_Vipps_Recurring_Config_Exception
	 */
	public function order_status( WP_REST_Request $request ) {
		$order_id  = $request->get_param( 'order_id' );
		$order_key = $request->get_param( 'key' );

		$order = wc_get_order( $order_id );
		if ( ! $order || $order_key !== $order->get_order_key() ) {
			return new WP_Error(
				'not_found',
				'Order not found.',
				[ 'status' => 404 ]
			);
		}

		$this->gateway->check_charge_status( $order_id );
		$order = wc_get_order( $order_id );

		return [
			'status'       => $order->get_status(),
			'redirect_url' => $order->get_checkout_order_received_url()
		];
	}
}
