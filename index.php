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

  /* WooCommerce Hooks */
  public function woocommerce_payment_gateways($methods) {
    $methods[] = 'WC_Gateway_Vipps'; 
    return $methods;
  }
  public function admin_init () {
  }
 
  public function admin_menu () {
  }

  public function init () {
  }

  public function plugins_loaded() {
   require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");// IOK FIXME 2018-02-07 instantiate the Gateway object here
   add_filter( 'woocommerce_payment_gateways', array($this,'woocommerce_payment_gateways' ));
  }
  public function activate () {
  }
  public function uninstall() {
  }
  public function footer() {
  }
}

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
// Always runs
add_action('init',array($Vipps,'init'));
add_action( 'plugins_loaded', array($Vipps,'plugins_loaded'));

?>
