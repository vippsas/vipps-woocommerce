<?php
/*
   Plugin Name: Woocommerce Vipps Payment Module
   Description: Offer Vips as a payment method for Woocommerce
   Author: Iver Odin Kvello
   Version: 0.9
 */
require_once(dirname(__FILE__) . "/exceptions.php");

/* This class is for hooks and plugin managent, and is instantiated as a singleton. IOK 2018-02-07*/
class Vipps {

    function __construct() {
    }

    public function admin_init () {
    }

    public function admin_menu () {
    }

    public function init () {
    }

    public function log ($what,$type='info') {
        $logger = wc_get_logger();
        $context = array('source','Vipps Woo Gateway');
        $logger->log($type,$what,$context);
    }


    // Temporary redirect handler! IOK FIXME REPLACE IOK 2018-04-23
    // This needs to be an actual page instead, which must be created on plugin activate
    // and then selected, and error-handling added and so forth.
    public function template_redirect() {
        // Check if using pretty links, if so, use the pretty link, otherwise use a GET parameter which we will need to add, ala VFlow=orderid
        $isvippscheckout = 0;
        $orderid=0;
        if ( get_option('permalink_structure')) {
            if (preg_match("!^/vipps-betaling/([^/]*)!", $_SERVER['REQUEST_URI'], $matches)) { 
                $orderid=@intval($matches[1]);
                $isvippscheckout = 1;
            }
        } else {
            if (isset($_GET['VippsBetaling'])) {
                $orderid=@intval($_GET['VippsBetaling']);
                $isvippscheckout = 1;
            }
        } 
        if ($isvippscheckout) {
            status_header(200,'OK');
            $order = null;
            if ($orderid) {
                $order = new WC_Order($orderid); 
            }
            // Check that order exists and belongs to our session. Can use WC()->session->get() I guess - set the orderid or a hash value in the session
            // and check that the order matches (and is 'pending') (and exists)

            print "<pre>Terminal logic goes here\n";
            exit();
        }
    }

    public function plugins_loaded() {
        /* The gateway is added at 'plugins_loaded' and instantiated by Woo itself. IOK 2018-02-07 */
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        add_filter( 'woocommerce_payment_gateways', array($this,'woocommerce_payment_gateways' ));
        add_action( 'woocommerce_api_wc_gateway_vipps', array($this,'vipps_callback'));
        /* URL rewriting and that sort of thing */
        add_action('template_redirect', array($this,'template_redirect'));
    }

    // This is the main callback from Vipps when payments are returned. IOK 2018-04-20
    public function vipps_callback() {
        $this->log("Got this in the callback: " . print_r($_REQUEST,true));
        // Do stuff. 
        /*
           Vipps answers immediately to service call and rest of processing is asynchronous. After reservation processing is done, Vipps will execute callback to the provided URL with the status of the payment. The callback call will be made via HTTPS, without any credentials. Callback is sent once. Please note that callback can be executed at any time within time-frame of 5 minutes after payment request is sent. With other words, if the merchant doesn’t receive any confirmation on payment request within callback timeframe, getPaymentDetails should be called to conclude further action. 

        // Normal transaction
        {
        "orderId": "219930212",[String] REQUIRED
        "transactionInfo": {
        "amount": 120000, [Integer] REQUIRED Scale 2
        "timeStamp": "2014-06-24T08:34:25-07:00",
        "status": "Reserve",
        "transactionId": "1000234732"
        },
        "errorInfo":{
        "errorCode": "",
        "errorGroup":"",
        "errorMessage": ""
        }}
        // Express
        {
        "merchantSerialNumber": "csdac33",[String] REQUIRED
        "orderId": "219930212",[String] REQUIRED
        "shippingDetails" : {
        "address" : {
        "addressLine1":"",[String] REQUIRED
        "addressLine2":"",[String] OPTIONAL
        "city":"",[String] REQUIRED
        "country" : "",[String] REQUIRED Default NO
        "postCode":""[String] REQUIRED
        },
        "shippingCost" : 50.89, [BigDecimal]REQUIRED Scale 2
        "shippingMethod" : ""[String] REQUIRED
        "shippingMethodId" : ""[String] REQUIRED
        } ,
        "transactionInfo": {
        "amount": 1200, [Integer] REQUIRED Scale 2
        "status": "Reserve",
        "timeStamp": "2014-06-24T08:34:25-07:00",
        "transactionId": "1000234732"
        },
        "userDetails" : {
        "bankIdVerified" : "Y",[Char] OPTIONAL Y/N only
        "dateOfBirth" : "",[String] OPTIONAL
        "email" : "",[String] REQUIRED
        "firstName" : "",[String] REQUIRED
        "lastName" : "",[String] REQUIRED
        "mobileNumber" : "",[String]REQUIRED Length=8
        "ssn" : "",[String] OPTIONAL Length=11
        "userId" : ""[String] REQUIRED
        }
        "errorInfo":{
        "errorCode": "",
        "errorGroup":"",



Version: 2.0

"errorMessage": ""
},
}

         */ 
        }

/* WooCommerce Hooks */
public function woocommerce_payment_gateways($methods) {
    $methods[] = 'WC_Gateway_Vipps'; 
    return $methods;
}
/* End Woocommerce hoos*/

public function activate () {

}
public function uninstall() {
}
public function footer() {
}
}

/* Instantiate the singleton, stash it in a global and add hooks. IOK 2018-02-07 */
global $Vipps;
$Vipps = new Vipps();
register_activation_hook(__FILE__,array($Vipps,'activate'));
register_uninstall_hook(__FILE__,array($Vipps,'uninstall'));

if (is_admin()) {
    add_action('admin_init',array($Vipps,'admin_init'));
    add_action('admin_menu',array($Vipps,'admin_menu'));
} else {
    add_action('wp_footer', array($Vipps,'footer'));
}
add_action('init',array($Vipps,'init'));
add_action( 'plugins_loaded', array($Vipps,'plugins_loaded'));

?>
