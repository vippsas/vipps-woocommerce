<?php
/*
   This singleton class exists just to organize dismissible admin banners that Vipps sometimes wants to show users.


This file is part of the plugin Pay with Vipps and MobilePay for WooCommerce
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
    private $dismissed =  null;
    public $configured = false;
    public $vipps_checkout_enabled = false;
    public static function instance()  {
        if (!static::$instance) static::$instance = new VippsDismissibleAdminBanners();
        return static::$instance;
    }

    function __construct() {
       $this->dismissed = get_option('_vipps_dismissed_notices');
       $this->configured = get_option('woo-vipps-configured');
       $this->vipps_checkout_enabled = get_option('woo_vipps_checkout_activated'); // if true, the pages exists etc
    }

    public function add_vipps_dismissible_admin_banners() {
        if ($this->configured) {
           // Login with Vipps 
           $this->add_login_vipps_dismissible_admin_banner();
           // If WooCommerce Subscriptions is installed, but Vipps Recurring isn't, create a banner.
           $this->add_recurring_vipps_dismissible_admin_banner();
           // Advertise Vipps Checkout for users who haven't seen/dismissed the banner
           $this->add_vipps_checkout_dismissible_admin_banner();
        }
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
        $this->dismissed = $dismissed;
        wp_cache_flush();
    }

    // Advertise Vipps Checkout if not installed
    public function add_vipps_checkout_dismissible_admin_banner () {
        $dismissed = $this->dismissed;
        if (isset($dismissed['vippscheckout01'])) {
           return;
        }

        $dont_advertise_checkout = $this->vipps_checkout_enabled; // if "yes" or "no", the user has interacted with Vipps Checkout

        if ($dont_advertise_checkout) {
           if (!is_array($dismissed)) $dismissed = array();
           $dismissed['vippscheckout01'] = time();
           update_option('_vipps_dismissed_notices', $dismissed, false);
           $this->dismissed = $dismissed;
           return;
        }

        add_action('admin_notices', function () {
            $logo = plugins_url('img/vipps-rgb-orange-neg.svg',__FILE__);
            $settingsurl = admin_url("/admin.php?page=vipps_settings_menu");
            $screen = get_current_screen();
            if ($screen && $screen->id == 'woocommerce_page_wc-settings' && ($_GET['tab'] ?? false) == 'checkout') return;
            if ($screen && $screen->id == 'toplevel_page_vipps_admin_menu') return;
            if ($screen && $screen->id == 'vipps-mobilepay_page_vipps_settings_menu') return;
            ?>
            <div class='notice notice-vipps notice-vipps-neg notice-info is-dismissible'  data-key='vippscheckout01'>
            <a   href="<?php echo $settingsurl; ?>">
            <img src="<?php echo $logo; ?>" style="float:right; height: 3rem; margin-top: 0.2rem" alt="Vipps-logo">
             <div>
                 <p style="font-size:1rem">
                     <?php  printf(__('You can get %1$s now!', 'woo-vipps'), Vipps::CheckoutName()); ?>
                    <ul style='margin-left: 1rem; list-style-type: "✓  ";'>
                     <li><?php printf(__('Your customers can pay with %1$s , Visa or Mastercard', 'woo-vipps'), Vipps::CompanyName()); ?></li>
                     <li><?php _e("Shipping information is autofilled with Vipps", 'woo-vipps'); ?></li>
                     <li><?php _e("You get settlement in three days", 'woo-vipps'); ?>.</li>
                    </ul>
                 </p>
             </div>
             </a>
            </div>
            <?php
            });
    }

    // Advertise The Login Plugin if not installed
    public function add_login_vipps_dismissible_admin_banner () {
        if (!function_exists('get_plugins')) return;

        $dismissed = $this->dismissed;
        if (isset($dismissed['vippslogin01'])) return;

        $installed_plugins = get_plugins();
        if (isset($installed_plugins['login-with-vipps/login-with-vipps.php'])) {
           if (!is_array($dismissed)) $dismissed = array();
           $dismissed['vippslogin01'] = time();
           update_option('_vipps_dismissed_notices', $dismissed, false);
           $this->dismissed = $dismissed;
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

    // Advertise The Recurring Plugin if not installed and WooCommerce subscriptions is
    public function add_recurring_vipps_dismissible_admin_banner () {
        if (!function_exists('get_plugins')) return;

        $dismissed = $this->dismissed;
        if (isset($dismissed['vippssub01'])) return;

        if (!class_exists('WC_Subscriptions')) {
            // We only need this if the user has Woocommerce Subscriptions installed
            return;
        }

        $installed_plugins = get_plugins();
        if (class_exists( 'WC_Vipps_Recurring' ) || isset($installed_plugins['vipps-recurring-payments-gateway-for-woocommerce/woo-vipps-recurring.php']) || get_option('woo-vipps-recurring-version')) {
            if (!is_array($dismissed)) $dismissed = array();
            $dismissed['vippssub01'] = time();
            update_option('_vipps_dismissed_notices', $dismissed, false);
            $this->dismissed = $dismissed;
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
