<?php
/*
   Plugin Name: Woocommerce Vipps Payment Gateway
   Description: Offer Vipps as a payment method for Woocommerce
   Author: Iver Odin Kvello
   Text-domain: vipps
   Version: 0.9
 */

// This is currently not available. Affects creation of accounts for express checkout too.
define('VIPPS_LOGIN', false);

// Only be active if Woocommerce is active IOK 2018-06-05
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    require_once(dirname(__FILE__) . "/Vipps.class.php");

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

}


?>
