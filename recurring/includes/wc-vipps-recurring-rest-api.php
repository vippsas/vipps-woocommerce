<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Rest_Api {
	private string $api_namespace = 'vipps-mobilepay-recurring/v1';

	private static ?WC_Vipps_Recurring_Rest_Api $instance = null;

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
		add_action( 'rest_api_init', [ $this, 'init' ] );
	}

	public function init() {
		register_rest_route( $this->api_namespace, '/orders/status/(?P<order_id>[0-9]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'order_status' ],
			'permission_callback' => '__return_true'
		] );
	}

	/**
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Data_Exception
	 */
	public function order_status( WP_REST_Request $request ) {
		$order_id  = $request->get_param( 'order_id' );
		$order_key = $request->get_param( 'key' );

		$order = wc_get_order( $order_id );

		if ( ! $order || ! is_string( $order_key ) || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return new WP_Error(
				'not_found',
				'Order not found.',
				[ 'status' => 404 ]
			);
		}
		if ( $order->get_meta( WC_Vipps_Recurring_Profile::META_REQUIRED ) || $order->get_meta( WC_Vipps_Recurring_Express_Checkout::META_SAVED_CART ) ) {
			if ( ! WC()->session || ! WC()->cart ) { wc_load_cart(); }
		}

		$gateway = WC_Vipps_Recurring::get_instance()->gateway();

		do_action( 'wc_vipps_recurring_before_rest_api_check_order_status', $order_id );

		// Skip the lock here, otherwise we get a false result
		$gateway->check_charge_status( $order_id, true );
		$order        = wc_get_order( $order_id );
		$agreement_id = WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order );

		if ( ! $agreement_id ) {
			if ( $order->get_meta( WC_Vipps_Recurring_Profile::META_STATUS ) === 'needs_attention' ) {
				return [ 'status' => 'PROFILE_PENDING', 'redirect_url' => $order->get_checkout_order_received_url() ];
			}

			return new WP_REST_Response( [
				'status'       => 'PENDING',
				'redirect_url' => false
			], 202 );
		}

		try {
			$agreement = $gateway->api->get_agreement( $agreement_id );
		} catch ( WC_Vipps_Recurring_Exception $exception ) {
			if ( $exception->response_code !== 404 ) {
				throw $exception;
			}

			return new WP_REST_Response( [
				'status'       => 'PENDING',
				'redirect_url' => false
			], 202 );
		}

		do_action( 'wc_vipps_recurring_after_rest_api_check_order_status', $order_id );

		if ( WC_Vipps_Recurring_Profile::needs_completion( $order ) ) {
			$attention = $order->get_meta( WC_Vipps_Recurring_Profile::META_STATUS ) === 'needs_attention';
			return [
				'status' => $attention ? 'PROFILE_PENDING' : 'PENDING',
				'redirect_url' => $attention ? $order->get_checkout_order_received_url() : false,
			];
		}

		$return_url = false;
		if ( in_array( $agreement->status, [ WC_Vipps_Agreement::STATUS_STOPPED, WC_Vipps_Agreement::STATUS_EXPIRED ], true ) ) {
			WC_Vipps_Recurring_Express_Checkout::get_instance()->restore_saved_cart( $order );
		}
		if ( $agreement->status !== 'PENDING' ) {
			if ( $agreement->status === 'ACTIVE' && ( $order->get_meta( WC_Vipps_Recurring_Helper::META_ORDER_IS_CHECKOUT )
				|| $order->get_meta( WC_Vipps_Recurring_Profile::META_REQUIRED ) ) ) {
				( new WC_Vipps_Recurring_Purchase( $gateway ) )->maybe_login_customer( $order->get_id(), $order_key );
			}
			$return_url = $order->get_checkout_order_received_url();
		}

		return [
			'status'       => $agreement->status,
			'redirect_url' => $return_url
		];
	}
}
