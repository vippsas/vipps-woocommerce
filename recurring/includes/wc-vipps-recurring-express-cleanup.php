<?php

defined( 'ABSPATH' ) || exit;

/** Cleanup policy for abandoned Express purchases, consumed by the existing hourly worker. */
class WC_Vipps_Recurring_Express_Cleanup {
	public const META_ABANDONED_AT = '_vipps_recurring_express_abandoned_at';
	private const META_QUEUED = '_vipps_recurring_express_cleanup_queued';
	private const RETENTION = 7 * DAY_IN_SECONDS;
	private WC_Gateway_Vipps_Recurring $gateway;

	public function __construct( WC_Gateway_Vipps_Recurring $gateway ) {
		$this->gateway = $gateway;
	}

	public static function register_hooks(): void {
		add_action( 'woocommerce_vipps_recurring_check_subscriptions_marked_for_deletion', static function () {
			( new self( WC_Vipps_Recurring::get_instance()->gateway() ) )->queue_abandoned_purchases();
		}, 5 );
	}

	public function queue_abandoned_purchases(): void {
		if ( $this->gateway->get_option( 'express_cleanup_abandoned_orders' ) !== 'yes' ) { return; }

		// Rotate through bounded pages so retained purchases do not block later candidates.
		$page = max( 1, (int) get_transient( '_vipps_recurring_express_cleanup_page' ) );
		$args = WC_Vipps_Recurring_Helper::add_meta_query_to_args( [
			'payment_method' => $this->gateway->id,
			'status' => 'cancelled', 'limit' => 25, 'page' => $page, 'paginate' => true,
			'orderby' => 'ID', 'order' => 'ASC',
		], WC_Vipps_Recurring_Profile::META_STATUS, '=', 'aborted', $this->gateway->use_high_performance_order_storage() );
		$result = wc_get_orders( $args );

		foreach ( $result->orders as $order ) {
			if ( $order->get_meta( self::META_QUEUED ) || ! $this->local_candidate( $order ) ) { continue; }
			$subscriptions = wcs_get_subscriptions_for_order( $order->get_id(), [ 'order_type' => 'parent' ] );
			if ( count( $subscriptions ) !== 1 ) { continue; }

			$subscription = reset( $subscriptions );
			$abandoned_at = $this->abandoned_at( $order );
			$order->update_meta_data( self::META_ABANDONED_AT, $abandoned_at );
			$order->save();
			$subscription->update_meta_data( WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_MARKED_FOR_DELETION, 1 );
			$subscription->save();
			$order->update_meta_data( self::META_QUEUED, true );
			$order->save();
		}

		set_transient( '_vipps_recurring_express_cleanup_page', $page < $result->max_num_pages ? $page + 1 : 1, DAY_IN_SECONDS );
	}

	private function abandoned_at( WC_Order $order ): int {
		$date = $order->get_date_modified() ?: $order->get_date_created();
		return (int) $order->get_meta( self::META_ABANDONED_AT ) ?: ( $date ? $date->getTimestamp() : 0 );
	}

	private function local_candidate( WC_Order $order ): bool {
		$abandoned_at = $this->abandoned_at( $order );
		return $this->gateway->get_option( 'express_cleanup_abandoned_orders' ) === 'yes'
			&& $order->get_payment_method() === $this->gateway->id
			&& $order->get_meta( WC_Vipps_Recurring_Express_Checkout::META_ATTEMPT )
			&& $order->get_meta( WC_Vipps_Recurring_Profile::META_STATUS ) === 'aborted'
			&& $order->has_status( 'cancelled' ) && ! $order->get_date_paid()
			&& in_array( trim( $order->get_billing_email() ), [ '', WC_Vipps_Recurring_Helper::FAKE_USER_EMAIL ], true )
			&& ! $order->get_meta( WC_Vipps_Recurring_Helper::META_CHARGE_CAPTURED )
			&& ! WC_Vipps_Recurring_Profile::protect_from_cleanup( $order )
			&& ! wcs_order_contains_renewal( $order )
			&& $abandoned_at > 0 && $abandoned_at <= time() - self::RETENTION;
	}

