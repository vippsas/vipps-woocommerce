<?php

defined( 'ABSPATH' ) || exit;

/** Profile completion for new recurring express purchases, independent of Checkout. */
class WC_Vipps_Recurring_Profile {
	public const META_REQUIRED = '_vipps_recurring_profile_required';
	public const META_STATUS = '_vipps_recurring_profile_status';
	public const META_AGREEMENT = '_vipps_recurring_profile_agreement';
	public const META_CONTACT = '_vipps_recurring_profile_contact';
	public const META_NEXT_ATTEMPT = '_vipps_recurring_profile_next_attempt';
	public const META_ATTEMPTS = '_vipps_recurring_profile_attempts';
	public const META_CONSENT_TIME = '_vipps_recurring_profile_consent_time';
	public const META_PAYMENT_STATUS = '_vipps_recurring_profile_payment_status';
	public const META_MANUAL_RECOVERY = '_vipps_recurring_profile_manual_recovery';
	public const META_REASON = '_vipps_recurring_profile_reason';
	private WC_Gateway_Vipps_Recurring $gateway;

	public function __construct( WC_Gateway_Vipps_Recurring $gateway ) {
		$this->gateway = $gateway;
	}

	public static function register_hooks(): void {
		add_filter( 'woocommerce_order_actions', [ self::class, 'order_actions' ], 10, 2 );
		add_action( 'woocommerce_order_action_vipps_recurring_complete_profile', [ self::class, 'complete_manually' ] );
		add_filter( 'woocommerce_cancel_unpaid_order', [ self::class, 'allow_unpaid_cancellation' ], 20, 2 );
	}

	public static function allow_unpaid_cancellation( $cancel, $order ) {
		return $order instanceof WC_Order && self::protect_from_cleanup( $order ) ? false : $cancel;
	}

	public static function order_actions( array $actions, $order ): array {
		if ( $order instanceof WC_Order && self::needs_completion( $order )
		     && $order->get_meta( self::META_STATUS ) === 'needs_attention'
		     && ! in_array( $order->get_meta( self::META_REASON ), [ 'stopped_payment', 'agreement_creation' ], true ) ) {
			$actions['vipps_recurring_complete_profile'] = __( 'Retry recurring completion with saved billing details', 'woo-vipps' );
		}

		return $actions;
	}

	/** WooCommerce's order action supplies its own admin authorization and nonce. */
	public static function complete_manually( WC_Order $order ): void {
		if ( ! current_user_can( 'edit_shop_orders' ) || ! self::needs_completion( $order ) ) {
			return;
		}

		$order->update_meta_data( self::META_MANUAL_RECOVERY, true );
		$order->update_meta_data( WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, true );
		$order->save();

		WC_Vipps_Recurring::get_instance()->gateway()->check_charge_status( $order->get_id(), true );
	}

	/** Called only for a new express purchase, after the normal agreement filters. */
	public function prepare_agreement( WC_Order $order, WC_Vipps_Agreement $agreement ): void {
		$scope = [ 'name', 'email', 'phoneNumber' ];
		if ( $order->get_meta( WC_Vipps_Recurring_Helper::META_ORDER_NEEDS_SHIPPING ) ) {
			$scope[] = 'address';
		}

		if ( Vipps::instance()->gateway()->get_option( 'expresscheckout_always_address' ) === 'yes' ) {
			$scope[] = 'address';
		}

		$agreement->set_scope( implode( ' ', array_unique( $scope ) ) );
		$order->update_meta_data( self::META_REQUIRED, true );

		( new WC_Vipps_Recurring_Purchase( $this->gateway ) )->confirm_addresses( $order );
	}

	/** Bind retries/cache to one agreement; a new terminal-attempt replacement resets them. */
	public function bind_agreement( WC_Order $order, string $agreement_id ): void {
		if ( $order->get_meta( self::META_AGREEMENT ) === $agreement_id ) {
			return;
		}

		foreach ( [ self::META_CONTACT, self::META_CONSENT_TIME, self::META_ATTEMPTS, self::META_NEXT_ATTEMPT, self::META_PAYMENT_STATUS, self::META_MANUAL_RECOVERY, self::META_REASON ] as $key ) {
			$order->delete_meta_data( $key );
		}

		$order->update_meta_data( self::META_AGREEMENT, $agreement_id );
		$order->update_meta_data( self::META_STATUS, 'waiting' );
		$order->update_meta_data( WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, true );
		$order->save();
	}

	public static function needs_completion( WC_Order $order ): bool {
		return $order->get_meta( self::META_REQUIRED )
			&& ! wcs_is_subscription( $order )
			&& ! wcs_order_contains_renewal( $order )
			&& ! in_array( $order->get_meta( self::META_STATUS ), [ 'complete', 'aborted' ], true );
	}

	public static function protect_from_cleanup( WC_Order $order ): bool {
		return $order->get_meta( self::META_REQUIRED ) && ( self::needs_completion( $order )
			|| $order->is_paid() || in_array( $order->get_meta( self::META_PAYMENT_STATUS ),
				[ WC_Vipps_Charge::STATUS_CHARGED, WC_Vipps_Charge::STATUS_REFUNDED, WC_Vipps_Charge::STATUS_PARTIALLY_REFUNDED ], true ) );
	}

