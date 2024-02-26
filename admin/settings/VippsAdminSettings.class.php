<?php
/*
    Initializes the admin settings page which uses React for the Vipps plugin

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

class VippsAdminSettings {
    private static $instance = null;
    
    private $page_templates = null;
    private $page_list = null;

    // This returns the singleton instance of this class
    public static function instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
    } 

    // Get the singleton WC_GatewayVipps instance
    public function gateway() {
        global $Vipps;
        if (class_exists('WC_Payment_Gateway')) {
            require_once(dirname(dirname(dirname(__FILE__))) . "/WC_Gateway_Vipps.class.php");
            return WC_Gateway_Vipps::instance();
        } else {
          $Vipps->log(__("Error: Cannot instantiate payment gateway, because WooCommerce is not loaded! This can happen when WooCommerce updates itself; but if it didn't, please activate WooCommerce again", 'woo-vipps'), 'error');
          return null;
        }
    }

    // Handle the submission of the admin settings page
    public function ajax_vipps_update_admin_settings() {   
        $ok = wp_verify_nonce($_REQUEST['vippsadmin_nonce'],'vippsadmin_nonce');
        if (!$ok) {
           echo json_encode(array('ok'=>0, 'options' => [], 'msg'=>__('You don\'t have sufficient rights to edit these settings', 'woo-vipps')));
           exit();
        }
       if (!current_user_can('manage_woocommerce')) {
           echo json_encode(array('ok'=>0, 'options' => [], 'msg'=>__('You don\'t have sufficient rights to edit these settings', 'woo-vipps')));
           exit();
       }
       $msg = ""; // Message for the user.

       // Decode the settings from the values sents, then save them to "woocommerce_vipps_settings"
       $new_settings = $_POST['values'];

       // IOK FIXME This will ensure sanitization etc works as it is supposed to using the 
       // admin settings api of WooCommerce. We will however want to run this code independently, so we'll handle this 
       // by ourselves at a later point, ending it like so:
       // update_option('woocommerce_vipps_settings', $new_settings); // After sanitation etc
       $admin_options = [];
       foreach($new_settings as $key => $value) {
          $admin_options['woocommerce_vipps_' . $key]  = $value;
       }
       $this->gateway()->set_post_data($admin_options);
       $this->gateway()->process_admin_options();
//        $this->gateway()->add_error("Jaboloko!");  // Also add_warning, add_notice plz
       $errorlist = $this->gateway()->get_errors();
       $msg .= join("<br>", $errorlist);
       // end use of process_admin_options IOK 2024-01-03

       // Verify the connection to Vipps
       list($ok,$error_message) = $this->gateway()->check_connection();
       if ($ok) {
           $msg .= sprintf(__("Connection to %1\$s is OK", 'woo-vipps'), Vipps::CompanyName());
       } else {
           $msg .= sprintf(__("Could not connect to %1\$s", 'woo-vipps'), Vipps::CompanyName()) . ": $error_message";
       }
       // OK is still true here, because we will only say ok is false for *errors*, not *wrong input*. But we may want to
       // add another value to signify that as well. IOK 2023-01-03
       echo json_encode(array("ok" => true, "msg" => $msg, 'options' => get_option('woocommerce_vipps_settings')));
       exit();
   }

   // Initializes the admin settings UI for VippsMobilePay
   function init_admin_settings_page_react_ui() {
        global $Vipps;
       echo "<div class='wrap vipps-admin-settings-page'>";
       // Add nonce first.
       wp_nonce_field('vippsadmin_nonce', 'vippsadmin_nonce');

       // We must first generate the root element for the React UI before we load the React app itself, otherwise React will fail to load.
       ?>
           <div id="vipps-mobilepay-react-ui"></div><?php

       // Initializing the wordpress media plugin, so we can upload images
       wp_enqueue_media();
       $gw = $this->gateway();

       $page_templates = $gw->get_theme_page_templates();
       $page_list = $gw->get_pagelist();

       // Loads the React UI
       $reactpath = "dist";
       wp_enqueue_script('vipps-mobilepay-react-ui', plugins_url($reactpath . '/plugin.js',__FILE__), array('wp-i18n'), filemtime(__DIR__ . "/$reactpath/plugin.js"), true ); 
       wp_enqueue_style('vipps-mobilepay-react-ui', plugins_url($reactpath . '/plugin.css',__FILE__), array(), filemtime(__DIR__ . "/$reactpath/plugin.css"));

       $expresscreateuserdefault = "no";
       $vippscreateuserdefault = "no";
       // Express checkout uses verified email addresses,so we'll create users if the Login plugin is installed and WooCommerce is set to allow user registration.
       if (class_exists('VippsWooLogin')) {
          $woodefault = 'yes' === get_option('woocommerce_enable_signup_and_login_from_checkout');
          if ($woodefault) {
              $expresscreateuserdefault = "yes";
        //      $vippscreateuserdefault = "yes"; // However, for Vipps Checkout the email address is freetext so we'll treat the default a bit different.
          }
       }

       $current = get_option('woocommerce_vipps_settings');
       // New defaults based on old defaults
       $default_static_shipping_for_checkout = 'no';
       $default_ask_address_for_express = 'no';
       if ($current) {
           $default_static_shipping_for_checkout = (isset($current['enablestaticshipping'])) ? $current['enablestaticshipping'] : 'no';
           $default_ask_address_for_express = (isset($current['useExplicitCheckoutFlow']) && $current['useExplicitCheckoutFlow'] == "yes") ? "yes" : "no";
           // The old default used the same value as for Express Checkout. IOK 2023-07-27
           $vippscreateuserdefault = isset($current['expresscreateuser']) ? $current['expresscreateuser'] : $vippscreateuserdefault;
       }

       $translations = array(
               // Common translations
               'save_changes' => __('Save changes', 'woo-vipps'),
               'initial_settings' => __('Initial settings', 'woo-vipps'),

               // Main options
               'main_options_title' => __('Main options', 'woo-vipps'),
               'main_options_description' => __('Main options description', 'woo-vipps'),
   
               'enabled_title' => __('Enable/Disable', 'woocommerce'),
               'enabled_label' => sprintf(__('Enable %1$s', 'woo-vipps'), Vipps::CompanyName()),
               
               'payment_method_name_title' => __('Payment method', 'woo-vipps'),
               'payment_method_name_label' => __('Choose which payment method should be displayed to users at checkout', 'woo-vipps'),
               'payment_method_name_options_vipps' => __('Vipps', 'woo-vipps'),
               'payment_method_name_options_mobilepay' => __('MobilePay', 'woo-vipps'),
               
               'orderprefix_title' => __('Order-id Prefix', 'woo-vipps'),
               'orderprefix_label' => __('An alphanumeric textstring to use as a prefix on orders from your shop, to avoid duplicate order-ids', 'woo-vipps'),
               
                           'merchantSerialNumber_title' => __('Merchant Serial Number', 'woo-vipps'),
               'merchantSerialNumber_label' => __('Your "Merchant Serial Number" from the Developer tab on https://portal.vipps.no', 'woo-vipps'),
               
               'clientId_title' => __('Client Id', 'woo-vipps'),
               'clientId_label' => __('Find your account under the "Developer" tab on https://portal.vipps.no/ and choose "Show keys". Copy the value of "client_id"', 'woo-vipps'),
               
               'secret_title' => __('Client Secret', 'woo-vipps'),
               'secret_label' => __('Find your account under the "Developer" tab on https://portal.vipps.no/ and choose "show keys". Copy the value of "client_secret"', 'woo-vipps'),
               
               'Ocp_Apim_Key_eCommerce_title' => __('Subscription Key', 'woo-vipps'),
               'Ocp_Apim_Key_eCommerce_label' => __('Find your account under the "Developer" tab on https://portal.vipps.no/ and choose "show keys". Copy the value of "Vipps-Subscription-Key"', 'woo-vipps'),
               
               'result_status_title' => sprintf(__('Order status on return from %1$s', 'woo-vipps'), Vipps::CompanyName()),
               'result_status_label' => __('Choose default order status for reserved (not captured) orders', 'woo-vipps'),
               'result_status_description' => __('By default, orders that are <b>reserved</b> but not <b>captured</b> will have the order status \'On hold\' until you capture the sum (by changing the status to \'Processing\' or \'Complete\')<br> Some stores prefer to use \'On hold\' only for orders where there are issues with the payment. In this case you can choose  \'Processing\' instead, but you must then ensure that you do <b>not ship the order until after you have done capture</b> - because the \'capture\' step may in rare cases fail. <br>If you choose this setting, capture will still automatically happen on the status change to \'Complete\' ', 'woo-vipps'),
               'result_status_options_on-hold' => __('On hold', 'woo-vipps'),
               'result_status_options_processing' => __('Processing', 'woo-vipps'),
               
               'title_title' => __('Title', 'woocommerce'),
               'title_description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
               
               'description_title' => __('Description', 'woocommerce'),
               'description_description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
               
               'vippsdefault_title' => sprintf(__('Use %1$s as default payment method on checkout page', 'woo-vipps'), $gw->get_payment_method_name()),
               'vippsdefault_label' => sprintf(__('%1$s is default payment method', 'woo-vipps'), $gw->get_payment_method_name()),
               'vippsdefault_description' => sprintf(__('Enable this to use %1$s as the default payment method on the checkout page, regardless of order.', 'woo-vipps'), $gw->get_payment_method_name()),
               
               // Express options
               'express_options_title' => sprintf(__('Express Checkout', 'woo-vipps')),
               'express_options_description' => sprintf(__("%1\$s allows you to buy products by a single click from the cart page or directly from product or catalog pages. Product will get a 'buy now' button which will start the purchase process immediately.", 'woo-vipps'), Vipps::ExpressCheckoutName()),
           
               'cartexpress_title' => __('Enable Express Checkout in cart', 'woo-vipps'),
               'cartexpress_label' => __('Enable Express Checkout in cart', 'woo-vipps'),
               'cartexpress_description' => sprintf(__('Enable this to allow customers to shop using %1$s directly from the cart with no login or address input needed', 'woo-vipps'), Vipps::ExpressCheckoutName()) . '.<br>' .
                   sprintf(__('Please note that for Express Checkout, shipping must be calculated in a callback from the %1$s app, without any knowledge of the customer. This means that Express Checkout may not be compatible with all Shipping plugins or setup. You should test that your setup works if you intend to provide this feature.', 'woo-vipps'), Vipps::CompanyName()),
           
               'singleproductexpress_title' => __('Enable Express Checkout for single products', 'woo-vipps'),
               'singleproductexpress_label' => __('Enable Express Checkout for single products', 'woo-vipps'),
               'singleproductexpress_options_none' => __('No products', 'woo-vipps'),
               'singleproductexpress_options_some' => __('Some products', 'woo-vipps'),
               'singleproductexpress_options_all' => __('All products', 'woo-vipps'),
   
               'singleproductexpress_description' => sprintf(__('Enable this to allow customers to buy a product using %1$s directly from the product page. If you choose \'some\', you must enable this on the relevant products', 'woo-vipps'), Vipps::ExpressCheckoutName()),
           
               'singleproductexpressarchives_title' => __('Add \'Buy now\' button on catalog pages too', 'woo-vipps'),
               'singleproductexpressarchives_label' => __('Add the button for all relevant products on catalog pages', 'woo-vipps'),
               'singleproductexpressarchives_description' => sprintf(__('If %1$s is enabled for a product, add the \'Buy now\' button to catalog pages too', 'woo-vipps'), Vipps::ExpressCheckoutName()),
           
               'expresscheckout_termscheckbox_title' => sprintf(__('Add terms and conditions checkbox on %1$s', 'woo-vipps'), Vipps::ExpressCheckoutName()),
               'expresscheckout_termscheckbox_label' => sprintf(__('Always ask for confirmation on %1$s', 'woo-vipps'), Vipps::ExpressCheckoutName()),
               'expresscheckout_termscheckbox_description' => sprintf(__('When using %1$s, ask the user to confirm that they have read and accepted the store\'s terms and conditions before proceeding', 'woo-vipps'), Vipps::ExpressCheckoutName()),
           
               'expresscheckout_always_address_title' => __('Always ask for address, even if products don\'t need shipping', 'woo-vipps'),
               'expresscheckout_always_address_label' => __('Always ask the user for their address, even if you don\'t need it for shipping', 'woo-vipps'),
               'expresscheckout_always_address_description' => __('If the order contains only "virtual" products that do not need shipping, we do not normally ask the user for their address - but check this box to do so anyway.', 'woo-vipps'),
           
               'enablestaticshipping_title' => __('Enable static shipping for Express Checkout', 'woo-vipps'),
               'enablestaticshipping_label' => __('Enable static shipping', 'woo-vipps'),
               'enablestaticshipping_description' => __('If your shipping options do not depend on the customer\'s address, you can enable \'Static shipping\', which will precompute the shipping options when using Express Checkout so that this will be much faster. If you do this and the customer isn\'t logged in, the base location of the store will be used to compute the shipping options for the order. You should only use this if your shipping is actually \'static\', that is, does not vary based on the customer\'s address. So fixed price/free shipping will work. If the customer is logged in, their address as registered in the store will be used, so if your customers are always logged in, you may be able to use this too.', 'woo-vipps'),
           
               'expresscreateuser_title' => __('Create new customers on Express Checkout', 'woo-vipps'),
               'expresscreateuser_label' => __('Create new customers on Express Checkout', 'woo-vipps'),
               'expresscreateuser_description' => sprintf(__('Enable this to create and log in new customers when using express checkout. Otherwise, these will all be guest checkouts. If you have "Login with Vipps" installed, this will be the default (unless you have turned off user creation in WooCommerce itself)', 'woo-vipps'), Vipps::CompanyName()),
           
               'singleproductbuynowcompatmode_title' => __('"Buy now" compatibility mode', 'woo-vipps'),
               'singleproductbuynowcompatmode_label' => __('Activate compatibility mode for all "Buy now" buttons', 'woo-vipps'),
               'singleproductbuynowcompatmode_description' => __('Choosing this will use a different method of handling the "Buy now" button on a single product, which will work for more product types and more plugins - while being <i>slightly</i> less smooth. Use this if your product needs more configuration than simple or standard variable products', 'woo-vipps'),
           
               'deletefailedexpressorders_title' => __('Delete failed Express Checkout Orders', 'woo-vipps'),
               'deletefailedexpressorders_label' => __('Delete failed Express Checkout Orders', 'woo-vipps'),
               'deletefailedexpressorders_description' => __('As Express Checkout orders are anonymous, failed orders will end up as "cancelled" orders with no information in them. Enable this to delete these automatically when cancelled - but test to make sure no other plugin needs them for anything.', 'woo-vipps'),
   
               // Checkout options
               'checkout_options_title' => sprintf(__('Checkout', 'woo-vipps'), Vipps::CompanyName()),
               'checkout_options_description' => sprintf(__("%1\$s is a new service from %2\$s which replaces the usual WooCommerce checkout page entirely, replacing it with a simplified checkout screen providing payment both with %2\$s and credit card. Additionally, your customers will get the option of providing their address information using their %2\$s app directly.", 'woo-vipps'), Vipps::CheckoutName(), Vipps::CompanyName()),
   
               'vipps_checkout_enabled_title' => sprintf(__('Activate Alternative %1$s', 'woocommerce'), Vipps::CheckoutName()),
               'vipps_checkout_enabled_label' => sprintf(__('Enable Alternative %1$s screen, replacing the standard checkout page', 'woo-vipps'), Vipps::CheckoutName()),
               'vipps_checkout_enabled_description' => sprintf(__('If activated, this will <strong>replace</strong> the standard Woo checkout screen with %1$s, providing easy checkout using %1$s or credit card, with no need to type in addresses.', 'woo-vipps'), Vipps::CheckoutName()),
              
               'checkoutcreateuser_title' => sprintf(__('Create new customers on %1$s', 'woo-vipps'), Vipps::CheckoutName()),
               'checkoutcreateuser_label' => sprintf(__('Create new customers on %1$s', 'woo-vipps'), Vipps::CheckoutName()),
               'checkoutcreateuser_description' => sprintf(__('Enable this to create and login customers when using %1$s. Otherwise these will all be guest checkouts. If using, you may want to install Login with %1$s too.', 'woo-vipps'), Vipps::CheckoutName()),
   
               'enablestaticshipping_checkout_title' => sprintf(__('Enable static shipping for %1$s', 'woo-vipps'), Vipps::CheckoutName()),
               'enablestaticshipping_checkout_label' => __('Enable static shipping', 'woo-vipps'),
               'enablestaticshipping_checkout_description' => sprintf(__('If your shipping options do not depend on the customers address, you can enable \'Static shipping\', which will precompute the shipping options when using %1$s so that this will be much faster. If you do this and the customer isn\'t logged in, the base location of the store will be used to compute the shipping options for the order. You should only use this if your shipping is actually \'static\', that is, does not vary based on the customers address. So fixed price/free shipping will work. If the customer is logged in, their address as registered in the store will be used, so if your customers are always logged in, you may be able to use this too.', 'woo-vipps'), Vipps::CheckoutName()),
   
               'requireUserInfo_checkout_title' => __('Ask the user to consent to share user information', 'woo-vipps'),
               'requireUserInfo_checkout_label' => __('Ask the user to consent to share user information', 'woo-vipps'),
               'requireUserInfo_checkout_description' => sprintf(__('If using %1$s, ask for the users consent to share user information with the store. This will allow better integration between Login With %1$s but will add another step to first-time buyers.', 'woo-vipps'), Vipps::CompanyName()),
   
               'noAddressFields_title' => __('Drop the address fields on the Checkout screen', 'woo-vipps'),
               'noAddressFields_label' => __('Don\'t require the address fields', 'woo-vipps'),
               'noAddressFields_description' => __('If your products <i>don\'t require shipping</i>, either because they are digital downloads, immaterial products or delivering the products directly on purchase, you can check this box. The user will then not be required to provide an address, which should speed things up a bit. If your products require shipping, this will have no effect. NB: If you have plugins that require shipping information, then this is not going to work very well.','woo-vipps'),
   
               'noContactFields_title' => __('Drop the contact fields on the Checkout screen', 'woo-vipps'),
               'noContactFields_label' => __('Don\'t require the contact fields', 'woo-vipps'),
               'noContactFields_description' => __('If your products <i>don\'t require shipping</i> as above, and you also don\'t care about the customers name or contact information, you can drop this too! The customer fields will then be filled with a placeholder. NB: If you have plugins that require contact information, then this is not going to work very well. Also, for this to work you have to check the \'no addresses\' box as well.','woo-vipps'),
               
               // Checkout options -- shipping options
               'checkout_shipping_title' => sprintf(__('%1$s Shipping Methods', 'woo-vipps'), Vipps::CheckoutName()),
               'checkout_shipping_description' => sprintf(__("When using %1\$s, you have the option to use %1\$s specific shipping methods with extended features for certain carriers. These will add an apropriate logo as well as extended delivery options for certain methods. For some of these, you need to add integration data from the carriers below. You can then add these shipping methods to your shipping zones the normal way, but they will only appear in the %1\$s screen.", 'woo-vipps'), Vipps::CheckoutName()),
   
               'vcs_posten_title' => __('Posten Norge', 'woo-vipps'),
               'vcs_posten_label' => sprintf(__('Support Posten Norge as a shipping method in %1$s', 'woo-vipps'), Vipps::CheckoutName()),
               'vcs_posten_description' => sprintf(__('Activate this for Posten Norge as a %1$s Shipping method.', 'woo-vipps'), Vipps::CheckoutName()),
   
               'vcs_postnord_title' => __('PostNord', 'woo-vipps'),
               'vcs_postnord_label' => sprintf(__('Support PostenNord as a shipping method in %1$s', 'woo-vipps'), Vipps::CheckoutName()),
               'vcs_postnord_description' => sprintf(__('Activate this for PostNord as a %1$s Shipping method.', 'woo-vipps'), Vipps::CheckoutName()),
   
               'vcs_porterbuddy_title' => __('Porterbuddy', 'woo-vipps'),
               'vcs_porterbuddy_label' => sprintf(__('Support Porterbuddy as a shipping method in %1$s', 'woo-vipps'), Vipps::CheckoutName()),
               'vcs_porterbuddy_description' => sprintf(__('Activate this for Porterbuddy as a %1$s Shipping method. Your store address will be used as the pick-up point and your admin email will be used for booking information from Porterbuddy.' ,'woo-vipps'), Vipps::CheckoutName()),
   
               'vcs_porterbuddy_publicToken_title' => __('Porterbuddy public token', 'woo-vipps'),
               'vcs_porterbuddy_publicToken_label' => __('Porterbuddy public token', 'woo-vipps'),
               'vcs_porterbuddy_publicToken_description' => __('The public key provided to you by Porterbuddy','woo-vipps'),
   
               'vcs_porterbuddy_apiKey_title' => __('Porterbuddy API key', 'woo-vipps'),
               'vcs_porterbuddy_apiKey_label' => __('Porterbuddy API key', 'woo-vipps'),
               'vcs_porterbuddy_apiKey_description' => __('The API key provided to you by Porterbuddy','woo-vipps'),
   
               'vcs_porterbuddy_phoneNumber_title' => __('Porterbuddy Phone Number', 'woo-vipps'),
               'vcs_porterbuddy_phoneNumber_label' => __('Porterbuddy Phone Number', 'woo-vipps'),
               'vcs_porterbuddy_phoneNumber_description' => __('Your phone number where Porterbuddy may send you important messages. Format must be MSISDN (including country code). Example: "4791234567"','woo-vipps'),
   
               'vcs_instabox_title' => __('Instabox', 'woo-vipps'),
               'vcs_instabox_label' => sprintf(__('Support Instabox as a shipping method in %1$s', 'woo-vipps'), Vipps::CheckoutName()),
               'vcs_instabox_description' => sprintf(__('Activate this for Instabox as a %1$s Shipping method.' ,'woo-vipps'), Vipps::CheckoutName()),
   
               'vcs_instabox_clientId_title' => __('Instabox Client Id', 'woo-vipps'),
               'vcs_instabox_clientId_label' => __('Instabox Client Id', 'woo-vipps'),
               'vcs_instabox_clientId_description' => __('The Client Id provided to you by Instabox','woo-vipps'),
   
               'vcs_instabox_clientSecret_title' => __('Instabox Client Secret', 'woo-vipps'),
               'vcs_instabox_clientSecret_label' => __('Instabox Client Secret', 'woo-vipps'),
               'vcs_instabox_clientSecret_description' => __('The Client Secret provided to you by Instabox','woo-vipps'),
   
               'vcs_helthjem_title' => __('Helthjem', 'woo-vipps'),
               'vcs_helthjem_label' => sprintf(__('Support Helthjem as a shipping method in %1$s', 'woo-vipps'), Vipps::CheckoutName()),
               'vcs_helthjem_description' => sprintf(__('Activate this for Helthjem as a %1$s Shipping method.' ,'woo-vipps'), Vipps::CheckoutName()),
   
               'vcs_helthjem_shopId_title' => __('Helthjem Shop Id', 'woo-vipps'),
               'vcs_helthjem_shopId_label' => __('Helthjem Shop Id', 'woo-vipps'),
               'vcs_helthjem_shopId_description' => __('The ShopId provided to you by Helthjem','woo-vipps'),
   
               'vcs_helthjem_username_title' => __('Helthjem Username', 'woo-vipps'),
               'vcs_helthjem_username_label' => __('Helthjem Username', 'woo-vipps'),
               'vcs_helthjem_username_description' => __('The Username provided to you by Helthjem','woo-vipps'),
   
               'vcs_helthjem_password_title' => __('Helthjem Password', 'woo-vipps'),
               'vcs_helthjem_password_label' => __('Helthjem Password', 'woo-vipps'),
               'vcs_helthjem_password_description' => __('Password provided to you by Helthjem','woo-vipps'),
           
               // Advanced options
               'advanced_options_title' => __('Advanced', 'woo-vipps'),
               'advanced_options_description' => __("If you have issues with your theme, you might find a setting here that will help. Normally you do not need to change these.", 'woo-vipps'),
              
               'vippsspecialpagetemplate_title' => sprintf(__('Override page template used for the special %1$s pages', 'woo-vipps'), Vipps::CompanyName()),
               'vippsspecialpagetemplate_label' => sprintf(__('Use specific template for %1$s', 'woo-vipps'), Vipps::CompanyName()),
               'vippsspecialpagetemplate_description' => sprintf(__('Use this template from your theme or child-theme to display all the special %1$s pages. You will probably want a full-width template and it should call \'the_content()\' normally.', 'woo-vipps'), Vipps::CompanyName()),
               'vippsspecialpagetemplate_options' => $page_templates,
   
               'vippsspecialpageid_title' =>  sprintf(__('Use a real page ID for the special %1$s pages - neccessary for some themes', 'woo-vipps'), Vipps::CompanyName()),
               'vippsspecialpageid_label' => __('Use a real page ID', 'woo-vipps'),
               'vippsspecialpageid_description' => sprintf(__('Some very few themes do not work with the simulated pages used by this plugin, and needs a real page ID for this. Choose a blank page for this; the content will be replaced, but the template and other metadata will be present. You only need to use this if the plugin seems to break on the special %1$s pages.', 'woo-vipps'), Vipps::CompanyName()),
               'vippsspecialpageid_options' => $page_list,
   
               'sendreceipts_title' => __("Send receipts and order confirmation info to the customers' app on completed purchases.", 'woo-vipps'),
               'sendreceipts_label' => sprintf(__("Send receipts to the customers %1\$s app", 'woo-vipps'), Vipps::CompanyName()),
               'sendreceipts_description' => sprintf(__("If this is checked, a receipt will be sent to %1\$s which will be viewable in the users' app, specifying the order items, shipping et cetera", 'woo-vipps'), Vipps::CompanyName()),
   
               'receiptimage_title' => sprintf(__('Use this image for the order confirmation link uploaded to the customers\' %1$s app', 'woo-vipps'), Vipps::CompanyName()),
               'receiptimage_label' => sprintf(__('Profile image used in the %1$s App', 'woo-vipps'), Vipps::CompanyName()),
               'receiptimage_description' => sprintf(__('If set, this image will be uploaded to %1$s and used to profile your store in the %1$s app for links to the order confirmation etc', 'woo-vipps'), Vipps::CompanyName()),
               'upload_image' => __('Upload image', 'woo-vipps'),
               'remove_image' => __('Remove image', 'woo-vipps'),
   
               'use_flock_title' => __('Use flock() to lock orders for Express Checkout', 'woo-vipps'),
               'use_flock_label' => __('Use flock() to lock orders for Express Checkout', 'woo-vipps'),
               'use_flock_description' => __('Use the flock() system call to ensure orders are only finalized once. You can use this for normal setups, but probably not on Windows with IIS, and possibly not on distributed filesystems like NFS. If you don\t know what it is, probably do not use it. If you get duplicated shipping lines on some express orders, you may try using this', 'woo-vipps'),
   
               'developermode_title' => __('Enable developer mode', 'woo-vipps'),
               'developermode_label' => __('Enable developer mode', 'woo-vipps'),
               'developermode_description' => __('Enable this to enter developer mode. This gives you access to the test-api and sometimes other tools not yet ready for general consumption', 'woo-vipps'),
           
               'developer_options_title' => __('Developer mode', 'woo-vipps'),
               'developer_options_description' => __('These are settings for developers that contain extra features that are normally not useful for regular users, or are not yet ready for primetime', 'woo-vipps'),
   
               'testmode_title' => __('Enable test mode', 'woo-vipps'),
               'testmode_label' => __('Enable test mode', 'woo-vipps'),
               'testmode_description' => sprintf(__('If you enable this, transactions will be made towards the %1$s Test API instead of the live one. No real transactions will be performed. You will need to fill out your test accounts keys below, and you will need to install a special test-mode app from Testflight on a device (which cannot run the regular %1$s app). Contact %1$s\'s technical support if you need this. If you turn this mode off, normal operation will resume. If you have the VIPPS_TEST_MODE defined in your wp-config file, this will override this value. ', 'woo-vipps'), Vipps::CompanyName()),
               
               'merchantSerialNumber_test_title' => __('Merchant Serial Number', 'woo-vipps'),
               'merchantSerialNumber_test_label' => __('Merchant Serial Number', 'woo-vipps'),
               'merchantSerialNumber_test_description' => __('Your test account "Merchant Serial Number" from the Developer tab on https://portal.vipps.no','woo-vipps'),
   
               'clientId_test_title' => __('Client Id', 'woo-vipps'),
               'clientId_test_label' => __('Client Id', 'woo-vipps'),
               'clientId_test_description' => __('Find your test account under the "Developer" tab on https://portal.vipps.no/ and choose "Show keys". Copy the value of "client_id"','woo-vipps'),
   
               'secret_test_title' => __('Client Secret', 'woo-vipps'),
               'secret_test_label' => __('Client Secret', 'woo-vipps'),
               'secret_test_description' => __('Find your test account under the "Developer" tab on https://portal.vipps.no/ and choose "show keys". Copy the value of "client_secret"','woo-vipps'),
   
               'Ocp_Apim_Key_eCommerce_test_title' => __('Subscription Key', 'woo-vipps'),
               'Ocp_Apim_Key_eCommerce_test_label' => __('Subscription Key', 'woo-vipps'),
               'Ocp_Apim_Key_eCommerce_test_description' => __('Find your test account under the "Developer" tab on https://portal.vipps.no/ and choose "show keys". Copy the value of "Vipps-Subscription-Key"','woo-vipps'),
       );


       // Attempt to read the set payment method name, otherwise try to detect a default one from the settings
       $payment_method_name = $gw->get_payment_method_name() ? $gw->get_payment_method_name() : $gw->detect_default_payment_method_name();
       $options = array(
               'configured' => $gw->get_option('configured', 'no'),
               // Main options tab data
               'enabled' => $gw->get_option('enabled', 'no') ,
               'payment_method_name' => $payment_method_name,
               'orderprefix' => $gw->get_option('orderprefix', $Vipps->generate_order_prefix()),
               'merchantSerialNumber' => $gw->get_option('merchantSerialNumber'),
               'clientId' => $gw->get_option('clientId'),
               'secret' => $gw->get_option('secret'),
               'Ocp_Apim_Key_eCommerce' => $gw->get_option('Ocp_Apim_Key_eCommerce'),
               'result_status' => $gw->get_option('result_status', 'on-hold'), 
               'title' => $gw->get_option('title', sprintf(__('%1$s','woo-vipps'), $payment_method_name)),
               'description' => $gw->get_option('description', sprintf(__("Almost done! Remember, there are no fees using %1\$s when shopping online.", 'woo-vipps'), Vipps::CompanyName())),
               'vippsdefault' => $gw->get_option('vippsdefault', 'yes'),
   
               // Express checkout tab data
               'cartexpress' => $gw->get_option('cartexpress', 'yes'),
               'singleproductexpress' => $gw->get_option('singleproductexpress', 'none'),
               'singleproductexpressarchives' => $gw->get_option('singleproductexpressarchives', 'no'),
               'expresscheckout_termscheckbox' => $gw->get_option('expresscheckout_termscheckbox', 'no'),
               'expresscheckout_always_address' => $gw->get_option('expresscheckout_always_address', $default_ask_address_for_express),
               'enablestaticshipping' => $gw->get_option('enablestaticshipping', 'no'),
               'expresscreateuser' => $gw->get_option('expresscreateuser', $expresscreateuserdefault),
               'singleproductbuynowcompatmode' => $gw->get_option('singleproductbuynowcompatmode', 'no'),
               'deletefailedexpressorders' => $gw->get_option('deletefailedexpressorders', 'no'),
   
               // Checkout tab data
               'vipps_checkout_enabled' => $gw->get_option('vipps_checkout_enabled', 'no'),
               'checkoutcreateuser' => $gw->get_option('checkoutcreateuser', $vippscreateuserdefault),
               'enablestaticshipping_checkout' => $gw->get_option('enablestaticshipping_checkout', 'no'),
               'requireUserInfo_checkout' => $gw->get_option('requireUserInfo_checkout', 'no'),
               'noAddressFields' => $gw->get_option('noAddressFields', 'no'),
               'noContactFields' => $gw->get_option('noContactFields', 'no'),
   
               // Checkout tab - shipping data
               'checkout_shipping' => $gw->get_option('checkout_shipping'),
               'vcs_posten' => $gw->get_option('vcs_posten', 'yes'),
               'vcs_postnord' => $gw->get_option('vcs_postnord', 'yes'),
               'vcs_porterbuddy' => $gw->get_option('vcs_porterbuddy', 'no'),
               'vcs_porterbuddy_publicToken' => $gw->get_option('vcs_porterbuddy_publicToken'),
               'vcs_porterbuddy_apiKey' => $gw->get_option('vcs_porterbuddy_apiKey'),
               'vcs_porterbuddy_phoneNumber' => $gw->get_option('vcs_porterbuddy_phoneNumber'),
               'vcs_instabox' => $gw->get_option('vcs_instabox', 'no'),
               'vcs_instabox_clientId' => $gw->get_option('vcs_instabox_clientId'),
               'vcs_instabox_clientSecret' => $gw->get_option('vcs_instabox_clientSecret'),
               'vcs_helthjem' => $gw->get_option('vcs_helthjem', 'no'),
               'vcs_helthjem_shopId' => $gw->get_option('vcs_helthjem_shopId'),
               'vcs_helthjem_username' => $gw->get_option('vcs_helthjem_username'),
               'vcs_helthjem_password' => $gw->get_option('vcs_helthjem_password'),
   
               // Advanced tab
               'vippsspecialpagetemplate' => $gw->get_option('vippsspecialpagetemplate'),
               'vippsspecialpageid' => $gw->get_option('vippsspecialpageid'),
               'sendreceipts' => $gw->get_option('sendreceipts', 'yes'),
               'receiptimage' => $gw->get_option('receiptimage'),
               'receiptimage_url' => wp_get_attachment_url($gw->get_option('receiptimage')),
               'use_flock' => $gw->get_option('use_flock', 'no'),
               'developermode' => $gw->get_option('developermode', VIPPS_TEST_MODE ? 'yes' : 'no'),
   
               // Developer tab
               'testmode' => $gw->get_option('testmode', VIPPS_TEST_MODE ? 'yes' : 'no'),
               'merchantSerialNumber_test' => $gw->get_option('merchantSerialNumber_test'),
               'clientId_test' => $gw->get_option('clientId_test'),
               'secret_test' => $gw->get_option('secret_test'),
               'Ocp_Apim_Key_eCommerce_test' => $gw->get_option('Ocp_Apim_Key_eCommerce_test'),
       );

       $metadata = array(
           'admin_url' => admin_url('admin-ajax.php'),
           'page' => 'admin_settings_page'
       );
       wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactTranslations', $translations);
       wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactOptions', $options);
       wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactMetadata', $metadata);

       echo "</div>";
   }
}
?>

