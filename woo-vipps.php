<?php
/*
   Plugin Name: Pay with Vipps and MobilePay for WooCommerce
   Plugin URI: https://wordpress.org/plugins/woo-vipps/
   Description: Offer Vipps as a payment method for WooCommerce
   Author: WP Hosting, Everyday AS
   Author URI: https://www.wp-hosting.no/
   Text-domain: woo-vipps
   Domain Path: /languages
   Version: 4.0.0
   Stable tag: 4.0.0
   Requires at least: 6.2
   Tested up to: 6.7.1
   Requires PHP: 7.0
   Requires Plugins: woocommerce
   WC requires at least: 3.3.4
   WC tested up to: 9.4.2

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

/* If the recurring feature has been activated at any point, we will need to load the recurring-support, if only to call the deactivate method. */
$recurring_active = in_array('vipps-recurring-woocommerce/woo-vipps-recurring.php', $activeplugins);
$recurring_active = $recurring_active || in_array('vipps-recurring-payments-gateway-for-woocommerce/woo-vipps-recurring.php', $activeplugins);
$subscriptions_active = in_array('woocommerce-subscriptions/woocommerce-subscriptions.php', $activeplugins);
if ($recurring_active) {
   update_option('woo_vipps_recurring_payments', 1);
}
$recurring_activated = get_option('woo_vipps_recurring_payments');

if ($woo_active) {
    /* Load support for the basic payment plugin IOK 2024-09-27 */
    require_once(dirname(__FILE__) ."/payment/payment.php");

    /* Load support for recurring payments if the stand-alone plugin isn't active IOK 2024-09-27  */
    /* But only if Subscriptions is or if we have activated recurring at an earlier date. IOK 2024-11-22  */
    if (!$recurring_active && ($recurring_activated || $subscriptions_active)) {
        require_once(dirname(__FILE__) . "/recurring/recurring.php");
// "is defined" på funksjonen
        if ((!defined("DOING_AJAX") || !DOING_AJAX) && !wp_is_json_request() && !wp_doing_cron()) {
            add_action('plugins_loaded', 'woo_vipps_maybe_activate_recurring');
        }
    }
}

// Declare our support for the HPOS feature IOK 2022-12-07
add_action( 'before_woocommerce_init', function() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WC_VIPPS_MAIN_FILE, true );
        }
});

// Maybe activate/deactivate the recurring feature
function woo_vipps_maybe_activate_recurring () {
    global $recurring_activated;
    error_log("Hello maybe to activate " . $_SERVER['REQUEST_URI']);
}
