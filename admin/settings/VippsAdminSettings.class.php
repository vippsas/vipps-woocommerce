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

       // Loads the React UI
       $reactpath = "dist";
       wp_enqueue_script('vipps-mobilepay-react-ui', plugins_url($reactpath . '/plugin.js',__FILE__), array('wp-i18n'), filemtime(__DIR__ . "/$reactpath/plugin.js"), true ); 
       wp_enqueue_style('vipps-mobilepay-react-ui', plugins_url($reactpath . '/plugin.css',__FILE__), array(), filemtime(__DIR__ . "/$reactpath/plugin.css"));

       $metadata = array(
           'admin_url' => admin_url('admin-ajax.php'),
           'page' => 'admin_settings_page',
        );
       // add some extra common translations only used by the React UI
       $commonTranslations = array(
            'save_changes' => __('Save changes', 'woo-vipps'),
            'initial_settings' => __('Initial settings', 'woo-vipps'),
            'upload_image' => __('Upload image', 'woo-vipps'),
            'remove_image' => __('Remove image', 'woo-vipps'),
            'next_step' => __('Next step', 'woo-vipps'),
            'previous_step' => __('Previous step', 'woo-vipps'),
       );
       wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactTranslations', array_merge($gw->form_fields, $commonTranslations));
       wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactOptions', $gw->settings);
       wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactMetadata', $metadata);

       echo "</div>";
   }
}
?>

