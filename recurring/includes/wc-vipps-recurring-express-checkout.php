<?php

defined( 'ABSPATH' ) || exit;

/** Shop-side express purchase adapter. No Checkout sessions or ePayment Express requests. */
class WC_Vipps_Recurring_Express_Checkout {
	public const META_ATTEMPT = '_vipps_recurring_express_attempt';
	public const META_BODY = '_vipps_recurring_express_agreement_body';
	public const META_REQUEST = '_vipps_recurring_express_request';
	public const META_SAVED_CART = '_vipps_recurring_saved_cart';
	public const META_PRODUCT_BUTTON = '_vipps_recurring_express_button';
	private const SESSION_ATTEMPT = 'vipps_recurring_express_attempt';
	private const SESSION_BACKUP = 'vipps_recurring_product_cart_backup';
	private static ?self $instance = null;

	public static function get_instance(): self {
		return self::$instance ?? ( self::$instance = new self() );
	}

	private function gateway(): WC_Gateway_Vipps_Recurring {
		return WC_Vipps_Recurring::get_instance()->gateway();
	}

	public function is_enabled(): bool {
		return $this->gateway()->get_option( 'express_enabled' ) === 'yes' && $this->gateway()->is_available();
	}

	public static function register_hooks(): void {
		$self = self::get_instance();

		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
		add_action( 'wc_ajax_vipps_recurring_express_config', [ $self, 'session_config' ] );
		add_action( 'wp_enqueue_scripts', [ $self, 'enqueue_scripts' ], 25 );
		add_action( 'woocommerce_cart_actions', [ $self, 'cart_button' ] );
		add_action( 'woocommerce_widget_shopping_cart_buttons', [ $self, 'minicart_button' ], 30 );
		add_action( 'woocommerce_before_checkout_form', [ $self, 'checkout_button' ], 11 );
		add_action( 'woocommerce_after_add_to_cart_button', [ $self, 'product_button' ] );
		add_action( 'woocommerce_after_shop_loop_item', [ $self, 'catalog_button' ], 20 );
		add_action( 'woocommerce_before_thankyou', [ $self, 'pending_notice' ], 5 );
		add_action( 'wc_vipps_recurring_resume_express_purchase', [ $self, 'resume_purchase' ] );
		add_action( 'woocommerce_checkout_order_created', [ $self, 'bind_saved_cart' ], 30 );
		add_filter( 'wc_subscriptions_object_data', [ $self, 'exclude_purchase_data' ], 20, 3 );
	}

	public function register_routes(): void {
		foreach ( [ '/express-checkout', '/express-checkout/product', '/express-checkout/abort' ] as $route ) {
			register_rest_route( 'vipps-mobilepay-recurring/v1', $route, [
				'methods' => 'POST', 'callback' => [ $this, 'purchase' ],
				'permission_callback' => [ $this, 'authorize' ],
			] );
		}
	}

	/** Purchase identities, consent and raw request data must never become renewal data. */
	public function exclude_purchase_data( array $data, $to_order, $from_order ): array {
		foreach ( array_keys( $data ) as $key ) {
			if ( strpos( $key, '_vipps_recurring_express_' ) === 0 || strpos( $key, '_vipps_recurring_profile_' ) === 0
				|| in_array( $key, [ self::META_SAVED_CART, '_vipps_recurring_terms_accepted_at' ], true ) ) {
				unset( $data[ $key ] );
			}
		}

		if ( $from_order->get_meta( self::META_ATTEMPT ) ) { unset( $data[ WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN ] ); }

		return $data;
	}

	public function authorize( WP_REST_Request $request ) {
		if ( ! WC()->cart || ! WC()->session ) {
			wc_load_cart();
		}

		$nonce = $request->get_header( 'X-Vipps-Recurring-Nonce' );

		if ( ! WC()->session || ! wp_verify_nonce( $nonce, 'vipps_recurring_express_' . WC()->session->get_customer_id() ) ) {
			return new WP_Error( 'vipps_recurring_session', __( 'Reload this page before starting checkout.', 'woo-vipps' ), [ 'status' => 403 ] );
		}

		return true;
	}

