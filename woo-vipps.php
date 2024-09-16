<?php
/*
   Plugin Name: Pay with Vipps and MobilePay for WooCommerce
   Plugin URI: https://wordpress.org/plugins/woo-vipps/
   Description: Offer Vipps as a payment method for WooCommerce
   Author: WP Hosting
   Author URI: https://www.wp-hosting.no/
   Text-domain: woo-vipps
   Domain Path: /languages
   Version: 3.0.0
   Stable tag: 3.0.0
   Requires at least: 6.2
   Tested up to: 6.6.2
   Requires PHP: 7.0
   Requires Plugins: woocommerce
   WC requires at least: 3.3.4
   WC tested up to: 9.3.1

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


// Report version externally
define('WOO_VIPPS_VERSION', '3.0.0');

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
    /* Instantiate the singleton, stash it in a global and add hooks. IOK 2018-02-07 */
    require_once(dirname(__FILE__) . "/Vipps.class.php");
    global $Vipps;
    $Vipps = Vipps::instance();
    Vipps::register_hooks();

    /* The QR Code functionality is in its own class for modularity reasons. It is a singleton too. */
    require_once(dirname(__FILE__) . "/VippsQRCodeController.class.php");
    VippsQRCodeController::register_hooks();

    /* If Vipps Checkout is activated, load its support. It can still be turned on and off. */
    if (get_option('woo_vipps_checkout_activated', false)) {
        require_once(dirname(__FILE__) . "/VippsCheckout.class.php");
        VippsCheckout::register_hooks();
    }

    // Gutenberg block for on-site messaging badges, if Gutenberg is installed. IOK 2022-11-16
    require_once(dirname(__FILE__) . '/Blocks/Badges/vipps-badge.php');

    // Helper code for specific plugins, themes etc
    require_once(dirname(__FILE__) .  '/woo-vipps-compatibility.php');

    // Load code for the new WooCommerce product editor
    add_action('woocommerce_init', function() {
        // Only load if we're on a version of WooCommerce that supports all the blocks and features we're using.
        $is_version_supported = version_compare(wc()->version, '8.6.0', '>=');
        // Only load if the feature flag is enabled.
        $is_product_editor_v2_enabled = get_option('woocommerce_feature_product_block_editor_enabled');
        if($is_version_supported && $is_product_editor_v2_enabled) {
            // Load the new blocks
            require_once(dirname(__FILE__) .  '/admin/blocks/register-woo-blocks.php');

            // Load the V2 product editor
            require_once(dirname(__FILE__) .  '/VippsWCProductEditorV2.class.php');
            VippsWCProductEditorV2::register_hooks();

        }
    });
}

add_action ('before_woocommerce_init', function () {
 $url = sanitize_text_field($_SERVER['REQUEST_URI']);
 # This removes cookies for the wc-api callback events for the vipps plugin, to be 100% sure no sessions are restored when they ought not be IOK 2020-07-01
 # Re-added and modified IOK 2022-06-20
 if (preg_match("!(wc_gateway_vipps|vipps_shipping_details|vipps-consent-removal)!", $url) && preg_match("!\bwc-api\b!", $url)) {
    // Disallow woo from setting any cookies for these URLs. This happens very early, so we need to do this a bit awkwardly.
    add_filter('woocommerce_set_cookie_enabled', function ($val,$name ,$value, $expire, $secure) {
                return false;
    }, 999, 5);
    // Any cookies that was previously set: Remove them.
    foreach($_COOKIE as $key=>$value) unset($_COOKIE[$key]);
 }
},1);

// Declare our support for the HPOS feature IOK 2022-12-07
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
});

// Load the extra Vipps Checkout Shipping classes only when necessary
add_action( 'woocommerce_shipping_init', function () {
    if (!class_exists('VippsCheckout_Shipping_Method') && get_option('woo_vipps_checkout_activated', false)) {
        require_once(dirname(__FILE__) . "/VippsCheckoutShippingMethods.php");
    }
});
