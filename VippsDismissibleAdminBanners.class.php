<?php
/*
   This singleton class exists just to organize dismissible admin banners that Vipps sometimes wants to show users.


This file is part of the plugin Checkout with Vipps for WooCommerce
Copyright (c) 2022 WP-Hosting AS

MIT License

Copyright (c) 2022 WP-Hosting AS

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

class VippsDismissibleAdminBanners {
    private static $instance = null;
    public static function instance()  {
        if (!static::$instance) static::$instance = new VippsDismissibleAdminBanners();
        return static::$instance;
    }

    public function add_vipps_dismissible_admin_banners() {
        // Login with Vipps 
        $this->add_login_vipps_dismissible_admin_banner();
        // If WooCommerce Subscriptions is installed, but Vipps Recurring isn't, create a banner.
        $this->add_recurring_vipps_dismissible_admin_banner();
   }

    public static function add() {
        $instance = VippsDismissibleAdminBanners::instance();
        add_action('wp_ajax_vipps_dismiss_notice', array($instance, 'ajax_vipps_dismiss_notice'));
        // Add dismissible banners to the admin screen for promoting Vipps plugins/features
        $instance->add_vipps_dismissible_admin_banners();
    }

    public function ajax_vipps_dismiss_notice() {
        check_ajax_referer('vippssecnonce','vipps_sec');
        if (!isset($_POST['key']) || !$_POST['key']) return;
        $dismissed = get_option('_vipps_dismissed_notices');
        if (!is_array($dismissed)) $dismissed = array();
        $key = sanitize_text_field($_POST['key']);
        $dismissed[$key] = time();
        Vipps::instance()->log(__("Dismissed message ", 'woo-vipps')  . $key, 'info');
        update_option('_vipps_dismissed_notices', $dismissed, false);
        wp_cache_flush();
    }

    // Advertise The Other Plugin if not installed
    public function add_login_vipps_dismissible_admin_banner () {
        if (!function_exists('get_plugins')) return;

        $dismissed = get_option('_vipps_dismissed_notices');
        if (isset($dismissed['vippslogin01'])) return;

        $installed_plugins = get_plugins();
        if (isset($installed_plugins['login-with-vipps/login-with-vipps.php'])) {
           if (!is_array($dismissed)) $dismissed = array();
           $dismissed['vippslogin01'] = time();
           update_option('_vipps_dismissed_notices', $dismissed, false);
           return;
        }


        add_action('admin_notices', function () {
            $logo = plugins_url('img/vipps-rgb-orange-neg.svg',__FILE__);
            $loginurl = "https://wordpress.org/plugins/login-with-vipps/#description";
            ?>
            <div class='notice notice-vipps notice-vipps-neg notice-info is-dismissible'  data-key='vippslogin01'>
            <a target="_blank"  href="<?php echo $loginurl; ?>">
            <img src="<?php echo $logo; ?>" style="float:left; height: 3rem; margin-top: 0.2rem" alt="Logg inn med Vipps-logo">
             <div>
                 <p style="font-size:1rem"><?php echo __("Login with Vipps is available for WordPress and WooCommerce - Super easy registration and login - No more usernames and passwords. Get started here", 'woo-vipps'); ?></p>
             </div>
             </a>
            </div>
            <?php
            });
    }

    // Advertise The Other Plugin if not installed and WooCommerce subscriptions is
    public function add_recurring_vipps_dismissible_admin_banner () {
        if (!function_exists('get_plugins')) return;

        $dismissed = get_option('_vipps_dismissed_notices');
        if (isset($dismissed['vippssub01'])) return;

        if (!class_exists('WC_Subscriptions')) {
            // We only need this if the user has Woocommerce Subscriptions installed
            return;
        }

        $installed_plugins = get_plugins();
        if (class_exists( 'WC_Vipps_Recurring' ) || isset($installed_plugins['vipps-recurring-payments-gateway-for-woocommerce/woo-vipps-recurring.php']) || get_option('woo-vipps-recurring-version')) {
            error_log("It already has been installed");
            if (!is_array($dismissed)) $dismissed = array();
            $dismissed['vippssub01'] = time();
            update_option('_vipps_dismissed_notices', $dismissed, false);
           return;
        }

        add_action('admin_notices', function () {
            $logo = plugins_url('img/vipps-rgb-orange-neg.svg',__FILE__);
            $recurringurl = "https://wordpress.org/plugins/vipps-recurring-payments-gateway-for-woocommerce/";
            ?>
            <div class='notice notice-vipps notice-vipps-neg notice-info is-dismissible'  data-key='vippssub01'>
            <a target="_blank"  href="<?php echo $recurringurl; ?>">
            <img src="<?php echo $logo; ?>" style="float:left; height: 3rem; margin-top: 0.2rem" alt="Vipps Recurring Payments logo">
             <div>
                 <p style="font-size:1rem"><?php echo __("Vipps Recurring Payments for WooCommerce is perfect if you sell subscriptions or memberships. The plugin is available for Wordpress and WooCommerce - get started here!", 'woo-vipps');
 ?></p>
             </div>
             </a>
            </div>
            <?php
            });
    }

}