	/** False holds local activation/capture; the caller retains normal payment polling. */
	public function recover( WC_Order $order, WC_Vipps_Agreement $agreement ): bool {
		if ( ! self::needs_completion( $order ) ) {
			return true;
		}

		$lock_key = '_vipps_recurring_profile_lock_' . $order->get_id();
		$lock = WC_Vipps_Recurring_Helper::acquire_purchase_lock( $lock_key );
		if ( ! $lock ) { return false; }

		try {
			$order = wc_get_order( $order->get_id() );
			if ( ! self::needs_completion( $order ) ) {
				return true;
			}

			if ( $order->get_meta( WC_Vipps_Recurring_Helper::META_AGREEMENT_ID ) !== $agreement->id ) {
				return false;
			}

			$this->bind_agreement( $order, $agreement->id );
			if ( in_array( $agreement->status, [ WC_Vipps_Agreement::STATUS_EXPIRED, WC_Vipps_Agreement::STATUS_STOPPED ], true ) ) {
				$charge = $this->read_payment_status( $order, $agreement );
				if ( in_array( $order->get_meta( self::META_PAYMENT_STATUS ),
					[ WC_Vipps_Charge::STATUS_CHARGED, WC_Vipps_Charge::STATUS_REFUNDED, WC_Vipps_Charge::STATUS_PARTIALLY_REFUNDED ], true ) ) {
					$this->set_state( $order, 'needs_attention', 'stopped_payment' );
					return false;
				}

				// Do not pass a stopped purchase into normal activation/capture processing.
				$this->gateway->check_charge_agreement_cancelled( $order, $agreement, $charge );
				$order->delete_meta_data( self::META_CONTACT );
				$this->set_state( $order, 'aborted' );

				return false;
			}

			if ( $agreement->status !== WC_Vipps_Agreement::STATUS_ACTIVE ) {
				return false;
			}

			$manual = $order->get_meta( self::META_MANUAL_RECOVERY ) && $this->has_manual_customer( $order );
			if ( ! $manual && (int) $order->get_meta( self::META_NEXT_ATTEMPT ) > time() ) {
				return false;
			}

			// Preserve the actual initial-payment state while local completion is held.
			$this->read_payment_status( $order, $agreement );
			$contact = $order->get_meta( self::META_CONTACT );

			if ( $manual ) {
				$contact = $order->get_address( 'billing' );
			} elseif ( ! is_array( $contact ) || empty( $contact ) ) {
				if ( ! $order->get_meta( self::META_CONSENT_TIME ) ) {
					$start = $agreement->start instanceof DateTimeInterface ? $agreement->start->getTimestamp()
						: ( is_string( $agreement->start ) ? strtotime( $agreement->start ) : false );
					$order->update_meta_data( self::META_CONSENT_TIME, $start ?: time() );
					$order->save();
				}

				if ( time() - (int) $order->get_meta( self::META_CONSENT_TIME ) >= 168 * HOUR_IN_SECONDS ) {
					$this->set_state( $order, 'needs_attention', 'expired' );
					return false;
				}

				if ( empty( $agreement->sub ) ) {
					$this->retry( $order );
					return false;
				}

				$profile = $this->gateway->api->get_userinfo( $agreement );
				if ( ( $profile['sub'] ?? $agreement->sub ) !== $agreement->sub ) {
					$this->set_state( $order, 'needs_attention', 'invalid' );
					return false;
				}

				foreach ( [ 'email', 'given_name', 'family_name', 'phone_number' ] as $field ) {
					if ( ( $profile[ $field ] ?? '' ) === '[Expired]' ) {
						$this->set_state( $order, 'needs_attention', 'expired' );
						return false;
					}
				}

				$contact = $this->normalize_profile( $profile );
				if ( ! $this->has_contact( $contact ) ) {
					$this->retry( $order, 'incomplete' );
					return false;
				}

				// Cache only the purchase fields so account failures can retry after consent expires.
				$order->update_meta_data( self::META_CONTACT, $contact );
				$order->save();
			}

			$purchase = new WC_Vipps_Recurring_Purchase( $this->gateway );
			if ( ! $purchase->complete( $order, $agreement->id, $contact, null, WC_Vipps_Recurring_Purchase::ADDRESS_SOURCE_PROFILE, false ) ) {
				$this->retry( $order, 'customer' );
				return false;
			}

			// Refresh after the purchase service saved customer/subscription assignment.
			$order = wc_get_order( $order->get_id() );
			$this->set_state( $order, 'complete' );
			$order->delete_meta_data( self::META_CONTACT );
			$order->delete_meta_data( self::META_NEXT_ATTEMPT );
			$order->delete_meta_data( self::META_MANUAL_RECOVERY );
			$order->save();

			return true;
		} catch ( WC_Vipps_Recurring_Exception $error ) {
			if ( $error instanceof WC_Vipps_Recurring_Config_Exception || in_array( $error->response_code, [ 400, 401, 403 ], true ) ) {
				$this->set_state( $order, 'needs_attention', 'api' );
			} else {
				$this->retry( $order );
			}

			return false;
		} catch ( Exception $error ) {
			$this->retry( $order, 'customer' );

			return false;
		} finally {
			WC_Vipps_Recurring_Helper::release_purchase_lock( $lock_key, $lock );
		}
	}

