<?php
/*
   This class is for hooks and plugin managent, and is instantiated as a singleton and set globally as $Vipps. IOK 2018-02-07
   For WP-specific interactions.

 */
require_once(dirname(__FILE__) . "/exceptions.php");

class Vipps {
    /* This directory stores the files used to speed up the callbacks checking the order status. IOK 2018-05-04 */
    private $callbackDirname = 'wc-vipps-status';
    private static $instance = null;

    function __construct() {
    }

    public static function instance()  {
        if (!static::$instance) static::$instance = new Vipps();
        return static::$instance;
    }

    public function init () {
        // IOK move this to a wp-cron job so it doesn't run like every time 2018-05-03
        $this->cleanupCallbackSignals();
        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
        add_action('wp_login', array($this, 'wp_login'), 10, 2);
    }

    public function admin_init () {
        // Stuff for the Order screen
        add_action('woocommerce_order_item_add_action_buttons', array($this, 'order_item_add_action_buttons'), 10, 1);
        add_action('save_post', array($this, 'save_order'), 10, 3);

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));

        // Keep admin notices during redirects IOK 2018-05-07
        add_action('admin_notices',array($this,'stored_admin_notices'));
    }

    public function admin_menu () {
    }

    public function add_meta_boxes () {
        // Metabox showing order status at Vipps IOK 2018-05-07
        add_meta_box( 'vippsdata', __('Vipps','vipps'), array($this,'add_vipps_metabox'), 'shop_order', 'side', 'core' );
    }

    public function wp_enqueue_scripts() {
        wp_enqueue_script('vipps',plugins_url('js/vipps.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/vipps.js"), 'true');
        wp_enqueue_style('vipps',plugins_url('css/vipps.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/vipps.css"));
    }

    public function log ($what,$type='info') {
        $logger = wc_get_logger();
        $context = array('source','Vipps Woo Gateway');
        $logger->log($type,$what,$context);
    }


    // If we have admin-notices that we haven't gotten a chance to show because of
    // a redirect, this method will fetch and show them IOK 2018-05-07
    public function stored_admin_notices() {
        $stored = get_transient('_vipps_save_admin_notices',$notices, 5*60);
        if ($stored) {
            delete_transient('_vipps_save_admin_notices');
            print $stored;
        }
    }

    // Show the express button if reasonable to do so
    public function cart_express_checkout_button() {
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        $gw = new WC_Gateway_Vipps();
        if ($gw->get_option('cartexpress') == 'yes') { 
            $url = $this->express_checkout_url();
            $url = wp_nonce_url($url,'express','sec');
            echo ' <a href="'.$url.'" class="button wc-forward vipps-express-checkout">' . __('Buy now with Vipps!', 'vipps') . '</a>';
        }
    }


    // A metabox for showing Vipps information about the order. IOK 2018-05-07
    public function add_vipps_metabox ($post) {
        $order = new WC_Order($post);
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') return;
        $init =  intval($order->get_meta('_vipps_init_timestamp'));
        $callback =  intval($order->get_meta('_vipps_callback_timestamp'));
        $capture =  intval($order->get_meta('_vipps_capture_timestamp'));
        $refund =  intval($order->get_meta('_vipps_refund_timestamp'));
        $cancel =  intval($order->get_meta('_vipps_cancel_timestamp'));

        $status = $order->get_meta('_vipps_status');
        $total = intval($order->get_meta('_vipps_amount'));
        $captured = intval($order->get_meta('_vipps_captured'));
        $refunded = intval($order->get_meta('_vipps_refunded'));

        $capremain = intval($order->get_meta('_vipps_capture_remaining'));
        $refundremain = intval($order->get_meta('_vipps_refund_remaining'));

        print "<table border=0><thead></thead><tbody>";
        print "<tr><td>Status</td>";
        print "<td align=right>" . htmlspecialchars($status);print "</td></tr>";
        print "<tr><td>Amount</td><td align=right>" . sprintf("%0.2f ",$total/100); print "NOK"; print "</td></tr>";
        print "<tr><td>Captured</td><td align=right>" . sprintf("%0.2f ",$captured/100); print "NOK"; print "</td></tr>";
        print "<tr><td>Refunded</td><td align=right>" . sprintf("%0.2f ",$refunded/100); print "NOK"; print "</td></tr>";

        print "<tr><td>Vipps initiated</td><td align=right>";if ($init) print date('Y-m-d H:i:s',$init); print "</td></tr>";
        print "<tr><td>Vipps response </td><td align=right>";if ($callback) print date('Y-m-d H:i:s',$callback); print "</td></tr>";
        print "<tr><td>Vipps capture </td><td align=right>";if ($capture) print date('Y-m-d H:i:s',$capture); print "</td></tr>";
        print "<tr><td>Vipps refund</td><td align=right>";if ($refund) print date('Y-m-d H:i:s',$refund); print "</td></tr>";
        print "<tr><td>Vipps cancelled</td><td align=right>";if ($cancel) print date('Y-m-d H:i:s',$cancel); print "</td></tr>";
        print "</tbody></table>";
    }

    // This function will create a file with an obscure filename in the $callbackDirname directory.
    // When initiating payment, this file will be created with a zero value. When the response is reday,
    // it will be rewritten with the value 1.
    // This function can fail if we can't write to the directory in question, in which case, return null and
    // to the check with admin-ajax instead. IOK 2018-05-04
    public function createCallbackSignal($order,$ok=0) {
        $fname = $this->callbackSignal($order);
        if (!$fname) return null;
        if ($ok) {
            @file_put_contents($fname,"1");
        }else {
            @file_put_contents($fname,"0");
        }
        if (is_file($fname)) return $fname;
        return null;
    }

    //Helper function that produces the signal file name for an order IOK 2018-05-04
    public function callbackSignal($order) {
        $dir = $this->callbackDir();
        if (!$dir) return null;
        $fname = 'vipps-'.md5($order->get_order_key() . $order->get_meta('_vipps_transaction'));
        return $dir . DIRECTORY_SEPARATOR . $fname;
    }
    // URL of the above product thing
    public function callbackSignalURL($signal) {
        if (!$signal) return "";
        $uploaddir = wp_upload_dir();
        return $uploaddir['baseurl'] . '/' . $this->callbackDirname . '/' . basename($signal);
    }

    // Clean up old signals. IOK 2018-05-04. They should contain no useful information, but still. IOK 2018-05-04
    public function cleanupCallbackSignals() {
        $dir = $this->callbackDir();
        if (!is_dir($dir)) return;
        $signals = scandir($dir);
        $now = time();
        foreach($signals as $signal) {
            $path = $dir .  DIRECTORY_SEPARATOR . $signal;
            if (is_dir($path)) continue;
            if (is_file($path)) {
                $age = @filemtime($path);
                $halfhour = 30*60*60;
                if (($age+$halfhour) < $now) {
                    @unlink($path);
                }
            }
        }
    }

    // Returns the name of the callback-directory, or null if it doesn't exist. IOK 2018-05-04
    private function callbackDir() {
        $uploaddir = wp_upload_dir();
        $base = $uploaddir['basedir'];
        $callbackdir = $base . DIRECTORY_SEPARATOR . $this->callbackDirname;
        if (is_dir($callbackdir)) return $callbackdir;
        $ok = mkdir($callbackdir, 0755);
        if ($ok) return $callbackdir; 
        return null;
    }


    // Because the prefix used to create the Vipps order id is editable
    // by the user, we will store that as a meta and use this for callbacks etc.
    public function getOrderIdByVippsOrderId($vippsorderid) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_vipps_orderid' AND meta_value = %s", $vippsorderid) );
    }

     
    // This will, in a callback from express checkout, or Vipps login, create a user as a Vipps user. If the user exists 
    // it will be updated with the relevant information from Vipps. Returns the user ID, not a user object. IOK 2018-05-29
    public function createVippsUser($userdetails,$address) {
        if (!$userdetails) return null;
        if (!$address) $address = array();

        $email = $userdetails['email'];
        $user = get_user_by('email',$email);
        $userdata = array();
        $userid = 0;

        if ($user) {
            $userid = $user->ID;
        } else {
            $username = sanitize_user( current( explode( '@', $email ) ), true );
            $append     = 1;
            $o_username = $username;
            while ( username_exists( $username ) ) {
                $username = $o_username . $append;
                $append++;
            }
            $password = wp_generate_password();
            $userid =  wc_create_new_customer($email, $username, $password);

        }
        if (is_wp_error($userid)) {
            throw new VippsApiException($userid->get_error_message());
        }

        $userdata['first_name'] = $userdetails['firstName'];
        $userdata['last_name'] = $userdetails['lastName'];
        $userdata['display_name'] =  $userdetails['firstName'] . ' ' . $userdetails['lastName'];
        $userdata['ID'] = $userid;
        $updateresult = wp_update_user($userdata);

        // Update all metadata for both new and old users. IOK 2018-05-29
        update_user_meta( $userid, "vipps_id", $userdetails['userId']);
        update_user_meta( $userid, "billing_first_name", $userdetails['firstName']);
        update_user_meta( $userid, "billing_last_name", $userdetails['lastName']);
        update_user_meta( $userid, "billing_phone", $userdetails['mobileNumber']);
        update_user_meta( $userid, "billing_email", $email);
        update_user_meta( $userid, "billing_address_1", $address['addressLine1']);

        if ($address['addressLine2']) update_user_meta( $userid, "billing_address_2", $address['addressLine2']);
        if ($address['city']) update_user_meta( $userid, "billing_city", $address['city']);
        if ($address['zipCode']) update_user_meta( $userid, "billing_postcode", $address['zipCode']);
        if ($address['postCode']) update_user_meta( $userid, "billing_postcode", $address['postCode']); // *Both* are used by different calls!
        if ($address['country']) update_user_meta( $userid, "billing_country", $address['country']);

        return $userid;
    }

    // Login the given user ID - unless somebody is already logged in, in which case, don't.
    private function loginUser($userid) {
        $user = get_user_by('id',$userid);
        if (!$user) return false;
        wp_set_current_user($user->ID,$user->user_login);
        wp_set_auth_cookie($userid);
        do_action('wp_login', $user->user_login); 
        return $user;
    }

    // Creates a Vipps user or retreives and updates and existing one, then logs them in. IOK 2018-05-29
    private function  createOrLoginVippsUser($userdetails,$address) {
        if (is_user_logged_in()) return false;
        $userid = $this->createVippsUser($userdetails,$address);
        return $this->loginUser($userid);
    }

    // Given an orderid, check to see if we should log the user in. This is done on 'express checkout' when enabled. IOK 2018-05-29
    // called by template-redirect when on the thank-you page. As the order may not be ready, we may have to store the order ID in session
    // and log in later when the user navigates. IOK 2018-05-30
    private function maybe_login_user($orderid) {
            if (!$orderid) return false;
            if (is_user_logged_in()) {
                wc()->session->set('_login_order',0);
                return false;
            }
            $order = new WC_Order($orderid);
            if (!$order) return false;
            $express =  $order->get_meta('_vipps_express_checkout');
            if ($express != 'create') {
             wc()->session->set('_login_order',0);
             return false;
            }

            $userid = $order->get_meta('_vipps_express_user'); 
            // As the user may have been created async, and the creation process not yet done
            // we need to mark that the user should be logged in on the *next* click instead.
            // The session should be active in this case, if it isn't, oh well.
            if (!$userid) {
              $this->log("No user yet, mark session");
              wc()->session->set('_login_order',$orderid);
              return true;
            } else {
              wc()->session->set('_login_order',0);
              $this->loginUser($userid); 
              return true;
            }
    }
    public function wp_login($user_login,$user) {
              // Remove any waiting login actions from the express checkout branch.
              wc()->session->set('_login_order',0);
    }

    // Special pages, and some callbacks. IOK 2018-05-18 
    public function template_redirect() {
        // If this is the/an order-received/thank you page, check to see if we should log the user in. 
        //  We can only do this if the user actually has been created, and if that hasn't happened - because some servers are 
        // slow and this is/can be asynchronous, we will store the order inn a sessino. Then login using that when it is ready.
        // This will happen only for express checkout, when the 'create customers for express checkout' is true. IOK 2018-05-30
        if (is_order_received_page()){
            global $wp;
            $orderid = isset( $wp->query_vars['order-received'] ) ? intval( $wp->query_vars['order-received'] ) : 0;
            $this->maybe_login_user($orderid);
            return;
        }
        if ($orderid = WC()->session->get('_login_order')) {
          $this->maybe_login_user($orderid); 
        }


        // Handle special callbacks
        $special = $this->is_special_page() ;
        if ($special) return $this->$special();

        $consentremoval = $this->is_consent_removal();
        if ($consentremoval) return  $this->vipps_consent_removal_callback($consentremoval);

    }


    // Can't use wc-api for this, as that does not support DELETE . IOK 2018-05-18
    private function is_consent_removal () {
        if ($_SERVER['REQUEST_METHOD'] != 'DELETE') return false;
        if ( !get_option('permalink_structure')) {
            if (@$_REQUEST['vipps-consent-removal']) return @$_REQUEST['callback'];
            return false;
        }
        if (preg_match("!^/vipps-consent-removal/([^/]*)!", $_SERVER['REQUEST_URI'], $matches)) {
            return @$_REQUEST['callback'];
        }
        return false;
    }

    public function plugins_loaded() {
        load_plugin_textdomain('vipps', false, basename( dirname( __FILE__ ) ));

        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        /* The gateway is added at 'plugins_loaded' and instantiated by Woo itself. IOK 2018-02-07 */
        add_filter( 'woocommerce_payment_gateways', array($this,'woocommerce_payment_gateways' ));

        // Callbacks use the Woo API IOK 2018-05-18
        add_action( 'woocommerce_api_wc_gateway_vipps', array($this,'vipps_callback'));
        add_action( 'woocommerce_api_vipps_login', array($this,'vipps_login_callback'));
        add_action( 'woocommerce_api_vipps_shipping_details', array($this,'vipps_shipping_details_callback'));

        // Template integrations
        add_action( 'woocommerce_cart_actions', array($this, 'cart_express_checkout_button'));
        add_action( 'woocommerce_widget_shopping_cart_buttons', array($this, 'cart_express_checkout_button'), 30);


        // Special pages and callbacks handled by template_redirect
        add_action('template_redirect', array($this,'template_redirect'));

        // Ajax endpoints for checking the order status while waiting for confirmation
        add_action('wp_ajax_nopriv_check_order_status', array($this, 'ajax_check_order_status'));
        add_action('wp_ajax_check_order_status', array($this, 'ajax_check_order_status'));

        // This is for express checkout which we will also do asynchronously IOK 2018-05-28
        add_action('wp_ajax_nopriv_do_express_checkout', array($this, 'ajax_do_express_checkout'));
        add_action('wp_ajax_do_express_checkout', array($this, 'ajax_do_express_checkout'));


    }

    public function save_order($postid,$post,$update) {
        if ($post->post_type != 'shop_order') return;
        $order = new WC_Order($postid);
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') return;

        if (isset($_POST['do_capture_vipps']) && $_POST['do_capture_vipps']) {
            require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
            $gw = new WC_Gateway_Vipps();
            // 0 in amount means full payment, which is all we currently support. IOK 2018-05-7
            $ok = $gw->capture_payment($order,0);
            // This will result in a redirect, so store admin notices, then display them. IOK 2018-05-07
            $this->store_admin_notices();
        }
    }

    // Make admin-notices persistent so we can provide error messages whenever possible. IOK 2018-05-11
    public function store_admin_notices() {
        ob_start();
        do_action('admin_notices');
        $notices = ob_get_clean();
        set_transient('_vipps_save_admin_notices',$notices, 5*60);
    }

    public function order_item_add_action_buttons ($order) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') return;
        $status = $order->get_status();
        if ($status != 'on-hold' && $status != 'processing') return;

        $captured = intval($order->get_meta('_vipps_captured'));
        $capremain = intval($order->get_meta('_vipps_capture_remaining'));
        if ($captured && !$capremain) return;

        print '<button type="button" onclick="document.getElementById(\'docapture\').value=1;document.post.submit();" style="background-color:#ff5b24;border-color:#ff5b24;color:#ffffff" class="button vippsbutton generate-items">' . __('Capture payment','vipps') . '</button>';
        print "<input id=docapture type=hidden name=do_capture_vipps value=0>"; 
    } 

    // This is the main callback from Vipps when payments are returned. IOK 2018-04-20
    public function vipps_callback() {
        $raw_post = @file_get_contents( 'php://input' );
        $this->log("Callback!",'debug');
        $this->log($raw_post,'debug');

        $result = @json_decode($raw_post,true);
        if (!$result) {
            $this->log(__("Did not understand callback from Vipps:",'vipps') . " " .  $raw_post);
            return false;
        }
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        $gw = new WC_Gateway_Vipps();
        $gw->handle_callback($result);
        exit();
    }

    // This if for the callback from Vipps when logging in
    public function vipps_login_callback() {
        $raw_post = @file_get_contents( 'php://input' );
        $result = @json_decode($raw_post,true);
        if (!$result) {
            $this->log(__("Did not understand login callback from Vipps:",'vipps') . " " .  $raw_post);
            return false;
        }
        $loginrequest = get_transient('_vipps_loginrequests_' . $result['requestId']);
        if (!$loginrequest) {
            $this->log(__("Error during Vipps login callback: unknown request id",'vipps') . ' ' . print_r($result,true));
            return false;
        }
        if ($_SERVER['PHP_AUTH_USER'] != 'Vipps' || $_SERVER['PHP_AUTH_PW'] != $loginrequest['authToken']) {
            $this->log(__("Error during Vipps login callback: wrong or no authtoken. Make sure the Authorization header is not stripped on your system",'vipps'));
            return false;
        }
        // Store the request! The waiting-page will do the login/creation. IOK 2018-05-18 
        $this->log(__("Got login callback from",'vipps') . " " .$result['status']);;
        set_transient('_vipps_loginrequests_' . $result['requestId'], $result, 10*60);
        exit();
    }
    // Getting shipping methods/costs for a given order to Vipps for express checkout
    public function vipps_shipping_details_callback() {
        $raw_post = @file_get_contents( 'php://input' );
        $result = @json_decode($raw_post,true);
        $callback = @$_REQUEST['callback'];
        $data = array_reverse(explode("/",$callback));
        $vippsorderid = @$data[1]; // Second element - callback is /v2/payments/{orderId}/shippingDetails
        $orderid = $this->getOrderIdByVippsOrderId($vippsorderid);
        if (!$orderid) {
            exit();
        }
        $order = new WC_Order($orderid);

        if (!$order) {
            exit();
        }

        // a small bit of security
        if ($order->get_meta('_vipps_authtoken') && $order->get_meta('_vipps_authtoken') != $_SERVER['PHP_AUTH_PW']) {
            $this->log("Wrong authtoken on shipping details callback");
            print "-1";
            exit();
        }

        // Get addressinfo from the callback, this is from Vipps. IOK 2018-05-24. 
        // {"addressId":973,"addressLine1":"BOKS 6300, ETTERSTAD","addressLine2":null,"country":"Norway","city":"OSLO","postalCode":"0603","postCode":"0603","addressType":"H"}
        $addressid = $result['addressId'];
        $addressline1 = $result['addressLine1'];
        $addressline2 = $result['addressLine2'];
        $vippscountry = $result['country'];
        $city = $result['city'];
        $postcode= $result['postCode'];

        $addressid = "0";
        $addressline1 = "obs";
        $addressline1 = "obs";
        $vippscountry = 'NO';
        $city = 'OSLO';
        $postcode = '0254';


        $country = '';  
        switch (strtoupper($vippscountry)) { 
            case 'NORWAY':
            case 'NORGE':
            case 'NOREG':
            case 'NO':
                $country = 'NO';
                break;
        }
        $order->set_billing_address_1($addressline1);
        $order->set_billing_address_2($addressline2);
        $order->set_billing_city($city);
        $order->set_billing_postcode($postcode);
        $order->set_billing_country($country);
        $order->set_shipping_address_1($address1);
        $order->set_shipping_address_2($address2);
        $order->set_shipping_city($city);
        $order->set_shipping_postcode($postcode);
        $order->set_shipping_country($country);
        $order->save();


        // We need unfortunately to create a fake cart to be able to send a 'package' to the
        // shipping calculation environment.  This will however not sufficiently handle tax issues and so forth,
        // so this needs to be maintained. IOK 2018.
        $acart = new WC_Cart();
        foreach($order->get_items() as $item) {
            $varid = $item['variation_id'];
            $prodid = $item['product_id'];
            $quantity = $item['quantity'];
            $acart->add_to_cart($prodid,$quantity,$varid);
        }
        $package = array();
        $package['contents'] = $acart->cart_contents;
        $package['contents_cost'] = $order->get_total() - $order->get_shipping_total() - $order->get_shipping_tax();
        $package['destination'] = array();
        $package['destination']['country']  = $country;
        $package['destination']['state']    = '';
        $package['destination']['postcode'] = $postcode;
        $package['destination']['city']     = $city;
        $package['destination']['address']  = $addressline1;
        if ($addressline2 && !$addressline2 == 'null') {
            $package['destination']['address_2']= $addressline2;
        }

        $packages = array($package);
        $shipping =  WC()->shipping->calculate_shipping($packages);
        $shipping_methods = WC()->shipping->packages[0]['rates']; // the 'rates' of the first package is what we want.

        // Then format for Vipps
        $methods = array();
        $howmany = count($methods);
        $priority=0;
        $isdefault = 1;

        // This way of calculating the results will always produce the cost as if it was
        // not including tax. This means that it will be wrong in all those cases - it will 
        // for a cost of 50 have cost '50' and tax 12.5 instead of 10. So we need to redo the tax. IOK 2018-05-25
        $pricesincludetax = 0;
        if (get_option('woocommerce_prices_include_tax') == 'yes') $pricesincludetax=1;

        foreach ($shipping_methods as  $rate) {
            $priority++;
            $method['isDefault'] = $isdefault ? 'Y' : 'N';
            $isdefault=0;
            $method['priority'] = $priority;
            $tax  = $rate->get_shipping_tax();
            $cost = $rate->get_cost();

            // Limitations in the  Woo api makes it neccessary to recalculate the tax in this case. IOK 2018-05-25
            if ($pricesincludetax && $tax>0) {
                $sum = $tax+$cost;
                $percentage = $sum/$cost;
                $exTax = $cost/$percentage; 
                $tax = sprintf("%.2f", $cost-$exTax);
            } 


            $method['shippingCost'] = sprintf("%.2f",$cost);
            $method['shippingMethod'] = $rate->get_label();
            // It's possible that just the id is enough, but Woo isn't quite clear here and the
            // constructor for WC_Shipping_Rate takes both. So pack them together with an ; - the id uses the colon. IOK 2018-05-24
            $method['shippingMethodId'] = $rate->get_method_id() . ";" . $rate->get_id() . ";" . $tax; 
            $methods[]= $method;
        }

        $return = array('addressId'=>intval($addressid), 'orderId'=>$vippsorderid, 'shippingDetails'=>$methods);
        $json = json_encode($return);
        header("Content-type: application/json; charset=UTF-8");
        print $json;
        exit();
    }

    // Handle DELETE on a vipps consent removal callback
    public function vipps_consent_removal_callback ($callback) {
        $data = array_reverse(explode("/",$callback));
        if (empty($data)) return false;
        $uid = $data[0];
        $this->log(__("Vipps consent removal received for",'vipps') . ' ' . $uid); 
        if (!$uid) {
            print "-1";exit();
        }
        $users = get_users(array('meta_key'=>'vipps_id','meta_value'=>$uid));
        if (empty($users)) {
            print "-1";exit();
        }
        $user = $users[0];
        if ($user->has_cap("remove_users")) {
            $this->log(__("Administrator user can remove users - don't accidentally remove by consent removal:", 'vipps') . " " . $user->ID); 
            print "-1";exit();
        }

        $customer = new WC_Customer($user->ID); 
        if (!$customer) {
            print "-1";exit();
        }

        // Quite incredibly, this is neccessary. IOK 2018-05-18
        require_once(ABSPATH.'wp-admin/includes/user.php');
        $customer->delete();
        print "1"; exit();
        exit();
    }

    public function woocommerce_payment_gateways($methods) {
        $methods[] = 'WC_Gateway_Vipps'; 
        return $methods;
    }

    public function activate () {

    }
    public function uninstall() {
    }
    public function footer() {
    }

    // Check order status in the database, and if it is pending for a long time, directly at Vipps
    // IOK 2018-05-04
    public function check_order_status($order) {
        if (!$order) return null;
        $order_status = $order->get_status();
        if ($order_status != 'pending') return $order_status;
        // No callback has occured yet. If this has been going on for a while, check directly with Vipps
        if ($order_status == 'pending') {
            $now = time();
            $then= $order->get_meta('_vipps_init_timestamp');
            if ($then + (1 * 60) < $now) { // more than a minute? Start checking at Vipps
                return $order_status;
            }
        }
        $this->log("Checking order status on Vipps");
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        $gw = new WC_Gateway_Vipps();
        try {
            $order_status = $gw->callback_check_order_status($order);
            return $order_status;
        } catch (Exception $e) {
            $this->log($e->getMessage());
            return null;
        }
    }

    // We have to empty the cart when the user goes to Vipps, so
    // we store it in the session and restore it if the users cancels. IOK 2018-05-07
    public function save_cart($order) {
        global $woocommerce;
        $cartcontents = $woocommerce->cart->get_cart();
        $carts = $woocommerce->session->get('_vipps_carts');
        if (!$carts) $carts = array();
        $carts[$order->get_id()] = $cartcontents;
        $woocommerce->session->set('_vipps_carts',$carts); 
    }
    public function restore_cart($order) {
        global $woocommerce;
        $carts = $woocommerce->session->get('_vipps_carts');
        if (empty($carts)) return;
        $cart = @$carts[$order->get_id()];
        unset($carts[$order->get_id()]);
        $woocommerce->session->set('_vipps_carts');
        foreach ($cart as $cart_item_key => $values) {
            $id =$values['product_id'];
            $quant=$values['quantity'];
            $woocommerce->cart->add_to_cart($id,$quant);
        }
    }

    public function ajax_do_express_checkout () {
        check_ajax_referer('do_express','sec');
        try {
            require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
            $gw = new WC_Gateway_Vipps();
            $orderid = $gw->create_partial_order();
        } catch (Exception $e) {
            $result = array('ok'=>0, 'msg'=>__('Could not create order','vipps') . ': ' . $e->getMessage(), 'url'=>false);
            wp_send_json($result);
            exit();
        } 
        if (!$orderid) {
            $result = array('ok'=>0, 'msg'=>__('Could not create order','vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        }

        $gw->express_checkout = 1;
        $ok = $gw->process_payment($orderid);
        if ($ok && $ok['result'] == 'success') {
            $result = array('ok'=>1, 'msg'=>'', 'url'=>$ok['redirect']);
            wp_send_json($result);
            exit();
        }
        $result = array('ok'=>0, 'msg'=> __('Vipps is temporarily unavailable.','vipps'), 'url'=>'');
        wp_send_json($result);
        exit();
    }

    // Check the status of the order if it is a part of our session, and return a result to the handler function IOK 2018-05-04
    public function ajax_check_order_status () {
        check_ajax_referer('vippsstatus','sec');

        $orderid= wc_get_order_id_by_order_key($_POST['key']);
        $transaction = wc_get_order_id_by_order_key($_POST['transaction']);

        $sessionorders= WC()->session->get('_vipps_session_orders');
        if (!isset($sessionorders[$orderid])) {
            $this->log(__('The orderid passed is not from this session:','vipps') . $orderid);
            wp_send_json(array('status'=>'error', 'msg'=>__('Not an order','vipps')));
        }

        $order = new WC_Order($orderid); 
        if (!$order) {
            wp_send_json(array('status'=>'error', 'msg'=>__('Not an order','vipps')));
        }
        $order_status = $this->check_order_status($order);
        if ($order_status == 'on-hold') {
            wp_send_json(array('status'=>'ok', 'msg'=>__('Payment authorized', 'vipps')));
        }
        if ($order_status == 'processing') {
            wp_send_json(array('status'=>'ok', 'msg'=>__('Payment captured', 'vipps')));
        }
        if ($order_status == 'completed') {
            wp_send_json(array('status'=>'ok', 'msg'=>__('Order complete', 'vipps')));
        }

        if ($order_status == 'failed') {
            $this->restore_cart($order);
            wp_send_json(array('status'=>'failed', 'msg'=>__('Order failed', 'vipps')));
        }
        if ($order_status == 'cancelled') {
            $this->restore_cart($order);
            wp_send_json(array('status'=>'failed', 'msg'=>__('Order failed', 'vipps')));
        }
        if ($order_status == 'refunded') {
            $this->restore_cart($order);
            wp_send_json(array('status'=>'failed', 'msg'=>__('Order failed', 'vipps')));
        }
        // No callback has occured yet. If this has been going on for a while, check directly with Vipps
        if ($order_status == 'pending') {
            wp_send_json(array('status'=>'waiting', 'msg'=>__('Waiting on order', 'vipps')));
        }
        wp_send_json(array('status'=>'error', 'msg'=> __('Unknown order status','vipps') . $order_status));
        return false;
    }

    // The various return URLs for special pages of the Vipps stuff depend on settings and pretty-URLs so we supply them from here
    // These are for the "fallback URL" mostly. IOK 2018-05-18
    // IOK  FIXME add backend support for these
    private function make_return_url($what) {
        $url = '';
        if ( !get_option('permalink_structure')) {
            $url = "/?VippsSpecialPage=$what";
        } else {
            $url = "/$what/";
        }
        return set_url_scheme(home_url(),'https') . $url;
    }
    public function payment_return_url() {
        return $this->make_return_url('vipps-betaling');
    }
    public function login_return_url() {
        return $this->make_return_url('vipps-login-venter');
    }
    public function express_checkout_url() {
        return $this->make_return_url('vipps-express-checkout');
    }
    // Return the method in the Vipps
    public function is_special_page() {
        $specials = array('vipps-login'=>'vipps_login_page','vipps-betaling' => 'vipps_wait_for_payment','vipps-login-venter'=>'vipps_wait_for_login', 'vipps-express-checkout'=>'vipps_express_checkout');
        $method = null;
        if ( get_option('permalink_structure')) {
            foreach($specials as $special=>$specialmethod) {
                if (preg_match("!^/$special/([^/]*)!", $_SERVER['REQUEST_URI'], $matches)) {
                    $method = $specialmethod; break;
                }
            }
        } else {
            if (isset($_GET['VippsSpecialPage'])) {
                $method = array_search($_GET['VippsSpecialPage'], $specials);
            }
        }
        return $method;
    }

    // Just create a spinner and a overlay.
    public function spinner () {
        ob_start();
?>
<div class="vippsoverlay">
<div id="floatingCirclesG" class="vippsspinner">
    <div class="f_circleG" id="frotateG_01"></div>
    <div class="f_circleG" id="frotateG_02"></div>
    <div class="f_circleG" id="frotateG_03"></div>
    <div class="f_circleG" id="frotateG_04"></div>
    <div class="f_circleG" id="frotateG_05"></div>
    <div class="f_circleG" id="frotateG_06"></div>
    <div class="f_circleG" id="frotateG_07"></div>
    <div class="f_circleG" id="frotateG_08"></div>
</div>
</div>
<?php
        return ob_get_clean(); 
    }


    // This URL only exists to recieve calls to "express checkout" and to redirect to Vipps.
    // It could be changed to be a normal page that would call the below as an ajax method - this would
    // be better performancewise as this can take some time. IOK 2018-05-25
    public function vipps_express_checkout() {
        // We need a nonce to get here, but we should only get here when we have a cart, so this will not be cached.
        // IOK 2018-05-28
        $ok = wp_verify_nonce($_REQUEST['sec'],'express');

        $backurl = wp_validate_redirect($_SERVER['HTTP_REFERER']);
        if (!$backurl) $backurl = home_url();

        if ( WC()->cart->get_cart_contents_count() == 0 ) {
            wc_add_notice(__('Your shopping cart is empty','vipps'),'error');
            wp_redirect($backurl);
            exit();
        }

        wp_enqueue_script('vipps-express-checkout',plugins_url('js/express-checkout.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/express-checkout.js"), 'true');
        wp_add_inline_script('vipps-express-checkout','var vippsajaxurl="'.admin_url('admin-ajax.php').'";', 'before');
        // If we have a valid nonce when we get here, just call the 'create order' bit at once. Otherwise, make a button
        // to actually perform the express checkout.

        $content = $this->spinner();
        $content .= "<form id='vippsdata'>";
        $content .= "<input type='hidden' name='action' value='do_express_checkout'>";
        $content .= wp_nonce_field('do_express','sec',1,false); 
        $content .= "</form>";

        if ($ok) {
            $content .= "<p id=waiting>" . __("Please wait while we are preparing your order", 'vipps') . "</p>";
            $content .= "<div style='display:none' id='success'></div>";
            $content .= "<div style='display:none' id='failure'></div>";
            $content .= "<div style='display:none' id='error'>". __('Vipps is temporarily unavailable.','vipps')  . "</div>";
            $this->fakepage(__('Order in progress','vipps'), $content);
            return;
        } else {
            $content .= "<p id=waiting>" . __("Ready for express checkout - press the button", 'vipps') . "</p>";
            $content .= "<p><button id='do-express-checkout' class='button vipps-express-checkout'>". __("Checkout with Vipps",'vipps'). "</button></p>";
            $content .= "<div style='display:none' id='success'></div>";
            $content .= "<div style='display:none' id='failure'></div>";
            $content .= "<div style='display:none' id='error'>". __('Vipps is temporarily unavailable.','vipps')  . "</div>";
            $this->fakepage(__('Express checkout','vipps'), $content);
            return;
        }
    }



    public function vipps_wait_for_payment() {
        status_header(200,'OK');
        $orderid = WC()->session->get('_vipps_pending_order');
        $order = null;
        if ($orderid) {
            $order = new WC_Order($orderid); 
        }
        if (!$order) wp_die(__('Unknown order', 'vipps'));


        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        $gw = new WC_Gateway_Vipps();

        // If we are done, we are done, so go directly to the end. IOK 2018-05-16
        $status = $order->get_status();
        // Still pending, no callback. Make a call to the server as the order might not have been created. IOK 2018-05-16
        if ($status == 'pending') {
            $newstatus = $gw->callback_check_order_status($order);
            if ($newstatus) {
                $status = $newstatus;
            }
        }
        if ($status == 'on-hold' || $status == 'processing' || $status == 'completed') {
            wp_redirect($gw->get_return_url($order));
            exit();
        }

        // We are done, but in failure. Don't poll.
        if ($status == 'cancelled' || $status == 'refunded') {
            $content .= "<div id=failure><p>". __('Order cancelled', 'vipps') . '</p>';
            $content .= "<p><a href='" . home_url() . "' class='btn button'>" . __('Continue shopping','vipps') . '</a></p>';
            $content .= "</div>";
            $this->fakepage(__('Order cancelled','vipps'), $content);
            return;
        }

        // Still pending and order is supposed to exist, so wait for Vipps. This part might not be relevant anymore. IOK 2018-05-16
        $this->log("Unexpectedly reached the wait-for-callback branch.");

        // Otherwise, go to a page waiting/polling for the callback. IOK 2018-05-16
        wp_enqueue_script('check-vipps',plugins_url('js/check-order-status.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/check-order-status.js"), 'true');
        wp_add_inline_script('check-vipps','var vippsajaxurl="'.admin_url('admin-ajax.php').'";', 'before');

        // Check that order exists and belongs to our session. Can use WC()->session->get() I guess - set the orderid or a hash value in the session
        // and check that the order matches (and is 'pending') (and exists)
        $vippsstamp = $order->get_meta('_vipps_init_timestamp');
        $vippsstatus = $order->get_meta('_vipps_init_status');
        $message = __($order->get_meta('_vipps_confirm_message'),'vipps');

        $signal = $this->callbackSignal($order);
        $content .= "<div id='waiting'><p>" . __('Waiting for confirmation of purchase from Vipps','vipps');

        if ($signal && !is_file($signal)) $signal = '';
        $signalurl = $this->callbackSignalURL($signal);

        $content .= '<span id=vippsstatus>'.htmlspecialchars("$message\n$vippsstatus\n" . date('Y-m-d H:i:s',$vippsstamp)) .'</span>';
        $content .= "<span id='vippstime'></span>";
        $content .= "</p></div>";

        $content .= "<form id='vippsdata'>";
        $content .= "<input type='hidden' id='fkey' name='fkey' value='".htmlspecialchars($signalurl)."'>";
        $content .= "<input type='hidden' name='key' value='".htmlspecialchars($order->get_order_key())."'>";
        $content .= "<input type='hidden' name='action' value='check_order_status'>";
        $content .= wp_nonce_field('vippsstatus','sec',1,false); 
        $content .= "</form>";


        $content .= "<div id='error' style='display:none'><p>".__('Error during order confirmation','vipps'). '</p>';
        $content .= "<p>" . __('An error occured during order confirmation. The error has been logged. Please contact us to determine the status of your order', 'vipps') . "</p>";
        $content .= "<p><a href='" . home_url() . "' class='btn button'>" . __('Continue shopping','vipps') . '</a></p>';
        $content .= "</div>";

        $content .= "<div id=success style='display:none'><p>". __('Order confirmed', 'vipps') . '</p>';
        $content .= "<p><a class='btn button' id='continueToThankYou' href='" . $gw->get_return_url($order)  . "'>".__('Continue','vipps') ."</a></p>";
        $content .= '</div>';

        $content .= "<div id=failure style='display:none'><p>". __('Order cancelled', 'vipps') . '</p>';
        $content .= "<p><a href='" . home_url() . "' class='btn button'>" . __('Continue shopping','vipps') . '</a></p>';
        $content .= "</div>";


        $this->fakepage(__('Waiting for your order confirmation','vipps'), $content);
    }

    public function vipps_login_page() {
        // Because we want to call this using GET we must ensure no caching happens so we can set cookies etc.
        header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', FALSE);
        header('Pragma: no-cache');

        if (is_user_logged_in()) {
            wc_add_notice(__('You are already logged in!','vipps'),'notice');
            wp_redirect(home_url());
            exit();
        } 

        // To login, we need a session, even if the user hasn't added anything to the cart yet. IOK 2018-05-18 
        if (!WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
        WC()->session->set('vipps_login_request', null);

        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        $gw = new WC_Gateway_Vipps();
        $msg = __('Unknown error','vipps');
        $result = null;
        try {
            $result = $gw->login_request(); 
        } catch (Exception $e) {
            $msg = $e->getMessage();
        }
        if (!$result) {
            wc_add_notice($msg);
            wp_redirect(home_url());
            exit();
        }

        WC()->session->set('vipps_login_request', $result['requestId']);
        wp_redirect($result['url']);
    }

    // Actually create/login the user when we have a result. 
    // IOK 2018-05-18 FIXME REWRITE TO USE AJAX INSTEAD OF RELOAD for smoothness. Also, potentialy call the 'get login status' call.
    public function vipps_wait_for_login() {
        header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', FALSE);
        header('Pragma: no-cache');

        $requestid = WC()->session->get('vipps_login_request');


        $loginrequest = get_transient('_vipps_loginrequests_' . $requestid);
        if (!$loginrequest) {
            $msg = __('Could not login with Vipps:','vipps');
            $this->log(__('Login wait page: Unknown login request','vipps'));
            wc_add_notice($msg, 'error');
            wp_redirect(home_url());
            exit();
        }
        $status = isset($loginrequest['status']) ? $loginrequest['status'] : '';

        // No callback yet, so make 1 call to the get status thing, which should normally produce some result.
        if (!$status || $status == 'PENDING') {
            try {
                require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
                $gw = new WC_Gateway_Vipps();
                $result = $gw->login_request_status($requestid); 
                $status = isset($result['status']) ?  $result['status'] : '';
            } catch (Exception $e) {
                $msg = __('Could not login with Vipps:','vipps') . ' ' . $e->getMessage();
                $this->log($msg);
                wc_add_notice(__('Could not login with Vipps:','vipps') . ' ' .  __('Vipps is temporarily unavailable.','vipps'), 'error');
                delete_transient('_vipps_loginrequests_' . $requestid);
                wp_redirect(home_url());
                exit();
            }
        }

        if ($status == 'SUCCESS') {
            try {
                $userdetails = $loginrequest['userDetails'];
                $address = $userdetails['address'];
                $user = $this->createOrLoginVippsUser($userdetails,$address);
                if ($user) {
                 // Returns false if e.g. the user is already logged in, in which case, don't go "welcome!". IOK 2018-05-29
                 wc_add_notice(__('Welcome') . ' ' . $user->display_name . '!', 'success');
                }
            } catch (Exception $e) {
                $msg = __('Could not login with Vipps:','vipps') . ' ' . $e->getMessage();
                $this->log($msg);
                wc_add_notice($msg, 'error');
            }
            delete_transient('_vipps_loginrequests_' . $requestid);
            wp_redirect(home_url());
            exit();
        }

        // We haven't had a result in 30 seconds - give up . IOK 2018-05-18
        if (!isset($loginrequest['when']) || ($loginrequest['when'] + 30) < time()) {
            $status = 'FAILURE';
        }

        if ($status == 'FAILURE' || $status == 'DECLINED' || $status == 'REMOVED') {
            $msg = __('Could not login with Vipps:','vipps') . ' ' . $status;
            delete_transient('_vipps_loginrequests_' . $requestid);
            wc_add_notice(__('Vipps login cancelled','vipps'),'error');
            wp_redirect(home_url());
            exit();
        }


        if ($status == 'PENDING' || !$status) {
            // We should actaully try to call the 'get login request status after enough time has passed here,
            // but really.. this is just a failure result, so we could just as well go directly to failure after a couple of seconnds. IOK 2018-05-18
            $content = __("Waiting for Vipps login approval",'vipps');
            $content .= "\n<script>setTimeout(function() {window.location.reload(true);}, 10000);</script\n>";
            $this->fakepage(__("Waiting for Vipps login approval", 'vipps'),$content);
        }
    }


    public function fakepage($title,$content) {
        global $wp, $wp_query;
        $post = new stdClass();
        $post->ID = -99;
        $post->post_author = 1;
        $post->post_date = current_time( 'mysql' );
        $post->post_date_gmt = current_time( 'mysql', 1 );
        $post->post_title = $title;
        $post->post_content = $content;
        $post->post_status = 'publish';
        $post->comment_status = 'closed';
        $post->ping_status = 'closed';
        $post->post_name = 'vippsconfirm-fake-page-name';
        $post->post_type = 'page';
        $post->filter = 'raw'; // important
        $wp_post = new WP_Post($post);
        wp_cache_add( -99, $wp_post, 'posts' );
        // Update the main query
        $wp_query->post = $wp_post;
        $wp_query->posts = array( $wp_post );
        $wp_query->queried_object = $wp_post;
        $wp_query->queried_object_id = $post_id;
        $wp_query->found_posts = 1;
        $wp_query->post_count = 1;
        $wp_query->max_num_pages = 1; 
        $wp_query->is_page = true;
        $wp_query->is_singular = true; 
        $wp_query->is_single = false; 
        $wp_query->is_attachment = false;
        $wp_query->is_archive = false; 
        $wp_query->is_category = false;
        $wp_query->is_tag = false; 
        $wp_query->is_tax = false;
        $wp_query->is_author = false;
        $wp_query->is_date = false;
        $wp_query->is_year = false;
        $wp_query->is_month = false;
        $wp_query->is_day = false;
        $wp_query->is_time = false;
        $wp_query->is_search = false;
        $wp_query->is_feed = false;
        $wp_query->is_comment_feed = false;
        $wp_query->is_trackback = false;
        $wp_query->is_home = false;
        $wp_query->is_embed = false;
        $wp_query->is_404 = false; 
        $wp_query->is_paged = false;
        $wp_query->is_admin = false; 
        $wp_query->is_preview = false; 
        $wp_query->is_robots = false; 
        $wp_query->is_posts_page = false;
        $wp_query->is_post_type_archive = false;
        // Update globals
        $GLOBALS['wp_query'] = $wp_query;
        $wp->register_globals();
        return $wp_post;
    }

}