	public function enqueue_scripts(): void {
		wp_register_script( 'vipps-recurring-express', WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/js/vipps-recurring-express.js',
			[ 'vipps-gw', 'jquery' ], filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/js/vipps-recurring-express.js' ), true );

		if ( ! $this->gateway()->is_available() || ! WC()->session ) {
			return;
		}

		wp_localize_script( 'vipps-recurring-express', 'VippsRecurringExpress', [
			'sessionUrl' => WC_AJAX::get_endpoint( 'vipps_recurring_express_config' ),
			'checkoutUrl' => wc_get_checkout_url(),
			'error' => __( 'Could not start Express checkout. Check your order before trying again.', 'woo-vipps' ),
			'selectOptions' => __( 'Select the product options before continuing.', 'woo-vipps' ),
		] );

		wp_enqueue_script( 'vipps-recurring-express' );

		// Registering another host or classic controller would duplicate the payment implementation.
		$pay_order = is_checkout_pay_page() ? wc_get_order( absint( get_query_var( 'order-pay' ) ) ) : false;
		if ( ! $pay_order || ! wcs_is_subscription( $pay_order ) ) {
			wp_add_inline_script( 'vipps-classic-checkout', 'window.VippsClassicCheckoutMethods = ["vipps", "vipps_recurring"];', 'before' );
		}
	}

	/** Fetch nonces outside cached page HTML; Woo AJAX retains WordPress cookie authentication. */
	public function session_config(): void {
		nocache_headers();

		if ( ! WC()->session || ! WC()->cart ) { wc_load_cart(); }

		WC()->session->set_customer_session_cookie( true );

		wp_send_json_success( [
			'endpoint' => rest_url( 'vipps-mobilepay-recurring/v1/express-checkout' ),
			'nonce' => wp_create_nonce( 'vipps_recurring_express_' . WC()->session->get_customer_id() ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
		] );
	}

	public function show_cart_button( string $context ): bool {
		$key = $context === 'checkout' ? 'express_checkout_button' : 'express_cart_button';
		$show = $this->gateway()->get_option( $key ) === 'yes';

		return $this->is_enabled() && WC()->cart && WC_Subscriptions_Cart::cart_contains_subscription()
			&& apply_filters( 'woo_vipps_show_express_checkout', $show );
	}

	public function button_html( string $context, int $product_id = 0 ): string {
		if ( ! $this->is_enabled() ) { return ''; }
		if ( ! $product_id && ! $this->show_cart_button( strpos( $context, 'checkout' ) === 0 ? 'checkout' : 'cart' ) ) {
			return '';
		}

		$button = apply_filters( 'woo_vipps_express_checkout_button', Vipps::instance()->get_html_button_for_context( $context ) );
		// Reuse the renderer and appearance options, but use the recurring agreement's brand.
		$markup = new WP_HTML_Tag_Processor( $button );
		while ( $markup->next_tag( 'vipps-mobilepay-button' ) ) {
			$markup->set_attribute( 'brand', $this->gateway()->brand );
		}
		$button = $markup->get_updated_html();
		$classes = [ 'vipps-recurring-express', 'button', 'vipps-buy-now', $this->gateway()->title ];
		$button_args = Vipps::instance()->get_html_button_attrs_for_context( $context );

		if ( ( $button_args['compact'] ?? 'false' ) === 'true' ) {
			$classes[] = 'short';
		}

		// Keep single-product and initialized exclusive to the one-time payment click handler.
		return '<a href="' . esc_url( wc_get_checkout_url() ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '" data-context="' . esc_attr( $context )
			. '" data-product-id="' . esc_attr( $product_id ) . '">' . $button . '</a>';
	}

	public function cart_button(): void {
		echo $this->button_html( 'cart' );
	}

	public function pending_notice( $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( $order && ( ( isset( $_GET['key'] ) && is_string( $_GET['key'] ) && hash_equals( $order->get_order_key(), wp_unslash( $_GET['key'] ) ) )
			|| ( get_current_user_id() && get_current_user_id() === (int) $order->get_customer_id() ) ) ) {
			$this->restore_saved_cart( $order );
		}

		if ( $order && WC_Vipps_Recurring_Profile::needs_completion( $order ) ) {
			wc_print_notice( __( 'Your recurring order is still being processed. You do not need to make another payment. Contact the store if you need help.', 'woo-vipps' ), 'notice' );
		}
	}

	/** Reuse the payment plugin's generic cart persistence, including normal-checkout fallback. */
	public function bind_saved_cart( WC_Order $order ): void {
		$backup = WC()->session ? WC()->session->get( self::SESSION_BACKUP ) : null;
		if ( ! is_array( $backup ) || ! WC()->cart || ! $order->has_cart_hash( WC()->cart->get_cart_hash() ) ) { return; }

		$contents = unserialize( base64_decode( $backup['contents'] ), [ 'allowed_classes' => true ] );
		if ( ! is_array( $contents ) ) { return; }

		$cart = clone WC()->cart;
		$cart->set_cart_contents( $contents );
		Vipps::instance()->save_cart( $order, $cart );

		$coupons = WC()->session->get( 'vipps_recurring_saved_coupons', [] );
		$coupons[ $order->get_id() ] = $backup['coupons'];
		WC()->session->set( 'vipps_recurring_saved_coupons', $coupons );

		WC()->session->set( self::SESSION_BACKUP, null );

		$order->update_meta_data( self::META_SAVED_CART, true );
		$order->save();

		$saved = WC()->session->get( self::SESSION_ATTEMPT, [] );
		$saved['backup_order_id'] = $order->get_id();

		WC()->session->set( self::SESSION_ATTEMPT, $saved );

		set_transient( $this->state_key(), $saved, 8 * DAY_IN_SECONDS );
	}

	public function restore_saved_cart( WC_Order $order ): void {
		if ( ! $order->get_meta( self::META_SAVED_CART ) || ! WC()->session || ! WC()->cart ) { return; }

		$carts = WC()->session->get( '_vipps_carts', [] );

		if ( empty( $carts[ $order->get_id() ] ) ) { return; }

		WC()->cart->empty_cart( false );
		Vipps::instance()->restore_cart( $order );

		$coupons = WC()->session->get( 'vipps_recurring_saved_coupons', [] );
		foreach ( $coupons[ $order->get_id() ] ?? [] as $coupon ) { WC()->cart->apply_coupon( $coupon ); }
		unset( $coupons[ $order->get_id() ] );

		WC()->session->set( 'vipps_recurring_saved_coupons', $coupons );
		WC()->cart->calculate_totals();
		WC()->session->save_data();
	}

	public function minicart_button(): void {
		echo $this->button_html( 'minicart' );
	}

	public function checkout_button(): void {
		echo $this->button_html( 'checkout' );
	}

	private function product_enabled( $product ): bool {
		if ( ! $product || ! WC_Subscriptions_Product::is_subscription( $product ) || ! $product->is_purchasable()
			|| ! $product->is_in_stock() || ! $this->is_enabled() ) {
			return false;
		}

		$how = $this->gateway()->get_option( 'express_product_button' );
		$show = $how === 'all' || ( $how === 'some' && $product->get_meta( self::META_PRODUCT_BUTTON ) === 'yes' );

		return apply_filters( 'woo_vipps_show_single_product_buy_now', $show, $product );
	}

	public function product_button(): void {
		global $product;

		if ( $this->product_enabled( $product ) ) {
			echo $this->button_html( 'product', $product->get_id() );
		}
	}

	public function catalog_button(): void {
		global $product;

		if ( $this->gateway()->get_option( 'express_archive_button' ) === 'yes'
			&& $this->product_enabled( $product ) && $product->is_type( 'subscription' )
			&& apply_filters( 'woo_vipps_show_single_product_buy_now_in_loop', true, $product ) ) {
			echo $this->button_html( 'catalog', $product->get_id() );
		}
	}

	/** Require WooCommerce-selected locations and identical supported shipping for renewals. */
	private function eligibility(): bool {
		$cart = WC()->cart;
		if ( ! $this->gateway()->is_available() || $cart->is_empty() || ! WC_Subscriptions_Cart::cart_contains_subscription()
			|| wcs_cart_contains_switches() || wcs_cart_contains_failed_renewal_order_payment() !== false ) {
			return false;
		}

		$count = 0;
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['subscription_renewal'] ) || ! empty( $item['subscription_resubscribe'] ) ) {
				return false;
			}
			if ( WC_Subscriptions_Product::is_subscription( $item['data'] ) ) { ++$count; }
		}

		if ( $count !== 1 ) { return false; }

		// Custom required checkout fields need the normal checkout's validation/UI.
		$profile_fields = [ 'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone' ];
		$location_fields = [ 'address_1', 'address_2', 'city', 'country', 'state', 'postcode' ];

		foreach ( WC()->checkout()->get_checkout_fields() as $fields ) {
			foreach ( $fields as $name => $field ) {
				if ( empty( $field['required'] ) || in_array( $name, $profile_fields, true )
					|| preg_match( '/^(billing|shipping)_(' . implode( '|', $location_fields ) . '|first_name|last_name)$/', $name ) ) { continue; }
				if ( ! WC()->checkout()->get_value( $name ) && $name !== 'account_password' && $name !== 'account_username' ) { return false; }
			}
		}

		$customer = WC()->customer;
		if ( wc_tax_enabled() && get_option( 'woocommerce_tax_based_on' ) !== 'base' ) {
			$prefix = get_option( 'woocommerce_tax_based_on' ) === 'shipping' ? 'shipping' : 'billing';
			if ( ! $this->has_location( $prefix === 'shipping' ? $customer->get_shipping() : $customer->get_billing() ) ) { return false; }
		}

		if ( $cart->needs_shipping() ) {
			if ( ! $this->has_location( $customer->get_shipping() ) ) { return false; }

			$chosen = WC()->session->get( 'chosen_shipping_methods', [] );
			$packages = WC()->shipping()->get_packages();

			if ( ! $packages ) { return false; }

			foreach ( $packages as $key => $package ) {
				if ( ! isset( $package['rates'][ $chosen[ $key ] ?? '' ] ) ) { return false; }
			}

			foreach ( $cart->recurring_carts ?? [] as $recurring_cart ) {
				if ( $recurring_cart->needs_shipping() && ( (string) $recurring_cart->get_shipping_total() !== (string) $cart->get_shipping_total()
					|| (string) $recurring_cart->get_shipping_tax() !== (string) $cart->get_shipping_tax() ) ) { return false; }
			}
		}

		return true;
	}

