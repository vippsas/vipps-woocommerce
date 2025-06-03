<?php
/*
   This class is for hooks and plugin managent for the Vipps Checkout checkout-replacement feature, and is instantiated as a singleton.
   IOK 2023-05-16
   For WP-specific interactions.


This file is part of the plugin Pay with Vipps and MobilePay for WooCommerce
Copyright (c) 2023 WP-Hosting AS

MIT License

Copyright (c) 2023 WP-Hosting AS

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.


 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class VippsCheckout {
    private static $instance = null;
    private $gw = null;
    private $payid = 0; // Used to improve handling of "what is the current checkout page" IOK 2024-11-14

    public static function instance()  {
        if (!static::$instance) static::$instance = new VippsCheckout();
        return static::$instance;
    }

    public function gateway() {
        if ($this->gw) return $this->gw;
        $this->gw = Vipps::instance()->gateway();
        return $this->gw;
    }

    public static function register_hooks() {
        $VippsCheckout = static::instance();
        if (is_admin()) {
            add_action('admin_init',array($VippsCheckout,'admin_init'));
        }
        add_action('init',array($VippsCheckout,'init'));
        add_action( 'woocommerce_loaded', array($VippsCheckout,'woocommerce_loaded'));
        add_action( 'template_redirect', array($VippsCheckout,'template_redirect'));
        add_action( 'admin_post_nopriv_vipps_gw', array($VippsCheckout, 'choose_other_gw'));
        add_action( 'admin_post_vipps_gw', array($VippsCheckout, 'choose_other_gw'));
        add_filter( 'woocommerce_order_email_verification_required', array($VippsCheckout, 'allow_other_payment_method_email'), 10, 3);
        add_action('wp_footer', array($VippsCheckout, 'maybe_proceed_to_payment'));


        add_filter('woo_vipps_shipping_method_pickup_points', function ($points, $rate, $shipping_method, $order) {
            if ($rate->method_id == 'pickup_location') {
               // $locations = $shipping_method->pickup_locations ; // Protected attribute. Could wrap, but life is short so
                $locations = get_option( $shipping_method->id . '_pickup_locations', [] );
                foreach ( $locations as $index => $location ) {
                    if ( ! $location['enabled'] ) {
                        continue;
                    }

                    $addr = $location['address'] ?? [];
		    $default_country = WC()->countries->get_base_country() ?: "NO";

                    $point = [];
                    $point['id'] = "$index";
                    $point['name'] = $location['name'] ?: " ";
                    $point['address'] = $addr['address_1'] ?: " ";
                    $point['postalCode'] = $addr['postcode'] ?: " ";
                    $point['city'] = $addr['city'] ?: " ";
                    $point['country'] = $addr['country'] ?: $default_country;
                    $points[] = $point;
                }
            }
            return $points;
        }, 10, 4);


    }

    public function allow_other_payment_method_email ($email_verification_required, $order, $context ) {
        if (is_checkout_pay_page()) {
            $proceed = $order->get_meta('_vc_proceed');
            if ($proceed) {
               // Maybe do a timestamp check here FIXME
               return false;
            }
        }
        return $email_verification_required;
    }

    # For "Choose other payment method" in Vipps Checkout, we will decorate the order with the chosen gw
    # and use javascript to go directly to that method. This is partly because we can't set default gateway to
    # kco on the order pay screen. IOK 2024-05-15
    public function maybe_proceed_to_payment() {
        if (is_checkout_pay_page()) {
            $orderid = absint(get_query_var( 'order-pay'));
            $order = $orderid ? wc_get_order($orderid) : null;
            $gateway = sanitize_title(trim(is_a($order, 'WC_Order') ? $order->get_meta('_vc_proceed') : false));
            $method = $gateway;
            // Choose invoice payment if Klarna payments is the gateway. IOK 2024-10-11
            if ($gateway == 'klarna_payments') {
               $method = 'klarna_payments_pay_later';
            }
            $method = apply_filters('woo_vipps_checkout_external_payment_method_selected', $method, $gateway, $order);
            $order->update_meta_data('_vc_proceed', 'any'); // Just in case we return to the order-pay page
            $order->save();
     
            if ($method != 'any'): 
?>
<script>
jQuery(document).ready(function () {
        let selected = jQuery('input#payment_method_<?php echo sanitize_title($method); ?>[name="payment_method"]');
        if (selected.length > 0) {
            jQuery('input#terms[type="checkbox"]').prop('checked', true).trigger('change');
            selected.prop('checked', true).trigger('change');
            // Neccessary for Klarna Payments
            setTimeout(function () { 
                jQuery('button#place_order').click(); }, 100);
        }
        });
</script>
<?php
          endif;
        }
    }

    // Returns list of external payment methods - from Vipps id to gateway id. 
    // IOK 2024-10-11 filterable by 'woo_vipps_checkout_external_payment_methods'
    public function external_payment_methods() {
       $available = array_keys(WC()->payment_gateways->get_available_payment_gateways());
       $gw = WC_Gateway_Vipps::instance();
       $ok = $gw->allow_external_payments_in_checkout();
       if (!$ok) return [];
       // Only defined value at this point - klarna means either of these gateways (IOK 2024-05-28)
       // Prioritize payments if present
       $possible = ['klarna' => ['klarna_payments', 'kco']];
       $externals = [];
       foreach ($possible as $key => $gws) {
         $on = $gw->get_option('checkout_external_payments_' . $key);
         $active = array_intersect($gws, $available);
         if ($on == "yes" && !empty($active)) {
           $externals[$key] = ['gw' => array_values($active)[0]];
         }
       }
       return $externals;
    }

    # Called in admin-post and will finalize a Vipps Checkout order + send the customer to the payment page.
    public function choose_other_gw () {
        $orderid = intval($_GET['o']);
        $gw = trim(sanitize_title($_GET['gw']));
        if ($gw == 'any') $gw = "";
        $nonce = $_GET['cb'];
        $ok = wp_verify_nonce($nonce, 'vipps_gw');
        if (!$ok) {
            $this->abandonVippsCheckoutOrder(false);
            $this->log(sprintf(__("Orderid %1\$s: Wrong nonce when trying to switch payment methods.", 'woo-vipps'), $orderid), 'error');
            wp_redirect(home_url());
        }
        $order = wc_get_order($orderid);
        if (!$order || $order->get_status() != 'pending') {
            $this->abandonVippsCheckoutOrder(false);
            $this->log(sprintf(__("Orderid %1\$s is not pending when choosing another payment method from Vipps Checkout", 'woo-vipps'), $orderid), 'error');
            wp_redirect(home_url());
        }

        // Load session from cookies - it will not get loaded on admin-post.
        WC()->initialize_session();

        if (WC()->session) { 
            if (! WC()->session->has_session()) {
                WC()->session->set_customer_session_cookie( true );
            }
            # There is actually a bug here for KCO which will redirect to the normal checkout page with an error message. 
            #Try to stop that.. IOK 2024-05-15
            if ($gw != 'kco') {
                    WC()->session->set('chosen_payment_method', $gw); 
            }
            $addressdata = [];
            $addressdata["billing_email"] =  $order->get_billing_email();
            $addressdata["billing_address_1"] =  $order->get_billing_address_1();
            $addressdata["billing_address_2"] =  $order->get_billing_address_2();
            $addressdata["billing_postcode"] =  $order->get_billing_postcode();
            $addressdata["billing_city"] =  $order->get_billing_city();
            $addressdata["billing_country"] =  $order->get_billing_country();
            $addressdata["billing_first_name"] =  $order->get_billing_first_name();
            $addressdata["billing_last_name"] =  $order->get_billing_last_name();
            $addressdata["billing_phone"] =  $order->get_billing_phone();
            $addressdata["billing_city"] =  $order->get_billing_city();
            $addressdata["shipping_address_1"] =  $order->get_shipping_address_1();
            $addressdata["shipping_address_2"] =  $order->get_shipping_address_2();
            $addressdata["shipping_city"] =  $order->get_shipping_city();
            $addressdata["shipping_postcode"] =  $order->get_shipping_postcode();
            $addressdata["shipping_country"] =  $order->get_shipping_country();
            $addressdata["shipping_first_name"] =  $order->get_shipping_first_name();
            $addressdata["shipping_last_name"] =  $order->get_shipping_last_name();
            $addressdata["shipping_phone"] =  $order->get_shipping_phone();
            $addressdata["shipping_city"] =  $order->get_shipping_city();
            WC()->session->set('vc_address', $addressdata);
            WC()->session->save_data();
        } else {
            $this->log(__("No session choosing other gateway from Vipps Checkout", 'woo-vipps'), 'error');
        }

        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;

        # If we got here, we actually have shipping information already in place, so we can continue with the order directly!
        $paymentdetails = WC_Gateway_Vipps::instance()->get_payment_details($order);
        $billing = isset($paymentdetails['billingDetails']) ? $paymentdetails['billingDetails'] : false;
        # Don't assign the order to its user if we are not logged in - we are not completing this order using Vipps IOK 2024-05-15
        $assignuser = is_user_logged_in();

        WC_Gateway_Vipps::instance()->set_order_shipping_details($order,($paymentdetails['shippingDetails'] ?? []), $paymentdetails['userDetails'], $billing, $paymentdetails, $assignuser);

        # Now reset payment gateway and clear out the VC session
        $order->set_payment_method($gw);
        $order->update_meta_data('_vc_proceed', ($gw ? $gw : 'any'));
        $order->add_order_note(sprintf(__('Alternative payment method "%1$s" chosen, customer returned from Checkout', 'woo-vipps'), $gw));
        $order->save();
        // Should not be neccessary but we'll add this just so any caching does not cause other systems to return the wrong data here  IOK 2025-03-18
        clean_post_cache($order->get_id());

        $url = $order->get_checkout_payment_url();

        // This makes sure there is no "current vipps checkout" order, as this order is no longer payable at Vipps.
        // Actually, don't do this because it will most likely just create more spurious orders while this remains unpayable. IOK 2024-06-04
        // $this->abandonVippsCheckoutOrder(false);
        wp_redirect($url);
        exit();
    }

    public function template_redirect () {
        // This will normally be on the "checkout" page which shouldn't be cached, but just in case, add
        // nocache headres to any page that uses this shortcode. IOK 2021-08-26
        // Furthermore, sometimes woocommerce calls is_checkout() *before* woocommerce is loaded, so
        global $post;

        // Modify cart coupon validation - has to be very early because otherwise we can't show 
        // notices in gutenberg cart IOK 2025-05-15
        $this->handle_coupon_invalidation_in_checkout();

        if ($post && is_page() &&  has_shortcode($post->post_content, 'vipps_checkout')) {
            // Add fonts for the widgets on this page IOK 2025-05-02
            wp_enqueue_style('vipps-fonts',plugins_url('css/fonts.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/fonts.css"), 'all');

            add_filter('woocommerce_is_checkout', '__return_true');
            add_filter('body_class', function ($classes) {
                    $classes[] = 'vipps-checkout';
                    $classes[] = 'woocommerce-checkout'; // Required by Pixel Your Site IOK 2022-11-24
                    return apply_filters('woo_vipps_checkout_body_class', $classes);
                    });
            /* Suppress the title for this page, but on the front page only IOK 2023-01-27 (by request from Vipps) */
            $post_to_hide_title_for = $post->ID;
            add_filter('the_title', function ($title, $postid = 0) use ($post_to_hide_title_for) {
                    if (!is_admin() && $postid ==  $post_to_hide_title_for && is_singular()  && in_the_loop()) {
                    $title = "";
                    }
                    return $title;
                    }, 10, 2);
            Vipps::nocache();
        }
    }


    public function init () {
        add_action('wp_loaded', array($this, 'wp_register_scripts'));

        // For Vipps Checkout, poll for the result of the current session
        add_action('wp_ajax_vipps_checkout_poll_session', array($this, 'vipps_ajax_checkout_poll_session'));
        add_action('wp_ajax_nopriv_vipps_checkout_poll_session', array($this, 'vipps_ajax_checkout_poll_session'));
        // Use ajax to initiate the session too
        add_action('wp_ajax_vipps_checkout_start_session', array($this, 'vipps_ajax_checkout_start_session'));
        add_action('wp_ajax_nopriv_vipps_checkout_start_session', array($this, 'vipps_ajax_checkout_start_session'));

        // And for user-defined callbacks in the widgets. These may modify the order.
        add_action('wp_ajax_vipps_checkout_callback', array($this, 'vipps_ajax_checkout_callback'));
        add_action('wp_ajax_nopriv_vipps_checkout_callback', array($this, 'vipps_ajax_checkout_callback'));

        // Check cart total before initiating Vipps Checkout NT-2024-09-07
        // This allows for real-time validation of the cart before proceeding with the checkout process
        add_action('wp_ajax_vipps_checkout_validate_cart', array($this, 'ajax_vipps_checkout_validate_cart'));
        add_action('wp_ajax_nopriv_vipps_checkout_validate_cart', array($this, 'ajax_vipps_checkout_validate_cart'));

        // Retrieve widgets - this is done by ajax so as to ensure that the Order object exists at this point.
        add_action('wp_ajax_vipps_checkout_get_widgets', array($this, 'vipps_ajax_get_widgets'));
        add_action('wp_ajax_nopriv_vipps_checkout_get_widgets', array($this, 'vipps_ajax_get_widgets'));

        // Prevent previews and prefetches of the Vipps Checkout page starting and creating orders
        add_action('wp_head', array($this, 'wp_head'));


        // The Vipps Checkout feature which overrides the normal checkout process uses a shortcode
        add_shortcode('vipps_checkout', array($this, 'vipps_checkout_shortcode'));

        // Ensure we remove the current session on the thank you page (too).
        add_action('woocommerce_thankyou_vipps', function () {
            WC()->session->set('vipps_checkout_current_pending',false);
            WC()->session->set('vipps_address_hash', false);
        });

        // For Vipps Checkout - we need to know any time and as soon as the cart changes, so fold all the events into a single one. IOK 2021-08-24
        add_action( 'woocommerce_add_to_cart', function ($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
            do_action('vipps_cart_changed');
        }, 10, 6);
        // Cart emptied 
        add_action( 'woocommerce_cart_emptied', function ($clear_persistent_cart) {
            do_action('vipps_cart_changed');
        }, 10, 1);
        // After updating quantities
        add_action('woocommerce_after_cart_item_quantity_update', function ( $cart_item_key,  $quantity,  $old_quantity ) {
            do_action('vipps_cart_changed');
        }, 10, 3);
        // Blocks and ajax
        add_action( 'woocommerce_cart_item_removed', function ($cart_item_key, $cart) {
            do_action('vipps_cart_changed');
        }, 10, 3);
        // Restore deleted entry
        add_action( 'woocommerce_cart_item_restored', function ($cart_item_key, $cart) {
            do_action('vipps_cart_changed');
        }, 10, 3);
        // Normal cart form update
        add_filter('woocommerce_update_cart_action_cart_updated', function ($updated) {
            do_action('vipps_cart_changed');
            return $updated;
        });
        // Trigger cart_changed when a coupon is applied
        add_action('woocommerce_applied_coupon', array($this, 'cart_changed'));
        // Trigger cart_changed when a coupon is removed
        add_action('woocommerce_removed_coupon', array($this, 'cart_changed'));
        // Then handle the actual cart change
        add_action('vipps_cart_changed', array($this, 'cart_changed'));
    }

    public function admin_init () {
        // Stuff for the special Vipps Checkout page
        add_filter('woocommerce_settings_pages', array($this, 'woocommerce_settings_pages'), 10, 1);
    }

    // Extra stuff in the <head>, which will mostly mean dynamic CSS
    public function wp_head () {
        // If we have a Vipps Checkout page, stop iOS from giving previews of it that
        // starts the session - iOS should use the visibility API of the browser for this, but it doesn't as of 2021-11-11
        $checkoutid = wc_get_page_id('vipps_checkout');
        if ($checkoutid) {
            $url = get_permalink($checkoutid);
            echo "<style> a[href=\"$url\"] { -webkit-touch-callout: none;  } </style>\n";
        } 
    }
   
    public function wp_register_scripts () {
        $sdkurl = 'https://checkout.vipps.no/vippsCheckoutSDK.js';
        wp_register_script('vipps-sdk',$sdkurl,array());
        wp_register_script('vipps-checkout-widgets', plugins_url('js/vipps-checkout-widgets.js', __FILE__), [], filemtime(dirname(__FILE__) . "/js/vipps-checkout-widgets.js"), 'true');
        wp_register_script('vipps-checkout',plugins_url('js/vipps-checkout.js',__FILE__),array('vipps-gw','vipps-sdk', 'vipps-checkout-widgets'),filemtime(dirname(__FILE__) . "/js/vipps-checkout.js"), 'true');
    }

    public function log ($what,$type='info') {
        $logger = function_exists('wc_get_logger') ? wc_get_logger() : false;
        if ($logger) {
            $context = array('source'=>'woo-vipps');
            $logger->log($type,$what,$context);
        } else {
            error_log("woo-vipps ($type): $what");
        }
    }


    // This is used by the Vipps Checkout page to start the Vipps checkout session, including 
    // creating the partial order IOK 2021-09-03
    // IOK FIXME: Add support for starting payment *for a given order*. 2023-08-15
    public function vipps_ajax_checkout_start_session () {
        check_ajax_referer('do_vipps_checkout','vipps_checkout_sec');
        $url = ""; 
        $redir = "";
        $token = "";

        // First, check that we haven't already done this like in another window or something:
        // IOK 2024-06-04 This also happens when using the back button! Sometimes!
        $sessioninfo = $this->vipps_checkout_current_pending_session();

        if (isset($sessioninfo['redirect'])) {
            $redirect = $sessioninfo['redirect'];
        }
        if (isset($sessioninfo['session']) && isset($sessioninfo['session']['token'])) {
            $token = $sessioninfo['session']['token'];
            $src = $sessioninfo['session']['checkoutFrontendUrl'];
            $url = $src; 
        }
        // And if we do, just return what we have. NB: This *should not happen*.
        // IOK 2025-05-04 what are you talking about IOK, this absolutely happens e.g. when using the backbutton to a page starting the orders.
        if ($url || $redir) {
            $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
            return wp_send_json_success(array('ok'=>1, 'msg'=>'session started', 'src'=>$url, 'redirect'=>$redir, 'token'=>$token, 'orderid'=>$current_pending));
        }

        // Otherwise, create an order and start a new session
        $session = null;
        $current_pending = 0;
        $current_authtoken = "";
        $limited_session = "";
        try {
                $current_pending = $this->gateway()->create_partial_order('ischeckout');
                if ($current_pending) {
                    $order = wc_get_order($current_pending);
                    $order->update_meta_data('_vipps_checkout', true);
                    $current_authtoken = $this->gateway()->generate_authtoken();
                    $limited_session = $this->gateway()->generate_authtoken();
                    $order->update_meta_data('_vipps_authtoken',wp_hash_password($current_authtoken));
                    $order->update_meta_data('_vipps_limited_session',wp_hash_password($limited_session));
                    $order->save();
                    WC()->session->set('vipps_checkout_current_pending', $current_pending);

                    try {
                        Vipps::instance()->maybe_add_static_shipping($this->gateway(),$order->get_id(), 'vippscheckout');
                    } catch (Exception $e) {
                        // In this case, we just have to continue.
                        $this->log(sprintf(__("Error calculating static shipping for order %1\$s", 'woo-vipps'), $order->get_id()), 'error');
                        $this->log($e->getMessage(),'error');
                    }
                    $this->gateway()->save_session_in_order($order);
                    do_action('woo_vipps_checkout_order_created', $order);
                } else {
                    throw new Exception(sprintf(__('Unknown error creating %1$s partial order', 'woo-vipps'), Vipps::CheckoutName()));
                }
        } catch (Exception $e) {
            return wp_send_json_success(array('ok'=>0, 'msg'=>$e->getMessage(), 'src'=>'', 'redirect'=>'', 'orderid'=>0));
        }

        // Ensure we get the latest updates to the order too IOK 2021-10-22
        $order = wc_get_order($current_pending);
        if (is_user_logged_in()) {
            $phone = get_user_meta(get_current_user_id(), 'billing_phone', true);
        }
        $order_id = $order->get_id();
        $requestid = 1;
        $returnurl = Vipps::instance()->payment_return_url();
        $returnurl = add_query_arg('ls',$limited_session,$returnurl);
        $returnurl = add_query_arg('id', $order_id, $returnurl);

        $sessionorders= WC()->session->get('_vipps_session_orders');
        if (!$sessionorders) $sessionorders = array();
        $sessionorders[$order_id] = 1;
        WC()->session->set('_vipps_pending_order',$order_id);
        WC()->session->set('_vipps_session_orders',$sessionorders);

        $customer_id = get_current_user_id();
        if ($customer_id) {
           $customer = new WC_Customer( $customer_id );
        } else {
            $customer = WC()->customer;
        }

        if ($customer) {
            $customerinfo['email'] = $customer->get_billing_email();
            $customerinfo['firstName'] = $customer->get_billing_first_name();
            $customerinfo['lastName'] = $customer->get_billing_last_name();
            $customerinfo['streetAddress'] = $customer->get_billing_address_1();
            $address2 =  trim($customer->get_billing_address_2());
            if (!empty($address2)) {
                $customerinfo['streetAddress'] = $customerinfo['streetAddress'] . ", " . $address2;
            }
            $customerinfo['city'] = $customer->get_billing_city();
            $customerinfo['postalCode'] = $customer->get_billing_postcode();
            $customerinfo['country'] = $customer->get_billing_country();

            // Currently Vipps requires all phone numbers to have area codes and NO  +. We can't guaratee that at all, but try for Norway
            $phone = ""; 
            $phonenr = Vipps::normalizePhoneNumber($customer->get_billing_phone(), $customerinfo['country']);
            if ($phonenr) {
                $customerinfo['phoneNumber'] = $phone;
            }
        }

        $keys = ['firstName', 'lastName', 'streetAddress', 'postalCode', 'country', 'phoneNumber'];
        foreach($keys as $k) {
            if (empty($customerinfo[$k])) {
                $customerinfo = array(); break;
            }
        }
        $customerinfo = apply_filters('woo_vipps_customerinfo', $customerinfo, $order);

        try {
            $session = $this->gateway()->api->initiate_checkout($customerinfo,$order,$returnurl,$current_authtoken,$requestid); 
            if ($session) {
                $order = wc_get_order($current_pending);
                $order->update_meta_data('_vipps_init_timestamp',time());
                $order->update_meta_data('_vipps_status','INITIATE');
                $order->update_meta_data('_vipps_checkout_session', $session);

                $order->add_order_note(sprintf(__('%1$s payment initiated','woo-vipps'), Vipps::CheckoutName()));
                $order->add_order_note(sprintf(__('Customer passed to %1$s','woo-vipps'), Vipps::CheckoutName()));
                $order->save();
                $token = $session['token'];
                $src = $session['checkoutFrontendUrl'];
                $url = $src;
            } else {
                    throw new Exception(sprintf(__('Unknown error creating %1$s session', 'woo-vipps'), Vipps::CheckoutName()));
            }
        } catch (Exception $e) {
                $this->log(sprintf(__("Could not initiate %1\$s session: %2\$s", 'woo-vipps'), Vipps::CheckoutName(), $e->getMessage()), 'ERROR');
                return wp_send_json_success(array('ok'=>0, 'msg'=>$e->getMessage(), 'src'=>'', 'redirect'=>'', 'orderid'=>$order_id));
        }
        if ($url || $redir) {
            return wp_send_json_success(array('ok'=>1, 'msg'=>'session started', 'src'=>$url, 'redirect'=>$redir, 'token'=>$token, 'orderid'=>$order_id));
        } else { 
            return wp_send_json_success(array('ok'=>0, 'msg'=>sprintf(__('Could not start %1$s session'), Vipps::CheckoutName()),'src'=>$url, 'redirect'=>$redir, 'orderid'=>$order_id));
        }
    }

    // Handler function for all other callbacks from the Vipps MobilePay checkout screen - adding 
    // coupons, modifying the order etc. Actions are added with the filter 'woo_vipps_checkout_callback_actions' IOK 2025-05-13
    // -- they are functions taking the action name and an order object. IOK 2025-05-13
    public function vipps_ajax_checkout_callback() {
        check_ajax_referer('do_vipps_checkout','vipps_checkout_sec');
        $orderid = intval($_REQUEST['orderid']??0); // Currently not used because we are using a single pending order in session
        $lock_held = intval($_REQUEST['lock_held'] ?? 0);
        $action = sanitize_title($_REQUEST['callback_action'] ?? 0);

        add_filter('woo_vipps_is_checkout_callback', '__return_true'); // Signal that this is a special context.

        // add some default action handlers IOK  2025-05-13
        $this->add_widget_callback_actions();

        $actions = apply_filters('woo_vipps_checkout_callback_actions', []);

        $handler = $actions[$action] ?? false;
        if (!$handler) {
           $msg = sprintf(__("Vipps MobilePay Checkout callback with unknown action: %s", 'woo-vipps'), $action);
           $this->log($msg, 'DEBUG');
           return wp_send_json_error(array('msg'=>'FAILED', 'error'=>$msg));
        }
        // The single current pending order. IOK 2025-04-25
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
        $order = $current_pending ? wc_get_order($current_pending) : null;
        $prevtotal = $order->get_total();
        try {
            $result = $handler($action, $order);
            $order = wc_get_order($order->get_id()); // Incase the order has changed since last wc_get_order. LP 2025-05-14
            $newtotal = $order->get_total();
            if ($lock_held && $newtotal != $prevtotal) {
                try {
                    $res = $this->gateway()->api->checkout_modify_session($order);
                } catch (Exception $e) {
                    $this->log(__("Problem modifying Checkout session: ", 'woo-vipps')  . $e->getMessage());
                }
            }
            return wp_send_json_success(array('msg'=>$result));
        } catch (Exception $e) {
           return wp_send_json_error(array('msg'=>'FAILED', 'error'=>$e->getMessage()));
        }
    }

    // Check the current status of the current Checkout session for the user.
    public function vipps_ajax_checkout_poll_session () {
        check_ajax_referer('do_vipps_checkout','vipps_checkout_sec');

        $orderid = intval($_REQUEST['orderid']??0); // Currently not used because we are using a single pending order in session
        $lock_held = intval($_REQUEST['lock_held'] ?? 0);
        $type = $_REQUEST['type'] ?? "unknown"; // Type of callback

        // The single current pending order. IOK 2025-04-25
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
        $order = $current_pending ? wc_get_order($current_pending) : null;

        $payment_status = $order ?  $this->gateway()->check_payment_status($order) : 'unknown';
        if (in_array($payment_status, ['authorized', 'complete'])) {
            $this->abandonVippsCheckoutOrder(false);
            return wp_send_json_success(array('msg'=>'completed', 'url' => $this->gateway()->get_return_url($order)));;
        }
        if ($payment_status == 'cancelled') {
            $this->log(sprintf(__("%1\$s session %2\$d cancelled (payment status)", 'woo-vipps'), Vipps::CheckoutName(), $order->get_id()), 'debug');
            $this->abandonVippsCheckoutOrder($order);
            return wp_send_json_error(array('msg'=>'FAILED', 'url'=>home_url()));
        }

        $session = $order ? $order->get_meta('_vipps_checkout_session') : false;
        if (!$session) {
            WC()->session->set('vipps_address_hash', false);
            return wp_send_json_success(array('msg'=>'EXPIRED', 'url'=>false));
        }

        add_filter('woo_vipps_is_vipps_checkout', '__return_true');
        $status = $this->get_vipps_checkout_status($order);

        $failed = $status == 'ERROR' || $status == 'EXPIRED' || $status == 'TERMINATED';

        // Disallow sessions that go on for too long.
        if (is_a($order, "WC_Order")) {
            $created = $order->get_date_created();
            $timestamp = 0;
            $now = time();
            try {
                $timestamp = $created->getTimestamp();
            } catch (Exception $e) {
                // PHP 8 gives ValueError for certain older versions of WooCommerce here.
                $timestamp = intval($created->format('U'));

            }
            $passed = $now - $timestamp;
            $minutes = ($passed / 60);
            // Expire after 50 minutes
            if ($minutes > 50) {
                $this->log(sprintf(__("%1\$s session %2\$d expired after %3\$d minutes (limit 50)", 'woo-vipps'), Vipps::CheckoutName(), $order->get_id(), $minutes), 'debug');
                $this->abandonVippsCheckoutOrder($order);
                return wp_send_json_success(array('msg'=>'EXPIRED', 'url'=>false));
            }
        }

        $ok   = !$failed;

        // Since we checked the payment status at Vipps directly above, we don't actaully have any extra information at this point.
        // We do know that the session is live and ongoing, but that's it.

        if ($failed) { 
            $msg = $status;
            $this->log(sprintf(__("%1\$s session %2\$d failed with message %3\$s", 'woo-vipps'), Vipps::CheckoutName(), $order->get_id(), $msg), 'debug');
            $this->abandonVippsCheckoutOrder($order);
            return wp_send_json_error(array('msg'=>$msg, 'url'=>home_url()));
            exit();
        }
        // Errorhandling! If this happens we have an unknown status or something like it.
        if (!$ok) {
            $this->log("Unknown status on polling status: " . print_r($status, true), 'ERROR');
            $this->abandonVippsCheckoutOrder($order);
            return wp_send_json_error(array('msg'=>'ERROR', 'url'=>false));
            exit();
        }

        // This handles address information data from the poll if present. It is not, currently.  2021-09-27 IOK
        // it is now! IOK 2024-04-24
        $change = false;
        $vipps_address_hash =  WC()->session->get('vipps_address_hash');
        if ($ok && (isset($status['billingDetails']) || isset($status['shippingDetails'])))  {
            $serialized = sha1(json_encode(@$status['billingDetails']) . ':' . json_encode(@$status['shippingDetails']));
            if ($serialized != $vipps_address_hash) {
                $change = true;
                WC()->session->set('vipps_address_hash', $serialized);
            } 
        }

        // IOK This is the actual status of the order when this is called, which will
        // include personalia only when available
        if ($ok && $change && isset($status['billingDetails']))  {
            $contact = $status['billingDetails'];
            $order->set_billing_email($contact['email']);
            $order->set_billing_phone($contact['phoneNumber']);
            $order->set_billing_first_name($contact['firstName']);
            $order->set_billing_last_name($contact['lastName']);
            $order->set_billing_address_1($contact['streetAddress']);
            $order->set_billing_city($contact['city']);
            $order->set_billing_postcode($contact['postalCode']);
            $order->set_billing_country($contact['country']);
        }
        if ($ok &&  $change && isset($status['shippingDetails'])) {
            $contact = $status['shippingDetails'];
            if ($contact['country'] ?? false) {
                $countrycode =  Vipps::instance()->country_to_code($contact['country']); // No longer neccessary IOK 2023-01-09
                $order->set_shipping_first_name($contact['firstName']);
                $order->set_shipping_last_name($contact['lastName']);
                $order->set_shipping_address_1($contact['streetAddress']);
                $order->set_shipping_city($contact['city']);
                $order->set_shipping_postcode($contact['postalCode']);
                $order->set_shipping_country($contact['country']);
            }

        }
        if ($change) {
          $order->save();
        }

        // When the address changes, the VAT/taxes may have changed too. Recalculate the order total if we know the Vipps lock
        // of the order is held. IOK 2025-04-25
        if ($change) {
            $prevtotal = $order->get_total();
            $newtotal = $order->calculate_totals(true); // With taxes please
            if ($lock_held && $newtotal != $prevtotal) {
                try {
                    $res = $this->gateway()->api->checkout_modify_session($order);
                    $order->save();
                } catch (Exception $e) {
                    $this->log(__("Problem modifying Checkout session: ", 'woo-vipps')  . $e->getMessage());
                    if ($newtotal < $prevtotal) {
                        // In this case, the orders value will be lower than what is reserved at Vipps, which is OK - the rest will be cancelled
                        // on order completion. IOK 2025-05-24
                        $order->save();
                    }
                }
            }
        }

        if ($ok && $change) {
            wp_send_json_success(array('msg'=>'order_change', 'url'=>''));
            exit();
        }
        if ($ok) {
            wp_send_json_success(array('msg'=>'no_change', 'url'=>''));
            exit();
        }

        // This should never happen.
        wp_send_json_success(array('msg'=>'unknown', 'url'=>''));
    }

    // Check cart total before initiating Vipps Checkout NT-2024-09-07
    // Also any other checks we might want to do in the future. This will validate the cart each time the
    //  checkout page loads, even if a session is already in progress.  IOK 2024-09-09
    public function ajax_vipps_checkout_validate_cart() {
        $cart_total = WC()->cart->get_total('edit');
        $minimum_amount = 1; // 1 in the store currency

        if ($cart_total < $minimum_amount) {
            wp_send_json_error(array(
                'message' => sprintf(__('Vipps Checkout cannot be used for orders less than %1$s %2$s', 'woo-vipps'), $minimum_amount, get_woocommerce_currency() )
            ));
        } else {
            wp_send_json_success(array('message', __("OK", 'woo-vipps')));
        }
    }

    // Retrieve the current pending Vipps Checkout session, if it exists, and do some cleanup
    // if it isn't correct IOK 2021-09-03
    protected function vipps_checkout_current_pending_session () {
        // If this is set, this is a currently pending order which is maybe still valid
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
        $order = $current_pending ? wc_get_order($current_pending) : null;

        # If we do have an order, we need to check if it is 'pending', and if not, we have to check its payment status
        $payment_status = null;
        if ($order) {
            if ($order->get_status() == 'pending') {
                $payment_status = 'initiated'; // Just assume this for now
            } else {
                $payment_status = $order ?  $this->gateway()->check_payment_status($order) : 'unknown';
            } 
        }
        // This covers situations where we can actually go directly to the thankyou-screen or whatever
        $redirect = "";
        if (in_array($payment_status, ['authorized', 'complete'])) {
            $this->abandonVippsCheckoutOrder(false);
            $redirect = $this->gateway()->get_return_url($order);
        } elseif ($payment_status == 'cancelled') {
            $this->log(sprintf(__("%1\$s session %2\$d cancelled (pending session)", 'woo-vipps'), Vipps::CheckoutName(), $order->get_id()), 'debug');
            // This will mostly just wipe the session.
            $this->abandonVippsCheckoutOrder($order);
            $redirect = home_url();
        }
        // Now if we don't have an order right now, we should not have a session either, so fix that
        if (!$order) {
            $this->abandonVippsCheckoutOrder(false);
        } 

        // Now check the orders vipps session if it exist 
        $session = $order ? $order->get_meta('_vipps_checkout_session') : false;

        // A single word or array containing session data, containing token and frontendFrameUrl
        // If a word, it will be ERROR EXPIRED FAILED IOK 2025-04-07
        $session_status = $session ? $this->get_vipps_checkout_status($order) : null;

        // If this is the case, there is no redirect, but the session is gone, so wipe the order and session.
        if (in_array($session_status, ['ERROR', 'EXPIRED', 'FAILED'])) {
            $this->log(sprintf(__("%1\$s session %2\$d is gone", 'woo-vipps'), Vipps::CheckoutName(), $order->get_id()), 'debug');
            $this->abandonVippsCheckoutOrder($order);
        }

        // This will return either a valid vipps session, nothing, or  redirect. 
        return(array('order'=>$order ? $order->get_id() : false, 'session'=>$session,  'redirect'=>$redirect));
    }

    // Returns HTML of any widgets for the Checkout page IOK 2025-05-13
    function vipps_ajax_get_widgets () {
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
        $order = $current_pending ? wc_get_order($current_pending) : null;
        if (!$order) return "";
        print $this->get_checkout_widgets($order);
        exit();
    }

    // This will, when visiting the cart or another checkout page and Vipps Mobilepay Checkout is active,
    // remove any coupons that can't be both in the cart and in our current Checkout order (thus invalidating the order at the same time)
    // but without the standard, now wrong error message produced in the cart for this. IOK 2025-05-15
    public function prettily_cleanup_coupons_in_cart($silent=false) {
        $cart = WC()->cart;
        foreach ( $cart->get_applied_coupons() as $code ) {
            $coupon = new WC_Coupon( $code );
            if ( ! $coupon->is_valid() ) {
                if (!$silent) {
                    $msg = sprintf(__("Your coupon code %s has been removed from your cart and your Checkout session has ended. You can add the code again either here or on the Checkout page", 'woo-vipps'), $code); 
                    // Will only run in the legacy non-gutenberg cart
                    wc_add_notice($msg, 'notice');
                }
                $cart->remove_coupon( $code );
            }
        }
    }

    // This runs very early, in template-redirect; so we can add notices to the cart page. IOK 2025-05-15
    // If coupons are added in Checkout after the order has been created, we need to change the error message 
    // in the Cart when the coupon is noticed as invalid there and removed. IOK 2025-05-15
    public function handle_coupon_invalidation_in_checkout() {
        global $post;
        if ($post && is_page()) { 
            $gw = WC_Gateway_Vipps::instance();
            $active =  (wc_coupons_enabled() && $gw->get_option('vipps_checkout_enabled') == 'yes' &&  $gw->get_option('checkout_widget_coupon') === 'yes');
            if ($active)  {
                if (has_block("woocommerce/cart")) {
                    $this->prettily_cleanup_coupons_in_cart();
                } else {
                    // This is for the old shortcode-based cart; doing the remove several times is safe. IOK 2025-05-15
                    // Then add a new one that adds a different message, also reporting that the vipps session is gone
                    // Remove the standard validation code which reports an error
                    remove_action('woocommerce_check_cart_items', array(WC()->cart, 'check_cart_coupons'), 1);
                    add_action('woocommerce_check_cart_items', array($this, 'prettily_cleanup_coupons_in_cart'), 1);
                }
            }
        }
    }

    // Define handlers for some default widgets (if active etc). IOK 2025-05-13
    public function add_widget_callback_actions () {
        add_filter('woo_vipps_checkout_callback_actions', function ($filters) {
            $filters['submitnotes'] = function ($action, $order) {
                $notes = isset($_REQUEST['callbackdata']['notes']) ? trim($_REQUEST['callbackdata']['notes']) : '';

                // First delete latest customer order note if exists. LP 2025-05-14
                $order_notes = $order->get_customer_order_notes();
                $deleted = 0;
                if ($order_notes) {
                    $latest_note = $order_notes[0];
                    if (is_a($latest_note, 'WP_Comment')) {
                        $deleted = wc_delete_order_note($latest_note->comment_ID);
                    } else {
                        error_log('Latest customer order note in checkout widget was not a WP_Comment, but a ' . get_class($latest_note));
                    }
                }
                $order->set_customer_note(sanitize_text_field($notes));
                $order->save();

                // Disable the email that gets sent on new order notes. IOK 2025-05-14 
                add_filter('woocommerce_mail_callback', function ($mailer, $mailclass) {
                        return '__return_true';
                }, 999, 2);


                // Add new note. LP 2025-05-14
                if ($notes) {
                    $order->add_order_note($notes, 1, true);
                    return 1;
                }
                return 0;
            };

            $filters['submitcoupon'] = function ($action, $order) {
                $code = isset($_REQUEST['callbackdata']['code']) ? trim($_REQUEST['callbackdata']['code']) : '';

                if ($code) {
                    add_filter('woocommerce_add_success', function ($message) { return ""; });
                    add_filter('woocommerce_add_error', function ($message) { return ""; });
                    add_filter('woocommerce_add_notice', function ($message) { return ""; });

                    if (WC()->cart) {
                      $ok = WC()->cart->apply_coupon($code);
                      if (!$ok || is_wp_error($ok)) {
                        // IOK FIXME GET ACTUAL ERROR HERE
                        throw (new Exception("Failed to apply coupon code $code"));
                      }
                    }

                    $res = $order->apply_coupon($code);
                    if (is_wp_error($res)) throw (new Exception("Failed to apply coupon code $code"));

                    return 1;
                }
                return 0;
            };

            $filters['removecoupon'] = function ($action, $order) {
                $code = isset($_REQUEST['callbackdata']['code']) ? trim($_REQUEST['callbackdata']['code']) : '';
                if ($code) {
                    // Ensure the cart too loses the coupon
                    if (WC()->cart) {
                      $ok = WC()->cart->remove_coupon($code);
                      // can't do much if this fails so
                    }
                    $res = $order->remove_coupon($code);

                    if ($res) return 1;
                }
                return 1; // just do it ? if errors happen here, the coupon *gets stuck*? FIXME IOK 2025-05-15
            };
            return $filters;
        });
    }


    // Add premade widgets depending on users settings. LP 2025-05-14
    // For now, coupon code widget and order notes widget.
    function maybe_add_widgets() {
        // Premade widget: coupon code. LP 2025-05-08
        $widgets = [];
        $use_widget_coupon = wc_coupons_enabled() && $this->gateway()->get_option('checkout_widget_coupon') === 'yes';

        // Premade widget: order note. LP 2025-05-12
        $use_widget_ordernotes = $this->gateway()->get_option('checkout_widget_ordernotes') === 'yes';
        if ($use_widget_coupon || $use_widget_ordernotes) {
            add_filter('woo_vipps_checkout_widgets', function ($widgets) use ($use_widget_coupon, $use_widget_ordernotes) {
                if ($use_widget_coupon) {
                    $widgets[] = [
                        'title' => __('Coupon code', 'woo-vipps'),
                        'id' => 'vipps_checkout_widget_coupon',
                        'class' => 'vipps_checkout_widget_premade',
                        'callback' => function($order) {?>
                        <div id="vipps_checkout_widget_coupon_active_codes_container" style="display:none;">
                            Active codes
                            <div id="vipps_checkout_widget_coupon_active_codes_container_codes">
                            <?php 
                            if ($order):
                                foreach ($order->get_coupon_codes() as $code):?>
                                    <div class="vipps_checkout_widget_coupon_active_code_box" id="vipps_checkout_widget_coupon_active_code_<?php echo $code;?>">
                                        <span class="vipps_checkout_widget_coupon_active_code"><?php echo $code;?></span>
                                        <span class="vipps_checkout_widget_coupon_delete">✕</span>
                                    </div>
                                <?php endforeach; endif;?>
                        </div>
                        </div>
                        <form id="vipps_checkout_widget_coupon_form">
                            <label for="vipps_checkout_widget_coupon_code" class="vipps_checkout_widget_small"><?php echo __('Enter your code', 'woo-vipps')?></label>
                            <span id="vipps_checkout_widget_coupon_error" class="vipps_checkout_widget_error" style="display:none;"><?php echo __('Invalid coupon code', 'woo-vipps') ?></span>
                            <span id="vipps_checkout_widget_coupon_delete_error" class="vipps_checkout_widget_error" style="display:none;"><?php echo __('Could not remove coupon', 'woo-vipps') ?></span>
                            <span id="vipps_checkout_widget_coupon_success" class="vipps_checkout_widget_success" style="display:none;"><?php echo __('Coupon code added!', 'woo-vipps') ?></span>
                            <input required id="vipps_checkout_widget_coupon_code" class="vipps_checkout_widget_input" type="text" name="code"/>
                            <button type="submit" class="vippspurple2 vipps_checkout_widget_button"><?php echo __('Add', 'woo-vipps')?></button>
                        </form>
                        <?php
                        }
                    ];
                }
                if ($use_widget_ordernotes) {
                    $widgets[] = [
                        'title' => __('Order notes', 'woo-vipps'),
                        'id' => 'vipps_checkout_widget_ordernotes',
                        'class' => 'vipps_checkout_widget_premade',
                        'callback' => function($order) { ?>
                    <form id="vipps_checkout_widget_ordernotes_form">
                        <div for="vipps_checkout_widget_ordernotes_input" class="vipps_checkout_widget_info"><?php echo __('Is there anything you wish to inform the store about? Include it here', 'woo-vipps')?></div>
                        <label for="vipps_checkout_widget_ordernotes_input" class="vipps_checkout_widget_small"><?php echo __('Notes', 'woo-vipps')?></label>
                        <span id="vipps_checkout_widget_ordernotes_error" class="vipps_checkout_widget_error" style="display:none;"><?php echo __('Something went wrong', 'woo-vipps') ?></span>
                        <span id="vipps_checkout_widget_ordernotes_success" class="vipps_checkout_widget_success" style="display:none;"><?php echo __('Saved', 'woo-vipps') ?></span>
                        <input id="vipps_checkout_widget_ordernotes_input" class="vipps_checkout_widget_input" type="text" name="notes" value="<?php if ($order) {
                            $order_notes = $order->get_customer_order_notes();
                            if ($order_notes) {
                                $latest_note = $order_notes[0];
                                if (is_a($latest_note, 'WP_Comment')) echo $latest_note->comment_content;
                                else error_log('Latest customer order note in checkout widget was not a WP_Comment, but a ' . get_class($latest_note));
                            }
                        } ?>"/>
                        <button type="submit" class="vippspurple2 vipps_checkout_widget_button"><?php echo __('Save', 'woo-vipps')?></button>
                    </form>
                    <?php
                        }
                    ];
                }
                return $widgets;
            });
        }

        return $widgets;
    }

    // This will display widgets like coupon codes, order notes etc on the Vipps Checkout page IOK 2025-05-02
    function get_checkout_widgets($order) {
        // Array of tables of [title, id, callback, class].
        // $default_widgets = $this->get_checkout_default_widgets($order);
        $this->maybe_add_widgets();

        // NB: We may not have an order at this point. IOK 2025-05-02
        $widgets = apply_filters('woo_vipps_checkout_widgets', [], $order);

        if (empty($widgets)) return "";
        ob_start();
        echo "<div class='vipps_checkout_widget_wrapper' style='display:none;'>";
        foreach ($widgets as $widget) {
           $id = $widget['id'] ?? "";
           $title = $widget['title'] ?? "";
           $class = $widget['class'] ?? "";
           $callback = $widget['callback'] ?? "";

           if (!$title || !$callback) continue;

           $idattr = $id ? "id='" . esc_attr($id) . "'" : "";
           $classattr = "class='vipps_checkout_widget" . ($class ? " " . esc_attr($class) : "") . "'";
           echo "<div $idattr $classattr>";
           echo "<div class='vipps_checkout_widget_title accordion'>" . esc_html($title) . "<span class='vipps_checkout_widget_icon'></span></div>";
           echo "<div class='vipps_checkout_body'>";
           call_user_func($callback, $order);
           echo "</div>";
           echo "</div>";
        }
        echo "</div>";
        $res = ob_get_clean();

        return $res;
    }

    function vipps_checkout_shortcode ($atts, $content) {
        // No point in expanding this unless we are actually doing the checkout. IOK 2021-09-03
        if (is_admin()) return;
        if (wp_doing_ajax()) return;
        if (defined('REST_REQUEST') && REST_REQUEST ) return;
        wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
        add_filter('woo_vipps_is_vipps_checkout', '__return_true');

        // Defer to the normal code for endpoints IOK 2022-12-09
        if (is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' )) {
           return do_shortcode("[woocommerce_checkout]");
        } 

        if (!WC()->cart ||  WC()->cart->is_empty() ) {
            $this->abandonVippsCheckoutOrder(false);
            ob_start();
            wc_get_template( 'cart/cart-empty.php' );
            return ob_get_clean();
        }

        WC()->session->set( 'chosen_payment_method', 'vipps'); // This is to stop KCO from trying to replace Vipps Checkout with KCO and failing. IOK 2024-05-13

        // Previously registered, now enqueue this script which should then appear in the footer.
        // Then call a hook for people adding custom javascript. This needs to be moved to template redirect. IOK 2025-06-02
        wp_enqueue_script('vipps-checkout');
        do_action('woo_vipps_checkout_enqueue_scripts');

        do_action('vipps_checkout_before_get_session');

        // We need to be able to check if we still have a live, good session, in which case
        // we can open the iframe directly. Otherwise, the form we are going to output will 
        // create the iframe after a button press which will create a new order.
        $sessioninfo = $this->vipps_checkout_current_pending_session();

        $out = ""; // Start generating output already to make debugging easier

        // This is the current pending order id, if it exists. Will be used to restart orders etc . IOK 2023-08-15 FIXME
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;

        if ($sessioninfo['redirect']) {
           // This is always either the thankyou page or home_url()  IOK 2021-09-03
           $redir = json_encode($sessioninfo['redirect']);
           $out .= "<script>window.location.replace($redir);</script>";
           return $out;
        }

        // Now the normal case.
        $errortext = apply_filters('woo_vipps_checkout_error', __('An error has occured - please reload the page to restart your transaction, or return to the shop', 'woo-vipps'));
        $expiretext = apply_filters('woo_vipps_checkout_error', __('Your session has expired - please reload the page to restart, or return to the shop', 'woo-vipps')); 

        $out .= Vipps::instance()->spinner();

        if (!$sessioninfo['session']) {
           $out .= "<div style='visibility:hidden' class='vipps_checkout_startdiv'>";
           $out .= "<h2>" . sprintf(__('Press the button to complete your order with %1$s!', 'woo-vipps'), Vipps::instance()->get_payment_method_name()) . "</h2>";
           $out .= '<div class="vipps_checkout_button_wrapper" ><button type="submit" class="button vipps_checkout_button vippsorange" value="1">' . sprintf(__('%1$s', 'woo-vipps'), Vipps::CheckoutName()) . '</button></div>';
           $out .= "</div>";
        }

        // If we have an actual live session right now, add it to the page on load. Otherwise, the session will be started using ajax after the page loads (and is visible)
        if ($sessioninfo['session']) {
            $token = $sessioninfo['session']['token'];      // From Vipps
            $src = $sessioninfo['session']['checkoutFrontendUrl'];  // From Vipps
            $out .= "<script>VippsSessionState = " . json_encode(array('token'=>$token, 'checkoutFrontendUrl'=>$src)) . ";</script>\n";
        } else {
            $out .= "<script>VippsSessionState = null;</script>\n";
        }

        // Mount point for widgets. IOK 2025-05-13
        // starts hidden. is shown when vipps checkout loads successfully. LP 2025-05-12
        $out .= "<div id='vippscheckoutframe'>";
        $out .= "<div id='vipps_checkout_widget_mount'></div>";

        $out .= "</div>";
        $out .= "<div style='display:none' id='vippscheckouterror'><p>$errortext</p></div>";
        $out .= "<div style='display:none' id='vippscheckoutexpired'><p>$expiretext</p></div>";


        // We impersonate the woocommerce-checkout form here mainly to work with the Pixel Your Site plugin IOK 2022-11-24
        $classlist = apply_filters("woo_vipps_express_checkout_form_classes", "woocommerce-checkout");
        $out .= "<form id='vippsdata' class='" . esc_attr($classlist) . "'>";
        $out .= "<input type='hidden' id='vippsorderid' name='_vippsorder' value='" . intval($current_pending) . "' />";
        // And this is for the order attribution feature of Woo 8.5 IOK 2024-01-09
        if (WC_Gateway_Vipps::instance()->get_option('vippsorderattribution') == 'yes') {
            $out .= '<input type="hidden" id="vippsorderattribution" value="1" />';
            ob_start();
            do_action( 'woocommerce_after_order_notes');
            $out .= ob_get_clean();
        }
        $out .= wp_nonce_field('do_vipps_checkout','vipps_checkout_sec',1,false); 
        $out .= "</form>";

        return $out;
    }


    public function cart_changed() {
        // Don't do this if we are changing the cart in a Vipps Checkout callback. IOK 2025-05-15
        if (apply_filters('woo_vipps_is_checkout_callback', false)) {
           return;
        }
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
        $order = $current_pending ? wc_get_order($current_pending) : null;
        if (!$order) return;
        $this->log(sprintf(__("%1\$s: cart changed while session %2\$d in progress - now cancelled", 'woo-vipps'), Vipps::CheckoutName(), $order->get_id()), 'debug');
        $this->abandonVippsCheckoutOrder($order);
    } 
    
    public function abandonVippsCheckoutOrder($order) {

        if (WC()->session) {
            WC()->session->set('vipps_checkout_current_pending',0);
            WC()->session->set('vipps_address_hash', false);
        }

        if (is_a($order, 'WC_Order') && $order->get_status() == 'pending') {
            // We want to kill orders that have failed, or that the user has abandoned. To do this,
            // we must ensure that no race or other mechanism kills the order while or after being paid.
            // if order is in the process of being finalized, don't kill it
            if (Vipps::instance()->isLocked($order)) {
                return false;
            }
            // Get it again to ensure we have all the info, and check status again
            clean_post_cache($order->get_id());
            $order = wc_get_order($order->get_id());
            if ($order->get_status() != 'pending') return false;

            // And to be extra sure, check status at vipps
            $session = $order->get_meta('_vipps_checkout_session');
            if (!$session) return false;

            try {
                $polldata = $this->gateway()->api->checkout_get_session_info($order);
                $sessionState = (!empty($polldata) && is_array($polldata) && isset($polldata['sessionState'])) ? $polldata['sessionState'] : "";
                $this->log("Checking Checkout status on cart/order change for " . $order->get_id() . " $sessionState ", 'debug');
                if ($sessionState == 'PaymentSuccessful' || $sessionState == 'PaymentInitiated') {
                    // If we have started payment, we do not kill the order.
                    $this->log("Checkout payment started - cannot cancel for " . $order->get_id(), 'debug');
                    return false;
                }
            } catch (Exception $e) {
                $this->log(sprintf(__('Could not get Checkout status for order %1$s in progress while cancelling', 'woo-vipps'), $order->get_id()), 'debug');
            }


            // NB: This can *potentially* be revived by a callback!
            $this->log(sprintf(__('Cancelling Checkout order because order changed: %1$s', 'woo-vipps'), $order->get_id()), 'debug');
            $order->set_status('cancelled', __("Order specification changed - this order abandoned by customer in Checkout  ", 'woo-vipps'), false);
            // Also mark for deletion and remove stored session
            $order->delete_meta_data('_vipps_checkout_session');
            $order->update_meta_data('_vipps_delendum',1);
            $order->save();
        }
    }
    
    public function get_vipps_checkout_status($order) {
        $status = $this->gateway()->api->checkout_get_session_info($order);
        return $status;
    }


    public function maybe_override_checkout_page_id ($id) {
        //  Only do this if Vipps Checkout was ever activated
        $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);
        if (!$vipps_checkout_activated) return $id;

         // The gutenberg block  (and other pages) calls the checkout-page-id function *a lot* so let's just check once
        if ($this->payid) return $this->payid;

        // If we are on a checkout page, don't go other places please
        if (is_page()){
            global $post; 
            // The unfiltered checkout page from woo
            if ($post && $post->ID == get_option( 'woocommerce_checkout_page_id' )) {
                $this->payid = $id;
                return $id;
            }
            // any other page with a gutenberg checkout block. We don't need to test for the shortcode, that works fine.
            if ($post && has_block( 'woocommerce/checkout', $post->post_content) ) {
                $this->payid = $id;
                return $id;
            }
            // If this is "pay for order", also don't do anything.
            $orderid = absint(get_query_var( 'order-pay'));
            if ($orderid) {
                $this->payid = $id;
                return $id;
            }
        }

        // Else, if Vipps Checkout is enabled, can be used etc, use that.
        $checkoutid = $this->gateway()->vipps_checkout_available();
        if ($checkoutid) {
            $this->payid = $checkoutid;
            return $checkoutid;
        }

        return $id;
    }

    public function woocommerce_loaded () {
        # This implements the Vipps Checkout replacement checkout page for those that wants to use that, by filtering the checkout page id.
        add_filter('woocommerce_get_checkout_page_id', array($this, 'maybe_override_checkout_page_id'), 10, 1); 

        // This is for the 'other payment method' thing in Vipps Checkout - we store address info
        // in session. IOK 2024-05-13
        add_filter('woocommerce_checkout_fields', function ($fields) {
            if (empty(WC()->session)) return $fields;
            $possibly_address =  WC()->session->get('vc_address');

            if (!$possibly_address) return $fields;
            WC()->session->set('vc_address', null);

            foreach($fields['billing'] as $key => &$bdata) {
                $v = trim($possibly_address[$key] ?? "");
                if ($v) {
                    $bdata['default'] = $v;
                }
            }
            foreach($fields['shipping'] as $key => &$sdata) {
                $v = trim($possibly_address[$key] ?? "");
                if ($v) {
                    $sdata['default'] = $v;
                }
            }
            return $fields;
        });

    }


    public function woocommerce_settings_pages ($settings) {
        $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);
        if (!$vipps_checkout_activated) return $settings;
        $i = -1;
        foreach($settings as $entry) {
            $i++;
            if ($entry['type'] == 'sectionend' && $entry['id'] == 'advanced_page_options') {
                break;
            }
        }
        if ($i > 0) {

            $vippspagesettings = array(
                    array(
                        'title'    => sprintf(__( '%1$s Page', 'woo-vipps' ), Vipps::CheckoutName()),
                        'desc'     => sprintf(__('This page is used for the alternative %1$s page, which you can choose to use instead of the normal WooCommerce checkout page. ', 'woo-vipps'), Vipps::CheckoutName()) .  sprintf( __( 'Page contents: [%1$s]', 'woocommerce' ), 'vipps_checkout') ,
                        'id'       => 'woocommerce_vipps_checkout_page_id',
                        'type'     => 'single_select_page_with_search',
                        'default'  => '',
                        'class'    => 'wc-page-search',
                        'css'      => 'min-width:300px;',
                        'args'     => array(
                            'exclude' =>
                            array(
                                wc_get_page_id( 'myaccount' ),
                                ),
                            ),
                        'desc_tip' => true,
                        'autoload' => false,
                        ));
            array_splice($settings, $i, 0, $vippspagesettings);
        }

        return $settings;
    }


    // Translate from the Express Checkout shipping method format to the Vipps Checkout shipping
    // format, which is slightly different. The ratemap maps from a method key to its WC_Shipping_Rate, and the method map does
    // the same for WP_Shipping_Method.
    // IOK 2025-05-07 Also treat PickupLocation specially. We'll return at most one of these, and if there are more than one, we will add the locations available as
    // metadata.
    public function format_shipping_methods ($return, $ratemap, $methodmap, $order) {
        $translated = array();
        $currency = get_woocommerce_currency();
        $pickupLocation = null; // if we have a pickup_location rate, set this to be the first one. IOK 2025-05-07

        foreach ($return['shippingDetails']  as $m) {
            $m2 = array();

            $m2['isDefault'] = (bool) (($m['isDefault']=='Y') ? true : false); // type bool here, but not in the other api
            $m2['priority'] = $m['priority'];
            $m2['amount'] = array(
                'value' => round(100*$m['shippingCost']), // Unlike eComm, this uses cents
                'currency' => $currency // May want to use the orders' currency instead here, since it exists.
            );
            $m2['brand'] = "OTHER";
            $m2['title'] = $m['shippingMethod']; 
            $m2['id'] = $m['shippingMethodId'];

            $rate = $ratemap[$m2['id']];
            $shipping_method = $methodmap[$m2['id']];

            // If we have pickup_location-s, only use the first one. IOK 2025-05-07
            if ($rate->method_id == 'pickup_location') {
                if (!$pickupLocation) {
                    $pickupLocation = &$m2;
                } else {
                    continue; 
                }
            }
             

            // Some data must be visible in the Order screen, so add meta data, also, for dynamic pricing check that free shipping hasn't been reached
            $meta = $rate->get_meta_data();

            // The description is normally only stored only in the shipping method
            if ($shipping_method) {
                // Support dynamic cost alongside free shipping using the new api where NULL is dynamic pricing 2023-07-17 
                if  (isset($shipping_method->instance_settings['dynamic_cost']) && $shipping_method->instance_settings['dynamic_cost'] == 'yes') {
                    if (!isset($meta['free_shipping']) || !$meta['free_shipping']) {
                        $m2['amount'] = null;
                    }
                }
                $m2['description'] = $shipping_method->get_option('description', '');
            } else {
                $m2['description'] = "";
            }
            

            // Allow shipping methods to add pickup points data IOK 2025-04-08
            $delivery = [];
            $pickup_points = apply_filters('woo_vipps_shipping_method_pickup_points', [], $rate, $shipping_method, $order);
            if ($pickup_points) {
                $filtered = [];
                foreach($pickup_points as $point) {
                    $ok = true;
                    $entry = [];
                    foreach(['address', 'city', 'country', 'id', 'name', 'postalCode'] as $key) {
                        if (!isset($point[$key])) {
                            $this->log(__('Cannot add pickup point: A pickup point needs to have keys id, name, address, city, postalCode and country: ', 'woo-vipps') . print_r($point, true), 'error');
                            $ok = false;
                            break;
                        } else {
                            $entry[$key] = $point[$key];
                        }
                    }
                    foreach(['openingHours', 'leadTime'] as $key) {
                        if (isset($point[$key])) {
                            $entry[$key] = $point[$key];
                        }
                    }

                    if ($ok && !empty($entry)) {
                        $filtered[] = $entry;
                    }
                }
                $delivery['pickupPoints'] = $filtered;
                $m2['type'] = 'PICKUP_POINT';

                // Remove name of location for PickupLocation if we do have choices. IOK 2025-05-07
                if ($m2 == $pickupLocation && count($filtered) > 1) {
                   $m2['title'] = $shipping_method->title;
                }


            }

            // Timeslots. This is for home delivery options, should have values id (string), date (date), start (time), end (time).
            // IOK 2025-04-10
            $timeslots = apply_filters('woo_vipps_shipping_method_timeslots', [], $rate, $shipping_method, $order);
            if (!empty($timeslots)) {
                $filtered = [];
                foreach($timeslots as $timeslot) {
                    $entry = [];
                    $ok = true;
                    foreach(['id', 'date', 'start', 'end'] as $key) {
                        if (!isset($timeslot[$key])) {
                            $this->log(__('Cannot add timeslot: A timeslot needs to have keys id, date, start and end: ', 'woo-vipps') . print_r($timeslot, true), 'error');
                            $ok = false;
                            break;
                        } else {
                            $entry[$key] = $timeslot[$key];
                        }
                    }
                    if ($ok && !empty($entry)) {
                        $filtered[] = $entry;
                    }
                }
                $delivery['timeslots']=$filtered;
                $m2['type'] = 'HOME_DELIVERY';
            }
            
            // add leadTime data to "Mailbox" types
            $leadTime = apply_filters('woo_vipps_shipping_method_lead_time', null, $rate, $shipping_method, $order);
            if (!empty($leadTime)) {
                $entry = [];
                $ok = true;
                foreach(['earliest', 'latest'] as $key) {
                    if (!isset($leadTime[$key])) {
                        $ok = false; break;
                    }
                    $entry[$key] = $leadTime[$key];
                }
                if ($ok && !empty($entry)) {
                    $delivery['leadTime'] = $entry;
                }
            }

            if (!empty($delivery)) {
               $m2['delivery'] = $delivery;
            }

            if (isset($meta['brand'])) {
                $m2['brand'] = $meta['brand'];
            } else {
                // specialcase some known methods so they get brands, and put the label into the description
                if ($shipping_method && is_a($shipping_method, 'WC_Shipping_Method') && get_class($shipping_method) == 'WC_Shipping_Method_Bring_Pro') {
                    $m2['brand'] = "POSTEN";
                    $m2['description'] = $rate->get_label();
                }
                $m2['brand'] = apply_filters('woo_vipps_shipping_method_brand', $m2['brand'],$shipping_method, $rate);
            }

            if ($m2['brand'] != "OTHER" && isset($meta['type'])) {
                $m2['type'] = $meta['type'];
            }

            // Old filter kept for backwards compatibility
            $m2['description'] = apply_filters('woo_vipps_shipping_method_description', $m2['description'], $rate, $shipping_method);
            $translated[] = $m2;
        }

        $return['shippingDetails'] = $translated;
        unset($return['addressId']); // Not used it seems for checkout
        unset($return['orderId']);

        $return = apply_filters('woo_vipps_checkout_json_shipping_methods', $return, $order);
        return $return;
    }



}
