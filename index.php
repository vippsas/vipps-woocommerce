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
                $isvippscheckout = 1;
            }
        } else {
            if (isset($_GET['VippsBetaling'])) {
                $isvippscheckout = 1;
            }
        } 

        if ($isvippscheckout) {
            // Call a method here in the gatway here IOK FIXME
            status_header(200,'OK');
            $orderid = WC()->session->get('_vipps_pending_order');
            $order = null;
            if ($orderid) {
                $order = new WC_Order($orderid); 
            }
            // Check that order exists and belongs to our session. Can use WC()->session->get() I guess - set the orderid or a hash value in the session
            // and check that the order matches (and is 'pending') (and exists)
            $transid = $order->get_meta('_vipps_transaction');
            $vippsstamp = $order->get_meta('_vipps_init_timestamp');
            $vippsstatus = $order->get_meta('_vipps_init_status');
            $message = $order->get_meta('_vipps_confirm_message');
            print "<pre>This is where we await user confirmation:\n";
            print htmlspecialchars("$message\n$vippsstatus\n" . date('Y-m-d H:i:s',$vippsstamp));

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
        $raw_post = @file_get_contents( 'php://input' );
        $result = @json_decode($raw_post,true);
        if (!$result) {
            $this->log(__("Did not understand callback from Vipps:",'vipps') . " " .  $raw_post);
            return false;
        }
        $this->log("We are in the callback" . print_r($result,true), 'debug');
        $orderid = $result['orderId'];
     
        // IOK FIXME THE PREFIX THING STRIP IT
        $orderid = preg_replace("!^Woo!","",$orderid);
        $order = new WC_Order($orderid);
        if (!$order) {
           $this->log(__("Vipps callback for unknown order",'vipps') . " " .  $orderid);
           return false;
        }

        $merchant= $result['merchantSerialNumber'];
        // FIXME IOK MOVE TO GATEWAY AND CHECK THAT THIS IS CORRECT !

        $transaction = @$result['transactionInfo'];
        if (!$transaction) {
            $this->log(__("Anomalous callback from vipps, handle errors and clean up",'vipps'),'error');
            return false;
        }
        $transactionid = $transaction['transactionId'];
        $vippsstamp = strtotime($transaction['timeStamp']);
        $vippsamount = $transaction['amount'];
        $vippsstatus = $transaction['status'];

        $ordertransid = $order->get_meta('_vipps_transaction');
        if ($ordertransid != $transactionid) {
            $this->log(__("Vipps callback with wrong transaction id for order",'vipps'). " " . $orderid . ": " . $transactionid . ': ' . $ordertransid ,'error');
            return false;
        }

        $order->add_order_note(__('Vipps callback received','vipps'));

        $errorInfo = @$result['errorInfo'];
        if ($errorInfo) {
            $this->log(__("Error message in callback from Vipps for order",'vipps') . ' ' . $orderid . ' ' . $errorInfo['errorMessage'],'error');
            $order->add_order_note($errorInfo['errorMessage']);
        }

        $order->update_meta_data('_vipps_callback_timestamp',$vippsstamp);
        $order->update_meta_data('_vipps_amount',$vippsamount);
        $order->update_meta_data('_vipps_status',$vippsstatus); // should be RESERVED or REJECTED mostly, could be FAILED etc. IOK 2018-04-24
        
        if ($vippsstatus == 'RESERVED') {
         $order->update_status('processing', __( 'Payment reserved at Vipps', 'vipps' ));
        } else {
         $order->update_status('cancelled', __( 'Payment cancelled at Vipps', 'vipps' ));
        }
        $order->save();
        // At this point, add signal file for faster callbacks IOK 2018-04-24 FIXME
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
