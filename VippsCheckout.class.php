<?php
/*
   This class is for hooks and plugin managent for the Vipps Checkout checkout-replacement feature, and is instantiated as a singleton.
   IOK 2023-05-16
   For WP-specific interactions.


This file is part of the plugin Checkout with Vipps for WooCommerce
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
    function __construct() {
    }

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
        add_action( 'plugins_loaded', array($VippsCheckout,'plugins_loaded'));
        add_action( 'woocommerce_loaded', array($VippsCheckout,'woocommerce_loaded'));
        add_action( 'template_redirect', array($VippsCheckout,'template_redirect'));
    }

    public function template_redirect () {
        // This will normally be on the "checkout" page which shouldn't be cached, but just in case, add
        // nocache headres to any page that uses this shortcode. IOK 2021-08-26
        // Furthermore, sometimes woocommerce calls is_checkout() *before* woocommerce is loaded, so
        if ($post && is_page() &&  has_shortcode($post->post_content, 'vipps_checkout')) {
            add_filter('woocommerce_is_checkout', '__return_true');
            add_filter('body_class', function ($classes) {
                    $classes[] = 'vipps-checkout';
                    $classes[] = 'woocommerce-checkout'; // Required by Pixel Your Site IOK 2022-11-24
                    return apply_filters('woo_vipps_checkout_body_class', $classes);
                    });
            /* Suppress the title for this page, but on the front page only IOK 2023-01-27 (by request from Vipps) */
            $post_to_hide_title_for = $post->ID;
            add_filter('the_title', function ($title, $postid) use ($post_to_hide_title_for) {
                    if (!is_admin() && $postid ==  $post_to_hide_title_for && is_singular()  && in_the_loop()) {
                    $title = "";
                    }
                    return $title;
                    }, 10, 2);
            wc_nocache_headers();
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

        // Prevent previews and prefetches of the Vipps Checkout page starting and creating orders
        add_action('wp_head', array($this, 'wp_head'));

        // The Vipps Checkout feature which overrides the normal checkout process uses a shortcode
        add_shortcode('vipps_checkout', array($this, 'vipps_checkout_shortcode'));

        // Ensure we remove the current session on the thank you page (too).
        add_action('woocommerce_thankyou_vipps', function () {
            WC()->session->set('current_vipps_session', false);
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
        wp_register_script('vipps-checkout',plugins_url('js/vipps-checkout.js',__FILE__),array('vipps-gw','vipps-sdk'),filemtime(dirname(__FILE__) . "/js/vipps-checkout.js"), 'true');
    }


    public function log ($what,$type='info') {
        $logger = wc_get_logger();
        $context = array('source'=>'woo-vipps');
        $logger->log($type,$what,$context);
    }


    // This is used by the Vipps Checkout page to start the Vipps checkout session, including 
    // creating the partial order IOK 2021-09-03
    public function vipps_ajax_checkout_start_session () {
        check_ajax_referer('do_vipps_checkout','vipps_checkout_sec');
        $url = ""; 
        $redir = "";
        $token = "";

        // First, check that we haven't already done this like in another window or something:
        $session = $this->vipps_checkout_current_pending_session();
        if (isset($sessioninfo['redirect'])) {
            $redirect = $sessioninfo['redirect'];
        }
        if (isset($sessioninfo['session']) && isset($sessioninfo['session']['token'])) {
            $token = $sessioninfo['session']['token'];
            $src = $sessioninfo['session']['checkoutFrontendUrl'];
            $url = $src; 
        }
        // And if we do, just return what we have. NB: This *should not happen*.
        if ($url || $redir) {
            return wp_send_json_success(array('ok'=>1, 'msg'=>'session started', 'src'=>$url, 'redirect'=>$redir, 'token'=>$token));
        }

        // Otherwise, create an order and start a new session
        $current_vipps_session = null;
        $current_pending = 0;
        $current_authtoken = "";
        $limited_session = "";
        try {
                WC()->session->set('current_vipps_session', null);
                $current_pending = $this->gateway()->create_partial_order('ischeckout');
                if ($current_pending) {
                    $order = wc_get_order($current_pending);
                    $order->update_meta_data('_vipps_checkout', true);
                    $current_authtoken = $this->gateway()->generate_authtoken();
                    $limited_session = $this->gateway()->generate_authtoken();
                    WC()->session->set('vipps_checkout_authtoken', $current_authtoken);
                    $order->update_meta_data('_vipps_authtoken',wp_hash_password($current_authtoken));
                    $order->update_meta_data('_vipps_limited_session',wp_hash_password($limited_session));
                    $order->save();
                    WC()->session->set('vipps_checkout_current_pending', $current_pending);

                    try {
                        Vipps::instance()->maybe_add_static_shipping($this->gateway(),$order->get_id(), 'vippscheckout');
                    } catch (Exception $e) {
                        // In this case, we just have to continue.
                        $this->log(sprintf(__("Error calculating static shipping for order %s", 'woo-vipps'), $order->get_id()), 'error');
                        $this->log($e->getMessage(),'error');
                    }
                    $this->gateway()->save_session_in_order($order);
                    do_action('woo_vipps_checkout_order_created', $order);
                } else {
                    throw new Exception(__('Unknown error creating Vipps Checkout partial order', 'woo-vipps'));
                }
        } catch (Exception $e) {
            return wp_send_json_success(array('ok'=>0, 'msg'=>$e->getMessage(), 'src'=>'', 'redirect'=>''));
        }

        // Ensure we get the latest updates to the order too IOK 2021-10-22
        $order = wc_get_order($current_pending);
        if (is_user_logged_in()) {
            $phone = get_user_meta(get_current_user_id(), 'billing_phone', true);
        }
        $order_id = $order->get_id();
        $requestid = 1;
        $returnurl = Vipps::instance()->payment_return_url();
        $returnurl = add_query_arg('s',$limited_session,$returnurl);
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
            $phonenr = $customer->get_billing_phone();
            $phonenr = preg_replace("![^0-9]!", "",  $phonenr);
            $phonenr = preg_replace("!^0+!", "", $phonenr);
            if (strlen($phonenr) == 8 && $customerinfo['country'] == 'NO') {
                $phonenr = '47' + $phonenr;
            }
            if (preg_match("/47\d{8}/", $phonenr) && $customerinfo['country'] == 'NO') {
              $phone = $phonenr;
            }

            $customerinfo['phoneNumber'] = $phone;
        }

        $keys = ['firstName', 'lastName', 'streetAddress', 'postalCode', 'country', 'phoneNumber'];
        foreach($keys as $k) {
            if (empty($customerinfo[$k])) {
                $customerinfo = array(); break;
            }
        }
        $customerinfo = apply_filters('woo_vipps_customerinfo', $customerinfo, $order);

        try {
            $current_vipps_session = $this->gateway()->api->initiate_checkout($customerinfo,$order,$returnurl,$current_authtoken,$requestid); 
            if ($current_vipps_session) {
                $order = wc_get_order($current_pending);
                $order->update_meta_data('_vipps_init_timestamp',time());
                $order->update_meta_data('_vipps_status','INITIATE');
                $order->update_meta_data('_vipps_checkout_poll', $current_vipps_session['pollingUrl']);
                $order->add_order_note(__('Vipps Checkout payment initiated','woo-vipps'));
                $order->add_order_note(__('Customer passed to Vipps Checkout','woo-vipps'));
                $order->save();
                WC()->session->set('current_vipps_session', $current_vipps_session);
                $token = $current_vipps_session['token'];
                $src = $current_vipps_session['checkoutFrontendUrl'];
                $url = $src;
            } else {
                    throw new Exception(__('Unknown error creating Vipps Checkout session', 'woo-vipps'));
            }
        } catch (Exception $e) {
                $this->log(sprintf(__("Could not initiate Vipps Checkout session: %s", 'woo-vipps'), $e->getMessage()), 'ERROR');
                return wp_send_json_success(array('ok'=>0, 'msg'=>$e->getMessage(), 'src'=>'', 'redirect'=>''));
        }
        if ($url || $redir) {
            return wp_send_json_success(array('ok'=>1, 'msg'=>'session started', 'src'=>$url, 'redirect'=>$redir, 'token'=>$token));
        } else { 
            return wp_send_json_success(array('ok'=>0, 'msg'=>__('Could not start Vipps Checkout session'),'src'=>$url, 'redirect'=>$redir));
        }
    }

    // Check the current status of the current Checkout session for the user.
    public function vipps_ajax_checkout_poll_session () {
        check_ajax_referer('do_vipps_checkout','vipps_checkout_sec');
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
        $order = $current_pending ? wc_get_order($current_pending) : null;
        $payment_status = $order ?  $this->gateway()->check_payment_status($order) : 'unknown';
        if (in_array($payment_status, ['authorized', 'complete'])) {
            $this->abandonVippsCheckoutOrder(false);
            return wp_send_json_success(array('msg'=>'completed', 'url' => $this->gateway()->get_return_url($order)));;
        }
        if ($payment_status == 'cancelled') {
            $this->abandonVippsCheckoutOrder($order);
            return wp_send_json_error(array('msg'=>'FAILED', 'url'=>home_url()));
        }

        $current_vipps_session = $order ? WC()->session->get('current_vipps_session') : false;
        if (!$current_vipps_session) {
            WC()->session->set('current_vipps_session', false);
            WC()->session->set('vipps_address_hash', false);
            return wp_send_json_success(array('msg'=>'EXPIRED', 'url'=>false));
        }

        add_filter('woo_vipps_is_vipps_checkout', '__return_true');
        $status = $this->get_vipps_checkout_status($current_vipps_session); 

        $failed = $status == 'ERROR' || $status == 'EXPIRED' || $status == 'TERMINATED';
        $complete = false; // We no longer get informed about complete sessions; we only get this info when the order is wholly complete. IOK 2021-09-27

        // Disallow sessions that go on for too long.
        if (is_a($order, "WC_Order")) {
            $created = $order->get_date_created();
            $timestamp = 0;
            $now = time();
            try {
                $timestamp = $created->getTimestamp();
            } catch (Exception $e) {
                // PHP 8 gives ValueError for this, we'll use 0
            }
            $passed = $now - $timestamp;
            $minutes = ($passed / 60);
            // Expire after 50 minutes
            if ($minutes > 50) {
                $this->abandonVippsCheckoutOrder($order);
                return wp_send_json_success(array('msg'=>'EXPIRED', 'url'=>false));
            }
        }

        $ok   = !$failed;

        // Since we checked the payment status at Vipps directly above, we don't actaully have any extra information at this point.
        // We do know that the session is live and ongoing, but that's it.

        if ($failed) { 
            $msg = $status;
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
        $change = false;
        $vipps_address_hash =  WC()->session->get('vipps_address_hash');
        if ($ok && (isset($status['orderContactInformation']) || isset($status['orderShippingAddress'])))  {
            $serialized = sha1(json_encode(@$status['orderContactInformation']) . ':' . json_encode(@$status['orderShippingAddress']));
            if ($serialized != $vipps_address_hash) {
                $change = true;
                WC()->session->set('vipps_address_hash', $serialized);
            } 
        }
        if ($complete) $change = true;

        // IOK FIXME this is the actual status of the order when this is called, which will
        // include personalia only when continuing to payment

        if ($ok && $change && isset($status['billingDetails']))  {
            $contact = $status['billingDetails'];
            $order->set_billing_email($contact['email']);
            $order->set_billing_phone($contact['phoneNumber']);
            $order->set_billing_first_name($contact['firstName']);
            $order->set_billing_last_name($contact['lastName']);
            $order->set_billing_address_1($contact['streetAddress']);
            $order->set_billing_address_2("");
            $order->set_billing_city($contact['city']);
            $order->set_billing_postcode($contact['postalCode']);
            $order->set_billing_country($countrycode);
        }
        if ($ok &&  $change && isset($status['shippingDetails']))  {
            $contact = $status['shippingDetails'];
            $countrycode =  Vipps::instance()->country_to_code($contact['country']); // No longer neccessary IOK 2023-01-09
            $order->set_shipping_first_name($contact['firstName']);
            $order->set_shipping_last_name($contact['lastName']);
            $order->set_shipping_address_1($contact['streetAddress']);
            $order->set_shipping_address_2("");
            $order->set_shipping_city($contact['city']);
            $order->set_shipping_postcode($contact['postalCode']);
            $order->set_shipping_country($countrycode);

        }
        if ($change) $order->save();

        // IOK 2021-09-27 we no longer get informed when the order is complete, but if we did, this would handle it.
        if ($complete) {
            // Order is complete! Yay!
            $this->gateway()->update_vipps_payment_details($order);
            $this->gateway()->payment_complete($order);
            wp_send_json_success(array('msg'=>'completed','url' => $this->gateway()->get_return_url($order)));
            exit();
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
            // This will mostly just wipe the session.
            $this->abandonVippsCheckoutOrder($order);
            $redirect = home_url();
        }
        // Now if we don't have an order right now, we should not have a session either, so fix that
        if (!$order) {
            $this->abandonVippsCheckoutOrder(false);
        } 

        // Now check the current vipps session if it exist
        $current_vipps_session = $order ? WC()->session->get('current_vipps_session') : false;
        // A single word or array containing session data, containing token and frontendFrameUrl
        // ERROR EXPIRED FAILED
        $session_status = $current_vipps_session ? $this->get_vipps_checkout_status($current_vipps_session) : null;

        // If this is the case, there is no redirect, but the session is gone, so wipe the order and session.
        if (in_array($session_status, ['ERROR', 'EXPIRED', 'FAILED'])) {
            $this->abandonVippsCheckoutOrder($order);
        }

        // This will return either a valid vipps session, nothing, or  redirect. 
        return(array('order'=>$order ? $order->get_id() : false, 'session'=>$current_vipps_session,  'redirect'=>$redirect));
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
        // Previously registered, now enqueue this script which should then appear in the footer.
        wp_enqueue_script('vipps-checkout');

        do_action('vipps_checkout_before_get_session');

        // We need to be able to check if we still have a live, good session, in which case
        // we can open the iframe directly. Otherwise, the form we are going to output will 
        // create the iframe after a button press which will create a new order.
        $sessioninfo = $this->vipps_checkout_current_pending_session();
        $out = ""; // Start generating output already to make debugging easier

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
           $out .= "<h2>" . __('Press the button to complete your order with Vipps!', 'woo-vipps') . "</h2>";
           $out .= '<div class="vipps_checkout_button_wrapper" ><button type="submit" class="button vipps_checkout_button vippsorange" value="1">' . __('Vipps Checkout', 'woo-vipps') . '</button></div>';
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

        // Check that these exist etc
        $out .= "<div id='vippscheckoutframe'>";

        $out .= "</div>";
        $out .= "<div style='display:none' id='vippscheckouterror'><p>$errortext</p></div>";
        $out .= "<div style='display:none' id='vippscheckoutexpired'><p>$expiretext</p></div>";

        // We impersonate the woocommerce-checkout form here mainly to work with the Pixel Your Site plugin IOK 2022-11-24
        $classlist = apply_filters("woo_vipps_express_checkout_form_classes", "woocommerce-checkout");
        $out .= "<form id='vippsdata' class='" . esc_attr($classlist) . "'>";
        $out .= wp_nonce_field('do_vipps_checkout','vipps_checkout_sec',1,false); 
        $out .= "</form>";

        return $out;
    }


    public function cart_changed() {
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
        $order = $current_pending ? wc_get_order($current_pending) : null;
        if (!$order) return;
        $this->abandonVippsCheckoutOrder($order);
    } 
    
    public function abandonVippsCheckoutOrder($order) {
        if (WC()->session) {
            WC()->session->set('vipps_checkout_current_pending',0);
            WC()->session->set('current_vipps_session', null);
            WC()->session->set('vipps_address_hash', false);
        }

        if (is_a($order, 'WC_Order') && $order->get_status() == 'pending') {
            // NB: This can *potentially* be revived by a callback!
            $order->set_status('cancelled', __("Abandonded by customer", 'woo-vipps'), false);
            // Also mark for deletion
            $order->update_meta_data('_vipps_delendum',1);
            $order->save();
        }
    }
    
    public function get_vipps_checkout_status($session) {
        if ($session && isset($session['token'])) {
            $status = $this->gateway()->api->poll_checkout($session['pollingUrl']);
            return $status;
        }
    }

    public function woocommerce_loaded () {
        # This implements the Vipps Checkout replacement checkout page for those that wants to use that, by filtering the checkout page id.
        add_filter('woocommerce_get_checkout_page_id',  function ($id) {
                # Only do this if Vipps Checkout was ever activated
                $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);
                if (!$vipps_checkout_activated) return $id;

                # If Vipps Checkout is enabled, can be used etc, use that.
                $checkoutid = $this->gateway()->vipps_checkout_available();
                if ($checkoutid) {
                    return $checkoutid;
                }

                return $id;
        },10, 1);

    }

    public function plugins_loaded() {
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
                        'title'    => __( 'Vipps Checkout Page', 'woo-vipps' ),
                        'desc'     => __('This page is used for the alternative Vipps Checkout page, which you can choose to use instead of the normal WooCommerce checkout page. ', 'woo-vipps') .  sprintf( __( 'Page contents: [%s]', 'woocommerce' ), 'vipps_checkout') ,
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
    public function format_shipping_methods ($return, $ratemap, $methodmap) {
        $translated = array();
        $currency = get_woocommerce_currency();
        foreach ($return['shippingDetails']  as $m) {
            $m2 = array();

            $m2['isDefault'] = (bool) (($m['isDefault']=='Y') ? true : false); // type bool here, but not in the other api
            $m2['priority'] = $m['priority'];
            $m2['amount'] = array(
                'value' => round(100*$m['shippingCost']), // Unlike eComm, this uses cents
                'currency' => $currency // May want to use the orders' currency instead here, since it exists.
            );
            $m2['brand'] = "OTHER";
            $m2['title'] = $m['shippingMethod']; // Only for "other"
            $m2['id'] = $m['shippingMethodId'];

            $rate = $ratemap[$m2['id']];
            $shipping_method = $methodmap[$m2['id']];

            // The description is normally only stored only in the shipping method
            if ($shipping_method) {
                $m2['description'] = $shipping_method->get_option('description', '');
            } else {
                $m2['description'] = "";
            }

            // Some data must be visible in the Order screen, so add meta data
            $meta = $rate->get_meta_data();
            if (isset($meta['brand'])) {
                $m2['brand'] = $meta['brand'];
                unset($m2['title']);

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
        return $return;
    }



}
