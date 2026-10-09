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

        // Decode the settings from the values sents, then save them to "woocommerce_vipps_settings" and "woocommerce_vipps_card_settings"
        $new_settings = $_POST['values'];


        // IOK TODO This will ensure sanitization etc works as it is supposed to using the 
        // admin settings api of WooCommerce. We will however want to run this code independently, so we'll handle this 
        // by ourselves at a later point, ending it like so:
        // update_option('woocommerce_vipps_settings', $new_settings); // After sanitation etc
        // update_option('woocommerce_vipps_card_settings', $card_settings); // Separated out

        $admin_options = [];
        $card_admin_options= [];
        foreach ($new_settings as $key => $value) {
            // Checkbox settings (yes/no) can't be passed directly to set_post_data, because *any* value will be interpreted as "yes"
            // because of this, checkbox settings with the value "no" will be completely ignored
            if($value !== 'no') {
                // Because settings are processed manually, their keys need to prefixed with "woocommerce_<gatewayid>_" 
                // options with prefix cc_ are for the credit card gateway.
                if (preg_match("!^cc_!", $key)) {
                    $key = preg_replace("!^cc_!", "", $key);
                    $card_admin_options['woocommerce_vipps_card_' . $key] = $value;
                } else {
                    $admin_options['woocommerce_vipps_' . $key] = $value;
                }
            }
        }

        // Save options for the main gateway first
        // We need to initialize these again so "conditional" options are available
        // IOK 2024-06-05
        $this->gateway()->init_form_fields(); 
        $this->gateway()->set_post_data($admin_options);
        $this->gateway()->process_admin_options();
