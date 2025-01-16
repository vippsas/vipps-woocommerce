<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Checkout {
    private static ?WC_Vipps_Recurring_Checkout $instance = null;

    private ?WC_Gateway_Vipps_Recurring $gateway = null;

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

    public static function register_hooks(): void {
        $instance = WC_Vipps_Recurring_Checkout::get_instance();
        add_action( 'init', [ $instance, 'init' ] );
        // Higher priority than the single payments Vipps plugin
        add_filter( 'woocommerce_get_checkout_page_id', [ $instance, 'woocommerce_get_checkout_page_id' ], 20 );
        add_action( 'template_redirect', [ $instance, 'template_redirect' ] );

        if ( is_admin() ) {
            add_action( 'admin_init', [ $instance, 'admin_init' ] );
        }
    }

    public function gateway(): ?WC_Gateway_Vipps_Recurring {
        if ( $this->gateway ) {
            return $this->gateway;
        }

        $this->gateway = WC_Vipps_Recurring::get_instance()->gateway();

        return $this->gateway;
    }

    public function maybe_load_cart(): void {
        if ( version_compare( WC_VERSION, '3.6.0', '>=' ) && WC()->is_rest_api_request() ) {
            if ( empty( $_SERVER['REQUEST_URI'] ) ) {
                return;
            }

            $rest_prefix = WC_Vipps_Recurring_Checkout_Rest_Api::$api_namespace;
            $req_uri     = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

            $is_my_endpoint = ( false !== strpos( $req_uri, $rest_prefix ) );

            if ( ! $is_my_endpoint ) {
                return;
            }

            require_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            require_once WC_ABSPATH . 'includes/wc-notice-functions.php';

            if ( null === WC()->session ) {
                $session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

                // Prefix session class with global namespace if not already namespaced
                if ( false === strpos( $session_class, '\\' ) ) {
                    $session_class = '\\' . $session_class;
                }

                WC()->session = new $session_class();
                WC()->session->init();
            }

            /**
             * For logged in customers, pull data from their account rather than the
             * session which may contain incomplete data.
             */
            if ( is_null( WC()->customer ) ) {
                if ( is_user_logged_in() ) {
                    WC()->customer = new WC_Customer( get_current_user_id() );
                } else {
                    WC()->customer = new WC_Customer( get_current_user_id(), true );
                }

                // Customer should be saved during shutdown.
                add_action( 'shutdown', array( WC()->customer, 'save' ), 10 );
            }

            // Load Cart.
            if ( null === WC()->cart ) {
                WC()->cart = new WC_Cart();
            }
        }
    }

    public function init(): void {
        require_once __DIR__ . '/wc-vipps-recurring-checkout-rest-api.php';
        WC_Vipps_Recurring_Checkout_Rest_Api::get_instance();

        add_action( 'wp_loaded', [ $this, 'maybe_load_cart' ], 5 );

        add_action( 'wp_loaded', [ $this, 'register_scripts' ] );

        // Prevent previews and prefetches of the Vipps Checkout page starting and creating orders
        add_action( 'wp_head', [ $this, 'wp_head' ] );

        // The Vipps MobilePay Checkout feature which overrides the normal checkout process uses a shortcode
        add_shortcode( 'vipps_recurring_checkout', [ $this, 'shortcode' ] );

        add_action( 'wc_vipps_recurring_before_cron_check_order_status', [ $this, 'check_order_status' ] );
        add_action( 'wc_vipps_recurring_before_rest_api_check_order_status', [ $this, 'check_order_status' ] );

        // For Checkout, we need to know any time and as soon as the cart changes, so fold all the events into a single one
        add_action( 'woocommerce_add_to_cart', function () {
            do_action( 'vipps_recurring_cart_changed', 'woocommerce_add_to_cart' );
        }, 10, 0 );

        // Cart coupon applied
        add_action( 'woocommerce_applied_coupon', function () {
            do_action( 'vipps_recurring_cart_changed', 'woocommerce_applied_coupon' );
        }, 10, 0 );

        // Cart emptied
        add_action( 'woocommerce_cart_emptied', function () {
            do_action( 'vipps_recurring_cart_changed', 'woocommerce_cart_emptied' );
        }, 10, 0 );

        // After updating quantities
        add_action( 'woocommerce_after_cart_item_quantity_update', function () {
            do_action( 'vipps_recurring_cart_changed', 'woocommerce_after_cart_item_quantity_update' );
        }, 10, 0 );

        // Blocks and ajax
        add_action( 'woocommerce_cart_item_removed', function () {
            do_action( 'vipps_recurring_cart_changed', 'woocommerce_cart_item_removed' );
        }, 10, 0 );

        // Restore deleted entry
        add_action( 'woocommerce_cart_item_restored', function () {
            do_action( 'vipps_recurring_cart_changed', 'woocommerce_cart_item_restored' );
        }, 10, 0 );

        // Normal cart form update
        add_filter( 'woocommerce_update_cart_action_cart_updated', function ( $updated ) {
            do_action( 'vipps_recurring_cart_changed', 'woocommerce_update_cart_action_cart_updated' );

            return $updated;
        } );

        // Then handle the actual cart change
        add_action( 'vipps_recurring_cart_changed', [ $this, 'cart_changed' ] );

        add_action( 'wc_vipps_recurring_checkout_callback', [ $this, 'handle_callback' ], 10, 2 );

        // Handle cancelled orders
        add_action( 'wc_vipps_recurring_check_charge_status_no_agreement', [ $this, 'maybe_cancel_initial_order' ] );

        add_filter( 'wcs_user_has_subscription', [ $this, 'user_has_subscription' ], 10, 4 );
    }

    public function admin_init(): void {
        // Checkout page
        add_filter( 'woocommerce_settings_pages', array( $this, 'woocommerce_settings_pages' ) );
    }

    /**
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     * @throws WC_Vipps_Recurring_Config_Exception
     */
    public function maybe_create_session(): array {
        $redirect_url = null;
        $token        = null;
        $url          = null;

        $session = WC_Vipps_Recurring_Checkout::get_instance()->current_pending_session();

        if ( isset( $session['redirect_url'] ) ) {
            $redirect_url = $session['redirect_url'];
        }

        if ( isset( $session['session']['token'] ) ) {
            $token = $session['session']['token'];
            $src   = $session['session']['checkoutFrontendUrl'];
            $url   = $src;
        }

        if ( $url ) {
            $pending_order_id = WC_Vipps_Recurring_Helper::get_checkout_pending_order_id();

            return [
                'success'      => true,
                'src'          => $url,
                'redirect_url' => $redirect_url,
                'token'        => $token,
                'order_id'     => $pending_order_id
            ];
        }

        $session = null;

        try {
            $partial_order_id = WC_Gateway_Vipps_Recurring::get_instance()->create_partial_order( true );

            $order      = wc_get_order( $partial_order_id );
            $auth_token = WC_Gateway_Vipps_Recurring::get_instance()->api->generate_idempotency_key();

            $order->update_meta_data( WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN, $auth_token );
            $order->save();

            WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_CHECKOUT_PENDING_ORDER_ID, $partial_order_id );
            WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ORDER_EXPRESS_AUTH_TOKEN, $auth_token );

            do_action( 'wc_vipps_recurring_checkout_order_created', $order );
        } catch ( Exception $exception ) {
            return [
                'success'      => false,
                'msg'          => $exception->getMessage(),
                'src'          => null,
                'redirect_url' => null,
                'order_id'     => 0
            ];
        }

        $order = wc_get_order( $partial_order_id );

        $session_orders = WC()->session->get( WC_Vipps_Recurring_Helper::SESSION_ORDERS );
        if ( ! $session_orders ) {
            $session_orders = [];
        }

        $session_orders[ $partial_order_id ] = 1;
        WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_PENDING_ORDER_ID, $partial_order_id );
        WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ORDERS, $session_orders );

        $customer_id = get_current_user_id();
        if ( $customer_id ) {
            $customer = new WC_Customer( $customer_id );
        } else {
            $customer = WC()->customer;
        }

        if ( $customer ) {
            $customer_info['email']         = $customer->get_billing_email();
            $customer_info['firstName']     = $customer->get_billing_first_name();
            $customer_info['lastName']      = $customer->get_billing_last_name();
            $customer_info['streetAddress'] = $customer->get_billing_address_1();
            $address2                       = trim( $customer->get_billing_address_2() );

            if ( ! empty( $address2 ) ) {
                $customer_info['streetAddress'] = $customer_info['streetAddress'] . ", " . $address2;
            }
            $customer_info['city']       = $customer->get_billing_city();
            $customer_info['postalCode'] = $customer->get_billing_postcode();
            $customer_info['country']    = $customer->get_billing_country();

            // Currently Vipps requires all phone numbers to have area codes and NO +. We can't guarantee that at all, but try for Norway
            $normalized_phone_number = WC_Vipps_Recurring_Helper::normalize_phone_number( $customer->get_billing_phone(), $customer_info['country'] );
            if ( $normalized_phone_number ) {
                $customer_info['phoneNumber'] = $normalized_phone_number;
            }
        }

        $keys = [ 'firstName', 'lastName', 'streetAddress', 'postalCode', 'country', 'phoneNumber' ];
        foreach ( $keys as $k ) {
            if ( empty( $customer_info[ $k ] ) ) {
                $customer_info = [];
                break;
            }
        }
        $customer_info = apply_filters( 'wc_vipps_recurring_customer_info', $customer_info, $order );

        // todo: throw an error if we try to purchase a product with a location based shipping method?
        try {
            $checkout = WC_Vipps_Recurring_Checkout::get_instance();
            $gateway  = $checkout->gateway();

            $order_prefix = $gateway->order_prefix;

            // hack - fake "Anonymous Vipps/MobilePay User"
            $fake_user = false;
            if ( ! $order->get_customer_id( 'edit' ) ) {
                $fake_user = true;

                $order->set_customer_id( $this->gateway()->create_or_get_anonymous_system_customer()->get_id() );
                $order->save();
            }

            $subscriptions = $gateway->create_partial_subscriptions_from_order( $order );

            // reset hack
            if ( $fake_user ) {
                $order->set_customer_id( 0 );
                $order->save();
            }

            $subscription = array_pop( $subscriptions );

            // Remove this action to avoid making an unnecessary API request
            remove_action( 'woocommerce_order_after_calculate_totals', [
                $this->gateway(),
                'update_agreement_price_in_app'
            ] );

            $subscription->calculate_totals();
            $subscription->save();

            $agreement = $gateway->create_vipps_agreement_from_order( $order, $subscription );

            $checkout_subscription = ( new WC_Vipps_Checkout_Session_Subscription() )
                ->set_amount(
                    ( new WC_Vipps_Checkout_Session_Amount() )
                        ->set_value( $agreement->pricing->amount )
                        ->set_currency( $agreement->pricing->currency )
                )
                ->set_product_name( $agreement->product_name )
                ->set_interval( $agreement->interval )
                ->set_merchant_agreement_url( $agreement->merchant_agreement_url );

            if ( $agreement->campaign ) {
                $checkout_subscription = $checkout_subscription->set_campaign( $agreement->campaign );
            }

            if ( $agreement->product_description ) {
                $checkout_subscription = $checkout_subscription->set_product_description( $agreement->product_description );
            }

            $customer = new WC_Vipps_Checkout_Session_Customer( $customer_info );

            // Create a checkout session dto
            $checkout_session = ( new WC_Vipps_Checkout_Session() )
                ->set_type( WC_Vipps_Checkout_Session::TYPE_SUBSCRIPTION )
                ->set_subscription( $checkout_subscription )
                ->set_merchant_info(
                    ( new WC_Vipps_Checkout_Session_Merchant_Info() )
                        ->set_callback_url( $gateway->webhook_callback_url() )
                        ->set_return_url( $agreement->merchant_redirect_url )
                        ->set_callback_authorization_token( $auth_token )
                )
                ->set_prefill_customer( $customer )
                ->set_configuration(
                    ( new WC_Vipps_Checkout_Session_Configuration() )
                        ->set_user_flow( WC_Vipps_Checkout_Session_Configuration::USER_FLOW_WEB_REDIRECT )
                        ->set_customer_interaction( WC_Vipps_Checkout_Session_Configuration::CUSTOMER_INTERACTION_NOT_PRESENT )
                        ->set_elements( WC_Vipps_Checkout_Session_Configuration::ELEMENTS_FULL )
                        ->set_require_user_info( empty( $customer->email ) )
                        ->set_show_order_summary( true )
                );

            if ( $agreement->initial_charge ) {
                $reference = WC_Vipps_Recurring_Helper::generate_vipps_order_id( $order, $order_prefix );

                $checkout_transaction = ( new WC_Vipps_Checkout_Session_Transaction() )
                    ->set_reference( $reference )
                    ->set_amount(
                        ( new WC_Vipps_Checkout_Session_Amount() )
                            ->set_value( $agreement->initial_charge->amount )
                            ->set_currency( $agreement->pricing->currency )
                    )
                    ->set_order_summary( $checkout->make_order_summary( $order ) );

                if ( $agreement->initial_charge->description ) {
                    $checkout_transaction = $checkout_transaction->set_payment_description( $agreement->initial_charge->description );
                }

                $checkout_session = $checkout_session->set_transaction( $checkout_transaction );

                WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID, $reference );
            }

            $checkout_session = apply_filters( 'wc_vipps_recurring_checkout_session', $checkout_session, $order );

            $session = WC_Gateway_Vipps_Recurring::get_instance()->api->checkout_initiate( $checkout_session );

            $order = wc_get_order( $partial_order_id );
            WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, true );
            WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_INITIAL, true );
            WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION, $session->to_array() );

            $session_poll = WC_Gateway_Vipps_Recurring::get_instance()->api->checkout_poll( $session->polling_url );
            WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION_ID, $session_poll['sessionId'] );

            $order->add_order_note( __( 'Vipps/MobilePay recurring checkout payment initiated', 'woo-vipps' ) );
            $order->add_order_note( __( 'Customer passed to Vipps/MobilePay checkout', 'woo-vipps' ) );
            $order->save();

            $token = $session->token;
            $src   = $session->checkout_frontend_url;
            $url   = $src;
        } catch ( Exception $e ) {
            WC_Vipps_Recurring_Logger::log( sprintf( "Could not initiate Vipps/MobilePay checkout session: %s", $e->getMessage() ) );

            return [
                'success'      => false,
                'msg'          => $e->getMessage(),
                'src'          => null,
                'redirect_url' => null,
                'order_id'     => $partial_order_id
            ];
        }

        if ( $url || $redirect_url ) {
            return [
                'success'      => true,
                'msg'          => 'session started',
                'src'          => $url,
                'redirect_url' => $redirect_url,
                'token'        => $token,
                'order_id'     => $partial_order_id
            ];
        }

        return [
            'success'      => false,
            'msg'          => __( 'Could not start Vipps/MobilePay checkout session', 'woo-vipps' ),
            'src'          => $url,
            'redirect_url' => $redirect_url,
            'order_id'     => $partial_order_id
        ];
    }

    public function maybe_login_checkout_user( int $order_id, ?string $key = null ): void {
        if ( ! $key ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $order_key_db = $order->get_order_key( 'code' );
        if ( $order_key_db !== $key ) {
            return;
        }

        // Attempt to log the user in
        $session_auth_token = WC()->session->get( WC_Vipps_Recurring_Helper::SESSION_ORDER_EXPRESS_AUTH_TOKEN );
        $order_auth_token   = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN );

        if ( $order_auth_token !== $session_auth_token ) {
            return;
        }

        $user = $order->get_user();

        wc_set_customer_auth_cookie( $user->ID );
        WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ORDER_EXPRESS_AUTH_TOKEN, null );
    }

    public function cart_changed( string $source ): void {
        $pending_order_id = is_a( WC()->session, 'WC_Session' ) ? WC()->session->get( WC_Vipps_Recurring_Helper::SESSION_CHECKOUT_PENDING_ORDER_ID ) : false;
        $order            = $pending_order_id ? wc_get_order( $pending_order_id ) : null;

        if ( ! $order ) {
            return;
        }

        WC_Vipps_Recurring_Logger::log( sprintf( "Checkout cart changed while session %d in progress, attempting to cancel. Source: %s", $order->get_id(), $source ) );
        $this->abandon_checkout_order( $order );
    }

    /**
     * @throws WC_Vipps_Recurring_Config_Exception
     * @throws WC_Data_Exception
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     */
    public function check_order_status( $order_id ): void {
        $lock_name = "vipps_recurring_checkout_check_order_status_$order_id";
        $lock      = get_transient( $lock_name );

        if ( $lock ) {
            return;
        }

        set_transient( $lock_name, uniqid( '', true ), 5 );

        $order = wc_get_order( $order_id );

        if ( ! WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_IS_CHECKOUT ) ) {
            return;
        }

        $session = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION );
        if ( ! is_array( $session ) ) {
            return;
        }

        $session = $this->gateway()->api->checkout_poll( $session['pollingUrl'] );

        $this->handle_payment( $order, $session );
    }

    public function woocommerce_settings_pages( $settings ) {
        $checkout_enabled = get_option( WC_Vipps_Recurring_Helper::OPTION_CHECKOUT_ENABLED, false );
        if ( ! $checkout_enabled ) {
            return $settings;
        }

        // Find out where the end of the section advanced_page_options is
        $i     = 0;
        $count = count( $settings );

        for ( ; $i < $count; $i ++ ) {
            if ( $settings[ $i ]['type'] === 'sectionend' && $settings[ $i ]['id'] === 'advanced_page_options' ) {
                break;
            }
        }

        if ( $i < $count ) {
            array_splice( $settings, $i, 0, [
                [
                    'title'    => __( 'Vipps/MobilePay Recurring Checkout Page', 'woo-vipps' ),
                    'desc'     => __( 'This page is used for the alternative Vipps/MobilePay checkout page, which you can choose to use instead of the normal WooCommerce checkout page. ', 'woo-vipps' ) . sprintf( __( 'Page contents: [%1$s]', 'woocommerce' ), 'vipps_recurring_checkout' ),
                    'id'       => 'woocommerce_vipps_recurring_checkout_page_id',
                    'type'     => 'single_select_page_with_search',
                    'default'  => '',
                    'class'    => 'wc-page-search',
                    'css'      => 'min-width:300px;',
                    'args'     => [
                        'exclude' =>
                            [
                                wc_get_page_id( 'myaccount' ),
                            ],
                    ],
                    'desc_tip' => true,
                    'autoload' => false,
                ]
            ] );
        }

        return $settings;
    }

    public function woocommerce_get_checkout_page_id( $id ): int {
        $checkout_enabled = get_option( WC_Vipps_Recurring_Helper::OPTION_CHECKOUT_ENABLED, false );

        if ( ! $checkout_enabled ) {
            return $id;
        }

        $checkout_id = $this->gateway()->checkout_is_available();
        if ( $checkout_id ) {
            return $checkout_id;
        }

        return $id;
    }

    public function template_redirect(): void {
        global $post;

        if ( $post && is_page() && has_shortcode( $post->post_content, 'vipps_recurring_checkout' ) ) {
            add_filter( 'woocommerce_is_checkout', '__return_true' );

            add_filter( 'body_class', function ( $classes ) {
                $classes[] = 'vipps-recurring-checkout';
                $classes[] = 'woocommerce-checkout'; // Required by Pixel Your Site

                return apply_filters( 'wc_vipps_recurring_checkout_body_class', $classes );
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

    public function wp_head(): void {
        // If we have a Vipps MobilePay Checkout page, stop iOS from giving previews of it that
        // starts the session - iOS should use the visibility API of the browser for this, but it doesn't as of 2021-11-11
        $checkout_id = wc_get_page_id( 'vipps_recurring_checkout' );
        if ( $checkout_id ) {
            $url = get_permalink( $checkout_id );
            echo "<style> a[href=\"$url\"] { -webkit-touch-callout: none;  } </style>\n";
        }
    }

    public function register_scripts(): void {
        $sdk_url = 'https://checkout.vipps.no/vippsCheckoutSDK.js';
        wp_register_script( 'woo-vipps-recurring-sdk', $sdk_url );

        // Register our React component
        wp_enqueue_style(
            'woo-vipps-recurring-checkout',

            WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/build/checkout.css', [],
            filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/checkout.css' )
        );

        $asset = require WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/checkout.asset.php';

        wp_enqueue_script(
            'woo-vipps-recurring-checkout',
            WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/build/checkout.js',
            array_merge( $asset['dependencies'], [ 'woo-vipps-recurring', 'woo-vipps-recurring-sdk' ] ),
            filemtime( WC_VIPPS_RECURRING_PLUGIN_PATH . '/assets/build/checkout.js' ),
            true
        );
    }

    /**
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     * @throws WC_Vipps_Recurring_Config_Exception
     */
    public function shortcode() {
        global $wp;

        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return false;
        }

        // No point in expanding this unless we are actually doing the checkout. IOK 2021-09-03
        wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
        add_filter( 'wc_vipps_recurring_is_vipps_checkout', '__return_true' );

        if ( is_wc_endpoint_url( 'order-received' ) ) {
            $order_id = absint( $wp->query_vars['order-received'] );
            $this->maybe_login_checkout_user( $order_id, $_GET['key'] ?? null );
        }

        // Defer to the normal code for endpoints IOK 2022-12-09
        if ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) {
            return do_shortcode( "[woocommerce_checkout]" );
        }

        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            $this->abandon_checkout_order( false );
            ob_start();
            wc_get_template( 'cart/cart-empty.php' );

            return ob_get_clean();
        }

        // Previously registered, now enqueue this script which should then appear in the footer.
        wp_enqueue_script( 'vipps-recurring-checkout' );

        do_action( 'vipps_recurring_checkout_before_get_session' );

        // Localize script with variables we need to reveal to the frontend
        $data = $this->current_pending_session();
        if ( empty( $data['session'] ) ) {
            $data['session'] = $this->maybe_create_session();
        }

        wp_add_inline_script( 'woo-vipps-recurring-checkout', 'window.VippsRecurringCheckout = ' . wp_json_encode( $data ), 'before' );

        return '<div id="vipps-mobilepay-recurring-checkout"></div>';
    }

    public function make_order_summary( $order ): WC_Vipps_Checkout_Session_Transaction_Order_Summary {
        $order_lines = [];

        $bottom_line = ( new WC_Vipps_Checkout_Session_Transaction_Order_Summary_Bottom_Line() )
            ->set_currency( $order->get_currency() )
            ->set_gift_card_amount( apply_filters( 'wc_vipps_recurring_order_gift_card_amount', 0, $order ) * 100 )
            ->set_tip_amount( apply_filters( 'wc_vipps_recurring_order_tip_amount', 0, $order ) * 100 )
            ->set_terminal_id( apply_filters( 'wc_vipps_recurring_order_terminal_id', 'woocommerce', $order ) )
            ->set_receipt_number( strval( WC_Vipps_Recurring_Helper::get_id( $order ) ) );

        foreach ( $order->get_items() as $order_item ) {
            $order_line      = [];
            $product_id      = $order_item->get_product_id(); // sku can be tricky
            $total_no_tax    = $order_item->get_total();
            $tax             = $order_item->get_total_tax();
            $total           = $tax + $total_no_tax;
            $subtotal_no_tax = $order_item->get_subtotal();
            $subtotal_tax    = $order_item->get_subtotal_tax();
            $subtotal        = $subtotal_no_tax + $subtotal_tax;
            $quantity        = $order_item->get_quantity();
            $unit_price      = $subtotal / $quantity;

            // Must do this to avoid rounding errors, since we get floats instead of money here :(
            $discount = round( 100 * $subtotal ) - round( 100 * $total );
            if ( $discount < 0 ) {
                $discount = 0;
            }

            $product = wc_get_product( $product_id );
            $url     = home_url( "/" );
            if ( $product ) {
                $url = get_permalink( $product_id );
            }

            if ( $subtotal_no_tax == 0 ) {
                $tax_percentage = 0;
            } else {
                $tax_percentage = ( ( $subtotal - $subtotal_no_tax ) / $subtotal_no_tax ) * 100;
            }
            $tax_percentage = abs( round( $tax_percentage ) );

            $unit_info                             = [];
            $order_line['name']                    = $order_item->get_name();
            $order_line['id']                      = strval( $product_id );
            $order_line['totalAmount']             = round( $total * 100 );
            $order_line['totalAmountExcludingTax'] = round( $total_no_tax * 100 );
            $order_line['totalTaxAmount']          = round( $tax * 100 );
            $order_line['taxRate']                 = round( $tax_percentage * 100 );

            $unit_info['unitPrice']    = round( $unit_price * 100 );
            $unit_info['quantity']     = strval( $quantity );
            $unit_info['quantityUnit'] = 'PCS';
            $order_line['unitInfo']    = $unit_info;
            $order_line['discount']    = $discount;
            $order_line['productUrl']  = $url;
            $order_line['isShipping']  = false;

            $order_lines[] = $order_line;
        }

        foreach ( $order->get_items( 'fee' ) as $order_item ) {
            $order_line                            = [];
            $total_no_tax                          = $order_item->get_total();
            $tax                                   = $order_item->get_total_tax();
            $total                                 = $tax + $total_no_tax;
            $tax_percentage                        = ( ( $total - $total_no_tax ) / $total_no_tax ) * 100;
            $tax_percentage                        = abs( round( $tax_percentage ) );
            $order_line['name']                    = $order_item->get_name();
            $order_line['id']                      = substr( sanitize_title( $order_line['name'] ), 0, 254 );
            $order_line['totalAmount']             = round( $total * 100 );
            $order_line['totalAmountExcludingTax'] = round( $total_no_tax * 100 );
            $order_line['totalTaxAmount']          = round( $tax * 100 );
            $order_line['discount']                = 0;
            $order_line['taxRate']                 = round( $tax_percentage * 100 );

            $order_lines[] = $order_line;
        }

        // Handle shipping
        foreach ( $order->get_items( 'shipping' ) as $order_item ) {
            $order_line         = [];
            $order_line['name'] = $order_item->get_name();
            $order_line['id']   = strval( $order_item->get_method_id() );
            if ( method_exists( $order_item, 'get_instance_id' ) ) {
                $order_line['id'] .= ":" . $order_item->get_instance_id();
            }

            $total_no_tax    = $order_item->get_total();
            $tax             = $order_item->get_total_tax();
            $total           = $tax + $total_no_tax;
            $subtotal_no_tax = $total_no_tax;
            $subtotal_tax    = $tax;
            $subtotal        = $subtotal_no_tax + $subtotal_tax;

            if ( $subtotal_no_tax == 0 ) {
                $tax_percentage = 0;
            } else {
                $tax_percentage = ( ( $subtotal - $subtotal_no_tax ) / $subtotal_no_tax ) * 100;
            }
            $tax_percentage = abs( round( $tax_percentage ) );

            $order_line['totalAmount']             = round( $total * 100 );
            $order_line['totalAmountExcludingTax'] = round( $total_no_tax * 100 );
            $order_line['totalTaxAmount']          = round( $tax * 100 );
            $order_line['taxRate']                 = round( $tax_percentage * 100 );

            $unit_info                 = [];
            $unit_info['unitPrice']    = round( $total * 100 );
            $unit_info['quantity']     = strval( 1 );
            $unit_info['quantityUnit'] = 'PCS';

            $order_line['unitInfo']   = $unit_info;
            $discount                 = 0;
            $order_line['discount']   = $discount;
            $order_line['isShipping'] = true;

            $order_lines[] = $order_line;
        }

        return ( new WC_Vipps_Checkout_Session_Transaction_Order_Summary() )
            ->set_order_lines( $order_lines )
            ->set_order_bottom_line( $bottom_line );
    }

    /**
     * @throws WC_Vipps_Recurring_Config_Exception
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     */
    public function current_pending_session(): array {
        // If this is set, this is a currently pending order which is maybe still valid
        $pending_order_id = WC_Vipps_Recurring_Helper::get_checkout_pending_order_id();
        $order            = $pending_order_id ? wc_get_order( $pending_order_id ) : null;

        # If we do have an order, we need to check if it is 'pending', and if not, we have to check its payment status
        $payment_status = null;
        $redirect       = null;

        if ( $order ) {
            if ( $order->get_status() === 'pending' ) {
                $payment_status = 'INITIATED'; // Just assume this for now
            } else {
                $payment_status = $this->gateway()->check_charge_status( $pending_order_id, true ) ?? 'UNKNOWN';
            }

            if ( $payment_status === 'SUCCESS' ) {
                $redirect = apply_filters( 'wc_vipps_recurring_merchant_redirect_url', WC_Vipps_Recurring_Helper::get_payment_redirect_url( $order ) );
            }
        }

        if ( in_array( $payment_status, [ 'authorized', 'complete' ] ) ) {
            $this->abandon_checkout_order( false );
        } elseif ( $payment_status == 'cancelled' ) {
            WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Vipps/MobilePay checkout session cancelled (pending session)", $order->get_id() ) );

            // This will mostly just wipe the session.
            $this->abandon_checkout_order( $order );
        }

        // Now if we don't have an order right now, we should not have a session either, so fix that
        if ( ! $order ) {
            $this->abandon_checkout_order( false );
        }

        // Now check the orders vipps session if it exist
        $session = $order ? $order->get_meta( WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION ) : false;

        // A single word or array containing session data, containing token and frontendFrameUrl
        // ERROR EXPIRED FAILED
        $session_status = $session ? $this->get_checkout_status( $session ) : null;

        // If this is the case, there is no redirect, but the session is gone, so wipe the order and session.
        if ( in_array( $session_status, [ 'ERROR', 'EXPIRED', 'FAILED' ] ) ) {
            WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Vipps/MobilePay checkout session is gone", $order->get_id() ) );
            $this->abandon_checkout_order( $order );
        }

        // This will return either a valid vipps session, nothing, or redirect.
        return [
            'success'      => (bool) $order,
            'order'        => $order ? $order->get_id() : false,
            'session'      => $session,
            'redirect_url' => $redirect
        ];
    }

    /**
     * @param $session
     *
     * @return string
     */
    public function get_checkout_status( $session ): string {
        if ( $session && isset( $session['token'] ) ) {
            try {
                WC_Vipps_Recurring_Logger::log( "Polling checkout from get_checkout_status" );
                $response = $this->gateway()->api->checkout_poll( $session['pollingUrl'] );

                return $response['sessionState'] ?? "PaymentInitiated";
            } catch ( WC_Vipps_Recurring_Exception $e ) {
                if ( $e->responsecode == 400 ) {
                    return 'PaymentInitiated';
                } else if ( $e->responsecode == 404 ) {
                    return 'SessionExpired';
                } else {
                    WC_Vipps_Recurring_Logger::log( sprintf( "Error polling status - error message %s", $e->getMessage() ) );

                    return 'ERROR';
                }
            } catch ( Exception $e ) {
                WC_Vipps_Recurring_Logger::log( sprintf( "Error polling status - error message %s", $e->getMessage() ) );

                return 'ERROR';
            }
        }

        return "ERROR";
    }

    public function abandon_checkout_order( $order ) {
        if ( WC()->session ) {
            WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_CHECKOUT_PENDING_ORDER_ID, 0 );
            WC()->session->set( WC_Vipps_Recurring_Helper::SESSION_ADDRESS_HASH, false );
        }

        if ( is_a( $order, 'WC_Order' ) && $order->get_status() === 'pending' ) {
            // We want to kill orders that have failed, or that the user has abandoned. To do this,
            // we must ensure that no race or other mechanism kills the order while or after being paid.
            // if order is in the process of being finalized, don't kill it
            if ( WC_Vipps_Recurring_Helper::order_locked( $order ) ) {
                return false;
            }

            // Get it again to ensure we have all the info, and check status again
            clean_post_cache( $order->get_id() );
            $order = wc_get_order( $order->get_id() );
            if ( $order->get_status() !== 'pending' ) {
                return false;
            }

            // And to be extra sure, check status at Vipps/MobilePay
            $session       = $order->get_meta( WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION );
            $poll_endpoint = ( $session && isset( $session['pollingUrl'] ) ) ? $session['pollingUrl'] : false;

            if ( $poll_endpoint ) {
                try {
                    WC_Vipps_Recurring_Logger::log( "Polling checkout from abandon_checkout_order" );
                    $poll_data     = $this->gateway()->api->checkout_poll( $poll_endpoint );
                    $session_state = ( ! empty( $poll_data ) && is_array( $poll_data ) && isset( $poll_data['sessionState'] ) ) ? $poll_data['sessionState'] : "";
                    WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Checking Checkout status on cart/order change: %s", $order->get_id(), $session_state ) );
                    if ( $session_state === 'PaymentSuccessful' || $session_state === 'PaymentInitiated' ) {
                        // If we have started payment, we do not kill the order.
                        WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Checkout payment started - cannot cancel", $order->get_id() ) );

                        return false;
                    }
                } catch ( Exception $e ) {
                    WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Could not get Checkout status for order. Order is still in progress while cancelling', $order->get_id() ) );
                }
            }

            // NB: This can *potentially* be revived by a callback!
            WC_Vipps_Recurring_Logger::log( sprintf( '[%s] Cancelling Checkout order because order changed', $order->get_id() ) );
            $order->set_status( 'cancelled', __( "Order specification changed - order abandoned by customer in Checkout", 'woo-vipps' ), false );

            // Also mark for deletion and remove stored session
            WC_Vipps_Recurring_Helper::delete_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION );

            // Stop checking the status of this order in cron
            WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_PENDING, false );

            // This is dealt with by a cron schedule
            if ( $this->gateway()->get_option( 'checkout_cleanup_abandoned_orders' ) === 'yes' ) {
                WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_ORDER_MARKED_FOR_DELETION, 1 );
            }

            $order->save();
        }
    }

    /**
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     * @throws WC_Vipps_Recurring_Config_Exception
     * @throws WC_Data_Exception
     */
    public function handle_callback( array $body, string $authorization_token ): void {
        WC_Vipps_Recurring_Logger::log( sprintf( "Handling Vipps/MobilePay Checkout callback with body: %s", json_encode( $body ) ) );

        $orders = wc_get_orders( [
            'meta_query' => [
                [
                    'key'     => WC_Vipps_Recurring_Helper::META_ORDER_CHECKOUT_SESSION_ID,
                    'compare' => '=',
                    'value'   => $body['sessionId']
                ]
            ]
        ] );

        if ( empty( $orders ) ) {
            WC_Vipps_Recurring_Logger::log( sprintf( "Found no order ids in Vipps/MobilePay Checkout callback for session id: %s", $body['sessionId'] ) );

            return;
        }

        $order = array_pop( $orders );

        $stored_authorization_token = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_ORDER_EXPRESS_AUTH_TOKEN );
        if ( $authorization_token !== $stored_authorization_token ) {
            WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Invalid authorization token for session id %s.", WC_Vipps_Recurring_Helper::get_id( $order ), $body['sessionId'] ) );

            return;
        }

        $this->handle_payment( $order, $body );
    }

    /**
     * @param WC_Order $order
     * @param array $session
     *
     * @return void
     * @throws WC_Data_Exception
     * @throws WC_Vipps_Recurring_Config_Exception
     * @throws WC_Vipps_Recurring_Exception
     * @throws WC_Vipps_Recurring_Temporary_Exception
     * @throws Exception
     */
    public function handle_payment( WC_Order $order, array $session ): void {
        $order_id     = WC_Vipps_Recurring_Helper::get_id( $order );
        $agreement_id = $session['subscriptionDetails']['agreementId'];
        $status       = $session['sessionState'];

        if ( empty( $agreement_id ) ) {
            return;
        }

        WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Handling Vipps/MobilePay Checkout payment for agreement ID %s with status %s", $order_id, $agreement_id, $status ) );

        // This makes sure we are covered by all our normal cron checks as well
        WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, $agreement_id );

        $order_charge_id = WC_Vipps_Recurring_Helper::get_meta( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID );
        if ( empty( $order_charge_id ) ) {
            $charges = $this->gateway()->api->get_charges_for( $agreement_id );
            /** @var WC_Vipps_Charge $charge */
            $charge = array_pop( $charges );

            WC_Vipps_Recurring_Helper::update_meta_data( $order, WC_Vipps_Recurring_Helper::META_CHARGE_ID, $charge->id );
        }

        $order->save();

        // "SessionCreated" "PaymentInitiated" "SessionExpired" "PaymentSuccessful" "PaymentTerminated"
        if ( in_array( $status, [ 'SessionExpired', 'PaymentTerminated' ] ) ) {
            $this->abandon_checkout_order( $order );

            return;
        }

        if ( $status !== 'PaymentSuccessful' ) {
            return;
        }

        // Create a subscription on success, and set all the required values like _charge_id, _agreement_id, and so on.
        // On success, we might have to create a user as well, if they don't already exist, this is because Woo Subscriptions REQUIRE a user.
        if ( ! $order->get_customer_id( 'edit' ) ) {
            $email   = $session['billingDetails']['email'];
            $user    = get_user_by( 'email', $email );
            $user_id = $user->ID;

            if ( ! $user_id ) {
                WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Handling Vipps/MobilePay Checkout payment: creating a new customer", $order_id ) );

                $username = wc_create_new_customer_username( $email );
                $user_id  = wc_create_new_customer( $email, $username, null );

                $customer = new WC_Customer( $user_id );
                $this->maybe_update_billing_and_shipping( $customer, $session );

                // Send a password reset link right away.
                $user_data = get_user_by( 'ID', $user_id );

                $key = get_password_reset_key( $user_data );

                WC()->mailer();
                do_action( 'woocommerce_reset_password_notification', $user_data->user_login, $key );

                // Log the user in, if we have a valid session.
                if ( WC()->session ) {
                    wc_set_customer_auth_cookie( $user_id );
                }
            }

            $order->set_customer_id( $user_id );
            $order->save();
        }

        $this->maybe_update_billing_and_shipping( $order, $session );

        // Update subscription with the correct customer id, and agreement id
        $existing_subscriptions = wcs_get_subscriptions_for_order( $order );

        /** @var WC_Subscription $subscription */
        $subscription = array_pop( $existing_subscriptions );

        if ( ! $subscription ) {
            return;
        }

        WC_Vipps_Recurring_Helper::update_meta_data( $subscription, WC_Vipps_Recurring_Helper::META_AGREEMENT_ID, $agreement_id );

        $subscription->set_customer_id( $order->get_customer_id( 'edit' ) );
        wcs_copy_order_address( $order, $subscription );
        $subscription->save();

        $this->gateway()->check_charge_status( $order_id, true );

        // This is passed off to regular cron or API handling after this point
        WC_Vipps_Recurring_Logger::log( sprintf( "[%s] Finished handling Vipps/MobilePay Checkout payment for agreement ID %s", $order_id, $agreement_id ) );
    }

    public function maybe_update_billing_and_shipping( $object, $session ): void {
        $contact = $session['billingDetails'] ?? $session['shippingDetails'];

        if ( empty( $contact ) ) {
            return;
        }

        $object->set_billing_email( $contact['email'] );
        $object->set_billing_phone( '+' . $contact['phoneNumber'] );
        $object->set_billing_first_name( $contact['firstName'] );
        $object->set_billing_last_name( $contact['lastName'] );
        $object->set_billing_address_1( $contact['streetAddress'] );
        $object->set_billing_city( $contact['city'] );
        $object->set_billing_postcode( $contact['postalCode'] );
        $object->set_billing_country( $contact['country'] );

        if ( isset( $session['shippingDetails'] ) ) {
            $contact = $session['shippingDetails'];
        }

        $object->set_shipping_first_name( $contact['firstName'] );
        $object->set_shipping_last_name( $contact['lastName'] );
        $object->set_shipping_address_1( $contact['streetAddress'] );
        $object->set_shipping_city( $contact['city'] );
        $object->set_shipping_postcode( $contact['postalCode'] );
        $object->set_shipping_country( $contact['country'] );

        $object->save();
    }

    public function maybe_cancel_initial_order( WC_Order $order ): void {
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

        if ( $order->get_status() === 'pending' && $minutes > 120 ) {
            $this->abandon_checkout_order( $order );
        }
    }

    public function user_has_subscription( $has_subscription, $user_id, $product_id, $status ) {
        $subscriptions = wcs_get_users_subscriptions( $user_id );

        foreach ( $subscriptions as $subscription ) {
            if ( ! WC_Vipps_Recurring_Helper::get_meta( $subscription, WC_Vipps_Recurring_Helper::META_ORDER_IS_CHECKOUT ) ) {
                continue;
            }

            // You do not have a subscription simply because you have a pending subscription.
            // Checkout subscriptions are created BEFORE a payment is made.
            if ( $subscription->has_status( 'pending' ) ) {
                $has_subscription = false;
                break;
            }
        }

        return $has_subscription;
    }
}
