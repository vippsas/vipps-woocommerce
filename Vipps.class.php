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

    // Get the singleton WC_GatewayVipps instance
    public function gateway() {
        require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");
        return WC_Gateway_Vipps::instance();
    }


    public function init () {
        add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));

        // Cart restoration and other post-purchase actions, mostly for express checkout IOK 2020-10-09
        add_action('woocommerce_thankyou_vipps', array($this, 'woocommerce_thankyou'), 10, 1); 

        add_filter('woocommerce_my_account_my_orders_actions', array($this,'woocommerce_my_account_my_orders_actions'), 10, 2);

        // Used in 'compat mode' only to add products to the cart
        add_filter('woocommerce_add_to_cart_redirect', array($this,  'woocommerce_add_to_cart_redirect'), 10, 1);

        $this->add_shortcodes();

        // Offload work to wp-cron so it can be done in the background on sites with heavy load IOK 2020-04-01
        add_action('vipps_cron_cleanup_hook', array($this, 'cron_cleanup_hook'));

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

        // Ajax just for the backend
        add_action('wp_ajax_vipps_create_shareable_link', array($this, 'ajax_vipps_create_shareable_link'));
        add_action('wp_ajax_vipps_link_qr', array($this, 'ajax_vipps_link_qr'));
        add_action('wp_ajax_vipps_payment_details', array($this, 'ajax_vipps_payment_details'));

        // Link to the settings page from the plugin list
        add_filter( 'plugin_action_links_'.plugin_basename( plugin_dir_path( __FILE__ ) . 'woo-vipps.php'), array($this, 'plugin_action_links'));

        if ($gw->enabled == 'yes' && $gw->is_test_mode()) {
            $what = __('Vipps is currently in test mode - no real transactions will occur', 'woo-vipps');
            $this->add_vipps_admin_notice($what,'info');
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

    // Add a link to the settings page from the plugin list
    public function plugin_action_links ($links) {
        $link = '<a href="'.esc_url(admin_url('/admin.php?page=wc-settings&tab=checkout&section=vipps')). '">'.__('Settings', 'woo-vipps').'</a>';
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
    public function add_vipps_admin_notice ($text, $type='info') {
                add_action('admin_notices', function() use ($text,$type) {
                        $logo = plugins_url('img/vipps_logo_rgb.png',__FILE__);
                        $text= "<img style='height:40px;float:left;' src='$logo' alt='Vipps-logo'> $text";
                        $message = sprintf($text, admin_url('admin.php?page=wc-settings&tab=checkout&section=vipps'));
                        echo "<div class='notice notice-vipps notice-$type is-dismissible'><p>$message</p></div>";
                        });
    }

    // This function will delete old orders that were cancelled before the Vipps action was completed. We keep them for
    // 10 minutes so we can work with them in hooks and callbacks after they are cancelled. IOK 2019-10-22
    protected function delete_old_cancelled_orders() {
        global $wpdb;
        $cutoff = time() - 600; // Ten minutes old orders: Delete them
        $delendaq = $wpdb->prepare("SELECT o.ID from {$wpdb->postmeta} m join {$wpdb->posts} o on (m.meta_key='_vipps_delendum' and o.id=m.post_id)
                WHERE o.post_type = 'shop_order' && m.meta_value=1 && o.post_status = 'wc-cancelled' && o.post_modified_gmt < %s limit 30", gmdate('Y-m-d H:i:s', $cutoff));
        $delenda = $wpdb->get_results($delendaq, ARRAY_A);
        foreach ($delenda as $del) {
            wp_delete_post($del['ID']);
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
        wp_register_script('vipps-admin',plugins_url('js/vipps-admin.js',__FILE__),array('jquery'),filemtime(dirname(__FILE__) . "/js/vipps-admin.js"), 'true');
        wp_localize_script('vipps-admin', 'VippsConfig', $this->vippsJSConfig);
        wp_enqueue_script('vipps-admin');

        wp_enqueue_style('vipps-admin-style',plugins_url('css/vipps-admin.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/vipps-admin.css"), 'all');
    }

    public function notice_is_test_mode() {
    }

    public function admin_menu () {
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

    public function wp_enqueue_scripts() {

        //  We are going to use the 'hooks' library introduced by WP 5.1, but we still support WP 4.7. So if this isn't enqueues 
        //  (which it only is if Gutenberg is active) or not provided at all, add it now.
        if (!wp_script_is( 'wp-hooks', 'registered')) {
            wp_register_script('wp-hooks', plugins_url('/compat/hooks.min.js', __FILE__));
        }

        wp_register_script('vipps-gw',plugins_url('js/vipps.js',__FILE__),array('jquery','wp-hooks'),filemtime(dirname(__FILE__) . "/js/vipps.js"), 'true');
        wp_localize_script('vipps-gw', 'VippsConfig', $this->vippsJSConfig);
        wp_enqueue_script('vipps-gw');

        wp_enqueue_style('vipps-gw',plugins_url('css/vipps.css',__FILE__),array(),filemtime(dirname(__FILE__) . "/css/vipps.css"));

    }


    public function add_shortcodes() {
        add_shortcode('woo_vipps_buy_now', array($this, 'buy_now_button_shortcode'));
        add_shortcode('woo_vipps_express_checkout_button', array($this, 'express_checkout_button_shortcode'));
        add_shortcode('woo_vipps_express_checkout_banner', array($this, 'express_checkout_banner_shortcode'));
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
        $imgurl = plugins_url('img/hurtigkasse.svg',__FILE__);
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
            update_post_meta($id, '_vipps_buy_now_button', $_POST['woo_vipps_add_buy_now_button']);
        }
        // This is for the shareable links.
        if (isset($_POST['woo_vipps_shareable_delenda'])) {
            $delenda = $_POST['woo_vipps_shareable_delenda'];
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
        if ($gw->get_option('singleproductexpress') == 'some') {
            $button = sanitize_text_field(get_post_meta( get_the_ID(), '_vipps_buy_now_button', true));
            echo '<div class="options_group">';
            echo "<div class='blurb' style='margin-left:13px'><h4>";
            echo __("Buy-now button", 'woo-vipps') ;
            echo "<h4></div>";
            echo "<input type='hidden' name='woo_vipps_add_buy_now_button' value='no' />";
            woocommerce_wp_checkbox( array(
                        'id'      => 'woo_vipps_add_buy_now_button',
                        'value'   => $button,
                        'label'   => __('Add  \'Buy now with Vipps\' button', 'woo-vipps'),
                        'desc_tip' => true,
                        'description' => __('Add a \'Buy now with Vipps\'-button to this product','woo-vipps')
                        ) ); 
            echo '</div>';
        }
    }
    public function product_options_vipps_shareable_link() {
        global $post;
        $product = wc_get_product($post->ID);
        $variable = ($product->get_type() == 'variable');
        $shareables = get_post_meta($post->ID,'_vipps_shareable_links', false);
        ?>
            <div class="options_group">
            <div class='blurb' style='margin-left:13px'>
            <h4><?php echo __("Shareable links", 'woo-vipps') ?></h4>
            <p><?php echo __("Shareable links are links you can share externally on banners or other places that when followed will start Express Checkout of this product immediately. Maintain these links here for this product.", 'woo-vipps'); ?>   </p>
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
            <a class="qraction" href='javascript:void(0)'>[QR]</a>
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
           <a class="qraction" title="<?php echo __('Create QR-code for link','woo-vipps'); ?>" href='javascript:void(0)'>[QR]</a>
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
        $prodid = sprintf("%d",$_POST['prodid']);
        $varid = sprintf("%d",$_POST['varid']);

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

    // Create a QR code for a shareable link for printing on posters and such.. or just for demos
    public function ajax_vipps_link_qr() {
        $ok = check_ajax_referer('share_link_nonce','vipps_share_sec',false);
        if (!$ok) {
            wp_die(__("You are not allowed to use this link to create QR codes",'woo-vipps'));
        }
        $url = $_GET['url']; 
        $key = $_GET['key']; 
        if (!$url) {
            wp_die(__("The requested link does not exist", 'woo-vipps'));
        }
        // External library, may have been included by other parties IOK 2018-10-04
        if (!class_exists('QRcode')) {
            require_once(dirname(__FILE__) ."/tools/phpqrcode/phpqrcode.php");
        }
        if (!method_exists('QRcode','png')) {
            wp_die(__("Cannot create QR code - library is missing or does not work", 'woo-vipps'));
        }
        header("Content-disposition: inline; filename='qr-$key.png'");
        QRcode::png($url,false, QR_ECLEVEL_H, 4);
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
        $orderid = $_REQUEST['orderid'];
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
        print  __('All values in ører (1/100 NOK)', 'woo-vipps') . "<br>";
        if (!empty(@$details['transactionSummary'])) {
            $ts = $details['transactionSummary'];
            print "<h3>" . __('Transaction summary', 'woo-vipps') . "</h3>";
            print __('Capured amount', 'woo-vipps') . ":" . @$ts['capturedAmount'] . "<br>";
            print __('Remaining amount to capture', 'woo-vipps') . ":" . @$ts['remainingAmountToCapture'] . "<br>";
            print __('Refunded amount', 'woo-vipps') . ":" . @$ts['refundedAmount'] . "<br>";
            print __('Remaining amount to refund', 'woo-vipps') . ":" . @$ts['remainingAmountToRefund'] . "<br>";
        }
        if (!empty(@$details['shippingDetails'])) {
            $ss = $details['shippingDetails'];
            print "<h3>" . __('Shipping details', 'woo-vipps') . "</h3>";
            print __('Address', 'woo-vipps') . ": " . htmlspecialchars(join(', ', array_values(@$ss['address']))) . "<br>";
            print __('Shipping method', 'woo-vipps') . ": " . htmlspecialchars(@$ss['shippingMethod']) . "<br>"; 
            print __('Shipping cost', 'woo-vipps') . ": " . @$ss['shippingCost'] . "<br>";
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
        return $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_vipps_orderid' AND meta_value = %s", $vippsorderid) );
    }

    // Special pages, and some callbacks. IOK 2018-05-18 
    public function template_redirect() {
        // Handle special callbacks
        $special = $this->is_special_page() ;


        if ($special) return $this->$special();

        $consentremoval = $this->is_consent_removal();
        if ($consentremoval) return  $this->vipps_consent_removal_callback($consentremoval);

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
                   $storeapi = "wc/store/products";
                   if (false === strpos($request_uri, $rest_prefix . 'wc/store')) return $description;;

                   // Now add a small tag to product descriptions if this product should be purchasable.
                   if (!$this->loop_single_product_is_express_checkout_purchasable($product)) return $description;
                   return $description . "<span class='_product_metadata _vipps_metadata _prod_{$product->get_id()}' data-vipps-purchasable='1'></span>";
                   },10,2);
        add_action( 'enqueue_block_editor_assets', function () {
                wp_enqueue_script( 'create-block-vipps-products-block-extension', plugins_url( 'Blocks/Products/js/index.js', __FILE__), array( 'wc-blocks-registry','wp-i18n','wp-element','vipps-admin' ), '1.0.0', true );
                wp_enqueue_script( 'create-block-vipps-products-block-editor', plugins_url( 'Blocks/Products/js/editor.js', __FILE__ ), array( 'wc-blocks','wp-i18n','wp-element','vipps-admin'), '1.0.0', true );
        });
        add_action( 'wp_enqueue_scripts', function () {
           wp_enqueue_script( 'create-block-vipps-products-block-extension', plugins_url( 'Blocks/Products/js/index.js', __FILE__ ), array( 'wc-blocks-registry','wp-i18n','wp-element','vipps-gw' ), '1.0.0', true );
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
<div class="wp-block-button  wc-block-components-product-button wc-block-button-vipps"><a javascript="void(0)" data-product-id="$pid" class="single-product button vipps-buy-now wp-block-button__link initialized" title="$title"><span class="vippsbuynow">$text</span><img class="inline vipps-logo negative" src="$logo" alt="Vipps" border="0"></a></div>
EOF;
           return $stripped . $button . "</li>";
        }, 10, 3);

    }

    public function plugins_loaded() {
        $ok = load_plugin_textdomain('woo-vipps', false, basename( dirname( __FILE__ ) ) . "/languages");

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
        add_action('template_redirect', array($this,'template_redirect'));
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

        // Used both in admin and non-admin-scripts, load as quick as possible IOK 2020-09-03
        $this->vippsJSConfig = array();
        $this->vippsJSConfig['vippsajaxurl'] =  admin_url('admin-ajax.php');
        $this->vippsJSConfig['BuyNowWith'] = __('Buy now with', 'woo-vipps');
        $this->vippsJSConfig['BuyNowWithVipps'] = __('Buy now with Vipps', 'woo-vipps');
        $this->vippsJSConfig['vippslogourl'] = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        $this->vippsJSConfig['vippssmileurl'] = plugins_url('img/vipps-smile-orange.png',__FILE__);
        $this->vippsJSConfig['vippsbuynowbutton'] = __( 'Vipps Buy Now button', 'woo-vipps' );
        $this->vippsJSConfig['vippsbuynowdescription'] =  __( 'Add a Vipps Buy Now-button to the product block', 'woo-vipps');


        // IOK 2020-03-17: Klarna Checkout now supports external payment methods, such as Vipps. This is great, but we need first to check
        // that any user hasn't already installed the free plugin for this created by Krokedil. If they have, this filter will be present:
        if (class_exists('KCO') && defined('KCO_WC_VERSION') && version_compare(KCO_WC_VERSION, '2.0.0', '>=')) {
            if (has_filter('kco_wc_api_request_args', 'kcoepm_create_order_vipps')) {
                // Vipps external payment support is already present - notify user and do nothing. IOK 2020-03-18
                if (is_admin()) {
// We don't add any messages for this quite yet. IOK 2020-03-18
//                    $this->add_vipps_admin_notice(__("It seems you have installed the plugin Klarna Checkout (V3) Vipps External Payment Method for WooCommerce. The functionality of this plugin is now provided in the standard Checkout with Vipps for WooCommerce plugin, so it is no longer needed.","woo-vipps"));
                }
            } else {
                require_once(dirname(__FILE__) . "/VippsKCSupport.class.php");
                VippsKCSupport::init();
            }
        }

        // If the site supports Gutenberg Blocks, support the Checkout block IOK 2020-08-10
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once(dirname(__FILE__) . "/Blocks/Payment/Vipps.class.php");
            Automattic\WooCommerce\Blocks\Payments\Integrations\Vipps::register();
        }

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
        $refunded = $order->get_meta('_vipps_refunded');
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
        $raw_post = @file_get_contents( 'php://input' );
        $result = @json_decode($raw_post,true);
        do_action('woo_vipps_vipps_callback', $result,$raw_post);

        if (!$result) {
            $error = json_last_error_msg();
            $this->log(__("Did not understand callback from Vipps:",'woo-vipps') . " " .  $raw_post, 'error');
            $this->log(sprintf(__("Error was: %s",'woo-vipps'), $error));
            return false;
        }

        $vippsorderid = $result['orderId'];
        $orderid = $this->getOrderIdByVippsOrderId($vippsorderid);

        // Ensure we use the same session as for the original order IOK 2019-10-21
        $this->callback_restore_session($orderid);

        do_action('woo_vipps_callback', $result);

        // a small bit of security
        $order = wc_get_order($orderid);
        if (!$order->get_meta('_vipps_authtoken') || (!wp_check_password($_REQUEST['tk'], $order->get_meta('_vipps_authtoken')))) {
            $this->log("Wrong authtoken on Vipps payment details callback", 'error');
            exit();
        }
        $gw = $this->gateway();
        $gw->handle_callback($result);

        // Just to be sure, save any changes made to the session by plugins/hooks IOK 2019-10-22
        if (is_a(WC()->session, 'WC_Session_Handler')) WC()->session->save_data();
        exit();
    }

    // Helper function to get ISO-3166 two-letter country codes from country names as supplied by Vipps
    public function country_to_code($countryname) {
        if (!$this->countrymap) $this->countrymap = unserialize(file_get_contents(dirname(__FILE__) . "/lib/countrycodes.php"));
        $mapped = @$this->countrymap[strtoupper($countryname)];
        $code = WC()->countries->get_base_country();
        if ($mapped) $code = $mapped;
        $code = apply_filters('woo_vipps_country_to_code', $code, $countryname);
        return  $code;
    }

    // When we get callbacks from Vipps, we want to restore the Woo session in place for the order.
    // For many plugins this is strictly neccessary because they don't check to see if there is a session
    // or not - and for many others, wrong results are produced without the (correct) session. IOK 2019-10-22
    protected function callback_restore_session ($orderid) {
        $this->callbackorder = $orderid;
        require_once(dirname(__FILE__) . "/VippsCallbackSessionHandler.class.php");
        add_filter('woocommerce_session_handler', function ($handler) { return "VippsCallbackSessionHandler";});
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
        $newcart = array();

        foreach(WC()->session->get('cart',true) as $key => $values) {
            $product = wc_get_product( $values['variation_id'] ? $values['variation_id'] : $values['product_id'] );
            $values['data'] = $product;
            $newcart[$key] = $values;
        }
        WC()->cart->set_cart_contents($newcart);
        WC()->cart->calculate_totals();

        // IOK 2020-07-01 plugins expect this to be called: hopefully they'll not get confused by it happening twice
        do_action( 'woocommerce_cart_loaded_from_session', WC()->cart);
        WC()->cart->calculate_totals(); // And if any of them changed anything, recalculate the totals again!

        return WC()->session;
    }

    // Based on either a logged-in user, or the stores' default address, get the address to use when using
    // the Express Checkout static shipping feature
    // This is neccessary because WC()->customer->set_shipping_address_to_base() only sets country and state.
    // IOK 2020-03-18
    public function get_static_shipping_address_data () {
         // This is the format used by the Vipps callback, we are going to mimic this.
         $defaultdata = array('addressId'=>0, "addressLine1"=>"", "addressLine2"=>"", "country"=>"Norway", "city"=>"", "postalCode"=>"", "postCode"=>"", "addressType"=>"H"); 
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
             $defaultdata['addressLine2'] = $countries->get_base_address_2();
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
        $callback = @$_REQUEST['callback'];
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
        $addressid = $vippsdata['addressId'];
        $addressline1 = $vippsdata['addressLine1'];
        $addressline2 = $vippsdata['addressLine2'];

        // IOK 2019-08-26 apparently the apps contain a lot of addresses with duplicate lines
        if ($addressline1 == $addressline2) $addressline2 = '';

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
        WC()->customer->set_billing_location($country,'',$postcode,$city);
        WC()->customer->set_shipping_location($country,'',$postcode,$city);


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
            $shipping_methods['none_required:0'] = new WC_Shipping_Rate('none_required:0',__('No shipping required','woo-vipps'),0,array(array('total'=>0)), 'none_required', 0);
        } else {
            $package = array();
            $package['contents'] = $acart->cart_contents;
            $package['contents_cost'] = wc_format_decimal($order->get_total() - $order->get_shipping_total() - $order->get_shipping_tax(),'');
            $package['destination'] = array();
            $package['destination']['country']  = $country;
            $package['destination']['state']    = '';
            $package['destination']['postcode'] = $postcode;
            $package['destination']['city']     = $city;
            $package['destination']['address']  = $addressline1;
            if ($addressline2 && !$addressline2 == 'null') {
                $package['destination']['address_2']= $addressline2;
            }

            $packages = apply_filters('woo_vipps_shipping_callback_packages', array($package));
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
        $methods[$chosen]['default'] = true;

        $methods = apply_filters('woo_vipps_express_checkout_shipping_rates', $methods, $order, $acart);

        $vippsmethods = array();
        $storedmethods = $order->get_meta('_vipps_express_checkout_shipping_method_table');
        if (!$storedmethods) $storedmethods= array();

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
        }
        $order->update_meta_data('_vipps_express_checkout_shipping_method_table', $storedmethods);
        $order->save();
 
        $return = array('addressId'=>intval($addressid), 'orderId'=>$vippsorderid, 'shippingDetails'=>$vippsmethods);
        $return = apply_filters('woo_vipps_vipps_formatted_shipping_methods', $return); // Mostly for debugging

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

    // This will probably be run in activate, but if the plugin is updated in other ways, will also be run on plugins_loaded. IOK 2020-04-01
    public static function maybe_add_cron_event() {
       if (!wp_next_scheduled('vipps_cron_cleanup_hook')) {
          wp_schedule_event(time(), 'hourly', 'vipps_cron_cleanup_hook');
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
        $gw = $this->gateway();
        try {
            $order_status = $gw->callback_check_order_status($order);
            $this->log("order status $order_status ");
            return $order_status;
        } catch (Exception $e) {
            $this->log($e->getMessage() . "\n" . $order->get_id(), 'error');
            return null;
        }
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
        if (!$order || $order->payment_method != 'vipps' ) return;

        // Make this filterable because you may want to only log on some users
        $do_login =  $order->get_meta('_vipps_express_checkout');
        $do_login = apply_filters('woo_vipps_login_user_on_express_checkout', $do_login, $order);
        if (!$do_login) return;

        $customer = $this->express_checkout_get_vipps_customer ($order);

        if( $customer) {
            $usermeta=get_userdata($customer->get_id());
            $iscustomer = (in_array('customer', $usermeta->roles) || in_array('subscriber', $usermeta->roles));
            // Ensure we don't have any admins with an additonal customer role logged in like this
            if($iscustomer && !user_can($customer->get_id(), 'manage_woocommerce') && !user_can($customer->get_id(),'manage_options'))  {
                wp_set_current_user($customer->get_id(), $customer->user_login);
                wp_set_auth_cookie($customer->get_id());
                do_action('wp_login', $customer->user_login, $customer);
            }
        }
    }

    // Get the customer that corresponds to the current order, maybe creating the customer if it does not exist yet and
    // the settings allow it.
    function express_checkout_get_vipps_customer($order) {
        if (!$order || $order->payment_method != 'vipps' ) return;
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
            update_post_meta( $order->get_id(), '_customer_user', $customerid );
            $order->save(); 
            if (is_multisite() && ! is_user_member_of_blog($customerid, get_current_blog_id())) {
                add_user_to_blog( get_current_blog_id(), $customerid, 'customer' );
            }
            return new WC_Customer($customerid);
        }

        // No customer yet. As we want to create users like this (set in the settings) let's do so.
        // Username will be created from email, but the settings may stop generating passwords, so we force that to be generated. IOK 2020-10-09
        $customerid = wc_create_new_customer( $email, '', wp_generate_password());
        if ($customerid && !is_wp_error($customerid)) {
            update_post_meta( $order_id, '_customer_user', $customer_id );
            $order->save(); 
            update_user_meta( $customerid, 'billing_address_1', $order->billing_address_1 );
            update_user_meta( $customerid, 'billing_address_2', $order->billing_address_2 );
            update_user_meta( $customerid, 'billing_city', $order->billing_city );
            update_user_meta( $customerid, 'billing_company', $order->billing_company );
            update_user_meta( $customerid, 'billing_country', $order->billing_country );
            update_user_meta( $customerid, 'billing_email', $order->billing_email );
            update_user_meta( $customerid, 'billing_first_name', $order->billing_first_name );
            update_user_meta( $customerid, 'billing_last_name', $order->billing_last_name );
            update_user_meta( $customerid, 'billing_phone', $order->billing_phone );
            update_user_meta( $customerid, 'billing_postcode', $order->billing_postcode );
            update_user_meta( $customerid, 'billing_state', $order->billing_state );
            update_user_meta( $customerid, 'shipping_address_1', $order->shipping_address_1 );
            update_user_meta( $customerid, 'shipping_address_2', $order->shipping_address_2 );
            update_user_meta( $customerid, 'shipping_city', $order->shipping_city );
            update_user_meta( $customerid, 'shipping_company', $order->shipping_company );
            update_user_meta( $customerid, 'shipping_country', $order->shipping_country );
            update_user_meta( $customerid, 'shipping_first_name', $order->shipping_first_name );
            update_user_meta( $customerid, 'shipping_last_name', $order->shipping_last_name );
            update_user_meta( $customerid, 'shipping_method', $order->shipping_method );
            update_user_meta( $customerid, 'shipping_postcode', $order->shipping_postcode );
            update_user_meta( $customerid, 'shipping_state', $order->shipping_state );


            return new WC_Customer($customerid);
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

        try {
            $orderid = $gw->create_partial_order();
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
        $varid = sprintf('%d',(@$_POST['variation_id']));
        $prodid = sprintf('%d',(@$_POST['product_id']));
        $sku = @$_POST['sku'];
        $quant = sprintf('%d',(@$_POST['quantity']));


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
            WC()->cart->add_to_cart($parent->get_id(),$quantity,$product->get_id());
        } else {
            WC()->cart->add_to_cart($product->get_id(),$quantity);
        }

        try {
            $orderid = $gw->create_partial_order();
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
    protected function maybe_add_static_shipping($gw,$orderid) {
        if ($gw->get_option('enablestaticshipping') == 'yes') {
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
    }

    // Check the status of the order if it is a part of our session, and return a result to the handler function IOK 2018-05-04
    public function ajax_check_order_status () {
        check_ajax_referer('vippsstatus','sec');
        wc_nocache_headers();

        $orderid= wc_get_order_id_by_order_key(@$_POST['key']);
        $transaction = wc_get_order_id_by_order_key(@$_POST['transaction']);

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
        $url = '';
        if ( !get_option('permalink_structure')) {
            $url = "/?VippsSpecialPage=$what";
        } else {
            $url = "/$what/";
        }
        return untrailingslashit(set_url_scheme(home_url(),'https')) . $url;
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
                if (preg_match("!/$special/([^/]*)!", $_SERVER['REQUEST_URI'], $matches)) {
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

        $showit = true;
        if ($product->get_price() <= 0)  $showit = false; 
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


    // This URL will when accessed add a product to the cart and go directly to the express  checkout page.
    // The argument passed must be a shareable link created for a given product - so this in effect acts as a landing page for 
    // the buying thru Vipps Express Checkout of a single product linked to in for instance banners. IOK 2018-09-24
    public function vipps_buy_product() {
        status_header(200,'OK');
        wc_nocache_headers();
        do_action('woo_vipps_express_checkout_page');

        $session = WC()->session;
        $posted = $session->get('__vipps_buy_product');
        $session->set('__vipps_buy_product', false); // Reloads won't work but that's ok.

        if (!$posted) {
            // Find product/variation using an external shareable link
            if (array_key_exists('pr',$_REQUEST)) {
                global $wpdb;
                $externalkey = $_REQUEST['pr'];
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
        if (array_key_exists('product_id',$productinfo)) $args['product_id'] = sprintf("%d", $productinfo['product_id']);
        if (array_key_exists('variation_id',$productinfo)) $args['variation_id'] = sprintf("%d", $productinfo['variation_id']);
        if (array_key_exists('product_sku',$productinfo)) $args['sku'] = $productinfo['product_sku'];
        if (array_key_exists('quantity',$productinfo)) $args['quantity'] = sprintf("%d", $productinfo['quantity']);

        $this->print_express_checkout_page(true,'do_single_product_express_checkout',$args);
    }

    //  This is a landing page for the express checkout of then normal cart - it is done like this because this could take time on slower hosts.
    public function vipps_express_checkout() {
        status_header(200,'OK');
        wc_nocache_headers();
        // We need a nonce to get here, but we should only get here when we have a cart, so this will not be cached.
        // IOK 2018-05-28
        $ok = wp_verify_nonce($_REQUEST['sec'],'express');

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
        $buttonimgurl= plugins_url('img/hurtigkasse.svg',__FILE__);


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
            $imgurl = plugins_url('img/hurtigkasse.svg',__FILE__);
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
        $authtoken = @$_GET['t'];

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