//        $this->gateway()->add_error("Jaboloko!");  // Also add_warning, add_notice plz
        $form_errors = $this->gateway()->get_errors();

        // Then the card gateway
        $cards= WC_Gateway_VippsCard::instance();
        $cards->init_form_fields();
        $cards->set_post_data($card_admin_options);
        $cards->process_admin_options();
        $form_errors = array_merge($form_errors, $cards->get_errors());


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
        // Augment with card gateway, with prefix
        $card_options = get_option('woocommerce_vipps_card_settings');
        foreach($card_options as $key => $data) {
          $options["cc_$key"] = $data;
        }

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

    public function post_update_button_settings () {
        $ok = wp_verify_nonce($_REQUEST['buttonnonce'] ?? '', 'buttonaction');
        if (!$ok) {
           wp_send_json_error(['msg' => __("Wrong nonce", 'woo-vipps')], 403);
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => __('You don\'t have sufficient rights', 'woo-vipps')], 403);
        }

        $old = get_option('vipps_button_options2', []);
        $new = $old;
        if (isset($_POST['express']['configs'])) {
            foreach ($_POST['express']['configs'] as $ctx => $config) {
                $sanitized_ctx = sanitize_title($ctx);
                $sanitized_config = map_deep($config, 'sanitize_title');

                // If nonglobal context that uses global config, just wipe the rest of the stored config. LP 2026-06-25
                if ("global" !== $sanitized_ctx && ($sanitized_config['use-global-config'] ?? false)) {
                    $new['express']['configs'][$sanitized_ctx] = ['use-global-config' => true];
                } else {
                    $new['express']['configs'][$sanitized_ctx] = $sanitized_config;
                }
            }
        }
        update_option('vipps_button_options2', $new);
        $saved = get_option('vipps_button_options2', []);
        if ($saved != $new) {
            wp_send_json_error(['msg' => __('Could not save settings', 'woo-vipps')], 500);
        }
        wp_send_json_success(['msg' => __('Settings saved', 'woo-vipps'), 'configs' => $saved['express']['configs'] ?? (object) []]);
    }

    public function post_update_badge_settings () {
        Vipps::set_locale_if_in_header();
        $ok = wp_verify_nonce($_REQUEST['badgenonce'] ?? '', 'badgeaction');
        if (!$ok) {
           wp_send_json_error(['msg' => __("Wrong nonce", 'woo-vipps')], 403);
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => __('You don\'t have sufficient rights', 'woo-vipps')], 403);
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
        $saved = get_option('vipps_badge_options', []);
        if ($saved != $current) {
            wp_send_json_error(['msg' => __('Could not save settings', 'woo-vipps')], 500);
        }
        wp_send_json_success(['msg' => __('Settings saved', 'woo-vipps'), 'options' => $saved]);
    }

    // To be called in admin-post.php
    public function post_vipps_delete_webhook() {
        Vipps::set_locale_if_in_header();
        $ok = wp_verify_nonce($_REQUEST['webhook_nonce'] ?? '', 'webhook_nonce');
        if (!$ok) {
           wp_send_json_error(['msg' => __("Wrong nonce", 'woo-vipps')], 403);
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => __('You don\'t have sufficient rights', 'woo-vipps')], 403);
        }

        $msn = sanitize_title($_REQUEST['webhook_msn'] ?? '');
        $id = sanitize_title($_REQUEST['webhook_id'] ?? '');

        if (!$msn || !$id) {
            wp_send_json_error(['msg' => __('Missing merchant serial number or webhook ID', 'woo-vipps')], 400);
        }
        try {
            $result = $this->gateway()->api->delete_webhook($msn, $id);
        } catch (Throwable $e) {
            wp_send_json_error(['msg' => $e->getMessage()], 502);
        }
        // Successful DELETE responses may have no body (null); only false means failure.
        if ($result === false) {
            wp_send_json_error(['msg' => __('Could not delete webhook', 'woo-vipps')], 502);
        }
        wp_send_json_success(['msg' => __('Webhook deleted', 'woo-vipps'), 'msn' => $msn, 'id' => $id]);
    }

    // To be called in admin-post.php
    public function post_vipps_add_webhook() {
        Vipps::set_locale_if_in_header();
        $ok = wp_verify_nonce($_REQUEST['webhook_nonce'] ?? '', 'webhook_nonce');
        if (!$ok) {
           wp_send_json_error(['msg' => __("Wrong nonce", 'woo-vipps')], 403);
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => __('You don\'t have sufficient rights', 'woo-vipps')], 403);
        }

        $msn = sanitize_title($_REQUEST['webhook_msn'] ?? '');
        $url = sanitize_url($_REQUEST['webhook_url'] ?? '');
        $events = array_values(array_filter(explode(',', $_REQUEST['webhook_events'] ?? ''), 'strlen'));
        if (empty($events) || !$msn || !$url) {
            wp_send_json_error(['msg' => __('Missing merchant serial number, URL or events', 'woo-vipps')], 400);
        }
        try {
            $result = $this->gateway()->api->register_webhook($msn, $url, $events);
        } catch (Throwable $e) {
            wp_send_json_error(['msg' => $e->getMessage()], 502);
        }
        if (!is_array($result) || empty($result['id'])) {
            wp_send_json_error(['msg' => __('Could not register webhook', 'woo-vipps')], 502);
        }
        $localhooks = get_option('_woo_vipps_webhooks', []);
        wp_send_json_success([
            'msg' => __('Webhook added', 'woo-vipps'), 'msn' => $msn,
            'hook' => [
                'id' => $result['id'], 'url' => $result['url'] ?? $url,
                'events' => $result['events'] ?? $events,
                'local' => !empty($localhooks[$msn][$result['id']]),
            ],
        ]);
    }


    public function webhook_menu_page () {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__("You don't have sufficient rights to access this page", 'woo-vipps'));
        }
        $keyset = $this->gateway()->get_keyset();
        $allhooks = $this->gateway()->initialize_webhooks();
        $localhooks = get_option('_woo_vipps_webhooks', []);
        $merchants = [];
        foreach ($keyset as $msn => $data) {
            $hooks = [];
            foreach (($allhooks[$msn]['webhooks'] ?? []) as $hook) {
                $hooks[] = [
                    'id' => $hook['id'], 'url' => $hook['url'],
                    'events' => $hook['events'],
                    'local' => !empty($localhooks[$msn][$hook['id']]),
                ];
            }
            $merchants[] = ['msn' => (string) $msn, 'testmode' => !empty($data['testmode']), 'hooks' => $hooks];
        }
        $events = [
            'Epayment' => [
                __('Created', 'woo-vipps') => 'epayments.payment.created.v1',
                __('Aborted', 'woo-vipps') => 'epayments.payment.aborted.v1',
                __('Expired', 'woo-vipps') => 'epayments.payment.expired.v1',
                __('Cancelled', 'woo-vipps') => 'epayments.payment.cancelled.v1',
                __('Captured', 'woo-vipps') => 'epayments.payment.captured.v1',
                __('Refunded', 'woo-vipps') => 'epayments.payment.refunded.v1',
                __('Authorized', 'woo-vipps') => 'epayments.payment.authorized.v1',
                __('Terminated', 'woo-vipps') => 'epayments.payment.terminated.v1',
            ],
            'Recurring' => [
                __('Agreement accepted', 'woo-vipps') => 'recurring.agreement-activated.v1',
                __('Agreement rejected', 'woo-vipps') => 'recurring.agreement-rejected.v1',
                __('Agreement stopped', 'woo-vipps') => 'recurring.agreement-stopped.v1',
                __('Agreement expired', 'woo-vipps') => 'recurring.agreement-expired.v1',
                __('Charge reserved', 'woo-vipps') => 'recurring.charge-reserved.v1',
                __('Charge captured', 'woo-vipps') => 'recurring.charge-captured.v1',
                __('Charge cancelled', 'woo-vipps') => 'recurring.charge-canceled.v1',
                __('Charge failed', 'woo-vipps') => 'recurring.charge-failed.v1',
            ],
            'QR' => [__('User Checked in', 'woo-vipps') => 'user.checked-in.v1'],
        ];
        $this->mount_settings_subpage('webhooks', [
            'merchants' => $merchants,
            'events' => $events,
            'defaultEvents' => ['epayments.payment.authorized.v1', 'epayments.payment.aborted.v1', 'epayments.payment.expired.v1', 'epayments.payment.terminated.v1'],
        ], [
            'title' => __('Webhooks', 'woo-vipps'),
            'description' => sprintf(__('Whenever an event like a payment or a cancellation occurs on a %1$s account, you can be notified of this using a <i>webhook</i>. This is used by this plugin to get noticed of payments by users even when they do not return to your store.', 'woo-vipps'), Vipps::CompanyName()),
            'automatic' => __('To do this, the plugin will automatically add webhooks for the MSN - Merchant Serial Numbers - configured on this site', 'woo-vipps'),
            'other' => __('If your MSN has registered other callbacks, for instance for another website, you can manage these here - and you can also add your own hooks that will be notified of payment events to any other URL you enter.', 'woo-vipps'),
            'developer' => sprintf(__('Implementing a webhook is not trivial, so you will probably need a developer for this.  You can read more about what is required <a href="%1$s">here</a>. ', 'woo-vipps'), 'https://developer.vippsmobilepay.com/docs/APIs/webhooks-api/'),
            'limit' => sprintf(__('Please note that there is normally a limit of <em><strong>5</strong> webhooks per MSN</em> - contact %1$s if you need more', 'woo-vipps'), Vipps::CompanyName()),
            'listing' => __('The following is a listing of your webhooks. If you have changed your website name, you may see some hooks that you do not recognize - these should be deleted', 'woo-vipps'),
            'merchant' => __('Merchant Serial Number %1$s', 'woo-vipps'),
            'testMode' => __('Test mode', 'woo-vipps'), 'addForMsn' => __('Add a webhook to this MSN', 'woo-vipps'),
            'webhook' => __('Webhook', 'woo-vipps'), 'action' => __('Action', 'woo-vipps'),
            'view' => __('View', 'woo-vipps'), 'delete' => __('Delete', 'woo-vipps'),
            'createdHere' => __('Created for this site', 'woo-vipps'), 'add' => __('Add a webhook', 'woo-vipps'),
            'addUrl' => __('Add this URL as a webhook', 'woo-vipps'), 'cancel' => __('No, forget it', 'woo-vipps'),
            'ok' => __('OK', 'woo-vipps'), 'delete_confirm' => __('Delete webhook?', 'woo-vipps'),
        ], 'webhook_nonce', 'webhook_nonce');
    }

    public function badge_menu_page () {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__("You don't have sufficient rights to access this page", 'woo-vipps'));
        }
        $vipps = Vipps::instance();
        wp_enqueue_script('vipps-onsite-messageing');
        $language = $vipps->get_customer_language();
        if ($language === 'se') $language = 'sv';
        if ($language === 'dk') $language = 'da';
        $this->mount_settings_subpage('badges', [
            'options' => get_option('vipps_badge_options', []),
            'brand' => strtolower($vipps->get_payment_method_name()),
            'language' => $language,
            'variants' => ['white' => __('White', 'woo-vipps'), 'grey' => __('Grey', 'woo-vipps'), 'filled' => __('Filled', 'woo-vipps'), 'light' => __('Light', 'woo-vipps'), 'purple' => __('Purple', 'woo-vipps')],
        ], [
            'title' => __('Badges', 'woo-vipps'),
            'intro' => sprintf(__('%1$s On-Site Messaging contains <em>badges</em> in different variants that can be used to let your customers know that %1$s payment is accepted.', 'woo-vipps'), Vipps::CompanyName()),
            'description' => __('You can configure these badges on this page, turning them on in all or some products and configure their default setup. You can also add a badge using a shortcode or a Block', 'woo-vipps'),
            'settings' => __('Settings', 'woo-vipps'), 'enabled' => sprintf(__('Turn on support for %1$s On-site Messaging badges', 'woo-vipps'), Vipps::CompanyName()),
            'defaultAll' => __('Add badge to all products by default', 'woo-vipps'),
            'defaultAllHelp' => sprintf(__("If selected, all products will get a badge, but you can override this on the %1\$s tab on the product data page. If not, it's the other way around. You can also choose a particular variant on that page", 'woo-vipps'), Vipps::CompanyName()),
            'variant' => __('Variant', 'woo-vipps'), 'chooseVariant' => __('Choose color variant:', 'woo-vipps'),
            'block' => __('The Gutenberg Block', 'woo-vipps'),
            'blockHelp' => sprintf(__('If you use Gutenberg, you should be able to add a %1$s Badge block wherever you need it. It is called %1$s On-Site Messaging Badge Block.', 'woo-vipps'), Vipps::CompanyName()),
            'shortcodes' => __('Shortcodes', 'woo-vipps'),
            'shortcodeHelp' => sprintf(__('If you need to add a %1$s badge on a specific page, footer, header and so on, and you cannot use the Gutenberg Block provided for this, you can either add the %1$s Badge manually (as <a href="%2$s" nofollow rel=nofollow target=_blank>documented here</a>) or you can use the shortcode.', 'woo-vipps'), Vipps::CompanyName(), 'https://developer.vippsmobilepay.com/docs/knowledge-base/design-guidelines/on-site-messaging/'),
            'shortcodeIntro' => __('The shortcode looks like this:', 'woo-vipps'),
            'shortcodeDocs' => __('Please refer to the documentation for the meaning of the parameters.', 'woo-vipps'),
            'brandAutomatic' => __('The brand will be automatically applied.', 'woo-vipps'),
        ], 'badgeaction', 'badgenonce');
    }

    public function button_menu_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__("You don't have sufficient rights to access this page", 'woo-vipps'));
        }
        $vipps = Vipps::instance();
        wp_enqueue_script('vipps-button-webcomponent');
        $this->mount_settings_subpage('buttons', [
            'configs' => get_option('vipps_button_options2', [])['express']['configs'] ?? [],
            'defaults' => $vipps->get_html_button_default_attrs(),
            'brand' => strtolower($vipps->get_payment_method_name()),
            'language' => substr(get_locale(), 0, 2),
            'context' => sanitize_title($_GET['express-context'] ?? 'global'),
            'isMobilePay' => $vipps->get_payment_method_name() === 'MobilePay',
        ], [
            'title' => __('Buttons', 'woo-vipps'),
            'express' => __('Express', 'woo-vipps'), 'context' => __('Config context', 'woo-vipps'),
            'global' => __('Global', 'woo-vipps'), 'product' => __('Product', 'woo-vipps'),
            'catalog' => __('Catalog', 'woo-vipps'), 'cart' => __('Cart', 'woo-vipps'),
            'minicart' => __('Mini cart', 'woo-vipps'), 'checkout' => __('Checkout', 'woo-vipps'),
            'useGlobal' => __('Use global config', 'woo-vipps'), 'rounded' => __('Rounded', 'woo-vipps'),
            'compact' => __('Compact', 'woo-vipps'), 'stretched' => __('Stretched', 'woo-vipps'),
            'languageLabel' => __('Language', 'woo-vipps'), 'store' => __('Store language', 'woo-vipps'),
            'en' => __('English', 'woo-vipps'), 'no' => __('Norwegian', 'woo-vipps'),
            'dk' => __('Danish', 'woo-vipps'), 'sv' => __('Swedish', 'woo-vipps'),
            'fi' => __('Finnish', 'woo-vipps'),
            'finnishHelp' => sprintf(__('Finnish is currently only available with the %s payment method.', 'woo-vipps'), 'MobilePay'),
            'verb' => __('Verb', 'woo-vipps'), 'buy' => __('Buy', 'woo-vipps'),
            'pay' => __('Pay', 'woo-vipps'), 'continue' => __('Continue', 'woo-vipps'),
            'confirm' => __('Confirm', 'woo-vipps'), 'donate' => __('Donate', 'woo-vipps'),
            'expressVerb' => __('Express', 'woo-vipps'), 'variant' => __('Variant', 'woo-vipps'),
            'primary' => __('Primary', 'woo-vipps'), 'dark' => __('Dark (WCAG AAA)', 'woo-vipps'),
            'light' => __('Light (WCAG AAA)', 'woo-vipps')
        ], 'buttonaction', 'buttonnonce');
    }

    private function mount_settings_subpage($page, $data, $translations, $nonce_action, $nonce_name) {
        $vipps = Vipps::instance();
        echo '<div class="wrap vipps-admin-settings-page"><div class="wp-header-end"></div>';
        echo '<div id="vipps-mobilepay-react-ui"></div>';
        wp_enqueue_script('vipps-mobilepay-react-ui', plugins_url('dist/plugin.js', __FILE__), ['wp-element'], filemtime(__DIR__ . '/dist/plugin.js'), true);
        $translations = array_merge(self::common_translations(), $translations);
        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactTranslations', $translations);
        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactOptions', []);
        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactMetadata', [
            'page' => $page, 'company_name' => Vipps::CompanyName(), 'payment_method' => $vipps->get_payment_method_name(), 'page_data' => $data,
            'post_url' => admin_url('admin-post.php'), 'nonce_name' => $nonce_name,
            'nonce' => wp_create_nonce($nonce_action),
        ]);
        echo '</div>';
    }

    // Translations shared by every React admin page. Resolve them when the page is rendered.
    public static function common_translations() {
        return array(
                'settings_subtitle' => __('Single payments', 'woo-vipps'),
                'express_shipping_section' => __('Shipping', 'woo-vipps'),
                'test_keys_section' => __('Test environment', 'woo-vipps'),
                'production_keys_section' => __('Production environment', 'woo-vipps'),
                'order_status_section' => __('Order status', 'woo-vipps'),
                'checkout_advanced_section' => __('Advanced settings', 'woo-vipps'),
                'express_advanced_section' => __('Advanced settings', 'woo-vipps'),
                'express_placement' => __('Configure placement', 'woo-vipps'),
                'request_error' => __('Could not complete the request. Please try again.', 'woo-vipps'),
                'save_changes' => __('Save changes', 'woo-vipps'),
                'upload_image' => __('Upload image', 'woo-vipps'),
                'remove_image' => __('Remove image', 'woo-vipps'),
                'next_step' => __('Next step', 'woo-vipps'),
                'previous_step' => __('Previous step', 'woo-vipps'),
                'receipt_image_size_requirement' => __('The image must be at least 167 pixels in height', 'woo-vipps'),
                'receipt_image_error' => __('The uploaded image is too small. It must be at least 167 pixels in height.', 'woo-vipps'),
                'settings_saved' => __('Settings saved', 'woo-vipps'),

                'kustom_sale_1' => __('Checkout - Important Update', 'woo-vipps'),
                'kustom_sale_2' => __('Vipps MobilePay has entered into an agreement to sell the Checkout solution to Kustom. As part of this transition, <b>Vipps MobilePay Checkout will become Kustom Checkout</b>. You can follow <a href="https://docs.kustom.co/contents/partners/e-commerce-platforms/woocommerce-vipps-guide#switch-from-vipps-checkout-to-kustom-checkoutguide" target="_blank">this guide</a> to migrate over to Kustom Checkout.', 'woo-vipps'),
                'kustom_sale_3' => __('Going forward, Kustom will be responsible for delivering and developing the Checkout solution. <b>Vipps MobilePay will remain available as a payment method in Kustom Checkout</b>, so your customers can continue to pay with Vipps MobilePay in the familiar way.', 'woo-vipps'),
                'kustom_sale_4' => __('Please note that <b>accounts created after March 27, 2026 will not support Checkout in this plugin</b>.', 'woo-vipps'),
                'kustom_sale_5' => __('If you have any questions about what the transition means for you, please see our <a href="https://vippsmobilepay.com/en-NO/vippsmobilepay-kustom" target="_blank">FAQ</a>.', 'woo-vipps'),
                'kustom_sale_6' => sprintf(__('For help you can reach out to <a href="mailto:%1$s">%1$s</a> and <a href="tel:%2$s">%3$s</a>. You can also find the Kustom portal <a href="%4$s" target="_blank">here</a>.', 'woo-vipps'), 'support@kustom.co', '+4721564684', '+47 21 56 46 84', 'https://portal.kustom.co/'),
                'checkoutcreateuser_extra' => sprintf(__('When disabled, orders are placed as guest checkouts.<br>If enabled, you may want to install the plugin %1$s to provide easier login for customers.', 'woo-vipps'), Vipps::LoginName()),
                'expresscreateuser_extra' => sprintf(__('When disabled, orders are placed as guest checkouts.<br>If enabled, you may want to install the plugin %1$s to provide easier login for customers.<br>If you have %1$s installed, customer creation is enabled by default unless disabled in WooCommerce.', 'woo-vipps'), Vipps::LoginName()),
                'enablestaticshipping_extra' => __('Guest orders use your store’s base location; logged-in customers use their saved address. Enable this only when those locations produce accurate shipping options, such as with flat-rate or free shipping.', 'woo-vipps'),
                'result_status_extra' => sprintf(
                    __('Select %1$s if you capture payment before shipping, either manually or by marking the order as %3$s.<br>Select %2$s if %1$s triggers shipping in your store.<br>&#9;&gt; Note that %2$s may send customers an email suggesting there is a problem with their order.', 'woo-vipps'),
                    __('Processing', 'woo-vipps'),
                    __('On hold','woo-vipps'),
                    __('Complete','woo-vipps'),
                ),
                'status_on_fail_extra' => sprintf(
                        __('%1$s orders will keep the customer\'s shopping cart intact.<br>%2$s orders can be restarted, possibly with another payment method.', 'woo-vipps'),
                        /* translators: woocommerce order status name */
                        __('Failed', 'woo-vipps'),
                        /* translators: woocommerce order status name */
                        __('Cancelled','woo-vipps'),
                ),
            );
    }

    // Initializes the admin settings UI for VippsMobilePay
    function init_admin_settings_page_react_ui() {
        global $Vipps;
        echo "<div class='wrap vipps-admin-settings-page'>";
        // Add nonce first.
        wp_nonce_field('vippsadmin_nonce', 'vippsadmin_nonce');

        // We must first generate the root element for the React UI before we load the React app itself, otherwise React will fail to load.
        ?>
        <div class="wp-header-end"></div> <?php // this makes the wp admin notices render here instead of inside the settings panel. LP 2026-09-25 ?>
        <div id="vipps-mobilepay-react-ui"></div>
        <?php

        // Initializing the wordpress media plugin, so we can upload images
        wp_enqueue_media();
        $gw = $this->gateway();
        $card_gw = WC_Gateway_VippsCard::instance();

        // Loads the React UI
        $reactpath = "dist";
        wp_enqueue_script('vipps-mobilepay-react-ui', plugins_url($reactpath . '/plugin.js', __FILE__), array('wp-element'), filemtime(__DIR__ . "/$reactpath/plugin.js"), true);
//        wp_enqueue_style('vipps-mobilepay-react-ui', plugins_url($reactpath . '/plugin.css', __FILE__), array(), filemtime(__DIR__ . "/$reactpath/plugin.css"));

        $metadata = array(
            'admin_url' => admin_url('admin-ajax.php'),
            'page' => 'admin_settings_page',
            'company_name' => Vipps::CompanyName(),
            'currency' => get_woocommerce_currency(),
            // for debugging/testing: show wizard screen always IOK 2025-01-20
            '__dev_force_wizard_screen' => defined('WOO_VIPPS_FORCE_WIZARD') && WOO_VIPPS_FORCE_WIZARD,
            // Only show checkout options if checkout has actually been activated.
            'vipps_checkout_activated' => intval(get_option('woo_vipps_checkout_activated'))
        );

        /* We need to postprocess the settings for.. various reasons IOK 2024-06-04  */
        /* Also we need to run init_form_fields here, because for whatever reason the
         * first time it is called, it does wrong things in this context. IOK 2024-06-04 */
        $gw->init_form_fields();
        $settings = $gw->settings;

        $wizardTranslations = [
            'initial_settings' => __('Initial settings wizard', 'woo-vipps'),
            'wizard_header' => [
                'title' => __('Initial settings', 'woo-vipps'),
                'description' => sprintf(__('Welcome! You are almost ready to accept payments with %1$s', 'woo-vipps'), Vipps::CompanyName()),
            ],
            'help_box' => [
                    'get_started' => __('Get started', 'woo_vipps'),
                    'documentation' => __('Documentation', 'woo-vipps'),
                    'portal' => sprintf(__('%1$s Portal', 'woo-vipps'), Vipps::CompanyName()),
                    'support' => [
                        'title' => __('Support', 'woo-vipps'),
                        'description' => __('If you have any questions related to this plugin, you are welcome to check out the <a href="https://wordpress.org/support/plugin/woo-vipps/" target="_blank">support forum.</a>', 'woo-vipps'),
                    ],
                ],
            'express_options_wizard' => array(
                'title' => sprintf(__('Get started with %1$s', 'woo-vipps'), Vipps::ExpressName()),
                'description' => sprintf(__("%s is your shortcut to faster and seamless payments. Designed for businesses and customers, it eliminates the hassle of traditional checkout processes, enabling frictionless transactions in seconds.", 'woo-vipps'), Vipps::ExpressName()),
                'readmore' => __('Read more', 'woo-vipps'),
            ),
            'testmode_wizard' => [
                'description' => sprintf(__('Use the %1$s test environment instead of live production environment. No real transactions will be performed.', 'woo-vipps'), Vipps::CompanyName()),
            ],
        ];

        if (!empty($settings['receiptimage'])) {
            $settings['receiptimage_url'] = wp_get_attachment_url($settings['receiptimage']);
        }
        if (!$gw->allow_external_payments_in_checkout()) {
           unset($settings['checkout_external_payments_klarna']);
        } else {
            // nop right now
        }

        /* Add the options from the credit card gateway too, with a cc_prefix which we strip on process_payments. IOK 2026-05-27*/
        $card_gw->init_form_fields();
        $cc_translations = [];
        foreach($card_gw->settings as $key => $data) {
            $settings["cc_$key"] = $data;
        }
        foreach($card_gw->form_fields as $key => $data) {
            $cc_translations["cc_$key"] = $data;
        }
        $cc_translations['cc_options'] = [
           'title' => __('Card payments', 'woo-vipps'), 
           'description' => __("Provide a card payment method for customers that haven't got the app (yet!)", 'woo-vipps'),
            'test_mode_warning' => sprintf(__('Warning: card payments may not yet be available in the test environment, please check the %1$s <a href="https://developer.vippsmobilepay.com/docs/knowledge-base/test-environment/">knowledge base</a> for updated status about the test environment.'), Vipps::CompanyName()),
        ];

        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactTranslations', array_merge($gw->form_fields, $cc_translations, self::common_translations(), $wizardTranslations));
        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactOptions', $settings);
        wp_localize_script('vipps-mobilepay-react-ui', 'VippsMobilePayReactMetadata', $metadata);

        echo "</div>";
    }
}
?>
