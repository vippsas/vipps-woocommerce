<?php
/*
   This class is for hooks and plugin managent, and is instantiated as a singleton and set globally as $Vipps. IOK 2018-02-07
   For WP-specific interactions.


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
require_once(dirname(__FILE__) . "/VippsAPIException.class.php");

class Vipps {
    private static $instance = null;

    /* Used to interact with other payment gateways if neccessary (for 'external payment gateways') IOK 2024-05-27 */
    public static $installed_gateways = [];

    /* This directory stores the files used to speed up the callbacks checking the order status. IOK 2018-05-04 */
    private $callbackDirname = 'wc-vipps-status';
    private $countrymap = null;
    // Used to provide the order in a callback to the session handler etc. IOK 2019-10-21
    public $callbackorder = 0;

    // True if HPOS is being used
    public $HPOSActive = null;

    // used in the fake locking mechanism using transients
    private $lockKey = null; 

    public $vippsJSConfig = array();

    // IOK 2023-11-29 Vipps merging with MobilePay causes some challenges which we solve by abstraction
    public static function CompanyName() { 
        return __("Vipps MobilePay", 'woo-vipps');
    }
    public static function CheckoutName($order=null) {
        return "Vipps MobilePay Checkout"; // Do not translate
    }
    public static function ExpressCheckoutName($order=null) {
        return __("Vipps Express Checkout", 'woo-vipps');
    }
    public static function LoginName() {
        return __("Login with Vipps", 'woo-vipps');
   }

    public static function instance()  {
        if (!static::$instance) static::$instance = new Vipps();
        return static::$instance;
    }

    // To simplify development, we load translations from the plugins' own .mos on development branches. IOK 2023-11-28
    public static function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
        $development = apply_filters('woo_vipps_use_plugin_translations', false);
        if (!$development) {
           return load_plugin_textdomain($domain, $deprecated, $plugin_rel_path);
        }
        // Available since 6.1.0 only IOK 2023-01-25
        global $wp_textdomain_registry;
        if ($wp_textdomain_registry) {
            $locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
            $mofile = $domain . '-' . $locale . '.mo';
            $path = WP_PLUGIN_DIR . '/' . trim( $plugin_rel_path, '/' );
            $wp_textdomain_registry->set_custom_path( $domain, $path );
            return load_textdomain( $domain, $path . '/' . $mofile, $locale );
        }
    }

    public static function register_hooks() {
        $Vipps = static::instance();
        register_activation_hook(WC_VIPPS_MAIN_FILE, array($Vipps,'activate'));
        register_deactivation_hook(WC_VIPPS_MAIN_FILE,array('Vipps','deactivate'));
        if (is_admin()) {
            add_action('admin_init',array($Vipps,'admin_init'));
            add_action('admin_menu',array($Vipps,'admin_menu'));
        } else {
            add_action('wp_footer', array($Vipps,'footer'));
        }
        add_action( 'plugins_loaded', array($Vipps,'plugins_loaded'));
        add_action( 'after_setup_theme', array($Vipps,'after_setup_theme'));
        add_action('init',array($Vipps,'init'));
        add_action( 'woocommerce_loaded', array($Vipps,'woocommerce_loaded'));
        add_filter( 'woocommerce_available_payment_gateways', array($Vipps, 'payment_gateway_filter'));
    }

    // Some different bits and pieces: If we are on the pay-for-order page, we cannot provide Vipps for an order that has been at Vipps. IOK 2024-05-17
    public function payment_gateway_filter ($gateways) {
        if (is_checkout_pay_page()) {
            $orderid = absint(get_query_var( 'order-pay')); 
            $order = $orderid ? wc_get_order($orderid) : null;
            if (is_a($order, 'WC_Order')) {
               $isavipps = $order->get_meta('_vipps_init_timestamp');
               // Existing override that allows repayment. IOK 2024-06-04
               $allow_repayment = class_exists('\Site\Plugins\WooVipps\WooVippsPayForOrder');
               $allow_repayment = $isavipps ? apply_filters('woo_vipps_allow_repayment', $allow_repayment, $order) : true;
               if ($isavipps && !$allow_repayment) unset($gateways['vipps']);
            }
        }
        return $gateways;
    } 

    // Get the singleton WC_GatewayVipps instance
    public function gateway() {
        if (class_exists('WC_Payment_Gateway')) {
            require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
            return WC_Gateway_Vipps::instance();
        } else {
          $this->log(__("Error: Cannot instantiate payment gateway, because WooCommerce is not loaded! This can happen when WooCommerce updates itself; but if it didn't, please activate WooCommerce again", 'woo-vipps'), 'error');
          return null;
        }
    }


    // These are strings that should be available for translation possibly at some future point. Partly to be easier to work with translate.wordpress.org
    // Other usages are to translate any dynamic strings that may come from APIs etc. IOK 2021-03-18
    private function translatable_strings() {
        // Nothing here right now
        return false;
    }

    // True iff support for HPOS has been activated IOK 2022-12-07
    public function useHPOS() {
        if ($this->HPOSActive == null) {

            // Current way of checking IOK 2023-12-19
            if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
                if (Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                    $this->HPOSActive = true;
                } else {
                    $this->HPOSActive = false;
                }
                return $this->HPOSActive;
            }

            // This works in the backend, so ensures we are good with the meta fields etc.
            if (function_exists('wc_get_container') &&  // 4.4.0
                function_exists('wc_get_page_screen_id') && // Part of HPOS, not yet released
                class_exists("Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController") &&
                wc_get_container()->get( Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()            ) {
                $this->HPOSActive = true;
            } else {
                $this->HPOSActive = false;
            }
        }
        return $this->HPOSActive;
    }

    public function init () {

        // Register certain scripts in wp_loaded because they will be added to the backend as well - the gutenberg checkout block
        // needs these to be defined in the backend. IOK 2024-04-16
        add_action('wp_loaded', array($this, 'wp_register_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));

        // Remove the possibility of restarting failed orders etc. This will be fixed in the future. IOK 2023-05-26
        add_filter('woocommerce_my_account_my_orders_actions', array($this,'woocommerce_my_account_my_orders_actions'), 10, 2);

        // Used in 'compat mode' only to add products to the cart
        add_filter('woocommerce_add_to_cart_redirect', array($this,  'woocommerce_add_to_cart_redirect'), 10, 1);

        $this->add_shortcodes();
        $this->maybe_add_vipps_badge_feature();

        // Handle the asynch call to send Order Management data on payment complete - this will push order data to the users' Vipps app
        add_action('admin_post_nopriv_woo_vipps_order_management', array($this, 'do_order_management'));
        add_action('admin_post_woo_vipps_order_management', array($this, 'do_order_management'));

        // Extra order actions on the order screen, now using ajax to be compatible with HPOS. IOK 2022-12-02
        add_action('wp_ajax_woo_vipps_order_action', array($this, 'order_handle_vipps_action'));

        // Activate support for Vipps Checkout, including creating the special checkout page etc. Triggered from the payment page.
        add_action('wp_ajax_woo_vipps_activate_checkout_page', function () {
          check_ajax_referer('woo_vipps_activate_checkout','_wpnonce');
          static::set_locale_if_in_header();
          update_option('woo_vipps_checkout_activated', true, true); // This will load Vipps Checkout functionality from now on
          $this->maybe_create_vipps_pages(); // Ensure the special page exists
          if (isset($_REQUEST['activate']) && $_REQUEST['activate']) {
             $this->gateway()->update_option('vipps_checkout_enabled', 'yes');
          } else {
             $this->gateway()->update_option('vipps_checkout_enabled', 'no');
          }
        });

        // Fetch wc products, but filter those only purchasable by VMP express checkout. LP 2026-01-22
        add_action('rest_api_init', function() {
                   register_rest_route('woo-vipps/v1', '/express-products', [
                        'methods' => 'GET',
                        'callback' => [$this, 'rest_express_checkout_products'],
                   ]);
        });

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

        // For the rest, we need to read the payment gateways setting, and the payment gateway may not actually 
        // exist at this point. This is because for it to exist, WooCommerce must have loaded, and if it hasn't, for instance
        // because it is self-updating or because it has been deactivated just now or something, we won't have access to it.
        // Therefore test it first. IOK 2022-12-08
        $gw = $this->gateway();

        // This is a developer-mode level feature because flock() is not portable. This ensures callbacks and shopreturns do not
        // simultaneously update the orders, in particular not the express checkout order lines wrt shipping. IOK 2020-05-19
        if ($gw && $gw->get_option('use_flock') == 'yes') {
            add_filter('woo_vipps_lock_order', array($this,'flock_lock_order'));
            add_action('woo_vipps_unlock_order', array($this, 'flock_unlock_order'));
        }

    }


   // IOK 2022-12-02 This is currently used in two places: In the code that finds orders marked "to be deleted", and 
   // in the getOrderIdByVippsOrderId function. This is for the old-style Woo order tables, and do nothing for HPOS right now.
   // This should however be replaced by its own table so it can be done more efficiently.
    public static function add_wc_order_meta_key_support() {
        if (did_action('woo_vipps_add_order_meta_key_support')) return;
        do_action('woo_vipps_add_order_meta_key_support');
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', function ($query, $query_vars) {
            if (isset($query_vars['meta_vipps_orderid']) && $query_vars['meta_vipps_orderid'] ) {
                if (!isset($query['meta_query'])) $query['meta_query'] = array();
                $query['meta_query'][] = array(
                    'key' => '_vipps_orderid',
                    'value' => $query_vars['meta_vipps_orderid']
                );
            }
            if (isset($query_vars['meta_vipps_delendum']) && $query_vars['meta_vipps_delendum'] ) {
                if (!isset($query['meta_query'])) $query['meta_query'] = array();
                $query['meta_query'][] = array(
                    'key' => '_vipps_delendum',
                    'value' => 1
                );
            }
            return $query;
        }, 10, 2);

    }

    public function admin_init () {

        $gw = $this->gateway();
        require_once(dirname(__FILE__) . "/admin/settings/VippsAdminSettings.class.php");
        $adminSettings = VippsAdminSettings::instance();
        // Stuff for the Order screen
        add_action('woocommerce_order_item_add_action_buttons', array($this, 'order_item_add_action_buttons'), 10, 1);

        // Don't allow deletion of refunds made through Vipps IOK 2025-11-17
        add_action('woocommerce_after_order_refund_item_name', function ($refund) {
            $orderid = $refund->get_parent_id();
            $order = wc_get_order($orderid);
            if (is_a($order, 'WC_Order')  && $order->get_payment_method() == 'vipps') {
                $id = $refund->get_id();
                $gw = $refund->get_refunded_payment();
                if ($gw) {
                    $msg = sprintf(__('Refunded through %1$s', 'woo-vipps'), $this->get_payment_method_name()); 
                    echo "<style>#woocommerce-order-items tr.refund[data-order_refund_id=\"" . intval($id) . "\"] .wc-order-edit-line-item .wc-order-edit-line-item-actions a.delete_refund { display: none; }</style>";
                    echo "<i>" . esc_html($msg) . "</i>";
                }
            }});

        require_once(dirname(__FILE__) . "/VippsDismissibleAdminBanners.class.php");
        VippsDismissibleAdminBanners::add();

        // Styling etc
        add_action('admin_head', array($this, 'admin_head'));

        // Scripts
        $this->vippsJSConfig['vippssecnonce'] = wp_create_nonce('vippssecnonce');
        wp_localize_script('vipps-gw', 'VippsConfig', $this->vippsJSConfig);
        add_action('admin_enqueue_scripts', array($this,'admin_enqueue_scripts'));

        // Redirect the default WooCommerce settings page to our own
        add_action( 'woocommerce_settings_start', function () {
                add_filter('admin_url', function ($url, $path) {
                        if (strpos($path, "tab=checkout&section=vipps") === false) return $url;
                        $qs = parse_url($path, PHP_URL_QUERY);
                        if (!$qs) return $url;
                        $args = [];
                        parse_str($qs, $args);
                        $ok = (($args['page']??false) == 'wc-settings') && (($args['tab']??false) == 'checkout') && (($args['section']??false) == 'vipps');
                        if (!$ok) return $url;
                        return admin_url("/admin.php?page=vipps_settings_menu");
                        }, 10, 2);
        });

        // Custom product properties
        // IOK 2024-01-17 temporary: The special product properties are currenlty only active for Vipps
        // IOK 2025-09-01 now available for all
        add_filter('woocommerce_product_data_tabs', array($this,'woocommerce_product_data_tabs'),99);
        add_action('woocommerce_product_data_panels', array($this,'woocommerce_product_data_panels'),99);
        add_action('woocommerce_process_product_meta', array($this, 'process_product_meta'), 10, 2);

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));

        // Keep admin notices during redirects IOK 2018-05-07
        add_action('admin_notices',array($this,'stored_admin_notices'));

        // Ajax just for the backend
        add_action('wp_ajax_vipps_create_shareable_link', array($this, 'ajax_vipps_create_shareable_link'));
        add_action('wp_ajax_vipps_payment_details', array($this, 'ajax_vipps_payment_details'));
        add_action('wp_ajax_vipps_update_admin_settings', array($adminSettings, 'ajax_vipps_update_admin_settings'));

        // POST actions for the backend
        add_action('admin_post_update_vipps_badge_settings', array($this, 'update_badge_settings'));
        add_action('admin_post_update_vipps_button_settings', array($this, 'update_button_settings'));
        add_action('admin_post_vipps_delete_webhook', array($this, 'vipps_delete_webhook'));
        add_action('admin_post_vipps_add_webhook', array($this, 'vipps_add_webhook'));

        // Link to the settings page from the plugin list
        add_filter( 'plugin_action_links_'.plugin_basename(WC_VIPPS_MAIN_FILE ), array($this, 'plugin_action_links'));

        if ($gw->enabled == 'yes' && $gw->is_test_mode()) {
            $what = sprintf(__('%1$s is currently in test mode - no real transactions will occur', 'woo-vipps'), Vipps::CompanyName());
            $this->add_vipps_admin_notice($what,'info', '', 'test-mode');
        }


        // This requires merchants using the old shipping callback filter to choose between this or the new shipping method mechanism. IOK 2020-02-17
        if (has_action('woo_vipps_shipping_methods')) {
            $option = $gw->get_option('newshippingcallback');
            if ($option != 'old' && $option != 'new') {
                $what = __('Your theme or a plugin is currently overriding the <code>\'woo_vipps_shipping_methods\'</code> filter to customize your shipping alternatives.  While this works, this disables the newer Express Checkout shipping system, which is neccessary if your shipping is to include metadata. You can do this, or stop this message, from the <a href="%1$s">settings page</a>', 'woo-vipps');
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
                        $this->add_vipps_admin_notice(sprintf(__("<p>%1\$s not yet correctly configured:  please go to <a href='%2\$s'>the %1\$s settings</a> to complete your setup:<br> %3\$s</p>", 'woo-vipps'), Vipps::CompanyName(), admin_url('/admin.php?page=vipps_settings_menu'), $msg));
                    } else {
                        $this->add_vipps_admin_notice(sprintf(__("<p>%1\$s not yet configured:  please go to <a href='%2\$s'>the %1\$s settings</a> to complete your setup!</p>", 'woo-vipps'), Vipps::CompanyName(), admin_url('/admin.php?page=vipps_settings_menu')));
                    }
                } 

            }
            // If we are configured, but we don't have any webhooks yet, initialize them for the epayment api. IOK 2023-12-20
            // if we do have them, check them for consistency
            if (get_option('woo-vipps-configured')) {
                if (empty(get_option('_woo_vipps_webhooks'))) {
                    $gw->initialize_webhooks();
                } else {
                    $ok =  $gw->check_webhooks();
                    if (!$ok) {
                        $gw->initialize_webhooks();
                    };
                }
            }
        }
    }


    // Runs on init, adds the Vipps badge feature if activated
    public function maybe_add_vipps_badge_feature () {
        $badge_options = get_option('vipps_badge_options');
        if (!$badge_options || !@$badge_options['badgeon']) return false; 

        add_action('wp_enqueue_scripts', function () { wp_enqueue_script('vipps-onsite-messageing'); });
        add_action('woocommerce_before_add_to_cart_form', function () use ($badge_options)  {
            global $product;
            if (!is_a($product, 'WC_Product')) return; 

            $show = intval(@$badge_options['defaultall']);
            $forthis = $product->get_meta('_vipps_show_badge', true);
            $dontshow = ($forthis  == 'none');

            $doshow = !$dontshow && ($show || ($forthis &&  $forthis  != 'none'));

            if (!apply_filters('woo_vipps_show_vipps_badge_for_product', $doshow, $product)) {
                return;
            } 

            $attr = "";
            if ($forthis != 'none' || isset($badge_options['variant'])) {
                $variant = ($forthis && $forthis != 'none') ? $forthis : $badge_options['variant'];
                $attr .= " variant='" . sanitize_title($variant) . "' ";
            }

            $lang = $this->get_customer_language();
            if ($lang) {
                $attr .= " language='". $lang . "' ";
            }

            $brand = $this->get_payment_method_name();
            if ($brand) $attr .= " brand='". strtolower($brand) . "' ";


            $badge = "<vipps-mobilepay-badge $attr></vipps-mobilepay-badge>";

            echo apply_filters('woo_vipps_product_badge_html', $badge); 
        });

    }

    // A small interface for editing and managing the webhooks for the MSNs for this site IOK 2023-12-20
    public function webhook_menu_page () {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You don\'t have sufficient rights to access this page', 'woo-vipps'));
        }
        $portalurl = 'https://portal.vippsmobilepay.com';
        $webhookapi = 'https://developer.vippsmobilepay.com/docs/APIs/webhooks-api/';

        echo "<div class='wrap vipps-badge-settings'>\n";
        echo "<h1>" . __('Webhooks', 'woo-vipps') . "</h1>\n";
        echo "<p>"; printf(__('Whenever an event like a payment or a cancellation occurs on a %1$s account, you can be notified of this using a <i>webhook</i>. This is used by this plugin to get noticed of payments by users even when they do not return to your store.', 'woo-vipps'), Vipps::CompanyName()); echo "</p>";
        echo "<p>"; __('To do this, the plugin will automatically add webhooks for the MSN - Merchant Serial Numbers - configured on this site', 'woo-vipps'); echo "</p>";
        echo "<p>"; __('If your MSN has registered other callbacks, for instance for another website, you can manage these here - and you can also add your own hooks that will be notified of payment events to any other URL you enter.', 'woo-vipps'); echo "</p>";
        echo "<p>"; printf(__('Implementing a webhook is not trivial, so you will probably need a developer for this.  You can read more about what is required <a href="%1$s">here</a>. ', 'woo-vipps'), $webhookapi); 
        printf(__('Please note that there is normally a limit of <em><strong>5</strong> webhooks per MSN</em> - contact %1$s if you need more', 'woo-vipps'), Vipps::CompanyName());
        echo "</p>";
        echo "<p>"; print __('The following is a listing of your webhooks. If you have changed your website name, you may see some hooks that you do not recognize - these should be deleted', 'woo-vipps'); echo "</p>";

        $keyset = $this->gateway()->get_keyset();
	$recurrings = $this->gateway()->get_keyset();
	foreach($recurrings as $msn=> $keys) {
		if (!isset($keyset[$msn])) {
			$keyset[$msn] = $keys;
		}
	}
        $allhooks = $this->gateway()->initialize_webhooks();
        $localhooks = get_option('_woo_vipps_webhooks');

        echo "<form method='post' action='" . admin_url("admin-post.php") . "' autocomplete='off' id=webhook_action_form>";
        echo "<input type='hidden' id='webhook_id' name='webhook_id' value='' autocomplete='false'>";
        echo "<input type='hidden' id='webhook_msn' name='webhook_msn' value='' autocomplete='false'>";
        echo "<input type='hidden' id='webhook_url' name='webhook_url' value='' autocomplete='false'>";
        echo "<input type='hidden' id='webhook_events' name='webhook_events' value='' autocomplete='false'>";
        echo "<input type='hidden' id='webhook_post_action' name='action' value='' autocomplete='false'>";
        wp_nonce_field('webhook_nonce', 'webhook_nonce');
        echo "</form>";

        foreach ($keyset as $msn => $data) {
            $testmode = $data['testmode'] ?? false;
            echo "<div style='margin-top: 2rem; margin-bottom: 2rem'>";
            echo "<h2>";
            echo  sprintf(__('Merchant Serial Number %1$s', 'woo-vipps'), $msn);
            if ($testmode) echo " (" . __('Test mode', 'woo-vipps') . ")";
            echo "<a style='float:right; font-size:smaller' class='webhook-adder'  href='javascript:void(0)' data-msn='" . esc_attr($msn) . "'>[" . __('Add a webhook to this MSN', 'woo-vipps') . "]</a>";
            echo "</h2>";

            $all = $allhooks[$msn] ?? [];
            $thehooks = $all['webhooks'] ?? [];
            $locals = $localhooks[$msn] ?? [];

            echo "<table class='table webhook-table'><thead><tr><th style='text-align: left'>"  . __('Webhook', 'woo-vipps') . "</th><th>" . __('Action', 'woo-vipps') . "</th>" . "</tr></thead>";
            echo "<tbody>";
            foreach($thehooks as $hook) {
                $id = $hook['id'];
                $url = $hook['url'];
                $events = $hook['events'];
                $local = $locals[$id] ?? false;


                echo "<tr" . ($local ? " class='local' " : '') . "  data-webhook-id='" . esc_attr($id) .  "' data-msn='" . esc_attr($msn) . "'";
                echo " data-hookdata='" . json_encode($hook) . "'>"; 
                echo "<td>" .  esc_html($url) .  "</td>";
                echo "<td class='actions'>";
                    echo "<a href='javascript:void(0)' class='webhook-viewer'>[" . __('View', 'woo-vipps') . "]</a> ";
                if (!$local) {
                    echo " <a href='javascript:void(0)' class='webhook-deleter'>[" . __('Delete', 'woo-vipps') . "]</a>";
                } else {
                    echo " <em>". __('Created for this site', 'woo-vipps') . "</em>";
                }
                echo "</td>";
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
            echo "</div>";
            echo "<hr>";
        }

        $epayment_events = [__('Created', 'woo-vipps') => 'epayments.payment.created.v1',
                            __('Aborted', 'woo-vipps') => 'epayments.payment.aborted.v1',
                            __('Expired', 'woo-vipps') => 'epayments.payment.expired.v1',
                            __('Cancelled', 'woo-vipps') => 'epayments.payment.cancelled.v1',
                            __('Captured', 'woo-vipps') => 'epayments.payment.captured.v1',
                            __('Refunded', 'woo-vipps') => 'epayments.payment.refunded.v1',
                            __('Authorized', 'woo-vipps') => 'epayments.payment.authorized.v1',
                            __('Terminated', 'woo-vipps') => 'epayments.payment.terminated.v1'];

        $recurring_events = [ __('Agreement accepted', 'woo-vipps') =>'recurring.agreement-activated.v1',
            __('Agreement rejected', 'woo-vipps') =>'recurring.agreement-rejected.v1',
            __('Agreement stopped', 'woo-vipps') =>'recurring.agreement-stopped.v1',
            __('Agreement expired', 'woo-vipps') =>'recurring.agreement-expired.v1',
            __('Charge reserved', 'woo-vipps') =>'recurring.charge-reserved.v1',
            __('Charge captured', 'woo-vipps') =>'recurring.charge-captured.v1',
            __('Charge cancelled', 'woo-vipps') =>'recurring.charge-canceled.v1',
            __('Charge failed', 'woo-vipps') =>'recurring.charge-failed.v1'];

        $qr_events = [__('User Checked in', 'woo-vipps')=> 'user.checked-in.v1'];


        $defaultevents = ['epayments.payment.authorized.v1', 'epayments.payment.aborted.v1', 'epayments.payment.expired.v1', 'epayments.payment.terminated.v1'];


        ?>

<dialog id='webhook_view_dialog' style='width:70%'>
  <form method="dialog">
       <div class='viewdata' style='margin-bottom: 3rem'>
         <label>ID</label><span class='webhook_id'></span>
         <label>URL</label><span class='webhook_url'></span>
         <label>Events</label><div style='width:80%' class='webhook_events'></div>
       </div>
       <button class="button btn button-primary" type="submit" value="OK"><?php _e('OK'); ?></button>
  </form>
</dialog>


<dialog id='webhook_add_dialog' style='width: 70%'>
  <form method="dialog">
    <h3><?php _e('Add a webhook', 'woo-vipps'); ?></h3>
    <label for='dialog_webhook_msn'>MSN</label><input style='width: 50%' id='dialog_webhook_msn' required readonly type="text" name="webhook_msn" placeholder="">
    <label for='dialog_webhook_url'>URL</label><input style='width: 50%' id='dialog_webhook_url' autofocus required type="url" name="webhook_url" placeholder="https://...">
    <div class="events" style="margin-bottom: 2rem">
     <h3>Epayment</h3>
     <?php foreach($epayment_events as $label=>$event): ?> 
       <label for='<?php echo  esc_attr($event); ?>'><?php echo esc_html($label);?>
          <input <?php if (in_array($event, $defaultevents)) echo " checked " ?>
                  type='checkbox' name='webhook_event' value='<?php echo esc_attr($event); ?>'>
       </label>
     <?php endforeach; ?>
     <h3>Recurring</h3>
     <?php foreach($recurring_events as $label=>$event): ?> 
       <label for='<?php echo  esc_attr($event); ?>'><?php echo esc_html($label);?>
          <input <?php if (in_array($event, $defaultevents)) echo " checked " ?>
                  type='checkbox' name='webhook_event' value='<?php echo esc_attr($event); ?>'>
       </label>
     <?php endforeach; ?>
     <h3>QR</h3>
     <?php foreach($qr_events as $label=>$event): ?> 
       <label for='<?php echo  esc_attr($event); ?>'><?php echo esc_html($label);?>
          <input <?php if (in_array($event, $defaultevents)) echo " checked " ?>
                  type='checkbox' name='webhook_event' value='<?php echo esc_attr($event); ?>'>
       </label>
     <?php endforeach; ?>

    </div>
    <div class='buttonholder'>
       <button class="button btn button-primary" type="submit" value="OK"><?php _e('Add this URL as a webhook', 'woo-vipps'); ?></button>
       <button class="button btn" type="submit" formnovalidate value="NO"><?php _e('No, forget it', 'woo-vipps'); ?></button>
    </div>
  </form>
</dialog>

<style>
 dialog#webhook_add_dialog::backdrop {
   background-color: rgba(0.9,0.9,0.9,0.7);
 }