	/** Called only by the shared deletion worker. Never changes an agreement or charge. */
	public function delete_if_abandoned( WC_Subscription $subscription ): bool {
		$order_id = $subscription->get_parent_id();
		$order_key = '_vipps_recurring_express_order_lock_' . $order_id;
		$profile_key = '_vipps_recurring_profile_lock_' . $order_id;
		$order_lock = WC_Vipps_Recurring_Helper::acquire_purchase_lock( $order_key, 180 );
		if ( ! $order_lock ) { return false; }
		$profile_lock = null;
		$deleted = false;

		try {
			$profile_lock = WC_Vipps_Recurring_Helper::acquire_purchase_lock( $profile_key, 180 );
			if ( ! $profile_lock ) { return false; }
			$order = wc_get_order( $order_id );
			$subscription = wcs_get_subscription( $subscription->get_id() );
			if ( ! $order || ! $subscription || ! $this->local_candidate( $order )
				|| $subscription->get_payment_method() !== $this->gateway->id
				|| ! $subscription->has_status( [ 'pending', 'cancelled' ] )
				|| ( $subscription->get_billing_email() && trim( $subscription->get_billing_email() ) !== WC_Vipps_Recurring_Helper::FAKE_USER_EMAIL ) ) { return false; }

			$latest = $subscription->get_last_order( 'all' );
			$siblings = wcs_get_subscriptions_for_order( $order_id, [ 'order_type' => 'parent' ] );
			if ( ! $latest || $latest->get_id() !== $order_id || count( $siblings ) !== 1 ) { return false; }

			$context = [ 'test_mode' => $this->gateway->test_mode, 'merchant' => $this->gateway->merchant_serial_number ];
			$agreement_id = $order->get_meta( WC_Vipps_Recurring_Helper::META_AGREEMENT_ID );
			if ( ! $agreement_id || $order->get_meta( '_vipps_recurring_express_request_context' ) !== $context ) { return false; }
			$agreement = $this->gateway->api->get_agreement( $agreement_id );
			if ( ! in_array( $agreement->status, [ WC_Vipps_Agreement::STATUS_STOPPED, WC_Vipps_Agreement::STATUS_EXPIRED ], true ) ) { return false; }

			$charge_id = $order->get_meta( WC_Vipps_Recurring_Helper::META_CHARGE_ID );
			$found_charge = ! $charge_id;
			foreach ( $this->gateway->api->get_charges_for( $agreement_id ) as $charge ) {
				if ( ! in_array( $charge->status, [ WC_Vipps_Charge::STATUS_FAILED, WC_Vipps_Charge::STATUS_CANCELLED ], true ) ) { return false; }
				if ( $charge->id === $charge_id ) { $found_charge = true; }
			}
			if ( ! $found_charge ) { return false; }

			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Cleaning up confirmed unpaid abandoned Express purchase and subscription %s.', $order_id, $subscription->get_id() ) );
			// Use the same recoverable deletion behavior as the existing Checkout worker.
			if ( ! $order->delete() || ! $subscription->delete() ) { return false; }
			$deleted = true;
			return true;
		} catch ( Exception $error ) {
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Express cleanup deferred because payment status could not be confirmed.', $order_id ) );
			return false;
		} finally {
			if ( ! $deleted && $profile_lock && isset( $order ) && $order && $subscription ) {
				// Let discovery retry later without filling the shared worker with retained records.
				if ( $subscription->get_meta( WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_MARKED_FOR_DELETION ) ) {
					$subscription->delete_meta_data( WC_Vipps_Recurring_Helper::META_SUBSCRIPTION_MARKED_FOR_DELETION );
					$subscription->save();
				}
				if ( $order->get_meta( self::META_QUEUED ) ) {
					$order->delete_meta_data( self::META_QUEUED );
					$order->save();
				}
			}
			if ( $profile_lock ) { WC_Vipps_Recurring_Helper::release_purchase_lock( $profile_key, $profile_lock ); }
			WC_Vipps_Recurring_Helper::release_purchase_lock( $order_key, $order_lock );
		}
	}
}
