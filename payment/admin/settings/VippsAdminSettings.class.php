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
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class VippsAdminSettings
{
    private static $instance = null;

    // This returns the singleton instance of this class
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Get the singleton WC_GatewayVipps instance
    public function gateway()
    {
        global $Vipps;
        if (class_exists('WC_Payment_Gateway')) {
            // Try to load payment gateways first, we need that infrastructure. IOK 2024-06-05
            $gateways = WC()->payment_gateways();
            return WC_Gateway_Vipps::instance();
        } else {
            $Vipps->log(__("Error: Cannot instantiate payment gateway, because WooCommerce is not loaded! This can happen when WooCommerce updates itself; but if it didn't, please activate WooCommerce again", 'woo-vipps'), 'error');
            return null;
        }
    }

    // Handle the submission of the admin settings page
    public function ajax_vipps_update_admin_settings()
    {
        $ok = wp_verify_nonce($_REQUEST['vippsadmin_nonce'], 'vippsadmin_nonce');
        if (!$ok) {
            echo json_encode(array('ok' => 0, 'options' => [], 'msg' => __('You don\'t have sufficient rights to edit these settings', 'woo-vipps')));
            exit();
        }
        if (!current_user_can('manage_woocommerce')) {
            echo json_encode(array('ok' => 0, 'options' => [], 'msg' => __('You don\'t have sufficient rights to edit these settings', 'woo-vipps')));
            exit();
        }

        // Decode the settings from the values sents, then save them to "woocommerce_vipps_settings"
        $new_settings = $_POST['values'];

        // IOK FIXME This will ensure sanitization etc works as it is supposed to using the 
        // admin settings api of WooCommerce. We will however want to run this code independently, so we'll handle this 
        // by ourselves at a later point, ending it like so:
        // update_option('woocommerce_vipps_settings', $new_settings); // After sanitation etc
        $admin_options = [];
        foreach ($new_settings as $key => $value) {
            // Checkbox settings (yes/no) can't be passed directly to set_post_data, because *any* value will be interpreted as "yes"
            // because of this, checkbox settings with the value "no" will be completely ignored
            if($value !== 'no') {
                // Because settings are processed manually, their keys need to prefixed with "woocommerce_vipps_"
                $admin_options['woocommerce_vipps_' . $key] = $value;
            }
        }
        // We need to initialize these again so "conditional" options are available
        // IOK 2024-06-05
        $this->gateway()->init_form_fields(); 
        $this->gateway()->set_post_data($admin_options);
        $this->gateway()->process_admin_options();
//        $this->gateway()->add_error("Jaboloko!");  // Also add_warning, add_notice plz
        $form_errors = $this->gateway()->get_errors();
        $form_ok = empty($form_errors);
        // end use of process_admin_options IOK 2024-01-03

        $connection_msg = "";
        // Verify the connection to Vipps
        list($connection_ok, $error_message) = $this->gateway()->check_connection();
        if ($connection_ok) {
            $connection_msg .= sprintf(__("Connection to %1\$s is OK", 'woo-vipps'), Vipps::CompanyName());
        } else {
            $connection_msg .= sprintf(__("Could not connect to %1\$s", 'woo-vipps'), Vipps::CompanyName()) . ": $error_message";
        }

        // Get the current options
        $options = get_option('woocommerce_vipps_settings');

        // Add receipt image URL if receipt image exists
        if (!empty($new_settings['receiptimage'])) {
            $options['receiptimage_url'] = wp_get_attachment_url($new_settings['receiptimage']);
        }

        // Return the result, sending the new options, the connection status/message and the form status/errors
        echo json_encode(
            array(
                'connection_ok' => $connection_ok, // Whether the connection to the Vipps servers is OK.
                'connection_msg' => $connection_msg, // The connection message, whether the connection is OK or not.
                'form_ok' => $form_ok, // Whether the form fields are OK.
                'form_errors' => $form_errors, // The form field errors, if any.
                'options' => $options // The new options
            )
        );
        exit();
    }

    // Initializes the admin settings UI for VippsMobilePay
    function init_admin_settings_page_react_ui()
    {
        global $Vipps;
        echo "<div class='wrap vipps-admin-settings-page'>";
        // Add nonce first.
        wp_nonce_field('vippsadmin_nonce', 'vippsadmin_nonce');

        // We must first generate the root element for the React UI before we load the React app itself, otherwise React will fail to load.
        ?>
        <div id="vipps-mobilepay-react-ui"></div>
        <?php

        // Initializing the wordpress media plugin, so we can upload images
        wp_enqueue_media();
        $gw = $this->gateway();

        // Loads the React UI
        $reactpath = "dist";
        wp_enqueue_script('vipps-mobilepay-react-ui', plugins_url($reactpath . '/plugin.js', __FILE__), array('wp-element'), filemtime(__DIR__ . "/$reactpath/plugin.js"), true);
//        wp_enqueue_style('vipps-mobilepay-react-ui', plugins_url($reactpath . '/plugin.css', __FILE__), array(), filemtime(__DIR__ . "/$reactpath/plugin.css"));

        $metadata = array(
            'admin_url' => admin_url('admin-ajax.php'),
            'page' => 'admin_settings_page',
            'currency' => get_woocommerce_currency(),
        );
        // Add some extra common translations only used by the React UI
        $commonTranslations = array(
            'save_changes' => __('Save changes', 'woo-vipps'),
            'initial_settings' => __('Initial settings', 'woo-vipps'),
            'upload_image' => __('Upload image', 'woo-vipps'),
            'remove_image' => __('Remove image', 'woo-vipps'),
            'next_step' => __('Next step', 'woo-vipps'),
            'previous_step' => __('Previous step', 'woo-vipps'),
            'receipt_image_size_requirement' => __('The image must be at least 167 pixels in height', 'woo-vipps'),
            'receipt_image_error' => __('The uploaded image is too small. It must be at least 167 pixels in height.', 'woo-vipps'),
            'settings_saved' => __('Settings saved', 'woo-vipps')
        );

        /* We need to postprocess the settings for.. various reasons IOK 2024-06-04  */
        /* Also we need to run init_form_fields here, because for whatever reason the
         * first time it is called, it does wrong things in this context. IOK 2024-06-04 */
        $gw->init_form_fields();
        $settings = $gw->settings;
        if (!empty($settings['receiptimage'])) {
            $settings['receiptimage_url'] = wp_get_attachment_url($settings['receiptimage']);
        }
        if (!$gw->allow_external_payments_in_checkout()) {
           unset($settings['checkout_external_payments_klarna']);
        } else {
            // nop right now
        }

        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactTranslations', array_merge($gw->form_fields, $commonTranslations));
        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactOptions', $settings);
        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactMetadata', $metadata);

        echo "</div>";
    }
}
?>