</style>

<script>
let dialog = document.getElementById('webhook_add_dialog');
let viewdialog = document.getElementById('webhook_view_dialog');
dialog.addEventListener('close', function () {
    if (dialog.returnValue =='OK') {
      let msn = dialog.querySelector('input[name="webhook_msn"]').value;
      let url = dialog.querySelector('input[name="webhook_url"]').value;
      dialog.querySelector('input[name="webhook_url"]').value = "";
      dialog.querySelector('input[name="webhook_msn"]').value = "";

      let events = dialog.querySelectorAll('input[name="webhook_event"]:checked');
      let eventlist = [];
      let eventstring = '';
       for (const ev of events.values()) {
          eventlist.push(ev.value);
      }
      eventstring = eventlist.join(',');
     

      if (msn && url && eventstring) {
       jQuery('#webhook_msn').val(msn);
       jQuery('#webhook_post_action').val('vipps_add_webhook');
       jQuery('#webhook_url').val(url);
       jQuery('#webhook_events').val(eventstring);
       let f = jQuery('#webhook_action_form');
       f.submit();
      }
    }
    dialog.querySelector('input[name="webhook_url"]').value = "";
    dialog.querySelector('input[name="webhook_msn"]').value = "";
});

let data = "";
jQuery('a.webhook-viewer').click(function (e) {
       e.preventDefault();
       let row= jQuery(this).closest('tr');
       data = row.data('hookdata');
       viewdialog.querySelector('.viewdata').querySelector('.webhook_id').innerHTML= data['id'];
       viewdialog.querySelector('.viewdata').querySelector('.webhook_url').innerHTML= data['url'];
       viewdialog.querySelector('.viewdata').querySelector('.webhook_events').innerHTML= data['events'].join(" ");
       viewdialog.showModal();
});


jQuery('a.webhook-deleter').click(function (e) {
       e.preventDefault();
       let row = jQuery(this).closest('tr');
       let wh  = row.data('webhook-id');
       let msn = row.data('msn');
       let f = jQuery('#webhook_action_form');
       jQuery('#webhook_id').val(wh);
       jQuery('#webhook_msn').val(msn);
       jQuery('#webhook_post_action').val('vipps_delete_webhook');
       f.submit();
});

jQuery('a.webhook-adder').click(function (e) {
            e.preventDefault();
            let msn = jQuery(this).data('msn');
            dialog.querySelector('input[name="webhook_url"]').value = "";
            dialog.querySelector('input[name="webhook_msn"]').value = msn;
            dialog.showModal();
});

        </script>

        <?php


        echo "</div>";
    }

    // To be called in admin-post.php
    public function vipps_delete_webhook() {
        static::set_locale_if_in_header();
        $ok = wp_verify_nonce($_REQUEST['webhook_nonce'],'webhook_nonce');
        if (!$ok) {
           wp_die("Wrong nonce");
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You don\'t have sufficient rights', 'woo-vipps'));
        }

        $msn = sanitize_title($_REQUEST['webhook_msn']);
        $id = sanitize_title($_REQUEST['webhook_id']);

        if ($msn && $id) {
            $this->gateway()->api->delete_webhook($msn, $id);
        }

        wp_safe_redirect(admin_url("admin.php?page=vipps_webhook_menu"));
        exit();
    }

    // To be called in admin-post.php
    public function vipps_add_webhook() {
        static::set_locale_if_in_header();
        $ok = wp_verify_nonce($_REQUEST['webhook_nonce'],'webhook_nonce');
        if (!$ok) {
           wp_die("Wrong nonce");
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You don\'t have sufficient rights', 'woo-vipps'));
        }

        $msn = sanitize_title($_REQUEST['webhook_msn']);
        $url = sanitize_url($_REQUEST['webhook_url']);
        $events = [];
        foreach(explode(",", $_REQUEST['webhook_events']) as $event) {
            $events[] = $event; 
        }
        if (!empty($events) && $msn && $url) {
            $this->gateway()->api->register_webhook($msn, $url, $events);
        }

        wp_safe_redirect(admin_url("admin.php?page=vipps_webhook_menu"));
        exit();
    }

    public function badge_menu_page () {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You don\'t have sufficient rights to access this page', 'woo-vipps'));
        }
        $badge_options = get_option('vipps_badge_options');
        
        // Get current brand and language
        $current_brand = strtolower($this->get_payment_method_name());
        $current_language = $this->get_customer_language();

        $variants = ['white'=> __('White', 'woo-vipps'), 'grey' => __('Grey','woo-vipps'), 
                     'filled'=> __('Filled', 'woo-vipps'), 'light'=>__('Light','woo-vipps'), 
                     'purple'=> __('Purple', 'woo-vipps')];

        ?>
        <div class='wrap vipps-badge-settings'>

          <h1><?php echo sprintf(__('%1$s On-Site Messaging', 'woo-vipps'), Vipps::CompanyName()); ?></h1>

           <h3><?php echo sprintf(__('%1$s On-Site Messaging contains <em>badges</em> in different variants that can be used to let your customers know that %1$s payment is accepted.', 'woo-vipps'), Vipps::CompanyName()); ?></h3>

           <p>
            <?php _e('You can configure these badges on this page, turning them on in all or some products and configure their default setup. You can also add a badge using a shortcode or a Block', 'woo-vipps'); ?>
           </p>

           <h2> <?php _e('Settings', 'woo-vipps'); ?></h2>
           <form class="vipps-badge-settings" action="<?php echo admin_url('admin-post.php'); ?>" method="POST">
            <input type="hidden" name="action" value="update_vipps_badge_settings" />
            <?php wp_nonce_field( 'badgeaction', 'badgenonce'); ?>
            <div>
             <label for="badgeon"><?php echo sprintf(__('Turn on support for %1$s On-site Messaging badges', 'woo-vipps'), Vipps::CompanyName()); ?></label>
             <input type="hidden" name="badgeon" value="0" />
             <input <?php if (@$badge_options['badgeon']) echo " checked "; ?> value="1" type="checkbox" id="badgeon" name="badgeon" />
            </div>

            <div>
             <label for="defaultall"><?php _e('Add badge to all products by default', 'woo-vipps'); ?></label>
             <input type="hidden" name="defaultall" value="0" />
             <input <?php if (@$badge_options['defaultall']) echo " checked "; ?> value="1" type="checkbox" id="defaultall" name="defaultall" />
             <p><?php echo sprintf(__("If selected, all products will get a badge, but you can override this on the %1\$s tab on the product data page. If not, it's the other way around. You can also choose a particular variant on that page", 'woo-vipps'), Vipps::CompanyName()); ?></p>
            </div>
           <p id="badgeholder" style="font-size:1.5rem">
              <vipps-mobilepay-badge id="vipps-badge-demo"
                brand="<?php echo esc_attr($current_brand); ?>"
                language="<?php echo esc_attr($current_language); ?>"
                <?php if (@$badge_options['variant']) echo ' variant="' . esc_attr($badge_options['variant']) . '" ' ?>
               ></vipps-mobilepay-badge>
           </p>

            <div>
              <label for="vippsBadgeVariant"><?php _e('Variant', 'woo-vipps'); ?></label>
            
              <select id=vippsBadgeVariant  name="variant" onChange='changeVariant()'>
               <option value=""><?php _e('Choose color variant:', 'woo-vipps'); ?></option>
               <?php foreach($variants as $key=>$name): ?>
                <option value="<?php echo $key; ?>" <?php if (@$badge_options['variant'] == $key) echo " selected "; ?> >
                   <?php echo $name ; ?>
                </option>
               <?php endforeach; ?>
              </select>

            <div>
              <input class="btn button primary"  type="submit" value="<?php _e('Update settings', 'woo-vipps'); ?>" />
            </div>

           </form>

           <h2><?php _e('The Gutenberg Block', 'woo-vipps'); ?></h2>
           <p><?php echo sprintf(__('If you use Gutenberg, you should be able to add a %1$s Badge block wherever you need it. It is called %1$s On-Site Messaging Badge Block.', 'woo-vipps'), Vipps::CompanyName()); ?>

           <h2><?php _e('Shortcodes', 'woo-vipps'); ?> </h2>
           <p><?php echo sprintf(__('If you need to add a %1$s badge on a specific page, footer, header and so on, and you cannot use the Gutenberg Block provided for this, you can either add the %1$s Badge manually (as <a href="%2$s" nofollow rel=nofollow target=_blank>documented here</a>) or you can use the shortcode.', 'woo-vipps'), Vipps::CompanyName(), "https://developer.vippsmobilepay.com/docs/knowledge-base/design-guidelines/on-site-messaging/"); ?></p>
           <br><?php _e("The shortcode looks like this:", 'woo-vipps')?><br>
              <pre>[vipps-mobilepay-badge variant={white|filled|light|grey|purple}<br>                       language={en|no|fi|dk} ] </pre><br> 
              <?php _e("Please refer to the documentation for the meaning of the parameters.", 'woo-vipps'); ?></br>
              <?php _e("The brand will be automatically applied.", 'woo-vipps'); ?>
           </p>

        </div>
        <script>
        function changeVariant() {
            const badge = document.getElementById('vipps-badge-demo');
            const variantSelector = document.getElementById('vippsBadgeVariant');
            const variant = variantSelector.options[variantSelector.selectedIndex].value;
            
            // Just update the variant attribute, preserving brand and language
            badge.setAttribute('variant', variant);
        }
        </script> 
        <?php
    }

    public function update_button_settings () {
        $ok = wp_verify_nonce($_REQUEST['buttonnonce'],'buttonaction');
        if (!$ok) {
           wp_die("Wrong nonce");
        }
        if (!current_user_can('manage_woocommerce')) {
            echo json_encode(array('ok'=>0,'msg'=>__('You don\'t have sufficient rights to edit this product', 'woo-vipps')));
            wp_die(__('You don\'t have sufficient rights to edit this product', 'woo-vipps'));
        }

        $options = get_option('vipps_button_options');
        if (isset($_POST['express']['variant'])) {
            $options['express']['variant'] = sanitize_title($_POST['express']['variant']);
        }
        if (isset($_POST['express']['mini-variant'])) {
            $options['express']['mini-variant'] = sanitize_title($_POST['express']['mini-variant']);
        }
        if (isset($_POST['express']['force-mini']) && is_array($_POST['express']['force-mini'])) {
            foreach($_POST['express']['force-mini'] as $key => $val)
              $options['express']['force-mini'][$key] = sanitize_title($val);
        }

        update_option('vipps_button_options', $options);
        wp_safe_redirect(admin_url("admin.php?page=vipps_button_menu"));
        exit();
    }

    public function update_badge_settings () {
        static::set_locale_if_in_header();
        $ok = wp_verify_nonce($_REQUEST['badgenonce'],'badgeaction');
        if (!$ok) {
           wp_die("Wrong nonce");
        }
        if (!current_user_can('manage_woocommerce')) {
            echo json_encode(array('ok'=>0,'msg'=>__('You don\'t have sufficient rights to edit this product', 'woo-vipps')));
            wp_die(__('You don\'t have sufficient rights to edit this product', 'woo-vipps'));
        }

        $current = get_option('vipps_badge_options');
        if (isset($_POST['badgeon'])) {
            $current['badgeon'] = intval($_POST['badgeon']);
        }
        if (isset($_POST['defaultall'])) {
            $current['defaultall'] = intval($_POST['defaultall']);
        }
        if (isset($_POST['variant'])) {
            $current['variant'] = sanitize_title($_POST['variant']);
        }

        update_option('vipps_badge_options', $current);
        wp_safe_redirect(admin_url("admin.php?page=vipps_badge_menu"));
        exit();
    }

    public function vipps_mobilepay_badge_shortcode($atts) {
        $args = shortcode_atts( array('id'=>'', 'class'=>'', 'brand' => '', 'variant' => '','language'=>''), $atts );

        $variant = in_array($args['variant'], ['orange', 'light-orange', 'grey','white', 'purple', 'filled', 'light']) ? $args['variant'] : "";
        $language = in_array($args['language'], ['en','no', 'fi', 'dk']) ? $args['language'] : $this->get_customer_language();
        $id = sanitize_title($args['id']);
        $class = sanitize_text_field($args['class']);

        $attributes = [];
        $attributes['brand'] = strtolower($this->get_payment_method_name());
        if ($variant) $attributes['variant'] = $variant;
        if ($language) $attributes['language'] = $language;
        if ($id) $attributes['id'] = $id;
        if ($class) $attributes['class'] = $class;

        $badgeatts = "";
        foreach($attributes as $key=>$value) $badgeatts .= " $key=\"" . esc_attr($value) . '"';

        return "<vipps-mobilepay-badge $badgeatts></vipps-mobilepay-badge>";
    }

    // legacy vipps_badge shortcode, the new one is vipps_mobilepay_badge_shortcode. LP 19.11.2024
    public function vipps_badge_shortcode($atts) {
        $args = shortcode_atts( array('id'=>'', 'class'=>'','variant' => '','language'=>''), $atts );
        
        $variant = in_array($args['variant'], ['orange', 'light-orange', 'grey','white', 'purple']) ? $args['variant'] : "";
        $language = in_array($args['language'], ['en','no', 'dk', 'fi']) ? $args['language'] : $this->get_customer_language();
        $id = sanitize_title($args['id']);
        $class = sanitize_text_field($args['class']);

        $attributes = [];
        if ($variant) $attributes['variant'] = $variant;
        if ($language) $attributes['language'] = $language;
        if ($id) $attributes['id'] = $id;
        if ($class) $attributes['class'] = $class;

        $badgeatts = "";
        foreach($attributes as $key=>$value) $badgeatts .= " $key=\"" . esc_attr($value) . '"';

        return "<vipps-badge $badgeatts></vipps-badge>";
    }

    public function get_express_logo_variants() {
        return [
            'buy-now-rectangular' => __('Buy now rectangular', 'woo-vipps'),
            'buy-now-pill' => __('Buy now pill', 'woo-vipps'),
            'express-rectangular' => __('Express rectangular', 'woo-vipps'),
            'express-pill' => __('Express pill', 'woo-vipps'),
            'express-rectangular-mini' => __('Express rectangular mini', 'woo-vipps'),
            'express-pill-mini' => __('Express pill mini', 'woo-vipps'),
        ];
    }


    public function button_menu_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You don\'t have sufficient rights to access this page', 'woo-vipps'));
        }
        $payment_method = $this->get_payment_method_name();
        $lang = $this->get_customer_language();
        $button_options = get_option('vipps_button_options');

        $variants = $this->get_express_logo_variants();
        $mini_variants = array_filter($variants, fn($key) => str_ends_with($key, 'mini'), ARRAY_FILTER_USE_KEY);

        $init_states = [
            'express' => [
                'variant' => array_key_exists(@$button_options['express']['variant'], $variants) ? $button_options['express']['variant'] : 'buy-now-rectangular',
                'mini-variant' => array_key_exists(@$button_options['express']['mini-variant'], $mini_variants) ? $button_options['express']['mini-variant'] : 'express-rectangular-mini',
                'force-mini' => [
                    'product' => @$button_options['express']['force-mini']['product'] ?? 'no',
                    'catalog' => @$button_options['express']['force-mini']['catalog'] ?? 'yes',
                    'cart' => @$button_options['express']['force-mini']['cart'] ?? 'no',
                    'minicart' => @$button_options['express']['force-mini']['minicart'] ?? 'yes',
                ],
            ],
        ];

        ?>
        <div class='wrap vipps-button-settings'>
          <h1><?php echo sprintf(__('%1$s button configuration', 'woo-vipps'), Vipps::CompanyName()); ?></h1>
          <span><?php echo sprintf(__('%1$s supports different variants of buttons for you to perfect your store\'s look', 'woo-vipps'), Vipps::CompanyName()); ?></span>
          <form class="vipps-button-settings" action="<?php echo admin_url('admin-post.php'); ?>" method="POST">

            <!-- EXPRESS SECTION -->
            <div id="vipps-button-settings-express-container">
              <h2> <?php _e('Express Checkout', 'woo-vipps'); ?></h2>
              <input type="hidden" name="action" value="update_vipps_button_settings" />
              <?php wp_nonce_field( 'buttonaction', 'buttonnonce'); ?>

              <!-- variant -->
              <div class="vipps-button-settings-section">
                <!-- variant dropdown -->
                <div class="vipps-button-settings-express-demo-container">
                  <label for="vippsButtonVariant"><?php _e('Choose variant', 'woo-vipps'); ?></label>
                  <select id="vippsButtonVariant"  name="express[variant]" onChange='changeExpressVariant()'>
                    <?php foreach($variants as $key=>$name): ?>
                      <option value="<?php echo $key; ?>" <?php if ($init_states['express']['variant'] === $key) echo " selected "; ?> >
                         <?php echo $name ; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Preload all variant images. Javascript will show the active one. LP 2025-12-16 -->
                <div class="vipps-button-settings-express-demo-container vipps-button-settings-img-container">
                <?php foreach(array_keys($variants) as $variant): ?>
                  <img
                    class="vipps-button-settings-express-demo"
                    id="vipps-button-settings-express-demo-<?php echo $variant; ?>" 
                    src="<?php echo $this->get_express_logo($payment_method, $lang, $variant); ?>"
                    style="display: <?php echo ($variant === $init_states['express']['variant'] ? 'block' : 'none') ;?>;"
                  >
                <?php endforeach; ?>
                </div>
              </div>


              <!-- mini variant section -->
              <div class="vipps-button-settings-section">
                <!-- Checkboxes "Use mini version for x page" -->
                <label><?php _e('Force mini variant in these contexts:', 'woo-vipps'); ?></label>
                <div class="vipps-button-settings-express-force-mini-container">
                  <label class="vipps-button-settings-express-force-mini" id="vipps-button-settings-express-force-mini-product"><?php _e('Product page', 'woo-vipps'); ?></label>
                  <input name="express[force-mini][product]" type="hidden" value="no">
                  <input name="express[force-mini][product]" type="checkbox" value="yes" <?php if ($init_states['express']['force-mini']['product'] == "yes") echo "checked";?>>
                </div>

                <div class="vipps-button-settings-express-force-mini-container">
                  <label class="vipps-button-settings-express-force-mini" id="vipps-button-settings-express-force-mini-catalog"><?php _e('Catalog page', 'woo-vipps'); ?></label>
                  <input name="express[force-mini][catalog]" type="hidden" value="no">
                  <input name="express[force-mini][catalog]" type="checkbox" value="yes" <?php if ($init_states['express']['force-mini']['catalog'] == "yes") echo "checked";?>>
                </div>

                <div class="vipps-button-settings-express-force-mini-container">
                  <label class="vipps-button-settings-express-force-mini" id="vipps-button-settings-express-force-mini-cart"><?php _e('Cart', 'woo-vipps'); ?></label>
                  <input name="express[force-mini][cart]" type="hidden" value="no">
                  <input name="express[force-mini][cart]" type="checkbox" value="yes" <?php if ($init_states['express']['force-mini']['cart'] == "yes") echo "checked";?>>
                </div>

                <div class="vipps-button-settings-express-force-mini-container">
                  <label class="vipps-button-settings-express-force-mini" id="vipps-button-settings-express-force-mini-minicart"><?php _e('Mini cart', 'woo-vipps'); ?></label>
                  <input name="express[force-mini][minicart]" type="hidden" value="no">
                  <input name="express[force-mini][minicart]" type="checkbox" value="yes" <?php if ($init_states['express']['force-mini']['minicart'] == "yes") echo "checked";?>>
                </div>

                <!-- mini variant dropdown -->
                <div class="vipps-button-settings-express-mini-demo-container">
                  <label for="vippsButtonMiniVariant"><?php _e('Choose variant to use in mini contexts', 'woo-vipps'); ?></label>
                  <select id="vippsButtonMiniVariant"  name="express[mini-variant]" onChange='changeExpressMiniVariant()'>
                    <?php foreach($mini_variants as $key=>$name): ?>
                      <option value="<?php echo $key; ?>" <?php if ($init_states['express']['mini-variant'] === $key) echo " selected "; ?> >
                         <?php echo $name ; ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Preload mini variant imgs. LP 2025-12-17  -->
                <div class="vipps-button-settings-express-mini-demo-container vipps-button-settings-img-container">
                  <?php foreach(array_keys($mini_variants) as $variant): ?>
                    <img
                      class="vipps-button-settings-express-mini-demo"
                      id="vipps-button-settings-express-mini-demo-<?php echo $variant; ?>" 
                      src="<?php echo $this->get_express_logo($payment_method, $lang, $variant); ?>"
                      style="display: <?php echo ($variant === $init_states['express']['mini-variant'] ? 'block' : 'none') ;?>;"
                    >
                  <?php endforeach; ?>
                </div>
              </div>

            <!-- END EXPRESS SECTION -->
            </div>

            <!-- Save button -->
            <div id="vipps-button-settings-save">
              <input class="btn button primary" type="submit" value="<?php _e('Update settings', 'woo-vipps'); ?>" />
            </div>

          </form>
        </div>

        <script>
          function changeExpressVariant() {
              const variant = jQuery('#vippsButtonVariant').val().trim();
              // Show the one selected, hide all others. LP 2025-12-16
              jQuery('.vipps-button-settings-express-demo').hide();
              jQuery(`#vipps-button-settings-express-demo-${variant}`).show();
          }

          function changeExpressMiniVariant() {
              const variant = jQuery('#vippsButtonMiniVariant').val().trim();
              // Show the one selected, hide all others. LP 2025-12-16
              jQuery('.vipps-button-settings-express-mini-demo').hide();
              jQuery(`#vipps-button-settings-express-mini-demo-${variant}`).show();
          }
        </script> 
        <?php
    }


    public function admin_menu_page () {
        $flavour = sanitize_title($this->get_payment_method_name());

        // The function which is hooked in to handle the output of the page must check that the user has the required capability as well.  (manage_woocommerce)
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You don\'t have sufficient rights to access this page', 'woo-vipps'));
        }

        $recurringsettings = admin_url('/admin.php?page=wc-settings&tab=checkout&section=vipps_recurring');
        $checkoutsettings  = admin_url('/admin.php?page=vipps_settings_menu');
        $loginsettings = admin_url('options-general.php?page=vipps_login_settings');

        $logininstall = admin_url('/plugin-install.php?s=login-with-vipps&tab=search&type=term');
        $subscriptioninstall = 'https://woocommerce.com/products/woocommerce-subscriptions/';

        $logspage = admin_url('/admin.php?page=wc-status&tab=logs');
        $forumpage = 'https://wordpress.org/support/plugin/woo-vipps/';

        $portalurl = 'https://portal.vippsmobilepay.com';

        $installed = get_plugins();

        $recurringinstalled = array_key_exists('vipps-recurring-payments-gateway-for-woocommerce/woo-vipps-recurring.php',$installed);
        $recurringactive = class_exists('WC_Vipps_Recurring');
        $recurringstandalone = $recurringactive && !(defined('WC_VIPPS_RECURRING_INTEGRATED') && WC_VIPPS_RECURRING_INTEGRATED);
        $deactivatelink = admin_url("plugins.php?s=vipps-recurring-payments-gateway-for-woocommerce");

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

        if (WC_Gateway_Vipps::instance()->get_payment_method_name() != "Vipps"):
    ?>
    <style>.notice.notice-vipps.test-mode { display: none; }body.wp-admin.toplevel_page_vipps_admin_menu #wpcontent {background-color: white; }</style>
    <header class="vipps-admin-page-header <?php echo esc_attr($flavour); ?>" style="padding-top: 3.5rem ; line-height: 30px;">
            <h1><?php echo esc_html(Vipps::CompanyName()); ?> <?php echo esc_html($slogan); ?></h1>
    </header>
    <div class='wrap vipps-admin-page'>
            <div id="vipps_page_vipps_banners"><?php echo apply_filters('woo_vipps_vipps_page_banners', ""); ?></div>
            <h1><?php echo sprintf(__("%1\$s for WordPress and WooCommerce", 'woo-vipps'), Vipps::CompanyName()); ?></h1>
            <div class="pluginsection woo-vipps">

               <p><?php echo sprintf(__("This plugin gives you %1\$s in WooCommerce, either as a fully fledged Checkout, or as a flexible payment method.",'woo-vipps'), WC_Gateway_Vipps::instance()->get_payment_method_name()); ?></p>
               <p><?php echo sprintf(__("With Checkout, you’ll also get access to shipping addresses, shipping selection and other payment options. Currently Checkout supports %1\$s and bank transfer; VISA and MasterCard payments will be added later.",'woo-vipps'), WC_Gateway_Vipps::instance()->get_payment_method_name()); ?></p>

               <p><strong><?php echo sprintf(__("NB! Checkout for MobilePay is currently in beta mode; Bank Transfer has limited availability", 'woo-vipps')); ?></strong></p>

               <p><?php echo sprintf(__("Configure the plugin on its <a href='%1\$s'>settings page</a> and  get your keys from the <a target='_blank' href='%2\$s'>%3\$s portal</a>.",'woo-vipps'), $checkoutsettings, $portalurl, Vipps::CompanyName());?></p>
               <p><?php echo sprintf(__("If you experience problems or unexpected results, please check the 'fatal-errors' and 'woo-vipps' logs at <a href='%1\$s'>WooCommerce logs page</a>.", 'woo-vipps'), $logspage); ?></p>
               <p><?php echo sprintf(__("If you need support, please use the <a href='%1\$s'>forum page</a> for the plugin. If you cannot post your question publicly, contact WP-Hosting directly at support@wp-hosting.no.", 'woo-vipps'), $forumpage); ?></p>
               <div class="pluginstatus vipps_admin_highlighted_section <?php echo esc_attr($flavour); ?>">
               <?php if ($istestmode): ?>
                  <p><b>
                   <?php echo sprintf(__('%1$s is currently in test mode - no real transactions will occur.', 'woo-vipps'), Vipps::CompanyName()); ?>
                  </b></p>
               <?php endif; ?>
               <p>
                  <?php if ($configured): ?>
                    <?php echo sprintf(__("<a href='%1\$s'>%2\$s configuration</a> is complete.", 'woo-vipps'), $checkoutsettings, Vipps::CompanyName()); ?> 
                  <?php else: ?>
                    <?php echo sprintf(__("%1\$s configuration is not yet complete - you must get your keys from the %1\$s portal and enter them on the <a href='%2\$s'>settings page</a>", 'woo-vipps'), Vipps::CompanyName(), $checkoutsettings); ?> 
                  <?php endif; ?>
               </p>
               <?php if ($isactive): ?>
                 <p> 
                   <?php echo sprintf(__("The plugin is <b>active</b> - %1\$s is available as a payment method.", 'woo-vipps'), Vipps::CompanyName()); ?>
                   <?php if ($ischeckout): ?>
                    </p>
                    <p> 
                    <?php echo sprintf(__("You are now using <b>%1\$s Checkout</b> instead of the standard WooCommerce Checkout page.", 'woo-vipps'), WC_Gateway_Vipps::instance()->get_payment_method_name()); ?>     
                   <?php endif; ?>
                 </p>
               <?php else:; ?>
               <?php endif; ?>
              </div>

            </div>
    </div> 
    
    <?php else: ?>
    
    <style>.notice.notice-vipps.test-mode { display: none; }body.wp-admin.toplevel_page_vipps_admin_menu #wpcontent {background-color: white; }</style>
    <header class="vipps-admin-page-header <?php echo esc_attr($flavour); ?>" style="padding-top: 3.5rem ; line-height: 30px;">
            <h1><?php echo esc_html(Vipps::CompanyName()); ?> <?php echo esc_html($slogan); ?></h1>
    </header>
    <div class='wrap vipps-admin-page'>
            <div id="vipps_page_vipps_banners"><?php echo apply_filters('woo_vipps_vipps_page_banners', ""); ?></div>
            <h1><?php echo sprintf(__("%1\$s for WordPress and WooCommerce", 'woo-vipps'), Vipps::CompanyName()); ?></h1>
            <p><?php echo sprintf(__("%1\$s officially supports WordPress and WooCommerce with a family of plugins implementing a payment gateway for WooCommerce, an optional complete checkout solution powered by %1\$s, a system for managing QR-codes that link to your products or landing pages, a plugin for recurring payments, and a system for passwordless logins.", 'woo-vipps'), Vipps::CompanyName());?></p>
            <p><?php echo sprintf(__("To order or configure your %1\$s account that powers these plugins, log onto <a target='_blank'  href='%2\$s'>the %1\$s portal</a> and use the keys and data from that to set up your plugins as needed.", 'woo-vipps'), Vipps::CompanyName(), $portalurl); ?></p>

            <h1><?php echo sprintf(__("The %1\$s plugins", 'woo-vipps'), Vipps::CompanyName()); ?></h1>
            <div class="pluginsection woo-vipps">
               <h2><?php echo sprintf(__('Pay with %1$s for WooCommerce', 'woo-vipps' ), Vipps::CompanyName());?></h2>
               <p><?php echo sprintf(__("This plugin implements a %1\$s checkout solution for WooCommerce and an alternate %1\$s hosted checkout that supports both %2\$s and credit cards. It also supports %1\$s's QR-api for creating QR-codes to your landing pages or products.", 'woo-vipps'), Vipps::CompanyName(), WC_Gateway_Vipps::instance()->get_payment_method_name()); ?></p>
               <p><?php echo sprintf(__("Configure the plugin on its <a href='%1\$s'>settings page</a> and  get your keys from the <a target='_blank' href='%2\$s'>%3\$s portal</a>.",'woo-vipps'), $checkoutsettings, $portalurl, Vipps::CompanyName());?></p>
               <p><?php echo sprintf(__("If you experience problems or unexpected results, please check the 'fatal-errors' and 'woo-vipps' logs at <a href='%1\$s'>WooCommerce logs page</a>.", 'woo-vipps'), $logspage); ?></p>
               <p><?php echo sprintf(__("If you need support, please use the <a href='%1\$s'>forum page</a> for the plugin. If you cannot post your question publicly, contact WP-Hosting directly at support@wp-hosting.no.", 'woo-vipps'), $forumpage); ?></p>
               <div class="pluginstatus vipps_admin_highlighted_section">
               <?php if ($istestmode): ?>
                  <p><b>
                   <?php echo sprintf(__('%1$s is currently in test mode - no real transactions will occur.', 'woo-vipps'), Vipps::CompanyName()); ?>
                  </b></p>
               <?php endif; ?>
               <p>
                  <?php if ($configured): ?>
                    <?php echo sprintf(__("<a href='%1\$s'>%2\$s configuration</a> is complete.", 'woo-vipps'), $checkoutsettings, Vipps::CompanyName()); ?> 
                  <?php else: ?>
                    <?php echo sprintf(__("%1\$s configuration is not yet complete - you must get your keys from the %1\$s portal and enter them on the <a href='%2\$s'>settings page</a>", 'woo-vipps'), Vipps::CompanyName(), $checkoutsettings); ?> 
                  <?php endif; ?>
               </p>
               <?php if ($isactive): ?>
                <p> 
                   <?php echo sprintf(__("The plugin is <b>active</b> - %1\$s is available as a payment method.", 'woo-vipps'), Vipps::CompanyName()); ?>
                   <?php if ($ischeckout): ?>
                    </p>
                    <p> 
                    <?php echo sprintf(__("You are now using <b>%1\$s Checkout</b> instead of the standard WooCommerce Checkout page.", 'woo-vipps'), WC_Gateway_Vipps::instance()->get_payment_method_name()); ?>     
                   <?php endif; ?>
                 </p>
               <?php else:; ?>
               <?php endif; ?>
              </div>

            </div>

            <div class="pluginsection vipps-recurring">
               <h2><?php echo sprintf(__( 'Recurring Payments with %1$s', 'woo-vipps' ), Vipps::CompanyName());?></h2>
               <p>
                  <?php echo sprintf(__("%1\$s supports recurring payments through the plugin <a href='%2\$s' target='_blank'>WooCommerce Subscriptions</a>. This support is written and supported by <a href='%3\$s' target='_blank'>Everyday</a>, and is perfect for you if you run a web shop with subscription based services or other products that would benefit from subscriptions.", 'woo-vipps'), Vipps::CompanyName(), 'https://woocommerce.com/products/woocommerce-subscriptions/', Vipps::CompanyName(), 'https://everyday.no/'); ?>
               <?php do_action('vipps_page_vipps_recurring_payments_section'); ?>
               <div class="pluginstatus vipps_admin_highlighted_section">
                 <?php if ($recurringactive): ?>
                  <p>
                   <?php echo sprintf(__("Support for recurring payments with %1\$s is <b>active</b>. You can configure the plugin at its <a href='%2\$s'>settings page</a>.", 'woo-vipps'), Vipps::CompanyName(), $recurringsettings); ?>
                  </p>
                 <?php endif; ?>
                 <?php if (!$subscriptioninstall): ?>
                  <p>
                   <?php echo sprintf(__("This plugins support for recurring payments requires the plugin <a href='%1\$s' target='_blank'>WooCommerce Subscriptions</a>. You need to install and activate this first.", 'woo-vipps'), 'https://woocommerce.com/products/woocommerce-subscriptions/'); ?>
                  </p>
                 <?php endif; ?>
                 <?php if ($recurringactive && $recurringstandalone): ?>
                  <p>
                  <?php echo sprintf(__("Your support for recurring payments with %1\$s uses the legacy stand-alone plugin. This is no longer required, and you should <b><a href='%2\$s'>deactivate</a></b> this plugin, since development on this will soon cease.", 'woo-vipps'), Vipps::CompanyName(), esc_attr($deactivatelink));?>
                  </p>

                 <?php endif; ?>

               </div>

            </div>

            <div class="pluginsection login-with-vipps">
               <h2><?php echo sprintf(__( '%1$s', 'woo-vipps' ), Vipps::LoginName());?></h2>
               <p><?php echo sprintf(__("<a href='%1\$s' target='_blank'>%3\$s</a> is a password-less solution that lets you or your customers to securely log into your site without having to remember passwords - you only need the %2\$s app. The plugin does not require WooCommerce, and it can be customized for many different usecases.", 'woo-vipps'), 'https://www.wordpress.org/plugins/login-with-vipps/',Vipps::CompanyName(), Vipps::LoginName()); ?></p>
               <p> <?php echo sprintf(__("If you use %1\$s in WooCommerce, this allows your %2\$s customers to safely log in without ever using a password.", 'woo-vipps'), Vipps::CheckoutName(), Vipps::CompanyName()); ?>
               <p>
                       <?php echo sprintf(__("Remember, you need to set up %3\$s at the <a target='_blank' href='%2\$s'>%1\$s Portal</a>, where you will find the keys you need and where you will have to register the <em>return url</em> you will find on the settings page.", 'woo-vipps'),Vipps::CompanyName(),$portalurl, Vipps::LoginName()); ?>
               </p>

               <div class="pluginstatus vipps_admin_highlighted_section">
               <?php if ($loginactive): ?>
                     <p>
                       <?php echo sprintf(__("%1\$s is installed and active. You can configure the plugin at its <a href='%2\$s'>settings page</a>", 'woo-vipps'),Vipps::LoginName(), $loginsettings); ?>
                    </p>
               <?php elseif ($logininstalled): ?>
                     <p>
                     <?php echo sprintf(__("%1\$s is installed, but not active. Activate it on the <a href='%2\$s'>plugins page</a>", 'woo-vipps'), Vipps::LoginName(), admin_url("/plugins.php")); ?>
                     </p>
               <?php else: ?>
                     <p>
                     <?php echo sprintf(__("%1\$s is not installed. You can install it <a href='%2\$s'>here!</a>", 'woo-vipps'), Vipps::LoginName(), $logininstall); ?>
                     </p>
               <?php endif; ?>
               </div>

            </div>
   
    </div> 
    
    <?php endif;
    }

    // Add a link to the settings page from the plugin list
    public function plugin_action_links ($links) {
        $link = '<a href="'.esc_attr(admin_url('/admin.php?page=vipps_settings_menu')). '">'.__('Settings', 'woo-vipps').'</a>';
        array_unshift( $links, $link);
        return $links;
    }


    // Requested by Vipps: It is a feature of this plugin that a prefix is added to the order number, in order to make it possible to use several different stores
    // that may use the same ordre number ranges. The prefix used to be just "Woo" by default, but Vipps felt it would be easier to respond to support request by
    // (trying to) identify the store/site directly in the order prefix. So this does that: It creates a prefix "woo-" + 8 chars derived from the domain of the siteurl.
    // The result should be "woo-abcdefgh-" which should leave 18 digits for the actual order number. IOK 2020-05-19 
    public function generate_order_prefix() {
        $parts = parse_url(site_url());
        if (!$parts) return 'Woo';
        $domain = explode(".", $parts['host'] ?? '');
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
                        $logo = plugins_url('img/vmp-logo.png',__FILE__);
                        $message = "<img style='height:40px;float:left;' src='$logo' alt='Vipps-logo'> $text";
                        echo "<div class='notice notice-vipps notice-$type $extraclasses is-dismissible'  data-key='" . esc_attr($key) . "'><p>$message</p></div>";
                        });
    }


    // This function will delete old orders that were cancelled before the Vipps action was completed. We keep them for
    // 10 minutes so we can work with them in hooks and callbacks after they are cancelled. IOK 2019-10-22
