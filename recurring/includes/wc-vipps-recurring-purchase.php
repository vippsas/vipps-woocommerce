<?php

defined( 'ABSPATH' ) || exit;

/**
 * Prepare and complete recurring purchases independently of their checkout UI.
 * Callers verify payment state and normalize contact data before completion.
 */
class WC_Vipps_Recurring_Purchase {
	public const ADDRESS_SOURCE_PROFILE = 'profile';
	public const ADDRESS_SOURCE_SELECTED = 'selected';
	public const META_CONFIRMED_ADDRESSES = '_vipps_recurring_addresses_confirmed';

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
	 * Use SELECTED only for an address selected for this purchase, not a profile
	 * default. Confirm shop-calculated addresses before requesting approval.
	 * A false result leaves customer/subscription completion available for retry.
	 */
	public function complete( WC_Order $order, string $agreement_id, array $billing, ?array $shipping = null, string $address_source = self::ADDRESS_SOURCE_PROFILE, bool $check_payment = true ): bool {
		$this->validate_address_source( $address_source );
		$new_customer_id       = null;
		$order_id              = WC_Vipps_Recurring_Helper::get_id( $order );
		$email                 = sanitize_email( $billing['email'] ?? '' );
		$order_email           = sanitize_email( $order->get_billing_email() );
		if ( $order_email && $order_email !== WC_Vipps_Recurring_Helper::FAKE_USER_EMAIL ) {
			$email = $order_email;
		}
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

			$order->set_customer_id( $user_id );
			$order->save();
			$new_customer_id = $user_id;
		}

		$order = wc_get_order( $order_id );
		$this->update_addresses( $order, $billing ?: ( $shipping ?? [] ), $shipping, $address_source );
		$this->confirm_addresses( $order );

