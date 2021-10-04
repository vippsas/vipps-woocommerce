<?php
/*
   Delegate class for talking to Vipps, encapsulating all the low-level behaviour and mapping error codes to exceptions

This file is part of the plugin Checkout with Vipps for WooCommerce
Copyright (c) 2019 WP-Hosting AS

MIT License

Copyright (c) 2019 WP-Hosting AS

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
require_once(dirname(__FILE__) . "/VippsApi.class.php");

class WC_Gateway_Vipps extends WC_Payment_Gateway {
    public $form_fields = null;
    public $dev_form_fields = null;
    public $id = 'vipps';
    public $icon = ''; 
    public $has_fields = true;
    public $method_title = 'Vipps';
    public $title = 'Vipps';
    public $method_description = "";
    public $apiurl = null;
    public $testapiurl = null;
    public $api = null;
    public $supports = null;
    public $express_checkout_supported_product_types;

    public $captured_statuses;

    // Used to signal state to process_payment
    public $express_checkout = 0;
    // Used to sometimes use the standard checkout screen if using Vipps Checkout
    public $get_real_checkout_screen = false;

    private static $instance = null;  // This class uses the singleton pattern to make actions easier to handle

    // This returns the singleton instance of this class
    public static function instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
    } 
        

    public function __construct() {
        $this->testapiurl = 'https://apitest.vipps.no';
        $this->apiurl = 'https://api.vipps.no';

        $this->method_description = __('Offer Vipps as a payment method', 'woo-vipps');
        $this->method_title = __('Vipps','woo-vipps');
        $this->title = __('Vipps','woo-vipps');
        $this->icon = plugins_url('img/vipps_logo_rgb.png',__FILE__);
        $this->order_button_text = __('Pay with Vipps','woo-vipps');
        $this->init_form_fields();
        $this->init_settings();
        $this->api = new VippsApi($this);

        $this->supports = array('products','refunds');

        // We can't guarantee any particular product type being supported, so we must enumerate those we are certain about
        // IOK 2020-04-21 Add support for WooCommerce Product Bundles
        $supported_types= array('simple','variable','variation','bundle');
        $this->express_checkout_supported_product_types = apply_filters('woo_vipps_express_checkout_supported_product_types',  $supported_types);

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        //  Capturing, refunding and cancelling the order when transitioning states:
        //   This are the statuses for which the Vipps plugin should try to ensure capture has been made.
        //   Normally, this is 'processing' and 'completed', but plugins may define other statuses. IOK 2018-10-05
        //  It is also possible to remove 'processing' from this list. If you do, you may use it as the end-state of the
        //  Vipps transaction (see below in after_vipps_order_status) IOK 2018-12-05
        $resultstatus = $this->get_option('result_status');
        $captured_statuses = apply_filters('woo_vipps_captured_statuses', array('processing', 'completed'));
        $captured_statuses = array_diff($captured_statuses, array($resultstatus));

        $this->captured_statuses = $captured_statuses;

        $non_completed_captured_statuses = array_diff($captured_statuses, array('completed'));

        // This ensures that funds are captured when transitioning from 'on hold' to a status where the money
        // should be captured, and refunded when moved from this status to cancelled or refunded
        foreach($captured_statuses as $capstatus) {
           add_action( 'woocommerce_order_status_' . $capstatus, array($this, 'maybe_capture_payment'));
        }
        add_action( 'woocommerce_order_status_cancelled', array($this, 'maybe_cancel_payment'));
        add_action( 'woocommerce_order_status_refunded', array($this, 'maybe_refund_payment'));

        add_action( 'woocommerce_order_status_pending_to_cancelled', array($this, 'maybe_delete_order'), 99999, 1);

    }

    // True iff this gateway is currently in test mode. IOK 2019-08-30
    public function is_test_mode() {
       if (VIPPS_TEST_MODE) return true;
       if ($this->get_option('developermode') == 'yes' && $this->get_option('testmode') == 'yes') return true;
       return false;
    }
    // These abstraction gets the correct client id and so forth based on whether or not test mode is on
    public function apiurl () {
       if ($this->is_test_mode()) return $this->testapiurl;
       return $this->apiurl;
    }
    public function get_merchant_serial() {
        $merch = $this->get_option('merchantSerialNumber');
        $testmerch = @$this->get_option('merchantSerialNumber_test');
        if (!empty($testmerch) && $this->is_test_mode()) return $testmerch;
        return $merch;
    }
    public function get_clientid() {
        $clientid=$this->get_option('clientId');
        $testclientid=$this->get_option('clientId_test');
        if (!empty($testclientid) && $this->is_test_mode()) return $testclientid;
        return $clientid;
    }
    public function get_secret() {
        $secret=$this->get_option('secret');
        $testsecret=$this->get_option('secret_test');
        if (!empty($testsecret) && $this->is_test_mode()) return $testsecret;
        return $secret;
    }
    public function get_key() {
        $key = $this->get_option('Ocp_Apim_Key_eCommerce');
        $testkey = $this->get_option('Ocp_Apim_Key_eCommerce_test');
        if (!empty($testkey) && $this->is_test_mode()) return $testkey;
        return $key;
    }
    public function get_orderprefix() {
        $prefix = $this->get_option('orderprefix');
        return $prefix;
    }

    // The return URL will normally use the Checkout page, but if we are using Vipps Checkout, we'll want to use the *normal* checkout
    // page to handle the thankyous. This way we don't have to handle the "thankyou" endpoint. IOK 2021-10-04
    public function get_return_url($order=null) {
        $this->get_real_checkout_screen = true;
        $url = parent::get_return_url($order); 
        $this->get_real_checkout_screen = false;
        return $url;
    }


    // Delete express checkout orders with no customer information - these were abandonend before the app started.
    // IOK 2019-08-26
    public function maybe_delete_order ($orderid) {
        $order = wc_get_order($orderid);
        if (!$order) return;
        if ('vipps' != $order->get_payment_method()) return false;
        $express = $order->get_meta('_vipps_express_checkout');
        if (!$express) return false;
        $email = $order->get_billing_email($orderid);
        if ($email) return false;

        // Only delete if we have to
        if ($this->get_option('deletefailedexpressorders'  != 'yes')) return false;
        // Mark this order that an order that wasn't completed with any user info - it can be deleted. IOK 2019-11-13
        $order->update_meta_data('_vipps_delendum',1);
        $order->save();
        return true;
    }


    // Return the status to use after return from Vipps for orders that are not both "virtual" and "downloadable".
    // These orders are *not* complete, and payment is *not* captured, which is why the default status is 'on-hold'.
    // If you use custom order statuses, or if you don't capture on 'processing' - see filter 'woo_vipps_captured_statuses' -
    // you can instead use 'processing' here - which is much nicer. 
    // If you do so, remember to capture *before* shipping is done on the order - if you send the package and then do 'complete', 
    // the capture may fail. IOK 2018-12-05
    // The intention is to provide this behaviour as a selectable checkbox in the backend in the future; it has to be
    // explicitly chosen so merchants can be aware of the possible isssues. IOK 2018-12-05
    public function after_vipps_order_status($order=null) {
      $defaultstatus = 'on-hold';
      $chosen = $this->get_option('result_status');
      if ($chosen == 'processing') $defaultstatus = $chosen;
      $newstatus = apply_filters('woo_vipps_after_vipps_order_status', $defaultstatus, $order);
      if (in_array($newstatus, $this->captured_statuses)){
             $this->log(sprintf(__("Cannot use %s as status for non-autocapturable orders: payment is captured on this status. See the woo_vipps_captured_statuses-filter.",'woo-vipps'), $newstatus),'debug');
             return  $defaultstatus;
      }
      return $newstatus;
    }

    // Create callback urls' using WC's callback API in a way that works with Vipps callbacks and both pretty and not so pretty urls.
    private function make_callback_urls($forwhat,$token='') {
        // Passing the token as GET arguments, as the Authorize header is stripped. IOK 2018-06-13
        $tk = '';
        if ($token)  {
          $tk = "tk=$token";
        }
        // HTTPS required. IOK 2018-05-18
        // If the user for some reason hasn't enabled pretty links, fall back to ancient version. IOK 2018-04-24
        if ( !get_option('permalink_structure')) {
            return untrailingslashit(set_url_scheme(home_url(),'https')) . "/?wc-api=$forwhat&$tk&callback=";
        } else {
            return untrailingslashit(set_url_scheme(home_url(),'https')) . "/wc-api/$forwhat?$tk&callback=";
        }
    }
    // The main payment callback
    public function payment_callback_url ($token='') {
        return $this->make_callback_urls('wc_gateway_vipps',$token);
    }
    public function shipping_details_callback_url($token='') {
        return $this->make_callback_urls('vipps_shipping_details',$token);
    }
    // Callback for the consetn removal callback. Must use template redirect directly, because wc-api doesn't handle DELETE.
    // IOK 2018-05-18
    public function consent_removal_callback_url () {
        if ( !get_option('permalink_structure')) {
            return untrailingslashit(set_url_scheme(home_url(),'https')) . "/?vipps-consent-removal&callback=";
        } else {
            return untrailingslashit(set_url_scheme(home_url(),'https')) . "/vipps-consent-removal/?callback=";
        }
    }

    // Allow user to select the template to be used for the special Vipps pages. IOK 2020-02-17
    public function get_theme_page_templates() {
         $choices = array('' => __('Use default template', 'woo-vipps'));
         foreach(wp_get_theme()->get_page_templates() as $filename=>$name) {
             //$choices[$filename]=$name;
             $choices[$filename]=$name;
         }
         return $choices;
     }

    // Check to see if the product in question can be bought with express checkout IOK 2018-12-04
    public function product_supports_express_checkout($product) {
        $type = $product->get_type();
        $ok = in_array($type, $this->express_checkout_supported_product_types);
        $ok = apply_filters('woo_vipps_product_supports_express_checkout',$ok,$product);
        return $ok;
    }

    // Check to see if the cart passed (or the global one) can be bought with express checkout IOK 2018-12-04
    public function cart_supports_express_checkout($cart=null) {
        if (!$cart) $cart = WC()->cart;
        $supports  = true;
        if (!$cart) return $supports;

            # Not supported by Vipps
            if ($cart->cart_contents_total <= 0) return false;

        foreach($cart->get_cart() as $key=>$val) {
            $prod = $val['data'];
            if (!is_a($prod, 'WC_Product')) continue;
            $product_supported = $this->product_supports_express_checkout($prod);
            if (!$product_supported) {
                $supports = false;
                break;
            }
        }
        $supports = apply_filters('woo_vipps_cart_supports_express_checkout', $supports, $cart);
        return $supports;
    }

    // True if "Express checkout" should be displayed IOK 2018-06-18
    public function show_express_checkout() {
            if (!$this->express_checkout_available()) return false;
        $show = ($this->enabled == 'yes') && ($this->get_option('cartexpress') == 'yes') ;
        $show = $show && $this->cart_supports_express_checkout();
            return apply_filters('woo_vipps_show_express_checkout', $show);
    }
    public function show_login_with_vipps() {
        return false;
    }


    public function maybe_cancel_payment($orderid) {
        $order = wc_get_order($orderid);
        if ('vipps' != $order->get_payment_method()) return false;
        $ok = 0;

        // Now first check to see if we have captured anything, and if we have, refund it. IOK 2018-05-07
        $captured = $order->get_meta('_vipps_captured');
        $vippsstatus = $order->get_meta('_vipps_status');
        if ($captured || $vippsstatus == 'SALE') {
            return $this->maybe_refund_payment($orderid);
        }


        $payment = $this->check_payment_status($order);
        if ($payment == 'initiated' || $payment == 'cancelled') {
           return true; // Can't cancel these
        }

        try {
            $ok = $this->cancel_payment($order);
        } catch (Exception $e) {
            // This is handled in sub-methods so we shouldn't actually hit this IOK 2018-05-07 
        } 
        if (!$ok) {
            // It's just a captured payment, so we'll ignore the illegal status change. IOK 2017-05-07
            $msg = __("Could not cancel Vipps payment", 'woo-vipps');
            $this->adminerr($msg);
            $order->save();
            global $Vipps;
            $Vipps->store_admin_notices();
        }
    }

    // Handle the transition from anything to "refund"
    public function maybe_refund_payment($orderid) {
        $order = wc_get_order($orderid);
        if ('vipps' != $order->get_payment_method()) return false;
        $ok = 0;

        // IOK 2019-10-03 it is now possible to do capture via other tools than Woo, so we must now first check to see if 
        // the order is capturable by getting full payment details.
        try {
                $order = $this->update_vipps_payment_details($order); 
       } catch (Exception $e) {
                //Do nothing with this for now
                $this->log(__("Error getting payment details before doing refund: ", 'woo-vipps') . $e->getMessage(), 'warning');
        }
        // Now first check to see if we have captured anything, and if we haven't, just cancel order IOK 2018-05-07
        $vippsstatus = $order->get_meta('_vipps_status');
        $captured = $order->get_meta('_vipps_captured');
        $to_refund =  $order->get_meta('_vipps_refund_remaining');

        if (!$captured) {
            return $this->maybe_cancel_payment($orderid);
        }
        if ($to_refund == 0) return true;

        try {
            $ok = $this->refund_payment($order,$to_refund,'exact');
        } catch (TemporaryVippsAPIException $e) {
            $this->adminerr(__('Temporary error when refunding payment through Vipps - ensure order is refunded manually, or reset the order to "Processing" and try again', 'woo-vipps'));
            $this->adminerr($e->getMessage());
            global $Vipps;
            $Vipps->store_admin_notices();
            return false;
        } catch (Exception $e) {
            $order->add_order_note(__("Error when refunding payment through Vipps:", 'woo-vipps') . ' ' . $e->getMessage());
            $order->save();
            $this->adminerr($e->getMessage());
        }
        if (!$ok) {
            $msg = __('Could not refund payment through Vipps - ensure the refund is handled manually!', 'woo-vipps');
            $this->adminerr($msg);
            $order->add_order_note($msg);
            // Unfortunately, we can't 'undo' the refund when the user manually sets the status to "Refunded" so we must 
            // allow the state change here if that happens.
            global $Vipps;
            $Vipps->store_admin_notices();
            return false;
        }
    }

    // This is for orders that are 'reserved' at Vipps but could actually be captured at once because
    // they don't require payment. So we try to capture. IOK 2020-09-22
    // do NOT call this unless the order is 'reserved' at Vipps!
    protected function maybe_complete_payment($order) {
        if ('vipps' != $order->get_payment_method()) return false;
        if ($order->needs_processing()) return false; // No auto-capture for orders needing processing
        // IOK 2018-10-03 when implementing partial capture, this must be modified.
        $captured = $order->get_meta('_vipps_captured'); 
        $vippsstatus = $order->get_meta('_vipps_status');
        if ($captured || $vippsstatus == 'SALE') { 
          return true;
        }
        $ok = 0;
        try {
            $ok = $this->capture_payment($order);
            $order->add_order_note(__('Payment automatically captured at Vipps for order not needing processing','woo_vipps'));
        } catch (Exception $e) {
            $order->add_order_note(__('Order does not need processing, but payment could not be captured at Vipps:','woo_vipps') . ' ' . $e->getMessage());
        }
        if (!$ok) return false;
        $order->save();
        return true;
    }


    // This is the Woocommerce refund api called by the "Refund" actions. IOK 2018-05-11
    public function process_refund($orderid,$amount=null,$reason='') {
        $order = wc_get_order($orderid);

        try {
                $order = $this->update_vipps_payment_details($order); 
        } catch (Exception $e) {
                //Do nothing with this for now
                $this->log(__("Error getting payment details before doing refund: ", 'woo-vipps') . $e->getMessage(), 'warning');
        }

        $captured = $order->get_meta('_vipps_captured');
        $to_refund =  $order->get_meta('_vipps_refund_remaining');

        if (!$captured) {
            return new WP_Error('Vipps', __("Cannot refund through Vipps - the payment has not been captured yet.", 'woo-vipps'));
        }
        if ($amount*100 > $to_refund) {
            return new WP_Error('Vipps', __("Cannot refund through Vipps - the refund amount is too large.", 'woo-vipps'));
        }
        $ok = 0;

        // Specialcase zero, because Vipps treats this as the entire amount IOK 2021-09-14
        if (is_numeric($amount) && $amount == 0) {
            $order->add_order_note($amount . ' ' . 'NOK' . ' ' . __(" refunded through Vipps:",'woo-vipps') . ' ' . $reason);
            return true;
        }

        try {
            $ok = $this->refund_payment($order,$amount);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not refund Vipps payment for order id:', 'woo-vipps') . ' ' . $orderid . "\n" .$e->getMessage(),'error');
            return new WP_Error('Vipps',__('Vipps is temporarily unavailable.','woo-vipps') . ' ' . $e->getMessage());
        } catch (Exception $e) {
            $msg = __('Could not refund Vipps payment','woo-vipps') . ' ' . $e->getMessage();
            $order->add_order_note($msg);
            $this->log($msg,'error');
            return new WP_Error('Vipps',$msg);
        }

        if ($ok) {
            $order->add_order_note($amount . ' ' . 'NOK' . ' ' . __(" refunded through Vipps:",'woo-vipps') . ' ' . $reason);
        } 
        return $ok;
    }


    public function init_form_fields() { 
        global $Vipps;
        $page_templates = $this->get_theme_page_templates();
        $page_list = array(''=>__('Use a simulated page (default)', 'woo-vipps'));
        foreach(get_pages() as $page) $page_list[$page->ID] = $page->post_title;

        $orderprefix = $Vipps->generate_order_prefix();
        $expresscreateuserdefault = "no";
        if (class_exists('VippsWooLogin')) {
           $woodefault = apply_filters('woocommerce_checkout_registration_enabled', 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout'));
           if ($woodefault) $expresscreateuserdefault = "yes";
        }

        // We will only show the Vipps Checkout options if the user has activated the feature (thus creating the pages involved etc). IOK 2021-10-01
        $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);

        $this->form_fields = array(
                'enabled' => array(
                    'title'       => __( 'Enable/Disable', 'woocommerce' ),
                    'label'       => __( 'Enable Vipps', 'woo-vipps' ),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no',
                    )
                );


        if ($vipps_checkout_activated) {
            $this->form_fields['vipps_checkout_enabled'] = 
                    array(
                        'title'       => __( 'Activate Alternative Vipps Checkout', 'woocommerce' ),
                        'label'       => __( 'Enable Alternative Vipps Checkout screen, replacing the standard checkout page', 'woo-vipps' ),
                        'type'        => 'checkbox',
                        'description' => __('If activated, this will <strong>replace</strong> the standard Woo checkout screen with Vipps Checkout, providing easy checkout using Vipps or credit card, with no need to type in addresses.', 'woo-vipps'),
                        'default'     => 'no',
                        );
        }
     

        $standardfields = array(
                'orderprefix' => array(
                    'title' => __('Order-id Prefix', 'woo-vipps'),
                    'label'       => __( 'Order-id Prefix', 'woo-vipps' ),
                    'type'        => 'string',
                    'description' => __('An alphanumeric textstring to use as a prefix on orders from your shop, to avoid duplicate order-ids','woo-vipps'),
                    'default'     => $orderprefix
                    ),
                'merchantSerialNumber' => array(
                    'title' => __('Merchant Serial Number', 'woo-vipps'),
                    'label'       => __( 'Merchant Serial Number', 'woo-vipps' ),
                    'type'        => 'number',
                    'description' => __('Your "Merchant Serial Number" from the Developer tab on https://portal.vipps.no','woo-vipps'),
                    'default'     => '',
                    ),
                'clientId' => array(
                    'title' => __('Client Id', 'woo-vipps'),
                    'class' => 'vippspw',
                    'label'       => __( 'Client Id', 'woo-vipps' ),
                    'type'        => 'password',
                    'description' => __('Find your account under the "Developer" tab on https://portal.vipps.no/ and choose "Show keys". Copy the value of "client_id"','woo-vipps'),
                    'default'     => '',
                    ),
                'secret' => array(
                        'title' => __('Client Secret', 'woo-vipps'),
                        'label'       => __( 'Client Secret', 'woo-vipps' ),
                        'class' => 'vippspw',
                        'type'        => 'password',
                        'description' => __('Find your account under the "Developer" tab on https://portal.vipps.no/ and choose "show keys". Copy the value of "client_secret"','woo-vipps'),
                        'default'     => '',
                        ),
                'Ocp_Apim_Key_eCommerce' => array(
                        'title' => __('Vipps Subscription Key', 'woo-vipps'),
                        'label'       => __( 'Vipps Subscription Key', 'woo-vipps' ),
                        'class' => 'vippspw',
                        'type'        => 'password',
                        'description' => __('Find your account under the "Developer" tab on https://portal.vipps.no/ and choose "show keys". Copy the value of "Vipps-Subscription-Key"','woo-vipps'),
                        'default'     => '',
                        ),

                'result_status' => array(
                        'title'       => __( 'Order status on return from Vipps', 'woo-vipps' ),
                        'label'       => __( 'Choose default order status for reserved (not captured) orders', 'woo-vipps' ),
                        'type'        => 'select',
                        'options' => array(
                            'on-hold' => __('On hold','woo-vipps'),
                            'processing' => __('Processing', 'woo-vipps'),
                            ), 
                        'description' => __('By default, orders that are <b>reserved</b> but not <b>captured</b> will have the order status \'On hold\' until you capture the sum (by changing the status to \'Processing\' or \'Complete\')<br> Some stores prefer to use \'On hold\' only for orders where there are issues with the payment. In this case you can choose  \'Processing\' instead, but you must then ensure that you do <b>not ship the order until after you have done capture</b> - because the \'capture\' step may in rare cases fail. <br>If you choose this setting, capture will still automatically happen on the status change to \'Complete\' ', 'woo-vipps'),
                        'default'     => 'on-hold',
                        ),

                'title' => array(
                        'title' => __( 'Title', 'woocommerce' ),
                        'type' => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                        'default' => __('Vipps','woo-vipps')
                        ),
                'description' => array(
                        'title' => __( 'Description', 'woocommerce' ),
                        'type' => 'textarea',
                        'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                        'default' => __("Pay with Vipps", 'woo-vipps')
                        ),

                'vippsdefault' => array(
                        'title'       => __( 'Use Vipps as default payment method on checkout page', 'woo-vipps' ),
                        'label'       => __( 'Vipps is default payment method', 'woo-vipps' ),
                        'type'        => 'checkbox',
                        'description' => __('Enable this to use Vipps as the default payment method on the checkout page, regardless of order.', 'woo-vipps'),
                        'default'     => 'yes',
                        ),

                'cartexpress' => array(
                        'title'       => __( 'Enable Express Checkout in cart', 'woo-vipps' ),
                        'label'       => __( 'Enable Express Checkout in cart', 'woo-vipps' ),
                        'type'        => 'checkbox',
                        'description' => __('Enable this to allow customers to shop using Express Checkout directly from the cart with no login or address input needed', 'woo-vipps') . '.<br>' .
                        __('Please note that for Express Checkout, shipping must be calculated in a callback from the Vipps app, without any knowledge of the customer. This means that Express Checkout may not be compatible with all Shipping plugins or setup. You should test that your setup works if you intend to provide this feature.', 'woo-vipps'),
                        'default'     => 'yes',
                        ),

                'singleproductexpress' => array(
                        'title'       => __( 'Enable Express Checkout for single products', 'woo-vipps' ),
                        'label'       => __( 'Enable Express Checkout for single products', 'woo-vipps' ),
                        'type'        => 'select',
                        'options' => array(
                            'none' => __('No products','woo-vipps'),
                            'some' => __('Some products', 'woo-vipps'),
                            'all' => __('All products','woo-vipps')
                            ), 
                        'description' => __('Enable this to allow customers to buy a product using Express Checkout directly from the product page. If you choose \'some\', you must enable this on the relevant products', 'woo-vipps'),
                        'default'     => 'none',
                        ),
                'singleproductexpressarchives' => array(
                        'title'       => __( 'Add \'Buy now\' button on catalog pages too', 'woo-vipps' ),
                        'label'       => __( 'Add the button for all relevant products on catalog pages', 'woo-vipps' ),
                        'type'        => 'checkbox',
                        'description' => __('If Express Checkout is enabled for a product, add the \'Buy now\' button to catalog pages too', 'woo-vipps'),
                        'default'     => 'no',
                        ),
                'expresscheckout_termscheckbox' => array(
                        'title'       => __( 'Add terms and conditions checkbox on Express Checkout', 'woo-vipps' ),
                        'label'       => __( 'Always ask for confirmation on Express Checkout', 'woo-vipps' ),
                        'type'        => 'checkbox',
                        'description' => __('When using Express Checkout, ask the user to confirm that they have read and accepted the stores terms and conditons before proceeding', 'woo-vipps'),
                        'default'     => 'no',
                        ),


                'expresscreateuser' => array (
                        'title'       => __( 'Create new customers on Express Checkout', 'woo-vipps' ),
                        'label'       => __( 'Create new customers on Express Checkout', 'woo-vipps' ),
                        'type'        => 'checkbox',
                        'description' => __('Enable this to create and login new customers when using express checkout. Otherwise these will all be guest checkouts. If you have "Login with Vipps" installed, this will be the default (unless you have turned off user creation in WooCommerce itself)', 'woo-vipps'),
                        'default'     => $expresscreateuserdefault,
                        ),

                'singleproductbuynowcompatmode' => array(
                        'title'       => __( '"Buy now" compatibility mode', 'woo-vipps' ),
                        'label'       => __( 'Activate compatibility mode for all "Buy now" buttons', 'woo-vipps' ),
                        'type'        => 'checkbox',
                        'description' => __('Choosing this will use a different method of handling the "Buy now" button on a single product, which will work for more product types and more plugins - while being <i>slightly</i> less smooth. Use this if your product needs more configuration than simple or standard variable products', 'woo-vipps'),
                        'default'     => 'no',
                        ),

                'enablestaticshipping' => array(
                        'title'       => __( 'Enable static shipping for Express Checkout', 'woo-vipps' ),
                        'label'       => __( 'Enable static shipping', 'woo-vipps' ),
                        'type'        => 'checkbox',
                        'description' => __('If your shipping options do not depend on the customers address, you can enable \'Static shipping\', which will precompute the shipping options when using Express Checkout so that this will be much faster. If you do this and the customer isn\'t logged in, the base location of the store will be used to compute the shipping options for the order. You should only use this if your shipping is actually \'static\', that is, does not vary based on the customers address. So fixed price/free shipping will work. If the customer is logged in, their address as registered in the store will be used, so if your customers are always logged in, you may be able to use this too.', 'woo-vipps'),
                        'default'     => 'no',
                        ),


                'deletefailedexpressorders' => array(
                        'title'       => __( 'Delete failed Express Checkout Orders', 'woo-vipps' ),
                        'label'       => __( 'Delete failed Express Checkout Orders', 'woo-vipps' ),
                        'type'        => 'checkbox',
                        'description' => __('As Express Checkout orders are anonymous, failed orders will end up as "cancelled" orders with no information in them. Enable this to delete these automatically when cancelled - but test to make sure no other plugin needs them for anything.', 'woo-vipps'),
                        'default'     => 'no',
                        ),

                'vippsspecialpagetemplate' => array(
                        'title'       => __( 'Override page template used for the special Vipps pages', 'woo-vipps' ),
                        'label'       => __( 'Use specific template for Vipps', 'woo-vipps' ),
                        'type'        => 'select',
                        'options' =>  $page_templates,
                        'description' => __('Use this template from your theme or child-theme to display all the special Vipps pages. You will probably want a full-width template and it should call \'the_content()\' normally.', 'woo-vipps'),
                        ),

                'vippsspecialpageid' =>  array(
                        'title' => __('Use a real page ID for the special Vipps pages - neccessary for some themes', 'woo-vipps'),
                        'label' => __('Use a real page ID', 'woo-vipps'),
                        'type'  => 'select',
                        'options' => $page_list,
                        'description' => __('Some very few themes do not work with the simulated pages used by this plugin, and needs a real page ID for this. Choose a blank page for this; the content will be replaced, but the template and other metadata will be present. You only need to use this if the plugin seems to break on the special Vipps pages.', 'woo-vipps'),
                        'default'=>'')


                    );

       // Add all the standard fields
       foreach($standardfields as $key=>$field) {
          $this->form_fields[$key] = $field;
       }
        

        $this->form_fields['developermode'] = array ( // DEVELOPERS! DEVELOPERS! DEVELOPERS! DEVE
                    'title'       => __( 'Enable developer mode', 'woo-vipps' ),
                    'label'       => __( 'Enable developer mode', 'woo-vipps' ),
                    'type'        => 'checkbox',
                    'description' => __('Enable this to enter developer mode. This gives you access to the test-api and sometimes other tools not yet ready for general consumption', 'woo-vipps'),
                    'default'     => VIPPS_TEST_MODE ? 'yes' : 'no',
        );

    $this->dev_form_fields = array(
            'developertitle' => array(
                'title' => __('Developer mode settings', 'woo-vipps'),
                'type'  => 'title',
                'description' => __('These are settings for developers that contain extra features that are normally not useful for regular users, or are not yet ready for primetime', 'woo-vipps'),
                ),
                        'use_flock' => array (
                            'title'       => __( 'Use flock() to lock orders for Express Checkout', 'woo-vipps' ),
                            'label'       => __( 'Use flock() to lock orders for Express Checkout', 'woo-vipps' ),
                            'type'        => 'checkbox',
                            'description' => __('Use the flock() system call to ensure orders are only finalized once. You can use this for normal setups, but probably not on Windows with IIS, and possibly not on distributed filesystems like NFS. If you don\t know what it is, probably do not use it. If you get duplicated shipping lines on some express orders, you may try using this', 'woo-vipps'),
                            'default'     => 'no',
                            ),



            'testmode' => array(
                'title' => __('Test mode', 'woo-vipps'),
                'title' => __('Enable test mode', 'woo-vipps'),
                'type'  => 'checkbox',
                'description' => __('If you enable this, transactions will be made towards the Vipps Test API instead of the live one. No real transactions will be performed. You will need to fill out your test
                    accounts keys below, and you will need to install a special test-mode app from Testflight on a device (which cannot run the regular Vipps app). Contact Vipps\' technical support if you need this. If you turn this mode off, normal operation will resume. If you have the VIPPS_TEST_MODE defined in your wp-config file, this will override this value. ', 'woo-vipps'),
                'default'     => VIPPS_TEST_MODE ? 'yes' : 'no',
                ),
            'merchantSerialNumber_test' => array(
                'title' => __('Merchant Serial Number', 'woo-vipps'),
                'class' => 'vippspw',
                'label'       => __( 'Merchant Serial Number', 'woo-vipps' ),
                'type'        => 'number',
                'description' => __('Your test account "Merchant Serial Number" from the Developer tab on https://portal.vipps.no','woo-vipps'),
                'default'     => '',
                ),
            'clientId_test' => array(
                    'title' => __('Client Id', 'woo-vipps'),
                    'label'       => __( 'Client Id', 'woo-vipps' ),
                    'type'        => 'password',
                    'class' => 'vippspw',
                    'description' => __('Find your test account under the "Developer" tab on https://portal.vipps.no/ and choose "Show keys". Copy the value of "client_id"','woo-vipps'),
                    'default'     => '',
                    ),
            'secret_test' => array(
                    'title' => __('Client Secret', 'woo-vipps'),
                    'label'       => __( 'Client Secret', 'woo-vipps' ),
                    'type'        => 'password',
                    'class' => 'vippspw',
                    'description' => __('Find your test account under the "Developer" tab on https://portal.vipps.no/ and choose "show keys". Copy the value of "client_secret"','woo-vipps'),
                    'default'     => '',
                    ),
            'Ocp_Apim_Key_eCommerce_test' => array(
                    'title' => __('Vipps Subscription Key', 'woo-vipps'),
                    'label'       => __( 'Vipps Subscription Key', 'woo-vipps' ),
                    'type'        => 'password',
                    'class' => 'vippspw',
                    'description' => __('Find your test account under the "Developer" tab on https://portal.vipps.no/ and choose "show keys". Copy the value of "Vipps-Subscription-Key"','woo-vipps'),
                    'default'     => '',
                    ),
            );

        // Developer mode settings: Only shown when active. IOK 2019-08-30
    if ($this->get_option('developermode') == 'yes' || VIPPS_TEST_MODE) {
        $this->form_fields = array_merge($this->form_fields,$this->dev_form_fields);
                if (VIPPS_TEST_MODE) {
                   $this->form_fields['developermode']['description'] .= '<br><b>' . __('VIPPS_TEST_MODE is set to true in your configuration - dev mode is forced', 'woo-vipps') . "</b>";
                   $this->form_fields['testmode']['description'] .= '<br><b>' . __('VIPPS_TEST_MODE is set to true in your configuration - test mode is forced', 'woo-vipps') . "</b>";
                }
    }

        // New shipping in express checkout is available, but merchant has overridden the old shipping callback. Ask what to do! IOK 2020-02-12
        if (has_action('woo_vipps_shipping_methods')) {
            $shippingoptions = array( 
                    'newshippingcallback' => array(
                        'title'       => __( 'Use old-style shipping callback for express checkout', 'woo-vipps' ),
                        'label'       => __( 'Use your current shipping filters', 'woo-vipps' ),
                        'type'        => 'select',
                        'options' => array(
                            'none' => __('Select one','woo-vipps'),
                            'old' => __('Keep using old shipping callback with my custom filter', 'woo-vipps'),
                            'new' => __('Use new shipping callback','woo-vipps')
                            ),
                        'description' => __('Since version 1.4 this plugin uses a new method of providing shipping methods to Vipps when using Express Checkout. The new method supports metadata in the shipping options, which is neccessary for integration with Bring, Postnord etc. However, the new method is not compatible with the old <code>\'woo_vipps_shipping_methods\'</code> filter, which your site has overridden in a theme or plugin. If you want to, you can continue using this filter and the old method. If you want to disable your filters and use the new method, you can choose this here. ', 'woo-vipps'),
                        'default'     => 'none',
                        )
                    );
            $this->form_fields = array_merge(array_slice($this->form_fields,0,1), $shippingoptions, array_slice($this->form_fields,1));
        }


   
    }






    // IOK 2018-04-18 utilities for the 'admin notices' interface.
    private function adminwarn($what) {
        add_action('admin_notices',function() use ($what) {
                echo "<div class='notice notice-warning is-dismissible'><p>$what</p></div>";
                });
    }
    private function adminerr($what) {
        add_action('admin_notices',function() use ($what) {
                echo "<div class='notice notice-error is-dismissible'><p>$what</p></div>";
                });
    }
    private function adminnotify($what) {
        add_action('admin_notices',function() use ($what) {
                echo "<div class='notice notice-info is-dismissible'><p>$what</p></div>";
                });
    }

    // Only be available if current currency is NOK IOK 2018-09-19
    public function is_available() {
        // This is split into two functions to avoid triggering infinite recursion in filters that override this value below. IOK 2021-10-30
        $ok = $this->standard_is_available();
        $ok = apply_filters('woo_vipps_is_available', $ok, $this);
        return $ok; 
    }

    // True if the alternative Vipps Checkout screen is both available and activated. Returns the page id of the checkout
    // page for convenience. IOK 2021-10-01
    public function vipps_checkout_available () {
        if ($this->get_option('vipps_checkout_enabled') != 'yes') return false;
        if (!$this->standard_is_available()) return false;

        $checkoutid = wc_get_page_id('vipps_checkout');
        if (!$checkoutid) return false;
      
        // Page doesn't exist anymore 
        if (! get_post_status($checkoutid)) {
             delete_option('woocommerce_vipps_checkout_page_id');
             return false;
        }

        // Restrictions on cart are the same as for express checkout
        if (!$this->cart_supports_express_checkout()) return false;

        // This checks currency and so forth
        $ok = $this->standard_is_available();
        // Filter to false if you want to use the standard checkout for whatever reason
        return apply_filters('woo_vipps_checkout_available', $checkoutid, $this);
    }

    //  Basic unfiltered version of "can I use vipps" ? IOK 2021-10-01
    protected function standard_is_available () {
        if (!$this->can_be_activated()) return false;
        if (!parent::is_available()) return false;
        $ok = true;
        $currency = get_woocommerce_currency(); 
        if ($currency != 'NOK') {
            $ok = false;
        }
        return $ok;
    }

    // True iff the express checkout feature  should be available 
    public function express_checkout_available() {
       if (! $this->is_available()) return false;
       $ok = true;
       $ok = apply_filters('woo_vipps_express_checkout_available', $ok, $this);
       return $ok;
    }

    // IOK 2018-04-20 Initiate payment at Vipps and redirect to the Vipps payment terminal.
    public function process_payment ($order_id) {
        global $woocommerce, $Vipps;
        if (!$order_id) return false;

        do_action('woo_vipps_before_process_payment',$order_id);

        // Do a quick check for correct setup first - this is the most critical point IOK 2018-05-11 
        try {
            $at = $this->api->get_access_token();
        } catch (Exception $e) {
            $this->log(__('Could not get access token when initiating Vipps payment for order id:','woo-vipps') . $order_id .":\n" . $e->getMessage(), 'error');
            wc_add_notice(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','woo-vipps'),'error');
            return false;
        }


        // From the request, get either    [billing_phone] =>  or [vipps phone]
        $phone = '';
        if (isset($_POST['vippsphone'])) {
            $phone = trim(sanitize_text_field($_POST['vippsphone']));
        }
        if (!$phone && isset($_POST['billing_phone'])) {
            $phone = trim(sanitize_text_field($_POST['billing_phone']));
        }

        // This is for express checkout if we know the customers' phone.
        // thanks to sOndre @ github for reporting , https://github.com/vippsas/vipps-woocommerce/issues/22
        if (!$phone && WC()->customer) {
            $phone = WC()->customer->get_billing_phone();
        }

        // No longer the case for V2 of the API
        if (false && !$phone) {
            wc_add_notice(__('You need to enter your phone number to pay with Vipps','woo-vipps') ,'error');
            return false;
        }

        $order = wc_get_order($order_id);
        $content = null;

        // This is needed to ensure that the callbacks from Vipps have access to the customers' session which is important for some plugins.  IOK 2019-11-22
        $this->save_session_in_order($order);

        // Vipps-terminal-page return url to poll/await return
        $returnurl= $Vipps->payment_return_url();
        // If we are using express checkout, use this to handle the address stuff
        // IOK 2018-11-19 also when *not* using express checkout. This allows us to pass the order-id in the return URL and use this as a password in case the sesson has been lost.
        $authtoken = $this->generate_authtoken();

        // IOK 2019-11-19 We have to do this because even though we actually store the order ID in the session, we can a) be redirected to another browser than the one with
        // the session, and b) some plugins wipe the session for guest purchases. So we might need to restore (enough of the) session to get to the than you page,
        // even if the session is gone or in another castle.
        $returnurl = add_query_arg('t',$authtoken,$returnurl);

        try {
            // The requestid is actually for replaying the request, but I get 402 if I retry with the same Orderid.
            // Still, if we want to handle transient error conditions, then that needs to be extended here (timeouts, etc)
            $requestid = $order->get_order_key();
            $content =  $this->api->initiate_payment($phone,$order,$returnurl,$authtoken,$requestid);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not initiate Vipps payment','woo-vipps') . ' ' . $e->getMessage(), 'error');
            wc_add_notice(__('Unfortunately, the Vipps payment method is temporarily unavailable. Please wait or  choose another method.','woo-vipps'),'error');
            return false;
        } catch (Exception $e) {
            $this->log(__('Could not initiate Vipps payment','woo-vipps') . ' ' . $e->getMessage(), 'error');
            wc_add_notice(__('Unfortunately, the Vipps payment method is currently unavailable. Please choose another method.','woo-vipps'),'error');
            return false;
        }

        $url = $content['url'];
        $vippstamp = time();

        // Ensure we only check the status by ajax of our own orders. IOK 2018-05-03
        $sessionorders= WC()->session->get('_vipps_session_orders');
        $sessionorders[$order_id] = 1;
        WC()->session->set('_vipps_session_orders',$sessionorders);
        WC()->session->set('_vipps_pending_order',$order_id); // Send information to the 'please confirm' screen IOK 2018-04-24
        // IOK 2018-11-19 And because the session may be dead, or stored in another browser, store the authtoken in a transient, used to retrieve the order
        // in the waiting screen if the session is dead. Note that the transients-api doesn't really guarantee that this value will exist, but
        // this is meant as a failsafe.
        set_transient('_vipps_pending_order_'.$authtoken, $order_id,20*MINUTE_IN_SECONDS);

        $order = wc_get_order($order_id);
        if ($authtoken) {
            $order->update_meta_data('_vipps_authtoken',wp_hash_password($authtoken));
        }
        $order->update_meta_data('_vipps_init_timestamp',$vippstamp);
        $order->update_meta_data('_vipps_status','INITIATE'); // INITIATE right now
        $order->add_order_note(__('Vipps payment initiated','woo-vipps'));
        $order->add_order_note(__('Awaiting Vipps payment confirmation','woo-vipps'));
        $order->save();

        // Create a signal file that we can check without calling wordpress to see if our result is in IOK 2018-05-04
        try {
            $Vipps->createCallbackSignal($order);
        } catch (Exception $e) {
            // Could not create a signal file, but that's ok.
        }

        do_action('woo_vipps_before_redirect_to_vipps',$order_id);

        // This will send us to a receipt page where we will do the actual work. IOK 2018-04-20
        return array('result'=>'success','redirect'=>$url);
    }


    // This tries to capture a Vipps payment, and resets the status to 'on-hold' if it fails.  IOK 2018-05-07
    public function maybe_capture_payment($orderid) {
        $order = wc_get_order($orderid);
        if ('vipps' != $order->get_payment_method()) return false;
        $ok = 0;

        # Shortcut orders that have been directly captured
        $vippsstatus = $order->get_meta('_vipps_status');
        if ($vippsstatus == 'SALE') {
            return true;
        }

        $remaining = $order->get_meta('_vipps_capture_remaining');

        // Somehow the order status in payment_complete has been set to the 'after order status' or 'complete' by a filter. If so, do not capture.
        // Capture will be done *before* payment_complete if appropriate IOK 2020-09-22
        if (did_action('woocommerce_pre_payment_complete')) {
            if (!$order->needs_processing()) return; // This is fine, we've captured.
        if ($remaining>0) {
                    // Not everything has been captured, but we have reached a capturable status. Complain, do not capture. IOK 2020-09-22
            $this->log(sprintf(__("Filters are setting the payment_complete order status to '%s' - will not capture", 'woo-vipps'), $order->get_status()),'debug');
            $order->add_order_note(sprintf(__('Payment complete set status to "%s" - will not capture payments automatically','woo-vipps'), $order->get_status()));
            return false;
        }
        }

        // IOK 2019-10-03 it is now possible to do capture via other tools than Woo, so we must now first check to see if 
        // the order is capturable by getting full payment details.
        try {
                $order = $this->update_vipps_payment_details($order); 
       } catch (Exception $e) {
                //Do nothing with this for now
                $this->log(__("Error getting payment details before doing capture: ", 'woo-vipps') . $e->getMessage(), 'warning');
        }


        try {
            $ok = $this->capture_payment($order);
        } catch (Exception $e) {
            // This is handled in sub-methods so we shouldn't actually hit this IOK 2018-05-07 
        } 
        if (!$ok) {
            $msg = __("Could not capture Vipps payment - status set to", 'woo-vipps') . ' ' . __('on-hold','woocommerce');
            $this->adminerr($msg);
            $order->set_status('on-hold',$msg);
            $order->save();
            global $Vipps;
            $Vipps->store_admin_notices();
            return false;
        }
    }


    // Capture (possibly partially) the order. Only full capture really supported by plugin at this point. IOK 2018-05-07
    public function capture_payment($order) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $this->log(__('Trying to capture payment on order not made by Vipps:','woo-vipps'). ' ' . $order->get_id(), 'error');
            $this->adminerr(__('Cannot capture payment on orders not made by Vipps','woo-vipps'));
            return false;
        }

        // Partial capture can happen if the order is edited IOK 2017-12-19
        $captured = intval($order->get_meta('_vipps_captured'));
        $vippsstatus = $order->get_meta('_vipps_status');

        // Ensure 'SALE' direct captured orders work
        if (!$captured && $vippsstatus == 'SALE') { 
            $order = $this->update_vipps_payment_details($order); 
            $captured = $order->get_meta('_vipps_captured');
        }

        $total = round(wc_format_decimal($order->get_total(),'')*100);
        $amount = $total-$captured;
        if ($amount<=0) {
                $order->add_order_note(__('Payment already captured','woo-vipps'));
                return true;
        }

        // If we already have captured everything, then we are ok! IOK 2017-05-07
        if ($captured) {
            $remaining = $order->get_meta('_vipps_capture_remaining');
            if (!$remaining) {
                $order->add_order_note(__('Payment already captured','woo-vipps'));
                return true;
            }
        }

        // Each time we succeed, we'll increase the 'capture' transaction id so we don't just capture the same amount again and again. IOK 2018-05-07
        // (but on failre, we don't increase it - and also, we don't really support partial capture yet.) IOK 2018-05-07
        $requestidnr = intval($order->get_meta('_vipps_capture_transid'));
        try {
            $requestid = $requestidnr . ":" . $order->get_order_key();
            $content =  $this->api->capture_payment($order,$amount,$requestid);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not capture Vipps payment for order id:', 'woo-vipps') . ' ' . $order->get_id() . "\n" .$e->getMessage(),'error');
            $this->adminerr(__('Vipps is temporarily unavailable.','woo-vipps') . "\n" . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $msg = __('Could not capture Vipps payment for order_id:','woo-vipps') . ' ' . $order->get_id() . "\n" . $e->getMessage();
            $this->log($msg,'error');
            $this->adminerr($msg);
            return false;
        }
        // Store amount captured, amount refunded etc and increase the capture-key if there is more to capture 
        // status 'captured'
        $transactionInfo = $content['transactionInfo'];
        $transactionSummary= $content['transactionSummary'];
        $order->update_meta_data('_vipps_capture_timestamp',strtotime($transactionInfo['timeStamp']));
        $order->update_meta_data('_vipps_captured',$transactionSummary['capturedAmount']);
        $order->update_meta_data('_vipps_refunded',$transactionSummary['refundedAmount']);
        $order->update_meta_data('_vipps_capture_remaining',$transactionSummary['remainingAmountToCapture']);
        $order->update_meta_data('_vipps_refund_remaining',$transactionSummary['remainingAmountToRefund']);
        // Since we succeeded, the next time we'll start a new transaction.
        $order->update_meta_data('_vipps_capture_transid', $requestidnr+1);
        $order->add_order_note(__('Vipps Payment captured:','woo-vipps') . ' ' .  sprintf("%0.2f",$transactionSummary['capturedAmount']/100) . ' ' . 'NOK');
        $order->save();

        return true;
    }

    public function refund_superfluous_capture($order) {
        $status = $order->get_status();
        if ($status != 'completed') {
            $this->log(__('Cannot refund superfluous capture on non-completed order:','woo-vipps'). ' ' . $order->get_id(), 'error');
            $this->adminerr(__('Order not completed, cannot refund superfluous capture','woo-vipps'));
            return false;
        }

        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $this->log(__('Trying to refund payment on order not made by Vipps:','woo-vipps'). ' ' . $order->get_id(), 'error');
            $this->adminerr(__('Cannot refund payment on orders not made by Vipps','woo-vipps'));
            return false;
        }

        $total = round(wc_format_decimal($order->get_total(),'')*100);
        $captured = $order->get_meta('_vipps_captured');
        $to_refund =  $order->get_meta('_vipps_refund_remaining');
        $refunded = $order->get_meta('_vipps_refunded');
        $superfluous = $captured-$total-$refunded;


        if ($captured <= $total) {
            return false;
        }
        $superfluous = $captured-$total-$refunded;
        if ($superfluous<=0) {
            return false;
        }
        $refundvalue = min($to_refund,$superfluous);

        $reason = __("The value of the order is less than the amount captured.", "woo-vipps");

        $ok = 0;
        try {
            $ok = $this->refund_payment($order,$refundvalue,'cents');
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not refund Vipps payment for order id:', 'woo-vipps') . ' ' . $order->get_id() . "\n" .$e->getMessage(),'error');
            return new WP_Error('Vipps',__('Vipps is temporarily unavailable.','woo-vipps') . ' ' . $e->getMessage());
        } catch (Exception $e) {
            $msg = __('Could not refund Vipps payment','woo-vipps') . ' ' . $e->getMessage();
            $order->add_order_note($msg);
            $this->log($msg,'error');
            return new WP_Error('Vipps',$msg);
        }

        if ($ok) {
            $order->add_order_note($refundvalue/100 . ' ' . 'NOK' . ' ' . __(" refunded through Vipps:",'woo-vipps') . ' ' . $reason);
        } 
        return $ok;
    }

    // Cancel (only completely) a reserved but not yet captured order IOK 2018-05-07
    public function cancel_payment($order) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $this->log(__('Trying to cancel payment on order not made by Vipps:','woo-vipps'). ' ' .$order->get_id(), 'error');
            $this->adminerr(__('Cannot cancel payment on orders not made by Vipps','woo-vipps'));
            return false;
        }
        // If we have captured the order, we can't cancel it. IOK 2018-05-07
        $captured = $order->get_meta('_vipps_captured');
        if ($captured) {
            $msg = __('Cannot cancel a captured Vipps transaction - use refund instead', 'woo-vipps');
            $this->adminerr($msg);
            return false;
        }
        // We'll use the same transaction id for all cancel jobs, as we can only do it completely. IOK 2018-05-07
        try {
            $requestid = "";
            $content =  $this->api->cancel_payment($order,$requestid);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not cancel Vipps payment for order_id:', 'woo-vipps') . ' ' . $order->get_id() . "\n" .$e->getMessage(),'error');
            $this->adminerr(__('Vipps is temporarily unavailable.','woo-vipps') . ' ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $msg = __('Could not cancel Vipps payment for order id:','woo-vipps') . $order->get_id() . "\n" . $e->getMessage();
            $this->log($msg,'error');
            $this->adminerr($msg);
            return false;
        }
        // Store amount captured, amount refunded etc and increase the capture-key if there is more to capture 
        $transactionInfo = $content['transactionInfo'];
        $transactionSummary= $content['transactionSummary'];
        $order->update_meta_data('_vipps_cancel_timestamp',strtotime($transactionInfo['timeStamp']));
        $order->update_meta_data('_vipps_captured',$transactionSummary['capturedAmount']);
        $order->update_meta_data('_vipps_refunded',$transactionSummary['refundedAmount']);
        $order->update_meta_data('_vipps_capture_remaining',$transactionSummary['remainingAmountToCapture']);
        $order->update_meta_data('_vipps_refund_remaining',$transactionSummary['remainingAmountToRefund']);
        $order->add_order_note(__('Vipps Payment cancelled:','woo-vipps'));
        $order->save();

        // Update status from Vipps, but ignore errors IOK 2018-05-07
        try {
            $status = $this->get_vipps_order_status($order);
            if ($status) $order->update_meta_data('_vipps_status',$status);
            $order->save();
        } catch (Exception $e)  {
        }
        return true;
    }

    // Refund (possibly partially) the captured order. IOK 2018-05-07
    // The caller must handle the errors.
    public function refund_payment($order,$amount=0,$cents=false) {

        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $msg = __('Trying to refund payment on order not made by Vipps:','woo-vipps') . ' ' . $order->get_id();
            $this->log($msg,'error');
            throw new VippsAPIException($msg);
        }
        // If we haven't captured anything, we can't refund IOK 2017-05-07
        $captured = $order->get_meta('_vipps_captured');
        if (!$captured) {
            $msg = __('Trying to refund payment on Vipps payment not captured:','woo-vipps'). ' ' .$order->get_id();
            $this->log($msg,'error');
            throw new VippsAPIException($msg);
        }

        // Each time we succeed, we'll increase the 'refund' transaction id so we don't just refund the same amount again and again. IOK 2018-05-07
        // (but on failre, we don't increase it.) IOK 2018-05-07
        $requestidnr = intval($order->get_meta('_vipps_refund_transid'));
        $requestid = $requestidnr . ":" . $order->get_order_key();
        $content =  $this->api->refund_payment($order,$requestid,$amount,$cents);
        // Store amount captured, amount refunded etc and increase the refund-key if there is more to capture 
        $transactionInfo = $content['transaction']; // NB! Completely different name here as compared to the other calls. IOK 2018-05-11
        $transactionSummary= $content['transactionSummary'];
        $order->update_meta_data('_vipps_refund_timestamp',strtotime($transactionInfo['timeStamp']));
        $order->update_meta_data('_vipps_captured',$transactionSummary['capturedAmount']);
        $order->update_meta_data('_vipps_refunded',$transactionSummary['refundedAmount']);
        $order->update_meta_data('_vipps_capture_remaining',$transactionSummary['remainingAmountToCapture']);
        $order->update_meta_data('_vipps_refund_remaining',$transactionSummary['remainingAmountToRefund']);
        // Since we succeeded, the next time we'll start a new transaction.
        $order->update_meta_data('_vipps_refund_transid', $requestidnr+1);
        $order->add_order_note(__('Vipps payment refunded:','woo-vipps') . ' ' .  sprintf("%0.2f",$transactionSummary['refundedAmount']/100) . ' ' . 'NOK');
        $order->save();
        return true;
    }

    // Generate a one-time password for certain callbacks, with some backwards compatibility for PHP 5.6
    public function generate_authtoken($length=32) {
        $token="";
        if (function_exists('random_bytes')) {
            $token = bin2hex(random_bytes($length));
        } elseif  (function_exists('openssl_random_pseudo_bytes')) {
            $token = bin2hex(openssl_random_pseudo_bytes($length));
        } elseif (function_exists('mcrypt_create_iv')) {
            // These aren't "secure" but they are probably ok for this purpose. IOK 2018-05-18
            $indirect = 'mcrypt_create_iv'; // grep-based 7.2 compatibility checkers need to be worked around IOK 2018-10-24
            $token = bin2hex($indirect($length));
        } else {
            // Final fallback
            $token = bin2hex(md5(microtime() . ":" . mt_rand()));
        }

        return $token;
    }


    // Collapse several statuses to a known list IOK 2019-01-23
    public function interpret_vipps_order_status($status) {
        switch ($status) { 
            case 'INITIATE':
            case 'REGISTER':
            case 'REGISTERED':
                return 'initiated';
                break;
            case 'RESERVE':
            case 'RESERVED':
                return 'authorized';
            case 'SALE':
                return 'complete';
                break;
            case 'CANCEL':
            case 'CANCELLED':
            case 'VOID':
            case 'AUTOREVERSAL':
            case 'AUTOCANCEL':
            case 'AUTO_CANCEL':
            case 'RESERVE_FAILED':
            case 'FAILED':
            case 'REJECTED':
                return 'cancelled';
                break;
            }
         // Default should never happen,  but just to ensure we are in our enumeration
         return "initiated";
    }

    // This does not normally call Vipps, so if you need to refresh status, please use callback_check_order_status first. IOK 2019-01-23
    public function check_payment_status($order) {
        if (!$order) return 'cancelled';
        $status = $this->interpret_vipps_order_status($order->get_meta('_vipps_status'));
        // This can happen if the vipps status is set from the back end for instance. IOK 2020-08-14
        if ($order->get_status() == 'pending' && $status != 'initiated') {
           $this->callback_check_order_status($order);
           $order = wc_get_order($order->get_id()); // refresh to get the new status IOK 2021-01-20
           $status = $this->interpret_vipps_order_status($order->get_meta('_vipps_status'));
        }
        return $status;
    }

    // Check status of order at Vipps, in case the callback has been delayed or failed.   
    // Should only be called if in status 'pending'; it will modify the order when status changes.
    public function callback_check_order_status($order) {
        global $Vipps;
        $orderid = $order->get_id();

        clean_post_cache($order->get_id());
        $order = wc_get_order($orderid); // Ensure a fresh copy is read.

        $oldstatus = $order->get_status();
        $newstatus = $oldstatus;

        if ($oldstatus != 'pending') return $oldstatus;

        // If we are in the process of getting a callback from vipps, don't update anything. Currently, Woo/WP has no locking mechanism,
        // and it isn't feasible to implement one portably. So this reduces somewhat the likelihood of races when this method is called 
        // and callbacks happen at the same time.
        if (!$Vipps->lockOrder($order)) {
            return $oldstatus;
        }
     
        $oldvippsstatus = $this->interpret_vipps_order_status($order->get_meta('_vipps_status'));
        $vippsstatus = "";

        /* Now read the payment details and update the order with the relevant values, finding the new Vipps status IOK 2021-01-20 */
        $paymentdetails = array();
        try {
            $paymentdetails = $this->get_payment_details($order);
            $newvippsstatus = $this->get_vipps_order_status($order,$paymentdetails);
            if (!$newvippsstatus) {
                throw new Exception(__("Could not interpret Vipps order status", 'woo-vipps'));
            }
            $order->update_meta_data('_vipps_status',$newvippsstatus);
            $vippsstatus = $this->interpret_vipps_order_status($newvippsstatus);
            $transaction = @$paymentdetails['transactionInfo'];
            if ($transaction) {
                $vippsstamp = strtotime($transaction['timeStamp']);
                $vippsamount= $transaction['amount'];
                $order->update_meta_data('_vipps_callback_timestamp',$vippsstamp);
                $order->update_meta_data('_vipps_amount',$vippsamount);
            }
        } catch (Exception $e) {
            $this->log(__("Error getting payment details from Vipps for order_id:",'woo-vipps') . $orderid . "\n" . $e->getMessage(), 'error');
            clean_post_cache($order->get_id());
            return $oldstatus;
        }

        $order->save();

        $address_set_by_poll = false;
        if (!$order->has_shipping_address() && !$order->has_billing_address()) {
            $checkoutpoll =  $order->get_meta('_vipps_checkout_poll');
            if ($checkoutpoll) {
                try {
                    $polldata =  $this->api->poll_checkout($checkoutpoll);
                    if (isset($polldata['shippingDetails'])) {
                        $this->set_order_shipping_details($order,$polldata['shippingDetails'], $polldata['userDetails']);
                        $address_set_by_poll = 1;
                    }
                } catch (Exception $e) {
                    $this->log(__("Cannot get address information for Vipps Checkout order:",'woo-vipps') . $orderid . "\n" . $e->getMessage(), 'error');
                }
            }
        }


        $statuschange = 0;
        if ($oldvippsstatus != $vippsstatus) {
            $statuschange = 1;
        }
        if ($oldstatus == 'pending' && $vippsstatus != 'initiated') {
            $statuschange = 1; // This is not supposed to happen, but if it does: The vippsstatus changed without the woo status changing.
        }

        // We have a completed order, but the callback haven't given us the payment details yet - so handle it.
        if ($statuschange && ($vippsstatus == 'authorized' || $vippsstatus=='complete') && $order->get_meta('_vipps_express_checkout')) {
            do_action('woo_vipps_express_checkout_get_order_status', $paymentdetails);
            // This is for orders using express checkout - set or update order info, customer info.  IOK 2018-05-29
            if (@$paymentdetails['shippingDetails']) {
                $this->set_order_shipping_details($order,$paymentdetails['shippingDetails'], $paymentdetails['userDetails']);
            } else if ($address_set_by_poll) {
                // NOOP - address is known
            } else {
                //  IN THIS CASE we actually need to cancel the order as we have no way of determining whose order this is.
                //  But first check to see if it has customer info! 
                // Cancel any orders where the Checkout session is dead and there is no address info available
                if (!$order->has_shipping_address() && !$order->has_billing_address()) {
                    $this->log(__("No shipping details from Vipps for express checkout for order id:",'woo-vipps') . ' ' . $orderid, 'error');
                    $sessiontimeout = time() - (60*90);
                    $then = intval($order->get_meta('_vipps_init_timestamp'));
                    if ($then < $sessiontimeout)  {
                        $this->log(sprintf(__("Order %d has no address info and any Vipps Checkout session is dead - have to cancel.", 'woo-vipps'), $order->get_id()));
                        $order->update_status('cancelled', __('Could not get address info for order from Vipps', 'woo-vipps'));
                        $order->save();
                    } else {
                        // NOOP - the vipps checkout session can still be active, so we need to let it be
                        $this->log(sprintf(__("No address information for order %d, but there still might be an active Vipps Checkout session for it, so do not cancel it.", 'woo-vipps'), $order->get_id()));
                    }
                }
                clean_post_cache($order->get_id());
                return $oldstatus; 
            }
        }

        if ($statuschange) {
            switch ($vippsstatus) {
                case 'authorized':
                    $this->payment_complete($order);
                    break;
                case 'complete':
                    $order->add_order_note(__( 'Payment captured directly at Vipps', 'woo-vipps' ));
                    $order = $this->update_vipps_payment_details($order, $paymentdetails); 
                    $order->payment_complete();
                    break;
                case 'cancelled':
                    $order->update_status('cancelled', __('Order failed or rejected at Vipps', 'woo-vipps'));
                    break;
            }
            $order->save();
            clean_post_cache($order->get_id());
            $newstatus = $order->get_status();
        }
        $Vipps->unlockOrder($order);
        return $newstatus;
    }

    // IOK 2020-01-20 Previously was just a debugging tool, then was used to update postmeta values. Now is used as the main source of info
    // about the order from Vipps; the previous side-effecting is now done by update_vipps_payment_details.
    public function get_payment_details($order) {
      // Then the details, which include the transaction history
      $result = $this->api->payment_details($order);

      if ($result) {
          // We would like e to have a single enum as the payment status like in the old API or v2 before march 2021, so find one and store it. IOK 2021-01-20
          $result['status'] = $this->get_payment_status_from_payment_details($result);

          // Add for backwards compatibility from the old order_status interface - last operations transactionInfo IOK 2021-01-20
          if (isset($result['transactionLogHistory'])){
              $log = $result['transactionLogHistory'];
              if (!empty($log)) {
                  $result['transactionInfo'] = $log[0];
              }
          }
      }

      return $result;
    }

    // Update the order with Vipps payment details, either passed or called using the API.
    public function update_vipps_payment_details ($order, $details = null) {
       if (!$details) $details = $this->get_payment_details($order);

       if ($details) {
           if (isset($details['transactionSummary'])) {
               $transactionSummary= $details['transactionSummary'];
               $order->update_meta_data('_vipps_status',$details['status']);
               $order->update_meta_data('_vipps_captured',$transactionSummary['capturedAmount']);
               $order->update_meta_data('_vipps_refunded',$transactionSummary['refundedAmount']);
               $order->update_meta_data('_vipps_capture_remaining',$transactionSummary['remainingAmountToCapture']);
               $order->update_meta_data('_vipps_refund_remaining',$transactionSummary['remainingAmountToRefund']);
           }
           $order->save();
       }
       return $order;
    }

    // IOK 2021-01-20 from March 2021 the order_status interface is removed; so we now need to interpret the payment history to find
    // out the order status.
    public function get_payment_status_from_payment_details($details) {

       $status = 'INITIATE';
       $statusknown = false;

       // We have to assume there is an transaction log history, but protect against having it missing anyway
       $log = isset($details['transactionLogHistory']) ? $details['transactionLogHistory'] : array();
       $synchronously = array_reverse($log);


       foreach ($synchronously as $op) {
         if ($statusknown) break; // Last iteration determined ths status

         $type = $op['operation'];
         $success = intval($op['operationSuccess']);
         switch($type) {
           case 'INITIATE': // Payment initiated, by merchant
                if (!$success) {
                   $status = 'FAILED';
                   $statusknown = true;
                }
                break;
           case 'RESERVE':  // Payment reserved, customer has completed payment
                if ($success) {
                   $status = 'RESERVE';        // 'authorized', but we need to check later ops
                } else {
                   $status = 'RESERVE_FAILED'; // a 'cancel' status, probably the last but need to check
                }
                break;
           case 'CAPTURE':  // Payment captured by merchant (after RESERVE, but can be partial)
                if ($success) {
                   $status = 'RESERVE';        // we know this at least. If the entire sum is captured, we could do a 'complete' status
                } else {
                   // This means nothing.
                } 
                break;
           case 'REFUND':   // Payment refunded, by merchant (after CAPTURE, but can be partial)
                if ($success) {
                   $status = 'RESERVE';        // we know this at least. If the entire sum is refunded, we could do a 'cancelled/refunded' status
                } else {
                   // This means nothing.
                } 
                break;
           case 'CANCEL':   // Payment cancelled by user in Vipps
                if ($success) {
                   $status = 'CANCEL';        // Successfully cancelled by user
                   $statusknown = true;
                } else {
                   // Is this even possible? I guess so! but we can't guess the state of the transaction here.
                } 

                break;
           case 'SALE':     // Payment captured with direct capture, by merchant (and is total)
                if ($success) {
                   $status = 'SALE';  // An order-complete status. However, final status isn't known because it may be refunded or cancelled after
                } else {
                    $status = 'FAILED'; // So direct capture didn't succed. Does this end the transaction though?
                } 
                break;
           case 'VOID':     // Payment cancelled by merchant, is total
                if ($success) {
                   $status = 'VOID';        // Successfully cancelled by merchant
                   $statusknown = true;
                } else {
                   // Is this even possible? I guess so! but we can't guess the state of the transaction here.
                } 
                break;
           default: 
                $this->log(sprintf("Unknown operation %s in transaction log for %s!", $op, $details['orderId']));
                break;
         }
       }


       return $status;
    }

    // Get the order status as defined by Vipps; if you have the payment details already, pass them. Will modify the order. IOK 2021-01-20
    public function get_vipps_order_status($order, $statusdata=null) {
        $vippsorderid = $order->get_meta('_vipps_orderid');
        if (!$vippsorderid) return null;
        if (!$statusdata) {
            try { 
                $statusdata = $this->get_payment_details($order);
            } catch (TemporaryVippsApiException $e) {
                $this->log(__('Could not get Vipps order status for order id:', 'woo-vipps') . ' ' . $order->get_id() . "\n" .$e->getMessage(),'error');
                return null;
            } catch (VippsAPIException $e) {
                $msg = __('Could not get Vipps order status','woo-vipps') . ' ' . $e->getMessage();
                $this->log($msg,'error');
                if (intval($e->responsecode) == 402) {
                    $this->log(__('Order does not exist at Vipps - cancelling','woo-vipps') . ' ' . $order->get_id(), 'warning');
                    return 'CANCEL'; 
                }
            } catch (Exception $e) {
                $msg = __('Could not get Vipps order status for order id:','woo-vipps') . ' ' . $order->get_id() . "\n" . $e->getMessage();
                $this->log($msg,'error');
                return null;
            }
        }
        if (!$statusdata) return null;

        $vippsstatus = $statusdata['status'];
        return $vippsstatus;
    }

    public function set_order_shipping_details($order,$shipping, $user) {
        global $Vipps;
        $done = $order->get_meta('_vipps_shipping_set');
        if ($done) return true;
        $order->update_meta_data('_vipps_shipping_set', true);
        $order->save(); // Limit the window for double shipping as much as possible.

        // We get different values from the normal callback and the Checkout callback, so be prepared for several results. IOK 2021-09-02
        $address = isset($shipping['address']) ? $shipping['address'] : $shipping;;

        $firstname = $user['firstName'];
        $lastname = $user['lastName'];
        $email = $user['email'];
        $phone = isset($user['mobileNumber']) ? $user['mobileNumber'] : "";
        if (isset($user['phoneNumber'])) $phone = $user['phoneNumber'];

        $shipping_firstname = $firstname;
        $shipping_lastname = $lastname;
        $addressline1 = isset($address['addressLine1']) ? $address['addressLine1'] : "";
        $addressline2 = isset($address['addressLine2']) ? $address['addressLine2'] : "";
        // This is the 'checkout'  callback
        if (isset($shipping['streetAddress'])) {
            $addressline1 = $shipping['streetAddress'];
            $addressline2 = "";
        }
        if (isset($shipping['firstName'])) {
            $shipping_firstname = $shipping['firstName'];
            $shipping_lastname = $shipping['lastName'];
        }
        // This apparently happens a lot IOK 2019-08-26 
        if ($addressline1 == $addressline2) $addressline2 = ''; 

        $city = "";
        if (isset($address['city'])) {
            $city = $address['city'];
        } elseif (isset($address['region'])) {
            $city = $address['region'];
        }
        
        $postcode= "";
        if (isset($address['zipCode'])) {
           $postcode = $address['zipCode'];
        } elseif (isset($address['postCode'])) {
            $postcode= $address['postCode'];
        } elseif (isset($address['postalCode'])){
            $postcode= $address['postalCode'];
        }

        // eCom returns "NORWAY", while Checkout returns "NO". Woo requires the two-letter country code.
        $vippscountry = $address['country'];
        if (strlen($vippscountry) == 2) {
            $country = strtoupper($vippscountry);
        } else {
            $country = $Vipps->country_to_code($vippscountry);
        }

        $order->set_billing_email($email);
        $order->set_billing_phone($phone);
        $order->set_billing_first_name($firstname);
        $order->set_billing_last_name($lastname);
        $order->set_billing_address_1($addressline1);
        $order->set_billing_address_2($addressline2);
        $order->set_billing_city($city);
        $order->set_billing_postcode($postcode);
        $order->set_billing_country($country);

        $order->set_shipping_first_name($shipping_firstname);
        $order->set_shipping_last_name($shipping_lastname);
        $order->set_shipping_address_1($addressline1);
        $order->set_shipping_address_2($addressline2);
        $order->set_shipping_city($city);
        $order->set_shipping_postcode($postcode);
        $order->set_shipping_country($country);

        // Allow the bearer of the current session to login (if not yet logged in) on the thankyou-screen IOK 2020-10-09
        if (WC()->session) {
            WC()->session->set('_vipps_order_finalized', $order->get_order_key());
        } else {
            $this->log(__("Finalizing an Express Checkout order with no session - this should probably not happen except when no callback has arrived", 'woo-vipps'), 'error');
        }
        $order->save();

        // This is *essential* to get VAT calculated correctly. That calculation uses the customer, which uses the session, which we will have restored at this point.IOK 2019-10-25
        WC()->customer->set_billing_location($country,'',$postcode,$city);
        WC()->customer->set_shipping_location($country,'',$postcode,$city);

        // Now do shipping, if it exists IOK 2021-09-02
        $method = isset($shipping['shippingMethodId']) ? $shipping['shippingMethodId'] : false;
        if ($method) {
            $shipping_rate=null;
            if (substr($method,0,1) != '$') {
                $shipping_rate = $this->get_legacy_express_checkout_shipping_rate($shipping);
            } else { 
                $shipping_table = $order->get_meta('_vipps_express_checkout_shipping_method_table');
                if (is_array($shipping_table) && isset($shipping_table[$method])) {
                    $shipping_rate = @unserialize($shipping_table[$method]);
                    if (!$shipping_rate) {
                        $this->log(sprintf(__("Vipps Express Checkout: Could not deserialize the chosen shipping method %s for order %d", 'woo-vipps'), $method, $order->get_id()), 'error');
                    } else {
                        // Empty this when done, but not if there was an error - let the merchant be able to debug. IOK 2020-02-14
                        $order->update_meta_data('_vipps_express_checkout_shipping_method_table', null);
                    }
                } 
            }
            $shipping_rate = apply_filters('woo_vipps_express_checkout_final_shipping_rate', $shipping_rate, $order, $shipping);
            $it = null;       
            if ($shipping_rate) {
                $it = new WC_Order_Item_Shipping();
                $it->set_shipping_rate($shipping_rate);
                $it->set_order_id( $order->get_id() );
                // This should actually have been done by the "set_shipping_rate" call above, but as of 3.9.2 at least, this does not work.
                // Therefore, do it manually/forcefully IOK 2020-02-17
                foreach($shipping_rate->get_meta_data() as $key => $value) {
                    $it->add_meta_data($key,$value,true);
                }
                $it->save();
                $order->add_item($it);
            }
            $order->save(); 
            $order->calculate_totals(true);
        }


        // If we have the 'expresscreateuser' thing set to true, we will create or assign the order here, as it is the first-ish place where we can.
        // If possible and safe, user will be logged in on the thankyou screen if they still are in the same sesssion. (see above, _vipps_order_finalized). IOK 2020-10-09
        // Do not call this if the function 'create_assign_user_on_vipps_callback' exists - this is the prior art for this created and used by Netthandelsgruppen, so ensure
        // that continues to work. IOK 2020-10-09
        $maybecreateuser = ($this->get_option('expresscreateuser') =='yes' && !function_exists('create_assign_user_on_vipps_callback')); 
        $maybecreateuser = apply_filters('woo_vipps_create_user_on_express_checkout', $maybecreateuser, $order, $user);
        if ($maybecreateuser) {
            global $Vipps;
            $customer = $Vipps->express_checkout_get_vipps_customer($order);
            if ($customer) {
                // This would have been used to ensure that we 'enroll' the users the same way as in the Login plugin. Unfortunately, the userId from express checkout isn't
                // the same as the 'sub' we get in Login so that must be a future feature. IOK 2020-10-09
                if (class_exists('VippsWooLogin') && $customer && !is_wp_error($customer) && !get_user_meta($customer->get_id(), '_vipps_phone',true)) {
                    update_user_meta($customer->get_id(), '_vipps_phone', $phone);
                    // update_user_meta($userid, '_vipps_id', $sub);
                    // update_user_meta($userid, '_vipps_just_connected', 1);
                }

                // Ensure we get any changes made to the order, as it will be re-saved later
                $order = wc_get_order($order->get_id());
            }
        }
        do_action('woo_vipps_set_order_shipping_details', $order, $shipping, $user);
        $order->save(); // I'm not sure why this is neccessary - but be sure.

    }
  
    // Previously, shipping rates were added by creating them here with metadata packed into the shippingMethodId. This is from 1.4.0 only 
    // used when the woo_vipps_shipping_methods filter has been overriden by the merchant. IOK 2020-02-14
    private function get_legacy_express_checkout_shipping_rate($shipping) {
        $method = $shipping['shippingMethodId'];
        list ($rate,$tax) = explode(";",$method);
        // The method ID is encoded in the rate ID but we apparently must still send it to the WC_Shipping_Rate constructor. IOK 2018-06-01
        // Unfortunately, Vipps won't accept long enought 'shipingMethodId' for us to actually stash all the information we need. IOK 2018-06-01
        list ($method,$product) = explode(":",$rate);
        $tax = wc_format_decimal($tax,'');
        $label = $shipping['shippingMethod'];
        $cost = wc_format_decimal($shipping['shippingCost'],''); // This is inclusive of tax
        $costExTax= wc_format_decimal($cost-$tax,'');
        $shipping_rate = new WC_Shipping_Rate($rate,$label,$costExTax,array(array('total'=>$tax)), $method, $product);
        $shipping_rate = apply_filters('woo_vipps_express_checkout_shipping_rate',$shipping_rate,$costExTax,$tax,$method,$product);
        return $shipping_rate;
    }

    // Handle the callback from Vipps eCom.
    public function handle_callback($result, $ischeckout=false) {
        global $Vipps;

        // These can have a prefix added, which may have changed, so we'll use our own search
        // to retrieve the order IOK 2018-05-03
        $vippsorderid = $result['orderId'];
        $orderid = $Vipps->getOrderIdByVippsOrderId($vippsorderid);

        $merchant= $result['merchantSerialNumber'];
       
        $me = $this->get_merchant_serial();

        if ($me != $merchant) {
            $this->log(__("Vipps callback with wrong merchantSerialNumber - might be forged",'woo-vipps') . " " .  $orderid, 'warning');
            return false;
        }

        $order = wc_get_order($orderid);
        if (!$order) {
            $this->log(__("Vipps callback for unknown order",'woo-vipps') . " " .  $orderid, 'warning');
            return false;
        }


        // This is for express checkout - some added protection
        $authtoken = $order->get_meta('_vipps_authtoken');
        if ($authtoken && !wp_check_password($_REQUEST['tk'], $authtoken)) {
            $this->log(__("Wrong auth token in callback from Vipps - possibly an attempt to fake a callback", 'woo-vipps'), 'warning');
            clean_post_cache($order->get_id());
            exit();
        }

        $transaction = @$result['transactionInfo'];
        if (!$transaction) {
            $this->log(__("Anomalous callback from vipps, handle errors and clean up",'woo-vipps'),'warning');
            clean_post_cache($order->get_id());
            return false;
        }
        $oldstatus = $order->get_status();
        if ($oldstatus != 'pending') {
            // Actually, we are ok with this order, abort the callback. IOK 2018-05-30
            clean_post_cache($order->get_id());
            return;
        }


        // If  the callback is late, and we have called get order status, and this is in progress, we'll log it and just drop the callback.
        // We do this because neither Woo nor WP has locking, and it isn't feasible to implement one portably. So this reduces somewhat the likelihood of race conditions
        // when callbacks happen while we are polling for results. IOK 2018-05-30
        if (!$Vipps->lockOrder($order)) {
            clean_post_cache($order->get_id());
            return;
        }
        if (@$result['shippingDetails'] && $order->get_meta('_vipps_express_checkout')) {
            $this->set_order_shipping_details($order,$result['shippingDetails'], $result['userDetails']);
        }

        $transactionid = @$transaction['transactionId'];
        $vippsstamp = strtotime($transaction['timeStamp']);
        $vippsamount = $transaction['amount'];
        $vippsstatus = $transaction['status'];

        // Create a signal file (if possible) so the confirm screen knows to check status IOK 2018-05-04
        // Not neccessary for Vipps Checkout though, as that is polled from outside the iframe. IOK 2021-09-02
        if (!$ischeckout) {
            try {
                $Vipps->createCallbackSignal($order,'ok');
            } catch (Exception $e) {
                // Could not create a signal file, but that's ok.
            }
        }
        $order->add_order_note(__('Vipps callback received','woo-vipps'));

        $errorInfo = @$result['errorInfo'];
        if ($errorInfo) {
            $this->log(__("Error message in callback from Vipps for order",'woo-vipps') . ' ' . $orderid . ' ' . $errorInfo['errorMessage'],'error');
            $order->add_order_note(sprintf(__("Error message from Vipps: %s",'woo-vipps'), $errorInfo['errorMessage']));
        }

        $order->update_meta_data('_vipps_callback_timestamp',$vippsstamp);
        $order->update_meta_data('_vipps_amount',$vippsamount);
        $order->update_meta_data('_vipps_status',$vippsstatus);  // Still uses the old status values - could call update_vipps_payment_details instead at the cost of an extra callIOK 2021-01-21

        if ($vippsstatus == 'RESERVED' || $vippsstatus == 'RESERVE') { // Apparently, the API uses *both* ! IOK 2018-05-03
            $this->payment_complete($order);
        } else if ($vippsstatus == 'SALE') {
          // Direct capture needs special handling because most of the meta values we use are missing IOK 2019-02-26
          $order->add_order_note(__( 'Payment captured directly at Vipps', 'woo-vipps' ));
          $order->payment_complete();
          $this->update_vipps_payment_details($order);
        } else {
            $order->update_status('cancelled', __( 'Payment cancelled at Vipps', 'woo-vipps' ));
        }
        $order->save();
        clean_post_cache($order->get_id());
        $Vipps->unlockOrder($order);
    }

    // Do the 'payment_complete' logic for non-SALE orders IOK 2020-09-22
    public function payment_complete($order,$transactionid='') {
            // Orders not needing processing can be autocaptured, so try to do so now.
            $autocapture = $this->maybe_complete_payment($order);
            if (!$autocapture) {
               add_filter( 'woocommerce_payment_complete_order_status', 
                    function ($status, $orderid, $order) {
                       return $this->after_vipps_order_status($order);
                    }, 99, 3);
               $order->add_order_note(__( 'Payment authorized at Vipps', 'woo-vipps' ));
            }
            $order->payment_complete();
    }

    // For the express checkout mechanism, create a partial order without shipping details by simulating checkout->create_order();
    // IOK 2018-05-25
    public function create_partial_order($ischeckout=false) {
        // This is neccessary for some plugins, like Yith Dynamic Pricing, that adds filters to get_price depending on whether or not is_checkout is true.
        // so basically, since we are impersonating WC_Checkout here, we should define this constant too. IOK 2020-07-03
        wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

         // In *some* cases you may need to actually load classes and reload the cart, because some plugins do not load when DOING_AJAX.
        do_action('woo_vipps_express_checkout_beforce_calculate_totals');
        WC()->cart->calculate_fees();
        WC()->cart->calculate_totals();
        do_action('woo_vipps_before_create_express_checkout_order', WC()->cart);

        $contents = WC()->cart->get_cart_contents();
        $contents = apply_filters('woo_vipps_create_express_checkout_cart_contents',$contents);
        
        $cart_hash = md5(json_encode(wc_clean($contents)) . WC()->cart->total);
        $order = new WC_Order();
        $order->set_status('pending');
        $order->set_payment_method($this);
        if ($ischeckout) {
            $order->set_created_via('Vipps Checkout');
            $order->set_payment_method_title('Vipps Checkout');
        } else {
            $order->set_created_via('Vipps Express Checkout');
            $order->set_payment_method_title('Vipps Express Checkout');
        }
        $dummy = __('Vipps Express Checkout', 'woo-vipps'); //  this is so gettext will find this string.
        $dummy = __('Vipps Checkout', 'woo-vipps'); //  this is so gettext will find this string.

        $order->update_meta_data('_vipps_express_checkout',1);

        $order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
        $order->set_currency( get_woocommerce_currency() );
        $order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
        $order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
        $order->set_customer_user_agent( wc_get_user_agent() );
        $order->set_discount_total( WC()->cart->get_discount_total()); 
        $order->set_discount_tax( WC()->cart->get_discount_tax() );
        $order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );

        // Use these methods directly - they should be safe.
        WC()->checkout->create_order_line_items( $order, WC()->cart);
        WC()->checkout->create_order_fee_lines( $order, WC()->cart);
        WC()->checkout->create_order_tax_lines( $order, WC()->cart);
        WC()->checkout->create_order_coupon_lines( $order, WC()->cart);
        $order->calculate_totals(true);

        // Added to support third-party plugins that wants to do stuff with the order before it is saved. IOK 2020-07-03
        do_action( 'woocommerce_checkout_create_order', $order, array()); 

        $orderid = $order->save(); 

        do_action('woo_vipps_express_checkout_order_created', $orderid);

        // Normally done by the WC_Checkout::create_order method, so call it here too. IOK 2018-11-19
        do_action('woocommerce_checkout_update_order_meta', $orderid, array());

        return $orderid;
    }

    protected function save_session_in_order($order) {
        // The callbacks from Vipps carry no session cookie, so we must store this in the order and use a special session handler when in a callback.
        // The Vipps class will restore the session from this on callbacks.
        // IOK 2019-10-21
        $sessioncookie = array();
        $sessionhandler = WC()->session;
        if ($sessionhandler && is_a($sessionhandler, 'WC_Session_Handler')) {
            WC()->session->save_data();
            $sessioncookie=$sessionhandler->get_session_cookie();
        }  else {
            // This actually can't happen. IOK 2020-04-08. Branch added for debugging only.
        }

        // If customer is actually logged in, take note IOK 2019-10-25
        if ($sessionhandler) WC()->session->set('express_customer_id',get_current_user_id());

        if (!empty($sessioncookie)) {
          // Customer id, session expiration, session-epiring and cookie-hash is the contents. IOK 2019-10-21
          $order->update_meta_data('_vipps_sessiondata',json_encode($sessioncookie));
          $order->save();
        }

    }

    // Using this internally to allow the 'enable' button or not. Checks SSL in addition to currency,
    // is valid_for_use can in principle run on a http version of the page; we only need to have https accessible for callbacks,
    // but if so, admin should definitely be HTTPS so we just check that. IOK 2018-06-06
    public function can_be_activated () {
        if (!is_ssl() && !preg_match("!^https!i",home_url())) return false;
        return true;
    }

    // Used by the ajax thing that 'sets activated' - checks that it can be activated and that all keys are present. IOK 2018-06-06
    function needs_setup() {
        if (!$this->can_be_activated()) return true;
        $required = array( 'merchantSerialNumber','clientId', 'secret',  'Ocp_Apim_Key_eCommerce'); 
        foreach ($required as $key) {
            if (!$this->get_option($key)) return true;
        }
        return false;
    }

   // Not present in WooCommerce until 3.4.0. Should be deleted when required versions are incremented. IOK 2018-10-26
    public function update_option( $key, $value = '' ) {
                if ( empty( $this->settings ) ) {
                        $this->init_settings();
                }
                $this->settings[ $key ] = $value;
                return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
    }


    public function admin_options() {
        if (!$this->can_be_activated()) {
            $this->update_option( 'enabled', 'no' );
        }
        ?>
            <h2><?php _e('Vipps','woo-vipps'); ?> <img style="float:right;max-height:40px" alt="<?php _e($this->title,'woo-vipps'); ?>" src="<?php echo $this->icon; ?>"></h2>
            <?php $this->display_errors(); ?>

            <?php 

            $currency = get_woocommerce_currency(); 
        if ($currency != 'NOK'): 
            ?> 
                <div class="inline error">
                <p><strong><?php _e( 'Vipps does not support your currency.', 'woo-vipps' ); ?></strong>
                <?php _e('Vipps will only be available as a payment option when currency is NOK', 'woo-vipps'); ?>                
                </p>
                </div>
        <?php endif; ?>

        <?php if (!is_ssl() &&  !preg_match("!^https!i",home_url())): ?>
                <div class="inline error">
                <p><strong><?php _e( 'Gateway disabled', 'woocommerce' ); ?></strong>:
                <?php _e( 'Vipps requires that your site uses HTTPS.', 'woo-vipps' ); ?>
                </p>
                </div>
        <?php endif; ?>
    
        <?php // We will only show the Vipps Checkout options if the user has activated the feature (thus creating the pages involved etc). IOK 2021-10-01
        $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);
        if (!$vipps_checkout_activated): ?>
        <div id="activate_vipps_checkout"  style="width:95%; background-color: white; border:2px solid #fe5b24; min-height:3rem; padding: 1rem 1rem 1rem 1rem; margin-top:2rem; margin-bottom: 1rem;font-weight:800">
                 <h2>Use Vipps Checkout for all purchases</h2>
          <p>Vipps checkout is a new service from Vipps which replaces the usual WooCommerce checkout page entirely, replacing it with a simplified checkout screen providing payment both with Vipps and credit card. Additionally, your customers will get the option of providing their address information using their Vipps app directly.</p>
          <p>To activate Vipps Checkout, just press the button below. Otherwise, Vipps will of course be available in the regular checkout screen; and you can also offer Vipps Express checkout from both the product pages and the shopping cart if you wish.</p>

          <div style="text-align:center">
                 <a class="button vipps-button vipps-orange" style="background-color: #fe5b24;color:white;border-color:#fe5b24" href="javascript:activate_vipps_checkout(1)">Yes, activate Vipps Checkout!</a>
                 <span style="width:30%; height:1rem;display:inline-block"></span>
                 <a class="button vipps-button secondary" href="javascript:activate_vipps_checkout(0)">No, thank you not right now anyway</a>
          </div>
