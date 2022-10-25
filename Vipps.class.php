<?php
/*
   This class is for hooks and plugin managent, and is instantiated as a singleton and set globally as $Vipps. IOK 2018-02-07
   For WP-specific interactions.


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
require_once(dirname(__FILE__) . "/VippsAPIException.class.php");

class Vipps {
    /* This directory stores the files used to speed up the callbacks checking the order status. IOK 2018-05-04 */
    private $callbackDirname = 'wc-vipps-status';
    private static $instance = null;
    private $countrymap = null;
    // Used to provide the order in a callback to the session handler etc. IOK 2019-10-21
    public $callbackorder = 0;

    // used in the fake locking mechanism using transients
    private $lockKey = null; 

    public $vippsJSConfig = array();

    function __construct() {
    }

    public static function instance()  {
        if (!static::$instance) static::$instance = new Vipps();
        return static::$instance;
    }

    public static function register_hooks() {
        $Vipps = static::instance();
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

    // Get the singleton WC_GatewayVipps instance
    public function gateway() {
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        return WC_Gateway_Vipps::instance();
    }


    // These are strings that should be available for translation possibly at some future point. Partly to be easier to work with translate.wordpress.org
    // Other usages are to translate any dynamic strings that may come from APIs etc. IOK 2021-03-18
    private function translatable_strings() {
        // For new settings setup
        __("Send receipts and order confirmation info to the customers' app on completed purchases.", 'woo-vipps');
        __("Send receipts to the customers Vipps app", 'woo-vipps');
        __("If this is checked, a receipt will be sent to Vipps which will be viewable in the users' app, specifying the order items, shipping et cetera", 'woo-vipps');

        __("You can get Vipps Checkout now!", 'woo-vipps');
        __("Your customers can pay with Vipps, Visa or Mastercard", 'woo-vipps');
        __("Shipping information is autofilled with Vipps", 'woo-vipps');
        __("You get settlement in three days", 'woo-vipps');

    }


    public function init () {
        add_action('wp_loaded', array($this, 'wp_register_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));


        // Cart restoration and other post-purchase actions, mostly for express checkout IOK 2020-10-09
        add_action('woocommerce_thankyou_vipps', array($this, 'woocommerce_thankyou'), 10, 1); 

        add_filter('woocommerce_my_account_my_orders_actions', array($this,'woocommerce_my_account_my_orders_actions'), 10, 2);

        // Used in 'compat mode' only to add products to the cart
        add_filter('woocommerce_add_to_cart_redirect', array($this,  'woocommerce_add_to_cart_redirect'), 10, 1);

        // Some stuff in the head for dynamic css etc
        add_action('wp_head', array($this, 'wp_head'));

        // Handle the hopefully asynch call to send Order Management data on payment complete.
        add_action('admin_post_nopriv_woo_vipps_order_management', array($this, 'do_order_management'));
        add_action('admin_post_woo_vipps_order_management', array($this, 'do_order_management'));

        // For Vipps Checkout, poll for the result of the current session
        add_action('wp_ajax_vipps_checkout_poll_session', array($this, 'vipps_ajax_checkout_poll_session'));
        add_action('wp_ajax_nopriv_vipps_checkout_poll_session', array($this, 'vipps_ajax_checkout_poll_session'));
        // Use ajax to initiate the session too
        add_action('wp_ajax_vipps_checkout_start_session', array($this, 'vipps_ajax_checkout_start_session'));
        add_action('wp_ajax_nopriv_vipps_checkout_start_session', array($this, 'vipps_ajax_checkout_start_session'));
        // Ensure we remove the current session on the thank you page (too).
        add_action('woocommerce_thankyou_vipps', function () {
            WC()->session->set('current_vipps_session', false);
            WC()->session->set('vipps_checkout_current_pending',false);
            WC()->session->set('vipps_address_hash', false);
        });

        // Activate support for Vipps Checkout, including creating the special checkout page etc
        add_action('wp_ajax_woo_vipps_activate_checkout_page', function () {
          check_ajax_referer('woo_vipps_activate_checkout','_wpnonce');
          $this->gateway()->maybe_create_vipps_pages();
  
          if (isset($_REQUEST['activate']) && $_REQUEST['activate']) {
             $this->gateway()->update_option('vipps_checkout_enabled', 'yes');
          } else {
             $this->gateway()->update_option('vipps_checkout_enabled', 'no');
          }


        });



        $this->add_shortcodes();

        // For Vipps Checkout - we need to know any time and as soon as the cart changes, so fold all the events into a single one. IOK 2021-08-24
        // Product added
        add_action( 'woocommerce_add_to_cart', function ($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
            do_action('vipps_cart_changed');
        }, 10, 6);
        // Cart emptied 
        add_action( 'woocommerce_cart_emptied', function ($clear_persistent_cart) {
            do_action('vipps_cart_changed');
        }, 10, 1);
        // After updating quantities
        add_action('woocommerce_after_cart_item_quantity_update', function ( $cart_item_key,  $quantity,  $old_quantity ) {
            do_action('vipps_cart_changed');
        }, 10, 3);
        // Blocks and ajax
        add_action( 'woocommerce_cart_item_removed', function ($cart_item_key, $cart) {
            do_action('vipps_cart_changed');
        }, 10, 3);
        // Restore deleted entry
        add_action( 'woocommerce_cart_item_restored', function ($cart_item_key, $cart) {
            do_action('vipps_cart_changed');
        }, 10, 3);
        // Normal cart form update
        add_filter('woocommerce_update_cart_action_cart_updated', function ($updated) {
            do_action('vipps_cart_changed');
            return $updated;
        });
        // Then handle the actual cart change
        add_action('vipps_cart_changed', array($this, 'cart_changed'));


        // We need a 5-minute scheduled event for the handler for missed callbacks. Using the 
        // action scheduler would be better, but we can't do that just yet because of backwards 
        // compatibility. At some point, support for older woo-versions should be dropped; then this
        // should use the action scheduler instead. IOK 2021-06-21
        add_filter('cron_schedules', function ($schedules) {
            if(!isset($schedules["5min"])){
                $schedules["5min"] = array(
                    'interval' => 5*60,
                    'display' => __('Once every 5 minutes'));
            }
            return $schedules;
        });

        // Offload work to wp-cron so it can be done in the background on sites with heavy load IOK 2020-04-01
        add_action('vipps_cron_cleanup_hook', array($this, 'cron_cleanup_hook'));
        // Check periodically for orders that are stuck pending with no callback IOK 2021-06-21
        add_action('vipps_cron_missing_callback_hook', array($this, 'cron_check_for_missing_callbacks'));

        // This is a developer-mode level feature because flock() is not portable. This ensures callbacks and shopreturns do not
        // simultaneously update the orders, in particular not the express checkout order lines wrt shipping. IOK 2020-05-19
        if ($this->gateway()->get_option('use_flock') == 'yes') {
            add_filter('woo_vipps_lock_order', array($this,'flock_lock_order'));
            add_action('woo_vipps_unlock_order', array($this, 'flock_unlock_order'));
        }
    }

    public function admin_init () {
        $gw = $this->gateway();
        // Stuff for the Order screen
        add_action('woocommerce_order_item_add_action_buttons', array($this, 'order_item_add_action_buttons'), 10, 1);


        // Stuff for the special Vipps Checkout page
        add_filter('woocommerce_settings_pages', array($this, 'woocommerce_settings_pages'), 10, 1);

        require_once(dirname(__FILE__) . "/VippsDismissibleAdminBanners.class.php");
        VippsDismissibleAdminBanners::add();

        // Styling etc
        add_action('admin_head', array($this, 'admin_head'));

        // Scripts
        add_action('admin_enqueue_scripts', array($this,'admin_enqueue_scripts'));

        // Custom product properties
        add_filter('woocommerce_product_data_tabs', array($this,'woocommerce_product_data_tabs'),99);
        add_action('woocommerce_product_data_panels', array($this,'woocommerce_product_data_panels'),99);
        add_action('woocommerce_process_product_meta', array($this, 'process_product_meta'), 10, 2);

        add_action('save_post', array($this, 'save_order'), 10, 3);

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));

        // Keep admin notices during redirects IOK 2018-05-07
        add_action('admin_notices',array($this,'stored_admin_notices'));

        add_action('admin_notices', function () {
});

        // Ajax just for the backend
        add_action('wp_ajax_vipps_create_shareable_link', array($this, 'ajax_vipps_create_shareable_link'));
        add_action('wp_ajax_vipps_payment_details', array($this, 'ajax_vipps_payment_details'));


        // Link to the settings page from the plugin list
        add_filter( 'plugin_action_links_'.plugin_basename( plugin_dir_path( __FILE__ ) . 'woo-vipps.php'), array($this, 'plugin_action_links'));

        if ($gw->enabled == 'yes' && $gw->is_test_mode()) {
            $what = __('Vipps is currently in test mode - no real transactions will occur', 'woo-vipps');
            $this->add_vipps_admin_notice($what,'info', '', 'test-mode');
        }


        // This requires merchants using the old shipping callback filter to choose between this or the new shipping method mechanism. IOK 2020-02-17
        if (has_action('woo_vipps_shipping_methods')) {
            $option = $gw->get_option('newshippingcallback');
            if ($option != 'old' && $option != 'new') {
                        $what = __('Your theme or a plugin is currently overriding the <code>\'woo_vipps_shipping_methods\'</code> filter to customize your shipping alternatives.  While this works, this disables the newer Express Checkout shipping system, which is neccessary if your shipping is to include metadata. You can do this, or stop this message, from the <a href="%s">settings page</a>', 'woo-vipps');
                        $this->add_vipps_admin_notice($what,'info');
            }
        }

        // IOK 2020-04-01 If the plugin is updated, the normal 'activate' hook may not run. Add the scheduled events if not present.
        // Normal updates will not need this, but if updates are 'sideloaded', it is neccessary still. This call will only do work if the
        // jobs are not scheduled. We'll ensure the action is active first time an admin logs in.
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            static::maybe_add_cron_event();
            if (!get_option('woo-vipps-configured')) {
                list($ok, $msg) = $gw->check_connection();
                if (!$ok){ 
                    if ($msg) {
                        $this->add_vipps_admin_notice(sprintf(__("<p>Vipps not yet correctly configured:  please go to <a href='%s'>the Vipps settings</a> to complete your setup:<br> %s</p>", 'woo-vipps'), admin_url('/admin.php?page=wc-settings&tab=checkout&section=vipps'), $msg));
                    } else {
                        $this->add_vipps_admin_notice(sprintf(__("<p>Vipps not yet configured:  please go to <a href='%s'>the Vipps settings</a> to complete your setup!</p>", 'woo-vipps'), admin_url('/admin.php?page=wc-settings&tab=checkout&section=vipps')));
                    }
                } 

            }
        }

    }

    public function admin_menu_page () {
        // The function which is hooked in to handle the output of the page must check that the user has the required capability as well.  (manage_woocommerce)
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You don\'t have sufficient rights to access this page', 'woo-vipps'));
        }

        $recurringsettings = admin_url('/admin.php?page=wc-settings&tab=checkout&section=vipps_recurring');
        $checkoutsettings  = admin_url('/admin.php?page=wc-settings&tab=checkout&section=vipps');
        $loginsettings = admin_url('/options-general.php?page=vipps_login_options');

        $logininstall = admin_url('/plugin-install.php?s=login-with-vipps&tab=search&type=term');
        $subscriptioninstall = 'https://woocommerce.com/products/woocommerce-subscriptions/';
        $recurringinstall = admin_url('/plugin-install.php?s=vipps-recurring-payments-gateway-for-woocommerce&tab=search&type=term');

        $logspage = admin_url('/admin.php?page=wc-status&tab=logs');
        $forumpage = 'https://wordpress.org/support/plugin/woo-vipps/';

        $portalurl = 'https://portal.vipps.no';

        $installed = get_plugins();
        $recurringinstalled = array_key_exists('vipps-recurring-payments-gateway-for-woocommerce/woo-vipps-recurring.php',$installed);
        $recurringactive = class_exists('WC_Vipps_Recurring');

        $logininstalled = array_key_exists('login-with-vipps/login-with-vipps.php', $installed);
        $loginactive = class_exists('ContinueWithVipps');
        $slogan = __('- very, very simple', 'woo-vipps');
 

        $gw = $this->gateway();
        $configured =  get_option('woo-vipps-configured', false);
        $isactive = ($gw->enabled == 'yes');
        $istestmode = $gw->is_test_mode();
        $ischeckout = false;
        if ($isactive) {
           $ischeckout = ($gw->get_option('vipps_checkout_enabled') == 'yes');
        }
/*
        $connected = false;
        $connerror = "";
        // Don't do this unless by button press. IOK 2022-05-04.
        if ($configured) {
            list($connected,$connerror)  = $gw->check_connection();
        }
*/




    ?>
    <style>.notice.notice-vipps.test-mode { display: none; }body.wp-admin.toplevel_page_vipps_admin_menu #wpcontent {background-color: white; }</style>
    <header class="vipps-admin-page-header">
            <h1><span><img src="<?php echo plugins_url('/img/vipps-rgb-orange-pos.svg', __FILE__);?>" alt="Vipps"></span><span class="slogan"><?php echo esc_html($slogan); ?></span></h1>
    </header>
    <div class='wrap vipps-admin-page'>
            <div id="vipps_page_vipps_banners"><?php echo apply_filters('woo_vipps_vipps_page_banners', ""); ?></div>
            <h1><?php _e("Vipps for WordPress and WooCommerce", 'woo-vipps'); ?></h1>
            <p><?php _e("Vipps officially supports WordPress and WooCommerce with a family of plugins implementing a payment gateway for WooCommerce, an Express Checkout for WooCommerce, an optional complete checkout solution powered by Vipps, a system for managing QR-codes that link to your products or landing pages,  a plugin for recurring payments, and a system for passwordless logins", 'woo-vipps');?></p>
            <p><?php echo sprintf( __("To order or  configure your Vipps account that powers these plugins, log onto <a target='_blank'  href='%s'>the Vipps portal</a> and use the keys and data from that to set up your plugins as needed.", 'woo-vipps'), $portalurl); ?></p>

            <h1><?php _e("The Vipps plugins", 'woo-vipps'); ?></h1>
            <div class="pluginsection woo-vipps">
               <h2><?php _e('Pay with Vipps for WooCommerce', 'woo-vipps' );?></h2>
               <p><?php _e("This plugin, currently active at your site, implements a Vipps checkout solution in WooCommerce, and Express Checkout solution for instant buys using the Vipps app, and an alternate Vipps-hosted checkout supporting both Vipps and credit cards. It also supports Vipps' QR-api for creating QR-codes to your landing pages or products", 'woo-vipps'); ?></p>
               <p><?php echo sprintf(__("Configure the plugin on its <a href='%s'>settings page</a> and  get your keys from the <a target='_blank'>Vipps portal</a>.",'woo-vipps'), $checkoutsettings, $portalurl);?></p>
               <p><?php echo sprintf(__("If you experience problems or unexpected results, please check the 'fatal-errors' and 'woo-vipps' logs at <a href='%s'>WooCommerce logs page</a>.", 'woo-vipps'), $logspage); ?></p>
               <p><?php echo sprintf(__("If you need support, please use the <a href='%s'>forum page</a> for the plugin. If you cannot post your question publicly, contact WP-Hosting directly at support@wp-hosting.no.", 'woo-vipps'), $forumpage); ?></p>
               <div class="pluginstatus vipps_admin_highlighted_section">
               <?php if ($istestmode): ?>
                  <p><b>
                   <?php _e('Vipps is currently in test mode - no real transactions will occur', 'woo-vipps'); ?>
                  </b></p>
               <?php endif; ?>
               <p>
                  <?php if ($configured): ?>
                    <?php echo sprintf(__("<a href='%s'>Vipps configuration</a> is complete.", 'woo-vipps'), $checkoutsettings); ?> 
                  <?php else: ?>
                    <?php echo sprintf(__("Vipps configuration is not yet complete - you must get your keys from the Vipps portal and enter them on the <a href='%s'>settings page</a>", 'woo-vipps'), $checkoutsettings); ?> 
                  <?php endif; ?>
               </p>
               <?php if ($isactive): ?>
                 <p> 
                   <?php _e("The plugin is currently <b>active</b> - Vipps is available as a payment method.", 'woo-vipps'); ?>
                   <?php if ($ischeckout): ?>
                      <?php _e("You are using Vipps Checkout instead of the standard WooCommerce Checkout page.", 'woo-vipps'); ?>     
                   <?php endif; ?>
                 </p>
               <?php else:; ?>
               <?php endif; ?>
              </div>

            </div>

            <div class="pluginsection vipps-recurring">
               <h2><?php _e( 'Vipps Recurring Payments', 'woo-vipps' );?></h2>
               <p>
                  <?php echo sprintf(__("<a href='%s' target='_blank'>Vipps Recurring Payments for WooCommerce</a> by <a href='%s' target='_blank'>Everyday</a>  is perfect for you if you run a web shop with subscription based services or other products that would benefit from subscriptions.", 'woo-vipps'), 'https://www.wordpress.org/plugins/vipps-recurring-payments-gateway-for-woocommerce/', 'https://everyday.no/'); ?>
                  <?php echo sprintf(__("Vipps Recurring Payments requires the <a href='%s' target='_blank'>WooCommerce Subscriptions plugin</a>.", 'woo-vipps'), 'https://woocommerce.com/products/woocommerce-subscriptions/'); ?>
               <?php do_action('vipps_page_vipps_recurring_payments_section'); ?>
               <div class="pluginstatus vipps_admin_highlighted_section">
               <?php if ($recurringactive): ?>
                     <p>
                       <?php echo sprintf(__("Vipps Recurring Payments is <b>installed and active</b>. You can configure the plugin at its <a href='%s'>settings page</a>", 'woo-vipps'),$recurringsettings); ?>
                    </p>
               <?php elseif ($recurringinstalled): ?>
                     <p>
                     <?php echo sprintf(__("Vipps Recurring Payments is installed, but <em>not active</em>. Activate it on the <a href='%s'>plugins page</a>", 'woo-vipps'), admin_url("/plugins.php")); ?>
                     </p>
               <?php else: ?>
                     <p>
                     <?php echo sprintf(__("Vipps Recurring Payments is not installed. You can install it <a href='%s'>here!</a>", 'woo-vipps'), $recurringinstall); ?>
                     </p>
               <?php endif; ?> 
               </div>

            </div>

            <div class="pluginsection login-with-vipps">
               <h2><?php _e( 'Login with Vipps', 'woo-vipps' );?></h2>
               <p><?php echo sprintf(__("<a href='%s' target='_blank'>Login with Vipps</a> is a password-less solution that lets you or your customers to securely log into your site without having to remember passwords - you only need the Vipps app. The plugin does not require WooCommerce, and it can be customized for many different usecases.", 'woo-vipps'), 'https://www.wordpress.org/plugins/login-with-vipps/'); ?></p>
               <p> <?php _e("If you use Vipps Checkout or Express Checkout in WooCommerce, this allows your Vipps customers to safely log in without ever using a password.", 'woo-vipps'); ?>
               <p>
                       <?php echo sprintf(__("Remember, you need to set up Login with Vipps at the <a target='_blank' href='%s'>Vipps Portal</a>, where you will find the keys you need and where you will have to register the <em>return url</em> you will find on the settings page.", 'woo-vipps'),$portalurl); ?>
               </p>

               <div class="pluginstatus vipps_admin_highlighted_section">
               <?php if ($loginactive): ?>
                     <p>
                       <?php echo sprintf(__("Login with Vipps is installed and active. You can configure the plugin at its <a href='%s'>settings page</a>", 'woo-vipps'),$loginsettings); ?>
                    </p>
               <?php elseif ($logininstalled): ?>
                     <p>
                     <?php echo sprintf(__("Login with Vipps is installed, but not active. Activate it on the <a href='%s'>plugins page</a>", 'woo-vipps'), admin_url("/plugins.php")); ?>
                     </p>
               <?php else: ?>
                     <p>
                     <?php echo sprintf(__("Login with Vipps is not installed. You can install it <a href='%s'>here!</a>", 'woo-vipps'), $logininstall); ?>
                     </p>
               <?php endif; ?>
               </div>

            </div>
   
    </div> 
    <?php
    }

    // Add a link to the settings page from the plugin list
    public function plugin_action_links ($links) {
        $link = '<a href="'.esc_url(admin_url('/admin.php?page=wc-settings&tab=checkout&section=vipps')). '">'.__('Settings', 'woo-vipps').'</a>';
        array_unshift( $links, $link);
        return $links;
    }

    // Extra stuff in the <head>, which will mostly mean dynamic CSS
    public function wp_head () {
        // If we have a Vipps Checkout page, stop iOS from giving previews of it that
        // starts the session - iOS should use the visibility API of the browser for this, but it doesn't as of 2021-11-11
        $checkoutid = wc_get_page_id('vipps_checkout');
        if ($checkoutid) {
            $url = get_permalink($checkoutid);
            echo "<style> a[href=\"$url\"] { -webkit-touch-callout: none;  } </style>\n";
        } 
    }
   

    // Requested by Vipps: It is a feature of this plugin that a prefix is added to the order number, in order to make it possible to use several different stores
    // that may use the same ordre number ranges. The prefix used to be just "Woo" by default, but Vipps felt it would be easier to respond to support request by
    // (trying to) identify the store/site directly in the order prefix. So this does that: It creates a prefix "woo-" + 8 chars derived from the domain of the siteurl.
    // The result should be "woo-abcdefgh-" which should leave 18 digits for the actual order number. IOK 2020-05-19 
    public function generate_order_prefix() {
        $parts = parse_url(site_url());
        if (!$parts) return 'Woo';
        $domain = explode(".", $parts['host']);
        if (empty($domain)) return 'Woo';
        $first = strtolower($domain[0]);
        $second = isset($domain[1]) ? $domain[1] : ''; 
        $key = 'Woo';
        // Select first part of domain unless that has no content, otherwise second. Default to Woo again.
        if (in_array($first, array('www','test','dev','vdev')) && !empty($second)) { 
           $key = $second;
        } else {
           $key = $first;
        }
        // Use only 8 chars for the site. Try to make it so by dropping vowels, if that doesn't succeed, just chop it.
        $key = $key;
        $key = sanitize_title($key);
        $len = strlen($key);
        if ($len <= 8) return "woo-$key-";
        $kzk = preg_replace("/[aeiouæøåüö]/i","",$key);
        if (strlen($kzk) <= 8) return "woo-$kzk-";
        return "woo-" . substr($key,0,8) . "-";
    }

    // Add a backend notice to stand out a bit, using a Vipps logo and the Vipps color for info-level messages. IOK 2020-02-16
    public function add_vipps_admin_notice ($text, $type='info',$key='', $extraclasses='') {
                if ($key) {
                    $dismissed = get_option('_vipps_dismissed_notices');
                    if (isset($dismissed[$key])) return;
                }
                add_action('admin_notices', function() use ($text,$type, $key, $extraclasses) {
                        $logo = plugins_url('img/vipps_logo_rgb.png',__FILE__);
                        $text= "<img style='height:40px;float:left;' src='$logo' alt='Vipps-logo'> $text";
                        $message = sprintf($text, admin_url('admin.php?page=wc-settings&tab=checkout&section=vipps'));
                        echo "<div class='notice notice-vipps notice-$type $extraclasses is-dismissible'  data-key='" . esc_attr($key) . "'><p>$message</p></div>";
                        });
    }


    // This function will delete old orders that were cancelled before the Vipps action was completed. We keep them for
    // 10 minutes so we can work with them in hooks and callbacks after they are cancelled. IOK 2019-10-22
