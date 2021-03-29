<?php
/*
   Plugin Name: Checkout with Vipps for WooCommerce
   Plugin URI: https://wordpress.org/plugins/woo-vipps/
   Description: Offer Vipps as a payment method for WooCommerce
   Author: WP Hosting
   Author URI: https://www.wp-hosting.no/
   Text-domain: woo-vipps
   Domain Path: /languages
   Version: 1.7.5

   Requires at least: 4.7
   Tested up to: 5.7.0
   Stable tag: trunk
   Requires PHP: 5.6
   WC requires at least: 3.3.4
   WC tested up to: 5.1.0

   License: MIT
   License URI: https://choosealicense.com/licenses/mit/


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


// Report version externally
define('WOO_VIPPS_VERSION', '1.7.5');

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Legacy way of starting test mode - please use developer- and test-modes from now on. IOK 2019-08-30
if ( ! defined('VIPPS_TEST_MODE' )) {
    define('VIPPS_TEST_MODE', false);
}

// Only be active if Woocommerce is active, either on site or network activated IOK 2018-08-29
$activeplugins =  apply_filters( 'active_plugins', get_option( 'active_plugins' ));
$activesiteplugins = apply_filters('active_sitewide_plugins', get_site_option('active_sitewide_plugins'));
if ($activesiteplugins) {
 $activeplugins = array_merge($activeplugins,array_keys($activesiteplugins));
}

if ( in_array( 'woocommerce/woocommerce.php', $activeplugins) ) {
    require_once(dirname(__FILE__) . "/Vipps.class.php");

    /* Instantiate the singleton, stash it in a global and add hooks. IOK 2018-02-07 */
    global $Vipps;
    $Vipps = Vipps::instance();
    register_activation_hook(__FILE__,array($Vipps,'activate'));
    register_deactivation_hook(__FILE__,array('Vipps','deactivate'));
    register_uninstall_hook(__FILE__, 'Vipps::uninstall');

    if (is_admin()) {
        add_action('admin_init',array($Vipps,'admin_init'));
        add_action('admin_menu',array($Vipps,'admin_menu'));
    } else {
        add_action('wp_footer', array($Vipps,'footer'));
    }
    add_action('init',array($Vipps,'init'));
    add_action( 'plugins_loaded', array($Vipps,'plugins_loaded'));
    add_action( 'woocommerce_loaded', array($Vipps,'woocommerce_loaded'));

}

add_action ('before_woocommerce_init', function () {
 $url = sanitize_text_field($_SERVER['REQUEST_URI']);
 # This removes cookies for the wc-api callback events for the vipps plugin, to be 100% sure no sessions are restored when they ought not be IOK 2020-07-01
 if (preg_match("!\bvipps\b!", $url) && preg_match("!\bwc-api\b!", $url)) {
    foreach($_COOKIE as $key=>$value) unset($_COOKIE[$key]);
 }
},1);