<script>
function activate_vipps_checkout(yesno) {
 console.log("This!");
  var nonce = <?php echo json_encode(wp_create_nonce('woo_vipps_activate_checkout')); ?>;
  var referer = jQuery('input[name="_wp_http_referer"]').val();
  var args = { '_wpnonce' : nonce, '_wp_http_referer' : referer, 'activate': yesno, 'action' : 'woo_vipps_activate_checkout_page' }

  jQuery("#activate_vipps_checkout .button.vipps-button").css('cursor', 'wait');
  jQuery("#activate_vipps_checkout .button.vipps-button").prop('inactive', true);
  jQuery("#activate_vipps_checkout .button.vipps-button").prop('disabled', true);
  jQuery("#activate_vipps_checkout .button.vipps-button").addClass('disabled');


  jQuery.ajax(<?php echo json_encode(admin_url('admin-ajax.php')); ?>, { 
            method: 'POST',
            data: args,
            error: function (jqXHR, stat, err) {
            },
            success: function  (data, stat, jqXHR) {
            },
            complete: function (xhr, stat)  {
               document.body.style.cursor = 'default';
               jQuery("#activate_vipps_checkout .button.vipps-button").css('cursor', 'default');
               window.location.replace(window.location.pathname + window.location.search + window.location.hash);
            }
    }
   );
   console.log("That!");
   return false;
}
</script>


        </div>
        <?php endif; ?>
                <table class="form-table">
                <?php $this->generate_settings_html(); ?>
                </table> <?php
    }

    // Validate/mangle input fields 
    function validate_text_field ($key, $value) {
        if ($key != 'orderprefix') return parent::validate_text_field($key,$value);
        $value = preg_replace('![^a-zA-Z0-9]!','',$value);
        return $value;
    }
    function validate_checkbox_field($key,$value) {
        if ($key == 'testmode' && VIPPS_TEST_MODE) {
              return "yes";    
        } else if ($key == 'developermode' && VIPPS_TEST_MODE) {
              return "yes";    
        } else if ($key == 'enabled') { 
              if ($value && $this->can_be_activated()) return 'yes';
              return "no";
        }
        return parent::validate_checkbox_field($key,$value);
    }

    function process_admin_options () {
        // Handle options updates
        $saved = parent::process_admin_options();
        // We may have changed the number of form fields at this point if dev mode was changed 
        // from off to on,so re-initialize the form fields here. IOK 2019-09-03
        $this->init_form_fields();

        list($ok,$msg)  = $this->check_connection();
        if ($ok) {
                $this->adminnotify(__("Connection to Vipps OK", 'woo-vipps'));
        } else {
                $this->adminerr(__("Could not connect to Vipps", 'woo-vipps') . ": $msg");
        }

        // If enabling this, ensure the page in question exists
        if ($this->get_option('vipps_checkout_enabled') == 'yes') {
            $this->maybe_create_vipps_pages();
        }

        return $saved;
    }

    // This creates the Vipps Checkout page if neccessary. Called when user activates Vipps Checkout,
    // or when it is enabled for the first time.
    public function maybe_create_vipps_pages () {
            // This always true if we get here at all
            update_option('woo_vipps_checkout_activated', true, true);
            error_log("Adding the page if not exists");
            $checkoutid = wc_get_page_id('vipps_checkout');

            $makeit = !$checkoutid || ! get_post_status($checkoutid);
            if ($makeit) {
               error_log("It doesn't! Doing it");
               delete_option('woocommerce_vipps_checkout_page_id');
               WC_Install::create_pages();
               error_log("Did it!");
            } else {
               error_log("Page exists, everything is good. ");
            }
    }

    public function check_connection () {
        $at = $this->get_key();
        $s = $this->get_secret();
        $c = $this->get_clientid();
        if ($at && $s && $c) {
            try {
                $token = $this->api->get_access_token('force');
                update_option('woo-vipps-configured', 1, true);
                return array(true,'');

            } catch (Exception $e) {
                update_option('woo-vipps-configured', 0, true);
                return array(false, $e->getMessage());
            }
        }
        return array(false, ''); // No configuration
    } 

    public function log ($what,$type='info') {
        $logger = wc_get_logger();
        $context = array('source'=>'woo-vipps');
        $logger->log($type,$what,$context);
    }

    // Ensure chosen name gets used in the checkout page IOK 2018-09-12
    public function get_title() {
     $title = trim($this->get_option('title'));
     if (!$title) $title = __('Vipps','woo-vipps');
     return $title;
    }

    public function payment_fields() {
        // Use Billing Phone if it is required, otherwise ask for a phone IOK 2018-04-24
        // For v2 of the api, just let Vipps ask for then umber
        // IOK 2019-09-12 removed dead code only used for v1 of api
    print $this->get_option('description');
        return;
    }
    public function validate_fields() {
        return true;
    }


}