	private function read_payment_status( WC_Order $order, WC_Vipps_Agreement $agreement ): ?WC_Vipps_Charge {
		$charge_id = $order->get_meta( WC_Vipps_Recurring_Helper::META_CHARGE_ID );
		$charge = null;

		if ( $charge_id ) {
			$charge = $this->gateway->api->get_charge( $agreement->id, $charge_id );
		} elseif ( (float) $order->get_total() > 0 ) {
			$charges = $this->gateway->api->get_charges_for( $agreement->id );

			$charge = array_pop( $charges );
			if ( $charge ) {
				$order->update_meta_data( WC_Vipps_Recurring_Helper::META_CHARGE_ID, $charge->id );
			}
		}

		if ( $charge ) {
			$order->update_meta_data( self::META_PAYMENT_STATUS, $charge->status );
			$order->save();
		}

		return $charge ?: null;
	}

	private function normalize_profile( array $profile ): array {
		$contact = [];
		foreach ( [ 'given_name' => 'first_name', 'family_name' => 'last_name', 'email' => 'email' ] as $source => $target ) {
			if ( is_string( $profile[ $source ] ?? null ) ) {
				$contact[ $target ] = sanitize_text_field( $profile[ $source ] );
			}
		}

		$contact['email'] = sanitize_email( $contact['email'] ?? '' );
		$address = is_array( $profile['address'] ?? null ) ? $profile['address'] : [];

		foreach ( [ 'street_address' => 'address_1', 'region' => 'city', 'postal_code' => 'postcode', 'country' => 'country' ] as $source => $target ) {
			if ( is_string( $address[ $source ] ?? null ) ) {
				$contact[ $target ] = $target === 'address_1' ? sanitize_textarea_field( $address[ $source ] ) : sanitize_text_field( $address[ $source ] );
			}
		}

		if ( ! empty( $contact['address_1'] ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $contact['address_1'], 2 );
			$contact['address_1'] = $lines[0];
			$contact['address_2'] = $lines[1] ?? '';
		}

		$phone = is_string( $profile['phone_number'] ?? null )
			? WC_Vipps_Recurring_Helper::normalize_phone_number( $profile['phone_number'], $contact['country'] ?? '' ) : false;

		if ( $phone ) {
			$contact['phone'] = '+' . $phone;
		}

		$contact['email_verified'] = ( $profile['email_verified'] ?? false ) === true;
		$contact['phone_number_verified'] = ( $profile['phone_number_verified'] ?? false ) === true;

		return $contact;
	}

	private function has_contact( array $contact ): bool {
		return is_email( $contact['email'] ?? '' ) && ! empty( $contact['first_name'] ) && ! empty( $contact['last_name'] ) && ! empty( $contact['phone'] );
	}

	private function has_manual_customer( WC_Order $order ): bool {
		$id = (int) $order->get_customer_id( 'edit' );

		return $id && $id !== (int) get_option( WC_Vipps_Recurring_Helper::OPTION_ANONYMOUS_SYSTEM_CUSTOMER_ID )
			&& get_user_by( 'ID', $id ) && $this->has_contact( $order->get_address( 'billing' ) );
	}

	private function retry( WC_Order $order, string $reason = 'waiting' ): void {
		$attempts = (int) $order->get_meta( self::META_ATTEMPTS ) + 1;
		$order->update_meta_data( self::META_ATTEMPTS, $attempts );
		$order->update_meta_data( self::META_NEXT_ATTEMPT, time() + min( 3600, 5 * ( 2 ** min( $attempts, 10 ) ) ) );
		$this->set_state( $order, $attempts >= 10 ? 'needs_attention' : 'waiting', $reason );
	}

	private function set_state( WC_Order $order, string $state, string $reason = '' ): void {
		if ( $reason && ( $order->get_meta( self::META_STATUS ) !== $state || $order->get_meta( self::META_REASON ) !== $reason ) ) {
			$message = $reason === 'stopped_payment'
				? __( 'The recurring agreement has stopped but its initial payment has a capture or refund. Review this purchase manually; do not delete it or create another payment.', 'woo-vipps' )
				: ( $state === 'needs_attention'
				? __( 'Vipps profile completion needs attention. Check the API configuration, assign a customer and complete the billing details, then use the order action to retry recurring completion with saved billing details. Do not create another payment.', 'woo-vipps' )
				: __( 'Waiting for Vipps profile/customer completion. Automatic status checks will retry the existing purchase.', 'woo-vipps' ) );
			$order->add_order_note( $message . ' (' . $reason . ')' );
		}

		$order->update_meta_data( self::META_STATUS, $state );
		$order->update_meta_data( self::META_REASON, $reason );

		if ( $state === 'needs_attention' ) {
			$order->update_meta_data( self::META_NEXT_ATTEMPT, time() + HOUR_IN_SECONDS );
		}

		$order->save();
	}

}
