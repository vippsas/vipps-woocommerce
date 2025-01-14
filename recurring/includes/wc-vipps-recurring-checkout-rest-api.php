<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Checkout_Rest_Api {
	public static string $api_namespace = 'vipps-mobilepay-recurring/v1/checkout';

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
		register_rest_route( self::$api_namespace, '/session', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'poll_session' ],
			'permission_callback' => function () {
				// This route does not work if we don't have a cart.
				return ! empty( WC()->cart );
			}
		] );
	}

	/**
	 * @return array
	 * @throws WC_Data_Exception
	 */
	public function poll_session(): array {
		$checkout = WC_Vipps_Recurring_Checkout::get_instance();

		$pending_order_id = WC_Vipps_Recurring_Helper::get_checkout_pending_order_id();
		$order            = $pending_order_id ? wc_get_order( $pending_order_id ) : null;
		$return_url       = null;

		if ( $order ) {
			$return_url = WC_Vipps_Recurring_Helper::get_payment_redirect_url( $order );
		}

		$session = $order ? $order->get_meta( WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION ) : false;
		if ( ! $session ) {
			WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ADDRESS_HASH, false );

			return [ 'status' => 'EXPIRED', 'redirect_url' => false ];
		}

		add_filter( 'wc_vipps_recurring_is_vipps_checkout', '__return_true' );
		$status = $checkout->get_checkout_status( $session );

		$failed = $status === 'ERROR' || $status === 'SessionExpired' || $status === 'PaymentTerminated';
		if ( $failed ) {
			WC_Vipps_Recurring_Logger::log( sprintf( "Checkout session %d failed with message %s", $order->get_id(), $status ) );
			$checkout->abandon_checkout_order( $order );

			return [ 'status' => 'FAILED', 'redirect_url' => $return_url ];
		}

		if ( $status === 'PaymentSuccessful' ) {
			// Cancel the session, but do not cancel the order.
			$checkout->abandon_checkout_order( false );

			return [ 'status' => 'COMPLETED', 'redirect_url' => $return_url ];
		}

		// Disallow sessions that go on for too long.
		if ( is_a( $order, "WC_Order" ) ) {
			$created = $order->get_date_created();
			$now     = time();
			try {
				$timestamp = $created->getTimestamp();
			} catch ( Exception $e ) {
				// PHP 8 gives ValueError for certain older versions of WooCommerce here.
				$timestamp = intval( $created->format( 'U' ) );

			}
			$passed  = $now - $timestamp;
			$minutes = ( $passed / 60 );

			// Expire after 50 minutes
			if ( $minutes > 50 ) {
				WC_Vipps_Recurring_Logger::log( sprintf( "Checkout session %s expired after %d minutes (limit 50)", $order->get_id(), $minutes ) );
				$checkout->abandon_checkout_order( $order );

				return [ 'status' => 'EXPIRED', 'redirect_url' => false ];
			}
		}

		// This handles address information data from the poll if present. It is not, currently.
		$change             = false;
		$vipps_address_hash = WC()->session->get( WC_Vipps_Recurring_Helper::SESSION_ADDRESS_HASH );
		if ( isset( $status['billingDetails'] ) || isset( $status['shippingDetails'] ) ) {
			$serialized = sha1( json_encode( @$status['billingDetails'] ) . ':' . json_encode( @$status['shippingDetails'] ) );
			if ( $serialized != $vipps_address_hash ) {
				$change = true;
				WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ADDRESS_HASH, $serialized );
			}
		}

		if ( $change ) {
			$checkout->maybe_update_order_billing_and_shipping( $order, $status );
			$order->save();

			return [ 'status' => 'ORDER_CHANGE', 'redirect_url' => false ];
		}

		return [ 'status' => 'NO_CHANGE', 'redirect_url' => false ];
	}
}