#    protected function delete_old_cancelled_orders() {
    public function delete_old_cancelled_orders() {
        $limit = 30;
        $cutoff = time() - 600; // Ten minutes old orders: Delete them
        $oldorders = time() - (60*60*24*7); // Very old orders: Ignore them to make this work on sites with enormous order databases
        // Ensure the old order table understands the meta query IOK 2022-12-02
        static::add_wc_order_meta_key_support();
        $args = array(
                'status' => 'cancelled',
                'limit' => $limit,
                'date_modified' => "$oldorders...$cutoff",
                'meta_vipps_delendum' => 1);
        if  ($this->useHPOS()) {
            /* The above, with the filter, is for the old orders table, the below is for the new IOK 2022-12-02 */
            $args['meta_query'] =  [[ 'key'  => '_vipps_delendum', 'value' => 1 ]];
        }

        $delenda = wc_get_orders($args);

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
        $smile= plugins_url('img/vmp-logo.png',__FILE__);
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
        // Add certain translations very late so translation plugins get a chance to work. IOK 2026-02-02
        $this->script_add_vippslocale();

        wp_register_script('vipps-admin',plugins_url('js/admin.js',__FILE__),array('jquery','vipps-gw'),filemtime(dirname(__FILE__) . "/js/admin.js"), 'true');
        wp_enqueue_script('vipps-admin');

        wp_enqueue_style('vipps-admin-style',plugins_url('css/admin.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/admin.css"), 'all');
        wp_enqueue_style('vipps-fonts');
        wp_enqueue_style('vipps-fonts',plugins_url('css/fonts.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/fonts.css"), 'all');

        wp_enqueue_script('vipps-onsite-messageing',"https://checkout.vipps.no/on-site-messaging/v1/vipps-osm.js",array(),WOO_VIPPS_VERSION,
            array(
                'in_footer' => true,
                'strategy'  => 'async',
            ));
    }


    public function admin_menu () {
        // IOK 2023-12-01 replace old Vipps smile in larger contexts
        // $logo= plugins_url('img/vipps-smile-orange.png',__FILE__);
        $logo = plugins_url('img/vmp-logo.png', __FILE__);
        require_once(dirname(__FILE__) . "/admin/settings/VippsAdminSettings.class.php");
        $adminSettings = VippsAdminSettings::instance();

        add_menu_page(sprintf(__("%1\$s", 'woo-vipps'), Vipps::CompanyName()), sprintf(__("%1\$s", 'woo-vipps'), Vipps::CompanyName()), 'manage_woocommerce', 'vipps_admin_menu', array($this, 'admin_menu_page'), $logo, 58);

        add_submenu_page( 'vipps_admin_menu', __('Settings', 'woo-vipps'),   __('Settings', 'woo-vipps'),   'manage_woocommerce', 'vipps_settings_menu', array($adminSettings, 'init_admin_settings_page_react_ui'), 90);

        if (class_exists('WC_Vipps_Recurring') && class_exists('WC_Subscriptions_Plugin')) {
            add_submenu_page( 'vipps_admin_menu', __('Recurring Payments', 'woo-vipps'),   __('Recurring Payments', 'woo-vipps'),   'manage_woocommerce', 'vipps_recurring__settings_menu', array($this, 'recurring_settings_page'), 95);
        }

        add_submenu_page( 'vipps_admin_menu', __('Badges', 'woo-vipps'),   __('Badges', 'woo-vipps'),   'manage_woocommerce', 'vipps_badge_menu', array($this, 'badge_menu_page'), 90);
        add_submenu_page( 'vipps_admin_menu', __('Buttons', 'woo-vipps'),   __('Buttons', 'woo-vipps'),   'manage_woocommerce', 'vipps_button_menu', array($this, 'button_menu_page'), 80);
        add_submenu_page( 'vipps_admin_menu', __('Webhooks', 'woo-vipps'),   __('Webhooks', 'woo-vipps'),   'manage_woocommerce', 'vipps_webhook_menu', array($this, 'webhook_menu_page'), 10);
    }

    // Just a redirect to the recurring payment settings for the time being. IOK 2025-01-08
    public function recurring_settings_page () {
        if (class_exists('WC_Vipps_Recurring') && class_exists('WC_Subscriptions_Plugin')) {
            wp_safe_redirect(admin_url('/admin.php?page=wc-settings&tab=checkout&section=vipps_recurring'), 302);
        } else {
            wp_safe_redirect(admin_url('/admin.php?page=vipps_admin_menu'), 302);
        }
        exit();
    }

    public function add_meta_boxes () {
        $screen = 'shop_order';
        $useHPOS = $this->useHPOS();

        if ($useHPOS && function_exists('wc_get_page_screen_id')) {
            $screen = wc_get_page_screen_id('shop-order');
        }

        $vippsorder = false;
        $order = null;
        global $post;
        if ($post && $post->post_type == 'shop_order') {
           $order = wc_get_order($post);
        } else {
            // New style HPOS order table doesn't let us inspect the order, so we must fetch it from query args
            $screen = get_current_screen();
            if ($screen && $screen->id == 'woocommerce_page_wc-orders') {
                $orderid = isset($_REQUEST['id']) ?  $_REQUEST['id'] : 0;
                $order = wc_get_order($orderid);
            }
        }
        if (is_a($order, 'WC_Order')  && $order->get_payment_method() == 'vipps') {
          $vippsorder = true;
        }

        if ($vippsorder) {
           add_meta_box( 'vippsdata', sprintf(__('%1$s','woo-vipps'), $this->get_payment_method_name()), array($this,'add_vipps_metabox'), $screen, 'side', 'core' );
        }
    }

    public function wp_register_scripts () {
        //  We are going to use the 'hooks' library introduced by WP 5.1, but we still support WP 4.7. So if this isn't enqueues 
        //  (which it only is if Gutenberg is active) or not provided at all, add it now.
        if (!wp_script_is( 'wp-hooks', 'registered')) {
            wp_register_script('wp-hooks', plugins_url('/compat/hooks.min.js', __FILE__));
        }
        wp_register_script('vipps-gw',plugins_url('js/vipps.js',__FILE__),array('jquery','wp-hooks'),filemtime(dirname(__FILE__) . "/js/vipps.js"), 'true');

    }

    // Runs late in both wp_enqueue_scripts and admin_enqueue_scripts to make it more compatible with translation plugins IOK 2026-02-02
    public function script_add_vippslocale () {
        // This is actually for the payment block, where localize script has started to not-work in certain contexts. IOK 2022-12-13
        $strings = array('Continue with Vipps'=>sprintf(__('Continue with %1$s', 'woo-vipps'), $this->get_payment_method_name()),'Vipps'=> sprintf(__('%1$s', 'woo-vipps'), $this->get_payment_method_name()));
        wp_localize_script('vipps-gw', 'VippsLocale', $strings);
    }

    public function wp_enqueue_scripts() {
        wp_localize_script('vipps-gw', 'VippsConfig', $this->vippsJSConfig);
        // Add certain translations very late so translation plugins get a chance to work. IOK 2026-02-02
        $this->script_add_vippslocale();

        wp_enqueue_script('vipps-gw');
        wp_enqueue_style('vipps-gw',plugins_url('css/vipps.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/vipps.css"));
        $badges = get_option('vipps_badge_options');
        // Only enqueue in the front-end if badges are actually on. IOK 2025-07-25
        if ($badges && ($badges['badgeon'] ?? false)) {
            wp_enqueue_script('vipps-onsite-messageing',"https://checkout.vipps.no/on-site-messaging/v1/vipps-osm.js",array(),WOO_VIPPS_VERSION,
           array(
                'in_footer' => true,
                'strategy'  => 'async',
            ));
        }
    }


    public function add_shortcodes() {
        add_shortcode('woo_vipps_buy_now', array($this, 'buy_now_button_shortcode'));
        add_shortcode('woo_vipps_express_checkout_button', array($this, 'express_checkout_button_shortcode'));
        add_shortcode('woo_vipps_express_checkout_banner', array($this, 'express_checkout_banner_shortcode'));

        // Badges, if using shortcodes
        // New vipps-mobilepay-badge shortcode. LP 19.11.2024
        add_shortcode('vipps-mobilepay-badge', array($this, 'vipps_mobilepay_badge_shortcode'));
        // Legacy vipps-badge shortcode. LP 19.11.2024
        add_shortcode('vipps-badge', array($this, 'vipps_badge_shortcode'));
    }


    public function log ($what,$type='info') {
        $logger = function_exists('wc_get_logger') ? wc_get_logger() : false;
        if ($logger) {
            $context = array('source'=>'woo-vipps');
            $logger->log($type,$what,$context);
        } else {
            error_log("woo-vipps ($type): $what");
        }
    }


    // If we have admin-notices that we haven't gotten a chance to show because of
    // a redirect, this method will fetch and show them IOK 2018-05-07
    public function stored_admin_notices() {
        $stored = get_transient('_vipps_save_admin_notices');
        if ($stored) {
            delete_transient('_vipps_save_admin_notices');
            print $stored;
        }
        do_action('vipps_admin_notices');
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
        $linktext = 'Express'; // dont translate. LP 2025-09-03
        $logo = $this->get_express_banner_logo();
        $payment_method = $this->get_payment_method_name();

        $img_classes = 'express-banner-logo inline negative ' . strtolower($payment_method) . '-logo';
        $div_classes = 'woocommerce-info ' . strtolower($payment_method) . '-info';
        $a_classes = 'express-banner-link ' . strtolower($payment_method) . '-link';

        $message = $text . "<a href='$url' class='$a_classes'><img class='$img_classes' border=0 src='$logo' alt='$payment_method'/>$linktext!</a>";
        $message = apply_filters('woo_vipps_express_checkout_banner', $message, $url, $payment_method);
        ?>
        <div class="<?php echo $div_classes;?>"><?php echo $message;?></div>
            <?php
    }

    // Show the express button if reasonable to do so
    public function cart_express_checkout_button() {
        $gw = $this->gateway();

        if ($gw->show_express_checkout()){
            return $this->cart_express_checkout_button_html();
        }
    }

    public function minicart_express_checkout_button() {
        $gw = $this->gateway();

        if ($gw->show_express_checkout()){
            return $this->cart_express_checkout_button_html(true);
        }
    }

    public function cart_express_checkout_button_html($minicart = false) {
        $url = $this->express_checkout_url();
        $url = wp_nonce_url($url,'express','sec');
        $page = $minicart ? 'minicart' : 'cart';
        $imgurl= apply_filters('woo_vipps_express_checkout_button', $this->get_payment_logo($page));
        $method = $this->get_payment_method_name();
        $title = sprintf(__('Buy now with %1$s!', 'woo-vipps'), $method);
        $button = "<a href='$url' class='button vipps-express-checkout short $method' title='$title'><img alt='$title' border=0 src='$imgurl'></a>";
        $button = apply_filters('woo_vipps_cart_express_checkout_button', $button, $url);
        echo $button;
    }

    // A shortcode for a single buy now button. Express checkout must be active; but I don't check for this here, as this button may be
    // cached. Therefore stock, purchasability etc will be done later. IOK 2018-10-02
    public function buy_now_button_shortcode ($atts) {
        $args = shortcode_atts( array( 'id' => '','variant'=>'','sku' => '',), $atts );
        return "<div class='vipps_buy_now_wrapper noloop'>".  $this->get_buy_now_button($args['id'], $args['variant'], $args['sku'], false, '', 'shortcode') . "</div>";
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
        // This is for overriding Vipps Badge settings
        if (isset($_POST['woo_vipps_show_badge'])) {
            update_post_meta($id, '_vipps_show_badge', sanitize_text_field($_POST['woo_vipps_show_badge']));
        }

        // This is for the shareable links.
        if (isset($_POST['woo_vipps_shareable_delenda'])) {
            $delenda = array_map('sanitize_text_field',$_POST['woo_vipps_shareable_delenda']);
            foreach($delenda as $delendum) {
                // This will delete the actual link
                delete_post_meta($post->ID, '_vipps_shareable_link_'.$delendum);
            }
            // Delete all legacy "shareable links" collections. IOK 2024-06-19
            delete_post_meta($post->ID, '_vipps_shareable_links');
        }
    }

    // An extra product meta tab for Vipps 
    public function woocommerce_product_data_tabs ($tabs) {
        $img =  plugins_url('img/vipps_logo.png',__FILE__);
        $tabs['vipps'] = array( 'label' =>  sprintf(__('%1$s', 'woo-vipps'), $this->get_payment_method_name()), 'priority'=>100, 'target'=>'woo-vipps', 'class'=>array());
        return $tabs;
    }
    public function woocommerce_product_data_panels() {
        global $post;
        echo "<div id='woo-vipps' class='panel woocommerce_options_panel'>";
        // IOK 2024-01-17 Temporary: Only Vipps supports express checkout, shareable links (express checkout) and badges
        // IOK 2025-09-01 Now available for all
        $this->product_options_vipps();
        $this->product_options_vipps_badges();
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
                        'label'   => sprintf(__('Add  \'Buy now with %1$s\' button', 'woo-vipps'), $this->get_payment_method_name()),
                        'desc_tip' => true,
                        'description' => sprintf(__('Add a \'Buy now with %1$s\'-button to this product','woo-vipps'), $this->get_payment_method_name())
                        ) ); 
        } else if ($choice == "all") {
          $prod = wc_get_product(get_the_ID());
          $canbebought = false;
          if (is_a($prod, 'WC_Product')) {
              $canbebought = $gw->product_supports_express_checkout(wc_get_product(get_the_ID()));
          }

          echo "<p>";
          echo sprintf(__("The %1\$s settings are currently set up so all products that can be bought with Express Checkout will have a Buy Now button.", 'woo-vipps'), Vipps::CompanyName()); 
          echo " ";
          if ($canbebought) {
            echo __("This product supports express checkout, and so will have a Buy Now button." , 'woo-vipps');
          } else {
            echo __("This product does <b>not</b> support express checkout, and so will <b>not</b> have a Buy Now button." , 'woo-vipps');
          } 
          echo "</p>";
        } else {
         $settings = esc_attr(admin_url('/admin.php?page=vipps_settings_menu'));
          echo "<p>";
          echo sprintf(__("The %1\$s settings</a> are configured so that no products will have a Buy Now button - including this.", 'woo-vipps'), Vipps::CompanyName());
          echo "</p>";
        }
        echo '</div>';
    }

    public function product_options_vipps_badges() {
        $current = get_option('vipps_badge_options');
        if (!$current || !($current['badgeon'] ?? false)) return;
        echo '<div class="options_group">';
        echo "<div class='blurb' style='margin-left:13px'><h4>";
        echo __("On-site messaging badge", 'woo-vipps') ;
        echo "<h4></div>";
        $showbadge = sanitize_text_field(get_post_meta( get_the_ID(), '_vipps_show_badge', true));

        woocommerce_wp_select( 
                array( 
                    'id'      => 'woo_vipps_show_badge', 
                    'label'   => __( 'Override default settings', 'woo-vipps' ),
                    'options' => array(
                        '' => __('Default setting', 'woo-vipps'),
                        'none' => __('No badge', 'woo-vipps'),
                        'white' => __('White', 'woo-vipps'),
                        'grey' => __('Grey', 'woo-vipps'),
                        'filled' => __('Filled', 'woo-vipps'),
                        'light' => __('Light', 'woo-vipps'),
                        'purple' => __('Purple', 'woo-vipps'),
                        ),
                    'value' => $showbadge
                    )
                );
        echo "</div>";
        
    } 

    public function product_options_vipps_shareable_link() {
        global $post;
        global $wpdb;
        $product = wc_get_product($post->ID);
        $variable = ($product->get_type() == 'variable');

        $buy_url = $this->buy_product_url();
        $q = $wpdb->prepare("SELECT meta_key, meta_value FROM `{$wpdb->postmeta}` WHERE post_id = %d AND meta_key LIKE '_vipps_shareable_link@_%' escape '@'", $product->get_id());
        $res = $wpdb->get_results($q, ARRAY_A);
        $shareables = [];
        if ($res) {
            foreach($res as $entry) {
                $shareable = maybe_unserialize($entry['meta_value']);
                if (!$shareable || empty($shareable['key'])) continue;
                $url = add_query_arg('pr',$shareable['key'],$this->buy_product_url());
                $shareable['url'] = $url;
                $shareables[] = $shareable;
            }
        }

        $qradmin = admin_url("/edit.php?post_type=vipps_qr_code");
        ?>
            <div class="options_group">
            <div class='blurb' style='margin-left:13px'>
            <h4><?php echo __("Shareable links", 'woo-vipps') ?></h4>
            <p><?php echo sprintf(__('Shareable links are links you can share externally on banners or other places that when followed will start %1$s of this product immediately. Maintain these links here for this product.', 'woo-vipps'), Vipps::ExpressCheckoutName()); ?>   </p>
            <p><?php echo sprintf(__("To create a QR code for your shareable link, we recommend copying the URL and then using the <a href='%2\$s'>%1\$s QR Api</a>", 'woo-vipps'), "Vipps", $qradmin); ?> </p>
            <input type=hidden id=vipps_sharelink_id value='<?php echo $product->get_id(); ?>'>
            <?php 
            echo wp_nonce_field('share_link_nonce','vipps_share_sec',1,false); 
        if ($variable):
            $variations = $product->get_available_variations(); 
        echo "<button id='vipps-share-link' disabled  class='button' onclick='return false;'>"; echo __("Create shareable link",'woo-vipps'); echo "</button>";
        echo "<select id='vipps_sharelink_variant'><option value=''>"; echo __("Select variant", 'woo-vipps'); echo "</option>";
        foreach($variations as $var) {
            $varid = esc_attr($var['variation_id']);
            echo "<option value='$varid'>$varid"; 
            echo esc_html($var['sku']);
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
           <?php if ($variable): ?><td><?php echo esc_html($shareable['variant']); ?></td><?php endif; ?>
           <td><a class='shareable' title="<?php echo __('Click to copy','woo-vipps'); ?>" href="javascrip:void(0)"><?php echo esc_html($shareable['url']); ?></a><input class="deletemarker" type=hidden value='<?php echo esc_attr($shareable['key']); ?>'></td>
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
        static::set_locale_if_in_header();
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

        echo json_encode(array('ok'=>1,'msg'=>'ok', 'url'=>$url, 'variant'=> $varname, 'key'=>$key));
        wp_die();
    }

    // A metabox for showing Vipps information about the order. IOK 2018-05-07
    public function add_vipps_metabox ($post_or_order_object) {
        $order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
        $order = wc_get_order($post_or_order_object);
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
        $cancelled = intval($order->get_meta('_vipps_cancelled'));

        $capremain = intval($order->get_meta('_vipps_capture_remaining'));
        $refundremain = intval($order->get_meta('_vipps_refund_remaining'));

        $paymentdetailsnonce=wp_create_nonce('paymentdetails');

        $failures = intval($order->get_meta('_vipps_capture_failures'));

        $currency = $order->get_currency();

        print "<table border=0><thead></thead><tbody>";
        print "<tr><td colspan=2>"; print $order->get_payment_method_title();print "</td></tr>";
        print "<tr><td>Status</td>";
        print "<td align=right>" . htmlspecialchars($status);print "</td></tr>";
        print "<tr><td>Amount</td><td align=right>" . sprintf("%0.2f ",$total/100); print $currency; print "</td></tr>";
        print "<tr><td>Captured</td><td align=right>" . sprintf("%0.2f ",$captured/100); print $currency; print "</td></tr>";
        print "<tr><td>Refunded</td><td align=right>" . sprintf("%0.2f ",$refunded/100); print $currency; print "</td></tr>";
        print "<tr><td>Cancelled</td><td align=right>" . sprintf("%0.2f ",$cancelled/100); print $currency; print "</td></tr>";

        if ($failures) {
          print("<tr><td>Capture attempts</td><td align=right>$failures</td></tr>");
        }

        print "<tr><td>Vipps initiated</td><td align=right>";if ($init) print date('Y-m-d H:i:s',$init); print "</td></tr>";
        print "<tr><td>Vipps response </td><td align=right>";if ($callback) print date('Y-m-d H:i:s',$callback); print "</td></tr>";
        print "<tr><td>Vipps capture </td><td align=right>";if ($capture) print date('Y-m-d H:i:s',$capture); print "</td></tr>";
        print "<tr><td>Vipps refund</td><td align=right>";if ($refund) print date('Y-m-d H:i:s',$refund); print "</td></tr>";
        print "<tr><td>Vipps cancelled</td><td align=right>";if ($cancel) print date('Y-m-d H:i:s',$cancel); print "</td></tr>";
        print "</tbody></table>";
        print "<a href='javascript:VippsGetPaymentDetails($orderid,\"$paymentdetailsnonce\");' class='button'>" . __('Show complete transaction details','woo-vipps') . "</a>";
    }


    // Vipps' requirement for phone numbers is very strict, and payments initiated with
    // numbers in any other format will fail. Therefore we must try to convert to MSISDN before that.
    public static function normalizePhoneNumber($phone, $country='') {
        $phonenr = preg_replace("![^0-9]!", "",  strval($phone));
        $phonenr = preg_replace("!^0+!", "", $phonenr);

        // Try to reconstruct phone numbers from information provided
        if (strlen($phonenr) == 8 && $country == 'NO') { 
            $phonenr = '47' . $phonenr;
        }

        if (!preg_match("/^\d{10,15}$/", $phonenr)) {
            $phonenr = false;
        }
        return $phonenr;
    }


    // This is for debugging and ensuring we have excact details correct for a transaction.
    public function ajax_vipps_payment_details() {
        check_ajax_referer('paymentdetails','vipps_paymentdetails_sec');
        static::set_locale_if_in_header();
        $orderid = intval($_REQUEST['orderid']);
        $gw = $this->gateway();
        $order = wc_get_order($orderid);
        if (!$order) {
            print "<p>" . __("Unknown order", 'woo-vipps') . "</p>";
            exit();
        }
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            print "<p>" . sprintf(__("The order is not a %1\$s order", 'woo-vipps'), $this->get_payment_method_name()) . "</p>";
            exit();
        }

        $gw = $this->gateway();
        try {
            $details = $gw->get_payment_details($order);

            if ($details) {
               try {
                   $details['epaymentLog'] =  $gw->api->epayment_get_payment_log ($order);
               } catch (Exception $e) {
                   $this->log("Could not get transaction log for " . $order->get_id() . " : " . $e->getMessage(), 'error');
               }
            }
            $order->update_meta_data('_vipps_capture_failures', 0); // Reset this if getting full data
            $order =   $gw->update_vipps_payment_details($order, $details);
        } catch (Exception $e) {
            print "<p>"; 
            print __('Transaction details not retrievable: ','woo-vipps') . $e->getMessage();
            print "</p>";
            exit();
        }

        print "<h2>" . __('Transaction details','woo-vipps') . "</h2>";
        print "<p>";
        print __('Order id', 'woo-vipps') . ": " . @$details['orderId'] . "<br>";
        print __('Order status', 'woo-vipps') . ": " .@$details['status'] . "<br>";
        if (isset($details['paymentMethod'])) {
            $method = (is_array($details['paymentMethod'])) ? $details['paymentMethod']['type'] : "";
            print __("Payment method", 'woo-vipps') . ":" . $method . "<br>";
        } else {
            print __("Payment method", 'woo-vipps') . ": Vipps <br>";
        }
        print __("API", 'woo-vipps') .": " . esc_html($order->get_meta('_vipps_api')) . "</br>";

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
            $addr = isset($ss['address']) ? $ss['address'] : array();
            print "<h3>" . __('Shipping details', 'woo-vipps') . "</h3>";
            print __('Address', 'woo-vipps') . ": " . htmlspecialchars(join(', ', array_filter(array_values($addr), 'is_scalar'))) . "<br>";
            if (@$ss['shippingMethod']) print __('Shipping method', 'woo-vipps') . ": " . htmlspecialchars(@$ss['shippingMethod']) . "<br>"; 
            if (@$ss['shippingCost']) print __('Shipping cost', 'woo-vipps') . ": " . @$ss['shippingCost'] . "<br>";
            print __('Shipping method ID', 'woo-vipps') . ": " . htmlspecialchars(@$ss['shippingMethodId']) . "<br>";
            if (isset($ss['pickupPoint'])) {
               $pp = $ss['pickupPoint'];
               print "<h3>" . __('Pickup Point', 'woo-vipps') . "</h3>";
               print $pp['name'] . "<br>";
               print $pp['address'] . "<br>";
               print $pp['postalCode'] . " ";
               print $pp['city'] . "<br>";
               print $pp['country'] . "<br>";
            }
        }
        if (!empty(@$details['billingDetails'])) {
            $us = $details['billingDetails'];
            print "<h3>" . __('Billing details', 'woo-vipps') . "</h3>";
            print __('First Name', 'woo-vipps') . ": " . htmlspecialchars(@$us['firstName']) . "<br>"; 
            print __('Last Name', 'woo-vipps') . ": " . htmlspecialchars(@$us['lastName']) . "<br>";
            print __('Mobile Number', 'woo-vipps') . ": " . htmlspecialchars(@$us['phoneNumber']) . "<br>";
            print __('Email', 'woo-vipps') . ": " . htmlspecialchars(@$us['email']) . "<br>";
        }
        // Checkout v3: No userDetails, but Vipps email may be present
        if (!empty(@$details['userInfo'])) {
            $us = $details['userInfo'];
            print "<h3>" . __('User details', 'woo-vipps') . "</h3>";
            print __('Email', 'woo-vipps') . ": " . htmlspecialchars(@$us['email']) . "<br>";
        } else if (!empty(@$details['userDetails'])) {
            // Older versions of the api, as well as express checkout has "userDetails"
            $us = $details['userDetails'];
            print "<h3>" . __('User details', 'woo-vipps') . "</h3>";
            print __('User ID', 'woo-vipps') . ": " . htmlspecialchars(@$us['userId']) . "<br>";
            print __('First Name', 'woo-vipps') . ": " . htmlspecialchars(@$us['firstName']) . "<br>"; 
            print __('Last Name', 'woo-vipps') . ": " . htmlspecialchars(@$us['lastName']) . "<br>";
            print __('Mobile Number', 'woo-vipps') . ": " . htmlspecialchars(@$us['mobileNumber']) . "<br>";
            print __('Email', 'woo-vipps') . ": " . htmlspecialchars(@$us['email']) . "<br>";
        }
        if (!empty(@$details['epaymentLog']) && is_array($details['epaymentLog'])) {
            print "<h3>" . __('Transaction Log', 'woo-vipps') . "</h3>";
            $i = count($details['epaymentLog'])+1; 
            $reversed = array_reverse($details['epaymentLog']);
            foreach ($reversed  as $td) {
                print "<br>";
                print __('Operation','woo-vipps') . ": " . htmlspecialchars(@$td['name']) . "<br>";
                $value = intval(@$td['amount']['value'])/100;
                $curr = $td['amount']['currency'];

                print __('Amount','woo-vipps') . ": " . esc_html($value) . " " . esc_html($curr) . "<br>";
                print __('Success','woo-vipps') . ": " . @$td['success'] . "<br>";
                print __('Timestamp','woo-vipps') . ": " . htmlspecialchars(@$td['timestamp']) . "<br>";
                print __('Transaction ID','woo-vipps') . ": " . htmlspecialchars(@$td['pspReference']) . "<br>";
            }
        }
        exit();
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
        $fname = 'vipps-'.md5($order->get_order_key() . $order->get_meta('_vipps_transaction')) . ".txt";
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
    // If the order is locked, it means it is in the process of being finalized, so for instance, we do *not* want to abandon it
    // in checkout.
    public function isLocked ($order) {
        $orderid = $order->get_id();
        $locked = get_transient('order_lock_'.$orderid);
        return apply_filters('woo_vipps_order_locked', $locked, $order);
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
    // IOK: This needs to be replaced by a separate table, but in the meantime, we will use
    // wc_get_orders and not $wpdb directly, so it should work with HPOS too.
    // IOK 2023-01-23 this function is no longer used, and kept only for backwards compatibility with
    // debug filters and similar.
    public function getOrderIdByVippsOrderId($vippsorderid) {
        // Ensure the old order table understands the meta query IOK 2022-12-02
        static::add_wc_order_meta_key_support();
        $result = wc_get_orders( array(
            'limit' => 1,
            'return' => 'ids',
            'meta_vipps_orderid' => $vippsorderid,
            /* The above, with the filter, is for the old orders table, the below is for the new IOK 2022-12-02 */
            'meta_query' =>  [[ 'key'   => '_vipps_orderid', 'value' => $vippsorderid ]]
        ));
        if ($result && is_array($result)) return $result[0];

        return 0;
    }

    // This is like getOrderByVipsOrderId, but only fetches pending orders.
    // This is used for the webhooks, where there is no way to add our own order info. IOK 2023-12-19
    private function get_pending_vipps_order($vippsorderid) {
        if ($this->useHPOS()) {
            $sevendaysago = time() - (60*60*24*7);
            $result = wc_get_orders( array(
                        'limit' => 1,
                        'status' => 'wc-pending',
                        'type' => 'shop_order',
                        'payment_method' => 'vipps',
                        'date_created' => '>' . $sevendaysago,
                        'return' => 'objects',
                        'meta_query' =>  [[ 'key'   => '_vipps_orderid', 'value' => $vippsorderid ]]
                        ));
            if (!empty($result) && is_a($result[0], 'WC_Order')) return $result[0];
            return null;
        } else {
            global $wpdb;
            $q = $wpdb->prepare("SELECT p.ID from `{$wpdb->posts}` p JOIN `{$wpdb->postmeta}` m ON (m.post_id = p.ID and m.meta_key = '_vipps_orderid') WHERE p.post_type = 'shop_order' &&  p.post_status = 'wc-pending' AND m.meta_value = %s LIMIT 1", $vippsorderid);
            $res = $wpdb->get_results($q, ARRAY_A);
            if (empty($res)) return null;
            $o = wc_get_order($res[0]['ID']);
            if (is_a($o, 'WC_Order')) return $o;
            return null;
        }
    }


    // If this is a special page, return true very early because we are handling this. IOK 2023-02-22
    public function pre_handle_404($current, $query) {
        if (!is_admin()) {
            $special = $this->is_special_page();
            if ($special) {
                // Ensure very early on that Autooptimize does not try to optimize us (if installed) IOK 2023-03-04
                add_filter( 'autoptimize_filter_noptimize', '__return_true');
                return true;
            }
        }
        return $current;
    }

    // Special pages, and some callbacks. IOK 2018-05-18 
    public function template_redirect() {
        global $post;
        // Handle special callbacks
        $special = $this->is_special_page() ;

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
    public function woocommerce_before_thankyou ($orderid) {
        $order = wc_get_order($orderid);
        if ($order) {
            // Requires that this is express checkout and that 'create users on express checkout' is chosen. IOK 2020-10-09
            // -- or the same thing for Vipps Checkout. Also, the NHG code should not be running, and there is a filter, too. IOK 2023-08-04
            $this->maybe_log_in_user($order);
            $order->delete_meta_data('_vipps_limited_session');
            $order->save();

            // Now if this was express checkout and we are a guest, ensure we have the correct email in the session->customer array IOK 2023-07-17
            if (! is_user_logged_in() ) {
                $this->maybe_set_session_customer_email($order);
            }
        }
        $this->maybe_restore_cart($orderid);

        WC()->session->set('current_vipps_session', false);
        WC()->session->set('vipps_checkout_current_pending',false);
        WC()->session->set('vipps_address_hash', false);
        do_action('woo_vipps_before_thankyou', $orderid, $order);
    }
    public function woocommerce_loaded() {
        // Ended buy-now product block support for allproducts block. LP 29.11.2024    

        /* This is for the other product blocks - here we only have a single HTML filter unfortunately */
        add_filter('woocommerce_blocks_product_grid_item_html', function ($html, $data, $product) {
            if (!$this->loop_single_product_is_express_checkout_purchasable($product)) return $html; 
            $stripped = preg_replace("!</li>$!", "", $html);
            $pid = $product->get_id();
            $button = '<div class="wp-block-button wc-block-components-product-button wc-block-button-vipps">';
            $button .= $this->get_buy_now_button($pid,false, null, false, '', 'catalog');
            $button .= '</div>';
            return $stripped . $button . "</li>";
        }, 10, 3);

        // If local pickup has been added to express/checkout by filters, add this to emails/confirmation pages. IOK 2025-08-15
        add_filter('woocommerce_order_shipping_to_display', function($shipping, $order, $tax_display) {
                if (!is_a($order, 'WC_Order')) return $shipping;
                if ($order->get_payment_method() != 'vipps') return $shipping;
                $shipping_method = current( $order->get_shipping_methods() );

                if (empty($shipping_method))  return $shipping;

                // Handled by Woo Central IOK 2025-08-15
                if ('pickup_location' == $shipping_method->get_method_id()) {
                    return $shipping;
                }


                $details         = trim($shipping_method->get_meta( 'pickup_details' ));
                $location        = trim($shipping_method->get_meta( 'pickup_location' ));
                $address         = trim($shipping_method->get_meta( 'pickup_address' ));

                if (!empty($location) || !empty($address)) {
                   $shipping .= "<br><strong>" .  __( 'Pickup location', 'woocommerce' ) . ":</strong>";
                }
                if (!empty($location))  $shipping .= esc_html($location);
                if (!empty($address)) $shipping .= "<br>" . esc_html($address);
                if (!empty($details)) $shipping .= "<br><small>" . esc_html($details) . "</small>";

                return $shipping;
            }, 10, 3);


        // Support adding pickup locations to any shipping rate using the 'woo_vipps_shipping_method_pickup_points' filter
        // IOK 2025-11-19
        add_filter('woo_vipps_modify_express_checkout_rate', array($this, 'express_add_pickup_location_options'), 10, 4);

    }

    public function get_payment_method_name() {
        return $this->gateway()->get_option('payment_method_name');
    }

    public function plugins_loaded() {
        /* The gateway is added at 'plugins_loaded' and instantiated by Woo itself. IOK 2018-02-07 */
        add_filter( 'woocommerce_payment_gateways', array($this,'woocommerce_payment_gateways' ));
        /* Try to get a list of all installed gateways *before* we instantiate our own IOK 2024-05-27 */
        add_filter( 'woocommerce_payment_gateways', function ($gws) {
           if (!empty(Vipps::$installed_gateways)) return Vipps::$installed_gateways;
           Vipps::$installed_gateways = $gws;
           return $gws;
        }, 99999);
    }

    public function after_setup_theme() {
        // To facilitate development, allow loading the plugin-supplied translations. Must be called here at the earliest.
        $ok = Vipps::load_plugin_textdomain('woo-vipps', false, basename( dirname( dirname( __FILE__ ) ) ) . "/languages");

        // Vipps Checkout replaces the default checkout page, and currently uses its own  page for this which needs to exist
        // Will also probably be used to maintain a real utility-page for Vipps actions later for themes where this
        // is important.
        add_filter('woocommerce_create_pages', array($this, 'woocommerce_create_pages'), 50, 1);


        // Callbacks use the Woo API IOK 2018-05-18
        add_action( 'woocommerce_api_wc_gateway_vipps', array($this,'vipps_callback'));
        add_action( 'woocommerce_api_vipps_shipping_details', array($this,'vipps_shipping_details_callback'));

        // Currently this sets Vipps as default payment method if hooked. IOK 2018-06-06 
        add_action( 'woocommerce_cart_updated', array($this,'woocommerce_cart_updated'));

        // Template integrations
        add_action( 'woocommerce_cart_actions', array($this, 'cart_express_checkout_button'));
        add_action( 'woocommerce_widget_shopping_cart_buttons', array($this, 'minicart_express_checkout_button'), 30);
        add_action('woocommerce_before_checkout_form', array($this, 'before_checkout_form_express'), 5);

        add_action('woocommerce_after_add_to_cart_button', array($this, 'single_product_buy_now_button'));
        add_action('woocommerce_after_shop_loop_item', array($this, 'loop_single_product_buy_now_button'), 20);


        // Special pages and callbacks handled by template_redirect
        // We must also notify WP and other plugins that we are handling this 404-like situation. IOK 2023-02-22
        add_action('template_redirect', array($this,'template_redirect'),1);
        add_action('pre_handle_404', array($this, 'pre_handle_404'), 1, 2);

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

        // Handle the cancel unpaid order action when the "hold stock" times out.
        // For *normal* vipps orders, we run another cronjob every 5. minute which checks order status,
        // therefore here it suffices to check if the order is 'cancelled' at Vipps, and if so we return.
        // For Checkout the rules are different though.
        add_filter('woocommerce_cancel_unpaid_order', function ($cancel, $order) {

            // If we can't cancel for some other reason, don't.  
            if (!$cancel) return $cancel;

            // Only check Vipps orders
            if ($order->get_payment_method() != 'vipps') return $cancel;

            // For Vipps, all unpaid orders must be pending. IOK FIXME ADD FAILED
            if ($order->get_status() != 'pending') return $cancel;

            // Handle this separately, in the Checkout class. IOK 2025-10-08
            $checkout_session = $order->get_meta('_vipps_checkout_session');
            if ($checkout_session) {
                try {
                     $polldata = $this->gateway()->api->checkout_get_session_info($order);
                     $sessionState = (!empty($polldata) && is_array($polldata) && isset($polldata['sessionState'])) ? $polldata['sessionState'] : "";
                     // We can cancel the order iff we haven't started payment yet.
                     if ($sessionState == 'PaymentSuccessful' || $sessionState == 'PaymentInitiated') return false; 
                     return true;
                } catch (Exception $e) {
                     // If Vipps is unreachable, be safe and don't delete
                     return false;
                }
                return false; 
            }
  
            try {
                $result = $this->gateway()->api->epayment_get_payment($order);
            } catch (Exception $e) {
                $this->log(sprintf(__("Cannot get status of %1\$d at %2\$s in woocommerce_cancel_unpaid_order, not allowing deletion: %3\$s", 'woo-vipps'), $order->get_id(), Vipps::CompanyName(), $e->getMessage()));
                return false;
            }

            // We should now have an object with the 'state' in one of the Vipps states. We'll translate all of them to 
            // cancelled or nah, and if cancelled, we allow deletion. IOK 2025-10-07
            if (empty($result)) return true; 
            $state = $this->gateway()->interpret_vipps_order_status($result['state'] ?? 'CANCEL');
            if (empty($state) || $state == 'cancelled') return true;
            
            return false;

        }, 20, 2);

        // Used both in admin and non-admin-scripts, load as quick as possible IOK 2020-09-03
        $this->vippsJSConfig = array();
        $this->vippsJSConfig['vippsajaxurl'] =  admin_url('admin-ajax.php');
        $this->vippsJSConfig['BuyNowWith'] = __('Buy now with', 'woo-vipps');
        $this->vippsJSConfig['BuyNowWithVipps'] = sprintf(__('Buy now with %1$s', 'woo-vipps'), $this->get_payment_method_name());
        $this->vippsJSConfig['vippslogourl'] = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $this->vippsJSConfig['vippssmileurl'] = plugins_url('img/vmp-logo.png',__FILE__);
        $this->vippsJSConfig['vippsbuynowbutton'] = sprintf(__( '%1$s Buy Now button', 'woo-vipps' ), $this->get_payment_method_name());
        $this->vippsJSConfig['vippsbuynowdescription'] =  sprintf(__( 'Add a %1$s Buy Now-button to the product block', 'woo-vipps'), $this->get_payment_method_name());
        $this->vippsJSConfig['vippslanguage'] = $this->get_customer_language();
        $this->vippsJSConfig['vippslocale'] = get_locale();
        $this->vippsJSConfig['vippsexpressbuttonurl'] = $this->get_payment_method_name();
        $this->vippsJSConfig['logoSvgUrl'] = $this->get_payment_logo('buy-now-block');
       

        // If the site supports Gutenberg Blocks, support the Checkout block IOK 2020-08-10
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once(dirname(__FILE__) . "/Blocks/Payment/Vipps.class.php");
            Automattic\WooCommerce\Blocks\Payments\Integrations\Vipps::register();
        }

        // Used for e.g. labels of product/shipping metadata. IOK 2025-05-07
        add_filter('woocommerce_attribute_label', function ($label, $name, $product) {
            if ( $product ) {
                return $label;
            }
            switch ( $name ) {
                case 'brand': // This is for shipping IOK 2025-05-07
                    return __('Company', 'woo-vipps');
                case 'type':
                    return __('Type', 'woo-vipps');
                case 'vipps_delivery_timeslot':
                    return __('Timeslot', 'woo-vipps');
                case 'vipps_delivery_timeslot_id':
                    return __('Timeslot ID', 'woo-vipps');
            }
            return $label;
        }, 9, 3);


    }

    // IOK 2021-12-09 try to get the current language in the format Vipps wants, one of 'en' and 'no'
    // IOK 2025-09-03 stop trying to get the logged-in users language - it does not seem to work especially well in newer woos.
    public function get_customer_language() {
        global $TRP_LANGUAGE; // TranslatePress IOK 2025-11-06

        $language = substr(get_bloginfo('language'),0,2);
        if (function_exists('pll_current_language')) {
            $pll_language = pll_current_language('slug');
            if ($pll_language) $language = $pll_language;
        } elseif (has_filter('wpml_current_language')){
            $language=apply_filters('wpml_current_language',null);
        }  elseif (!empty($TRP_LANGUAGE)) {
            $language = sanitize_title($TRP_LANGUAGE);
        }
        // Just to be sure.
        $language = strtolower($language);

        // Allow others to override in case they have some unorthodox setups IOK 2025-11-12
        $language = apply_filters('woo_vipps_customer_language', $language);

        if ($language == 'nb' || $language == 'nn') $language = 'no';
        if ($language == 'da') $language = 'dk';
        if ($language == 'sv') $language = 'se';
        if (! in_array($language, ['en', 'no', 'dk', 'fi', 'se'])) $language = 'en';
        return $language;
    }

    // Called by ajax on the order page; redirects back to same page. IOK 2022-11-02
    public function order_handle_vipps_action () {
           check_ajax_referer('vippssecnonce','vipps_sec');
           static::set_locale_if_in_header();
           $order = wc_get_order(intval($_REQUEST['orderid']));
           if (!is_a($order, 'WC_Order')) return;
           $pm = $order->get_payment_method();
           if ($pm != 'vipps') return;
 
           $action = isset($_REQUEST['do']) ? sanitize_title($_REQUEST['do']) : 'none';

           if ($action == 'do_capture') {
               $gw = $this->gateway();
               $ok = $gw->maybe_capture_payment($order->get_id());
           }
           if ($action == 'refund_superfluous') {
               $gw = $this->gateway();
               $ok = $gw->refund_superfluous_capture($order);
           }
           print "1";
    }

    // Rest route: returns wc products, but only those purchasable by VMP express checkout. LP 2026-01-22
    // Called by the buy-now express block. LP 2026-01-22
    public function rest_express_checkout_products($request) {
        static::set_locale_if_in_header();

        // Redirect product fetch to WC rest api. LP 2026-01-23
        $wc_request = new WP_REST_Request('GET', '/wc/store/v1/products');
        $wc_request->set_query_params($request->get_query_params());
        $response = rest_do_request($wc_request);
        if ($response->is_error()) {
                return $response;
        }
        $products = $response->get_data();

        // Extract variant products out from the parent product, so we can support these. LP 2026-01-22
        foreach($products as &$product) {
            if (!(isset($product['variations']) && is_array($product['variations']) && $product['variations'])) continue;

            foreach($product['variations'] as $variation) {
                $v = wc_get_product($variation->id);
                if (!is_a($v, 'WC_Product')) continue;
                $products[] = [
                'is_variation' => true,
                'parent' => $product['id'],
                'id' => $v->get_id(),
                'sku' => $v->get_sku(),
                'type' => $v->get_type(),
                'slug' => $v->get_slug(),
                'name' => $v->get_name()
                ];
            }
        }

        // Filter only Express-purchaseable products, variant parents should also be removed here. LP 2026-01-22
        $filtered_products = array_filter($products, fn($p) => $this->loop_single_product_is_express_checkout_purchasable(wc_get_product($p['id'])));
        // Reindex array to fix output. LP 2026-01-22
        $filtered_products = array_values($filtered_products);
        $response->set_data($filtered_products);
        return $response;

    }

    // Make admin-notices persistent so we can provide error messages whenever possible. IOK 2018-05-11
    public function store_admin_notices() {
        // WooCommerce will (now) call this function in the inject_before_notices method. If it does not exist,
        // we get a crash. If there is no "current screen", then we cannot provide these.
        if (!function_exists('get_current_screen')) return false;
        ob_start();
        do_action('vipps_admin_notices');
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
            print "<div><strong>" . sprintf(__("The entire amount has been captured at %1\$s", 'woo-vipps'), $this->get_payment_method_name()) . "</strong></div>";
            return;
        }

        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);

        print '<button type="button" class="button vippsbutton generate-items vipps-action" 
                 data-orderid="' . $order->get_id() . '" data-action="do_capture"
                 style="background-color:#ff5b24;border-color:#ff5b24;color:#ffffff" >
                <img border=0 style="display:inline;height:2ex;vertical-align:text-bottom" class="inline" alt=0 src="'.$logo.'"/> ' . __('Capture payment','woo-vipps') . '</button>';

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
        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        print "<div><strong>" . sprintf(__('More funds than the order total has been captured at %1$s. Press this button to refund this amount at %1$s without editing this order', 'woo_vipps'), $this->get_payment_method_name()) . "</strong></div>";
        print '<button type="button" class="button vippsbutton generate-items vipps-action" 
                 data-orderid="' . $order->get_id() . '" data-action="refund_superfluous"
                 style="background-color:#ff5b24;border-color:#ff5b24;color:#ffffff" >
                <img border=0 style="display:inline;height:2ex;vertical-align:text-bottom" class="inline" alt=0 src="'.$logo.'"/> ' . __('Refund superfluous payment','woo-vipps')  . '</button>';

    } 


    // This is the main callback from Vipps when payments are returned. IOK 2018-04-20
    public function vipps_callback() {
        $this->log("Callback received");

      	Vipps::nocache();
        // Required for Checkout, we send this early as error recovery here will be tricky anyhow.
        status_header(202, "Accepted");


        $raw_post = @file_get_contents( 'php://input' );
        $result = @json_decode($raw_post,true);

        // This handler handles both Vipps Checkout and Vipps ECom IOK 2021-09-02
        // .. and the epayment webhooks 2023-12-19
        $ischeckout = false;
        $iswebhook = false;
        $callback = isset($_REQUEST['callback']) ?  $_REQUEST['callback'] : "";
        // For Vipps Checkout v3 and onwards, we control the callback so the type is just this field
        if ($callback == 'checkout') {
            $ischeckout = true;
        } 
        // For the webhooks, we will add 'webhook' to the result, but we also know that 'pspReference' will be present. IOK 2023-12-19
        if ($callback == 'webhook' || (!$ischeckout && ($result['pspReference'] ?? false))) {
            $iswebhook = true;
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
            $this->log(sprintf(__("Did not understand callback from %1\$s:",'woo-vipps'), $this->get_payment_method_name()) . " " .  $raw_post, 'error');
            $this->log(sprintf(__("Error was: %1\$s",'woo-vipps'), $error));
            return false;
        }

        // For testing sites that appear not to receive callbacks
        if (isset($result['testing_callback'])) {
            $this->log(__("Received a test callback, exiting" , 'woo-vipps'), 'debug');
            print '{"status": 1, "msg": "Test ok"}';
            exit();
        }

        // If this is a webhook call, we need to verify it, check that it is one of the 'callback' webhooks, check that we still have a pending
        // order for it, normalize the callback data and then handle the callback. IOK 2023-12-21
        if ($iswebhook) {
            // The webhook payloads spell the msn differently. IOK 2023-12-21
            $msn = ($result['msn'] ?? '') ? $result['msn'] : ($result['merchantSerialNumber'] ?? '');
            if ($msn) {
                $result['msn'] = $msn;
                $result['merchantSerialNumber'] = $msn;
            }
            $hookdata = $this->gateway()->get_local_webhook($msn);
            $secret = $hookdata ? ($hookdata['secret'] ?? false) : false;
            if (!$secret) {
                $this->log(sprintf(__('Cannot verify webhook callback for order %1$s - this shop does not know the secret. You should delete all unwanted webhooks. If you are using the same MSN on several shops, this callback is probably for one of the others.', 'woo-vipps'), $vippsorderid), 'debug');
                return false;
            }
            $verified = $this->verify_webhook($raw_post, $secret);
            if (!$verified) {
                $this->log(sprintf(__('Cannot verify webhook callback for order %1$s - signature does not match. This may be an attempt to forge callbacks', 'woo-vipps'), $vippsorderid), 'debug');
                return;
            }

            // We need to check if this is a payment event, or if not, and if it is, if it is one of the ones we are prepared to handle. IOK 2023-12-21
            $event = $result['name'] ?? '';
            $payment_events = ["CREATED", "ABORTED", "EXPIRED", "CANCELLED", "CAPTURED", "REFUNDED", "AUTHORIZED", "TERMINATED"];
            $callback_events = ["ABORTED","EXPIRED", "AUTHORIZED",  "TERMINATED"];

            // If this is a payment event, we should have an order too so try to retrieve it. IOK 2023-12-21
            $order = null;
            $pending = false;
            if ($vippsorderid && $msn && in_array($event, $payment_events)) {
                // Then check if the reference/vippsorderid is a pending order
                $order =  $this->get_pending_vipps_order($vippsorderid);
                if ($order) {
                    $pending = true;
                } else {
                    // If it isn't, but it is a payment event, get the order id from the epayment metadata. IOK 2023-12-21
                    try {
                        $polldata = $this->gateway()->api->epayment_get_payment($vippsorderid, $msn);
                        if ($polldata && isset($polldata['metadata'])) {
                            $orderid = $polldata['metadata']['orderid'];
                            if ($orderid) {
                                $order = wc_get_order($orderid);
                                if (!$order || $vippsorderid != $order->get_meta('_vipps_orderid')) {
                                    $this->log(
                                        sprintf(__('The reference %1$s and order id %2$s does not match in webhook event %3$s - callback is invalid for the order.', 'woo-vipps'),
                                        $vippsorderid, $orderid, $event), 'debug');
                                    $order = null;
                                    return;
                                    $order = null;
                                }
                            }
                        }
                    } catch (Exception $e) {
                      $this->log(sprintf(__("Could not get orderid of reference %2\$s from %1\$s: ", 'woo-vipps'), Vipps::CompanyName(), $vippsorderid) . $e->getMessage(), 'debug');
                    }
                }
            }

            // This will run for all events, not just the one this handler handles IOK 2023-12-21
            do_action('woo_vipps_webhook_event', $result, $order);

            // We are not interested in Checkout orders - they have their own callback systems
            if ($order && $order->get_meta('_vipps_checkout')) {
                $this->log(sprintf(__('Received webhook callback for Checkout order %1$d - ignoring since full callback should come', 'woo-vipps'), $order->get_id()), 'debug');
                return;
            }
            // Now we will handle everything that is a callback event. IOK 2023-12-21
            if (!in_array($event, $callback_events)) {
                return;
            }

            if (!$pending) {
                // If the order is no longer pending, then we can safely ignore it. IOK 2023-12-21
                $this->log(sprintf(__('Received webhook callback for order %1$s but this is no longer pending.', 'woo-vipps'), $vippsorderid), 'debug');
                return; 
            }
            do_action('woo_vipps_callback_webhook', $result);

            $ok = $this->gateway()->handle_callback($result, $order, false, $iswebhook);
            if ($ok) {
                // This runs only if the callback actually handled the order, if not, then the order was handled by poll.
                do_action('woo_vipps_callback_handled_order', $order);
            }

            exit();
        }

        // This branch is only for non-webhook callbacks; which currently means Checkout only. IOK 2025-08-13
        $orderid = intval(@$_REQUEST['id']);

        if (!$orderid) {
            $this->log(sprintf(__("There is no order with this %1\$s orderid, callback fails:",'woo-vipps'), $this->get_payment_method_name()) . " " . $vippsorderid,  'error');
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

        do_action('woo_vipps_callback_checkout', $result);

        $gw = $this->gateway();

        // If neccessary, the order session will be restored in this method, and if so it will be reset before the exit happens
        // to reduce issues with users simultaneously returning to the store. IOK 2023-07-18
        $ok = $gw->handle_callback($result, $order, $ischeckout);
        if ($ok) {
            // This runs only if the callback actually handled the order, if not, then the order was handled by poll.
            do_action('woo_vipps_callback_handled_order', $order);
        }

        exit();
    }

    // Returns true iff we can verify that the webhook we just received is valid and that we know its secret IOK 2023-12-21
    public function verify_webhook($serialized, $secret) {
        // Extract the necessary headers.
        $expected_auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['HTTP_X_VIPPS_AUTHORIZATION'] ?? ""); 
        $expected_date = $_SERVER['HTTP_X_MS_DATE'] ?? '';

        // Check if the date header is present and within an acceptable range (e.g., +/- 5 minutes) NT 2023-12-22
        if (!$this->isDateValid($expected_date)) {
            return false; // Date is not valid or not within the acceptable range
        }

        // Prepare the data for signing.
        $hashed_payload = base64_encode(hash('sha256', $serialized, true));
        $path_and_query = $_SERVER['REQUEST_URI'];
        $host = $_SERVER['HTTP_HOST'];

        // Construct the string to sign.
        $toSign = "POST\n{$path_and_query}\n{$expected_date};{$host};{$hashed_payload}";

        // Generate the HMAC signature.
        $signature = base64_encode(hash_hmac('sha256', $toSign, $secret, true));

        // Construct the authorization string.
        $auth = "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature={$signature}";

        // Compare the generated auth string with the expected one. 
        // Hash_equals is used to mitigate timing attacks NT 2023-12-22
        return hash_equals($auth, $expected_auth);
    }

    // Helper function to validate the date NT 2023-12-22
    private function isDateValid($dateHeader) {
        // Define the acceptable time leeway (e.g., 5 minutes)
        $leewayInSeconds = 300;

        // Convert the header date to a Unix timestamp
        $headerTime = strtotime($dateHeader);

        // Check if the date is valid
        if ($headerTime === false) {
            return false; // Invalid date
        }

        // Get the current time
        $currentTime = time();

        // Check if the date is within the acceptable range
        return abs($currentTime - $headerTime) <= $leewayInSeconds;
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
   public function callback_restore_session ($orderid) {
        $this->callbackorder = $orderid;
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
        } else {
            $this->log(sprintf(__("Could not restore cart from session of order %1\$d", 'woo-vipps'), $orderid));
        }
        if (WC()->cart) {

            // When doing "calculate_totals" on a cart, Woo will now compare "previous shipping methods" with
            // "current shipping methods" and reset the chosen shipping methods even if it is still available. 
            // This becomes a problem because Woo only loads the pickup location methods in a few places - mostly checkout -
            // so if we chose a shipping method while these were available, we'd get ourselves reset just by calculating
            // cart totals. Fix this by saving and restoring this value. IOK 2025-11-05
            $all_chosen =  WC()->session->get( 'chosen_shipping_methods' );

            WC()->cart->set_totals( WC()->session->get( 'cart_totals', null ) );
            WC()->cart->set_applied_coupons( WC()->session->get( 'applied_coupons', array() ) );
            WC()->cart->set_coupon_discount_totals( WC()->session->get( 'coupon_discount_totals', array() ) );
            WC()->cart->set_coupon_discount_tax_totals( WC()->session->get( 'coupon_discount_tax_totals', array() ) );
            WC()->cart->set_removed_cart_contents( WC()->session->get( 'removed_cart_contents', array() ) );
            WC()->cart->set_cart_contents($newcart);
            // IOK 2020-07-01 plugins expect this to be called: hopefully they'll not get confused by it happening twice
            do_action( 'woocommerce_cart_loaded_from_session', WC()->cart);
            WC()->cart->calculate_totals(); // And if any of them changed anything, recalculate the totals again!
            // See above: Reset chosen shipping methods to avoid having it be reset by Woo for no good reason.
            if ($all_chosen) {
                WC()->session->set('chosen_shipping_methods', $all_chosen);
            }
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
       // IOK 2025-05-08 now also using the format used by Checkout in addition to Express. -- streetAddress, postalCode, region
       $defaultdata = array('addressId'=>0, "addressLine1"=>"", "addressLine2"=>"", "streetAddress"=>"", "country"=>"NO", "city"=>"", "postalCode"=>"", "postCode"=>"", "addressType"=>"Home"); 
       // IOK 2025-08-14 previously this used the customers' address if logged in or available, but that I think was a mistake - since this is intended to be static, ensure we use the base address only.
       $countries=new WC_Countries();
       $defaultdata['country'] = $countries->get_base_country();
       $defaultdata['city'] = $countries->get_base_city(); 
       $defaultdata['region'] = $countries->get_base_city(); 
       $defaultdata['postalCode'] = $countries->get_base_postcode();
       $defaultdata['postCode'] =   $countries->get_base_postcode();
       $defaultdata['streetAddress'] = $countries->get_base_address();
       $defaultdata['addressLine1'] = $countries->get_base_address();
       return $defaultdata;
   }

    // Getting shipping methods/costs for a given order to Vipps for express checkout
    public function vipps_shipping_details_callback() {
    	Vipps::nocache();

        $raw_post = @file_get_contents( 'php://input' );
        $result = @json_decode($raw_post,true);

        if (!$result) {
           if (empty(trim($raw_post))) {
               status_header(400, "Empty address info");
               print "No address";
           } else {
               status_header(400, "Invalid JSON");
               print "Invalid JSON";
           }
           $error = json_last_error_msg();
           $this->log(sprintf(__("Error getting customer data in the %1\$s shipping details callback: %2\$s",'woo-vipps'), $this->get_payment_method_name(), $error));
           $this->log(__("Raw input was ", 'woo-vipps'));
           $this->log($raw_post);
           exit();
        }

        // IOK 2025-08-15 Express Checkout (now) passes the reference/order-id in the data, but Checkout passes it in the URL, which we
        // capture in a callback= parameter added at the end. Format is
        //  '/v3/checkout/woodigitalt4780/shippingDetails'
        $vippsorderid = "";
        $callback = sanitize_text_field($_REQUEST['callback'] ?? "");
        do_action('woo_vipps_shipping_details_callback', $result,$raw_post,$callback); // This is for debugging. IOK 2025-08-15

        if ($callback) {
            $data = array_reverse(explode("/",$callback));
            $vippsorderid = !empty($data) ? ($data[1] ?? "") : ""; // Second element - callback is  /v3/checkout/woodigitalt4780/shippingDetails
        } elseif (isset($result['reference'])) {
            $vippsorderid = $result['reference'];
        }
        
        $orderid = intval($_REQUEST['id'] ?? 0);
        if (!$orderid) {
            status_header(404, "Unknown order");
            print "Unknown order";
            $this->log(sprintf(__('Could not find %1$s order with id:', 'woo-vipps'), $this->get_payment_method_name()) . " " . $vippsorderid . "\n" . __('Callback was:', 'woo-vipps') . " " . $callback, 'error');
            exit();
        }

        // This is for debugging sites where shipping handling fails because of blocks etc IOK 2026-01-15
        $this->log(sprintf(__("Received shipping callback for order %d", 'woo-vipps'), $orderid));

        do_action('woo_vipps_shipping_details_callback_order', $orderid, $vippsorderid);

        $order = wc_get_order($orderid);
        if (!$order) {
            status_header(404, "Unknown order");
            print "Unknown order";
            $this->log(__('Could not find Woo order with id:', 'woo-vipps') . " " . $orderid, 'error');
            exit();
        }
        if ($order->get_payment_method() != 'vipps') {
            status_header(400, "Invalid order");
            print "Invalid order";
            $this->log(__('Invalid order for shipping callback:', 'woo-vipps') . " " . $orderid, 'error');
            exit();
        }
        // a small bit of security
        if (!$order->get_meta('_vipps_authtoken') || (!wp_check_password($_REQUEST['tk'], $order->get_meta('_vipps_authtoken')))) {
            status_header(403, "Wrong auth");
            print "Wrong auth";
            $this->log("Wrong authtoken on shipping details callback", 'error');
            exit();
        }
        if ($vippsorderid != $order->get_meta('_vipps_orderid')) {
            status_header(400, "Invalid order id");
            print "Invalid order id";
            $this->log(sprintf(__("Wrong %1\$s Orderid on shipping details callback", 'woo-vipps'), $this->get_payment_method_name()), 'warning');
            exit();
        }

        // If we are doing this for Vipps Checkout after version 3, communicate to any shipping methods with
        // special support for Vipps Checkout that this is in fact happening. IOK 2023-01-19
        // This needs to be done before "calculate totals".
        // Moved from "vipps_shipping_details_callback_handler" because we need it before restoring sessions. IOK 2025-05-06
        $ischeckout = $order->get_meta('_vipps_checkout');

        $this->callback_restore_session($orderid);       

        // If we need to add more shipping methods *before* the shipping callback starts, it must be done before we load the session. IOK 2025-05-06
        // here we will add support for PickupLocations. Also called for static shipping.
        // IOK 2025-08-14 now also supported for Express Checkout
        $this->load_extra_shipping_methods($order, $result, $ischeckout);

        $return = $this->vipps_shipping_details_callback_handler($order, $result,$vippsorderid, $ischeckout);

        // Express checkout wants the data wrapped in a object with a 'groups' attribute, Checkout wants thing unwrapped.
        // Dispatch on the known type. IOK 2025-08-15
        if ($ischeckout) {
           $return = $return['shippingDetails'];
        } else {
            // Note that this is of course different from both Checkout and static shipping.
           $return = [ "groups" => $return ];
        }

        $json = json_encode($return);

        header("Content-type: application/json; charset=UTF-8");
        print $json;
        // Just to be sure, save any changes made to the session by plugins/hooks IOK 2019-10-22
        if (is_a(WC()->session, 'WC_Session_Handler')) WC()->session->save_data();
        exit();
    }
  
    // This function calculates and returns one of two possible JSON representations to Vipps MobilePay, one for Express and one for Checkout.
    // First, an intermediate representation is created, based on the original Express API. This is kept because users may still have filters
    // that expects this representation. Later, these are transformed and augmented for the newer APIs.  IOK 2025-08-14
    // Also used for Static Shipping for both representations.  IOK 2025-08-14
    public function vipps_shipping_details_callback_handler($order, $vippsdata,$vippsorderid, $ischeckout) {
        // This filter is used in sub-functions to keep track of what we are calculating for, without having to set globals or pass arguments. IOK 2025-08-14
        if ($ischeckout) add_filter('woo_vipps_is_vipps_checkout', '__return_true');

        // We may have an address already in the Order, and no *new* address, when recalculating shipping options after modifying the order.
        // We'll still create a $vippsdata struct so that old filters can do whatever is neccessary. IOK 2025-09-16
        $new_address = !empty($vippsdata);
        if (!$new_address) {
            $vippsdata['addressLine1'] =  $order->get_shipping_address_1();
            $vippsdata['addressLine2'] =  $order->get_shipping_address_2();
            $vippsdata['postCode'] = $order->get_shipping_postcode();
            $vippsdata['city'] = $order->get_shipping_city();
            $vippsdata['country'] = $order->get_shipping_country();
        }

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

        $country = $vippsdata['country'];
        $city = $vippsdata['city'];
        $postcode= $vippsdata['postCode'];

        // Old code here treated "Sofienberggata 12" as a special Vipps pro-forma address; this is no longer necessary.
        // If we have gotten a new address from Express or Checkout, update the order. IOK 2025-09-16.
        if ($new_address) {
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
        }

        // This is *essential* to get VAT calculated correctly. That calculation uses the customer, which uses the session.IOK 2019-10-25
        // We don't *save* this to the customer, because this may happen in a callback from Checkout where the customers' session is live and
        // the address info is from Checkout (and not necessarily the customers real address). IOK 2025-09-12
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

        // calculate_totals() overwrites the session chosen_shipping_methods to default if it think it changed,
        // which will be true if the pickup points are missing from previously. Pickup points only get loaded in woos checkout. 
        // So reset this to what it was before calling calculate_totals(). LP 2025-11-05
        // To be more specific if the *list of available methods* change, it will reset the chosen shipping method,
        // even if the chosen shipping method is actually still available. We need to call calculate_totals on the cart,
        // so we need to save + restore this.
        $chosen = null;
        $all_chosen = null; 
        if (is_a(WC()->session, 'WC_Session_Handler')) {
            $all_chosen =  WC()->session->get( 'chosen_shipping_methods' );
            if (!empty($all_chosen)) $chosen= $all_chosen[0];
        }

        //  Previously, we would create a shoppingcart at this point, because we would not have access to the 'live' one,
        // but it turns out this isn't actually possible. Any cart so created will become "the" cart for the Woo front end,
        // and anyway, some plugins override the class of the cart, so just using WC_Cart will sometimes break.
        //  Now however, the session is stored in the order, and the cart will not have been deleted, so we should
        // now be able to calculate shipping for the actual cart with no further manipulation. IOK 2020-04-08
        
        // Turns out it is possible for the session - and the cart - to have been deleted at this point, for whatever reason.
        // Login will do it, probably some other plugins as well. So if we have no cart at this point, we will ressurect the
        // probable cart based on the order. This is only neccessary because Woo will not let us calculate shipping for an *order*.
        // IOK 2024-04-09
        $cart_is_reconstructed = $this->maybe_reconstruct_cart($order->get_id());
       
        WC()->cart->calculate_totals();

        // See above. Restore chosen shipping methods if neccessary. IOK 2025-11-05
        if ($all_chosen) {
            WC()->session->set('chosen_shipping_methods', $all_chosen);
        }

        $acart = WC()->cart;

        $shipping_methods = array();
        $shipping_tax_rates = WC_Tax::get_shipping_tax_rates();


        // If no shipping is required (for virtual products, say) ensure we send *something* back IOK 2018-09-20 
        if (!$acart->needs_shipping()) {
            $no_shipping_taxes = WC_Tax::calc_shipping_tax('0', $shipping_tax_rates);
            $shipping_methods['none_required:0'] = new WC_Shipping_Rate('none_required:0',__('No shipping required','woo-vipps'),0,$no_shipping_taxes, 'none_required', 0);
        } else {
            // Ensure the shipping packages we use has the current order address IOK 2025-09-12
            $destination = [ 'country' => $country, 'state' => '', 'postcode' => $postcode, 'city'=> $city, 'address' => $addressline1, 'address_1' => $addressline1, 'address_2' => $addressline2 ];
            add_filter('woocommerce_cart_shipping_packages', function ($packages)  use($destination) {
               $new = [];
               foreach($packages as $package) {
                   $package['destination'] =  $destination;
                   $new[] = $package;
               }
               return $new;
            });

            $packages = apply_filters('woo_vipps_shipping_callback_packages', WC()->cart->get_shipping_packages());
            $shipping =  WC()->shipping->calculate_shipping($packages);

            $shipping_methods = WC()->shipping->packages[0]['rates']; // the 'rates' of the first package is what we want.
         }

        // No exit here, because developers can add more methods using the filter below. IOK 2018-09-20
        if (empty($shipping_methods)) {
            $name = $ischeckout ? Vipps::CheckoutName() : Vipps::ExpressCheckoutName();
            $this->log(sprintf(__('Could not find any applicable shipping methods for %1$s - order %2$d will fail', 'woo-vipps', 'warning'), $name, $order->get_id()), 'debug');
            $this->log(sprintf(__('Address given for %1$s was %2$s', 'woo-vipps'), $order->get_id(), 
              ($addressline1 . " " .  $addressline2 . " " .  $city . " " .  $postcode . " " .  $country)
            ), 'debug');

        }
       
        // Add shipping tax rates to the *order* so we can calculate this correctly when using Vipps Checkouts 
        // 'dynamic pricing' 2023-01-26 
        // Which may be deprecated, but anyway, for future use IOK 2025-08-14
        $taxrate = 0;
        if (is_array($shipping_tax_rates) && !empty($shipping_tax_rates)) {
          $taxrate = current($shipping_tax_rates)['rate'];
        }
        $order->update_meta_data('_vipps_shipping_tax_rates', $taxrate);

        // Merchant is using the old 'woo_vipps_shipping_methods' filter, and hasn't chosen to disable it. Use legacy methd.
        // IOK 2025-08-14 I think we should add a deprecation notice to this now. It really should not be used anymore. FIXME
        if (has_action('woo_vipps_shipping_methods') &&  $this->gateway()->get_option('newshippingcallback') != 'new') {
            return $this->legacy_shipping_callback_handler($shipping_methods, $chosen, $addressid, $vippsorderid, $order, $acart);
        }

        // Earlier we sorted shipping methods based on price; currently we just use WooCommerce's order, but we
        // provide this filter for people who would prefer the old logic.
        $shipping_methods = apply_filters('woo_vipps_sort_shipping_methods', $shipping_methods, $order);

        // IOK 2020-02-13 Ok, new method!  We are going to provide a list full of metadata for the users to process this time, which we will massage into the final Vipps method list
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
            $this->log(sprintf(__("Unavailable shipping method set as default in the %1\$s Express Checkout shipping callback - check the 'woo_vipps_default_shipping_method' filter",'debug'), $this->get_payment_method_name()));
        }

        if (!$chosen) {
            // Find first method that isn't 'local_pickup'
            // or pickup_location. IOK 2025-05-07
            foreach($methods as $key=>&$data) {
              $mid = $data['rate']->get_method_id();
              if ($mid != 'local_pickup' && $mid != 'pickup_location') {
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

        // Just to be sure, if the current cart was reconstructed from an order, we will delete it now after 
        // last use of $acart
        if ($cart_is_reconstructed) {
            WC()->cart->empty_cart();
        }

        $vippsmethods = array();

        // Just a utility from shippingMethodIds to the non-serialized rates, and from the same to the non-serialized 
        // shipping methods - the last stores settings, the first store metadata
        // The ratemap will be used to store a table in the order from an arbitrary ID key to the calculated shipping rate IOK 2025-08-15
        $ratemap = array();
        $methodmap = array();

        // We need access to the extended settings of the shipping methods.
        // This is for the 'new' local pickup feature for Woo. IOK 2025-08-14
        $methods_classes = WC()->shipping->get_shipping_method_class_names();
        $methods_classes['pickup_location'] = 'Automattic\WooCommerce\Blocks\Shipping\PickupLocation'; // Loaded using the "load" hook, after the registered methods, so we need to add it specially.

        $has_free_shipping = false;
        foreach($methods as $method) {
           $rate = $method['rate'];
           $methodid = $rate->get_method_id();

           // Extended settings are stored in these objects
           $methodclass = $methods_classes[$methodid] ?? null;
           $shipping_method = $methodclass ? new $methodclass($rate->get_instance_id()) : null;

           $tax  = $rate->get_shipping_tax() ?: 0;
           $cost = $rate->get_cost() ?: 0;
           $label = $rate->get_label();
  
           if ($cost == 0 && ($methodid != 'local_pickup' && $methodid != 'pickup_location')) {
              $has_free_shipping = true;
           }

           // We can't just use the method id, because the customer may have different addresses. Just to be sure, hash the entire method and use as a key.
           // Actually, we probably *can* use the method id, because other addresses are irellevant. But still, add a random factor 
           $rand = md5($methodid . bin2hex(random_bytes(32))); // Random enough, 32 chars
           // Ensure this never is over 100 chars. Use a dollar sign to indicate 'new method' IOK 2020-02-14
           // Reserve 8 chars to contain a : and an option index for Express Checkout IOK 2025-08-15
           // IOK 2025-08-14 "new" method is the current system; the legacy system has shipping method ids with different naming conventions. Again, to be deprecated. FIXME.
           $key = '$' . substr($methodid,0,58) . '$' . $rand;
           $vippsmethod = array();
           $vippsmethod['isDefault'] = @$method['default'] ? 'Y' :'N';
           $vippsmethod['priority'] = $method['priority'];

           // It seems woo actually computes rounding of prices and taxes *separately* when computing
           // shipping costs, but we can't really assume this (or that all plugins do this, and so on.)
           // Therefore we compute shipping cost with rounding *both ways* and choose the more expensive one -
           // this way we should reserve enough money to complete the order in all cases. IOK 2025-09-30
           $shippingcostA = sprintf("%.2F",wc_format_decimal($cost+$tax,''));
           $shippingcostB = sprintf("%.2F",wc_format_decimal($cost, '') + wc_format_decimal($tax,''));
           $shippingcost = max($shippingcostA, $shippingcostB);

           $vippsmethod['shippingCost'] = $shippingcost;
           $vippsmethod['shippingMethod'] = $rate->get_label();
           $vippsmethod['shippingMethodId'] = $key;
           $vippsmethods[]=$vippsmethod;

           // Metadata and settings stored for later use for Vipps Checkout
           // and express checkout - basically, for each *key* have the corresponding object. IOK 2025-08-15
           // In the end, this data will be serialized and stored in the Order, and used in the gateways method set_order_shipping_details to
           // finalize the order. IOK 2025-08-15
           $ratemap[$key]=$rate;
           $methodmap[$key]=$shipping_method;
        }

        // This then is the old Express Checkout format, which we have exposed in filters. IOK 2025-08-14 
        $return = array('addressId'=>intval($addressid), 'orderId'=>$vippsorderid, 'shippingDetails'=>$vippsmethods);
        $return = apply_filters('woo_vipps_vipps_formatted_shipping_methods', $return); // Mostly for debugging

        // IOK 2021-11-16 Vipps Checkout uses a slightly different syntax and format.
        // IOK 2025-08-15 and new Express yet another slightly different format.
        // IOK 2025-08-15 pass the ratemap as a reference, so transforms can update them
        if ($ischeckout) {
            $return = VippsCheckout::instance()->format_shipping_methods($return, $ratemap, $methodmap, $order);
        } else { // New express format. LP 2025-05-26
            $return = $this->express_format_shipping_methods($return, $ratemap, $methodmap, $order);
            $return = $this->express_group_shipping_methods($return, $ratemap, $methodmap, $order);
            $return = apply_filters('woo_vipps_express_json_shipping_methods', $return, $order); // wat
        }

        // We need to store the WC_Shipping_Rate objects with all its meta data in the database until return from Vipps. IOK 2020-02-17
        $storedmethods = array(); 
        $errormethods = array();
        foreach($ratemap as $key => $rate) {
            $serialized = '';
            try {
                // We use serialize here instead of json_encode because we need the object back.
                // we base64-encode the serialized object, because it is to be stored in a database in a text field.a IOK 2025-12-12
                $raw = @serialize($rate);
                $serialized = $raw ? @base64_encode($raw) : null;
                if (!$serialized) {
                    throw new Exception("Could not serialize rate $key");
                }
                // Retrieve these precalculated rates on return from the store IOK 2020-02-14 
                $storedmethods[$key] = $serialized;
            } catch (Exception $e) {
                $errormethods[] = $key;
                $this->log(sprintf(__("Cannot use shipping method %2\$s in %1\$s Express checkout: the shipping method isn't serializable.", 'woo-vipps'), $this->get_payment_method_name(), $label), 'error');
                $this->log($rate, 'error');
                continue;
            }
        }

        // Remove any methods from the return that was not serializable
        if (!empty($errormethods)) {
            $fixedreturn = [];
            if ($ischeckout) {
                foreach($errormethods as $problem) {
                    foreach($return['shippingDetails'] as $method) {
                        $id = preg_replace('!:\d+$!', "", $method['id']);
                        if ($id != $problem) $fixedreturn[]  = $method;
                    }
                }
                $return['shippingDetails'] = $fixedreturn;
            } else {
                foreach($errormethods as $problem) {
                    foreach($return as $method) {
                        $option = $method['options'][0];
                        $id = preg_replace('!:\d+$!', "", $option['id']);
                        if ($id != $problem) $fixedreturn[]  = $method;
                    }
                }
                $return = $fixedreturn;
            }
        }


        // We'll also store whether or not this set of rates include free shipping in some way. IOK 2025-09-16
        $storedmethods['_meta_has_free_shipping'] = $has_free_shipping;
        $storedmethods['_is_base64'] = true;

        $order->update_meta_data('_vipps_express_checkout_shipping_method_table', $storedmethods);
        $order->save_meta_data();
        return $return;
    }

    // Translate from the old to the new express format. LP 2025-05-26
    public function express_format_shipping_methods ($return, &$ratemap, $methodmap, $order) {
        $translated = array();
        $currency = $order->get_currency();

        // First, we'll translate the legacy format originally used by express to the new one (that may 
        // still be in use by filters etc), then add hooks to modify options and other new features.
        // IOK 2025-11-19
        foreach ($return['shippingDetails']  as $m) {
            $m2 = array();
            $options = [];

            $m2['isDefault'] = ($m['isDefault']=='Y') ? true : false; 
            $m2['priority'] = $m['priority'];
            $m2['brand'] = 'OTHER'; // the default. This is replaced for certain brands. LP 2025-05-26
            $m2['type'] = 'OTHER'; // default, replaced for certain types. LP 2025-05-26

            $id = $m['shippingMethodId'];
            $rate = $ratemap[$id];
            $shipping_method = $methodmap[$id];

            if ($rate->method_id == 'pickup_location') {
                $m2['type'] = 'PICKUP_POINT';
            } 

            // Each shipping method needs a list of options at this point.
            $options = []; 


            // A rate can have a delivery time as a string in both Woo and Express
            $delivery_time = $rate->get_delivery_time();
            // And some rates have metadata, such as pickup locations (local_delivery).
            $meta = $rate->get_meta_data();
            // We can also support descriptions, in the "meta" field
            $description = $rate->get_description();

            $option = [];
            $option['priority'] = $m['priority'];
            $option['name'] = $m['shippingMethod']; 
            $option['id'] = $id;
            $option['amount'] = [ 'value' => round(100*$m['shippingCost']), 'currency' => $currency ];
            if ($delivery_time) $option['estimatedDelivery'] = $delivery_time;
            if ($description) $entry['meta'] = $description;
            $options[] = $option;

            if (isset($meta['brand'])) {
                $m2['brand'] = $meta['brand'];
            } else {
                // specialcase some known methods so they get brands, and put the label into the description
                if ($shipping_method && is_a($shipping_method, 'WC_Shipping_Method') && get_class($shipping_method) == 'WC_Shipping_Method_Bring_Pro') {
                    $m2['brand'] = "POSTEN";
                }
                $m2['brand'] = apply_filters('woo_vipps_shipping_method_brand', $m2['brand'],$shipping_method, $rate);
            }

            if ($m2['brand'] != "OTHER" && isset($meta['type'])) {
                $m2['type'] = apply_filters('woo_vipps_shipping_method_type', $meta['type'], $shipping_method, $rate);
            }
            $m2['options'] = $options;

            // Now allow custom code to modify both the rate (adding metadata, mostly) and the Vipps shipping method (probably adding 
            // options, changing the brand etc) IOK 2025-11-19
            // For an example, see the express_add_pickup_location_options method. IOK 2025-11-19
            list ($rate, $m2) = apply_filters('woo_vipps_modify_express_checkout_rate', [$rate, $m2], $shipping_method, $rate, $order);
            $ratemap[$id] = $rate; // Modify the ratemaps copy with any new data here - ratemap is passed by reference IOK 2025-11-19

            $translated[] = $m2;
        }

        return $translated;
    }

    // This adds extra options for express checkout shipping rates that implement the 'woo_vipps_shipping_method_pickup_points' filter,
    // making these into groups with a dropdown for the exact shipping location as separate options.
    // This will create multiple pointers to the same shipping rate, which will be extended with a metadata field containing the pickup point.
    // That is, this is *not* for local_pickup, but for legacy local pickup and other shipping methods that have the same rate price, but
    // allows the user to select a location. IOK 2025-08-15
    public function express_add_pickup_location_options ( $data, $shipping_method, $rate, $order) {
        list ($rate, $m2) = $data;
        $pickup_points = apply_filters('woo_vipps_shipping_method_pickup_points', [], $rate, $shipping_method, $order);
        if (empty($pickup_points)) return $data;
        if (count($m2['options'])>1) return $data; 

        $index = 0;
        $pickup_point_table = [];
        $option = $m2['options'][0];
        $id = $option['id'];

        foreach($pickup_points as $point) {
            $index++; // Start at 1
            $entry = $option; // This is a copy in PHP

            $addr = [];
            foreach(['name', 'address', 'postalCode', 'city', 'country'] as $key) {
                $v = trim($point[$key]);
                if (!empty($v)) $addr[$key] = $v;
            }
            // To avoid confusion, force the keys to be strings. IOK 2025-08-15
            $pickup_point_table["i".$index] = $addr;

            // This is for display in the App only IOK 2025-08-15
            $description = join(", ", array_values($addr));
            $description = trim(apply_filters('woo_vipps_shipping_option_meta', trim($description, " ,"), $rate, $shipping_method, $order));
            if ($description) $entry['meta'] = $description;

            // IOK 2025-06-04 Since we are here mapping several Express rates to a single Woo rate,
            // we need to add a suffix, which is removed in gw->set_order_shipping_details().
            $entry['id'] = $id . ":" . $index;
            $entry['name'] = $point['name'];
            $options[] = $entry;
        }
        // If we have pickup points added, then store them in a table in the rate itself. We'll strip that value when finalizing the order. IOK 2025-08-15
        // This gets stored in the orders ratemap on return. IOK 2025-11-19
        if (!empty($pickup_point_table)) {
            $rate->add_meta_data('_vipps_pickupPoints', $pickup_point_table);
        }
        $m2['options'] = $options;
        $m2['type'] = 'PICKUP_POINT';

        return [$rate, $m2];
    }


    // Group certain shipping methods together in the new express format, into a group of options for one method (for example pickup locations). LP 2025-06-04
    // $order not used, will keep for now so to have a similar signature to express_format_shipping_methods, also it might be used in future change of this method. LP 2025-08-18
    public function express_group_shipping_methods($methods, &$ratemap, $methodmap, $order) { 
        if (!$methods) return $methods;
        $grouped = [];
        $maybe_groupable_methods = $methods;
        while (!empty($maybe_groupable_methods)) {
            $first = array_shift($maybe_groupable_methods);
            $first_id = preg_replace("!:.+$!", "",  $first['options'][0]['id']); // strip option index from 'augmented' methods.
            $first_rate = $ratemap[$first_id];
            $first_method = $methodmap[$first_id];

            $rest = [];
            foreach ($maybe_groupable_methods as $candidate) {
                $candidate_id = preg_replace("!:.+$!", "",  $candidate['options'][0]['id']); // strip option index from 'augmented' methods.
                $candidate_rate = $ratemap[$candidate_id];
                $candidate_method = $methodmap[$candidate_id];

                // By default, we will group all rates that are pickup_location-s. LP 2025-08-18
                $is_pickup = $first_rate->method_id === $candidate_rate->method_id && $first_rate->method_id === 'pickup_location';
                $should_group = apply_filters('woo_vipps_express_should_group_shipping_methods', $is_pickup, $first_rate, $first_method, $candidate_rate, $candidate_method); 

                if ($should_group) {
                    $first_options = $first['options'];
                    $second_options = $candidate['options'];

                    $first['options'] = array_merge($first_options, $second_options);

                    // Reset default-ness and priority to the highest value from the merged methods.
                    if ($candidate['isDefault']) $first['isDefault'] = true;
                    if ($candidate['priority'] < $first['priority']) $first['priority'] = $candidate['priority'];

                } else {
                    $rest[] = $candidate;
                }

            }
            $grouped[] = $first;

            // Start over again with the ones who weren't grouped to the first method of the list. LP 2025-08-18
            $maybe_groupable_methods = $rest;
        }

        return $grouped;
    }


    // In certain situations the session may have no cart, which among other things makes it impossible for us to calculate shipping.
    // We must therefore reconstruct the cart as close to what it were before calculating shipping; and we must delete it afterwards
    // because it may not be correct wrt meta values and so forth. Based on cart-sessions "populate_cart_from_order" used in the "order again" path.
    // Returns "true" if cart is reconstructed from the order, else false.
    // IOK 2024-04-08
    private function maybe_reconstruct_cart($order_id) {
        if (!WC()->cart->is_empty()) return false;
        $this->log(sprintf(__("No cart, so will try to calculate shipping based on order contents for order %1\$d", 'woo-vipps'),   $order_id), 'error');
        try {
            $order = wc_get_order( $order_id );
            $cart = array();
            $inital_cart_size = 0;
            $order_items = $order->get_items();
            foreach ( $order_items as $item ) {
                $product_id     = (int) $item->get_product_id();
                $quantity       = $item->get_quantity();
                $variation_id   = (int) $item->get_variation_id();
                $variations     = array();
                $cart_item_data = array();
                $product        = $item->get_product();
                if ( ! $product ) {
                    continue;
                }
                if ( ! $variation_id && $product->is_type( 'variable' ) )  continue;
                // We ignore the out-of-stock rule here, it doesn't matter for shipping in this case IOK 2024-04-09
                foreach ( $item->get_meta_data() as $meta ) {
                    if ( taxonomy_is_product_attribute( $meta->key ) || meta_is_product_attribute( $meta->key, $meta->value, $product_id ) ) {
                        $variations[ $meta->key ] = $meta->value;
                    }
                }
                $cart_id          = WC()->cart->generate_cart_id( $product_id, $variation_id, $variations, $cart_item_data );
                $product_data     = wc_get_product( $variation_id ? $variation_id : $product_id );
                $cart[ $cart_id ] = array_merge(
                    $cart_item_data,
                    array(
                        'key'          => $cart_id,
                        'product_id'   => $product_id,
                        'variation_id' => $variation_id,
                        'variation'    => $variations,
                        'quantity'     => $quantity,
                        'data'         => $product_data,
                        'data_hash'    => wc_get_cart_item_data_hash( $product_data ),
                    )
                );

            }
            WC()->cart->set_cart_contents($cart);
            WC()->cart->calculate_totals();
            WC()->cart->set_session();
            return true;
        } catch (Exception $e) {
            $this->log(sprintf(__("Error regenerating cart from order %1\$d:  %2\$s", 'woo-vipps'), $order_id,   $e->get_message()), 'error');
            return false;
        }
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
            $tax  = $rate->get_shipping_tax() ?: 0;
            $cost = $rate->get_cost() ?: 0;

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
        // Or pickup_location, same thing. IOK 2025-05-07
        if(!$defaultset && !empty($methods)) {
            foreach($methods as &$method) {
                if (!preg_match("!^(local_pickup|pickup_location)!",$method['shippingMethodId'])) {
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

    public static function nocache() {
        wc_nocache_headers();
        header("X-Accel-Expires: 0"); 
    }



    // Handle DELETE on a vipps consent removal callback
    public function vipps_consent_removal_callback ($callback) {
	    Vipps::nocache();
            // Currently, no such requests will be posted, and as this code isn't sufficiently tested,we'll just have 
            // to escape here when the API is changed. IOK 2020-10-14
            $this->log("Consent removal is non-functional pending API changes as of 2020-10-14"); print "1"; exit();
    }

    public function woocommerce_payment_gateways($methods) {
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        // Protect the singleton: Use the object instead of the class name IOK 2025-02-04
        $gateway = $this->gateway();
        if ($gateway) {
            $methods[] = $gateway;
        } else {
            $methods[] =  'WC_Gateway_Vipps';
        }
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

            $vippstatus = $o->get_meta('_vipps_status');
            $currentstatus = $this->gateway()->interpret_vipps_order_status($vippstatus);
            if ($currentstatus != 'initiated') {
                $this->log(sprintf(__("Order %2\$d is 'pending' but its %1\$s Order Status is %3\$s  - this means that the order has been erroneously set to 'pending' after completion or cancellation. Will not process further. Please check status of order at Vipps and set to correct status in WooCommerce", 'woo-vipps'), $this->get_payment_method_name(), $o->get_id(), $currentstatus), 'debug');
                return;
            }
            $this->check_status_of_pending_order($o, false);
        }
    }

    // Check and possibly update the status of a pending order at Vipps. We only restore session if we know this is called from a context with no session -
    // e.g. wp-cron. IOK 2021-06-21
    // Stop restoring session in wp-cron too. IOK 2021-08-23
    public function check_status_of_pending_order($order, $maybe_restore_session=0) {
        $express = $order->get_meta('_vipps_express_checkout'); 
        $vippstatus = $order->get_meta('_vipps_status');
        if ($express && $maybe_restore_session) {
           $this->log(sprintf(__("Restoring session of order %1\$d", 'woo-vipps'), $order->get_id()), 'debug'); 
           $this->callback_restore_session($order->get_id());
        }
        $gw = $this->gateway();

        $order_status = null;
        try {
            $order->add_order_note(sprintf(__("Callback from %1\$s delayed or never happened; order status checked by periodic job", 'woo-vipps'), $this->get_payment_method_name()));
            $order_status = $gw->callback_check_order_status($order);
            $this->log(sprintf(__("For order %2\$d order status at %1\$s is %3\$s", 'woo-vipps'), $this->get_payment_method_name(), $order->get_id(), $order_status), 'debug');
        } catch (Exception $e) {
            $this->log(sprintf(__("Error getting order status at %1\$s for order %2\$d", 'woo-vipps'), $this->get_payment_method_name(), $order->get_id()), 'error'); 
            $this->log($e->getMessage() . "\n" . $order->get_id(), 'error');
        }
        // Ensure we don't keep using an old session for more than one order here.
        if ($express && $maybe_restore_session) {
            $this->callback_destroy_session();
        }
        return $order_status;
    }

    // This will probably be run in activate, but if the plugin is updated in other ways, will also be run on after_setup_theme. IOK 2020-04-01
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
       // IOK 2023-12-20 for the epayment api, we need to re-initialize webhooks at this point. 
       $gw->initialize_webhooks();
       $this->payment_method_name = $gw->get_option('payment_method_name');
    }  

    // We have added some hooks to wp-cron; remove these. IOK 2020-04-01
    public static function deactivate() {
        $timestamp = wp_next_scheduled('vipps_cron_cleanup_hook');
        wp_unschedule_event($timestamp, 'vipps_cron_cleanup_hook');
        $timestamp = wp_next_scheduled('vipps_cron_missing_callback_hook');
        wp_unschedule_event($timestamp, 'vipps_cron_missing_callback_hook');
        // IOK 2023-12-20 Delete all webhooks for this instance
        $gw = WC_Gateway_Vipps::instance();
        $gw->delete_all_webhooks();

        // Delete all settings if checked in settings menu. LP 2025-10-06
        $should_delete = $gw->get_option( 'delete_settings_on_deactivation' ) === 'yes';
        if ( ($should_delete)) {
            // Delete options.
            $options = ['woocommerce_vipps_settings', 'woo-vipps-configured', 'vipps_badge_options', 'vipps_button_options', '_vipps_dismissed_notices', 'woo_vipps_checkout_activated'];
            foreach($options as $option) {
                error_log("Deleting woo-vipps option: $option");
                delete_option($option);
            }
        }

        // Run deactivation logic for recurring
        if (class_exists('WC_Vipps_Recurring')) {
            WC_Vipps_Recurring::get_instance()->deactivate();
        }
        delete_option('woo_vipps_recurring_payments_activation');

    }

    /** Try manually setting locale to locale recieved in AcceptLanguage header.
    *
    * This should fix incorrect language recieved from ajax when using translate plugins like polylang, wpml.
    * E.g. for checkout widgets and product names: We send the correct locale to the frontend when first setting up Checkout,
    * then we send the locale back in the Accept-Language header to ajax endpoints. LP 2025-12-11
    */
    public static function set_locale_if_in_header() {
        $locales = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        // get first in list, but strip away semicolon and everything after. LP 2025-12-16
        $newlocale = trim(preg_replace("!;.*!", "", explode(",", $locales)[0]));
        if (empty($newlocale))
            return false;
        return switch_to_locale($newlocale); // note: this may fail and return a false. LP 2025-12-11
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
        // We can't use the vipps init timestamp here, because that may be  in the past for Checkout at least. IOK 2025-08-13
        if ($order_status == 'pending') {
            if (WC()->session) {
                $now = time();
                $then = WC()->session->get('_vipps_check_' . $order->get_id());
                if (!$then) {
                    $then = $now;
                    WC()->session->set('_vipps_check_' . $order->get_id(), $then);
                }
                if (($then + (1 * 30)) > $now) { // more than half a minute? Start checking at Vipps
                    return $order_status;
                }
            } else {
                // No session shouldn't be possible, but if it is..
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

    // Should only be run when this is an order in our own session,
    // used in the ajax_check_order_status and vipps_payment methods, where we are 
    // expecting a customer return that may be from Express Checkout. If it is, we may
    // have no customer email in the current session, which in 7.8.2 will stop the user from 
    // viewing his or her orders. IOK 2023-07-17
    function maybe_set_session_customer_email($order) {
        if ($order->get_meta('_vipps_express_checkout')) {
            $email = $order->get_billing_email();
            if ($email && WC()->customer) {
                WC()->customer->set_email($email);
                WC()->customer->set_billing_email($email);
                WC()->customer->save();
                WC()->session->set('tstamp', time()); // Just to ensure it is 'dirty'
            }  else {
                $this->log(__("Could not get user email from order before thankyou-page", 'woo-vipps'));
            }
        }
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

        // We will not log in Vipps Checkout users unless the option for that is true
        if ($order->get_meta('_vipps_checkout') && 'yes' != $this->gateway()->get_option('checkoutcreateuser')) {
            $do_login = false;
        }


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
        if (!$order || $order->get_payment_method() != 'vipps' ) return null;
        // specific code for this by netthandelsgruppen if the below function exists
        if (function_exists('create_assign_user_on_vipps_callback')) return null;

        // Both Checkout and Express Checkout have the below value set to true
        if (!$order->get_meta('_vipps_express_checkout')) return;

        // Creating/logging in users are handled separately for Vipps Checkout and Express Checkout, so check the correct setting
        // IOK 2023-07-27
        $ischeckout = $order->get_meta('_vipps_checkout');
        if ($ischeckout) {
            if ($this->gateway()->get_option('checkoutcreateuser') != 'yes') return null;
        } else {
            if ($this->gateway()->get_option('expresscreateuser') != 'yes') return null;
        }

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

        // Previously this got the user data from Vipps here as a third argument; this is no longer available after refactoring.
        $user = [];
        $maybecreateuser = apply_filters('woo_vipps_create_user_on_express_checkout', true, $order, $user);
        if (! $maybecreateuser) return;

        // No customer yet. As we want to create users like this (set in the settings) let's do so.
        // Username will be created from email, but the settings may stop generating passwords, so we force that to be generated. IOK 2020-10-09
        $firstname = $order->get_billing_first_name();
        $lastname =  $order->get_billing_last_name();
        $name = $firstname;
        $userdata = array('user_nicename'=>$name, 'display_name'=>"$firstname $lastname", 'nickname'=>$firstname, 'first_name'=>$firstname, 'last_name'=>$lastname);

        // Add filter to allow other ways of creating usernames.
        $newusername = apply_filters('woo_vipps_express_checkout_new_username', '', $email, $userdata, $order);

        $customerid = wc_create_new_customer($email, $newusername,  wp_generate_password(), $userdata);
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
	Vipps::nocache();
        static::set_locale_if_in_header();
        // We're not checking ajax referer here, because what we do is creating a session and redirecting to the
        // 'create order' page wherein we'll do the actual work. IOK 2018-09-28
        $session = WC()->session;
        if (!$session->has_session()) {
            $session->set_customer_session_cookie(true);
        }
        $session->set('__vipps_buy_product', json_encode($_REQUEST));

        // Incredibly, some caches will cache this page even with cookies set and no-cache headers set. So we try to 
        // add yet another way to inform caches that this is, in fact, not cacheable. IOK 2023-06-12
        $url = add_query_arg('nc', sha1(uniqid(WC()->session->get_customer_id(),true)), $this->buy_product_url());

        $result = array('ok'=>1, 'msg'=>__('Processing order... ','woo-vipps'), 'url'=> $url);
        wp_send_json($result);
        exit();
    }

    public function ajax_do_express_checkout () {
        check_ajax_referer('do_express','sec');
	Vipps::nocache();
        static::set_locale_if_in_header();
        $gw = $this->gateway();

        if (!$gw->express_checkout_available() || !$gw->cart_supports_express_checkout()) {
            $result = array('ok'=>0, 'msg'=>sprintf(__('%1$s is not available for this order','woo-vipps'), Vipps::ExpressCheckoutName()), 'url'=>false);
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
            $result = array('ok'=>0, 'msg'=>sprintf(__('Some of the products in your cart are no longer available in the quantities you have ordered. Please <a href="%1$s">edit your order</a> before continuing the checkout','woo-vipps'), wc_get_cart_url()) . $msg, 'url'=>false);
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
        
        $ok = $gw->process_payment($orderid);
        if ($ok && $ok['result'] == 'success') {
            $result = array('ok'=>1, 'msg'=>'', 'url'=>$ok['redirect']);
            wp_send_json($result);
            exit();
        }
        $result = array('ok'=>0, 'msg'=> sprintf(__('%1$s is temporarily unavailable.','woo-vipps'), $this->get_payment_method_name()), 'url'=>'');
        wp_send_json($result);
        exit();
    }

    // Same as ajax_do_express_checkout, but for a single product/variation. Duplicate code because we want to manipulate the cart differently here. IOK 2018-09-25
    public function ajax_do_single_product_express_checkout() {
        check_ajax_referer('do_express','sec');
	Vipps::nocache();
        static::set_locale_if_in_header();
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        $gw = $this->gateway();

        if (!$gw->express_checkout_available()) {
            $result = array('ok'=>0, 'msg'=>sprintf(__('%1$s is not available for this order','woo-vipps'), Vipps::ExpressCheckoutName()), 'url'=>false);
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
            $result = array('ok'=>0, 'msg'=>sprintf(__('%1$s is not available for this order','woo-vipps'), Vipps::ExpressCheckoutName()), 'url'=>false);
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

        $ok = $gw->process_payment($orderid);
        if ($ok && $ok['result'] == 'success') {
            $result = array('ok'=>1, 'msg'=>'', 'url'=>$ok['redirect']);
            wp_send_json($result);
            exit();
        }
        $result = array('ok'=>0, 'msg'=> sprintf(__('%1$s is temporarily unavailable.','woo-vipps'), $this->get_payment_method_name()), 'url'=>'');
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
            return $this->add_static_shipping($gw, $orderid, $ischeckout);
        }
    }

    // And this function adds static shipping no matter what. It may need to be used in plugins, hence visible. IOK 2021-10-22
    public function add_static_shipping ($gw, $orderid, $ischeckout=false) {
        $order = wc_get_order($orderid);
        $prefix  = $gw->get_orderprefix();
        $vippsorderid =  apply_filters('woo_vipps_orderid', $prefix.$orderid, $prefix, $order);
        $addressinfo = $this->get_static_shipping_address_data();

        // Both Checkout and new Express Checkout supports LocalPickup, so add it (it is normally only present for Gutenberg checkout)
        // Add special shipping methods (LocalPickup etc);
        $this->load_extra_shipping_methods($order, $addressinfo, $ischeckout);
	
        $options = $this->vipps_shipping_details_callback_handler($order, $addressinfo,$vippsorderid, $ischeckout);

        if ($options) {
            $order->update_meta_data('_vipps_static_shipping', $options);
            $order->save();
        }
    }

    // Vipps Checkout and Express Checkout allows loading specific kinds of shipping methods with non-standard APIs, such as PickupLocations. IOK 2025-05-08
    // Must be called *early*. IOK 2025-05-08. Called in callback methods, and if using static shipping, in the 'start session' callback. 
    public function load_extra_shipping_methods($order, $addressdata, $ischeckout=false) {
        // If we need to add more shipping methods *before* the shipping callback starts, it must be done before we load the session. IOK 2025-05-06
        add_action('woocommerce_load_shipping_methods', function () use ($order, $addressdata) {
            // Support local pickup. This is normally only registered when the Gutenberg Checkout block is either on the
            // 'checkout-page' or in some template; the first case will not occur when Vipps MobilePay Checkout is active, so make sure it is
            // Express checkout does not support this (yet). IOK 2025-05-06
            // Afterwards, we need to post-process this, because *each* location gets a different rate. See the VippsCheckout class.
            if (class_exists('Automattic\WooCommerce\Blocks\Shipping\PickupLocation')) {
                $ok = wc()->shipping->register_shipping_method( new Automattic\WooCommerce\Blocks\Shipping\PickupLocation() );
            }
            do_action('woo_vipps_express_load_shipping_methods', $order, $addressdata);
        }, 99);
    }
    

    // Check the status of the order if it is a part of our session, and return a result to the handler function IOK 2018-05-04
    public function ajax_check_order_status () {
        check_ajax_referer('vippsstatus','sec');
        static::set_locale_if_in_header();
        Vipps::nocache();

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
            // IOK Previously handled in the thankyou hook 2023-07-17
            $this->woocommerce_before_thankyou($order->get_id());
            wp_send_json(array('status'=>'ok', 'msg'=>__('Payment authorized', 'woo-vipps')));
            return false;
        }
        if ($payment == 'complete') {
            // IOK Previously handled in the thankyou hook 2023-07-17
            $this->woocommerce_before_thankyou($order->get_id());
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
    private function make_vipps_url($what) {
        if ( !get_option('permalink_structure')) {
            return add_query_arg('VippsSpecialPage', $what, home_url("/", 'https'));
        }
        return trailingslashit(home_url($what, 'https'));
    }
    public function payment_return_url() {
        return apply_filters('woo_vipps_payment_return_url', $this->make_vipps_url('vipps-betaling')); 
    }
    public function express_checkout_url() {
        return $this->make_vipps_url('vipps-express-checkout');
    }
    public function buy_product_url() {
        return $this->make_vipps_url('vipps-buy-product');
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
        $flavour = sanitize_title($this->get_payment_method_name());
        ob_start();
        ?>
            <div class="vippsoverlay">
            <div id="floatingCirclesG" class="vippsspinner <?php echo esc_attr($flavour); ?>">
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


    // Returns express logo images depending on parameters, these are the new express svgs received 2025-12-12.
    // Fallbacks to defaults for each payment method. LP 2025-12-15
    public function get_express_logo($payment_method, $lang, $variant) {
        $base = plugins_url('img', __FILE__);

        // A much more concise approach could be to name the variant files directly and do a oneliner, but harder to grep after the files' usage. LP 2025-12-12
        $img_map = [
            "vipps" => [
                "default" => "$base/vipps/express/en/buy-now-vipps-en-rectangular.svg",
                "default-mini" => "$base/vipps/express/en/express-vipps-en-rectangular-mini.svg",
                "en" => [
                    "default" => "$base/vipps/express/en/buy-now-vipps-en-rectangular.svg",
                    "default-mini" => "$base/vipps/express/en/express-vipps-en-rectangular-mini.svg",
                    "buy-now-rectangular" => "$base/vipps/express/en/buy-now-vipps-en-rectangular.svg",
                    "buy-now-pill" => "$base/vipps/express/en/buy-now-vipps-en-pill.svg",
                    "express-rectangular" => "$base/vipps/express/en/express-vipps-en-rectangular.svg",
                    "express-rectangular-mini" => "$base/vipps/express/en/express-vipps-en-rectangular-mini.svg",
                    "express-pill" => "$base/vipps/express/en/express-vipps-en-pill.svg",
                    "express-pill-mini" => "$base/vipps/express/en/express-vipps-en-pill-mini.svg",

                ],
                "no" => [
                    "default" => "$base/vipps/express/no/kjop-na-vipps-no-rectangular.svg",
                    "default-mini" => "$base/vipps/express/no/ekspress-vipps-no-rectangular-mini.svg",
                    "buy-now-rectangular" => "$base/vipps/express/no/kjop-na-vipps-no-rectangular.svg",
                    "buy-now-pill" => "$base/vipps/express/no/kjop-na-vipps-no-pill.svg",
                    "express-rectangular" => "$base/vipps/express/no/ekspress-vipps-no-rectangular.svg",
                    "express-rectangular-mini" => "$base/vipps/express/no/ekspress-vipps-no-rectangular-mini.svg",
                    "express-pill" => "$base/vipps/express/no/ekspress-vipps-no-pill.svg",
                    "express-pill-mini" => "$base/vipps/express/no/ekspress-vipps-no-pill-mini.svg",
                ],
                "se" => [
                    "default" => "$base/vipps/express/se/kop-nu-vipps-se-rectangular.svg",
                    "default-mini" => "$base/vipps/express/se/express-vipps-se-rectangular-mini.svg",
                    "buy-now-rectangular" => "$base/vipps/express/se/kop-nu-vipps-se-rectangular.svg",
                    "buy-now-pill" => "$base/vipps/express/se/kop-nu-vipps-se-pill.svg",
                    "express-rectangular" => "$base/vipps/express/se/express-vipps-se-rectangular.svg",
                    "express-rectangular-mini" => "$base/vipps/express/se/express-vipps-se-rectangular-mini.svg",
                    "express-pill" => "$base/vipps/express/se/express-vipps-se-pill.svg",
                    "express-pill-mini" => "$base/vipps/express/se/express-vipps-se-pill-mini.svg",

                ],
            ],
            "mobilepay" => [
                "default" => "$base/mobilepay/express/en/buy-now-mp-en-rectangular.svg",
                "default-mini" => "$base/mobilepay/express/en/express-mp-en-rectangular-mini.svg",
                "en" => [
                    "default" => "$base/mobilepay/express/en/buy-now-mp-en-rectangular.svg",
                    "default-mini" => "$base/mobilepay/express/en/express-mp-en-rectangular-mini.svg",
                    "buy-now-rectangular" => "$base/mobilepay/express/en/buy-now-mp-en-rectangular.svg",
                    "buy-now-pill" => "$base/mobilepay/express/en/buy-now-mp-en-pill.svg",
                    "express-rectangular" => "$base/mobilepay/express/en/express-mp-en-rectangular.svg",
                    "express-rectangular-mini" => "$base/mobilepay/express/en/express-mp-en-rectangular-mini.svg",
                    "express-pill" => "$base/mobilepay/express/en/express-mp-en-pill.svg",
                    "express-pill-mini" => "$base/mobilepay/express/en/express-mp-en-pill-mini.svg",

                ],
                "dk" => [
                    "default" => "$base/mobilepay/express/dk/kob-nu-mp-dk-rectangular.svg",
                    "default-mini" => "$base/mobilepay/express/dk/express-mp-dk-rectangular-mini.svg",
                    "buy-now-rectangular" => "$base/mobilepay/express/dk/kob-nu-mp-dk-rectangular.svg",
                    "buy-now-pill" => "$base/mobilepay/express/dk/kob-nu-mp-dk-pill.svg",
                    "express-rectangular" => "$base/mobilepay/express/dk/express-mp-dk-rectangular.svg",
                    "express-rectangular-mini" => "$base/mobilepay/express/dk/express-mp-dk-rectangular-mini.svg",
                    "express-pill" => "$base/mobilepay/express/dk/express-mp-dk-pill.svg",
                    "express-pill-mini" => "$base/mobilepay/express/dk/express-mp-dk-pill-mini.svg",
                ],
                "fi" => [
                    "default" => "$base/mobilepay/express/fi/osta-nyt-mp-fi-rectangular.svg",
                    "default-mini" => "$base/mobilepay/express/fi/express-mp-fi-rectangular-mini.svg",
                    "buy-now-rectangular" => "$base/mobilepay/express/fi/osta-nyt-mp-fi-rectangular.svg",
                    "buy-now-pill" => "$base/mobilepay/express/fi/osta-nyt-mp-fi-pill.svg",
                    "express-rectangular" => "$base/mobilepay/express/fi/express-mp-fi-rectangular.svg",
                    "express-rectangular-mini" => "$base/mobilepay/express/fi/express-mp-fi-rectangular-mini.svg",
                    "express-pill" => "$base/mobilepay/express/fi/express-mp-fi-pill.svg",
                    "express-pill-mini" => "$base/mobilepay/express/fi/express-mp-fi-pill-mini.svg",

                ],
            ],

        ];

        $payment = strtolower($payment_method);
        if ($lang === 'store') $lang = $this->get_customer_language();

        // Dont give a default if payment method not found. LP 2025-12-12
        if (!array_key_exists($payment, $img_map)) {
            return null;
        }
        $payment_map = $img_map[$payment];

        $img = null;
        if (array_key_exists($lang, $payment_map)
            && is_array($payment_map[$lang])
            && array_key_exists($variant, $payment_map[$lang])) {
            $img = @$payment_map[$lang][$variant];
        }

        // Default fallback behaviour
        if (!$img) {
            $default = str_ends_with($variant, '-mini') ? 'default-mini' : 'default';

            // First try getting default for payment method + language. LP 2026-01-16
            if (array_key_exists($lang, $payment_map) && is_array($payment_map[$lang])) {
                /* translators: %1= payment method name, %2 = language string, %3 = variant name */
                $this->log(sprintf(__('Could not find chosen express logo for payment method %1$s, language %2$s, and variant %3$s, attempting to fall back on language and payment method, else only language.', 'woo-vipps'), $payment_method, $lang, $variant), 'error');
                $img = @$payment_map[$lang][$default];
            }

            // If not found, then try global default for payment method. LP 2026-01-16
            if (!$img) {
                $img = @$payment_map[$default];
            }

            // Found no logo at all, log this. LP 2026-01-16
            if (!$img) {
                /* translators: %1= payment method name, %2 = language string, %3 = variant name */
                $this->log(sprintf(__('Found no express logo fallback for payment method %1$s, language %2$s, and variant %3$s.', 'woo-vipps'), $payment_method, $lang, $variant), 'error');
            }
        }
        return $img;
    }

    // Get payment logo based on payment method, then language NT 2023-11-30
    // and based on custom variant setting. $page is the page origin slug, e.g 'cart', 'product'. LP 2025-12-15
    private function get_payment_logo($page = null) {
        $lang = $this->get_customer_language();
        $payment_method = $this->get_payment_method_name();
        $variant = $this->get_express_logo_page_variant($page);
        $logo_url = $this->get_express_logo($payment_method, $lang, $variant);
        return $logo_url;
    }

    /** Returns the correct variant to use for the given page, found from the wp option. LP 2025-12-23 */
    private function get_express_logo_page_variant($page = null) {
        $options = get_option('vipps_button_options');

        // Init defaults, use mini version by default in below pages. LP 2025-12-17
        $use_mini = in_array($page, ['catalog', 'minicart']);
        $variant = "";

        // Find correct variant from button settings. LP 2025-12-17
        if (is_array($options) && array_key_exists('express', $options)) {
            if (array_key_exists($page, $options['express']['force-mini'])) {
                $use_mini = sanitize_title($options['express']['force-mini'][$page]) === 'yes';
            }
            $key = $use_mini ? 'mini-variant' : 'variant';
            $variant = sanitize_title($options['express'][$key]) ?? '';
        }

        if (!$variant) {
            $variant = $use_mini ? "default-mini" : "default";
        }
        return $variant;
    }

    // Get express banner logo based on payment method. LP 2025-09-03
    private function get_express_banner_logo() {
        $payment_method = $this->get_payment_method_name();

        if($payment_method === "Vipps"){
            return plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__); 
        } else if($payment_method === "MobilePay"){
            return plugins_url('img/mobilepay-white.svg',__FILE__); 
        }
        return null;
    }

    // Get buy now button by manually selecting logo variant and language. LP 2026-01-16
    public function get_buy_now_button_manual($product_id,$variation_id=null,$sku=null,$disabled=false, $classes='', $logo_variant=null, $logo_lang=null) {
        $disabled = $disabled ? 'disabled' : '';
        $data = array();

        // Support directly using the variant id as $product_id with no $variation_id. LP 2026-01-23
        if ($product_id && !$variation_id) {
            $product = wc_get_product($product_id);
            if ($product && is_a($product, 'WC_Product_Variation')) {
                $variation_id = $product_id;
                $product_id = $product->get_parent_id();
            }
        }

        if ($sku) $data['product_sku'] = $sku;
        if ($product_id) $data['product_id'] = $product_id;
        if ($variation_id) $data['variation_id'] = $variation_id;


        $buttoncode = "<a href='javascript:void(0)' $disabled ";
        foreach($data as $key=>$value) {
            $value = esc_attr($value);
            $buttoncode .= " data-$key='$value' ";
        }

        $payment_method = $this->get_payment_method_name();
        $title = sprintf(__('Buy now with %1$s', 'woo-vipps'), $payment_method);
        $short = str_ends_with($logo_variant, 'mini');
        $logo = $this->get_express_logo($payment_method, $logo_lang, $logo_variant);

        $message =" <img border=0 src='$logo' alt='$payment_method'/>";

# Extra classes, if passed IOK 2019-02-26
        if (is_array($classes)) {
            $classes = join(" ", $classes);
        }
        if ($classes) $classes = " $classes";
        if ($short) $classes = "short $classes";

        $buttoncode .=  " class='single-product button vipps-buy-now $payment_method $disabled$classes' title='$title'>$message</a>";
        return apply_filters('woo_vipps_buy_now_button', $buttoncode, $product_id, $variation_id, $sku, $disabled);
    }

    // Code that will generate various versions of the 'buy now with Vipps' button IOK 2018-09-27
    public function get_buy_now_button($product_id,$variation_id=null,$sku=null,$disabled=false, $classes='', $page=null) {
        $logo_lang = $this->get_customer_language();
        $logo_variant = $this->get_express_logo_page_variant($page);
        return $this->get_buy_now_button_manual($product_id, $variation_id, $sku, $disabled, $classes, $logo_variant, $logo_lang);
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

        $classes = array();
        $disabled="";
        if ($product->is_type('variable')) {
            $disabled="disabled";
            $classes[] = 'variable-product';
        }

# If true, add a class that signals that the button should be added in 'compat mode', which is compatible with
# more plugins because it does not handle tha product add itself. IOK 2019-02-26
        $compat = ($gw->get_option('singleproductbuynowcompatmode') == 'yes');
        $compat = apply_filters('woo_vipps_single_product_compat_mode', $compat, $product);

        if ($compat) $classes[] ='compat-mode';
        $classes = apply_filters('woo_vipps_single_product_buy_now_classes', $classes, $product);

        $button = $this->get_buy_now_button(false,false,false, ($product->is_type('variable') ? 'disabled' : false), $classes, 'product');
        $code = "<div class='vipps_buy_now_wrapper noloop'>$button</div>";
        echo $code;
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
        $button = $this->get_buy_now_button($product->get_id(),false,$sku, false, '', 'catalog');
        echo "<div class='vipps_buy_now_wrapper loop'>$button</div>";
    }



    // Vipps Checkout replaces the default checkout page, and currently uses its own  page for this which needs to exist
    public function woocommerce_create_pages ($data) {
        $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);
        if (!$vipps_checkout_activated) return $data;

        $data['vipps_checkout'] = array(
                'name'    => _x( 'vipps_checkout', 'Page slug', 'woo-vipps' ),
                'title'   => _x( 'Vipps MobilePay Checkout', 'Page title', 'woo-vipps' ),
                'content' => '<!-- wp:shortcode -->[' . 'vipps_checkout' . ']<!-- /wp:shortcode -->',
                );

        return $data;
    }

    // Creates any necessary Vipps pages. Will be called e.g. when activating Vipps Checkout or turning it on.
    public function maybe_create_vipps_pages () {
            $checkoutid = wc_get_page_id('vipps_checkout');
            $makeit = !$checkoutid || ! get_post_status($checkoutid);
            if ($makeit) {
               delete_option('woocommerce_vipps_checkout_page_id');
            }

            if ($makeit) {
               WC_Install::create_pages();
            }
    }


    // This URL will when accessed add a product to the cart and go directly to the express  checkout page.
    // The argument passed must be a shareable link created for a given product - so this in effect acts as a landing page for 
    // the buying thru Vipps Express Checkout of a single product linked to in for instance banners. IOK 2018-09-24
    public function vipps_buy_product() {
        status_header(200,'OK');
	Vipps::nocache();

        add_filter('body_class', function ($classes) {
            $classes[] = 'vipps-express-checkout';
            $classes[] = 'woocommerce-checkout'; // Required by Pixel Your Site IOK 2022-11-24
            return apply_filters('woo_vipps_express_checkout_body_class', $classes);
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
	Vipps::nocache();
        // We need a nonce to get here, but we should only get here when we have a cart, so this will not be cached.
        // IOK 2018-05-28
        $ok = isset($_REQUEST['sec']) && wp_verify_nonce($_REQUEST['sec'],'express');


        $backurl = wp_validate_redirect(@$_SERVER['HTTP_REFERER']);
        if (!$backurl) $backurl = home_url();

        if (!$ok) {
            wc_add_notice(__('Link expired, please try again', 'woo-vipps'));
            wp_redirect($backurl);
            exit();
        }

        if ( WC()->cart->get_cart_contents_count() == 0 ) {
            wc_add_notice(__('Your shopping cart is empty','woo-vipps'),'error');
            wp_redirect($backurl);
            exit();
        }

        add_filter('body_class', function ($classes) {
            $classes[] = 'vipps-express-checkout';
            $classes[] = 'woocommerce-checkout'; // Required by Pixel Your Site IOK 2022-11-24
            return apply_filters('woo_vipps_express_checkout_body_class', $classes);
        });

        do_action('woo_vipps_express_checkout_page');

        $this->print_express_checkout_page(true, 'do_express_checkout');
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
        $expressCheckoutMessages['temporaryError'] = sprintf(__('%1$s is temporarily unavailable.','woo-vipps'), $this->get_payment_method_name());
        $expressCheckoutMessages['successMessage'] = sprintf(__('To the %1$s app!','woo-vipps'), $this->get_payment_method_name());

        wp_register_script('vipps-express-checkout',plugins_url('js/express-checkout.js',__FILE__),array('jquery','wp-hooks'),filemtime(dirname(__FILE__) . "/js/express-checkout.js"), 'true');
        wp_localize_script('vipps-express-checkout', 'VippsCheckoutMessages', $expressCheckoutMessages);
        wp_enqueue_script('vipps-express-checkout');
        // If we have a valid nonce when we get here, just call the 'create order' bit at once. Otherwise, make a button
        // to actually perform the express checkout.
        $buttonimgurl= apply_filters('woo_vipps_express_checkout_button', $this->get_payment_logo('landing'));


        $orderspec = $this->get_orderspec_from_arguments($productinfo);
        if (empty($orderspec)) { 
            $orderspec = $this->get_orderspec_from_cart();
        }
        $orderisOK = $this->validate_express_checkout_orderspec($orderspec);
        $orderisOK = apply_filters('woo_vipps_validate_express_checkout_orderspec', $orderisOK, $orderspec);

        $askForTerms = function_exists('wc_terms_and_conditions_checkbox_enabled') ?  wc_terms_and_conditions_checkbox_enabled() : true;
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

        // We impersonate the woocommerce-checkout form here mainly to work with the Pixel Your Site plugin IOK 2022-11-24
        // The form data below is sent on order creation; the sec is also used to poll session status
        $classlist = apply_filters("woo_vipps_express_checkout_form_classes", "woocommerce-checkout");
        $content .= "<form id='vippsdata' class='" . esc_attr($classlist) . "'>";
        $content .= "<input type='hidden' name='action' value='" . esc_attr($action) ."'>";
        if ($this->gateway()->get_option('vippsorderattribution') == 'yes') {
            // This is for the new order attribution feature of woo. IOK 2024-01-09
            $content .= '<input type="hidden" id="vippsorderattribution" value="1" />';
            ob_start();
            do_action( 'woocommerce_after_order_notes');
            $content .= ob_get_clean();
        }
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
                $k = esc_attr($key);
                $v = esc_attr($value);
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
            $pressTheButtonHTML =  "<p id=waiting>" . sprintf(__('Ready for %1$s - press the button', 'woo-vipps'), Vipps::ExpressCheckoutName()) . "</p>";
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
            $title = sprintf(__('Buy now with %1$s!', 'woo-vipps'), $this->get_payment_method_name());
            $content .= "<div class='vipps_buy_now_wrapper noloop'><a href='#' id='do-express-checkout' class='button vipps-express-checkout' title='$title'><img alt='$title' border=0 src='$buttonimgurl'></a></div>";
            $content .= "<div id='vipps-status-message'></div>";
            $this->fakepage(sprintf(__('%1$s Express Checkout','woo-vipps'), $this->get_payment_method_name()), $content);
            return;
        }
    }



    public function vipps_wait_for_payment() {
        status_header(200,'OK');
	Vipps::nocache();

        $orderid = WC()->session->get('_vipps_pending_order');

        $order = null;
        $gw = $this->gateway();

        // Failsafe for when the session disappears IOK 2018-11-19
        $no_session  = $orderid ? false : true;
        $limited_session = sanitize_text_field(@$_GET['ls']);

        // Now we *should* have a session at this point, but the session may have been deleted,
        // or the session may be in another browser, because we get here by the Vipps app opening the app.
        // If so, we will read the order id from the GET arguments and check if the auth token is correct,
        // simulating the session with that.
        // IOK 2019-11-19, changed to using GET 2023-01-23
        if ($no_session && $limited_session) {
            $orderid = intval(@$_GET['id']);
        }
        if ($orderid) {
            clean_post_cache($orderid);
            $order = wc_get_order($orderid); 
        }

        // if we came here with no session, check to see if we are allowed to do stuff with the order.
        if ($order && $no_session) {
            if (!$order->get_meta('_vipps_limited_session') || (!wp_check_password($limited_session, $order->get_meta('_vipps_limited_session')))) {
                $this->log("Wrong order session id on Vipps payment return url", 'error');
                $order = null; $orderid=0;
            } else {
                $session = WC()->session;
                if (!$session->has_session()) {
                    $session->set_customer_session_cookie(true);
                }
                $session->set('_vipps_pending_order', $orderid);
            }
        }


        do_action('woo_vipps_wait_for_payment_page',$order);

        $deleted_order=0;
        if ($orderid && !$order) {
            // If this happens, we actually did have an order, but it has been deleted, which must mean that it was cancelled.
            // Concievably a hook on the 'cancel'-transition or in the callback handlers could clean that up before we get here. IOK 2019-09-26
            $this->log(__("In order return: The order %1\$d  seems to be deleted", 'woo-vipps'), 'debug');
            $deleted_order=1;
        }

        if (!$order && !$deleted_order) wp_die(__('Unknown order', 'woo-vipps'));

        // If we are done, we are done, so go directly to the end. IOK 2018-05-16
        $status = $deleted_order ? 'cancelled' : $order->get_status();

        // This is for debugging only - set to false to ensure we wait for the callback. IOK 2023-08-04
        $do_poll = true;

        // Still pending, no callback. Make a call to the server as the order might not have been created. IOK 2018-05-16
        if ($do_poll && $status == 'pending') {
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

        $payment = 'notchecked';
        if ($do_poll) {
            $payment = $deleted_order ? 'cancelled' : $gw->check_payment_status($order);
        }

        // All these payment statuses are successes so go to the thankyou page. 
        if ($payment == 'authorized' || $payment == 'complete') {
            // IOK 2023-07-17 this used to be called in the woocommerce_thankyou hook, now we do it here instead, since 
            // we may need to be logged in to be able to get to that hook.
            $this->woocommerce_before_thankyou($order->get_id());
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
        $content .= "<div id='waiting'><p>" . sprintf(__('Waiting for confirmation of purchase from %1$s','woo-vipps'), $this->get_payment_method_name());

        if ($signal && !is_file($signal)) $signal = '';
        $signalurl = $this->callbackSignalURL($signal);

        $content .= "</p></div>";

        // We impersonate the woocommerce-checkout form here mainly to work with the Pixel Your Site plugin IOK 2022-11-24
        $classlist = apply_filters("woo_vipps_express_checkout_form_classes", "woocommerce-checkout");
        $content .= "<form id='vippsdata' class='" . esc_attr($classlist) . "'>";
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
            if ($wp_post) {
                $wp_post->post_title = $title;
                $wp_post->post_content = $content;
                // Normalize a bit
                $wp_post->filter = 'raw'; // important
                $wp_post->post_status = 'publish';
                $wp_post->comment_status= 'closed';
                $wp_post->ping_status= 'closed';
            } else {
              $this->log(sprintf(__("Could not use special page with id %s - it seems not to exist.", 'woo-vipps'), $specialid), 'error');
            }
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