	private function has_location( array $address ): bool {
		foreach ( [ 'country', 'address_1', 'city' ] as $field ) {
			if ( empty( $address[ $field ] ) ) { return false; }
		}

		$country = $address['country'];
		foreach ( WC()->countries->get_address_fields( $country, '' ) as $field => $definition ) {
			if ( ! empty( $definition['required'] ) && in_array( $field, [ 'postcode', 'state' ], true ) && empty( $address[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	/** A session lock covers cart changes, order creation, and the uncertain API request. */
	public function purchase( WP_REST_Request $request ) {
		nocache_headers();
		$args = $request->get_json_params();

		if ( ! is_array( $args ) || ! is_string( $args['attempt'] ?? null ) || ! preg_match( '/^[a-zA-Z0-9-]{16,64}$/', $args['attempt'] ) ) {
			return new WP_Error( 'vipps_recurring_attempt', __( 'Invalid checkout attempt. Reload this page.', 'woo-vipps' ), [ 'status' => 400 ] );
		}

		$lock_key = '_vipps_recurring_express_lock_' . hash( 'sha256', WC()->session->get_customer_id() );
		$lock = WC_Vipps_Recurring_Helper::acquire_purchase_lock( $lock_key, 180 );
		if ( ! $lock ) {
			return new WP_Error( 'vipps_recurring_busy', __( 'A checkout request is already in progress. Please wait.', 'woo-vipps' ), [ 'status' => 409 ] );
		}

		try {
			return $this->start_purchase( $request, $args );
		} catch ( Exception $error ) {
			return new WP_Error( 'vipps_recurring_start', __( 'Could not start Express checkout. Reload this page to check the existing purchase before trying again.', 'woo-vipps' ), [ 'status' => 503 ] );
		} finally {
			// Also store outside the request's session snapshot, for concurrent requests loaded before the lock.
			set_transient( $this->state_key(), WC()->session->get( self::SESSION_ATTEMPT, [] ), 8 * DAY_IN_SECONDS );
			WC()->session->save_data();
			WC_Vipps_Recurring_Helper::release_purchase_lock( $lock_key, $lock );
		}
	}

	private function state_key(): string {
		return 'vipps_recurring_express_state_' . hash( 'sha256', WC()->session->get_customer_id() );
	}

	private function start_purchase( WP_REST_Request $request, array $args ) {
		$saved = get_transient( $this->state_key() ) ?: WC()->session->get( self::SESSION_ATTEMPT, [] );
		WC()->session->set( self::SESSION_ATTEMPT, $saved );

		if ( ! empty( $saved['auth_token'] ) ) {
			WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ORDER_EXPRESS_AUTH_TOKEN, $saved['auth_token'] );
		}

		$order = ! empty( $saved['order_id'] ) ? wc_get_order( $saved['order_id'] ) : false;
		if ( substr( $request->get_route(), -6 ) === '/abort' && ( $saved['attempt'] ?? '' ) === $args['attempt'] && ! $order ) {
			$backup = WC()->session->get( self::SESSION_BACKUP );

			if ( is_array( $backup ) ) {
				$contents = unserialize( base64_decode( $backup['contents'] ), [ 'allowed_classes' => true ] );

				if ( is_array( $contents ) ) {
					WC()->cart->set_cart_contents( $contents );
					WC()->cart->set_applied_coupons( $backup['coupons'] );
					WC()->cart->calculate_totals();
				}
			}

			WC()->session->set( self::SESSION_BACKUP, null );
			WC()->session->set( self::SESSION_ATTEMPT, [] );

			return [ 'cancelled' => true ];
		}

		if ( substr( $request->get_route(), -6 ) === '/abort' ) {
			return [ 'redirect' => $order ? WC_Vipps_Recurring_Helper::get_payment_redirect_url( $order ) : wc_get_checkout_url() ];
		}

		$previous_order = false;
		if ( ! $order && ! empty( $saved['backup_order_id'] ) ) {
			$fallback_order = wc_get_order( $saved['backup_order_id'] );
			if ( $fallback_order && ! $fallback_order->is_paid() && ! $fallback_order->has_status( 'cancelled' ) ) {
				return [ 'redirect' => $fallback_order->get_checkout_payment_url() ];
			}
		}

		if ( $order && $order->get_meta( self::META_ATTEMPT ) === ( $saved['attempt'] ?? '' ) ) {
			// Never modify an outstanding order, even if the current cart has changed.
			$agreement_id = $order->get_meta( WC_Vipps_Recurring_Helper::META_AGREEMENT_ID );

			if ( $agreement_id ) {
				$agreement = $this->gateway()->api->get_agreement( $agreement_id );
				if ( $agreement->status === WC_Vipps_Agreement::STATUS_ACTIVE ) {
					if ( WC_Vipps_Recurring_Profile::needs_completion( $order ) || ( $saved['attempt'] ?? '' ) === $args['attempt'] ) {
						return [ 'redirect' => WC_Vipps_Recurring_Helper::get_payment_redirect_url( $order ) ];
					}

					$previous_order = $order;
					$order = false;

				} elseif ( $agreement->status === WC_Vipps_Agreement::STATUS_PENDING ) {
					return [ 'paymentUrl' => $agreement->vipps_confirmation_url, 'returnUrl' => WC_Vipps_Recurring_Helper::get_payment_redirect_url( $order ) ];
				} else {
					$this->gateway()->check_charge_status( $order->get_id(), true );
					$order = wc_get_order( $order->get_id() );

					if ( WC_Vipps_Recurring_Profile::needs_completion( $order ) || ( $saved['attempt'] ?? '' ) === $args['attempt'] ) {
						return [ 'redirect' => WC_Vipps_Recurring_Helper::get_payment_redirect_url( $order ) ];
					}

					$order = false;
				}
			} elseif ( ! empty( $order->get_meta( self::META_BODY ) ) ) {
				// Retry the frozen request before accepting any cart/product changes.
				return $this->process_order( $order );
			} elseif ( ( $saved['attempt'] ?? '' ) !== $args['attempt'] ) {
				return new WP_Error( 'vipps_recurring_resume', __( 'Reload this page to resume the existing purchase.', 'woo-vipps' ), [ 'status' => 409 ] );
			}
		}

		if ( ! $order ) {
			if ( ! $this->is_enabled() ) {
				return new WP_Error( 'vipps_recurring_express_disabled', __( 'Express checkout for subscriptions is disabled. Please use the checkout page.', 'woo-vipps' ), [ 'status' => 403 ] );
			}

			$context = $args['context'] ?? 'cart';
			$product_request = substr( $request->get_route(), -8 ) === '/product';

			if ( $product_request ) {
				$product_hash = hash( 'sha256', wp_json_encode( [ $args['product_id'] ?? 0, $args['variation_id'] ?? 0, $args['quantity'] ?? 1, $args['variation'] ?? [] ] ) );

				if ( ( $saved['attempt'] ?? '' ) === $args['attempt'] && ! empty( $saved['product_hash'] ) ) {
					if ( $saved['product_hash'] !== $product_hash ) { throw new Exception( 'Product changed during confirmation' ); }
				} else {
					$result = $this->add_product( $args );
					if ( is_wp_error( $result ) ) { return $result; }
					WC()->session->set( self::SESSION_ATTEMPT, [ 'attempt' => $args['attempt'], 'product_hash' => $product_hash ] );
				}
			} elseif ( ! is_string( $context ) || ! $this->show_cart_button( strpos( $context, 'checkout' ) === 0 ? 'checkout' : 'cart' ) ) {
				return [ 'redirect' => wc_get_checkout_url() ];
			}

			WC()->cart->calculate_totals();

			foreach ( [ WC()->cart->check_cart_item_validity(), WC()->cart->check_cart_item_stock() ] as $valid ) {
				if ( is_wp_error( $valid ) ) { return $valid; }
			}

			if ( ! $this->eligibility() ) {
				return [ 'redirect' => wc_get_checkout_url() ];
			}

			$terms = Vipps::instance()->get_express_checkout_terms_html( $this->gateway()->get_option( 'express_terms' ) === 'yes' );
			if ( $previous_order && $previous_order->has_cart_hash( WC()->cart->get_cart_hash() )
				&& time() - $previous_order->get_date_created()->getTimestamp() <= apply_filters( 'woo_vipps_recent_order_cutoff', 3 * MINUTE_IN_SECONDS ) ) {
				$terms = '<p>' . esc_html__( 'You recently completed an order with these products. Confirm that you want to buy them again.', 'woo-vipps' ) . '</p>' . ( $terms ?? '' );
			}

			$post = is_array( $args['post'] ?? null ) ? wc_clean( $args['post'] ) : [];

			ob_start();
			do_action( 'woo_vipps_express_checkout_orderspec_form', null, $args );

			$extra = ob_get_clean();
			$terms_required = $terms !== null && preg_match( '/<input\b[^>]*\bname\s*=\s*([\'\"])terms\1/i', $terms );

			if ( ( $terms !== null || $extra ) && empty( $post['confirmed'] ) ) {
				return [ 'confirmationHtml' => ( $terms ?? '' ) . $extra ];
			}

			if ( $terms_required && ( $post['terms'] ?? '' ) !== 'on' && ( $post['terms'] ?? '' ) !== '1' ) {
				return new WP_Error( 'vipps_recurring_terms', __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce' ), [ 'status' => 400 ] );
			}

			$errors = new WP_Error();
			do_action( 'wc_vipps_recurring_express_checkout_validation', $post, $errors );
			if ( $errors->has_errors() ) { return $errors; }

			// The generic preparation method must not resume/mutate a previous awaiting order.
			WC()->session->set( 'order_awaiting_payment', null );
			$order = wc_get_order( $this->gateway()->create_partial_order( false ) );

			if ( ! $order || $order->get_meta( WC_Vipps_Recurring_Helper::META_AGREEMENT_ID )
				|| (int) $order->get_customer_id() !== get_current_user_id() ) {
				throw new Exception( 'Unexpected partial order' );
			}

			$order->update_meta_data( self::META_ATTEMPT, $args['attempt'] );
			$order->update_meta_data( '_vipps_recurring_terms_accepted_at', $terms_required ? time() : 0 );
			$order->update_meta_data( WC_Vipps_Recurring_Profile::META_REQUIRED, true );
			$order->set_address( WC()->customer->get_billing(), 'billing' );
			$order->set_address( WC()->customer->get_shipping(), 'shipping' );
			$token = wp_generate_password( 64, false );
			$order->update_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN, $token );
			$order->save();
			WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ORDER_EXPRESS_AUTH_TOKEN, $token );
			WC()->session->set( self::SESSION_ATTEMPT, [ 'attempt' => $args['attempt'], 'order_id' => $order->get_id(), 'auth_token' => $token ] );
			WC()->session->set( 'order_awaiting_payment', $order->get_id() );
			WC()->session->save_data();

			do_action( 'wc_vipps_recurring_express_checkout_order_created', $order, $post );
		}

		return $this->process_order( $order );
	}

	private function process_order( WC_Order $order ) {
		$lock_key = '_vipps_recurring_express_order_lock_' . $order->get_id();
		$lock = WC_Vipps_Recurring_Helper::acquire_purchase_lock( $lock_key, 180 );

		if ( ! $lock ) { throw new Exception( 'Purchase is already being processed' ); }

		try {
			$order = wc_get_order( $order->get_id() );
			$request_time = (int) $order->get_meta( '_vipps_recurring_express_request_time' );

			if ( $order->has_status( 'cancelled' ) ) { throw new Exception( 'Cancelled purchases require reconciliation' ); }
			if ( ! $order->get_meta( WC_Vipps_Recurring_Helper::META_AGREEMENT_ID ) && $request_time && time() - $request_time > 12 * HOUR_IN_SECONDS ) {
				throw new Exception( 'Uncertain request needs manual reconciliation before replay' );
			}

			return $this->process_prepared_order( $order );
		} finally {
			WC_Vipps_Recurring_Helper::release_purchase_lock( $lock_key, $lock );
		}
	}

	public function resume_purchase( $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_meta( self::META_ATTEMPT ) || $order->get_payment_method() !== $this->gateway()->id ) { return; }
		if ( $order->get_meta( WC_Vipps_Recurring_Helper::META_AGREEMENT_ID ) ) {
			$this->gateway()->check_charge_status( $order_id );
			return;
		}

		try {
			$this->process_order( $order );
			$this->gateway()->check_charge_status( $order_id );
		} catch ( Exception $error ) {
			$order = wc_get_order( $order_id );

			$attempts = (int) $order->get_meta( '_vipps_recurring_express_recovery_attempts' ) + 1;
			$order->update_meta_data( '_vipps_recurring_express_recovery_attempts', $attempts );

			$retryable = ! ( $error instanceof WC_Vipps_Recurring_Exception ) || $error instanceof WC_Vipps_Recurring_Temporary_Exception;
			if ( $retryable && ! $order->has_status( 'cancelled' ) && $attempts < 10 && time() - (int) $order->get_meta( '_vipps_recurring_express_request_time' ) <= 12 * HOUR_IN_SECONDS ) {
				wp_clear_scheduled_hook( 'wc_vipps_recurring_resume_express_purchase', [ $order_id ] );
				wp_schedule_single_event( time() + min( HOUR_IN_SECONDS, 60 * ( 2 ** $attempts ) ), 'wc_vipps_recurring_resume_express_purchase', [ $order_id ] );
			} else {
				wp_clear_scheduled_hook( 'wc_vipps_recurring_resume_express_purchase', [ $order_id ] );
				$order->add_order_note( __( 'Recurring agreement creation could not be confirmed. Check the API configuration and reconcile this purchase with Vipps MobilePay before creating another agreement or payment.', 'woo-vipps' ) );
				$order->update_meta_data( WC_Vipps_Recurring_Profile::META_STATUS, 'needs_attention' );
				$order->update_meta_data( WC_Vipps_Recurring_Profile::META_REASON, 'agreement_creation' );
			}

			$order->save();
		}
	}

	private function process_prepared_order( WC_Order $order ) {
		$subscriptions = ( new WC_Vipps_Recurring_Purchase( $this->gateway() ) )->prepare_subscriptions( $order );

		if ( count( $subscriptions ) !== 1 ) { throw new Exception( 'Unsupported subscription groups' ); }

		if ( ! $order->get_meta( self::META_REQUEST ) && function_exists( 'wc_reserve_stock_for_order' ) ) {
			wc_reserve_stock_for_order( $order );
		}

		if ( ! wp_next_scheduled( 'wc_vipps_recurring_resume_express_purchase', [ $order->get_id() ] ) ) {
			wp_schedule_single_event( time() + 60, 'wc_vipps_recurring_resume_express_purchase', [ $order->get_id() ] );
		}

		$result = $this->gateway()->process_payment( $order->get_id() );
		if ( ( $result['result'] ?? '' ) !== 'success' || empty( $result['redirect'] ) ) {
			throw new Exception( 'Agreement creation did not return a URL' );
		}

		return [ 'paymentUrl' => $result['redirect'], 'returnUrl' => WC_Vipps_Recurring_Helper::get_payment_redirect_url( $order ) ];
	}

	private function add_product( array $args ) {
		if ( ! is_numeric( $args['product_id'] ?? null ) || ! is_numeric( $args['quantity'] ?? 1 )
			|| ! is_numeric( $args['variation_id'] ?? 0 )
			|| ( isset( $args['variation'] ) && ( ! is_array( $args['variation'] ) || count( array_filter( $args['variation'], 'is_scalar' ) ) !== count( $args['variation'] ) ) ) ) {
			return new WP_Error( 'vipps_recurring_product_request', __( 'Invalid product options or quantity.', 'woo-vipps' ), [ 'status' => 400 ] );
		}

		$product = wc_get_product( absint( $args['product_id'] ?? 0 ) );
		if ( ! $this->product_enabled( $product ) ) {
			return new WP_Error( 'vipps_recurring_product', __( 'This product is not available for Express checkout.', 'woo-vipps' ), [ 'status' => 400 ] );
		}

		$variation_id = absint( $args['variation_id'] ?? 0 );
		$attributes = is_array( $args['variation'] ?? null ) ? wc_clean( $args['variation'] ) : [];

		if ( $product->is_type( 'variable-subscription' ) ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || $variation->get_parent_id() !== $product->get_id()
				|| WC_Data_Store::load( 'product' )->find_matching_product_variation( $product, $attributes ) !== $variation_id ) {
				return new WP_Error( 'vipps_recurring_variation', __( 'Select the product options before continuing.', 'woo-vipps' ), [ 'status' => 400 ] );
			}
		} elseif ( $variation_id || ! $product->is_type( 'subscription' ) ) {
			return new WP_Error( 'vipps_recurring_product_type', __( 'Use shop checkout for this product.', 'woo-vipps' ), [ 'status' => 400 ] );
		}

		$quantity = wc_stock_amount( $args['quantity'] ?? 1 );
		if ( $quantity <= 0 || ! apply_filters( 'woocommerce_add_to_cart_validation', true, $product->get_id(), $quantity, $variation_id, $attributes ) ) {
			return new WP_Error( 'vipps_recurring_quantity', __( 'Could not add this product to the cart. Check its options and quantity.', 'woo-vipps' ), [ 'status' => 400 ] );
		}

		$compat = apply_filters( 'woo_vipps_single_product_compat_mode', $this->gateway()->get_option( 'express_include_cart' ) === 'yes', $product );
		$contents = WC()->cart->get_cart_contents();
		$coupons = WC()->cart->get_applied_coupons();

		if ( ! $compat ) { WC()->cart->empty_cart( false ); }
		if ( ! WC()->cart->add_to_cart( $product->get_id(), $quantity, $variation_id, $attributes ) ) {
			WC()->cart->set_cart_contents( $contents );
			WC()->cart->set_applied_coupons( $coupons );
			WC()->cart->calculate_totals();
			return new WP_Error( 'vipps_recurring_add_product', __( 'Could not add this product to the cart.', 'woo-vipps' ), [ 'status' => 400 ] );
		}

		if ( ! $compat ) {
			WC()->session->set( self::SESSION_BACKUP, [ 'contents' => base64_encode( serialize( $contents ) ), 'coupons' => $coupons ] );
		}

		WC()->cart->set_session();
		return true;
	}
}
