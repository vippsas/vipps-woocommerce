<?php
/*
   Delegate class for talking to Vipps, encapsulating all the low-level behaviour and mapping error codes to exceptions


   This file is part of the WordPress plugin Checkout with Vipps for WooCommerce
   Copyright (C) 2018 WP Hosting AS

   Checkout with Vipps for WooCommerce is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   Checkout with Vipps for WooCommerce is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with this program.  If not, see <http://www.gnu.org/licenses/>.




 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
require_once(dirname(__FILE__) . "/VippsApi.class.php");

class WC_Gateway_Vipps extends WC_Payment_Gateway {
    public $form_fields = null;
    public $id = 'vipps';
    public $icon = ''; 
    public $has_fields = true;
    public $method_title = 'Vipps';
    public $title = 'Vipps';
    public $method_description = "";
    public $apiurl = null;
    public $api = null;
    public $supports = null;
    public $express_checkout_supported_product_types;

    public $captured_statuses;

    // Used to signal state to process_payment
    public $express_checkout = 0;
    public $tempcart = 0;
    private static $instance = null;  // This class uses the singleton pattern to make actions easier to handle

    // This returns the singleton instance of this class
    public static function instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
    } 
	    

    public function __construct() {

        // This can be set in your wp-config.php file. IOK 2018-05-31
        if (defined('VIPPS_TEST_MODE') && VIPPS_TEST_MODE) {
            $this->apiurl = 'https://apitest.vipps.no';
        } else {
            $this->apiurl = 'https://api.vipps.no';
        }

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
	$supported_types= array('simple','variable','variation');
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
            return set_url_scheme(home_url(),'https') . "/?wc-api=$forwhat&$tk&callback=";
        } else {
            return set_url_scheme(home_url(),'https') . "/wc-api/$forwhat?$tk&callback=";
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
            return set_url_scheme(home_url(),'https') . "/?vipps-consent-removal&callback=";
        } else {
            return set_url_scheme(home_url(),'https') . "/vipps-consent-removal/?callback=";
        }
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
        if ($captured) {
            return $this->maybe_refund_payment($orderid);
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

        // Now first check to see if we have captured anything, and if we haven't, just cancel order IOK 2018-05-07
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
    // they don't require payment. So we try to capture, and if successful, call "payment_complete". IOK 2018-09-21
    // do NOT call this unless the order is 'reserved' at Vipps!
    protected function maybe_complete_payment($order) {
        if ('vipps' != $order->get_payment_method()) return false;
        if ($order->needs_processing()) return false; // No auto-capture for orders needing processing
        // IOK 2018-10-03 when implementing partial capture, this must be modified.
        $captured = $order->get_meta('_vipps_captured'); 
        if ($captured) { 
          // IOK 2019-09-21 already captured, so just run 'payment complete'
          $order->payment_complete();
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
        $order->payment_complete();
        return true;
    }


    // This is the Woocommerce refund api called by the "Refund" actions. IOK 2018-05-11
    public function process_refund($orderid,$amount=null,$reason='') {
        $order = wc_get_order($orderid);

        $captured = $order->get_meta('_vipps_captured');
        $to_refund =  $order->get_meta('_vipps_refund_remaining');
        if (!$captured) {
            return new WP_Error('Vipps', __("Cannot refund through Vipps - the payment has not been captured yet.", 'woo-vipps'));
        }
        if ($amount*100 > $to_refund) {
            return new WP_Error('Vipps', __("Cannot refund through Vipps - the refund amount is too large.", 'woo-vipps'));
        }
        $ok = 0;
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
        $this->form_fields = array(
                'enabled' => array(
                    'title'       => __( 'Enable/Disable', 'woocommerce' ),
                    'label'       => __( 'Enable Vipps', 'woo-vipps' ),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no',
                    ),
                'orderprefix' => array(
                    'title' => __('Order-id Prefix', 'woo-vipps'),
                    'label'       => __( 'Order-id Prefix', 'woo-vipps' ),
                    'type'        => 'string',
                    'description' => __('An alphanumeric textstring to use as a prefix on orders from your shop, to avoid duplicate order-ids','woo-vipps'),
                    'default'     => 'Woo',
                    ),
                'merchantSerialNumber' => array(
                    'title' => __('Merchant Serial Number', 'woo-vipps'),
                    'label'       => __( 'Merchant Serial Number', 'woo-vipps' ),
                    'type'        => 'number',
                    'description' => __('Your merchant serial number from the Developer Portal - Applications tab, Saleunit Serial Number','woo-vipps'),
                    'default'     => '',
                    ),
                'clientId' => array(
                        'title' => __('Client Id', 'woo-vipps'),
                        'label'       => __( 'Client Id', 'woo-vipps' ),
                        'type'        => 'password',
                        'description' => __('Client Id from Developer Portal - Applications tab, "View Secret"','woo-vipps'),
                        'default'     => '',
                        ),
                'secret' => array(
                        'title' => __('Application Secret', 'woo-vipps'),
                        'label'       => __( 'Application Secret', 'woo-vipps' ),
                        'type'        => 'password',
                        'description' => __('Application secret from Developer Portal - Applications tab, "View Secret"','woo-vipps'),
                        'default'     => '',
                        ),
                'Ocp_Apim_Key_AccessToken' => array(
                        'title' => __('Subscription key for Access Token', 'woo-vipps'),
                        'label'       => __( 'Subscription key for Access Token', 'woo-vipps' ),
                        'type'        => 'password',
                        'description' => __('The Primary key for the Access Token subscription from your profile on the developer portal','woo-vipps'),
                        'default'     => '',
                        ),
                'Ocp_Apim_Key_eCommerce' => array(
                        'title' => __('Subscription key for eCommerce', 'woo-vipps'),
                        'label'       => __( 'Subscription key for eCommerce', 'woo-vipps' ),
                        'type'        => 'password',
                        'description' => __('The Primary key for the eCommerce API subscription from your profile on the developer portal','woo-vipps'),
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

                    );

        // This will be enabled on a later date . IOK 2018-06-05
        if (false) {
            $this->form_fields['expresscreateuser'] = array (
                    'title'       => __( 'Create new customers on Express Checkout', 'woo-vipps' ),
                    'label'       => __( 'Create new customers on Express Checkout', 'woo-vipps' ),
                    'type'        => 'checkbox',
                    'description' => __('Enable this to create and login new customers when using express checkout. Otherwise these will all be guest checkouts.', 'woo-vipps'),
                    'default'     => 'yes',
                    );
            $this->form_fields['vippslogin']  = array (
                    'title'       => __( 'Enable "Login with Vipps"', 'woo-vipps' ),
                    'label'       => __( 'Enable "Login with Vipps"', 'woo-vipps' ),
                    'type'        => 'checkbox',
                    'description' => __('Enable this to allow customers (and yourself!) to log in with Vipps', 'woo-vipps'),
                    'default'     => 'yes',
                    );
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
        if (!$this->can_be_activated()) return false;
        if (!parent::is_available()) return false;

        $ok = true;

        $currency = get_woocommerce_currency(); 
        if ($currency != 'NOK') {
            $ok = false;
        }

        $ok = apply_filters('woo_vipps_is_available', $ok, $this);
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
            $phone = trim($_POST['vippsphone']);
        }
        if (!$phone && isset($_POST['billing_phone'])) {
            $phone = trim($_POST['billing_phone']);
        }
        // No longer the case for V2 of the API
        if (false && !$phone) {
            wc_add_notice(__('You need to enter your phone number to pay with Vipps','woo-vipps') ,'error');
            return false;
        }

        $order = wc_get_order($order_id);
        $content = null;

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
        // Needed to ensure we have orderinfo
        if ($this->express_checkout) {
            $order->update_meta_data('_vipps_express_checkout',1);
        }
        $order->update_meta_data('_vipps_init_timestamp',$vippstamp);
        $order->update_meta_data('_vipps_status','INITIATE'); // INITIATE right now
        $order->add_order_note(__('Vipps payment initiated','woo-vipps'));
        $order->add_order_note(__('Awaiting Vipps payment confirmation','woo-vipps'));

        //  Annotate this order as a single-product express checkout thing. This is done to ensure the 'real' cart is not emptied after a successful purchase. IOK 2019-10-01
	if ($this->tempcart) {
           $order->update_meta_data('_vipps_single_product_express',true); 
	}

        $order->save();

        // Create a signal file that we can check without calling wordpress to see if our result is in IOK 2018-05-04
        try {
            $Vipps->createCallbackSignal($order);
        } catch (Exception $e) {
            // Could not create a signal file, but that's ok.
        }

        // If we have a temporary cart for a single product checkout, this will *replace* the current cart. In this case, we need to save the current cart,
        // and restore it on return from Vipps.
        try {
             // This actually isn't neccessary *unless* there is a tempcart, so this could be rewritten in the future.
             // It saves the *current* order which will not include a single-product to be bought. On purchase, that cart will replace
             // the 'current' cart, so that this one will be empty and must be restored (both on success and failure). IOK 2019-10-02
             // IOK 2018-12-10 Finally implement this logic: Only save/restore the cart when the cart is temporary.
             if ($this->tempcart)  $Vipps->save_cart($order); 
         } catch (Exception $e) {
         }
         // Emptying the current cart isn't strictly neccessary (and if done, we need to save the cart above) because it will be emptied on 
         // order complete. If this is a temporary cart for a single-product express checkout purchase; this cart will be *replaced* by the
         // single product cart. If it isn't, the cart will be emptied on purchase completion. For now I'm keeping this logic just to avoid
         // exhaustive testing. IOK 2018-10-02
        do_action('woo_vipps_before_redirect_to_vipps',$order_id);

        // This will send us to a receipt page where we will do the actual work. IOK 2018-04-20
        return array('result'=>'success','redirect'=>$url);
    }


    // This tries to capture a Vipps payment, and resets the status to 'on-hold' if it fails.  IOK 2018-05-07
    public function maybe_capture_payment($orderid) {
        $order = wc_get_order($orderid);
        if ('vipps' != $order->get_payment_method()) return false;
        $ok = 0;
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
        $total = round($order->get_total()*100);
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

        $total = round($order->get_total()*100);
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

        $ok = 0;
        try {
            $ok = $this->refund_payment($order,$refundvalue,'cents');
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

        // Update status from Vipps, but ignore errors IO 2018-05-07
        try {
            $this->get_vipps_order_status($order,false);
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


    // Check status of order at Vipps, in case the callback has been delayed or failed.   
    // Should only be called if in status 'pending'; it will modify the order when status changes.
    public function callback_check_order_status($order) {
        $orderid = $order->get_id();

        clean_post_cache($order->get_id());
        $order = wc_get_order($orderid); // Ensure a fresh copy is read.

        $oldstatus = $order->get_status();
        $newstatus = $oldstatus;
        $vippsstatus = $this->get_vipps_order_status($order,'iscallback');

        switch ($vippsstatus) { 
            case 'INITIATE':
            case 'REGISTER':
            case 'REGISTERED':
                $newstatus = 'pending';
                break;
            case 'RESERVE':
            case 'RESERVED':
                $newstatus = 'on-hold';
                break;
            case 'SALE':
                $newstatus = 'processing'; 
                break;
            case 'CANCEL':
            case 'CANCELLED':
            case 'VOID':
            case 'AUTOREVERSAL':
            case 'AUTOCANCEL':
            case 'RESERVE_FAILED':
            case 'FAILED':
            case 'REJECTED':
                $newstatus = 'cancelled'; 
                break;
        }

        // If we are in the process of getting a callback from vipps, don't update anything. Currently, Woo/WP has no locking mechanism,
        // and it isn't feasible to implement one portably. So this reduces somewhat the likelihood of races when this method is called 
        // and callbacks happen at the same time.
        if(get_transient('order_callback_'.$orderid)) return $oldstatus;

        $statuschange = 0;
        if ($newstatus != $oldstatus) {
            // Again, because we have no way of handling locks portably in Woo or WP yet, we must reduce the risk of race conditions by doing a 'fast' operation 
            // If the status hasn't changed this is probably not the case, so we'll only do it in this case. IOK 2018-05-30
            set_transient('order_query_'.$orderid, 1, 30);
            $statuschange = 1;
        }


        // We have a completed order, but the callback haven't given us the payment details yet - so handle it.
        if ($statuschange && ($newstatus == 'on-hold' || $newstatus=='processing') && $order->get_meta('_vipps_express_checkout')) {
            $this->log(__("Express checkout - no callback yet, so getting payment details from Vipps for order id:", 'woo-vipps') . ' ' . $orderid, 'warning');

            try {
                $statusdata = $this->api->payment_details($order);
                do_action('woo_vipps_express_checkout_get_order_status', $statusdata);
            } catch (Exception $e) {
                $this->log(__("Error getting payment details from Vipps for express checkout for order_id:",'woo-vipps') . $orderid . "\n" . $e->getMessage(), 'error');
                clean_post_cache($order->get_id());
                return $oldstatus; 
            }
            // This is for orders using express checkout - set or update order info, customer info.  IOK 2018-05-29
            if (@$statusdata['shippingDetails']) {
                $this->set_order_shipping_details($order,$statusdata['shippingDetails'], $statusdata['userDetails']);
            } else {
                $this->log(__("No shipping details from Vipps for express checkout for order id:",'woo-vipps') . ' ' . $orderid, 'error');
                clean_post_cache($order->get_id());
                return $oldstatus; 
            }
        }

        if ($oldstatus != $newstatus) {
            switch ($newstatus) {
                case 'on-hold':
                    // Orders not needing processing can be autocaptured, so try to do so now. This will reduce stock and mark the order 'completed' IOK 2019-09-21
                    $autocapture = $this->maybe_complete_payment($order);
                    if (!$autocapture) {
                      wc_reduce_stock_levels($order->get_id());
                      $authorized_state = $this->after_vipps_order_status($order);
                      $order->update_status($authorized_state, __( 'Payment authorized at Vipps', 'woo-vipps' ));
                    }
                    break;
                case 'processing':
                    $order->payment_complete();
                    break;
                case 'cancelled':
                    $order->update_status('cancelled', __('Order failed or rejected at Vipps', 'woo-vipps'));
                    break;
            }
            $order->save();
            clean_post_cache($order->get_id());
        }
        // Ensure this is gone so any callback can happen. IOK 2018-05-30
        delete_transient('order_query_'.$orderid);
        return $newstatus;
    }

    // This is primarily for debugging right now. Can be made callable to update the order status directley with Vipps status. IOK 2019-09-21
    // IOK 2018-12-19 now as a side-effect refreshes all the postmeta values from Vipps
    public function get_payment_details($order) {
      // First, update the Vips order status
      $status = $this->get_vipps_order_status($order,false);
      // Then the details, which include the transaction history
      $result = $this->api->payment_details($order);
      if ($result) {
        $result['status'] = $status;
        if (isset($result['transactionSummary'])) {
        $transactionSummary= $result['transactionSummary'];
        $order->update_meta_data('_vipps_captured',$transactionSummary['capturedAmount']);
        $order->update_meta_data('_vipps_refunded',$transactionSummary['refundedAmount']);
        $order->update_meta_data('_vipps_capture_remaining',$transactionSummary['remainingAmountToCapture']);
        $order->update_meta_data('_vipps_refund_remaining',$transactionSummary['remainingAmountToRefund']);
        }
      }
      $order->save();
      return $result;
    }

    // Get the order status as defined by Vipps. If 'iscallback' is true, set timestamps etc as if this was a Vipps callback. IOK 2018-05-04 
    public function get_vipps_order_status($order, $iscallback=0) {
        $vippsorderid = $order->get_meta('_vipps_orderid');
        if (!$vippsorderid) return null;
        try { 
            $statusdata = $this->api->order_status($order);
        } catch (TemporaryVippsApiException $e) {
            $this->log(__('Could not get Vipps order status for order id:', 'woo-vipps') . ' ' . $order->get_id() . "\n" .$e->getMessage(),'error');
            if (!$iscallback) $this->adminerr(__('Vipps is temporarily unavailable.','woo-vipps') . ' ' . $e->getMessage());
            return null;
        } catch (VippsAPIException $e) {
            $msg = __('Could not get Vipps order status','woo-vipps') . ' ' . $e->getMessage();
            $this->log($msg,'error');
            if (intval($e->responsecode) == 402) {
                $this->log(__('Order does not exist at Vipps - cancelling','woo-vipps') . ' ' . $order->get_id(), 'warning');
                return 'CANCEL'; 
            }
            if (!$iscallback) $this->adminerr($msg);
        } catch (Exception $e) {
            $msg = __('Could not get Vipps order status for order id:','woo-vipps') . ' ' . $order->get_id() . "\n" . $e->getMessage();
            $this->log($msg,'error');
            if (!$iscallback) $this->adminerr($msg);
            return null;
        }
        if (!$statusdata) return null;

        $transaction = @$statusdata['transactionInfo'];
        if (!$transaction) return null;
        $vippsstatus = $transaction['status'];
        $vippsstamp = strtotime($transaction['timeStamp']);
        $vippsamount= $transaction['amount'];

        if ($iscallback) {
            $order->update_meta_data('_vipps_callback_timestamp',$vippsstamp);
        }
        $order->update_meta_data('_vipps_amount',$vippsamount);
        $order->update_meta_data('_vipps_status',$vippsstatus); // should be RESERVED or REJECTED mostly, could be FAILED etc. IOK 2018-04-24
        $order->save();

        return $vippsstatus;
    }

    public function set_order_shipping_details($order,$shipping, $user) {
        global $Vipps;
        $address = $shipping['address'];

        $firstname = $user['firstName'];
        $lastname = $user['lastName'];
        $phone = $user['mobileNumber'];
        $email = $user['email'];

        $addressline1 = $address['addressLine1'];
        $addressline2 = @$address['addressLine2'];
        $vippscountry = $address['country'];
        $city = $address['city'];
        $postcode= @$address['zipCode'];
        if (isset($address['postCode'])) {
            $postcode= $address['postCode'];
        } elseif (isset($address['postalCode'])){
            $postcode= $address['postalCode'];
        }
        $country = $Vipps->country_to_code($vippscountry);

        $order->set_billing_email($email);
        $order->set_billing_phone($phone);
        $order->set_billing_first_name($firstname);
        $order->set_billing_last_name($lastname);
        $order->set_billing_address_1($addressline1);
        $order->set_billing_address_2($addressline2);
        $order->set_billing_city($city);
        $order->set_billing_postcode($postcode);
        $order->set_billing_country($country);

        $order->set_shipping_first_name($firstname);
        $order->set_shipping_last_name($lastname);
        $order->set_shipping_address_1($addressline1);
        $order->set_shipping_address_2($addressline2);
        $order->set_shipping_city($city);
        $order->set_shipping_postcode($postcode);
        $order->set_shipping_country($country);
        $order->save();

        // Because Woocommerce is so difficult wrt shipping, we will have 'packed' some data into the
        // method name - including any tax.
        $method = $shipping['shippingMethodId'];
        list ($rate,$tax) = explode(";",$method);
        // The method ID is encoded in the rate ID but we apparently must still send it to the WC_Shipping_Rate constructor. IOK 2018-06-01
        // Unfortunately, Vipps won't accept long enought 'shipingMethodId' for us to actually stash all the information we need. IOK 2018-06-01
        list ($method,$product) = explode(":",$rate);
        $tax = sprintf("%.2F",$tax);
        $label = $shipping['shippingMethod'];
        $cost = sprintf("%.2F",$shipping['shippingCost']); // This is inclusive of tax
        $costExTax= sprintf("%.2F",$cost-$tax);

        $shipping_rate = new WC_Shipping_Rate($rate,$label,$costExTax,array(array('total'=>$tax)), $method);
        $shipping_rate = apply_filters('woo_vipps_express_checkout_shipping_rate',$shipping_rate,$costExTax,$tax,$method,$product);
        if ($shipping_rate) {
            $it = new WC_Order_Item_Shipping();
            $it->set_shipping_rate($shipping_rate);
            $it->set_order_id( $order->get_id() );
            $order->add_item($it);
        }
        $order->save(); 
        $order->calculate_totals(true);

        do_action('woo_vipps_set_order_shipping_details', $order, $shipping, $user);

        $order->save(); // I'm not sure why this is neccessary - but be sure.
    }

    // Handle the callback from Vipps.
    public function handle_callback($result) {
        global $Vipps;

        // These can have a prefix added, which may have changed, so we'll use our own search
        // to retrieve the order IOK 2018-05-03
        $vippsorderid = $result['orderId'];
        $orderid = $Vipps->getOrderIdByVippsOrderId($vippsorderid);

        $merchant= $result['merchantSerialNumber'];
        $me = $this->get_option('merchantSerialNumber');
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

        // If  the callback is late, and we have called get order status, and this is in progress, we'll log it and just drop the callback.
        // We do this because neither Woo nor WP has locking, and it isn't feasible to implement one portably. So this reduces somewhat the likelihood of race conditions
        // when callbacks happen while we are polling for results. IOK 2018-05-30
        if(get_transient('order_query_'.$orderid))  {
            $this->log(__('Vipps callback ignored because we are currently updating the order using get order status', 'woo-vipps') . ' ' . $orderid, 'notice');
            clean_post_cache($order->get_id());
            return;
        }


        $oldstatus = $order->get_status();
        if ($oldstatus != 'pending') {
            // Actually, we are ok with this order, abort the callback. IOK 2018-05-30
            $this->log(__('Vipps callback recieved for order no longer pending. Ignoring callback.','woo-vipps') . ' ' . $orderid, 'notice');
            clean_post_cache($order->get_id());
            return;
        }

        // Entering critical area, so start with the fake locking mentioned above. IOK 2018-05-30
        set_transient('order_callback_'.$orderid,1, 60);

        if (@$result['shippingDetails']) {
            $this->set_order_shipping_details($order,$result['shippingDetails'], $result['userDetails']);
        }

        $transactionid = $transaction['transactionId'];
        $vippsstamp = strtotime($transaction['timeStamp']);
        $vippsamount = $transaction['amount'];
        $vippsstatus = $transaction['status'];

        // Create a signal file (if possible) so the confirm screen knows to check status IOK 2018-05-04
        try {
            $Vipps->createCallbackSignal($order,'ok');
        } catch (Exception $e) {
            // Could not create a signal file, but that's ok.
        }
        $order->add_order_note(__('Vipps callback received','woo-vipps'));

        $errorInfo = @$result['errorInfo'];
        if ($errorInfo) {
            $this->log(__("Error message in callback from Vipps for order",'woo-vipps') . ' ' . $orderid . ' ' . $errorInfo['errorMessage'],'error');
            $order->add_order_note($errorInfo['errorMessage']);
        }

        $order->update_meta_data('_vipps_callback_timestamp',$vippsstamp);
        $order->update_meta_data('_vipps_amount',$vippsamount);
        $order->update_meta_data('_vipps_status',$vippsstatus); 

        if ($vippsstatus == 'RESERVED' || $vippsstatus == 'RESERVE') { // Apparenlty, the API uses *both* ! IOK 2018-05-03
            // Orders not needing processing can be autocaptured, so try to do so now. This will reduce stock and mark the order 'completed' IOK 2019-09-21
            $autocapture = $this->maybe_complete_payment($order);
            if (!$autocapture) {
               wc_reduce_stock_levels($order->get_id());
               $authorized_state = $this->after_vipps_order_status($order);
               $order->update_status($authorized_state, __( 'Payment authorized at Vipps', 'woo-vipps' ));
            }
        } else {
            $order->update_status('cancelled', __( 'Payment cancelled at Vipps', 'woo-vipps' ));
        }
        $order->save();
        clean_post_cache($order->get_id());
        delete_transient('order_callback_',$orderid);
    }

    // For the express checkout mechanism, create a partial order without shipping details by simulating checkout->create_order();
    // IOK 2018-05-25
    public function create_partial_order($thecart=null) {
        if (!$thecart) {
         $thecart = WC()->cart;
         $contents = $thecart->get_cart_for_session();
        } else {
            // if ( ! did_action( 'woocommerce_cart_loaded_from_session' ) ) 
         $contents = $thecart->get_cart_contents();
        }
        $cart_hash = md5(json_encode(wc_clean($contents)) . $thecart->total);
        $order = new WC_Order();
        $order->set_status('pending');
        $order->set_payment_method($this);
	$order->set_created_via('Vipps Express Checkout');
	$order->set_payment_method_title('Vipps Express Checkout');
	$dummy = __('Vipps Express Checkout', 'woo-vipps');

        $order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
        $order->set_currency( get_woocommerce_currency() );
        $order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
        $order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
        $order->set_customer_user_agent( wc_get_user_agent() );
        $order->set_discount_total( $thecart->get_discount_total()); 
        $order->set_discount_tax( $thecart->get_discount_tax() );
        $order->set_cart_tax( $thecart->get_cart_contents_tax() + $thecart->get_fee_tax() );

        // Use these methods directly - they should be safe.
        WC()->checkout->create_order_line_items( $order, $thecart);
        WC()->checkout->create_order_fee_lines( $order, $thecart);
        WC()->checkout->create_order_tax_lines( $order, $thecart);
        WC()->checkout->create_order_coupon_lines( $order, $thecart);
        $order->calculate_totals(true);
        $orderid = $order->save(); 

        // Normally done by the WC_Checkout::create_order method, so call it here too. IOK 2018-11-19
        do_action('woocommerce_checkout_update_order_meta', $orderid, array());

        return $orderid;
    }

    // Using this internally to allow the 'enable' button or not. Checks SSL in addition to currency,
    // is valid_for_use can in principle run on a http version of the page; we only need to have https accessible for callbacks,
    // but if so, admin should definitely be HTTPS so we just check that. IOK 2018-06-06
    public function can_be_activated () {
        if (!is_ssl() && !preg_match("!^https!i",home_url())) return false;
        if (!ini_get('allow_url_fopen')) return false;
        return true;
    }

    // Used by the ajax thing that 'sets activated' - checks that it can be activated and that all keys are present. IOK 2018-06-06
    function needs_setup() {
        if (!$this->can_be_activated()) return true;
        $required = array( 'merchantSerialNumber','clientId', 'secret', 'Ocp_Apim_Key_AccessToken', 'Ocp_Apim_Key_eCommerce'); 
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
    
        <?php if (! ini_get('allow_url_fopen')): ?>
                <div class="inline error">
                <p><strong><?php _e( 'Gateway disabled', 'woocommerce' ); ?></strong>:
                <?php _e( 'Vipps requires that your PHP configuraton has <b>allow_url_fopen</b> set to true.', 'woo-vipps' ); ?>
                </p>
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
        if ($key != 'enabled') return parent::validate_checkbox_field($key,$value);
        if ($value && $this->can_be_activated()) return 'yes';
        return 'no';
    }

    function process_admin_options () {
        // Handle options updates
        $saved = parent::process_admin_options();

        $at = $this->get_option('Ocp_Apim_Key_AccessToken');
        $s = $this->get_option('secret');
        $c = $this->get_option('clientId');
        if ($at && $s && $c) {
            try {
                $token = $this->api->get_access_token('force');
                $this->adminnotify(__("Connection to Vipps OK", 'woo-vipps'));
            } catch (Exception $e) {
                $msg = $e->getMessage();
                $this->adminerr(__("Could not connect to Vipps", 'woo-vipps') . ": $msg");
            }
        }

        return $saved;
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
