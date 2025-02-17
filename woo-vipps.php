<?php
/*
   Plugin Name: Pay with Vipps and MobilePay for WooCommerce
   Plugin URI: https://wordpress.org/plugins/woo-vipps/
   Description: Offer Vipps as a payment method for WooCommerce
   Author: WP Hosting, Everyday AS
   Author URI: https://www.wp-hosting.no/
   Text-domain: woo-vipps
   Domain Path: /languages
   Version: 4.0.5
   Stable tag: 4.0.5
   Requires at least: 6.2
   Tested up to: 6.7.2
   Requires PHP: 7.4
   Requires Plugins: woocommerce
   WC requires at least: 3.3.4
   WC tested up to: 9.6.2

   License: MIT
   License URI: https://choosealicense.com/licenses/mit/


This file is part of the plugin Pay with Vipps and MobilePay for WooCommerce
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

define( 'WC_VIPPS_MAIN_FILE', __FILE__ );

// Only be active if Woocommerce is active, either on site or network activated IOK 2018-08-29
$activeplugins =  apply_filters( 'active_plugins', get_option( 'active_plugins' ));
$activesiteplugins = apply_filters('active_sitewide_plugins', get_site_option('active_sitewide_plugins'));
if ($activesiteplugins) {
 $activeplugins = array_merge($activeplugins,array_keys($activesiteplugins));
}

$woo_active = in_array('woocommerce/woocommerce.php', $activeplugins);

if ($woo_active) {
    /* Load support for the basic payment plugin IOK 2024-09-27 */
    require_once(dirname(__FILE__) ."/payment/payment.php");

    /* Load support for recurring payments if the stand-alone plugin isn't active IOK 2024-09-27  */
    /* Moved to a separate file and the action moved to early in plugins_loaded so we can try to do this as gracefully as possible. 
       This will also handle the activation and deactivation code for the recurring features. IOK 2024-12-04 */
    require_once(dirname(__FILE__) . "/recurring/maybe_load.php");
}

// Declare our support for the HPOS feature IOK 2022-12-07
add_action( 'before_woocommerce_init', function() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WC_VIPPS_MAIN_FILE, true );
        }
});
