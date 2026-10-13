<?php

defined( 'ABSPATH' ) || exit;

/**
 * Prepare and complete recurring purchases independently of their checkout UI.
 * Callers verify payment state and normalize contact data before completion.
 */
class WC_Vipps_Recurring_Purchase {

	private WC_Gateway_Vipps_Recurring $gateway;

	public function __construct( WC_Gateway_Vipps_Recurring $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Create pending subscriptions without requiring a guest to register first.
	 *
	 * @return WC_Subscription[]
	 */
	public function prepare_subscriptions( WC_Order $order ): array {
		$customer_id = (int) $order->get_customer_id( 'edit' );

		try {
			if ( ! $customer_id ) {
				$order->set_customer_id( $this->gateway->create_or_get_anonymous_system_customer()->get_id() );
				$order->save();
			}

			if ( wcs_order_contains_subscription( $order, 'any' ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order );
			} else {
				$subscriptions = $this->gateway->create_partial_subscriptions_from_order( $order );
			}

			if ( empty( $subscriptions ) ) {
				throw new Exception( 'Unable to prepare subscriptions for recurring order ' . $order->get_id() );
			}

			return $subscriptions;
		} finally {
			if ( ! $customer_id ) {
				$order->set_customer_id( 0 );
				$order->save();
			}
		}
	}

	public function assign_customer( WC_Order $order, int $customer_id ): void {
		$order->set_customer_id( $customer_id );
		$order->save();

		foreach ( wcs_get_subscriptions_for_order( $order ) as $subscription ) {
			$subscription->set_customer_id( $customer_id );
			$subscription->save();
		}
	}

	/** Record identifiers even when the customer has not finished approval yet. */
	public function record_agreement( WC_Order $order, string $agreement_id ): void {
		WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, $agreement_id );

		if ( ! WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID ) ) {
			$charges = $this->gateway->api->get_charges_for( $agreement_id );
			$charge  = array_pop( $charges );
			if ( $charge ) {
				WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID, $charge->id );
			}
		}

		$order->save();
	}

	/**
	 * Complete an API-confirmed purchase without reading the browser session.
	 * Contacts use WooCommerce address keys, e.g. first_name, address_1, postcode.
	 * Call record_agreement() before this method to store the order identifiers.
	 * A false result leaves customer/subscription completion available for retry.
	 */
	public function complete( WC_Order $order, string $agreement_id, array $billing, ?array $shipping = null ): bool {
		$order_id              = WC_Vipps_Recurring_Helper::get_id( $order );
		$email                 = sanitize_email( $billing['email'] ?? '' );
		$customer_id           = (int) $order->get_customer_id( 'edit' );
		$anonymous_customer_id = (int) get_option( WC_Vipps_Recurring_Helper::OPTION_ANONYMOUS_SYSTEM_CUSTOMER_ID );
		$user                  = $customer_id ? get_user_by( 'ID', $customer_id ) : false;
		$has_real_user         = $user && $customer_id !== $anonymous_customer_id;

		if ( ! $has_real_user && ! $email ) {
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Unable to assign the recurring order because the billing email is empty', $order_id ) );
			return false;
		}

		if ( ! $has_real_user ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$has_real_user = true;
				$order->set_customer_id( $user->ID );
				$order->save();
			}
		}

		if ( ! $has_real_user ) {
			$firstname = $billing['first_name'] ?? '';
			$lastname  = $billing['last_name'] ?? '';
			$userdata  = [
				'user_nicename' => $firstname,
				'display_name'  => "$firstname $lastname",
				'nickname'      => $firstname,
				'first_name'    => $firstname,
				'last_name'     => $lastname,
			];

			$username = apply_filters( 'woo_vipps_express_checkout_new_username', '', $email, $userdata, $order );
			$user_id  = wc_create_new_customer( $email, $username, wp_generate_password(), $userdata );
			if ( is_wp_error( $user_id ) ) {
				$user = get_user_by( 'email', $email );
				if ( ! $user ) {
					WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Unable to create or find the recurring customer: %s', $order_id, $user_id->get_error_message() ) );
					return false;
				}
				$user_id = $user->ID;
			}

			$customer = new WC_Customer( $user_id );
			$this->update_addresses( $customer, $billing, $shipping );
			$order->set_customer_id( $user_id );
			$order->save();

			do_action( 'woo_vipps_express_checkout_new_customer', new WC_Customer( $user_id ), $order_id );
		}

		$order = wc_get_order( $order_id );
		$this->update_addresses( $order, $billing ?: ( $shipping ?? [] ), $shipping );

		$subscriptions = wcs_get_subscriptions_for_order( $order );
		if ( empty( $subscriptions ) ) {
			$subscriptions = $this->gateway->create_partial_subscriptions_from_order( $order );
		}
		if ( empty( $subscriptions ) ) {
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Unable to create a subscription for recurring order and agreement ID %s', $order_id, $agreement_id ) );
			return false;
		}

		foreach ( $subscriptions as $subscription ) {
			WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, $agreement_id );
			$subscription->set_customer_id( $order->get_customer_id( 'edit' ) );
			wcs_copy_order_address( $order, $subscription );
			$subscription->save();
		}

		// The customer and subscriptions must be assigned before activation/capture.
		$this->gateway->check_charge_status( $order_id, true );
		return true;
	}

	/**
	 * Apply authoritative contact data. The caller selects the accepted address;
	 * a profile default must not be passed as a replacement for a confirmed one.
	 */
	public function update_addresses( $object, array $billing, ?array $shipping = null ): void {
		if ( empty( $billing ) ) {
			return;
		}

		if ( ! trim( $object->get_billing_email() ) || trim( $object->get_billing_email() ) === trim( WC_Vipps_Recurring_Helper::FAKE_USER_EMAIL ) ) {
			if ( array_key_exists( 'email', $billing ) ) {
				$object->set_billing_email( $billing['email'] );
			}
		}

		foreach ( [ 'phone', 'first_name', 'last_name', 'address_1', 'city', 'postcode', 'country' ] as $field ) {
			if ( array_key_exists( $field, $billing ) ) {
				$object->{ 'set_billing_' . $field }( $billing[ $field ] );
			}
		}

		$shipping = $shipping ?? $billing;
		foreach ( [ 'first_name', 'last_name', 'address_1', 'city', 'postcode', 'country' ] as $field ) {
			if ( array_key_exists( $field, $shipping ) ) {
				$object->{ 'set_shipping_' . $field }( $shipping[ $field ] );
			}
		}

		$object->save();
	}
}
