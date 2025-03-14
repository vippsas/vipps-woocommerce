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
            // for debugging/testing: show wizard screen always IOK 2025-01-20
            '__dev_force_wizard_screen' => defined('WOO_VIPPS_FORCE_WIZARD') && WOO_VIPPS_FORCE_WIZARD
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

        $wizardTranslations = [
            'wizard_header' => [
                'title' => __('Initial settings', 'woo-vipps'),
                'description' => sprintf(__('Welcome! You are almost ready to accept payments with %1$s', 'woo-vipps'), Vipps::CompanyName()),
            ],
            'checkout_options_wizard' => array(
                'title' => sprintf(__('Get started with %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                'description' => sprintf(__('%1$s is a service from %2$s, which allows you to replace the usual WooCommerce checkout page with a super simple checkout screen, where your customers can pay with Vipps, Visa, and MasterCard!', 'woo-vipps'), Vipps::CheckoutName(), Vipps::CompanyName()),
            ),
            'vipps_checkout_enabled_wizard' => array(
                'title' => Vipps::CheckoutName(),
                'label' => sprintf(__('Yes, I want to start using %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                'description' => sprintf(__('If activated, this will <strong>replace</strong> the standard Woo checkout screen with %1$s, providing easy checkout using %1$s or credit card, with no need to type in addresses.', 'woo-vipps'), Vipps::CheckoutName()),
                'default' => 'no',
            ),
            'enablestaticshipping_checkout_wizard' => array(
                'title' => __('Are you going to base shipping price on the customers address?', 'woo-vipps'),
                'label' => __('Yes, I want dynamic shipping calculation', 'woo-vipps'),
                'description' => __('If your shipping prices are the same no matter where the customer lives, you don\'t need dynamic shipping calculation.'),
            ),
            'checkoutcreateuser_wizard' => array(
                    'title' => sprintf(__('Do you want to create new customers on %1$s?', 'woo-vipps'), Vipps::CheckoutName()),
                    'label' => sprintf(__('Yes, create new customers on %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                    'description' => sprintf(__('By creating new customers, you avoid orders showing up as guest orders. We recommend combining this with Log in with %1$s for a full overview of your customers.', 'woo-vipps'), Vipps::CompanyName()),
                ),
            'noAddressFields_wizard' => array(
                    'title' => __('Do you want the customer to enter their address?', 'woo-vipps'),
                    'label' => __('Yes, all customers must enter their address', 'woo-vipps'),
                    'description' => __('If you only sell products that are not to be shipped, you don\'t need the customer to enter their address.', 'woo-vipps'),
                ),
            'checkout_shipping_wizard' => array(
                    'title' => sprintf(__('Shipping alternatives available with %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                    'description' => sprintf(__('When you use %1$s, you have a variety of shipping options to choose from, giving you even more choices from certain shipping providers.'), Vipps::CheckoutName())
                ),
            'vcs_posten_wizard' => array(
                'title' => __('Posten Norge', 'woo-vipps'),
                'custom_attributes' => array('data-vcs-show' => '.vcs_depend.vcs_posten'),
                'label' => __('Offer Posten Norge as a shipping method', 'woo-vipps'),
                'description' => __('Select this to offer this shipping method.', 'woo-vipps'),
            ),
            'vcs_helthjem_wizard' => array(
                'title' => __('Helthjem', 'woo-vipps'),
                'label' => __('Offer Helthjem as a shipping method', 'woo-vipps'),
                'description' => __('Select this to offer this shipping method.', 'woo-vipps'),
            ),
            'vcs_posti_wizard' => array(
                    'title' => __('Posti', 'woo-vipps'),
                    'label' => __('Offer Posti as a shipping method', 'woo-vipps'),
                    'description' => __('Select this to offer this shipping method.', 'woo-vipps'),
                ),
            'vcs_postnord_wizard' => array(
                    'title' => __('PostNord', 'woo-vipps'),
                    'label' => __('Offer PostNord as a shipping method', 'woo-vipps'),
                    'description' => __('Select this to offer this shipping method.', 'woo-vipps'),
                ),
            'vcs_porterbuddy_wizard' => array(
                    'title' => __('Porterbuddy', 'woo-vipps'),
                    'label' => __('Offer Porterbuddy as a shipping method', 'woo-vipps'),
                    'description' => __('Select this to offer this shipping method.', 'woo-vipps'),
                ),
            'help_box' => [
                    'get_started' => __('Get started', 'woo_vipps'),
                    'documentation' => __('Documentation', 'woo-vipps'),
                    'portal' => sprintf(__('%1$s Portal', 'woo-vipps'), Vipps::CompanyName()),
                    'support' => [
                        'title' => __('Support', 'woo-vipps'),
                        'description' => __('If you have any questions related to this plugin, you are welcome to check out the <a href="https://wordpress.org/support/plugin/woo-vipps/" target="_blank">support forum.</a>', 'woo-vipps'),
                    ],
                ],
            'checkout_confirm' => [
                'title' => sprintf(__('Upgrade your customer experience with %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                'img' => [
                    'vipps' => [
                        'src' => plugins_url('img/no_vippsmobilepay_checkout_app-teaser_2400x1600.webp', WC_VIPPS_PAYMENT_MAIN_FILE),
                        'alt' => __('Sketch of', 'woo-vipps') . ' Vipps Checkout', // dont translate checkout name
                    ],
                    'mobilepay' => [
                        'src' => plugins_url('img/dk_vm_checkout_app-teaser_2400x1600.webp', WC_VIPPS_PAYMENT_MAIN_FILE),
                        'alt' => __('Sketch of', 'woo-vipps') . ' MobilePay Checkout',
                    ],
                ],
                'paragraph1' => [
                    'header' => sprintf(__('Why %1$s?', 'woo-vipps'), Vipps::CheckoutName()),
                    'first' => __('Effortless Payments: Accept payments smoothly with Vipps/MobilePay, Visa, and Mastercard.', 'woo-vipps'),
                    'second' => __('Streamlined Shopping Process: Simplify your customers\' journey from cart to confirmation', 'woo-vipps'),
                    'third' => __('All-in-One Solution: No fixed monthly fees-just pure convenience.', 'woo-vipps'),
                ],
                'paragraph2' => [
                    'header' => __('Perfect for you if:', 'woo-vipps'),
                    'first' => __('You want simple, varied payment options', 'woo-vipps'),
                    'second' => __('You need easy shipping solutions with diverse choices.', 'woo-vipps'),
                ],
                'accept' => sprintf(__('Start using %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                'skip' => __('Skip & save', 'woo-vipps'),
            ],

        ];
        
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

        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactTranslations', array_merge($gw->form_fields, $commonTranslations, $wizardTranslations));
        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactOptions', $settings);
        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactMetadata', $metadata);

        echo "</div>";
    }
}
?>
