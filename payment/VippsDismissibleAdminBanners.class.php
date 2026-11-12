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
           // The Checkout sold to Kustom banner
           $this->add_kustom_checkout_banner();
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
        Vipps::set_locale_if_in_header();
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

    // Notify users of Checkout that this is sold to Custom and should be 
    public function add_kustom_checkout_banner () {
        $dismissed = $this->dismissed;
        if (isset($dismissed['vippskustom01'])) return;

        $gw = Vipps::instance()->gateway();
        if (class_exists('VippsCheckout') && $gw->get_option('vipps_checkout_enabled') == 'yes') { 
            add_action('admin_notices', function () {
                    $logo = plugins_url('img/vipps-rgb-orange-neg.svg',__FILE__);
                    $kustomurl = "https://docs.kustom.co/contents/partners/e-commerce-platforms/woocommerce-vipps-guide";
                    ?>
                    <div class='notice notice-vipps notice-vipps-neg notice-info is-dismissible'  data-key='vippskustom01'>
                    <img src="<?php echo $logo; ?>" style="float:left; height: 3rem; margin-top: 0.2rem" alt="Vipps-logo">
                    <div>
                    <h2 style='color:white'><?php echo __('Checkout - Important Update', 'woo-vipps'); ?></h2>
                    <p style="color:white;font-size:1rem"><?php echo sprintf(__("Vipps MobilePay has entered into an agreement to sell Checkout to Kustom. As part of this transition, <b>Vipps Mobilepay Checkout will become Kustom Checkout</b>. You can follow <a style='font-weight:bold' target='_blank', href='%s'>this guide</a></b> to migrate over to Kustom Checkout.", 'woo-vipps'), esc_attr($kustomurl)); ?></p>
                    <p style="color:white;font-size:1rem"><?php echo sprintf(__('For help you can reach out to <a href="mailto:%1$s">%1$s</a> and <a href="tel:%2$s">%3$s</a>. You can also find the Kustom portal <a href="%4$s" target="_blank">here</a>.', 'woo-vipps'), 'support@kustom.co', '+4721564684', '+47 21 56 46 84', 'https://portal.kustom.co/'); ?></p>
                    </div>
                    </div>
                    <?php
                    });
        }
    }

}