		if ( $new_customer_id ) {
			$customer = new WC_Customer( $new_customer_id );
			// Store the accepted order addresses, not conflicting profile defaults.
			$this->update_addresses( $customer, $order->get_address( 'billing' ), $order->get_address( 'shipping' ) );
			do_action( 'woo_vipps_express_checkout_new_customer', $customer, $order_id );
		}

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
			$this->confirm_addresses( $subscription );
		}

		// The customer and subscriptions must be assigned before activation/capture.
		if ( $check_payment ) {
			$this->gateway->check_charge_status( $order_id, true );
		}
		return true;
	}

	public function maybe_login_customer( int $order_id, ?string $key = null ): void {
		if ( ! $key ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$order_key_db = $order->get_order_key( 'code' );
		if ( ! hash_equals( $order_key_db, $key ) ) {
			return;
		}

		if ( ! WC()->session ) {
			return;
		}

		// Only the browser session that created this recurring order may log in its customer.
		$session_auth_token = WC()->session->get( WC_Vipps_Recurring_Helper::SESSION_ORDER_EXPRESS_AUTH_TOKEN );
		$order_auth_token   = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN );

		if ( ! is_string( $order_auth_token ) || ! is_string( $session_auth_token )
		     || ! $order_auth_token || ! $session_auth_token
		     || ! hash_equals( $order_auth_token, $session_auth_token ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$current_user_id = get_current_user_id();
			$order_user_id   = $order->get_customer_id( 'edit' );

			if ( $current_user_id !== $order_user_id ) {
				$this->assign_customer( $order, $current_user_id );
				WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Assigned the recurring order to returning logged-in customer %s instead of customer %s', $order_id, $current_user_id, $order_user_id ) );
			}

			WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ORDER_EXPRESS_AUTH_TOKEN, null );
			$order->delete_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN );
			$order->save();

			return;
		}

		$user = $order->get_user();
		if ( ! $user ) {
			return;
		}

		$roles       = $user->roles;
		$is_customer = ! empty( array_intersect( [ 'customer', 'subscriber' ], $roles ) );
		if ( ! $is_customer || user_can( $user, 'manage_woocommerce' ) || user_can( $user, 'manage_options' ) ) {
			WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Refusing to log in a privileged or non-customer user on the recurring return', $order_id ) );
			WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ORDER_EXPRESS_AUTH_TOKEN, null );
			$order->delete_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN );
			$order->save();

			return;
		}

		wc_set_customer_auth_cookie( $user->ID );
		WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ORDER_EXPRESS_AUTH_TOKEN, null );
		$order->delete_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN );
		$order->save();
	}


	/**
	 * Mark addresses used for the approved totals. New purchase adapters must call
	 * this before agreement creation; hosted Checkout calls it after selection.
	 */
	public function confirm_addresses( WC_Order $order ): void {
		$order->update_meta_data( self::META_CONFIRMED_ADDRESSES, true );
		$order->save();
	}

	/** Merge profile details; selected addresses may replace provisional order data. */
	public function update_addresses( $object, array $billing, ?array $shipping = null, string $source = self::ADDRESS_SOURCE_PROFILE ): void {
		$this->validate_address_source( $source );
		if ( $shipping !== null && ! array_filter( $shipping, static function ( $value ) {
			return is_scalar( $value ) && trim( (string) $value ) !== '';
		} ) ) {
			$shipping = null;
		}

		$this->update_contact( $object, 'billing', $billing, $source );
		$this->update_contact( $object, 'shipping', $shipping ?? $billing, $source );
		$object->save();
	}

	private function validate_address_source( string $source ): void {
		if ( ! in_array( $source, [ self::ADDRESS_SOURCE_PROFILE, self::ADDRESS_SOURCE_SELECTED ], true ) ) {
			throw new InvalidArgumentException( 'Unknown recurring address source' );
		}
	}

	private function update_contact( $object, string $type, array $contact, string $source ): void {
		$location_fields = [ 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ];
		$is_order        = $object instanceof WC_Order;
		$confirmed       = $is_order && ( $object->get_meta( self::META_CONFIRMED_ADDRESSES ) || $object->is_paid() );
		$replace         = $is_order && ! $confirmed && $source === self::ADDRESS_SOURCE_SELECTED;
		// Confirmed addresses that priced the order stay locked; others may still be filled.
		$compatible      = ! $confirmed || ! in_array( $type, $this->priced_address_types( $object ), true );

		// Do not combine a profile street with another address's city/postcode.
		if ( ! $replace && $compatible ) {
			foreach ( $location_fields as $field ) {
				$current = trim( (string) $object->{ 'get_' . $type . '_' . $field }( 'edit' ) );
				$incoming = is_scalar( $contact[ $field ] ?? null ) ? trim( (string) $contact[ $field ] ) : '';
				if ( $current !== '' && $incoming !== '' && strcasecmp( $current, $incoming ) !== 0 ) {
					$compatible = false;
					break;
				}
			}
		}

		$fields = array_merge( [ 'first_name', 'last_name', 'company' ], $location_fields );
		if ( $type === 'billing' ) {
			$fields = array_merge( $fields, [ 'email', 'phone' ] );
		}

		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $contact ) || ! is_scalar( $contact[ $field ] ) ) {
				continue;
			}
			$incoming = trim( (string) $contact[ $field ] );
			$current  = trim( (string) $object->{ 'get_' . $type . '_' . $field }( 'edit' ) );
			$is_location = in_array( $field, $location_fields, true );
			if ( $is_location && ! $replace && ! $compatible ) {
				continue;
			}
			// An explicitly selected street without a second line clears a stale unit.
			$clear_line2 = $replace && $field === 'address_2' && ! empty( $contact['address_1'] );
			if ( $incoming === '' && ! $clear_line2 ) {
				continue;
			}
			$empty = $current === '' || ( $field === 'email' && $current === WC_Vipps_Recurring_Helper::FAKE_USER_EMAIL );
			// Preserve email used for account matching, including selected purchases.
			if ( $empty || ( $replace && $field !== 'email' ) ) {
				$object->{ 'set_' . $type . '_' . $field }( $incoming );
			}
		}
	}

	/**
	 * Address types whose location determined the order's shipping or tax, following
	 * WC_Abstract_Order::get_tax_location(). Filling one of these, even an empty one,
	 * could change what a recalculation charges.
	 */
	private function priced_address_types( WC_Order $order ): array {
		$types      = [];
		$method_ids = array_map( static function ( $method ) {
			return $method->get_method_id();
		}, $order->get_shipping_methods() );

		if ( $method_ids ) {
			$types[] = 'shipping';
		}

		$pickup_methods = apply_filters( 'woocommerce_local_pickup_methods', [ 'legacy_local_pickup', 'local_pickup' ] );
		$base_pickup    = true === apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true )
		                  && array_intersect( $method_ids, $pickup_methods );

		if ( wc_tax_enabled() && ! $base_pickup ) {
			$tax_based_on = get_option( 'woocommerce_tax_based_on' );

			if ( $tax_based_on === 'shipping' ) {
				// WooCommerce falls back to billing while the shipping country is empty.
				$types[] = 'shipping';

				if ( ! $order->get_shipping_country() ) {
					$types[] = 'billing';
				}
			} elseif ( $tax_based_on === 'billing' ) {
				$types[] = 'billing';
			}
		}

		return array_unique( $types );
	}
}
