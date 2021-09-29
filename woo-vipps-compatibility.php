<?php
/*
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
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// This file collects actions, hooks and filters that are specific to third-party plugins that need extra support.

// Support Yith WooCommerce Name your price. We need to load the front-end filters when doing express checkout - otherwise price will be zero.
// Unfortunately, we can't do this before priority 10 for plugins loaded to support their 'premium' stuff. IOK 2021-09-29
add_action('plugins_loaded', function () {
    if (function_exists('YITH_Name_Your_Price_Frontend')) {
        if (is_admin() && defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && $_REQUEST['action'] == 'do_express_checkout') {
            YITH_Name_Your_Price_Frontend();
        }
    }

}, 20);

// IOK 2020-03-17: Klarna Checkout now supports external payment methods, such as Vipps. This is great, but we need first to check
// that any user hasn't already installed the free plugin for this created by Krokedil. If they have, this filter will be present:
add_action('plugins_loaded', function () {
    if (class_exists('KCO') && defined('KCO_WC_VERSION') && version_compare(KCO_WC_VERSION, '2.0.0', '>=') && Vipps::instance()->gateway()->enabled == 'yes') {
        if (has_filter('kco_wc_api_request_args', 'kcoepm_create_order_vipps')) {
            // Vipps external payment support is already present - do nothing. IOK 2021-09-29
        } else {
            require_once(dirname(__FILE__) . "/VippsKCSupport.class.php");
            VippsKCSupport::init();
        }
    }
});

