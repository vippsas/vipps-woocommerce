<?php
/*
   Plugin Name: Woocommerce Vipps Payment Module
   Description: Offer Vips as a payment method for Woocommerce
   Author: Iver Odin Kvello
   Version: 0.9
 */

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

    public function plugins_loaded() {
        /* The gateway is added at 'plugins_loaded' and instantiated by Woo itself. IOK 2018-02-07 */
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        add_filter( 'woocommerce_payment_gateways', array($this,'woocommerce_payment_gateways' ));
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