#    protected function delete_old_cancelled_orders() {
    public function delete_old_cancelled_orders() {
        global $wpdb;
        $limit = 30;
        $cutoff = time() - 600; // Ten minutes old orders: Delete them
        $oldorders = time() - (60*60*24*7); // Very old orders: Ignore them to make this work on sites with enormous order databases

        /*
         * A. If you are joining custom metadata, then using WC_Query with meta_query param will handle both data storage types. If you are joining a meta_key, which is now migrated to a proper column in the HPOS, then you should be able to use a field_query param with the same property as a meta_query. We plan to backport this to CPT so that the same query works for both tables.
         */
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', function ($query, $query_vars) {
            if (isset($query_vars['meta_vipps_delendum']) && $query_vars['meta_vipps_delendum'] ) {
                if (!isset($query['meta_query'])) $query['meta_query'] = array();
                $query['meta_query'][] = array(
                    'key' => '_vipps_delendum',
                    'value' => 1
                );
            }
            return $query;
        }, 10, 2);

        $delenda = wc_get_orders( array(
            'status' => 'cancelled',
            'type' => 'shop_order',
            'limit' => $limit,
            'date_modified' => "$oldorders...$cutoff",
            'meta_vipps_delendum' => 1
        ) );

        foreach ($delenda as $del) {
            // Delete only if there is no customer info for the order IOK 2022-10-12
            if (!$del->get_billing_email()) {
                $del->delete(true);
            }  else {
                // If we've gotten a billing email, don't delete this. IOK 2022-10-12
                $del->delete_meta_data('_vipps_delendum');
            }
        }
    }

    // This is called asynch/nonblocking on payment_complete
    public function do_order_management() {
        $orderid = isset($_POST['orderid']) ? $_POST['orderid'] : false;
        $orderkey = isset($_POST['orderkey']) ? $_POST['orderkey'] : false;
        if ($orderid && $orderkey) {
          // This will keep running even if the request ends, and this method is called asynchrounously.
          add_action('shutdown', function () use ($orderid, $orderkey) { WC_Gateway_Vipps::instance()->payment_complete_at_shutdown ($orderid, $orderkey); });
          http_response_code(200);
          header('Content-Type: application/json; charset=utf-8');
          header("Content-length: 1");
          print "1";
          flush();
        } else {
          http_response_code(403);
        }
    }


    public function admin_head() {
        // Add some styling to the Vipps product-meta box
        $smile= plugins_url('img/vipps-smile-orange.png',__FILE__);
        ?>
            <style>
            @media only screen and (max-width: 900px) {
               #woocommerce-product-data ul.wc-tabs li.vipps_tab a:before {
                       background: url(<?php echo $smile ?>) center center no-repeat;
                       content: " " !important;
                       background-size: 20px 20px;
               }
            }
            @media only screen and (min-width: 900px) {
               #woocommerce-product-data ul.wc-tabs li.vipps_tab a:before {
                    background: url(<?php echo $smile ?>) center center no-repeat;
                    content: " " !important;
                    background-size:100%;
                    width:13px;height:13px;display:inline-block;line-height:1;
               }
            }
            </style>
    <?php
    }
    // Scripts used in the backend
    public function admin_enqueue_scripts($hook) {
        wp_register_script('vipps-admin',plugins_url('js/admin.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/admin.js"), 'true');
        $this->vippsJSConfig['vippssecnonce'] = wp_create_nonce('vippssecnonce');
        wp_localize_script('vipps-admin', 'VippsConfig', $this->vippsJSConfig);
        wp_enqueue_script('vipps-admin');

        wp_enqueue_style('vipps-admin-style',plugins_url('css/admin.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/admin.css"), 'all');
        wp_enqueue_style('vipps-fonts');
        wp_enqueue_style('vipps-fonts',plugins_url('css/fonts.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/fonts.css"), 'all');

    }

    public function notice_is_test_mode() {
    }

    public function admin_menu () {
            $smile= plugins_url('img/vipps-smile-orange.png',__FILE__);
            add_menu_page(__("Vipps", 'woo-vipps'), __("Vipps", 'woo-vipps'), 'manage_woocommerce', 'vipps_admin_menu', array($this, 'admin_menu_page'), $smile, 58);
    }

    public function add_meta_boxes () {
        // Metabox showing order status at Vipps IOK 2018-05-07
        global $post;
        if ($post && get_post_type($post) == 'shop_order' ) {
            $order = wc_get_order($post);
            $pm = $order->get_payment_method();
            if ($pm == 'vipps') { 
                add_meta_box( 'vippsdata', __('Vipps','woo-vipps'), array($this,'add_vipps_metabox'), 'shop_order', 'side', 'core' );
            }
        }
    }
    public function wp_register_scripts () {
        //  We are going to use the 'hooks' library introduced by WP 5.1, but we still support WP 4.7. So if this isn't enqueues 
        //  (which it only is if Gutenberg is active) or not provided at all, add it now.
        if (!wp_script_is( 'wp-hooks', 'registered')) {
            wp_register_script('wp-hooks', plugins_url('/compat/hooks.min.js', __FILE__));
        }
        wp_register_script('vipps-gw',plugins_url('js/vipps.js',__FILE__),array('jquery','wp-hooks'),filemtime(dirname(__FILE__) . "/js/vipps.js"), 'true');
        wp_localize_script('vipps-gw', 'VippsConfig', $this->vippsJSConfig);

        $sdkurl = $this->gateway()->is_test_mode() ? 'https://checkout.vipps.no/vippsCheckoutSDK.js' : 'https://vippscheckoutprod.z6.web.core.windows.net/vippsCheckoutSDK.js';
        wp_register_script('vipps-sdk',$sdkurl,array());
        wp_register_script('vipps-checkout',plugins_url('js/vipps-checkout.js',__FILE__),array('vipps-gw','vipps-sdk'),filemtime(dirname(__FILE__) . "/js/vipps-checkout.js"), 'true');
    }

    public function wp_enqueue_scripts() {
        wp_enqueue_script('vipps-gw');
        wp_enqueue_style('vipps-gw',plugins_url('css/vipps.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/vipps.css"));
    }


    public function add_shortcodes() {
        add_shortcode('woo_vipps_buy_now', array($this, 'buy_now_button_shortcode'));
        add_shortcode('woo_vipps_express_checkout_button', array($this, 'express_checkout_button_shortcode'));
        add_shortcode('woo_vipps_express_checkout_banner', array($this, 'express_checkout_banner_shortcode'));
        // The Vipps Checkout feature which overrides the normal checkout process.
        add_shortcode('vipps_checkout', array($this, 'vipps_checkout_shortcode'));
    }

    public function log ($what,$type='info') {
        $logger = wc_get_logger();
        $context = array('source'=>'woo-vipps');
        $logger->log($type,$what,$context);
    }


    // If we have admin-notices that we haven't gotten a chance to show because of
    // a redirect, this method will fetch and show them IOK 2018-05-07
    public function stored_admin_notices() {
        $stored = get_transient('_vipps_save_admin_notices');
        if ($stored) {
            delete_transient('_vipps_save_admin_notices');
            print $stored;
        }
    }

    // Show express option on checkout form too
    public function before_checkout_form_express () {
        if (is_user_logged_in()) return;
        $this->express_checkout_banner();
    }

    public function express_checkout_banner() {
        $gw = $this->gateway();
        if (!$gw->show_express_checkout()) return;
        return $this->express_checkout_banner_html();
    }

    public function express_checkout_banner_html() {
        $url = $this->express_checkout_url();
        $url = wp_nonce_url($url,'express','sec');
        $text = __('Skip entering your address and just checkout using', 'woo-vipps');
        $linktext = __('express checkout','woo-vipps');
        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);

        $message = $text . "<a href='$url'> <img class='inline vipps-logo negative' border=0 src='$logo' alt='Vipps'/> $linktext!</a>";
        $message = apply_filters('woo_vipps_express_checkout_banner', $message, $url);
        ?>
            <div class="woocommerce-info vipps-info"><?php echo $message;?></div>
            <?php
    }

    // Show the express button if reasonable to do so
    public function cart_express_checkout_button() {
        $gw = $this->gateway();

        if ($gw->show_express_checkout()){
            return $this->cart_express_checkout_button_html();
        }
    }

    public function cart_express_checkout_button_html() {
        $url = $this->express_checkout_url();
        $url = wp_nonce_url($url,'express','sec');
        $imgurl= apply_filters('woo_vipps_express_checkout_button', plugins_url('img/pay-with-vipps.svg',__FILE__));
        $title = __('Buy now with Vipps!', 'woo-vipps');
        $button = "<a href='$url' class='button vipps-express-checkout' title='$title'><img alt='$title' border=0 src='$imgurl'></a>";
        $button = apply_filters('woo_vipps_cart_express_checkout_button', $button, $url);
        echo $button;
    }

    // A shortcode for a single buy now button. Express checkout must be active; but I don't check for this here, as this button may be
    // cached. Therefore stock, purchasability etc will be done later. IOK 2018-10-02
    public function buy_now_button_shortcode ($atts) {
        $args = shortcode_atts( array( 'id' => '','variant'=>'','sku' => '',), $atts );
        return $this->get_buy_now_button($args['id'], $args['variant'], $args['sku'], false);
    }

    // The express checkout shortcode implementation. It does not need to check if we are to show the button, obviously, but needs to see if the cart works
    public function express_checkout_button_shortcode() {
        $gw = $this->gateway();
        if (!$gw->cart_supports_express_checkout()) return;
        ob_start();
        $this->cart_express_checkout_button_html();
        return ob_get_clean();
    }
    // Show a banner normally shown for non-logged-in-users at the checkout page.  It does not need to check if we are to show the button, obviously, but needs to see if the cart works
    public function express_checkout_banner_shortcode() {
        $gw = $this->gateway();
        if (!$gw->cart_supports_express_checkout()) return;
        ob_start();
        $this->express_checkout_banner_html();
        return ob_get_clean();
    }

    // Manage the various product meta fields
    public function process_product_meta ($id, $post) {
        // This is for the 'buy now' button
        if (isset($_POST['woo_vipps_add_buy_now_button'])) {
            update_post_meta($id, '_vipps_buy_now_button', sanitize_text_field($_POST['woo_vipps_add_buy_now_button']));
        }
        // This is for the shareable links.
        if (isset($_POST['woo_vipps_shareable_delenda'])) {
            $delenda = array_map('sanitize_text_field',$_POST['woo_vipps_shareable_delenda']);
            foreach($delenda as $delendum) {
                // This will delete the actual link
                delete_post_meta($post->ID, '_vipps_shareable_link_'.$delendum);
            }
            // This will delete the item from the list of links for the product
            $shareables = get_post_meta($post->ID,'_vipps_shareable_links', false);
            foreach ($shareables as $shareable) {
                if (in_array($shareable['key'], $delenda)) {
                    delete_post_meta($post->ID,'_vipps_shareable_links', $shareable);
                }
            }
        }
    }

    // An extra product meta tab for Vipps 
    public function woocommerce_product_data_tabs ($tabs) {
        $img =  plugins_url('img/vipps_logo.png',__FILE__);
        $tabs['vipps'] = array( 'label' =>  __('Vipps', 'woo-vipps'), 'priority'=>100, 'target'=>'woo-vipps', 'class'=>array());
        return $tabs;
    }
    public function woocommerce_product_data_panels() {
        global $post;
        echo "<div id='woo-vipps' class='panel woocommerce_options_panel'>";
        $this->product_options_vipps();
        $this->product_options_vipps_shareable_link();
        echo "</div>";
    }
    // Product data specific to Vipps - mostly the use of the 'Buy now!' button
    public function product_options_vipps() {
        $gw = $this->gateway();
        $choice = $gw->get_option('singleproductexpress');
        echo '<div class="options_group">';
        echo "<div class='blurb' style='margin-left:13px'><h4>";
        echo __("Buy-now button", 'woo-vipps') ;
        echo "<h4></div>";
        if ($choice == 'some') {
            $button = sanitize_text_field(get_post_meta( get_the_ID(), '_vipps_buy_now_button', true));
            echo "<input type='hidden' name='woo_vipps_add_buy_now_button' value='no' />";
            woocommerce_wp_checkbox( array(
                        'id'      => 'woo_vipps_add_buy_now_button',
                        'value'   => $button,
                        'label'   => __('Add  \'Buy now with Vipps\' button', 'woo-vipps'),
                        'desc_tip' => true,
                        'description' => __('Add a \'Buy now with Vipps\'-button to this product','woo-vipps')
                        ) ); 
        } else if ($choice == "all") {
          $prod = wc_get_product(get_the_ID());
          $canbebought = false;
          if (is_a($prod, 'WC_Product')) {
              $canbebought = $gw->product_supports_express_checkout(wc_get_product(get_the_ID()));
          }

          echo "<p>";
          $settings = esc_url(admin_url('/admin.php?page=wc-settings&tab=checkout&section=vipps'));
          echo sprintf(__("The <a href='%s'>Vipps settings</a> are currently set up so all products that can be bought with Express Checkout will have a Buy Now button.", 'woo-vipps'), $settings); 
          echo " ";
          if ($canbebought) {
            echo __("This product supports express checkout, and so will have a Buy Now button." , 'woo-vipps');
          } else {
            echo __("This product does <b>not</b> support express checkout, and so will <b>not</b> have a Buy Now button." , 'woo-vipps');
          } 
          echo "</p>";
        } else {
         $settings = esc_url(admin_url('/admin.php?page=wc-settings&tab=checkout&section=vipps'));
          echo "<p>";
          echo sprintf(__("The <a href='%s'>Vipps settings</a> are configured so that no products will have a Buy Now button - including this.", 'woo-vipps'), $settings);
          echo "</p>";
        }
        echo '</div>';
    }
    public function product_options_vipps_shareable_link() {
        global $post;
        $product = wc_get_product($post->ID);
        $variable = ($product->get_type() == 'variable');
        $shareables = get_post_meta($post->ID,'_vipps_shareable_links', false);
        $qradmin = admin_url("/edit.php?post_type=vipps_qr_code");
        ?>
            <div class="options_group">
            <div class='blurb' style='margin-left:13px'>
            <h4><?php echo __("Shareable links", 'woo-vipps') ?></h4>
            <p><?php echo __("Shareable links are links you can share externally on banners or other places that when followed will start Express Checkout of this product immediately. Maintain these links here for this product.", 'woo-vipps'); ?>   </p>
            <p><?php echo sprintf(__("To create a QR code for your shareable link, we recommend copying the URL and then using the <a href='%s'>Vipps QR Api</a>", 'woo-vipps'), $qradmin); ?> </p>
            <input type=hidden id=vipps_sharelink_id value='<?php echo $product->get_id(); ?>'>
            <?php 
            echo wp_nonce_field('share_link_nonce','vipps_share_sec',1,false); 
        if ($variable):
            $variations = $product->get_available_variations(); 
        echo "<button id='vipps-share-link' disabled  class='button' onclick='return false;'>"; echo __("Create shareable link",'woo-vipps'); echo "</button>";
        echo "<select id='vipps_sharelink_variant'><option value=''>"; echo __("Select variant", 'woo-vipps'); echo "</option>";
        foreach($variations as $var) {
            echo "<option value='{$var['variation_id']}'>{$var['variation_id']}"; 
            echo sanitize_text_field($var['sku']);
            echo "</option>";
        }
        echo "</select>";
else:
        echo "<button id='vipps-share-link' class='button'  onclick='return false;'>"; echo __("Create shareable link", 'woo-vipps'); "</button>";
        endif;
        ?>
            </div> <!-- end blurb -->
            <div style="display:none;" id='woo_vipps_shareable_link_template'>
            <a class='shareable' title="<?php echo __('Click to copy', 'woo-vipps'); ?>" href="javascrip:void(0)"></a><input class=deletemarker type=hidden  value=''>
            </div>
            <div style="display:none;" id='woo_vipps_shareable_command_template'>
            <a class="copyaction" href='javascript:void(0)'>[<?php echo __("Copy", 'woo-vipps'); ?>]</a>
            <a class="deleteaction" style="margin-left:13px;" class="deleteaction" href="javascript:void(0)">[<?php echo __('Delete', 'woo-vipps'); ?>]</a>
            </div>
            <style>
            #woo_vipps_shareables a.deleted {
                 text-decoration: line-through;
            }
            </style>
            <div class='blurb' style='margin-left:13px;margin-right:13px'>
            <div id="message-area" style="min-height:2em">
              <div class="vipps-shareable-link-error" style="display:none"><?php echo __('An error occured while creating a shareable link', 'woo-vipps');?>
              <span id="vipps-shareable-link-error"></span>
           </div>
           <div id="vipps-shareable-link-delete-message" style="display:none"><em><?php echo __('Link(s) will be deleted when you save the product', 'woo-vipps');?> </em></div>
           </div>
           <table id='woo_vipps_shareables' class='woo-vipps-link-table' style="width:100% <?php if (empty($shareables)) echo ';display:none;'?>">
           <thead>
           <tr>
           <?php if ($variable): ?><th align=left><?php echo __('Variant','woo-vipps'); ?></th><?php endif; ?>
              <th align=left><?php echo __('Link','woo-vipps'); ?></th>
              <th><?php echo __('Action','woo-vipps'); ?></th></tr>
           </thead>
           <tbody>
           <tr>
           <?php foreach ($shareables as $shareable): ?>
           <?php if ($variable): ?><td><?php echo sanitize_text_field($shareable['variant']); ?></td><?php endif; ?>
           <td><a class='shareable' title="<?php echo __('Click to copy','woo-vipps'); ?>" href="javascrip:void(0)"><?php echo esc_url($shareable['url']); ?></a><input class="deletemarker" type=hidden value='<?php echo sanitize_text_field($shareable['key']); ?>'></td>
           <td align=center>
           <a class="copyaction" title="<?php echo __('Click to copy','woo-vipps'); ?>" href='javascript:void(0)'>[<?php echo __("Copy", 'woo-vipps'); ?>]</a>
           <a class="deleteaction" title="<?php echo __('Mark this link for deletion', 'woo-vipps'); ?>" style="margin-left:13px;" class="deleteaction" href="javascript:void(0)">[<?php echo __('Delete', 'woo-vipps'); ?>]</a>
           </td>
           </tr>
           <?php endforeach; ?>
           </tbody>
           </table>   
           </div> <!-- end blurb -->
           </div> <!-- end options-group -->
    <?php
    }


    // This creates and stores a shareable link that when followed will allow external buyers to buy the specified product direclty.
    // Only products with these links can be bought like this; both to avoid having to create spurious orders from griefers and to ensure
    // that a link can be retracted if it has been printed or shared in emails with a specific price. IOK 2018-10-03
    public function ajax_vipps_create_shareable_link() {
        check_ajax_referer('share_link_nonce','vipps_share_sec');
        if (!current_user_can('manage_woocommerce')) {
            echo json_encode(array('ok'=>0,'msg'=>__('You don\'t have sufficient rights to edit this product', 'woo-vipps')));
            wp_die();
        }
        $prodid = intval($_POST['prodid']);
        $varid = intval($_POST['varid']);

        $product = ''; 
        $variant = '';
        $varname = '';
        try {
            $product = wc_get_product($prodid);
            $variant = $varid ? wc_get_product($varid) : null;
            $varname = $variant ? $variant->get_id() : '';
            if ($variant && $variant->get_sku()) {
                $varname .= ":" . sanitize_text_field($variant->get_sku());
            }
        } catch (Exception $e) {
            echo json_encode(array('ok'=>0,'msg'=>$e->getMessage()));
            wp_die();
        }
        if (!$product) {
            echo json_encode(array('ok'=>0,'msg'=>__('The product doesn\'t exist', 'woo-vipps')));
            wp_die();
        }

        // Find a free shareable link by generating a hash and testing it. Normally there won't be any collisions at all.
        $key = '';
        while (!$key) {
            global $wpdb;
            $key = substr(sha1(mt_rand() . ":" . $prodid . ":" . $varid),0,8);
            $existing =  $wpdb->get_row("SELECT post_id from {$wpdb->prefix}postmeta where meta_key='_vipps_shareable_link_$key' limit 1",'ARRAY_A');
            if (!empty($existing)) $key = '';
        }

        $url = add_query_arg('pr',$key,$this->buy_product_url());
        $payload = array('product_id'=>$prodid,'variation_id'=>$varid,'key'=>$key, 'url'=>$url, 'variant'=>$varname);

        // This is used to find the link itself
        update_post_meta($prodid,'_vipps_shareable_link_'.$key, array('product_id'=>$prodid,'variation_id'=>$varid,'key'=>$key));
        add_post_meta($prodid,'_vipps_shareable_links',$payload);

        echo json_encode(array('ok'=>1,'msg'=>'ok', 'url'=>$url, 'variant'=> $varname, 'key'=>$key));
        wp_die();
    }

    // A metabox for showing Vipps information about the order. IOK 2018-05-07
    public function add_vipps_metabox ($post) {
        $order = wc_get_order($post);
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') return;
        $orderid=$order->get_id();

        $init =  intval($order->get_meta('_vipps_init_timestamp'));
        $callback =  intval($order->get_meta('_vipps_callback_timestamp'));
        $capture =  intval($order->get_meta('_vipps_capture_timestamp'));
        $refund =  intval($order->get_meta('_vipps_refund_timestamp'));
        $cancel =  intval($order->get_meta('_vipps_cancel_timestamp'));

        $status = $order->get_meta('_vipps_status');
        $total = intval($order->get_meta('_vipps_amount'));
        $captured = intval($order->get_meta('_vipps_captured'));
        $refunded = intval($order->get_meta('_vipps_refunded'));

        $capremain = intval($order->get_meta('_vipps_capture_remaining'));
        $refundremain = intval($order->get_meta('_vipps_refund_remaining'));

        $paymentdetailsnonce=wp_create_nonce('paymentdetails');


        print "<table border=0><thead></thead><tbody>";
        print "<tr><td colspan=2>"; print $order->get_payment_method_title();print "</td></tr>";
        print "<tr><td>Status</td>";
        print "<td align=right>" . htmlspecialchars($status);print "</td></tr>";
        print "<tr><td>Amount</td><td align=right>" . sprintf("%0.2f ",$total/100); print "NOK"; print "</td></tr>";
        print "<tr><td>Captured</td><td align=right>" . sprintf("%0.2f ",$captured/100); print "NOK"; print "</td></tr>";
        print "<tr><td>Refunded</td><td align=right>" . sprintf("%0.2f ",$refunded/100); print "NOK"; print "</td></tr>";

        print "<tr><td>Vipps initiated</td><td align=right>";if ($init) print date('Y-m-d H:i:s',$init); print "</td></tr>";
        print "<tr><td>Vipps response </td><td align=right>";if ($callback) print date('Y-m-d H:i:s',$callback); print "</td></tr>";
        print "<tr><td>Vipps capture </td><td align=right>";if ($capture) print date('Y-m-d H:i:s',$capture); print "</td></tr>";
        print "<tr><td>Vipps refund</td><td align=right>";if ($refund) print date('Y-m-d H:i:s',$refund); print "</td></tr>";
        print "<tr><td>Vipps cancelled</td><td align=right>";if ($cancel) print date('Y-m-d H:i:s',$cancel); print "</td></tr>";
        print "</tbody></table>";
        print "<a href='javascript:VippsGetPaymentDetails($orderid,\"$paymentdetailsnonce\");' class='button'>" . __('Show complete transaction details','woo-vipps') . "</a>";
    }

    // This is for debugging and ensuring we have excact details correct for a transaction.
    public function ajax_vipps_payment_details() {
        check_ajax_referer('paymentdetails','vipps_paymentdetails_sec');
        $orderid = intval($_REQUEST['orderid']);
        $gw = $this->gateway();
        $order = wc_get_order($orderid);
        if (!$order) {
            print "<p>" . __("Unknown order", 'woo-vipps') . "</p>";
            exit();
        }
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            print "<p>" . __("The order is not a Vipps order", 'woo-vipps') . "</p>";
            exit();
        }

        $gw = $this->gateway();
        try {
            $details = $gw->get_payment_details($order);
            if ($details && $order->get_meta('_vipps_api') == 'epayment') {
               try {
                   $details['epaymentLog'] =  $gw->api->epayment_get_payment_log ($order);
               } catch (Exception $e) {
                   $this->log("Could not get transaction log for " . $order->get_id() . " : " . $e->getMessage(), 'error');
               }
            }
            $order =   $gw->update_vipps_payment_details($order, $details);
        } catch (Exception $e) {
            print "<p>"; 
            print __('Transaction details not retrievable: ','woo-vipps') . $e->getMessage();
            print "</p>";
            exit();
        }

       // This is for testing; but maybe add a button for it (interacts with the Order Management API.)
       // IOK 2022-07-01
       // $gw->order_payment_complete($orderid);
        

        print "<h2>" . __('Transaction details','woo-vipps') . "</h2>";
        print "<p>";
        print __('Order id', 'woo-vipps') . ": " . @$details['orderId'] . "<br>";
        print __('Order status', 'woo-vipps') . ": " .@$details['status'] . "<br>";
        if (isset($details['paymentMethod'])) {
            print __("Payment method", 'woo-vipps') . ":" . $details['paymentMethod'] . "<br>";
        } else {
            print __("Payment method", 'woo-vipps') . ": Vipps <br>";
        }
        print __("API", 'woo-vipps') .": " . esc_html($order->get_meta('_vipps_api')) . "</br>";
        print  __('All values in ører (1/100 NOK)', 'woo-vipps') . "<br>";
        if (!empty(@$details['transactionSummary'])) {
            $ts = $details['transactionSummary'];
            print "<h3>" . __('Transaction summary', 'woo-vipps') . "</h3>";
            print __('Capured amount', 'woo-vipps') . ":" . @$ts['capturedAmount'] . "<br>";
            print __('Remaining amount to capture', 'woo-vipps') . ":" . @$ts['remainingAmountToCapture'] . "<br>";
            print __('Refunded amount', 'woo-vipps') . ":" . @$ts['refundedAmount'] . "<br>";
            print __('Remaining amount to refund', 'woo-vipps') . ":" . @$ts['remainingAmountToRefund'] . "<br>";
            if (isset($ts['cancelledAmount'])) {
                print __('Cancelled amount', 'woo-vipps') . ":" . @$ts['cancelledAmount'] . "<br>";
                print __('Remaining amount to cancel', 'woo-vipps') . ":" . @$ts['remainingAmountToCancel'] . "<br>";
            }
        }
        if (!empty(@$details['shippingDetails'])) {
            $ss = $details['shippingDetails'];
            print "<h3>" . __('Shipping details', 'woo-vipps') . "</h3>";
            print __('Address', 'woo-vipps') . ": " . htmlspecialchars(join(', ', array_values(@$ss['address']))) . "<br>";
            if (@$ss['shippingMethod']) print __('Shipping method', 'woo-vipps') . ": " . htmlspecialchars(@$ss['shippingMethod']) . "<br>"; 
            if (@$ss['shippingCost']) print __('Shipping cost', 'woo-vipps') . ": " . @$ss['shippingCost'] . "<br>";
            print __('Shipping method ID', 'woo-vipps') . ": " . htmlspecialchars(@$ss['shippingMethodId']) . "<br>";
        }
        if (!empty(@$details['userDetails'])) {
            $us = $details['userDetails'];
            print "<h3>" . __('User details', 'woo-vipps') . "</h3>";
            print __('User ID', 'woo-vipps') . ": " . htmlspecialchars(@$us['userId']) . "<br>";
            print __('First Name', 'woo-vipps') . ": " . htmlspecialchars(@$us['firstName']) . "<br>"; 
            print __('Last Name', 'woo-vipps') . ": " . htmlspecialchars(@$us['lastName']) . "<br>";
            print __('Mobile Number', 'woo-vipps') . ": " . htmlspecialchars(@$us['mobileNumber']) . "<br>";
            print __('Email', 'woo-vipps') . ": " . htmlspecialchars(@$us['email']) . "<br>";
        }
        if (!empty(@$details['transactionLogHistory'])) {
            print "<h3>" . __('Transaction Log', 'woo-vipps') . "</h3>";
            $i = count($details['transactionLogHistory'])+1; 
            foreach ($details['transactionLogHistory'] as $td) {
                print "<br>";
                print __('Operation','woo-vipps') . ": " . htmlspecialchars(@$td['operation']) . "<br>";
                print __('Amount','woo-vipps') . ": " . htmlspecialchars(@$td['amount']) . "<br>";
                print __('Success','woo-vipps') . ": " . @$td['operationSuccess'] . "<br>";
                print __('Timestamp','woo-vipps') . ": " . htmlspecialchars(@$td['timeStamp']) . "<br>";
                print __('Transaction text','woo-vipps') . ": " . htmlspecialchars(@$td['transactionText']) . "<br>";
                print __('Transaction ID','woo-vipps') . ": " . htmlspecialchars(@$td['transactionId']) . "<br>";
                print __('Request ID','woo-vipps') . ": " . htmlspecialchars(@$td['requestId']) . "<br>";
            }
        }
        if (!empty(@$details['epaymentLog']) && is_array($details['epaymentLog'])) {
            print "<h3>" . __('Transaction Log', 'woo-vipps') . "</h3>";
            $i = count($details['epaymentLog'])+1; 
            $reversed = array_reverse($details['epaymentLog']);
            foreach ($reversed  as $td) {
                print "<br>";
                print __('Operation','woo-vipps') . ": " . htmlspecialchars(@$td['paymentAction']) . "<br>";
                $value = intval(@$td['amount']['value'])/100;
                $curr = $td['amount']['currency'];

                print __('Amount','woo-vipps') . ": " . esc_html($value) . " " . esc_html($curr) . "<br>";
                print __('Success','woo-vipps') . ": " . @$td['success'] . "<br>";
                print __('Timestamp','woo-vipps') . ": " . htmlspecialchars(@$td['processedAt']) . "<br>";
                print __('Transaction ID','woo-vipps') . ": " . htmlspecialchars(@$td['pspReference']) . "<br>";
            }
        }
        exit();
    }

    // This is used by the Vipps Checkout page to start the Vipps checkout session, including 
    // creating the partial order IOK 2021-09-03
    public function vipps_ajax_checkout_start_session () {
        check_ajax_referer('do_vipps_checkout','vipps_checkout_sec');
        $url = ""; 
        $redir = "";
        // First, check that we haven't already done this like in another window or something:
        $session = $this->vipps_checkout_current_pending_session();
        if (isset($sessioninfo['redirect'])) {
            $redirect = $sessioninfo['redirect'];
        }
        if (isset($sessioninfo['session']) && isset($sessioninfo['session']['token'])) {
            $token = $sessioninfo['session']['token'];
            $src = $sessioninfo['session']['checkoutFrontendUrl'];
            $url = $src; 
        }
        $current_vipps_session = null;
        $current_pending = 0;
        $current_authtoken = "";
        if (!$redir && !$url) {
            try {
                WC()->session->set('current_vipps_session', null);
                $current_pending = $this->gateway()->create_partial_order('ischeckout');
                if ($current_pending) {
                    $order = wc_get_order($current_pending);
                    $order->update_meta_data('_vipps_checkout', true);
                    $current_authtoken = $this->gateway()->generate_authtoken();
                    WC()->session->set('vipps_checkout_authtoken', $current_authtoken);
                    $order->update_meta_data('_vipps_authtoken',wp_hash_password($current_authtoken));
                    $order->save();
                    WC()->session->set('vipps_checkout_current_pending', $current_pending);



                    try {
                        $this->maybe_add_static_shipping($this->gateway(),$order->get_id(), 'vippscheckout');
                    } catch (Exception $e) {
                        // In this case, we just have to continue.
                        $this->log(sprintf(__("Error calculating static shipping for order %s", 'woo-vipps'), $order->get_id()), 'error');
                        $this->log($e->getMessage(),'error');
                    }
                    $this->gateway()->save_session_in_order($order);
                    do_action('woo_vipps_checkout_order_created', $order);
                } else {
                    throw new Exception(__('Unknown error creating Vipps Checkout partial order', 'woo-vipps'));
                }
            } catch (Exception $e) {
                return wp_send_json_success(array('ok'=>0, 'msg'=>$e->getMessage(), 'src'=>'', 'redirect'=>''));
            }
        }
        // Ensure we get the latest updates to the order too IOK 2021-10-22
        $order = wc_get_order($current_pending);
        if (is_user_logged_in()) {
            $phone = get_user_meta(get_current_user_id(), 'billing_phone', true);
        }
        $requestid = 1;
        $returnurl = $this->payment_return_url();
        $returnurl = add_query_arg('t',$current_authtoken,$returnurl);

        $order_id = $order->get_id();
        $sessionorders= WC()->session->get('_vipps_session_orders');
        if (!$sessionorders) $sessionorders = array();
        $sessionorders[$order_id] = 1;
        WC()->session->set('_vipps_pending_order',$order_id);
        WC()->session->set('_vipps_session_orders',$sessionorders);
        // For mobiles where we may return from a different browser than we started from.
        set_transient('_vipps_pending_order_'.$current_authtoken, $order_id,20*MINUTE_IN_SECONDS);

        $customer_id = get_current_user_id();
        if ($customer_id) {
           $customer = new WC_Customer( $customer_id );
        } else {
            $customer = WC()->customer;
        }

        if ($customer) {
            $customerinfo['email'] = $customer->get_billing_email();
            $customerinfo['firstName'] = $customer->get_billing_first_name();
            $customerinfo['lastName'] = $customer->get_billing_last_name();
            $customerinfo['streetAddress'] = $customer->get_billing_address_1();
            $address2 =  trim($customer->get_billing_address_2());
            if (!empty($address2)) {
                $customerinfo['streetAddress'] = $customerinfo['streetAddress'] . ", " . $address2;
            }
            $customerinfo['city'] = $customer->get_billing_city();
            $customerinfo['postalCode'] = $customer->get_billing_postcode();
            $customerinfo['country'] = $customer->get_billing_country();

            // Currently Vipps requires all phone numbers to have area codes and NO  +. We can't guaratee that at all, but try for Norway
            $phone = ""; 
            $phonenr = $customer->get_billing_phone();
            $phonenr = preg_replace("![^0-9]!", "",  $phonenr);
            $phonenr = preg_replace("!^0+!", "", $phonenr);
            if (strlen($phonenr) == 8 && $customerinfo['country'] == 'NO') {
                $phonenr = '47' + $phonenr;
            }
            if (preg_match("/47\d{8}/", $phonenr) && $customerinfo['country'] == 'NO') {
              $phone = $phonenr;
            }

            $customerinfo['phoneNumber'] = $phone;
        }

        $keys = ['firstName', 'lastName', 'streetAddress', 'postalCode', 'country', 'phoneNumber'];
        foreach($keys as $k) {
            if (empty($customerinfo[$k])) {
                $customerinfo = array(); break;
            }
        }
        $customerinfo = apply_filters('woo_vipps_customerinfo', $customerinfo, $order);

        try {
            $current_vipps_session = $this->gateway()->api->initiate_checkout($customerinfo,$order,$returnurl,$current_authtoken,$requestid); 
            if ($current_vipps_session) {
                $order = wc_get_order($current_pending);
                $order->update_meta_data('_vipps_init_timestamp',time());
                $order->update_meta_data('_vipps_status','INITIATE');
                $order->update_meta_data('_vipps_checkout_poll', $current_vipps_session['pollingUrl']);
                $order->add_order_note(__('Vipps Checkout payment initiated','woo-vipps'));
                $order->add_order_note(__('Customer passed to Vipps Checkout','woo-vipps'));
                $order->save();
                WC()->session->set('current_vipps_session', $current_vipps_session);
                $token = $current_vipps_session['token'];
                $src = $current_vipps_session['checkoutFrontendUrl'];
                $url = $src;
            } else {
                    throw new Exception(__('Unknown error creating Vipps Checkout session', 'woo-vipps'));
            }
        } catch (Exception $e) {
                $this->log(sprintf(__("Could not initiate Vipps Checkout session: %s", 'woo-vipps'), $e->getMessage()), 'ERROR');
                return wp_send_json_success(array('ok'=>0, 'msg'=>$e->getMessage(), 'src'=>'', 'redirect'=>''));
        }
        if ($url || $redir) {
            return wp_send_json_success(array('ok'=>1, 'msg'=>'session started', 'src'=>$url, 'redirect'=>$redir, 'token'=>$token));
        } else { 
            return wp_send_json_success(array('ok'=>0, 'msg'=>__('Could not start Vipps Checkout session'),'src'=>$url, 'redirect'=>$redir));
        }
    }

    // Check the current status of the current Checkout session for the user.
    public function vipps_ajax_checkout_poll_session () {
        check_ajax_referer('do_vipps_checkout','vipps_checkout_sec');
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
        $order = $current_pending ? wc_get_order($current_pending) : null;
        $payment_status = $order ?  $this->gateway()->check_payment_status($order) : 'unknown';
        if (in_array($payment_status, ['authorized', 'complete'])) {
            $this->abandonVippsCheckoutOrder(false);
            return wp_send_json_success(array('msg'=>'completed', 'url' => $this->gateway()->get_return_url($order)));;
        }
        if ($payment_status == 'cancelled') {
            $this->abandonVippsCheckoutOrder($order);
            return wp_send_json_error(array('msg'=>'FAILED', 'url'=>home_url()));
        }

        $current_vipps_session = $order ? WC()->session->get('current_vipps_session') : false;
        if (!$current_vipps_session) {
            WC()->session->set('current_vipps_session', false);
            WC()->session->set('vipps_address_hash', false);
            return wp_send_json_success(array('msg'=>'EXPIRED', 'url'=>false));
        }

        $status = $this->get_vipps_checkout_status($current_vipps_session); 

        $failed = $status == 'ERROR' || $status == 'EXPIRED' || $status == 'TERMINATED';
        $complete = false; // We no longer get informed about complete sessions; we only get this info when the order is wholly complete. IOK 2021-09-27

        // Disallow sessions that go on for too long.
        if (is_a($order, "WC_Order")) {
            $created = $order->get_date_created();
            $timestamp = 0;
            $now = time();
            try {
                $timestamp = $created->getTimestamp();
            } catch (Exception $e) {
                // PHP 8 gives ValueError for this, we'll use 0
            }
            $passed = $now - $timestamp;
            $minutes = ($passed / 60);
            // Expire after 50 minutes
            if ($minutes > 50) {
                $this->abandonVippsCheckoutOrder($order);
                return wp_send_json_success(array('msg'=>'EXPIRED', 'url'=>false));
            }
        }

        $ok   = !$failed;

        // Since we checked the payment status at Vipps directly above, we don't actaully have any extra information at this point.
        // We do know that the session is live and ongoing, but that's it.

        if ($failed) { 
            $msg = $status;
            $this->abandonVippsCheckoutOrder($order);
            return wp_send_json_error(array('msg'=>$msg, 'url'=>home_url()));
            exit();
        }
        // Errorhandling! If this happens we have an unknown status or something like it.
        if (!$ok) {
            $this->log("Unknown status on polling status: " . print_r($status, true), 'ERROR');
            $this->abandonVippsCheckoutOrder($order);
            return wp_send_json_error(array('msg'=>'ERROR', 'url'=>false));
            exit();
        }

        // This handles address information data from the poll if present. It is not, currently.  2021-09-27 IOK
        $change = false;
        $vipps_address_hash =  WC()->session->get('vipps_address_hash');
        if ($ok && (isset($status['orderContactInformation']) || isset($status['orderShippingAddress'])))  {
            $serialized = sha1(json_encode(@$status['orderContactInformation']) . ':' . json_encode(@$status['orderShippingAddress']));
            if ($serialized != $vipps_address_hash) {
                $change = true;
                WC()->session->set('vipps_address_hash', $serialized);
            } 
        }
        if ($complete) $change = true;

        if ($ok && $change && isset($status['userDetails']))  {
            $contact = $status['userDetails'];
            $order->set_billing_email($contact['email']);
            $order->set_billing_phone($contact['phoneNumber']);
            $order->set_billing_first_name($contact['firstName']);
            $order->set_billing_last_name($contact['lastName']);
        }
        if ($ok &&  $change && isset($status['shippingDetails']))  {
            $contact = $status['shippingDetails'];
            $countrycode =  $this->country_to_code($contact['country']);
            $order->set_shipping_first_name($contact['firstName']);
            $order->set_shipping_last_name($contact['lastName']);
            $order->set_shipping_address_1($contact['streetAddress']);
            $order->set_shipping_address_2("");
            $order->set_shipping_city($contact['region']);
            $order->set_shipping_postcode($contact['postalCode']);
            $order->set_shipping_country($countrycode);

            $order->set_billing_address_1($contact['streetAddress']);
            $order->set_billing_address_2("");
            $order->set_billing_city($contact['region']);
            $order->set_billing_postcode($contact['postalCode']);
            $order->set_billing_country($countrycode);
        }
        if ($change) $order->save();

        // IOK 2021-09-27 we no longer get informed when the order is complete, but if we did, this would handle it.
        if ($complete) {
            // Order is complete! Yay!
            $this->gateway()->update_vipps_payment_details($order);
            $this->gateway()->payment_complete($order);
            wp_send_json_success(array('msg'=>'completed','url' => $this->gateway()->get_return_url($order)));
            exit();
        }

        if ($ok && $change) {
            wp_send_json_success(array('msg'=>'order_change', 'url'=>''));
            exit();
        }
        if ($ok) {
            wp_send_json_success(array('msg'=>'no_change', 'url'=>''));
            exit();
        }

        // This should never happen.
        wp_send_json_success(array('msg'=>'unknown', 'url'=>''));
    }

    // Retrieve the current pending Vipps Checkout session, if it exists, and do some cleanup
    // if it isn't correct IOK 2021-09-03
    protected function vipps_checkout_current_pending_session () {
        // If this is set, this is a currently pending order which is maybe still valid
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
        $order = $current_pending ? wc_get_order($current_pending) : null;

        # If we do have an order, we need to check if it is 'pending', and if not, we have to check its payment status
        $payment_status = null;
        if ($order) {
            if ($order->get_status() == 'pending') {
                $payment_status = 'initiated'; // Just assume this for now
            } else {
                $payment_status = $order ?  $this->gateway()->check_payment_status($order) : 'unknown';
            } 
        }
        // This covers situations where we can actually go directly to the thankyou-screen or whatever
        $redirect = "";
        if (in_array($payment_status, ['authorized', 'complete'])) {
            $this->abandonVippsCheckoutOrder(false);
            $redirect = $this->gateway()->get_return_url($order);
        } elseif ($payment_status == 'cancelled') {
            // This will mostly just wipe the session.
            $this->abandonVippsCheckoutOrder($order);
            $redirect = home_url();
        }
        // Now if we don't have an order right now, we should not have a session either, so fix that
        if (!$order) {
            $this->abandonVippsCheckoutOrder(false);
        } 

        // Now check the current vipps session if it exist
        $current_vipps_session = $order ? WC()->session->get('current_vipps_session') : false;
        // A single word or array containing session data, containing token and frontendFrameUrl
        // ERROR EXPIRED FAILED
        $session_status = $current_vipps_session ? $this->get_vipps_checkout_status($current_vipps_session) : null;

        // If this is the case, there is no redirect, but the session is gone, so wipe the order and session.
        if (in_array($session_status, ['ERROR', 'EXPIRED', 'FAILED'])) {
            $this->abandonVippsCheckoutOrder($order);
        }

        // This will return either a valid vipps session, nothing, or  redirect. 
        return(array('order'=>$order ? $order->get_id() : false, 'session'=>$current_vipps_session,  'redirect'=>$redirect));
    }

    function vipps_checkout_shortcode ($atts, $content) {
        // No point in expanding this unless we are actually doing the checkout. IOK 2021-09-03
        if (is_admin()) return;
        if (wp_doing_ajax()) return;
        if (defined('REST_REQUEST') && REST_REQUEST ) return;
        wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

        if (!WC()->cart ||  WC()->cart->is_empty() ) {
            $this->abandonVippsCheckoutOrder(false);
            ob_start();
            wc_get_template( 'cart/cart-empty.php' );
            return ob_get_clean();
        }
        // Previously registered, now enqueue this script which should then appear in the footer.
        wp_enqueue_script('vipps-checkout');

        do_action('vipps_checkout_before_get_session');

        // We need to be able to check if we still have a live, good session, in which case
        // we can open the iframe directly. Otherwise, the form we are going to output will 
        // create the iframe after a button press which will create a new order.
        $sessioninfo = $this->vipps_checkout_current_pending_session();
        $out = ""; // Start generating output already to make debugging easier

        if ($sessioninfo['redirect']) {
           // This is always either the thankyou page or home_url()  IOK 2021-09-03
           $redir = json_encode($sessioninfo['redirect']);
           $out .= "<script>window.location.replace($redir);</script>";
           return $out;
        }

        // Now the normal case.
        $errortext = apply_filters('woo_vipps_checkout_error', __('An error has occured - please reload the page to restart your transaction, or return to the shop', 'woo-vipps'));
        $expiretext = apply_filters('woo_vipps_checkout_error', __('Your session has expired - please reload the page to restart, or return to the shop', 'woo-vipps')); 

        $out .= $this->spinner();

        if (!$sessioninfo['session']) {
           $out .= "<div style='visibility:hidden' class='vipps_checkout_startdiv'>";
           $out .= "<h2>" . __('Press the button to complete your order with Vipps!', 'woo-vipps') . "</h2>";
           $out .= '<div class="vipps_checkout_button_wrapper" ><button type="submit" class="button vipps_checkout_button vippsorange" value="1">' . __('Vipps Checkout', 'woo-vipps') . '</button></div>';
           $out .= "</div>";
        }

        // If we have an actual live session right now, add it to the page on load. Otherwise, the session will be started using ajax after the page loads (and is visible)
        if ($sessioninfo['session']) {
            $token = $sessioninfo['session']['token'];      // From Vipps
            $src = $sessioninfo['session']['checkoutFrontendUrl'];  // From Vipps
            $out .= "<script>VippsSessionState = " . json_encode(array('token'=>$token, 'checkoutFrontendUrl'=>$src)) . ";</script>\n";
        } else {
            $out .= "<script>VippsSessionState = null;</script>\n";
        }

        // Check that these exist etc
        $out .= "<div id='vippscheckoutframe'>";

        $out .= "</div>";
        $out .= wp_nonce_field('do_vipps_checkout','vipps_checkout_sec',1,false); 
        $out .= "<div style='display:none' id='vippscheckouterror'><p>$errortext</p></div>";
        $out .= "<div style='display:none' id='vippscheckoutexpired'><p>$expiretext</p></div>";

        return $out;
    }


    public function cart_changed() {
        $current_pending = is_a(WC()->session, 'WC_Session') ? WC()->session->get('vipps_checkout_current_pending') : false;
        $order = $current_pending ? wc_get_order($current_pending) : null;
        if (!$order) return;
        $this->abandonVippsCheckoutOrder($order);
    } 
    
    public function abandonVippsCheckoutOrder($order) {
        if (WC()->session) {
            WC()->session->set('vipps_checkout_current_pending',0);
            WC()->session->set('current_vipps_session', null);
            WC()->session->set('vipps_address_hash', false);
        }

        if (is_a($order, 'WC_Order') && $order->get_status() == 'pending') {
            // NB: This can *potentially* be revived by a callback!
            $order->set_status('cancelled', __("Abandonded by customer", 'woo-vipps'), false);
            // Also mark for deletion
            $order->update_meta_data('_vipps_delendum',1);
            $order->save();
        }
    }
    
    public function get_vipps_checkout_status($session) {
        if ($session && isset($session['token'])) {
            $status = $this->gateway()->api->poll_checkout($session['pollingUrl']);
            return $status;
        }
    }
    

    // This function will create a file with an obscure filename in the $callbackDirname directory.
    // When initiating payment, this file will be created with a zero value. When the response is reday,
    // it will be rewritten with the value 1.
    // This function can fail if we can't write to the directory in question, in which case, return null and
    // to the check with admin-ajax instead. IOK 2018-05-04
    public function createCallbackSignal($order,$ok=0) {
        $fname = $this->callbackSignal($order);
        if (!$fname) return null;
        if ($ok) {
            @file_put_contents($fname,"1");
        }else {
            @file_put_contents($fname,"0");
        }
        if (is_file($fname)) return $fname;
        return null;
    }

    //Helper function that produces the signal file name for an order IOK 2018-05-04
    public function callbackSignal($order) {
        $dir = $this->callbackDir();
        if (!$dir) return null;
        $fname = 'vipps-'.md5($order->get_order_key() . $order->get_meta('_vipps_transaction'));
        return $dir . DIRECTORY_SEPARATOR . $fname;
    }
    // URL of the above product thing
    public function callbackSignalURL($signal) {
        if (!$signal) return "";
        $uploaddir = wp_upload_dir();
        return $uploaddir['baseurl'] . '/' . $this->callbackDirname . '/' . basename($signal);
    }

    // Clean up old signal files. If there gets to be a lot of them, this may take some time. IOK 2018-05-04.
    public function cleanupCallbackSignals() {
        $dir = $this->callbackDir();
        if (!is_dir($dir)) return;
        $signals = scandir($dir);
        $now = time();
        foreach($signals as $signal) {
            $path = $dir .  DIRECTORY_SEPARATOR . $signal;
            if (is_dir($path)) continue;
            if (is_file($path)) {
                $age = @filemtime($path);
                $halfhour = 30*60;
                if (($age+$halfhour) < $now) {
                    @unlink($path);
                }
            }
        }
    }

    // Returns the name of the callback-directory, or null if it doesn't exist. IOK 2018-05-04
    private function callbackDir() {
        $uploaddir = wp_upload_dir();
        $base = $uploaddir['basedir'];
        $callbackdir = $base . DIRECTORY_SEPARATOR . $this->callbackDirname;
        if (is_dir($callbackdir)) return $callbackdir;
        $ok = mkdir($callbackdir, 0755);
        if ($ok) return $callbackdir; 
        return null;
    }

    // Unfortunately, we cannot do any form of portable locking, and we may get callbacks from Vipps arriving at the same moment as we check the status at Vipps,
    // which in the very worst case, for Express Checkout orders, may lead to a double shipping line. Changing this to a queue system is non-trivial, because some of
    // the operations done when modifying the order actually requires the customers session to be active. This operation will make conflicts a litte less probable
    // by implementing something that isn't quite a lock, and the filter may be used to implement proper locking, using e.g. flock, where this can be used 
    // (non-distributed environments using unix on standard filesystems. IOK 2020-05-15
    // Returns true if lock succeeds, or false.
    public function lockOrder($order) {
        $orderid = $order->get_id();
        if (has_filter('woo_vipps_lock_order')) {
            $ok = apply_filters('woo_vipps_lock_order', $order);
            if (!$ok) return false;
        } else {
            if(get_transient('order_lock_'.$orderid)) return false;
            $this->lockKey = uniqid();
            set_transient('order_lock_' . $orderid, $this->lockKey, 30);
        }
        add_action('shutdown', function () use ($order) { global $Vipps; $Vipps->unlockOrder($order); });
        return true;
    }
    public function unlockOrder($order) {
        $orderid = $order->get_id();
        if (has_action('woo_vipps_unlock_order')) {
            do_action('woo_vipps_unlock_order', $order); 
        } else {
            if(get_transient('order_lock_'.$orderid) == $this->lockKey) {
                delete_transient('order_lock_'.$orderid);
            }
        }
    }

    // Functions using flock() and files to lock orders. This is only guaranteed to work on certain setups, ie, non-distributed setups
    // using Unix with normal filesystems (not NFS).
    public function flock_lock_order($order) {
       global $_orderlocks;
       if (!$_orderlocks) $_orderlocks = array();
       $dir = $this->callbackDir();
       if (!$dir) { 
         $this->log(__("Cannot use flock() to lock orders: cannot create or write to directory", "woo-vipps"), 'error');
         return true;
       }
       $fname = '.ht-vipps-lock-'.md5($order->get_order_key() . $order->get_meta('_vipps_transaction'));
       $path = $dir .  DIRECTORY_SEPARATOR . $fname;
       touch($path);
       if (!is_writable($path)) {
         $this->log(__("Cannot use flock() to lock orders: cannot create lockfiles ", "woo-vipps"), 'error');
         return true;
       }
       $handle = fopen($path, 'w+');
       if (flock($handle, LOCK_EX | LOCK_NB)) {
          $_orderlocks[$order->get_id()] = array($handle,$path);
          return true;
       }
       return false;
    }
    public function flock_unlock_order($order) {
       $orderid=$order->get_id();
       global $_orderlocks;
       if (!$_orderlocks) return;
       if (!isset($_orderlocks[$orderid])) return;
       list($handle, $path) = $_orderlocks[$orderid];
       unset($_orderlocks[$orderid]);
       flock($handle, LOCK_UN);
       fclose($handle);
       @unlink($path);
    }
   

    // Because the prefix used to create the Vipps order id is editable
    // by the user, we will store that as a meta and use this for callbacks etc.
    public function getOrderIdByVippsOrderId($vippsorderid) {
        global $wpdb;
        // IOK 2022-10-04 STOP USING POSTMETA FOR ORDERS
        return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_vipps_orderid' AND meta_value = %s", $vippsorderid) );
    }

    // Special pages, and some callbacks. IOK 2018-05-18 
    public function template_redirect() {
        global $post;
        // Handle special callbacks
        $special = $this->is_special_page() ;

        // This will normally be on the "checkout" page which shouldn't be cached, but just in case, add
        // nocache headres to any page that uses this shortcode. IOK 2021-08-26
        if (is_page() &&  has_shortcode($post->post_content, 'vipps_checkout')) { 
            wc_nocache_headers();
        }

        if ($special) {
            remove_filter('template_redirect', 'redirect_canonical', 10);
            do_action('woo_vipps_before_handling_special_page', $special);

            // Allow above hook to actually handle special pages. It should probably call $Vipps->fakepage or a redirect; can be used
            // to intercept express checkout etc. IOK 2022-03-18
            if (! apply_filters('woo_vipps_special_page_handled', false, $special)) {
                $this->$special();
            }
        }

        $consentremoval = $this->is_consent_removal();
        if ($consentremoval) {
            remove_filter('template_redirect', 'redirect_canonical', 10);
            do_action('woo_vipps_before_handling_special_page', 'consentremoval');
            if (! apply_filters('woo_vipps_special_page_handled', false, 'consentremoval')) {
                $this->vipps_consent_removal_callback($consentremoval);
            }
        }

    }
    // Template handling for special pages. IOK 2018-11-21
    public function template_include($template) {
        $special = $this->is_special_page() ;
        if ($special) {
            // Get any special template override from the options IOK 2020-02-18
            $specific = $this->gateway()->get_option('vippsspecialpagetemplate');
            $found = locate_template($specific,false,false);
            if ($found) $template=$found;

            return apply_filters('woo_vipps_special_page_template', $template, $special);
        }
        return $template;
    }


    // Can't use wc-api for this, as that does not support DELETE . IOK 2018-05-18
    private function is_consent_removal () {
        
        if ($_SERVER['REQUEST_METHOD'] != 'DELETE') return false;
        if ( !get_option('permalink_structure')) {
            if (@$_REQUEST['vipps-consent-removal']) return @$_REQUEST['callback'];
            return false;
        }
        if (preg_match("!/vipps-consent-removal/([^/]*)!", $_SERVER['REQUEST_URI'], $matches)) {
            return @$_REQUEST['callback'];
        }
        return false;
    }

    // On the thank you page, we have a completed order, so we need to restore any saved cart and possibly log in 
    // the user if using Express Checkout IOK 2020-10-09
    public function woocommerce_thankyou ($orderid) {
        $this->maybe_restore_cart($orderid);
        $order = wc_get_order($orderid);
        if ($order) {
            $sessionkey = WC()->session->get('_vipps_order_finalized');
            $orderkey = $order->get_order_key();

            if ($orderkey == $sessionkey) {
                // If this is the case, this order belongs to this session and we can proceed to do 'sensitive' things. IOK 2020-10-09
                // Given the settings, maybe log in the user on express checkout. If the below function exists however, don't: That means that
                // NHGs code for this runs and we should not interfere with that. IOK 2020-10-09
                // Actual logging in is governed by a filter in "maybe_log_in" too.
                if (!function_exists('create_assign_user_on_vipps_callback')) {
                    $this->maybe_log_in_user($order); // Requires that this is express checkout and that 'create users on express checkout' is chosen. IOK 2020-10-09
                }
            }
        }
    }

    public function woocommerce_loaded () {


        /* IOK 2020-09-03 experimental support for the All Products type product block */
        // This is for product blocks - augment the description when using the StoreAPI so that we know that a button should be added
        add_filter('woocommerce_product_get_description', function ($description, $product) {
                   // This is basically the store_api init, but as that calls no action, we need to replicate the logic of its protected function
                   // here for the time being. IOK 2020-09-02
                   if (empty($_SERVER['REQUEST_URI'])) return $description;
                   if (!did_action('rest_api_init')) return $description;
                   $request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
                   $storeapi = "/wc/store/";
                   if (false === strpos($request_uri, $storeapi)) return $description;;

                   // Now add a small tag to product descriptions if this product should be purchasable.
                   if (!$this->loop_single_product_is_express_checkout_purchasable($product)) return $description;
                   return $description . "<span class='_product_metadata _vipps_metadata _prod_{$product->get_id()}' data-vipps-purchasable='1'></span>";
                   },10,2);

        add_action( 'enqueue_block_editor_assets', function () {
                wp_enqueue_script( 'create-block-vipps-products-block-extension', plugins_url( 'Blocks/Products/js/index.js', __FILE__), array( 'wc-blocks-registry','wp-i18n','wp-element','vipps-admin' ), filemtime(dirname(__FILE__) . "/Blocks/Products/js/index.js"), true );
                wp_enqueue_script( 'create-block-vipps-products-block-editor', plugins_url( 'Blocks/Products/js/editor.js', __FILE__ ), array( 'wc-blocks','wp-i18n','wp-element','vipps-admin'),  filemtime(dirname(__FILE__) . "/Blocks/Products/js/editor.js"), true );
        });



        // Conditionally add the javascript for All Products Blocks so that they are only loaded when the block is used on a page.
        // Overridable by filter if you are adding the block in some other way (for now). In the future, this may be a backend setting and eventually
        // become the default, depending on how this goes. IOK 2020-11-16
        add_action( 'wp_enqueue_scripts', function () {
                $support_all_products_block = function_exists('has_block') && has_block('woocommerce/all-products');
                $support_all_products_block = apply_filters('woo_vipps_support_all_products_block', $support_all_products_block);
                if ($support_all_products_block) {
                    wp_enqueue_script( 'create-block-vipps-products-block-extension', plugins_url( 'Blocks/Products/js/index.js', __FILE__ ), array( 'wc-blocks-registry','wp-i18n','wp-element','vipps-gw' ), filemtime(dirname(__FILE__) . "/Blocks/Products/js/index.js"), true );
                }
       });
        

        /* End 'all products' blocks support */
        /* This is for the other product blocks - here we only have a single HTML filter unfortunately */
        add_filter('woocommerce_blocks_product_grid_item_html', function ($html, $data, $product) {
           if (!$this->loop_single_product_is_express_checkout_purchasable($product)) return $html; 
           $stripped = preg_replace("!</li>$!", "", $html);
           $pid = $product->get_id();
           $title = __('Buy now with Vipps', 'woo-vipps');
           $text = __('Buy now with', 'woo-vipps');
           $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
           $a=1;
           $button = <<<EOF
<div class="wp-block-button  wc-block-components-product-button wc-block-button-vipps"><a javascript="void(0)" data-product_id="$pid" class="single-product button vipps-buy-now wp-block-button__link" title="$title"><span class="vippsbuynow">$text</span><img class="inline vipps-logo negative" src="$logo" alt="Vipps" border="0"></a></div>
EOF;
           return $stripped . $button . "</li>";
        }, 10, 3);



        # This implements the Vipps Checkout replacement checkout page for those that wants to use that, by filtering the checkout page id.
        add_filter('woocommerce_get_checkout_page_id',  function ($id) {
                # Only do this if Vipps Checkout was ever activated
                $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);
                if (!$vipps_checkout_activated) return $id;

                # We sometimes want to use the 'real' checkout screen, ie, like for "thankyou"
                # IOK TODO: Instead, we probably should implement the endpoints for thankyou and so forth directly, just in case someone
                # deletes the standard page or somethng.
                if ($this->gateway()->get_real_checkout_screen) return $id;

                # If Vipps Checkout is enabled, can be used etc, use that.
                $checkoutid = $this->gateway()->vipps_checkout_available();
                if ($checkoutid) {
                    return $checkoutid;
                }

                return $id;
        },10, 1);

    }

    public function plugins_loaded() {
        $ok = load_plugin_textdomain('woo-vipps', false, basename( dirname( __FILE__ ) ) . "/languages");

        // Vipps Checkout replaces the default checkout page, and currently uses its own  page for this which needs to exist
        add_filter('woocommerce_create_pages', array($this, 'woocommerce_create_pages'), 50, 1);

        /* The gateway is added at 'plugins_loaded' and instantiated by Woo itself. IOK 2018-02-07 */
        add_filter( 'woocommerce_payment_gateways', array($this,'woocommerce_payment_gateways' ));

        // Callbacks use the Woo API IOK 2018-05-18
        add_action( 'woocommerce_api_wc_gateway_vipps', array($this,'vipps_callback'));
        add_action( 'woocommerce_api_vipps_shipping_details', array($this,'vipps_shipping_details_callback'));

        // Currently this sets Vipps as default payment method if hooked. IOK 2018-06-06 
        add_action( 'woocommerce_cart_updated', array($this,'woocommerce_cart_updated'));

        // Template integrations
        add_action( 'woocommerce_cart_actions', array($this, 'cart_express_checkout_button'));
        add_action( 'woocommerce_widget_shopping_cart_buttons', array($this, 'cart_express_checkout_button'), 30);
        add_action('woocommerce_before_checkout_form', array($this, 'before_checkout_form_express'), 5);

        add_action('woocommerce_after_add_to_cart_button', array($this, 'single_product_buy_now_button'));
        add_action('woocommerce_after_shop_loop_item', array($this, 'loop_single_product_buy_now_button'), 20);


        // Special pages and callbacks handled by template_redirect
        add_action('template_redirect', array($this,'template_redirect'),1);
        // Allow overriding their templates
        add_filter('template_include', array($this,'template_include'), 10, 1);

        // Ajax endpoints for checking the order status while waiting for confirmation
        add_action('wp_ajax_nopriv_check_order_status', array($this, 'ajax_check_order_status'));
        add_action('wp_ajax_check_order_status', array($this, 'ajax_check_order_status'));


        // Buying a single product directly using express checkout IOK 2018-09-28
        add_action('wp_ajax_nopriv_vipps_buy_single_product', array($this, 'ajax_vipps_buy_single_product'));
        add_action('wp_ajax_vipps_buy_single_product', array($this, 'ajax_vipps_buy_single_product'));

        // This is for express checkout which we will also do asynchronously IOK 2018-05-28
        add_action('wp_ajax_nopriv_do_express_checkout', array($this, 'ajax_do_express_checkout'));
        add_action('wp_ajax_do_express_checkout', array($this, 'ajax_do_express_checkout'));

        // Same thing, but for single products IOK 2018-05-28
        add_action('wp_ajax_nopriv_do_single_product_express_checkout', array($this, 'ajax_do_single_product_express_checkout'));
        add_action('wp_ajax_do_single_product_express_checkout', array($this, 'ajax_do_single_product_express_checkout'));

        // The normal 'cancel unpaid order' thing for Woo only works for orders created via normal checkout
        // We want it to work with Vipps Checkout and Express Checkout orders too IOK 2021-11-24 
        add_filter('woocommerce_cancel_unpaid_order', function ($cancel, $order) {
            if ($cancel) return $cancel;
            // Only check Vipps orders
            if ($order->get_payment_method() != 'vipps') return $cancel;
            // For Vipps, all unpaid orders must be pending.
            if ($order->get_status() != 'pending') return $cancel;
            // We do need to check the order status, because this could be called very frequently on some sites.
            try {
              $details = $this->gateway()->get_payment_details($order);
              if ($details && isset($details['status']) && $details['status'] == 'CANCEL') {
                  return true;
              }
            } catch (Exception $e) {
              // Don't do anything here at this point. IOK 2021-11-24
            } 
            return $cancel;
        }, 20, 2);

        // Used both in admin and non-admin-scripts, load as quick as possible IOK 2020-09-03
        $this->vippsJSConfig = array();
        $this->vippsJSConfig['vippsajaxurl'] =  admin_url('admin-ajax.php');
        $this->vippsJSConfig['BuyNowWith'] = __('Buy now with', 'woo-vipps');
        $this->vippsJSConfig['BuyNowWithVipps'] = __('Buy now with Vipps', 'woo-vipps');
        $this->vippsJSConfig['vippslogourl'] = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $this->vippsJSConfig['vippssmileurl'] = plugins_url('img/vipps-smile-orange.png',__FILE__);
        $this->vippsJSConfig['vippsbuynowbutton'] = __( 'Vipps Buy Now button', 'woo-vipps' );
        $this->vippsJSConfig['vippsbuynowdescription'] =  __( 'Add a Vipps Buy Now-button to the product block', 'woo-vipps');
        $this->vippsJSConfig['vippslanguage'] = $this->get_customer_language();
        $this->vippsJSConfig['vippsexpressbuttonurl'] = plugins_url('img/pay-with-vipps.svg', __FILE__);
       

        // If the site supports Gutenberg Blocks, support the Checkout block IOK 2020-08-10
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once(dirname(__FILE__) . "/Blocks/Payment/Vipps.class.php");
            Automattic\WooCommerce\Blocks\Payments\Integrations\Vipps::register();
        }

    }

    // IOK 2021-12-09 try to get the current language in the format Vipps wants, one of 'en' and 'no'
    public function get_customer_language() {
        $language = ""; 
        if (function_exists('pll_current_language')) {
           $language = pll_current_language('slug');
        } elseif (has_filter('wpml_current_language')){
           $language=apply_filters('wpml_current_language',null);
        } 
        if (! $language) $language = substr(get_bloginfo('language'),0,2);
        if ($language == 'nb' || $language == 'nn') $language = 'no';
        if (! in_array($language, ['en', 'no'])) $language = 'en';
        return $language;
    }
    
    public function save_order($postid,$post,$update) {
        if ($post->post_type != 'shop_order') return;
        $order = wc_get_order($postid);
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') return;

        if (isset($_POST['do_capture_vipps']) && $_POST['do_capture_vipps']) {
            $gw = $this->gateway();
            $ok = $gw->maybe_capture_payment($postid);
            // This will result in a redirect, so store admin notices, then display them. IOK 2018-05-07
            $this->store_admin_notices();
        }

        if (isset($_POST['do_refund_superfluous_vipps']) && $_POST['do_refund_superfluous_vipps']) {
            $gw = $this->gateway();
            $ok = $gw->refund_superfluous_capture($order);
            // This will result in a redirect, so store admin notices, then display them. IOK 2018-05-07
            $this->store_admin_notices();
        }

    }

    // Make admin-notices persistent so we can provide error messages whenever possible. IOK 2018-05-11
    public function store_admin_notices() {
        ob_start();
        do_action('admin_notices');
        $notices = ob_get_clean();
        set_transient('_vipps_save_admin_notices',$notices, 5*60);
    }


    public function order_item_add_action_buttons ($order) {
        $this->order_item_add_capture_button($order);
        $this->order_item_refund_superfluous_captured_amount($order);
    }

    public function order_item_add_capture_button ($order) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') return;
        $status = $order->get_status();

        $show_capture_button = ($status == 'on-hold' || $status == 'processing');
        if (!apply_filters('woo_vipps_show_capture_button', $show_capture_button, $order)) {
            return; 
        }

        $captured = intval($order->get_meta('_vipps_captured'));
        $capremain = intval($order->get_meta('_vipps_capture_remaining'));
        if ($captured && !$capremain) { 
            print "<div><strong>" . __("The entire amount has been captured at Vipps", 'woo-vipps') . "</strong></div>";
            return;
        }

        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);

        print '<button type="button" onclick="document.getElementById(\'docapture\').value=1;document.post.submit();" style="background-color:#ff5b24;border-color:#ff5b24;color:#ffffff" class="button vippsbutton generate-items"><img border=0 style="display:inline;height:2ex;vertical-align:text-bottom" class="inline" alt=0 src="'.$logo.'"/> ' . __('Capture payment','woo-vipps') . '</button>';
        print "<input id=docapture type=hidden name=do_capture_vipps value=0>"; 
    } 

    public function order_item_refund_superfluous_captured_amount ($order) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') return;
        $status = $order->get_status();

        if ($status != 'completed') return;

        $captured = intval($order->get_meta('_vipps_captured'));
        $total = intval(100*wc_format_decimal($order->get_total(),''));
        $refunded = intval($order->get_meta('_vipps_refunded'));

        $superfluous = $captured-$total-$refunded;


        if ($superfluous<=0) {
            return;
        }
        print "<div><strong>" . __('More funds than the order total has been captured at Vipps. Press this button to refund this amount at Vipps without editing this order', 'woo_vipps') . "</strong></div>";
        print '<button type="button" onclick="document.getElementById(\'dorefundsuperfluous\').value=1;document.post.submit();" style="background-color:#ff5b24;border-color:#ff5b24;color:#ffffff" class="button generate-items">' .__('Refund superfluous payment','woo-vipps') . '</button>';
        print "<input id=dorefundsuperfluous type=hidden name=do_refund_superfluous_vipps value=0>"; 
    } 


    // This is the main callback from Vipps when payments are returned. IOK 2018-04-20
    public function vipps_callback() {
        // Required for Checkout, we send this early as error recovery here will be tricky anyhow.
        status_header(202, "Accepted");

        $raw_post = @file_get_contents( 'php://input' );
        $result = @json_decode($raw_post,true);

        // This handler handles both Vipps Checkout and Vipps ECom IOK 2021-09-02
        $ischeckout = false;
        $callback = isset($_REQUEST['callback']) ?  $_REQUEST['callback'] : "";
	    $parts = array_reverse(explode("/", $callback));
        if (isset($parts[3]) && $parts[3] == 'checkout') {
            $ischeckout = true;
        }

        $vippsorderid = ($result && isset($result['orderId'])) ? $result['orderId'] : "";
        // For checkout, the orderId has been renamed to "reference" IOK 2022-02-11
        // We set the orderId here very early so old filters and hooks will continue working - mostly used for debugging.
        if (!$vippsorderid && $result && isset($result['reference'])) {
           $vippsorderid = $result['reference'];
           $result['orderId'] = $result['reference'];
        }
        
        do_action('woo_vipps_vipps_callback', $result,$raw_post);

        if (!$result) {
            $error = json_last_error_msg();
            $this->log(__("Did not understand callback from Vipps:",'woo-vipps') . " " .  $raw_post, 'error');
            $this->log(sprintf(__("Error was: %s",'woo-vipps'), $error));
            return false;
        }

        // For testing sites that appear not to receive callbacks
        if (isset($result['testing_callback'])) {
            $this->log(__("Received a test callback, exiting" , 'woo-vipps'), 'debug');
            print '{"status": 1, "msg": "Test ok"}';
            exit();
        }

        $orderid = $this->getOrderIdByVippsOrderId($vippsorderid);

        if (!$orderid) {
            $this->log(__("There is no order with this Vipps orderid, callback fails:",'woo-vipps') . " " . $vippsorderid,  'error');
            return false;
        }

        $order = wc_get_order($orderid);
        if (!is_a($order, 'WC_Order')) {
            $this->log(__("There is no order with this order id, callback fails:",'woo-vipps') . " " . $orderid,  'error');
            return false;
        }

        // a small bit of security
        if (!$order->get_meta('_vipps_authtoken') || (!wp_check_password($_REQUEST['tk'], $order->get_meta('_vipps_authtoken')))) {
            $this->log("Wrong authtoken on Vipps payment details callback", 'error');
            exit();
        }
        // Ensure we use the same session as for the original order IOK 2019-10-21
        $this->callback_restore_session($orderid);

        if ($ischeckout) {
             do_action('woo_vipps_callback_checkout', $result);
        } else {
             do_action('woo_vipps_callback', $result);
        }



        $gw = $this->gateway();

        $gw->handle_callback($result, $ischeckout);

        // Just to be sure, save any changes made to the session by plugins/hooks IOK 2019-10-22
        if (is_a(WC()->session, 'WC_Session_Handler')) WC()->session->save_data();
        exit();
    }

    // Helper function to get ISO-3166 two-letter country codes from country names as supplied by Vipps
    // IOK 2021-11-22 Seems as if Vipps is now sending two-letter country codes at least some times
    public function country_to_code($countryname) {
        if (!$this->countrymap) $this->countrymap = unserialize(file_get_contents(dirname(__FILE__) . "/lib/countrycodes.php"));
        $mapped = @$this->countrymap[strtoupper($countryname)];
        $code = WC()->countries->get_base_country();
        if ($mapped) {
           $code = $mapped;
        } else if (strlen($countryname)==2) {
           $code = strtoupper($countryname);
        }
        $code = apply_filters('woo_vipps_country_to_code', $code, $countryname);
        return  $code;
    }

    // To be added to the 'woocommerce_session_handler' filter IOK 2021-06-21
    public static function getCallbackSessionClass ($handler) {
          return  "VippsCallbackSessionHandler";
    }

    // Go back to the basic woocommerce session handler if we have temporarily restored session from an Vipps order 2021-06-21
    // Only to be called by wp-cron, callbacks etc. Will not actually destroy the stored session, just the current session.
    public function callback_destroy_session () {
           $this->callbackorder = null;
           remove_filter('woocommerce_session_handler', array('Vipps', 'getCallbackSessionClass'));
           unset(WC()->session);
           if (version_compare(WC_VERSION, '3.6.4', '>=')) {
               // This will replace the old session with this one. IOK 2019-10-22
               WC()->initialize_session(); 
           } else {
               // Do this manually for 3.6.3 and below
               WC()->session = new WC_Session_Handler();
               WC()->session->init();
           }
    }

    // When we get callbacks from Vipps, we want to restore the Woo session in place for the order.
    // For many plugins this is strictly neccessary because they don't check to see if there is a session
    // or not - and for many others, wrong results are produced without the (correct) session. IOK 2019-10-22
    protected function callback_restore_session ($orderid) {
        $this->callbackorder = $orderid;
        unset(WC()->session);
        require_once(dirname(__FILE__) . "/VippsCallbackSessionHandler.class.php");
        add_filter('woocommerce_session_handler', array('Vipps', 'getCallbackSessionClass')); 
        // Support older versions of Woo by inlining initialize session IOK 2019-12-12
        if (version_compare(WC_VERSION, '3.6.4', '>=')) {
            // This will replace the old session with this one. IOK 2019-10-22
            WC()->initialize_session(); 
        } else {
            // Do this manually for 3.6.3 and below
            $session_class = "VippsCallbackSessionHandler";
            WC()->session = new $session_class();
            WC()->session->init();
        }

        $customerid= 0;
        if (WC()->session && is_a(WC()->session, 'WC_Session_Handler')) {
            $customerid = WC()->session->get('express_customer_id');
        }
        if ($customerid) {
            WC()->customer = new WC_Customer($customerid); // Reset from session, logged in user
        } else {
            WC()->customer = new WC_Customer(); // Reset from session
        }
        // This is to provide defaults; real address will come from Vipps in this sitation. IOK 2019-10-25
        WC()->customer->set_billing_address_to_base();
        WC()->customer->set_shipping_address_to_base();

        // The normal "restore cart from session" thing runs on wp_loaded, and only there, and cannot
        // be called from outside the WC_Cart object. We cannot easily run this on wp_loaded, and it does 
        // do much more than it should for this particular use:
        // We have already created the order, so we only want this cart for the shipping calculations.
        // Therefore, we will just recreate the 'data' bit of the contents and set the cart contents directly
        // from the now restored session. IOK 2020-04-08
        // IOK 2022-06-28 Updated to also call the woocommerce_get_cart_item_from_session filters and to correctly handle
        // coupons.
        $newcart = array();
        if (WC()->session->get('cart', false)) {
            foreach(WC()->session->get('cart',[]) as $key => $values) {
                $product = wc_get_product( $values['variation_id'] ? $values['variation_id'] : $values['product_id'] );
                $session_data = array_merge($values, array( 'data' => $product));
                $newcart[$key] = apply_filters( 'woocommerce_get_cart_item_from_session', $session_data, $values, $key );
            }
        }
        if (WC()->cart) {
            WC()->cart->set_totals( WC()->session->get( 'cart_totals', null ) );
            WC()->cart->set_applied_coupons( WC()->session->get( 'applied_coupons', array() ) );
            WC()->cart->set_coupon_discount_totals( WC()->session->get( 'coupon_discount_totals', array() ) );
            WC()->cart->set_coupon_discount_tax_totals( WC()->session->get( 'coupon_discount_tax_totals', array() ) );
            WC()->cart->set_removed_cart_contents( WC()->session->get( 'removed_cart_contents', array() ) );
            WC()->cart->set_cart_contents($newcart);
            // IOK 2020-07-01 plugins expect this to be called: hopefully they'll not get confused by it happening twice
            do_action( 'woocommerce_cart_loaded_from_session', WC()->cart);
            WC()->cart->calculate_totals(); // And if any of them changed anything, recalculate the totals again!
        } else {
            // Apparently this happens quite a lot, so don't log it or anything. IOK 2021-06-21
        }
        return WC()->session;
    }



    // Based on either a logged-in user, or the stores' default address, get the address to use when using
    // the Express Checkout static shipping feature
    // This is neccessary because WC()->customer->set_shipping_address_to_base() only sets country and state.
    // IOK 2020-03-18
    public function get_static_shipping_address_data () {
         // This is the format used by the Vipps callback, we are going to mimic this.
         $defaultdata = array('addressId'=>0, "addressLine1"=>"", "addressLine2"=>"", "country"=>"NO", "city"=>"", "postalCode"=>"", "postCode"=>"", "addressType"=>"H"); 
         $addressok=false;
         if (WC()->customer) {
           $address = WC()->customer->get_shipping();
           if (empty(@$address['country']) || empty(@$address['city']) || empty(@$address['postcode'])) $address = WC()->customer->get_billing();
           if (@$address['country'] && @$address['city'] && @$address['postcode']) {
              $addressok = true;
              $defaultdata['country'] = $address['country'];
              $defaultdata['city'] = $address['city'];
              $defaultdata['postalCode'] = $address['postcode'];
              $defaultdata['postCode'] = $address['postcode'];
              $defaultdata['addressLine1'] = @$address['address_1'];
              $defaultdata['addressLine2'] = @$address['address_2'];
           }
         } 
         if (!$addressok) {
             $countries=new WC_Countries();
             $defaultdata['country'] = $countries->get_base_country();
             $defaultdata['city'] = $countries->get_base_city(); 
             $defaultdata['postalCode'] = $countries->get_base_postcode();
             $defaultdata['postCode'] =   $countries->get_base_postcode();
             $defaultdata['addressLine1'] = $countries->get_base_address();
             # Let addressLine2 be empty though, it's not needed for this.
             $defaultdata['addressLine2'] = "";
             $addressok=true;
         }
         return $defaultdata;
    }

    // Getting shipping methods/costs for a given order to Vipps for express checkout
    public function vipps_shipping_details_callback() {
        wc_nocache_headers();

        $raw_post = @file_get_contents( 'php://input' );
        $result = @json_decode($raw_post,true);

        if (!$result) {
           $error = json_last_error_msg();
           $this->log(sprintf(__("Error getting customer data in the Vipps shipping details callback: %s",'woo-vipps'), $error));
           $this->log(__("Raw input was ", 'woo-vipps'));
           $this->log($raw_post);
        }
        $callback = sanitize_text_field(@$_REQUEST['callback']);
        do_action('woo_vipps_shipping_details_callback', $result,$raw_post,$callback);

        $data = array_reverse(explode("/",$callback));
        $vippsorderid = @$data[1]; // Second element - callback is /v2/payments/{orderId}/shippingDetails
        $orderid = $this->getOrderIdByVippsOrderId($vippsorderid);

        $this->callback_restore_session($orderid);       

        do_action('woo_vipps_shipping_details_callback_order', $orderid, $vippsorderid);

        if (!$orderid) {
            $this->log(__('Could not find Vipps order with id:', 'woo-vipps') . " " . $vippsorderid . "\n" . __('Callback was:', 'woo-vipps') . " " . $callback, 'error');
            exit();
        }

        $order = wc_get_order($orderid);
        if (!$order) {
            $this->log(__('Could not find Woo order with id:', 'woo-vipps') . " " . $orderid, 'error');
            exit();
        }
        if ($order->get_payment_method() != 'vipps') {
            $this->log(__('Invalid order for shipping callback:', 'woo-vipps') . " " . $orderid, 'error');
            exit();
        }
        // a small bit of security
        if (!$order->get_meta('_vipps_authtoken') || (!wp_check_password($_REQUEST['tk'], $order->get_meta('_vipps_authtoken')))) {
            $this->log("Wrong authtoken on shipping details callback", 'error');
            exit();
        }
        
        $return = $this->vipps_shipping_details_callback_handler($order, $result,$vippsorderid);
 
        # Checkout does not have an addressID here, and should not be 'wrapped'
        if (!isset($return['addressId'])) {
           $return = $return['shippingDetails'];
        }

        $json = json_encode($return);
        header("Content-type: application/json; charset=UTF-8");
        print $json;
        // Just to be sure, save any changes made to the session by plugins/hooks IOK 2019-10-22
        if (is_a(WC()->session, 'WC_Session_Handler')) WC()->session->save_data();
        exit();
    }
   
    public function vipps_shipping_details_callback_handler($order, $vippsdata,$vippsorderid) {

       // Get addressinfo from the callback, this is from Vipps. IOK 2018-05-24. 
       // {"addressId":973,"addressLine1":"BOKS 6300, ETTERSTAD","addressLine2":null,"country":"Norway","city":"OSLO","postalCode":"0603","postCode":"0603","addressType":"H"}
       // checkout: {"streetAddress":"Observatorie terrasse 4a","postalCode":"0254","region":"Oslo","country":"NO"}

       // Since we have legacy users that may have filters defined on these values, we will translate newer apis to the older ones.
       // so filters will continue to work for newer apis/checkout
       if (isset($vippsdata['streetAddress'])){
            $vippsdata['addressLine1'] = $vippsdata['streetAddress'];
            $vippsdata['addressLine2'] = "";
       }
       if (isset($vippsdata['region'])) {
            $vippsdata['city'] = $vippsdata['region']; 
         }
       if (isset($vippsdata['postalCode'])) {
            $vippsdata['postCode'] = $vippsdata['postalCode']; 
        }
        // Translations for different versions of the API end

        $addressid = isset($vippsdata['addressId']) ? $vippsdata['addressId'] : "";
        $addressline1 = $vippsdata['addressLine1'];
        $addressline2 = $vippsdata['addressLine2'];

        // IOK 2019-08-26 apparently the apps contain a lot of addresses with duplicate lines
        if ($addressline1 == $addressline2) $addressline2 = '';
        if (!$addressline2) $addressline2 = '';

        $vippscountry = $vippsdata['country'];
        $city = $vippsdata['city'];
        $postcode= $vippsdata['postCode'];
        $country = $this->country_to_code($vippscountry);

        $order->set_billing_address_1($addressline1);
        $order->set_billing_address_2($addressline2);
        $order->set_billing_city($city);
        $order->set_billing_postcode($postcode);
        $order->set_billing_country($country);
        $order->set_shipping_address_1($addressline1);
        $order->set_shipping_address_2($addressline2);
        $order->set_shipping_city($city);
        $order->set_shipping_postcode($postcode);
        $order->set_shipping_country($country);
        $order->save();

        // This is *essential* to get VAT calculated correctly. That calculation uses the customer, which uses the session.IOK 2019-10-25
        if (WC()->customer) {  
            WC()->customer->set_billing_location($country,'',$postcode,$city);
            WC()->customer->set_shipping_location($country,'',$postcode,$city);
        } else {
            $this->log("No customer! when trying to calculate shipping");
        }


        // If you need to do something before the cart is manipulated, this is where it must be done.
        // It is possible for a plugin to require a session when manipulating the cart, which could 
        // currently crash the system. This could be used to avoid that. IOK 2019-10-09
        do_action('woo_vipps_shipping_details_before_cart_creation', $order, $vippsorderid, $vippsdata);


        //  Previously, we would create a shoppingcart at this point, because we would not have access to the 'live' one,
        // but it turns out this isn't actually possible. Any cart so created will become "the" cart for the Woo front end,
        // and anyway, some plugins override the class of the cart, so just using WC_Cart will sometimes break.
        //  Now however, the session is stored in the order, and the cart will not have been deleted, so we should
        // now be able to calculate shipping for the actual cart with no further manipulation. IOK 2020-04-08
        WC()->cart->calculate_totals();
        $acart = WC()->cart;

        $shipping_methods = array();
        // If no shipping is required (for virtual products, say) ensure we send *something* back IOK 2018-09-20 
        if (!$acart->needs_shipping()) {
            $no_shipping_taxes = WC_Tax::calc_shipping_tax('0', WC_Tax::get_shipping_tax_rates());
            $shipping_methods['none_required:0'] = new WC_Shipping_Rate('none_required:0',__('No shipping required','woo-vipps'),0,$no_shipping_taxes, 'none_required', 0);
        } else {
            $packages = apply_filters('woo_vipps_shipping_callback_packages', WC()->cart->get_shipping_packages());
            $shipping =  WC()->shipping->calculate_shipping($packages);
            $shipping_methods = WC()->shipping->packages[0]['rates']; // the 'rates' of the first package is what we want.
         }

        // No exit here, because developers can add more methods using the filter below. IOK 2018-09-20
        if (empty($shipping_methods)) {
            $this->log(__('Could not find any applicable shipping methods for Vipps Express Checkout - order will fail', 'woo-vipps', 'warning'));
        }

        $chosen = null;
        if (is_a(WC()->session, 'WC_Session_Handler')) {
            $all_chosen =  WC()->session->get( 'chosen_shipping_methods' );
            if (!empty($all_chosen)) $chosen= $all_chosen[0];
        }

        // Merchant is using the old 'woo_vipps_shipping_methods' filter, and hasn't chosen to disable it. Use legacy methd.
        if (has_action('woo_vipps_shipping_methods') &&  $this->gateway()->get_option('newshippingcallback') != 'new') {
            return $this->legacy_shipping_callback_handler($shipping_methods, $chosen, $addressid, $vippsorderid, $order, $acart);
        }
        // Default 'priority' is based on cost, so sort this thing
        uasort($shipping_methods, function($a, $b) { return $a->get_cost() - $b->get_cost(); });

        // IOK 2020-02-13 Ok, new method!  We are going to provide a list full of metadata for the users to process this time, which we will massage into the final
        // Vipps method list
        $methods = array();
        $i=-1;


        foreach ($shipping_methods as  $key=>$rate) {
            $i++;
            $method = array();
            $method['priority'] = $i;
            $method['default'] = false;
            $method['rate'] = $rate;
            $methods[$key]= $method;
        }
        $chosen = apply_filters('woo_vipps_default_shipping_method', $chosen, $shipping_methods, $order);
        if ($chosen && !isset($methods[$chosen]))  {
            $chosen = null; // Actually that isn't available
            $this->log(__("Unavailable shipping method set as default in the Vipps Express Checkout shipping callback - check the 'woo_vipps_default_shipping_method' filter",'debug'));
        }

        if (!$chosen) {
            // Find first method that isn't 'local_pickup'
            foreach($methods as $key=>&$data) {
              if ($data['rate']->get_method_id() != 'local_pickup') {
                 $chosen = $key;
                 break;
              }
            }
            // Ok, just pick the first
            if (!$chosen) {
               foreach($methods as $key=>&$data) {
                 $chosen = $key;
                 break;
               }
             
            }
        }
        if (isset($methods[$chosen])) {
            $methods[$chosen]['default'] = true;
        }
        $methods = apply_filters('woo_vipps_express_checkout_shipping_rates', $methods, $order, $acart);

        $vippsmethods = array();
        $storedmethods = $order->get_meta('_vipps_express_checkout_shipping_method_table');
        if (!$storedmethods) $storedmethods= array();

        // Just a utility from shippingMethodIds to the non-serialized rates
        $ratemap = array();

        foreach($methods as $method) {
           $rate = $method['rate'];
           $tax  = $rate->get_shipping_tax();
           $cost = $rate->get_cost();
           $label = $rate->get_label();
           // We need to store the WC_Shipping_Rate object with all its meta data in the database until return from Vipps. IOK 2020-02-17
           $serialized = '';
           try {
               $serialized = serialize($rate);
           } catch (Exception $e) {
               $this->log(sprintf(__("Cannot use shipping method %s in Vipps Express checkout: the shipping method isn't serializable.", 'woo-vipps'), $label));
               continue;
           }
           // Ensure this never is over 100 chars. Use a dollar sign to indicate 'new method' IOK 2020-02-14
           // We can't just use the method id, because the customer may have different addresses. Just to be sure, hash the entire method and use as a key.
           $key = '$' . substr($rate->get_method_id(),0,58) . '$' . sha1($serialized);
           $vippsmethod = array();
           $vippsmethod['isDefault'] = @$method['default'] ? 'Y' :'N';
           $vippsmethod['priority'] = $method['priority'];
           $vippsmethod['shippingCost'] = sprintf("%.2F",wc_format_decimal($cost+$tax,''));
           $vippsmethod['shippingMethod'] = $rate->get_label();
           $vippsmethod['shippingMethodId'] = $key;
           $vippsmethods[]=$vippsmethod;
           // Retrieve these precalculated rates on return from the store IOK 2020-02-14 
           $storedmethods[$key] = $serialized;
           $ratemap[$key]=$rate;
        }
        $order->update_meta_data('_vipps_express_checkout_shipping_method_table', $storedmethods);
        $order->save();
 
        $return = array('addressId'=>intval($addressid), 'orderId'=>$vippsorderid, 'shippingDetails'=>$vippsmethods);
        $return = apply_filters('woo_vipps_vipps_formatted_shipping_methods', $return); // Mostly for debugging

        // IOK 2021-11-16 Vipps Checkout uses a slightly different syntax and format.
        if ($order->get_meta('_vipps_checkout')) {
            $translated = array();
            $currency = get_woocommerce_currency();
            foreach ($return['shippingDetails']  as $m) {

                  $m2['isDefault'] = (bool) (($m['isDefault']=='Y') ? true : false); // type bool here, but not in the other api
                  $m2['priority'] = $m['priority'];
                  $m2['amount'] = array(
                            'value' => round(100*$m['shippingCost']), // Unlike eComm, this uses cents
                            'currency' => $currency // May want to use the orders' currency instead here, since it exists.
                          );
                  $m2['product'] = $m['shippingMethod'];
                  $m2['id'] = $m['shippingMethodId'];

                  // Extra fields available for Checkout only, using the Rate as input   
                  $rate = $ratemap[$m2['id']];
                  // "Brand" not present in Woo but supply filters - must be 'posten', 'helthjem', 'postnord'
                  $m2['brand'] = apply_filters('woo_vipps_shipping_method_brand', '',  $rate);
                  // Not present in WordPress, so allow filters to add it
                  $m2['description'] = apply_filters('woo_vipps_shipping_method_description', '', $rate);
                  // Pickup points! But only if the shipping method supports it, which is currently for Bring and PostNord
                  $m2['isPickupPoint'] = apply_filters('woo_vipps_shipping_method_pickup_point', false, $rate);

                  $translated[] = $m2;
            }
            $return['shippingDetails'] = $translated;
            unset($return['addressId']); // Not used it seems for checkout
            unset($return['orderId']);
        }

        return $return;
    }


    // IOK 2020-02-13 This method implements the *old* style of providing shipping methods to Vipps Express Checkout.
    // It is 'stateless' in that it doesn't need to serialize shipping methods or anything like that - but precisely because of this,
    // metadata isn't possible to provide, and it reqires to send VAT separately coded into the shipping method ID which is pretty
    // clumsy. This method will currently only be used if a merchant has overridden the 'woo_vipps_shipping_methods' filter and hasn't chosen
    // the setting that overrides this.
    public function legacy_shipping_callback_handler ($shipping_methods, $chosen, $addressid, $vippsorderid, $order, $acart) {
        do_action('woo_vipps_legacy_shipping_methods', $order); // This will probably be mostly for debugging.

        // If no shipping is required (for virtual products, say) ensure we send *something* back IOK 2018-09-20 
        if (!$acart->needs_shipping()) {
            $methods = array(array('isDefault'=>'Y','priority'=>'0','shippingCost'=>'0.00','shippingMethod'=>__('No shipping required','woo-vipps'),'shippingMethodId'=>'Free:Free;0'));
            $return = array('addressId'=>intval($addressid), 'orderId'=>$vippsorderid, 'shippingDetails'=>$methods);
            return $return;
        }

        $free = 0;
        $defaultset = 0;
        $methods = array();
        foreach ($shipping_methods as  $rate) {
            $method = array();
            $method['priority'] = 0;
            $tax  = $rate->get_shipping_tax();
            $cost = $rate->get_cost();

            $method['shippingCost'] = sprintf("%.2F",wc_format_decimal($cost+$tax,''));
            $method['shippingMethod'] = $rate->get_label();
            // We may not really need the tax stashed here, but just to be sure.
            $method['shippingMethodId'] = $rate->get_id() . ";" . $tax; 
            $methods[]= $method;

            // If we qualify for free shipping, make it the default. Thanks to Emely Bakke for reporting. IOK 2019-11-15
            if (preg_match("!^free_shipping!",$rate->get_id())) {
                $free=1;
                $defaultset=1;
                $chosen = $rate->get_id();
            }
        }
        usort($methods, function($method1, $method2) {
                return $method1['shippingCost'] - $method2['shippingCost'];
                });
        $priority=0;
        foreach($methods as &$method) {
            $rateid = explode(";",$method['shippingMethodId'],2);
            if (!empty($rateid) && $rateid[0] == $chosen) {
                $defaultset=1;
                $method['isDefault'] = 'Y';
            } else {
                $method['isDefault'] = 'N';
            }
            $method['priority']=$priority;
            $priority++;
        }
        // If we don't have free shipping, select the first (cheapest) option, unless that is 'local pickup'. IOK 2019-11-26
        if(!$defaultset && !empty($methods)) {
            foreach($methods as &$method) {
                if (!preg_match("!^local_pickup!",$method['shippingMethodId'])) {
                    $defaultset=1;
                    $method['isDefault'] = 'Y';
                    break;
                }
            }
        }
        // Or the first if we stil have no default method.
        if (!$defaultset &&!empty($methods)) {
            $methods[0]['isDefault'] = 'Y';
        }

        $return = array('addressId'=>intval($addressid), 'orderId'=>$vippsorderid, 'shippingDetails'=>$methods);
        $return = apply_filters('woo_vipps_shipping_methods', $return,$order,$acart);

        return $return;
    }



    // Handle DELETE on a vipps consent removal callback
    public function vipps_consent_removal_callback ($callback) {
	    wc_nocache_headers();
            // Currently, no such requests will be posted, and as this code isn't sufficiently tested,we'll just have 
            // to escape here when the API is changed. IOK 2020-10-14
            $this->log("Consent removal is non-functional pending API changes as of 2020-10-14"); print "1"; exit();
	    //DELETE:[consetRemovalPrefix]/v2/consents/{userId}
	    $parts = array_reverse(explode("/", $callback));
	    if (empty($parts)) return 0;

	    $userId = $parts[0];

            $this->log(__("Got a consent removal call for user with Vipps id $userId", 'woo-vipps'));

	    global $wpdb;
            // a single userID may have several accounts on Woo
	    $query = $wpdb->prepare("select * from {$wpdb->prefix}usermeta where key='_vipps_express_id' and value=%s", $userId); 
            $users = $wpdb->get_results($query, ARRAY_A);
	    foreach ($users as $userdata) {
		    $userdata = $wpdb->get_row($query, ARRAY_A);
		    $user_id=0;
		    if (!empty($userdata)) {
			    $user_id = $userdata['user_id']; 
		    }
		    if (!$user_id) {
			    $this->log(__("Could not find user with Vipps user ID %s for account deletion", 'woo-vipps'), $userId);
			    continue;
		    }
		    $user = get_user_by('ID', $user_id);
		    if (!$user) {
			    $this->log(__("No user with id %d when processing consent removal request", 'woo-vipps'), $userid);
			    continue;
		    }
                    // Only do deletion for non-privileged users so admins don't accidentally erase themselves IOK 2020-10-12
                    if (user_can($userid, 'manage_woocommerce') || user_can($userid,'manage_options'))  {
			    $this->log(__("User with ID %d is an adminstrator - user erase request is not sent after receiving consent removal request", 'woo-vipps'), $userid);
			    continue;
                    }


		    // We'll use the standard API by WordPress to handle this as an erasure request. IOK 2020-10-12. This gives a nice
		    // confirmation to the user, and allows the admin to handle these carefully.
		    $email = $user->user_email;
		    if (!$email) {
			    $this->log(__("User %d has no valid email", 'woo-vipps'), $user_id);
			    continue;
		    }
		    $request_id = wp_create_user_request( $email , 'remove_personal_data');
		    if (is_wp_error($request_id)) {
			    $this->log(__("Could not handle remove personal data request for user %s : %s", 'woo-vipps'), $email, $request_id->get_error_message());
			    continue;
		    }
		    wp_send_user_request( $request_id );
		    $this->log(__("Based on a consent removal call from the Vipps app a data erasure request has been created and a confirmation request been sent to the user %s", 'woo-vipps'), $email);
	    }
	    print "1";
	    exit();
    }

    public function woocommerce_payment_gateways($methods) {
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        $methods[] = WC_Gateway_Vipps::instance();
        return $methods;
    }

    // Runs after set_session, so if the session is just created, we'll get called. IOK 2018-06-06
    public function woocommerce_cart_updated() {
        $this->maybe_set_vipps_as_default();
    }

    public function woocommerce_add_to_cart_redirect ($url) {
        if ( empty($_REQUEST['add-to-cart']) || ! is_numeric($_REQUEST['add-to-cart']) || empty($_REQUEST['vipps_compat_mode']) || !$_REQUEST['vipps_compat_mode']) {
            return $url;
        }
        $url = $this->express_checkout_url();
        $url = wp_nonce_url($url,'express','sec');

        return $url;
    }

    // We can't allow a customer to re-call the Vipps Express checkout payment thing twice -
    // This would happen if a logged-in user tries to re-start the transaction after breaking it.
    // But for express checkout this breaks because there is no shipping method or address, and of course,
    // the order id is unique too.. IOK 2018-11-21
    public function  woocommerce_my_account_my_orders_actions($actions, $order ) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') return $actions;

        if ($order->get_meta('_vipps_express_checkout')) {
            unset($actions['pay']);
        }
        return $actions;
    }

    // This job runs in the wp-cron context, and is intended to clean up signal files and other temporariy data. IOK 2020-04-01
    public function cron_cleanup_hook () {
       $this->cleanupCallbackSignals(); // Remove old callback signals (files in uploads)
       $this->delete_old_cancelled_orders(); // Remove cancelled express checkout orders if selected
    }

    // This job runs in the wp-cron context and checks if there are *old* pending orders with payment method Vipps. If so, it will
    // check if the status of these orders are now known. This is intended to handle the case where a user does not return
    // to the store and the Vipps callback fails for whatever reason.  IOK 2021-06-21
    public function cron_check_for_missing_callbacks() {
        $eightminutesago = time() - (60*8);
        $sevendaysago = time() - (60*60*24*7);
        $pending = wc_get_orders(
          array('limit'=>-1, 'status'=>'pending', 'payment_method' => 'vipps', 'date_created' => '>' . $sevendaysago ));
        if (empty($pending)) return;
        foreach ($pending as $o) {
            $then = $o->get_meta('_vipps_init_timestamp');
            if (! $then) continue; # Race condition! We may not have set the timestamp yet. IOK 2022-03-24
            if (!$o->get_meta('_vipps_orderid')) continue; # ditto
            if ($then > $eightminutesago) continue;
            $this->check_status_of_pending_order($o, false);
        }
    }

    // Check and possibly update the status of a pending order at Vipps. We only restore session if we know this is called from a context with no session -
    // e.g. wp-cron. IOK 2021-06-21
    // Stop restoring session in wp-cron too. IOK 2021-08-23
    public function check_status_of_pending_order($order, $maybe_restore_session=0) {
        $express = $order->get_meta('_vipps_express_checkout'); 
        if ($express && $maybe_restore_session) {
           $this->log(sprintf(__("Restoring session of order %d", 'woo-vipps'), $order->get_id()), 'debug'); 
           $this->callback_restore_session($order->get_id());
        }
        $gw = $this->gateway();
        $order_status = null;
        try {
            $order->add_order_note(__("Callback from Vipps delayed or never happened; order status checked by periodic job", 'woo-vipps'));
            $order_status = $gw->callback_check_order_status($order);
            $order->save();
            $this->log(sprintf(__("For order %d order status at vipps is %s", 'woo-vipps'), $order->get_id(), $order_status), 'debug');
        } catch (Exception $e) {
            $this->log(sprintf(__("Error getting order status at Vipps for order %d", 'woo-vipps'), $order->get_id()), 'error'); 
            $this->log($e->getMessage() . "\n" . $order->get_id(), 'error');
        }
        // Ensure we don't keep using an old session for more than one order here.
        if ($express && $maybe_restore_session) {
            $this->callback_destroy_session();
        }
        return $order_status;
    }

    // This will probably be run in activate, but if the plugin is updated in other ways, will also be run on plugins_loaded. IOK 2020-04-01
    public static function maybe_add_cron_event() {
       if (!wp_next_scheduled('vipps_cron_cleanup_hook')) {
          wp_schedule_event(time(), 'hourly', 'vipps_cron_cleanup_hook');
       }
       if (!wp_next_scheduled('vipps_cron_missing_callback_hook')) {
          wp_schedule_event(time(), '5min', 'vipps_cron_missing_callback_hook');
       }
    }

    public function activate () {
       static::maybe_add_cron_event();
       $gw = $this->gateway();

       // If store is using the default "Woo" orderprefix, generate a new one, this time using the stores' sitename if possible. IOK 2020-05-19
       if ($gw->get_option('orderprefix') == 'Woo') {
         $gw->update_option('orderprefix', $this->generate_order_prefix()); 
       }
    }

    // We have added some hooks to wp-cron; remove these. IOK 2020-04-01
    public static function deactivate() {
       $timestamp = wp_next_scheduled('vipps_cron_cleanup_hook');
       wp_unschedule_event($timestamp, 'vipps_cron_cleanup_hook');
    }

    public static function uninstall() {
       // Nothing yet
    }
    public function footer() {
       // Nothing yet
    }


    // If setting is true, use Vipps as default payment. Called by the woocommrece_cart_updated hook. IOK 2018-06-06
    private function maybe_set_vipps_as_default() {
        if (WC()->session->get('chosen_payment_method')) return; // User has already chosen payment method, so we're done.
        $gw = $this->gateway();
        if ($gw->get_option('vippsdefault')=='yes') {
            WC()->session->set('chosen_payment_method', $gw->id);
        }
    }

    // Check order status in the database, and if it is pending for a long time, directly at Vipps
    // IOK 2018-05-04
    public function check_order_status($order) {
        if (!$order) return null;
        clean_post_cache($order->get_id());  // Get a fresh copy
        $order = wc_get_order($order->get_id());
        $order_status = $order->get_status();
        if ($order_status != 'pending') return $order_status;
        // No callback has occured yet. If this has been going on for a while, check directly with Vipps
        if ($order_status == 'pending') {
            $now = time();
            $then= $order->get_meta('_vipps_init_timestamp');
            if ($then + (1 * 60) < $now) { // more than a minute? Start checking at Vipps
                return $order_status;
            }
        }
        $this->log("Checking order status on Vipps for order id: " . $order->get_id(), 'info');
        return $this->check_status_of_pending_order($order);
    }

    // In some situations we have to empty the cart when the user goes to Vipps, so
    // we store it in the session and restore it if the users cancels. IOK 2018-05-07
    // Try to avoid this now 2018-12-10 - only do it for single-product checkouts. IOK 2018-10-12
    // Changed to use a serialized cart, which should be more compatible with subclassed carts and cart metadata.
    // Serialization errors are not yet handled - they can't be fixed but they could be signalled. IOK 2020-04-07
    public function save_cart($order,$cart_to_save) {
        $carts = WC()->session->get('_vipps_carts');
        if (!$carts) $carts = array();
        $serialized = base64_encode(@serialize($cart_to_save->get_cart_contents()));
        $carts[$order->get_id()] = $serialized;
        WC()->session->set('_vipps_carts',$carts); 
        do_action('woo_vipps_cart_saved');
    }
    public function restore_cart($order) {
        global $woocommerce;
        $carts = $woocommerce->session->get('_vipps_carts');
        if (empty($carts)) return;
        $cart = null;
        $cartdata = @$carts[$order->get_id()];
        if ($cartdata) {
            $cart = @unserialize(@base64_decode($cartdata));
        }
        do_action('woo_vipps_restoring_cart',$order,$cart);
        unset($carts[$order->get_id()]);
        $woocommerce->session->set('_vipps_carts',$carts);
        // It will absolutely not work to just use set_cart_contents, because this will not
        // correctly initialize this 'new' cart. So we *have* to use add_to_cart at least once.  IOK 2020-04-07
        if (!empty($cart)) {
            foreach ($cart  as $cart_item_key => $values) {
                $id =$values['product_id'];
                $quant=$values['quantity'];
                $varid = @$values['variation_id'];
                $variation = @$values['variation'];
                // .. and there may be any number of other attributes, which we need to pass on.
                $cart_item_data = array();
                foreach($values as $key=>$value) {
                    if (in_array($key,array('product_id','quantity','variation_id','variation'))) continue;
                    $cart_item_data[$key] = $value;
                }
                $woocommerce->cart->add_to_cart($id,$quant,$varid,$variation,$cart_item_data);
            }
        }
        do_action('woo_vipps_cart_restored');
    }

    // Maybe log in user
    // It is done on the thank-you page of the order, and only for express checkout.
    function maybe_log_in_user ($order) {
        if (is_user_logged_in()) return;
        if (!$order || $order->get_payment_method()!= 'vipps' ) return;

        // We *do* want to log in express checkout customers, but not those that 
        // use the Vipps Checkout solution - those can change their emails in the
        // checkout screen. IOK 2021-09-03
        $do_login =  $order->get_meta('_vipps_express_checkout');
        $do_login = $do_login && !$order->get_meta('_vipps_checkout');

        // Make this filterable because you may want to only log on some users
        $do_login = apply_filters('woo_vipps_login_user_on_express_checkout', $do_login, $order);
        if (!$do_login) return;

        $customer = $this->express_checkout_get_vipps_customer ($order);
        if( $customer) {
            $usermeta=get_userdata($customer->get_id());
            $iscustomer = (in_array('customer', $usermeta->roles) || in_array('subscriber', $usermeta->roles));
            // Ensure we don't have any admins with an additonal customer role logged in like this
            if($iscustomer && !user_can($customer->get_id(), 'manage_woocommerce') && !user_can($customer->get_id(),'manage_options'))  {
                do_action('express_checkout_before_customer_login', $customer, $order);

                $user = new WP_User( $customer->get_id());
                wp_set_current_user($customer->get_id(), $user->user_login);

                wp_set_auth_cookie($customer->get_id());
                do_action('wp_login', $user->user_login, $user);
            }
        }
    }

    // Get the customer that corresponds to the current order, maybe creating the customer if it does not exist yet and
    // the settings allow it.
    function express_checkout_get_vipps_customer($order) {
        if (!$order || $order->get_payment_method() != 'vipps' ) return;
        if (!$order->get_meta('_vipps_express_checkout')) return;
        if ($this->gateway()->get_option('expresscreateuser') != 'yes') return null;
        if (is_user_logged_in()) return new WC_Customer(get_current_user_id());
        if ($order->get_user_id()) return new WC_Customer($order->get_user_id());

        $email = $order->get_billing_email();

        // Existing customer, so update the order (and possibly the site if multisite) and return the customer. IOK 2020-10-09 
        if (email_exists($email)) {
            $user = get_user_by( 'email', $email);
            if (!$user) return null;
            $customerid = $user->ID;
            $order->set_customer_id( $user->ID );
            $order->save(); 

            if (is_multisite() && ! is_user_member_of_blog($customerid, get_current_blog_id())) {
                add_user_to_blog( get_current_blog_id(), $customerid, 'customer' );
            }
            $customer = new WC_Customer($customerid);
            return $customer;
        }

        // No customer yet. As we want to create users like this (set in the settings) let's do so.
        // Username will be created from email, but the settings may stop generating passwords, so we force that to be generated. IOK 2020-10-09
        $firstname = $order->get_billing_first_name();
        $lastname =  $order->get_billing_last_name();
        $name = $firstname;
        $userdata = array('user_nicename'=>$name, 'display_name'=>"$firstname $lastname", 'nickname'=>$firstname, 'first_name'=>$firstname, 'last_name'=>$lastname);
 

        $customerid = wc_create_new_customer( $email, '', wp_generate_password(), $userdata);
        if ($customerid && !is_wp_error($customerid)) {
            $order->set_customer_id( $customerid );
            $order->save(); 

            // Ensure the standard WP user fields are set too IOK 2020-11-03
            wp_update_user(array('ID' => $customerid, 'first_name' => $firstname, 'last_name' => $lastname, 'display_name' => "$firstname $lastname", 'nickname' => $firstname));

            update_user_meta( $customerid, 'billing_address_1', $order->get_billing_address_1() );
            update_user_meta( $customerid, 'billing_address_2', $order->get_billing_address_2() );
            update_user_meta( $customerid, 'billing_city', $order->get_billing_city() );
            update_user_meta( $customerid, 'billing_company', $order->get_billing_company() );
            update_user_meta( $customerid, 'billing_country', $order->get_billing_country() );
            update_user_meta( $customerid, 'billing_email', $order->get_billing_email() );
            update_user_meta( $customerid, 'billing_first_name', $order->get_billing_first_name() );
            update_user_meta( $customerid, 'billing_last_name', $order->get_billing_last_name() );
            update_user_meta( $customerid, 'billing_phone', $order->get_billing_phone() );
            update_user_meta( $customerid, 'billing_postcode', $order->get_billing_postcode() );
            update_user_meta( $customerid, 'billing_state', $order->get_billing_state() );
            update_user_meta( $customerid, 'shipping_address_1', $order->get_shipping_address_1() );
            update_user_meta( $customerid, 'shipping_address_2', $order->get_shipping_address_2() );
            update_user_meta( $customerid, 'shipping_city', $order->get_shipping_city() );
            update_user_meta( $customerid, 'shipping_company', $order->get_shipping_company() );
            update_user_meta( $customerid, 'shipping_country', $order->get_shipping_country() );
            update_user_meta( $customerid, 'shipping_first_name', $order->get_shipping_first_name() );
            update_user_meta( $customerid, 'shipping_last_name', $order->get_shipping_last_name() );
            update_user_meta( $customerid, 'shipping_method', $order->get_shipping_method() );
            update_user_meta( $customerid, 'shipping_postcode', $order->get_shipping_postcode() );
            update_user_meta( $customerid, 'shipping_state', $order->get_shipping_state() );

            // Integration with All-in-one WP security - these accounts are created by validated accounts in the app.
            update_user_meta( $customerid,'aiowps_account_status', 'approved');

            $customer = new WC_Customer($customerid);
            do_action('woo_vipps_express_checkout_new_customer', $customer, $order->get_id());

            return $customer;
        }
        if (is_wp_error($customerid)) {
            $this->log(__("Error creating customer in express checkout: ", 'woo-vipps') . $customerid->get_error_message());
        } else {
            $this->log(__("Unknown error customer in express checkout.", 'woo-vipps'));
        }
        return null;
    }

    // This restores the cart on order complete, but only if the current order was a single product buy with an active cart.
    public function maybe_restore_cart($orderid,$failed=false) {
        if (!$orderid) return;
        $o = null;
        try {
            $o = wc_get_order($orderid);
        } catch (Exception $e) {
            // Well, we tried.
        }
        if (!$o) return;
        if (!$o->get_meta('_vipps_single_product_express')) return;
        if ($failed && !apply_filters('woo_vipps_restore_cart_on_express_checkout_failure', true, $o)) return;
        if ($failed) WC()->cart->empty_cart();
        $this->restore_cart($o);
    }


    public function ajax_vipps_buy_single_product () {
        wc_nocache_headers();
        // We're not checking ajax referer here, because what we do is creating a session and redirecting to the
        // 'create order' page wherein we'll do the actual work. IOK 2018-09-28
        $session = WC()->session;
        if (!$session->has_session()) {
            $session->set_customer_session_cookie(true);
        }
        $session->set('__vipps_buy_product', json_encode($_REQUEST));

        // Is there any errros that could be catched here?

        $result = array('ok'=>1, 'msg'=>__('Processing order... ','woo-vipps'), 'url'=>$this->buy_product_url());
        wp_send_json($result);
        exit();
    }

    public function ajax_do_express_checkout () {
        check_ajax_referer('do_express','sec');
        wc_nocache_headers();
        $gw = $this->gateway();

        if (!$gw->express_checkout_available() || !$gw->cart_supports_express_checkout()) {
            $result = array('ok'=>0, 'msg'=>__('Express checkout is not available for this order','woo-vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        }

        // Validate cart going forward using same logic as WC_Cart->check_cart() but not adding notices.
        $toolate = false;
        $msg = "";
        $valid  = WC()->cart->check_cart_item_validity();
        if ( is_wp_error( $valid) ) {
               $toolate = true;
               $msg = "<br>" .  $valid->get_error_message();
        }
        $stock = WC()->cart->check_cart_item_stock();
	if ( is_wp_error( $stock) ) {
		$toolate = true;
		$msg = "<br>" .  $stock->get_error_message();
	}

        if ($toolate) {
            $result = array('ok'=>0, 'msg'=>sprintf(__('Some of the products in your cart are no longer available in the quantities you have ordered. Please <a href="%s">edit your order</a> before continuing the checkout','woo-vipps'), wc_get_cart_url()) . $msg, 'url'=>false);
            wp_send_json($result);
            exit();
        }

        try {
            $orderid = $gw->create_partial_order();
            do_action('woo_vipps_ajax_do_express_checkout', $orderid);
        } catch (Exception $e) {
            $this->log($e->getMessage(),'error');
            $result = array('ok'=>0, 'msg'=>__('Could not create order','woo-vipps') . ': ' . $e->getMessage(), 'url'=>false);
            wp_send_json($result);
            exit();
        } 
        if (!$orderid) {
            $result = array('ok'=>0, 'msg'=>__('Could not create order','woo-vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        }

        try {
            $this->maybe_add_static_shipping($gw,$orderid); 
        } catch (Exception $e) {
                $this->log(__("Error calculating static shipping", 'woo-vipps'), 'error');
                $this->log($e->getMessage(),'error');
                $result = array('ok'=>0, 'msg'=>__('Could not create order','woo-vipps'), 'url'=>false);
                wp_send_json($result);
                exit();
        }
        
        $gw->express_checkout = 1;
        $ok = $gw->process_payment($orderid);
        if ($ok && $ok['result'] == 'success') {
            $result = array('ok'=>1, 'msg'=>'', 'url'=>$ok['redirect']);
            wp_send_json($result);
            exit();
        }
        $result = array('ok'=>0, 'msg'=> __('Vipps is temporarily unavailable.','woo-vipps'), 'url'=>'');
        wp_send_json($result);
        exit();
    }

    // Same as ajax_do_express_checkout, but for a single product/variation. Duplicate code because we want to manipulate the cart differently here. IOK 2018-09-25
    public function ajax_do_single_product_express_checkout() {
        check_ajax_referer('do_express','sec');
        wc_nocache_headers();
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        $gw = $this->gateway();

        if (!$gw->express_checkout_available()) {
            $result = array('ok'=>0, 'msg'=>__('Express checkout is not available for this order','woo-vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        }

        // Here we will either have a product-id, a variant-id and a product-id, or just a SKU. The product-id will not be a variant - but 
        // we'll double-check just in case. Also if we somehow *just* get a variant-id we should fix that too. But a SKU trumps all. IOK 2018-10-02
        $varid = intval(@$_POST['variation_id']);
        $prodid = intval(@$_POST['product_id']);
        $sku = sanitize_text_field(@$_POST['sku']);
        $quant = intval(@$_POST['quantity']);

        // Get any attributes posted for variable products (where one of the dimensions is "any" for instance)
        $variations = array();
        foreach ($_POST as $key => $value ) {
            if ( 'attribute_' !== substr( $key, 0, 10 ) ) {
                continue;
            }
            $variations[ sanitize_title( wp_unslash( $key ) ) ] = wp_unslash( $value );
        }

        $product = null;
        $variant = null;
        $parent = null;
        $parentid = null;
        $quantity = 1;
        if ($quant && $quant>1) $quantity=$quant;

        // Find the product, or variation, and get everything in order so we can check existence, availability etc. IOK 2018-10-02
        // Moved rules around as the _sku variant broke in 3.6.1 for stores that didn't bother to update the database IOK 2019-04-24
        // This broke single-product purchases for variable products; fixed IOK 2019-05-21 thanks to Gaute Terland Nilsen @ Easyweb for the report
        try {
            if ($varid) {
                $product = wc_get_product($varid);
            } elseif ($prodid) {
                $product = wc_get_product($prodid);
            } elseif ($sku) {
                $skuid = wc_get_product_id_by_sku($sku);
                $product = wc_get_product($skuid);
            }
        } catch (Exception $e) {
            $result = array('ok'=>0, 'msg'=>__('Error finding product - cannot create order','woo-vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        }


        if (!$product) {
            $result = array('ok'=>0, 'msg'=>__('Unknown product, cannot create order','woo-vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        }

        $parentid = $product ? $product->get_parent_id() : null; // If the product is a variation, then the parent product is the parentid.
        $parent = $parentid ? wc_get_product($parentid) : null; 

        // This can't really happen, but if it did..
        if ($prodid && $parentid && ($prodid != $parentid)) {
            $result = array('ok'=>0, 'msg'=>__('Selected product variant is not available','woo-vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        }
        if (!$gw->product_supports_express_checkout($product)) {
            $result = array('ok'=>0, 'msg'=>__('Express checkout is not available for this order','woo-vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        }

        // Somebody addded the wrong SKU
        if ($product->get_type() == 'variable'){
            $result = array('ok'=>0, 'msg'=>__('Selected product variant is not available for purchase','woo-vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        } 
        // Final check of availability
        if (!$product->is_purchasable() || !$product->is_in_stock()) {
            $result = array('ok'=>0, 'msg'=>__('Your product is temporarily no longer available for purchase','woo-vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        }

        // Now it should be safe to continue to the checkout process. IOK 2018-10-02

        // Create a new temporary cart for this order. We need to get (and save) the real session cart,
        // because some plugins actually override this.
        $current_cart = clone WC()->cart;
        WC()->cart->empty_cart();

        if ($parent && $parent->get_type() == 'variable') {
            WC()->cart->add_to_cart($parent->get_id(),$quantity,$product->get_id(), $variations);
        } else {
            WC()->cart->add_to_cart($product->get_id(),$quantity);
        }

        try {
            $orderid = $gw->create_partial_order();
            do_action('woo_vipps_ajax_do_express_checkout', $orderid);
        } catch (Exception $e) {
            $result = array('ok'=>0, 'msg'=>__('Could not create order','woo-vipps') . ': ' . $e->getMessage(), 'url'=>false);
            wp_send_json($result);
            exit();
        } 

        if (!$orderid) {
            $result = array('ok'=>0, 'msg'=>__('Could not create order','woo-vipps'), 'url'=>false);
            wp_send_json($result);
            exit();
        }

        try {
            $this->maybe_add_static_shipping($gw,$orderid);
        } catch (Exception $e) {
                $this->log(__("Error calculating static shipping", 'woo-vipps'), 'error');
                $this->log($e->getMessage(),'error');
                $result = array('ok'=>0, 'msg'=>__('Could not create order','woo-vipps'), 'url'=>false);
                wp_send_json($result);
                exit();
       }


        // Single product purchase, so save any contents of the real cart
        $order = wc_get_order($orderid);
        $order->update_meta_data('_vipps_single_product_express',true);
        $order->save();
        $this->save_cart($order,$current_cart);

        $gw->express_checkout = 1;
        $ok = $gw->process_payment($orderid);
        if ($ok && $ok['result'] == 'success') {
            $result = array('ok'=>1, 'msg'=>'', 'url'=>$ok['redirect']);
            wp_send_json($result);
            exit();
        }
        $result = array('ok'=>0, 'msg'=> __('Vipps is temporarily unavailable.','woo-vipps'), 'url'=>'');
        wp_send_json($result);
        exit();
    }

    // This calculates and adds static shipping info to a partial order for express checkout if merchant has enabled this. IOK 2020-03-19
    // Made visible for consistency with add_static_shipping. IOK 2021-10-22
    public function maybe_add_static_shipping($gw, $orderid, $ischeckout=false) {
        $key = $ischeckout ? 'enablestaticshipping_checkout' :  'enablestaticshipping';
        $ok = $gw->get_option($key) == 'yes';
        $ok = apply_filters('woo_vipps_enable_static_shipping', $ok, $orderid); 
        if ($ok) {
            return $this->add_static_shipping($gw, $orderid);
        }
    }

    // And this function adds static shipping no matter what. It may need to be used in plugins, hence visible. IOK 2021-10-22
    public function add_static_shipping ($gw, $orderid) {
        $order = wc_get_order($orderid);
        $prefix  = $gw->get_orderprefix();
        $vippsorderid =  apply_filters('woo_vipps_orderid', $prefix.$orderid, $prefix, $order);
        $addressinfo = $this->get_static_shipping_address_data();
        $options = $this->vipps_shipping_details_callback_handler($order, $addressinfo,$vippsorderid);

        if ($options) {
            $order->update_meta_data('_vipps_static_shipping', $options);
            $order->save();
        }
    }
    

    // Check the status of the order if it is a part of our session, and return a result to the handler function IOK 2018-05-04
    public function ajax_check_order_status () {
        check_ajax_referer('vippsstatus','sec');
        wc_nocache_headers();

        $orderid= wc_get_order_id_by_order_key(sanitize_text_field(@$_POST['key']));
        $transaction = sanitize_text_field(@$_POST['transaction']);

        $sessionorders= WC()->session->get('_vipps_session_orders');
        if (!isset($sessionorders[$orderid])) {
            wp_send_json(array('status'=>'error', 'msg'=>__('Not an order','woo-vipps')));
        }

        $order = wc_get_order($orderid); 
        if (!$order) {
            wp_send_json(array('status'=>'error', 'msg'=>__('Not an order','woo-vipps')));
        }
        $order_status = $this->check_order_status($order);
        // No callback has occured yet. If this has been going on for a while, check directly with Vipps
        if ($order_status == 'pending') {
            wp_send_json(array('status'=>'waiting', 'msg'=>__('Waiting on order', 'woo-vipps')));
            return false;
        }
        if ($order_status == 'cancelled') {
            $this->maybe_restore_cart($orderid,'failed');
            wp_send_json(array('status'=>'failed', 'msg'=>__('Order failed', 'woo-vipps')));
            return false;
        }

        // Order status isn't pending anymore, but there can be custom statuses, so check the payment status instead.
        $order = wc_get_order($orderid); // Reload
        $gw = $this->gateway();
        $payment = $gw->check_payment_status($order);
        if ($payment == 'initiated') {
            wp_send_json(array('status'=>'waiting', 'msg'=>__('Waiting on order', 'woo-vipps')));
            return false;
        }
        if ($payment == 'authorized') {
            wp_send_json(array('status'=>'ok', 'msg'=>__('Payment authorized', 'woo-vipps')));
            return false;
        }
        if ($payment == 'complete') {
            wp_send_json(array('status'=>'ok', 'msg'=>__('Payment captured', 'woo-vipps')));
            return false;
        }
        if ($payment == 'cancelled') {
            $this->maybe_restore_cart($orderid,'failed');
            wp_send_json(array('status'=>'failed', 'msg'=>__('Order failed', 'woo-vipps')));
            return false;
        }
        wp_send_json(array('status'=>'error', 'msg'=> __('Unknown payment status','woo-vipps') . ' ' . $payment));
        return false;
    }

    // The various return URLs for special pages of the Vipps stuff depend on settings and pretty-URLs so we supply them from here
    // These are for the "fallback URL" mostly. IOK 2018-05-18
    private function make_return_url($what) {
        if ( !get_option('permalink_structure')) {
            return add_query_arg('VippsSpecialPage', $what, home_url("/", 'https'));
        }
        return trailingslashit(home_url($what, 'https'));
    }
    public function payment_return_url() {
        return apply_filters('woo_vipps_payment_return_url', $this->make_return_url('vipps-betaling')); 
    }
    public function express_checkout_url() {
        return $this->make_return_url('vipps-express-checkout');
    }
    public function buy_product_url() {
        return $this->make_return_url('vipps-buy-product');
    }

    // Return the method in the Vipps
    public function is_special_page() {
        $specials = array('vipps-betaling' => 'vipps_wait_for_payment', 'vipps-express-checkout'=>'vipps_express_checkout', 'vipps-buy-product'=>'vipps_buy_product');
        $method = null;
        if ( get_option('permalink_structure')) {
            foreach($specials as $special=>$specialmethod) {
                // IOK 2018-06-07 Change to add any prefix from home-url for better matching IOK 2018-06-07
                $path =  parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                if ($path && preg_match("!/$special/?$!", $path, $matches)) {
                    $method = $specialmethod; break;
                }
            }
        } else {
            if (isset($_GET['VippsSpecialPage'])) {
                $method = @$specials[$_GET['VippsSpecialPage']];
            }
        }
        return $method;
    }

    // Just create a spinner and a overlay.
    public function spinner () {
        ob_start();
        ?>
            <div class="vippsoverlay">
            <div id="floatingCirclesG" class="vippsspinner">
            <div class="f_circleG" id="frotateG_01"></div>
            <div class="f_circleG" id="frotateG_02"></div>
            <div class="f_circleG" id="frotateG_03"></div>
            <div class="f_circleG" id="frotateG_04"></div>
            <div class="f_circleG" id="frotateG_05"></div>
            <div class="f_circleG" id="frotateG_06"></div>
            <div class="f_circleG" id="frotateG_07"></div>
            <div class="f_circleG" id="frotateG_08"></div>
            </div>
            </div>
            <?php
            return apply_filters('woo_vipps_spinner', ob_get_clean());
    }

    // Code that will generate various versions of the 'buy now with Vipps' button IOK 2018-09-27
    public function get_buy_now_button($product_id,$variation_id=null,$sku=null,$disabled=false, $classes='') {
        $disabled = $disabled ? 'disabled' : '';
        $data = array();
        if ($sku) $data['product_sku'] = $sku;
        if ($product_id) $data['product_id'] = $product_id;
        if ($variation_id) $data['variation_id'] = $variation_id;

        $buttoncode = "<a href='javascript:void(0)' $disabled ";
        foreach($data as $key=>$value) {
            $value = sanitize_text_field($value);
            $buttoncode .= " data-$key='$value' ";
        }
        $buynow = __('Buy now with', 'woo-vipps');
        $title = __('Buy now with Vipps', 'woo-vipps');
        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $message = "<span class='vippsbuynow'>" . $buynow . "</span>" . " <img class='inline vipps-logo negative' border=0 src='$logo' alt='Vipps'/>";

# Extra classes, if passed IOK 2019-02-26
        if (is_array($classes)) {
            $classes = join(" ", $classes);
        }
        if ($classes) $classes = " $classes";

        $buttoncode .=  " class='single-product button vipps-buy-now $disabled$classes' title='$title'>$message</a>";
        return apply_filters('woo_vipps_buy_now_button', $buttoncode, $product_id, $variation_id, $sku, $disabled);
    }

    // Display a 'buy now with express checkout' button on the product page IOK 2018-09-27
    public function single_product_buy_now_button () {
        $gw = $this->gateway();
        $how = $gw->get_option('singleproductexpress');
        if ($how == 'none') return;
        if (!$gw->express_checkout_available()) return;

        global $product;
        $prodid = $product->get_id();
        if (!$gw->product_supports_express_checkout($product)) return;

        // Vipps does not support 0,- products, so we need to check.
        // get_price() should normally return the lowest price for variable products, but that can fail, 
        // so we dispatch on the type and use the *minimum* price instead, requiring that to be nonzero. IOK 2022-06-08
        $showit = true;
        if (is_a($product, 'WC_Product_Variable')) {
            $minprice = $product->get_variation_price('min', 0);
            if ($minprice > 0) $showit = true;
        } else {
            if ($product->get_price() <= 0)  $showit = false; 
        }

        if ( $how=='some' && 'yes' != get_post_meta($prodid,  '_vipps_buy_now_button', true)) $showit = false;
        $showit = apply_filters('woo_vipps_show_single_product_buy_now', $showit, $product);
        if (!$showit) return;

        $disabled="";
        if ($product->is_type('variable')) {
            $disabled="disabled";
        }

# If true, add a class that signals that the button should be added in 'compat mode', which is compatible with
# more plugins because it does not handle tha product add itself. IOK 2019-02-26
        $compat = ($gw->get_option('singleproductbuynowcompatmode') == 'yes');
        $compat = apply_filters('woo_vipps_single_product_compat_mode', $compat, $product);

        $classes = array();
        if ($compat) $classes[] ='compat-mode';
        $classes = apply_filters('woo_vipps_single_product_buy_now_classes', $classes, $product);

        echo $this->get_buy_now_button(false,false,false, ($product->is_type('variable') ? 'disabled' : false), $classes);
    }


    // True for products that are purchasable using Vipps Express Checkout
    public function loop_single_product_is_express_checkout_purchasable($product) {
        if (!$product) return false;
        if (!$product->is_purchasable() || !$product->is_in_stock() || !$product->supports( 'ajax_add_to_cart' )) return false;
        $gw = $this->gateway();

        if (!$gw->express_checkout_available()) return false;
        if (!$gw->product_supports_express_checkout($product)) return false;
        if ($gw->get_option('singleproductexpressarchives') != 'yes') return false;

        $how = $gw->get_option('singleproductexpress');
        if ($how == 'none') return false;
        $prodid = $product->get_id();

        $showit = true;
        if ($product->get_price() <= 0)  $showit = false;
        if ( $how=='some' && 'yes' != get_post_meta($prodid,  '_vipps_buy_now_button', true)) $showit = false;
        $showit = apply_filters('woo_vipps_show_single_product_buy_now', $showit, $product);
        $showit = apply_filters('woo_vipps_show_single_product_buy_now_in_loop', $showit, $product);
        return $showit;
    }

    // Print a "buy now with vipps" for products in the loop, like on a category page
    public function loop_single_product_buy_now_button() {
        global $product;

        if (!$this->loop_single_product_is_express_checkout_purchasable($product)) return;
       
        $sku = $product->get_sku();

        echo $this->get_buy_now_button($product->get_id(),false,$sku);
    }



    // Vipps Checkout replaces the default checkout page, and currently uses its own  page for this which needs to exist
    public function woocommerce_create_pages ($data) {
        $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);
        if (!$vipps_checkout_activated) return $data;

        $data['vipps_checkout'] = array(
                'name'    => _x( 'vipps_checkout', 'Page slug', 'woo-vipps' ),
                'title'   => _x( 'Vipps Checkout', 'Page title', 'woo-vipps' ),
                'content' => '<!-- wp:shortcode -->[' . 'vipps_checkout' . ']<!-- /wp:shortcode -->',
                );

        return $data;
    }

    public function woocommerce_settings_pages ($settings) {
        $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);
        if (!$vipps_checkout_activated) return $settings;
        $i = -1;
        foreach($settings as $entry) {
            $i++;
            if ($entry['type'] == 'sectionend' && $entry['id'] == 'advanced_page_options') {
                break;
            }
        }
        if ($i > 0) {

            $vippspagesettings = array(
                    array(
                        'title'    => __( 'Vipps Checkout Page', 'woo-vipps' ),
                        'desc'     => __('This page is used for the alternative Vipps Checkout page, which you can choose to use instead of the normal WooCommerce checkout page. ', 'woo-vipps') .  sprintf( __( 'Page contents: [%s]', 'woocommerce' ), 'vipps_checkout') ,
                        'id'       => 'woocommerce_vipps_checkout_page_id',
                        'type'     => 'single_select_page_with_search',
                        'default'  => '',
                        'class'    => 'wc-page-search',
                        'css'      => 'min-width:300px;',
                        'args'     => array(
                            'exclude' =>
                            array(
                                wc_get_page_id( 'myaccount' ),
                                ),
                            ),
                        'desc_tip' => true,
                        'autoload' => false,
                        ));
            array_splice($settings, $i, 0, $vippspagesettings);
        }

        return $settings;
    }


    // This URL will when accessed add a product to the cart and go directly to the express  checkout page.
    // The argument passed must be a shareable link created for a given product - so this in effect acts as a landing page for 
    // the buying thru Vipps Express Checkout of a single product linked to in for instance banners. IOK 2018-09-24
    public function vipps_buy_product() {
        status_header(200,'OK');
        wc_nocache_headers();

        add_filter('body_class', function ($classes) {
            $classes[] = 'vipps-express-checkout';
            return $classes;
        });

        do_action('woo_vipps_express_checkout_page');

        $session = WC()->session;
        $posted = $session->get('__vipps_buy_product');
        $session->set('__vipps_buy_product', false); // Reloads won't work but that's ok.


        if (!$posted) {
            // Find product/variation using an external shareable link
            if (array_key_exists('pr',$_REQUEST)) {
                global $wpdb;
                $externalkey = sanitize_text_field($_REQUEST['pr']);
                $search = '_vipps_shareable_link_'.esc_sql($externalkey);
                $existing =  $wpdb->get_row("SELECT post_id from {$wpdb->prefix}postmeta where meta_key='$search' limit 1",'ARRAY_A');
                if (!empty($existing)) {
                    $posted = get_post_meta($existing['post_id'], $search, true);
                }
            }
        }

        $productinfo = false;
        if (is_array($posted)) {
            $productinfo = $posted;
        } else {
            $productinfo = $posted ? @json_decode($posted,true) : false; 
        }

        if (!$productinfo) {
            $title = __("Product is no longer available",'woo-vipps');
            $content =  __("The link you have followed is for a product that is no longer available at this location. Please return to the store and try again",'woo-vipps');
            return $this->fakepage($title,$content);
        }

        // Pass the productinfo to the express checkout form
        $args = array();
        $args['quantity'] = 1;
        if (array_key_exists('product_id',$productinfo)) $args['product_id'] = intval($productinfo['product_id']);
        if (array_key_exists('variation_id',$productinfo)) $args['variation_id'] = intval($productinfo['variation_id']);
        if (array_key_exists('product_sku',$productinfo)) $args['sku'] = sanitize_text_field($productinfo['product_sku']);
        if (array_key_exists('quantity',$productinfo)) $args['quantity'] = intval($productinfo['quantity']);

        // For variable products where some of the attributes are "any", we need to add these as well. This is from woos form-handler for these.
        foreach ($productinfo as $key => $value) {
            if ( 'attribute_' !== substr( $key, 0, 10 ) ) {
                continue;
            }
            $args[sanitize_title(wp_unslash($key))] = sanitize_text_field(wp_unslash($value));
        }




        $this->print_express_checkout_page(true,'do_single_product_express_checkout',$args);
    }

    //  This is a landing page for the express checkout of then normal cart - it is done like this because this could take time on slower hosts.
    public function vipps_express_checkout() {
        status_header(200,'OK');
        wc_nocache_headers();
        // We need a nonce to get here, but we should only get here when we have a cart, so this will not be cached.
        // IOK 2018-05-28
        $ok = wp_verify_nonce($_REQUEST['sec'],'express');

        add_filter('body_class', function ($classes) {
            $classes[] = 'vipps-express-checkout';
            return $classes;
        });

        $backurl = wp_validate_redirect(@$_SERVER['HTTP_REFERER']);
        if (!$backurl) $backurl = home_url();

        if ( WC()->cart->get_cart_contents_count() == 0 ) {
            wc_add_notice(__('Your shopping cart is empty','woo-vipps'),'error');
            wp_redirect($backurl);
            exit();
        }

        do_action('woo_vipps_express_checkout_page');


        $this->print_express_checkout_page($ok,'do_express_checkout');
    }

    // This method tries to ensure that a customer does not 'lose' the return page and
    // starts ordering the same products twice. IOK 2020-01-22
    protected function validate_express_checkout_orderspec ($orderspec) {
        if (empty($orderspec)) return true; // It's not a duplicate, it's nothing.

        // First build for the current order an array of hash-tables keyed by prodid, varid and quantity. 
        $orderset = array();
        foreach($orderspec as $entry) $orderset[] = join(':', $entry);

        // Then get open orders
        $sessionorders = array();
        $sessionorderdata = WC()->session->get('_vipps_session_orders');
        if ($sessionorderdata) {
            foreach(array_keys($sessionorderdata) as $oid) {
                $orderobject = wc_get_order($oid);
                // Check to see that this hasn't been deleted yet IOK 2020-01-07
                if ($orderobject instanceof WC_Order) {
                   $sessionorders[] = $orderobject;
                }
            }
        }
        // Nothing more to do here
        if (empty($sessionorders)) return true;

        // And create a similar hash table for each of the open orders
        $openorderdata = array();
        foreach ($sessionorders as $open_order) {
            $status = $open_order->get_status();
            if ($status == 'cancelled' || $status == 'pending') continue;
            $when = strtotime($open_order->get_date_modified());
            $cutoff = $when + apply_filters('woo_vipps_recent_order_cutoff', (5*60));
            if (time() > $cutoff) {
                continue;
            }
            $orderdata = array(); 
            foreach($open_order->get_items() as $item) {
                $productspec = $item->get_product_id() . ':' . $item->get_variation_id() . ':' . $item->get_quantity();
                $orderdata[] = $productspec;
            }
            $openorderdata[]=$orderdata;
        }

        // Now: For each entry in the orderhash, check if there is an order that has a) all of them and b) not any more of them.
        foreach($openorderdata as $prevorder) {
            $a = array_diff($prevorder, $orderset);
            $b  = array_diff($orderset, $prevorder);
            if (empty($a) && empty($b)) { 
                $this->log(__("It seems a customer is trying to re-order product(s) recently bought in the same session, asking user for confirmation", 'woo-vipps'), 'info');
                return false; 
            }
        }
        // Else, order is good.
        return true;
    }

    // Returns a triple of productid, variantid and quantity from an array of arguments which can pass either these or a SKU value.  
    // Return value is like in a cart.
    // Used to create an order in express checkout, and to see that this order isn't a repeat. IOK 2020-01-22
    protected function get_orderspec_from_arguments ($productinfo) {
        if (!$productinfo) return array();
        $variantid = 0;
        $productid = 0;
        $quantity = intval(@$productinfo['quantity']);
        if (!$quantity) $quantity = 1;
        if (isset($productinfo['sku']) && $productinfo['sku']) {
            $sku = $productinfo['sku'];
            $skuid = wc_get_product_id_by_sku($sku);
            $product = wc_get_product($skuid);
            $parentid = $product ? $product->get_parent_id() : null;
            if ($product) {
                if ($parentid) {   
                    $variantid = $skuid; $productid = $parentid;
                } else {
                    $productid = $skuid;
                }
            }
        } else if (isset($productinfo['product_id']) && $productinfo['product_id']) {
            $productid = intval($productinfo['product_id']);
            $variantid = intval(@$productinfo['variation_id']);
        }
        if ($productid) return array(array('product_id'=>$productid, 'variation_id'=>$variantid, 'quantity'=>$quantity));
        return array();
    }
    // If no productinfo, this will produce an orderspec from the current cart IOK 2020-01-24
    protected function get_orderspec_from_cart () {
        $cartitems = WC()->cart->get_cart();
        $orderspec = array();
        foreach($cartitems as $item => $values) {
            $orderspec[] = array('product_id'=>$values['product_id'], 'variation_id'=>$values['variation_id'], 'quantity'=>$values['quantity']);
        }
        return $orderspec;
    }

    // Used as a landing page for launching express checkout - borh for the cart and for single products. IOK 2018-09-28
    protected function print_express_checkout_page($execute,$action,$productinfo=null) {
        $gw = $this->gateway();

        $expressCheckoutMessages = array();
        $expressCheckoutMessages['termsAndConditionsError'] = __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce' );
        $expressCheckoutMessages['temporaryError'] = __('Vipps is temporarily unavailable.','woo-vipps');
        $expressCheckoutMessages['successMessage'] = __('To the Vipps app!','woo-vipps');

        wp_register_script('vipps-express-checkout',plugins_url('js/express-checkout.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/express-checkout.js"), 'true');
        wp_localize_script('vipps-express-checkout', 'VippsCheckoutMessages', $expressCheckoutMessages);
        wp_enqueue_script('vipps-express-checkout');
        // If we have a valid nonce when we get here, just call the 'create order' bit at once. Otherwise, make a button
        // to actually perform the express checkout.
        $buttonimgurl= apply_filters('woo_vipps_express_checkout_button', plugins_url('img/pay-with-vipps.svg',__FILE__));


        $orderspec = $this->get_orderspec_from_arguments($productinfo);
        if (empty($orderspec)) { 
            $orderspec = $this->get_orderspec_from_cart();
        }
        $orderisOK = $this->validate_express_checkout_orderspec($orderspec);
        $orderisOK = apply_filters('woo_vipps_validate_express_checkout_orderspec', $orderisOK, $orderspec);

        $askForTerms = wc_terms_and_conditions_checkbox_enabled();
        $askForTerms = $askForTerms && ($gw->get_option('expresscheckout_termscheckbox') == 'yes');
        $askForTerms = apply_filters('woo_vipps_express_checkout_terms_and_conditions_checkbox_enabled', $askForTerms);

        $askForConfirmationHTML = '';
        if (!$orderisOK) {
            $header = __("Are you sure?",'woo-vipps');
            $body = __("You recently completed an order with exactly the same products as you are buying now. There should be an email in your inbox from the previous purchase. Are you sure you want to order again?",'woo-vipps');
            $askForConfirmationHTML = apply_filters('woo_vipps_ask_user_to_confirm_repurchase', "<h2 class='confirmVippsExpressCheckoutHeader'>$header</h2><p>$body</p>");
        }
        // Should we go directly to checkout, or do we need to stop and ask the user something (for instance?) IOK 2010-01-20
        $execute = $execute && $orderisOK && !$askForTerms;
        $execute = apply_filters('woo_vipps_checkout_directly_to_vipps', $execute, $productinfo);

        $content = $this->spinner();

        $content .= "<form id='vippsdata'>";
        $content .= "<input type='hidden' name='action' value='$action'>";
        $content .= wp_nonce_field('do_express','sec',1,false); 

        $termsHTML = '';
        if ($askForTerms) {
            // Include shop terms 
           ob_start();
           wc_get_template('checkout/terms.php');
           $termsHTML = ob_get_clean();
           $termsHTML = apply_filters('woo_vipps_express_checkout_terms_and_conditions_html',$termsHTML);
        }
        $termsHTML = apply_filters('woo_vipps_express_checkout_terms_and_conditions_html',$termsHTML);

        if ($productinfo) {
            foreach($productinfo as $key=>$value) {
                $k = sanitize_text_field($key);
                $v = sanitize_text_field($value);
                $content .= "<input type='hidden' name='$k' value='$v' />";
            }
        }
        ob_start();
        $content .= do_action('woo_vipps_express_checkout_orderspec_form', $productinfo);
        $content .= ob_get_clean();
        $content .= "</form>";

        $extraHTML = apply_filters('woo_vipps_express_checkout_final_html', '', $termsHTML,$askForConfirmationHTML);
        $pressTheButtonHTML =  "";
        if (empty($termsHTML) && empty($askForConfirmationHTML) && empty($extraHTML)) {
            $pressTheButtonHTML =  "<p id=waiting>" . __("Ready for express checkout - press the button", 'woo-vipps') . "</p>";
        }

        if ($execute) {
            $content .= "<p id=waiting>" . __("Please wait while we are preparing your order", 'woo-vipps') . "</p>";
            $content .= "<div id='vipps-status-message'></div>";
            $this->fakepage(__('Order in progress','woo-vipps'), $content);
            return;
        } else {
            $content .= $askForConfirmationHTML;
            $content .= $extraHTML;
            $content .= $termsHTML;
            $content .= apply_filters('woo_vipps_express_checkout_validation_elements', '');
            $imgurl= apply_filters('woo_vipps_express_checkout_button', plugins_url('img/pay-with-vipps.svg',__FILE__));
            $title = __('Buy now with Vipps!', 'woo-vipps');
            $content .= "<p><a href='#' id='do-express-checkout' class='button vipps-express-checkout' title='$title'><img alt='$title' border=0 src='$buttonimgurl'></a>";
            $content .= "<div id='vipps-status-message'></div>";
            $this->fakepage(__('Vipps Express Checkout','woo-vipps'), $content);
            return;
        }
    }



    public function vipps_wait_for_payment() {
        status_header(200,'OK');
        wc_nocache_headers();

        $orderid = WC()->session->get('_vipps_pending_order');
        $order = null;
        $gw = $this->gateway();

        // Failsafe for when the session disappears IOK 2018-11-19
        $authtoken = sanitize_text_field(@$_GET['t']);

        // Now we *should* have a session at this point, but the session may have been deleted, or the session may be in another browser,
        // because we get here by the Vipps app opening the app. If so, we use a 'fake' session stored with the transient API and restore this session
        // so we can reload the screen, but don't have to worry about leaking stuff
        // IOK 2019-11-19
        if (!$orderid) {
            if ($authtoken) {
                $orderid = get_transient('_vipps_pending_order_'.$authtoken);
                if ($orderid) {
                    $session = WC()->session;
                    if (!$session->has_session()) {
                        $session->set_customer_session_cookie(true);
                    }
                    $session->set('_vipps_pending_order', $orderid);
                }
            }
        }
        delete_transient('_vipps_pending_order_'.$authtoken); 

        if ($orderid) {
            clean_post_cache($orderid);
            $order = wc_get_order($orderid); 
        }
        do_action('woo_vipps_wait_for_payment_page',$order);

        $deleted_order=0;
        if ($orderid && !$order) {
            // If this happens, we actually did have an order, but it has been deleted, which must mean that it was cancelled.
            // Concievably a hook on the 'cancel'-transition or in the callback handlers could clean that up before we get here. IOK 2019-09-26
            $deleted_order=1;
        }

        if (!$order && !$deleted_order) wp_die(__('Unknown order', 'woo-vipps'));

        // If we are done, we are done, so go directly to the end. IOK 2018-05-16
        $status = $deleted_order ? 'cancelled' : $order->get_status();

        // Still pending, no callback. Make a call to the server as the order might not have been created. IOK 2018-05-16
        if ($status == 'pending') {
            // Just in case the callback hasn't come yet, do a quick check of the order status at Vipps.
            $newstatus = $gw->callback_check_order_status($order);
            if ($status != $newstatus) {
                $status = $newstatus;
                clean_post_cache($orderid);
                $order = wc_get_order($orderid); // Reload order object
            }
        } else {
                // No need to do anyting here. IOK 2020-01-26
        }

        $payment = $deleted_order ? 'cancelled' : $gw->check_payment_status($order);

        // All these payment statuses are successes so go to the thankyou page. 
        if ($payment == 'authorized' || $payment == 'complete') {
            wp_redirect($gw->get_return_url($order));
            exit();
        }

        $content = "";
        $failure_redirect = apply_filters('woo_vipps_order_failed_redirect', '', $orderid);

        // We are done, but in failure. Don't poll.
        if ($status == 'cancelled' || $payment == 'cancelled') {
            $this->maybe_restore_cart($orderid,'failed');
            if ($failure_redirect){
                wp_redirect($failure_redirect);
                exit();
            }
            $content .= "<div id=failure><p>". __('Order cancelled','woo-vipps') . '</p>';
            $content .= "<p><a href='" . home_url() . "' class='btn button'>" . __('Continue shopping','woo-vipps') . '</a></p>';
            $content .= "</div>";
            $this->fakepage(__('Order cancelled','woo-vipps'), $content);

            return;
        }

        // Still pending and order is supposed to exist, so wait for Vipps. This happens all the time, so logging is removed. IOK 2018-09-27

        // Otherwise, go to a page waiting/polling for the callback. IOK 2018-05-16
        wp_enqueue_script('check-vipps',plugins_url('js/check-order-status.js',__FILE__),array('jquery','vipps-gw'),filemtime(dirname(__FILE__) . "/js/check-order-status.js"), 'true');

        // Check that order exists and belongs to our session. Can use WC()->session->get() I guess - set the orderid or a hash value in the session
        // and check that the order matches (and is 'pending') (and exists)
        $vippsstamp = $order->get_meta('_vipps_init_timestamp');
        $vippsstatus = $order->get_meta('_vipps_status');
        $message = __($order->get_meta('_vipps_confirm_message'),'woo-vipps');

        $signal = $this->callbackSignal($order);
        $content = "";
        $content .= "<div id='waiting'><p>" . __('Waiting for confirmation of purchase from Vipps','woo-vipps');

        if ($signal && !is_file($signal)) $signal = '';
        $signalurl = $this->callbackSignalURL($signal);

        $content .= "</p></div>";

        $content .= "<form id='vippsdata'>";
        $content .= "<input type='hidden' id='fkey' name='fkey' value='".htmlspecialchars($signalurl)."'>";
        $content .= "<input type='hidden' name='key' value='".htmlspecialchars($order->get_order_key())."'>";
        $content .= "<input type='hidden' name='action' value='check_order_status'>";
        $content .= wp_nonce_field('vippsstatus','sec',1,false); 
        $content .= "</form>";


        $content .= "<div id='error' style='display:none'><p>".__('Error during order confirmation','woo-vipps'). '</p>';
        $content .= "<p>" . __('An error occured during order confirmation. The error has been logged. Please contact us to determine the status of your order', 'woo-vipps') . "</p>";
        $content .= "<p><a href='" . home_url() . "' class='btn button'>" . __('Continue shopping','woo-vipps') . '</a></p>';
        $content .= "</div>";

        $content .= "<div id=success style='display:none'><p>". __('Order confirmed', 'woo-vipps') . '</p>';
        $content .= "<p><a class='btn button' id='continueToThankYou' href='" . $gw->get_return_url($order)  . "'>".__('Continue','woo-vipps') ."</a></p>";
        $content .= '</div>';

        $content .= "<div id=failure style='display:none'><p>". __('Order cancelled', 'woo-vipps') . '</p>';
        $content .= "<p><a href='" . home_url() . "' class='btn button'>" . __('Continue shopping','woo-vipps') . '</a></p>';
        $content .= "<a id='continueToOrderFailed' style='display:none' href='$failure_redirect'></a>";
        $content .= "</div>";


        $this->fakepage(__('Waiting for your order confirmation','woo-vipps'), $content);
    }



    public function fakepage($title,$content) {
        global $wp, $wp_query;
        // We don't want this here.
        remove_filter ('the_content', 'wpautop'); 

        $specialid = $this->gateway()->get_option('vippsspecialpageid');
        $wp_post = null;
        if ($specialid) {
          $wp_post = get_post($specialid);
          $wp_post->post_title = $title;
          $wp_post->post_content = $content;
          // Normalize a bit
          $wp_post->filter = 'raw'; // important
          $wp_post->post_status = 'publish';
          $wp_post->comment_status= 'closed';
          $wp_post->ping_status= 'closed';
	    }
        if (!$wp_post || is_wp_error($wp_post)) {
            $post = new stdClass();
            $post->ID = -99;
            $post->post_author = 1;
            $post->post_date = current_time( 'mysql' );
            $post->post_date_gmt = current_time( 'mysql', 1 );
            $post->post_title = $title;
            $post->post_content = $content;
            $post->post_status = 'publish';
            $post->comment_status = 'closed';
            $post->ping_status = 'closed';
            $post->post_name = 'vippsconfirm-fake-page-name';
            $post->post_type = 'page';
            $post->filter = 'raw'; // important
            $wp_post = new WP_Post($post);
            wp_cache_add( -99, $wp_post, 'posts' );
        }
 
        // Update the main query
        $wp_query->post = $wp_post;
        $wp_query->posts = array( $wp_post );
        $wp_query->queried_object = $wp_post;
        $wp_query->queried_object_id = $wp_post->ID;
        $wp_query->found_posts = 1;
        $wp_query->post_count = 1;
        $wp_query->max_num_pages = 1; 
        $wp_query->is_page = true;
        $wp_query->is_singular = true; 
        $wp_query->is_single = false; 
        $wp_query->is_attachment = false;
        $wp_query->is_archive = false; 
        $wp_query->is_category = false;
        $wp_query->is_tag = false; 
        $wp_query->is_tax = false;
        $wp_query->is_author = false;
        $wp_query->is_date = false;
        $wp_query->is_year = false;
        $wp_query->is_month = false;
        $wp_query->is_day = false;
        $wp_query->is_time = false;
        $wp_query->is_search = false;
        $wp_query->is_feed = false;
        $wp_query->is_comment_feed = false;
        $wp_query->is_trackback = false;
        $wp_query->is_home = false;
        $wp_query->is_embed = false;
        $wp_query->is_404 = false; 
        $wp_query->is_paged = false;
        $wp_query->is_admin = false; 
        $wp_query->is_preview = false; 
        $wp_query->is_robots = false; 
        $wp_query->is_posts_page = false;
        $wp_query->is_post_type_archive = false;
        // Update globals
        $GLOBALS['wp_query'] = $wp_query;
        $wp->register_globals();
        return $wp_post;
    }

}
