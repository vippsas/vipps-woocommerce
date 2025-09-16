<?php
/*
   Delegate class for talking to Vipps MobilePay, encapsulating all the low-level behaviour and mapping error codes to exceptions

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
if ( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly
}
require_once(dirname(__FILE__) . "/VippsApi.class.php");

class WC_Gateway_Vipps extends WC_Payment_Gateway {
    public $form_fields = null;
    public $dev_form_fields = null;
    public $id = 'vipps';
    public $icon = ''; 
    public $has_fields = true;
    public $method_title = 'Vipps MobilePay';
    public $title = 'Vipps MobilePay';
    public $method_description = "";
    public $apiurl = null;
    public $testapiurl = null;
    public $api = null;
    public $supports = null;
    public $express_checkout_supported_product_types;

    public $captured_statuses;

    private static $instance = null;  // This class uses the singleton pattern to make actions easier to handle

    protected $keyset = null; // This will contain all api keys etc for  the gateway, keyed on the merchant serial number.

    // Just to avoid calculating these alot
    private $page_templates = null;
    private $page_list = null;



    // This returns the singleton instance of this class
    public static function instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
    } 

    public function add_image_upload_setting_widget () {
        add_action('admin_enqueue_scripts', function ($suff) {
            if ($suff  == 'woocommerce_page_wc-settings' && (($_REQUEST['section'] ?? false) == 'vipps')) {
               if (!did_action('wp_enqueue_media')) {
		wp_enqueue_media();
	       }
            } 
        });
    }         

    
    // Generates html for the woo_vipps_image settings widget type
    public function generate_woo_vipps_image_html  ($key, $field) {
        $field_key = $this->get_field_key( $key );
        $defaults  = array(
                'title'             => '',
                'disabled'          => false,
                'class'             => '',
                'css'               => '',
                'placeholder'       => '',
                'type'              => 'woo_vipps_image',
                'desc_tip'          => false,
                'description'       => '',
                'custom_attributes' => array(),
                );
        $data = wp_parse_args( $field, $defaults );

        $imgid = intval($this->get_option($key));
        $image = $imgid ?  wp_get_attachment_image_src($imgid) : "";

        ob_start();
        ?>
            <tr valign="top">
            <th scope="row" class="titledesc">
            <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
        </th>
            <td class="forminp">
            <fieldset>
            <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
<?php if ($image): ?>
         <a href="#" class="woo-vipps-image-upload"><img style="max-width: 360px; max-height: 360px" src="<?php echo $image[0]; ?>" /><span style="display:none" class='uploadtext'><?php _e('Upload image', 'woo-vipps'); ?></span></a>
         <a href="#" class="woo-vipps-image-remove"><?php _e('Remove image', 'woo-vipps');?></a>
<?php else: ?>
         <a href="#" class="woo-vipps-image-upload"><img style="display:none; max-width:360px; max-height: 360px"/><span class='uploadtext'><?php _e('Upload image', 'woo-vipps'); ?></span></a>
         <a href="#" class="woo-vipps-image-remove" style="display:none;"><?php _e('Remove image', 'woo-vipps');?></a>
<?php endif; ?>
            <input type="hidden" class="woo-vipps-image-input <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr($imgid); ?>" <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
        <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
        </fieldset>
            </td>
            </tr>
            <?php

            return ob_get_clean();
    }

    // Attempts to detect the current country based on the store's currency NT-2024-10-15
    private function detect_country_from_currency() {
        $currency = get_woocommerce_currency();
        switch ($currency) {
            case 'DKK':
                return 'DK';
            case 'NOK':
                return 'NO';
            case 'SEK':
                return 'SE';
            case 'EUR':
                return 'FI';
            default:
                return null;
        }
    }
    // Migrates the keysets to include the country setting if it's missing
    // This is an initial migration step to ensure the country setting is explicitly set.
    // We do this because we no longer want to automatically guess payment method name. NT-2024-10-15
    private function migrate_keyset_with_country_detection() {
        $settings = get_option('woocommerce_vipps_settings', array());
        if ($settings['country'] ?? false) return; // Already set, do nothing IOK 2024-10-17

        // Now we are only wanting to do this with people who have already configured the plugin. These will have at least this value set:
        if ($settings['payment_method_name'] ?? false) {
            // This assumes that EUR == FI which will be correct for all users reaching this branch IOK 2024-10-17
            $detected_country = $this->detect_country_from_currency();
            // If we can't detect the country, there's nothing to migrate. "this cannot happen" etc. 
            if (!$detected_country) return;

            $settings['country'] = $detected_country;
            update_option('woocommerce_vipps_settings', $settings);
            delete_transient('_vipps_keyset');
            return;
        }
    }

    public function __construct() {
        $this->testapiurl = 'https://apitest.vipps.no';
        $this->apiurl = 'https://api.vipps.no';
        
        $this->method_description = __('Offer Vipps or MobilePay as a payment method', 'woo-vipps');
        $this->method_title = __('Vipps MobilePay','woo-vipps');
        $this->title = __('Vipps MobilePay','woo-vipps');

        $this->icon = plugins_url('img/vmp-logo.png',__FILE__);
        $this->migrate_keyset_with_country_detection();
        $this->init_form_fields();
        $this->init_settings();


        $this->api = new VippsApi($this);

        $this->supports = array('products','refunds');

        // We can't guarantee any particular product type being supported, so we must enumerate those we are certain about
        // IOK 2020-04-21 Add support for WooCommerce Product Bundles
        $supported_types= array('simple','variable','variation','bundle', 'yith_bundle', 'gift-card');
        $this->express_checkout_supported_product_types = apply_filters('woo_vipps_express_checkout_supported_product_types',  $supported_types);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options') );
        add_action('admin_init', array($this, 'add_image_upload_setting_widget'));

        //  Capturing, refunding and cancelling the order when transitioning states:
        //   This are the statuses for which the Vipps MobilePay plugin should try to ensure capture has been made.
        //   Normally, this is 'processing' and 'completed', but plugins may define other statuses. IOK 2018-10-05
        //  It is also possible to remove 'processing' from this list. If you do, you may use it as the end-state of the
        //  Vipps MobilePay transaction (see below in after_vipps_order_status) IOK 2018-12-05
        $resultstatus = $this->get_option('result_status');
        $captured_statuses = apply_filters('woo_vipps_captured_statuses', array('processing', 'completed'));
        $captured_statuses = array_diff($captured_statuses, array($resultstatus));

        $this->captured_statuses = $captured_statuses;

        $non_completed_captured_statuses = array_diff($captured_statuses, array('completed'));

        // This ensures that funds are captured when transitioning from 'on hold' to a status where the money
        // should be captured, and refunded when moved from this status to cancelled or refunded
        foreach($captured_statuses as $capstatus) {
           add_action('woocommerce_order_status_' . $capstatus, array($this, 'maybe_capture_payment'));
        }
        // We will refund money on cancelled orders, but only if they are *relatively new*. This is to 
        // avoid accidents and issues where old orders are *somehow* cancelled even though they are complete. IOK 2024-08-12
        add_action('woocommerce_order_status_cancelled', array($this, 'order_status_cancelled_wrapper'));
        add_action('woocommerce_order_status_refunded', array($this, 'maybe_refund_payment'));

        add_action('woocommerce_order_status_pending_to_cancelled', array($this, 'maybe_delete_order'), 99999, 1);

        add_action('woocommerce_payment_complete', array($this, 'order_payment_complete'), 10, 1);

        // when an order is complete, we need to check if there is reserved amount that is not captured
        // if so, we need to cancel this amount PMB 2024-11-21
        // nb: note very late priority - we must have captured before, please
        add_action('woocommerce_order_status_completed', array($this, 'maybe_cancel_reserved_amount'), 99);
    }

    // this function is called after an order is changed to complete it checks if there is reserved money that is not captured
    // if there still is money reserved, then this amount is cancelled  PMB 2024-11-21
    public function maybe_cancel_reserved_amount ($orderid) {
        $order = wc_get_order($orderid);
        if (!$order) return;
        if ('vipps' != $order->get_payment_method()) return false;
        // Cannot partially cancel legacy ecom orders
        if ('epayment' != $order->get_meta('_vipps_api')) return false; 
        // Check that the normal maybe_capture_order hook has actually ran *and* done something,
        // it's only after this we know we have captured 'everything' so if there is anything left, 
        // it should be cancelled. IOK 2025-05-04                                                              
        if (! $order->get_meta('_vipps_capture_complete')) {
            return false;
        }
        // We also only want to do this for orders that have had *something* captured. IOK 2025-02-04
        $captured = intval($order->get_meta('_vipps_captured'));
        if ($captured < 1) {
            return false;
        }
        // Allow merchants that do not reserve large amounts to opt out for safety IOK 2025-02-04
        if (apply_filters('woo_vipps_never_cancel_uncaptured_money', false, $order)) return false;

        $ok = true;

        $remaining = intval($order->get_meta('_vipps_capture_remaining'));
        if ($remaining > 0) {
            $this->log(sprintf(__("maybe_cancel_reserved_amount we have remaining reserved after capture of total %1\$s ",'woo-vipps'), $remaining),'debug');
            $currency = $order->get_currency();
            try {
                // This will only cancel any remaining amount. IOK 2024-11-25
                $res = $this->api->epayment_cancel_payment($order,$requestid=1);


                $amount = number_format($remaining/100, 2) . " " . $currency;
                $note = sprintf(__('Order %1$s: %2$s is cancelled to free up the reservation in the customers bank account.', 'woo-vipps'), $orderid, $amount);
                $order->add_order_note($note);
            } catch (Exception $e) {
                $ok = false;
                // if this happens, we just log it - we may not have an active admin
                $msg = sprintf(__('Was not able to cancel remaining amount for the order %1$s: %2$s','woo-vipps'), $orderid, $e->getMessage());
                $order->add_order_note($msg);
                $this->log($msg,'error');
            }

            // We need to update the order details after the fact. We can't fix errors here though. IOK 2024-11-22
            if ($ok) {
                try {
                    $this->update_vipps_payment_details($order);
                } catch (Exception $e) {
                    // noop
                }
            }

        }
        // we just return true from this function for now PMB 2024-11-21
        // return false if we couldn't cancel reserved. IOK 2024-11-22
        return $ok;
    }


    public function get_icon () {
        $src =  $this->icon;
        if ($this->get_payment_method_name() == "Vipps") {
            $src = plugins_url('img/vipps-mark.svg',__FILE__);
        } else {
            $src = plugins_url('img/mobilepay-mark.png',__FILE__);
        }
        return '<img src="' . esc_attr($src) . '" alt="' .  $this->get_payment_method_name() . '">';
    }


    // True iff this gateway is currently in test mode. IOK 2019-08-30
    public function is_test_mode() {
       if (VIPPS_TEST_MODE) return true;
       if ($this->get_option('developermode') == 'yes' && $this->get_option('testmode') == 'yes') return true;
       return false;
    }
    // These abstraction gets the correct client id and so forth based on whether or not test mode is on
    // "test mode" is now per MSN, so we accept that as an argument IOK 2023-12-19
    public function apiurl ($msn="") {
       $msn = $msn ?? $this->get_merchant_serial();
       $keyset = $this->get_keyset();
       $entry  = $keyset ? ($keyset[$msn] ?? null) : null;
       if (!$entry) {
           $testmode = $this->is_test_mode();
       } else {
           $testmode = $entry['testmode'];
       }
       if ($testmode) return $this->testapiurl;
       return $this->apiurl;
    }

    // This returns the *current* merchant serial number. There may be more than one, for instance if the test mode is on.
    // IOK 2023-12-19
    public function get_merchant_serial() {
        $merch = $this->get_option('merchantSerialNumber');
        $testmerch = @$this->get_option('merchantSerialNumber_test');
        if (!empty($testmerch) && $this->is_test_mode()) return $testmerch;
        return $merch;
    }

    // Returns a table of all the keydata of this instance, keyed on MSN. IOK 2023-12-19
    public function get_keyset() {
        if ($this->keyset) return $this->keyset;
        $stored = get_transient('_vipps_keyset');
        if ($stored) {
            return $stored;
        }

        $keyset = [];
        $main = $this->get_option('merchantSerialNumber');
        if ($main) {
            $data = ['client_id'=>$clientid=$this->get_option('clientId'), 
                'client_secret' => $this->get_option('secret'), 
                'sub_key'=>$this->get_option('Ocp_Apim_Key_eCommerce'),
                'country' => $this->get_option('country'),
                'gw' => 'vipps'
            ];
            if (! in_array(false, array_map('boolval', array_values($data)))) {
                $data['testmode'] = 0; // Must add after 
                $keyset[$main] = $data;
            }
        }

        $test = @$this->get_option('merchantSerialNumber_test');
        $testmode = @$this->get_option('testmode');
        if ($testmode === 'yes' && $test) {
            $data = [
                'client_id'=>$clientid=$this->get_option('clientId_test'),
                'client_secret' => $this->get_option('secret_test'), 
                'sub_key'=>$this->get_option('Ocp_Apim_Key_eCommerce_test'),
                'country' => $this->get_option('country'),
                'gw' => 'vipps'
            ]; 

            if (! in_array(false, array_map('boolval', array_values($data)))) {
                $data['testmode'] = 1;
                $keyset[$test] = $data;
            }
        }

        $this->keyset = $keyset;
        set_transient('_vipps_keyset', $keyset, DAY_IN_SECONDS);
        return $keyset;
    }

    // Get and show any keysets from the Vipps Recurring plugin too IOK 2024-11-29
    private function get_recurring_keysets () {
        $res = [];
        $settings = get_option( 'woocommerce_vipps_recurring_settings' );
        if (empty($settings)) return $res;

        $country = $this->get_option('country');

        $client_id     =  $settings["client_id"] ?? "";
        $client_secret =  $settings["secret_key"] ?? "";
        $subscription_key =  $settings["subscription_key"] ?? "";
        $merchant_serial_number =  $settings["merchant_serial_number"] ?? "";
        if ($merchant_serial_number && $client_id && $client_secret && $subscription_key ) {
           $res[$merchant_serial_number] = ['client_id' => $client_id, 'client_secret' => $client_secret, 'sub_key' => $subscription_key, 'country' => $country, 'testmode' => 0, 'gw'=>'vipps_recurring'];
        }

        $client_id     =  $settings["test_client_id"] ?? "";
        $client_secret =  $settings["test_secret_key"] ?? "";
        $subscription_key =  $settings["test_subscription_key"] ?? "";
        $merchant_serial_number =  $settings["test_merchant_serial_number"] ?? "";
        if ($merchant_serial_number && $client_id && $client_secret && $subscription_key ) {
           $res[$merchant_serial_number] = ['client_id' => $client_id, 'client_secret' => $client_secret, 'sub_key' => $subscription_key, 'country' => $country, 'testmode' => 1, 'gw'=>'vipps_recurring'];
        }
        return $res;
    }

    // Return all webhooks for our MSNs
    public function get_webhooks_from_vipps () {
        $keys = $this->get_keyset();
        $hooks = [];
        foreach($keys as $msn=>$data) {
            try {
                $hooks[$msn] = $this->api->get_webhooks($msn);
            } catch (Exception $e) {
                $this->log(sprintf(__('Could not get webhooks for Merchant Serial Number  %1$s: %2$s', 'woo-vipps'), $msn, $e->getMessage()), 'error');
                $hooks[$msn]=[];
            }
        }
        return $hooks;
    }


    // The rest of the settings gets the correct client id, secret, sub key and order prefix based on the MSN.
    public function get_clientid($msn="") {
        if (!$msn) $msn = $this->get_merchant_serial();
        $keyset = $this->get_keyset();
        if (!isset($keyset[$msn])) return false;
        return $keyset[$msn]['client_id'];
    }
    public function get_secret($msn="") {
        if (!$msn) $msn = $this->get_merchant_serial();
        $keyset = $this->get_keyset();
        if (!isset($keyset[$msn])) return false;
        return $keyset[$msn]['client_secret'];
    }
    public function get_key($msn="") {
        if (!$msn) $msn = $this->get_merchant_serial();
        $keyset = $this->get_keyset();
        if (!isset($keyset[$msn])) return false;
        return $keyset[$msn]['sub_key'];
    }
    public function get_country($msn="") {
        if (!$msn) $msn = $this->get_merchant_serial();
        $keyset = $this->get_keyset();
        if (!isset($keyset[$msn])) return false;
        return $keyset[$msn]['country'];
    }

    public function get_orderprefix() {
        $prefix = $this->get_option('orderprefix');
        return $prefix;
    }

    // We did shenanigans here earlier, we don't have to do that anymore. IOK 2022-12-09
    public function get_return_url($order=null) {
        $url = parent::get_return_url($order); 
        return $url;
    }


    // Delete express checkout orders with no customer information - these were abandonend before the app started.
    // IOK 2019-08-26
    public function maybe_delete_order ($orderid) {
        $order = wc_get_order($orderid);
        if (!$order) return;
        if ('vipps' != $order->get_payment_method()) return false;
        $express = $order->get_meta('_vipps_express_checkout');
        if (!$express) return false;
        $email = $order->get_billing_email();
        if ($email) return false;

        // Only delete if we have to
        if ($this->get_option('deletefailedexpressorders')  != 'yes') return false;
        // Mark this order that an order that wasn't completed with any user info - it can be deleted. IOK 2019-11-13
        $order->update_meta_data('_vipps_delendum',1);
        $order->save();
        return true;
    }


    // Return the status to use after return from Vipps MobilePay for orders that are not both "virtual" and "downloadable".
    // These orders are *not* complete, and payment is *not* captured, which is why the default status is 'on-hold'.
    // If you use custom order statuses, or if you don't capture on 'processing' - see filter 'woo_vipps_captured_statuses' -
    // you can instead use 'processing' here - which is much nicer. 
    // If you do so, remember to capture *before* shipping is done on the order - if you send the package and then do 'complete', 
    // the capture may fail. IOK 2018-12-05
    //
    // IOK As of 2023-12-22, the default is now 'processing', since this is more in line with what other gateways are using,
    // what other integrations and plugins expect, the most popular choice by users; and because of the fact that "on-hold" is
    // normally used to indicate "a problem with the order". Not being able to capture a reserved order has to my knowledge at this
    // point only happened once, in 2018, with a completely different api and backend.
    public function after_vipps_order_status($order=null) {
      // Revert to on-hold if the user tries to set a payment status that is a 'captured' status IOK 2024-01-25
      $defaultstatus = 'on-hold';

      $chosen = $this->get_option('result_status');
      $newstatus = apply_filters('woo_vipps_after_vipps_order_status', $chosen, $order);

      if (in_array($newstatus, $this->captured_statuses)){
             $this->log(sprintf(__("Cannot use %1\$s as status for non-autocapturable orders: payment is captured on this status. See the woo_vipps_captured_statuses-filter.",'woo-vipps'), $newstatus),'debug');
             return  $defaultstatus;
      }
      return $newstatus;
    }

    // Create callback urls' using WC's callback API in a way that works with Vipps MobilePay callbacks and both pretty and not so pretty urls.
    private function make_callback_urls($forwhat,$token='', $reference=0) {
        // Passing the token as GET arguments, as the Authorize header is stripped. IOK 2018-06-13
        // This applies to Ecom, Checkout and Express Checkout callbacks.  For epayment, we instead need to use 
        // the webhook api, which is altogether different. IOK 2023-12-19
        $url = home_url("/", 'https');
        $queryargs = [];
        if ($token) $queryargs['tk']=$token;
        if ($reference) $queryargs['id']=$reference;

        // HTTPS required. IOK 2018-05-18
        // If the user for some reason hasn't enabled pretty links, fall back to ancient version. IOK 2018-04-24
        if ( !get_option('permalink_structure')) {
            $queryargs['wc-api'] = $forwhat;
        } else {
            $url = trailingslashit(home_url("wc-api/$forwhat", 'https'));
        }
        // And we need to add an empty "callback" query arg as the very last arg to receive the actual callback.
        // We can't use add_query_arg for that, as an empty argument will remove the equals-sign.
        $callbackurl = add_query_arg($queryargs, $url) . "&callback=";
        return  $callbackurl;
    }

    // Webhook callbacks do not pass GET arguments at all, but do provide an X-Vipps-Authorization header for verification. IOK 2023-12-19
    public function webhook_callback_url () {
        $url = home_url("/", 'https');
        $queryargs = ['callback'=>'webhook'];
        $forwhat = 'wc_gateway_vipps'; // Same callback as for ecom, checkout, express checkout
        // HTTPS required. IOK 2018-05-18
        // If the user for some reason hasn't enabled pretty links, fall back to ancient version. IOK 2018-04-24
        if ( !get_option('permalink_structure')) {
            $queryargs['wc-api'] = $forwhat;
        } else {
            $url = trailingslashit(home_url("wc-api/$forwhat", 'https'));
        }
        $callbackurl = add_query_arg($queryargs, $url);
        return  $callbackurl;
    }


    // The main payment callback
    public function payment_callback_url ($token='', $reference=0) {
        return $this->make_callback_urls('wc_gateway_vipps',$token, $reference);
    }
    public function shipping_details_callback_url($token='',$reference=0) {
        return $this->make_callback_urls('vipps_shipping_details',$token,$reference);
    }
    // Callback for the consetn removal callback. Must use template redirect directly, because wc-api doesn't handle DELETE.
    // IOK 2018-05-18
    public function consent_removal_callback_url () {
        $queryargs = [];
        $url = home_url("/", 'https');
        if ( !get_option('permalink_structure')) {
            $queryargs['vipps-consent-removal']=1;
        } else {
            $url = trailingslashit(home_url('vipps-consent-removal', 'https'));
        }
        // And we need to add an empty "callback" query arg as the very last arg to receive the actual callback.
        // We can't use add_query_arg for that, as an empty argument will remove the equals-sign.
        return add_query_arg($queryargs, $url) . "&callback=";
    }

    // Allow user to select the template to be used for the special Vipps MobilePay pages. IOK 2020-02-17
    public function get_theme_page_templates() {
        if (!$this->page_templates) {
            $choices = array('' => __('Use default template', 'woo-vipps'));
            foreach(wp_get_theme()->get_page_templates() as $filename=>$name) {
                $choices[$filename]=$name;
            }
            $this->page_templates = $choices;
        }
        return $this->page_templates;
     }

    // We can't use get_pages to get a default list of pages for our settings, because it triggers
    // actions that can be used by other plugins. Therefore we must use the database directly and cache the results. IOK 2023-08-22
    public function get_pagelist () {
        if (!$this->page_list) {
            global $wpdb;
            $page_list = array(''=>__('Use a simulated page (default)', 'woo-vipps'));
            foreach($wpdb->get_results("SELECT ID,post_title FROM {$wpdb->prefix}posts WHERE post_type='page' and post_status='publish'") as $page) {
                $page_list[$page->ID] = $page->post_title;
            }
            $this->page_list = $page_list;
        }
        return $this->page_list;
    }

    // Check to see if the product in question can be bought with express checkout IOK 2018-12-04
    public function product_supports_express_checkout($product) {
        // IOK 2023-12-12 Can only support express checkout for Vipps - not MobilePay (yet!)
        // IOK 2025-09-01 Now supports mobilepay
        return apply_filters('woo_vipps_product_supports_express_checkout', $this->product_supports_checkout($product), $product);
    }

    // Checkout and Express Checkout are very similarily restricted because they both replace the standard
    // Woo Checkout page, but express checkout is even more restricted, so we need to separate out the commonalities. IOK 2024-01-11
    public function product_supports_checkout($product) {
        $type = $product->get_type();
        $ok = in_array($type, $this->express_checkout_supported_product_types);
        $ok = apply_filters('woo_vipps_product_supports_checkout',$ok,$product);
        return $ok;
    }

    // Almost the same as express checkout - unfortunately not *entirely* the same. IOK 2024-01-11
    public function cart_supports_checkout($cart=null) {
        if (!$cart) $cart = WC()->cart;
        if (!$cart) return false;
        # Not supported by Vipps MobilePay
        if ($cart->cart_contents_total <= 0) return false;

        $supports  = true;
        foreach($cart->get_cart() as $key=>$val) {
            $prod = $val['data'];
            if (!is_a($prod, 'WC_Product')) continue;
            $product_supported = $this->product_supports_checkout($prod);
            if (!$product_supported) {
                $supports = false;
                break;
            }
        }
        $supports = apply_filters('woo_vipps_cart_supports_checkout', $supports, $cart);
        return $supports;
    }

    // Check to see if the cart passed (or the global one) can be bought with express checkout IOK 2018-12-04
    public function cart_supports_express_checkout($cart=null) {
        if (!$cart) $cart = WC()->cart;
        $supports  = true;
        if (!$cart) return $supports;
        # Not supported by Vipps MobilePay
        if ($cart->cart_contents_total <= 0) return false;

        foreach($cart->get_cart() as $key=>$val) {
            $prod = $val['data'];
            if (!is_a($prod, 'WC_Product')) continue;
            $product_supported = $this->product_supports_express_checkout($prod);
            if (!$product_supported) {
                $supports = false;
                break;
            }
        }
        $supports = apply_filters('woo_vipps_cart_supports_express_checkout', $supports, $cart);
        return $supports;
    }

    // True if "Express checkout" should be displayed IOK 2018-06-18
    public function show_express_checkout() {
        if (!$this->express_checkout_available()) return false;
        $show = ($this->enabled == 'yes') && ($this->get_option('cartexpress') == 'yes') ;
        $show = $show && $this->cart_supports_express_checkout();

        // By default don't show express checkout in cart if Vipps MobilePay Checkout is enabled
        $show = $show && ($this->get_option('vipps_checkout_enabled') != 'yes');

        return apply_filters('woo_vipps_show_express_checkout', $show);
    }

    public function show_login_with_vipps() {
        return false;
    }
   
    // Called when orders reach the 'cancelled'-status. When this happens, orders will be *refunded*
    // when they have been captured, but for added safety, this is only done when the orders are relatively new. 
    public function order_status_cancelled_wrapper($order_id) {
        $order = wc_get_order($order_id);
        if ('vipps' != $order->get_payment_method()) return false;

        $days_threshold = apply_filters('woo_vipps_cancel_refund_days_threshold', 30);
        $order_date = $order->get_date_created();
        $days_since_order = (time() - $order_date->getTimestamp()) / (60 * 60 * 24);

        $captured = intval($order->get_meta('_vipps_captured'));

        // This will just cancel the order, including at Vipps. No funds have been captured.
        if ($captured == 0) {
            return $this->maybe_cancel_payment($order_id);
        }

        // If this is true then the order is *too old to refund* which would happen on maybe_cancel_payment. 
        // add a note instead.
        if ($days_since_order > $days_threshold) {
            $note = sprintf(__('Order with captured funds older than %d days cancelled - because the order is this old, it will not be automatically refunded at Vipps. Manual refund may be required.', 'woo-vipps'), $days_threshold);
            $order->add_order_note($note);
            // Add an admin notice in case this is interactive
            $msg = sprintf(__("Could not cancel %1\$s payment", 'woo-vipps'), $this->get_payment_method_name());
            $this->adminerr(__('Order', 'woo-vipps') . " " . $order->get_id() . ": " . $note);
            $order->save();
            Vipps::instance()->store_admin_notices();
            return false;
        }

        // If not, then the older is pretty new so we will cancel or refund it, as before
        return $this->maybe_cancel_payment($order_id);
    }

    public function maybe_cancel_payment($orderid) {
        $order = wc_get_order($orderid);
        if ('vipps' != $order->get_payment_method()) return false;
        $ok = 0;

        // Now first check to see if we have captured anything, and if we have, refund it. IOK 2018-05-07
        $captured = intval($order->get_meta('_vipps_captured'));
        $vippsstatus = $order->get_meta('_vipps_status');
        if ($captured || $vippsstatus == 'SALE') {
            return $this->maybe_refund_payment($orderid);
        }

        try {
            $order = $this->update_vipps_payment_details($order); 
        } catch (Exception $e) {
                //Do nothing with this for now
                $this->log(__("Error getting payment details before doing cancel: ", 'woo-vipps') . $e->getMessage(), 'warning');
        }

        $payment = $this->check_payment_status($order);
        if ($payment == 'initiated' || $payment == 'cancelled') {
           return true; // Can't cancel these
        }

        try {
            $ok = $this->cancel_payment($order);
        } catch (Exception $e) {
            // This is handled in sub-methods so we shouldn't actually hit this IOK 2018-05-07 
        } 
        if (!$ok) {
            // It's just a captured payment, so we'll ignore the illegal status change. IOK 2017-05-07
            $msg = sprintf(__("Could not cancel %1\$s payment", 'woo-vipps'), $this->get_payment_method_name());
            $this->adminerr($msg);
            $order->save();
            global $Vipps;
            $Vipps->store_admin_notices();
        }
    }

    // IOK 2024-09-01 In general, we can refund most Vipps Mobilepay orders through the api,
    // however, this is not the case for the Bank Transfer method available through Vipps Checkout. 
    public function can_refund_order( $order ) {
        $method = $order->get_meta('_vipps_api');
        switch ($method) {
            case 'banktransfer':
                return false;
                break;
            case 'epayment':
                return true;
                break;
                // Default is old-style ecom v2.
            default:
                return true;
                break;
        }
    }

    // Handle the transition from anything to "refund"
    public function maybe_refund_payment($orderid) {
        $order = wc_get_order($orderid);
        if ('vipps' != $order->get_payment_method()) return false;
        $ok = 0;

        // IOK 2019-10-03 it is now possible to do capture via other tools than Woo, so we must now first check to see if 
        // the order is capturable by getting full payment details.
        try {
                $order = $this->update_vipps_payment_details($order); 
       } catch (Exception $e) {
                //Do nothing with this for now
                $this->log(__("Error getting payment details before doing refund: ", 'woo-vipps') . $e->getMessage(), 'warning');
        }
        // Now first check to see if we have captured anything, and if we haven't, just cancel order IOK 2018-05-07
        $vippsstatus = $order->get_meta('_vipps_status');
        $captured = intval($order->get_meta('_vipps_captured'));
        $to_refund =  intval($order->get_meta('_vipps_refund_remaining'));

        if (!$captured) {
            return $this->maybe_cancel_payment($orderid);
        }
        if ($to_refund == 0) return true;

        try {
            $ok = $this->refund_payment($order,$to_refund,'exact');
        } catch (TemporaryVippsAPIException $e) {
            $this->adminerr(sprintf(__('Temporary error when refunding payment through %1$s - ensure order is refunded manually, or reset the order to "Processing" and try again', 'woo-vipps'), $this->get_payment_method_name()));
            $this->adminerr($e->getMessage());
            global $Vipps;
            $Vipps->store_admin_notices();
            return false;
        } catch (Exception $e) {
            $order->add_order_note(sprintf(__("Error when refunding payment through %1\$s:", 'woo-vipps'), $this->get_payment_method_name()) . ' ' . $e->getMessage());
            $order->save();
            $this->adminerr($e->getMessage());
        }
        if (!$ok) {
            $msg = sprintf(__('Could not refund payment through %1$s - ensure the refund is handled manually!', 'woo-vipps'), $this->get_payment_method_name());
            $this->adminerr($msg);
            $order->add_order_note($msg);
            // Unfortunately, we can't 'undo' the refund when the user manually sets the status to "Refunded" so we must 
            // allow the state change here if that happens.
            global $Vipps;
            $Vipps->store_admin_notices();
            return false;
        }
    }

    // This is for orders that are 'reserved' at Vipps but could actually be captured at once because
    // they don't require payment. So we try to capture. IOK 2020-09-22
    // do NOT call this unless the order is 'reserved' at Vipps!
    protected function maybe_complete_payment($order) {
        if ('vipps' != $order->get_payment_method()) return false;
        if ($order->needs_processing()) return false; // No auto-capture for orders needing processing
        // IOK 2018-10-03 when implementing partial capture, this must be modified.
        $captured = intval($order->get_meta('_vipps_captured')); 
        $vippsstatus = $order->get_meta('_vipps_status');
        if ($captured || $vippsstatus == 'SALE') { 
          return true;
        }
        $ok = 0;
        try {
            $ok = $this->capture_payment($order);
            $order->add_order_note(sprintf(__('Payment automatically captured at %1$s for order not needing processing','woo_vipps'), $this->get_payment_method_name()));
        } catch (Exception $e) {
            $order->add_order_note(sprintf(__('Order does not need processing, but payment could not be captured at %1$s:','woo_vipps'), $this->get_payment_method_name()) . ' ' . $e->getMessage());
        }
        if (!$ok) return false;
        $order->save();
        return true;
    }


    // This is the Woocommerce refund api called by the "Refund" actions. IOK 2018-05-11
    public function process_refund($orderid,$amount=null,$reason='') {
        $order = wc_get_order($orderid);

        $currency = $order->get_currency();

        try {
                $order = $this->update_vipps_payment_details($order); 
        } catch (Exception $e) {
                //Do nothing with this for now
                $this->log(__("Error getting payment details before doing refund: ", 'woo-vipps') . $e->getMessage(), 'warning');
        }

        $captured = intval($order->get_meta('_vipps_captured'));
        $to_refund =  intval($order->get_meta('_vipps_refund_remaining'));

        // No funds captured, by epayment can do cancel of partial capture, so let's note that we are not to capture this.
        if (!$captured) {
            if ('epayment' != $order->get_meta('_vipps_api')) {
                return new WP_Error('Vipps', sprintf(__("Cannot refund through %1\$s - the payment has not been captured yet.", 'woo-vipps'), $this->get_payment_method_name()));
            }
            if ($amount > $order->get_total()) {
                return new WP_Error('Vipps', sprintf(__("Cannot refund through %1\$s - the refund amount is too large.", 'woo-vipps'), $this->get_payment_method_name()));
            }
            $msg = sprintf(__('The money for order %1$d has not been captured, only reserved. %2$s %3$s of the reserved funds will be released when the order is set to complete.', 'woo-vipps'), $orderid, $amount, $currency);
            $this->log($msg, 'info');
            $uncapturable = round($amount * 100) + intval($order->get_meta('_vipps_noncapturable'));

            $order->update_meta_data('_vipps_noncapturable', $uncapturable);
            $order->add_order_note($msg);
            $order->save();
            return true;
        } 

        if ($amount*100 > $to_refund) {
            return new WP_Error('Vipps', sprintf(__("Cannot refund through %1\$s - the refund amount is too large.", 'woo-vipps'), $this->get_payment_method_name()));
        }
        $ok = 0;

        // Specialcase zero, because Vipps treats this as the entire amount IOK 2021-09-14
        if (is_numeric($amount) && $amount == 0) {
            $order->add_order_note($amount . ' ' . $currency . ' ' . sprintf(__(" refunded through %1\$s:",'woo-vipps'), Vipps::CompanyName()) . ' ' . $reason);
            return true;
        }

        try {
            $ok = $this->refund_payment($order,$amount);
        } catch (TemporaryVippsApiException $e) {
            $this->log(sprintf(__('Could not refund %1$s payment for order id:', 'woo-vipps'), $this->get_payment_method_name()) . ' ' . $orderid . "\n" .$e->getMessage(),'error');
            return new WP_Error('Vipps',sprintf(__('%1$s is temporarily unavailable.','woo-vipps'), Vipps::CompanyName()) . ' ' . $e->getMessage());
        } catch (Exception $e) {
            $msg = sprintf(__('Could not refund %1$s payment','woo-vipps'), Vipps::CompanyName()) . ' ' . $e->getMessage();
            $order->add_order_note($msg);
            $this->log($msg,'error');
            return new WP_Error('Vipps',$msg);
        }

        if ($ok) {
            $order->add_order_note($amount . ' ' . $currency . ' ' . sprintf(__(" refunded through %1\$s:",'woo-vipps'), Vipps::CompanyName()) . ' ' . $reason);
        } 
        return $ok;
    }

    // Detect default payment method based on country code NT-2024-10-15
    public function detect_default_payment_method_from_country($country_code) {
        // Default to MobilePay
        $payment_method_name = 'MobilePay';
        // Default to Vipps if country is Norway or Sweden
        if($country_code == 'NO' || $country_code == 'SE') {
            $payment_method_name = 'Vipps';
        }
        return $payment_method_name;
    }

    // Returns true iff this is a store where Vipps will allow external payment methods.
    // Currently this is only Finland, and only Klarna is supported. We need to call this like so because
    // most of woocommerce will not be initialized when we need this info. IOK 2024-05-28
    public function allow_external_payments_in_checkout() {
        $store_location=  wc_get_base_location();
        $store_country = $store_location['country'] ?? '';
        $finland = (get_woocommerce_currency() == "EUR" && $store_country == "FI");
        $norway = (get_woocommerce_currency() == "NOK" && $store_country == "NO");
        $sweden = (get_woocommerce_currency() == "SEK" && $store_country == "SE");
        return apply_filters('woo_vipps_allow_external_payment_methods', ($finland || $norway || $sweden));
    }

    public function init_form_fields() { 
        global $Vipps;

        // Used for defaults in the admin interface; however this functions is called a loot more often than that.
        $page_templates = $this->get_theme_page_templates();
        $page_list = $this->get_pagelist();

        $orderprefix = $Vipps->generate_order_prefix();

        // Default handling based on other parameters and earlier values.
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

        // We will only show the Vipps Checkout options if the user has activated the feature (thus creating the pages involved etc). IOK 2021-10-01
        $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);


        // This is used for new options,to set reasonable defaults based on older settings. We can't use WC_Settings->get_option for this unfortunately.
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

        // Get the already-set country code. For existing sites, this will guess the country based on the currency; for new sites, use 
        // the woo base country. IOK 2024-10-17 (previously used the currency here too).
        $countries = new WC_Countries(); // Can't use WC()->countries here - too early IOK 2024-10-17
        $country_code = $current['country'] ?? $countries->get_base_country();

        // Same issue as above: We need the default payment method name before it is set to be able to provide defaults IOK 2023-12-01
        $payment_method_name = $current['payment_method_name'] ?? $this->detect_default_payment_method_from_country($country_code);

        $checkoutfields = array(
                'checkout_options' => array(
                    'title' => "Checkout", // Vipps::CheckoutName(), // Don't translate this, but save some space IOK 2024-12-06
                    'type'  => 'title',
                    'class' => 'tab',
                    'description' => sprintf(__("%1\$s is a new service from %2\$s which replaces the usual WooCommerce checkout page entirely, replacing it with a simplified checkout screen providing payment both with %2\$s and credit card. Additionally, your customers will get the option of providing their address information using their %2\$s app directly.", 'woo-vipps'), Vipps::CheckoutName(), Vipps::CompanyName()),
                    ),


                'vipps_checkout_enabled' => array(
                    'title'       => sprintf(__('Activate Alternative %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                    'label'       => sprintf(__('Enable Alternative %1$s screen, replacing the standard checkout page', 'woo-vipps'), Vipps::CheckoutName()),
                    'type'        => 'checkbox',
                    'description' => sprintf(__('If activated, this will <strong>replace</strong> the standard Woo checkout screen with %1$s, providing easy checkout using %1$s or credit card, with no need to type in addresses.', 'woo-vipps'), Vipps::CheckoutName()),
                    'default'     => 'no',
                    ),

                'checkoutcreateuser' => array (
                        'title'       => sprintf(__('Create new customers on %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                        'label'       => sprintf(__('Create new customers on %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('Enable this to create and login customers when using %1$s. Otherwise these will all be guest checkouts. If using, you may want to install Login with Vipps too.', 'woo-vipps'), Vipps::CheckoutName()),
                        'default'     => $vippscreateuserdefault,
                        ),

            'enablestaticshipping_checkout' => array(
                        'title'       => sprintf(__('Enable static shipping for %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                        'label'       => __('Enable static shipping', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('If your shipping options do not depend on the customers address, you can enable \'Static shipping\', which will precompute the shipping options when using %1$s so that this will be much faster. If you do this and the customer isn\'t logged in, the base location of the store will be used to compute the shipping options for the order. You should only use this if your shipping is actually \'static\', that is, does not vary based on the customers address. So fixed price/free shipping will work. If the customer is logged in, their address as registered in the store will be used, so if your customers are always logged in, you may be able to use this too.', 'woo-vipps'), Vipps::CheckoutName()),
                        'default'     => $default_static_shipping_for_checkout
                        ),


                'requireUserInfo_checkout' => array(
                        'title'       => __('Ask the user to consent to share user information', 'woo-vipps'),
                        'label'       => __('Ask the user to consent to share user information', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('If using %1$s, ask for the users consent to share user information with the store. This will allow better integration between Login With %1$s but will add another step to first-time buyers.', 'woo-vipps'), Vipps::CompanyName()),
                        'default'     => 'no'
                        ),

                'noAddressFields' => array(
                        'title'       => __('Drop the address fields on the Checkout screen', 'woo-vipps'),
                        'label'       => __('Don\'t require the address fields', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => __('If your products <i>don\'t require shipping</i>, either because they are digital downloads, immaterial products or delivering the products directly on purchase, you can check this box. The user will then not be required to provide an address, which should speed things up a bit. If your products require shipping, this will have no effect. NB: If you have plugins that require shipping information, then this is not going to work very well.','woo-vipps'),
                        'default'     => 'no'
                    ),

                'noContactFields' => array(
                        'title'       => __('Drop the contact fields on the Checkout screen', 'woo-vipps'),
                        'label'       => __('Don\'t require the contact fields', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => __('If your products <i>don\'t require shipping</i> as above, and you also don\'t care about the customers name or contact information, you can drop this too! The customer fields will then be filled with a placeholder. NB: If you have plugins that require contact information, then this is not going to work very well. Also, for this to work you have to check the \'no addresses\' box as well.','woo-vipps'),
                        'default'     => 'no'
                    ),

      
                );

        $vipps_checkout_shipping_fields = array(

                'checkout_shipping' => array(
                    'title' => sprintf(__('%1$s Shipping Methods', 'woo-vipps'), Vipps::CheckoutName()),
                    'type'  => 'title',
                    'description' => sprintf(__("When using %1\$s, you have the option to use %1\$s specific shipping methods with extended features for certain carriers. These will add an apropriate logo as well as extended delivery options for certain methods. For some of these, you need to add integration data from the carriers below. You can then add these shipping methods to your shipping zones the normal way, but they will only appear in the %1\$s screen.", 'woo-vipps'), Vipps::CheckoutName())
                    ),

                'vcs_posten' => array(
                        'title'       => __('Posten Norge', 'woo-vipps'),
                        'class'       => 'vcs_posten vcs_main',
                        'custom_attributes' => array('data-vcs-show'=>'.vcs_depend.vcs_posten'),
                        'label'       => sprintf(__('Support Posten Norge as a shipping method in %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('Activate this for Posten Norge as a %1$s Shipping method.', 'woo-vipps'), Vipps::CheckoutName()),
                        'default'     => 'yes'
                    ),

                'vcs_posti' => array(
                        'title'       => __('Posti', 'woo-vipps'),
                        'class'       => 'vcs_posti vcs_main',
                        'custom_attributes' => array('data-vcs-show'=>'.vcs_depend.vcs_posti'),
                        'label'       => sprintf(__('Support Posti as a shipping method in %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('Activate this for Posti as a %1$s Shipping method.', 'woo-vipps'), Vipps::CheckoutName()),
                        'default'     => 'yes'
                    ),

                'vcs_postnord' => array(
                        'title'       => __('PostNord', 'woo-vipps'),
                        'class'       => 'vcs_postnord vcs_main',
                        'custom_attributes' => array('data-vcs-show'=>'.vcs_depend.vcs_postnord'),
                        'label'       => sprintf(__('Support PostenNord as a shipping method in %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('Activate this for PostNord as a %1$s Shipping method.', 'woo-vipps'), Vipps::CheckoutName()),
                        'default'     => 'yes'
                    ),

                'vcs_porterbuddy' => array(
                        'title'       => __('Porterbuddy', 'woo-vipps'),
                        'class'       => 'vcs_porterbuddy vcs_main',
                        'custom_attributes' => array('data-vcs-show'=>'.vcs_depend.vcs_porterbuddy'),
                        'label'       => sprintf(__('Support Porterbuddy as a shipping method in %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('Activate this for Porterbuddy as a %1$s Shipping method. Your store address will be used as the pick-up point and your admin email will be used for booking information from Porterbuddy.' ,'woo-vipps'), Vipps::CheckoutName()),
                        'default'     => 'no'
                    ),

                'vcs_porterbuddy_publicToken' => array(
                        'title' => __('Porterbuddy public token', 'woo-vipps'),
                        'class' => 'vippspw vcs_porterbuddy vcs_depend',
                        'type'        => 'password',
                        'description' => __('The public key provided to you by Porterbuddy','woo-vipps'),
                        'default'     => '',
                        ),
                'vcs_porterbuddy_apiKey' => array(
                        'title' => __('Porterbuddy API key', 'woo-vipps'),
                        'class' => 'vippspw vcs_porterbuddy vcs_depend',
                        'type'        => 'password',
                        'description' => __('The API key provided to you by Porterbuddy','woo-vipps'),
                        'default'     => '',
                        ),
                'vcs_porterbuddy_phoneNumber' => array(
                        'title' => __('Porterbuddy Phone Number', 'woo-vipps'),
                        'class' => 'vcs_porterbuddy vcs_depend',
                        'type'        => 'text',
                        'description' => __('Your phone number where Porterbuddy may send you important messages. Format must be MSISDN (including country code). Example: "4791234567"','woo-vipps'),
                        'default'     => '',
                        ),

                // Vipps checkout *shipping options* - extra shipping options that only work with Vipps Checkout
                'vcs_helthjem' => array(
                        'title'       => __('Helthjem', 'woo-vipps'),
                        'label'       => sprintf(__('Support Helthjem as a shipping method in %1$s', 'woo-vipps'), Vipps::CheckoutName()),
                        'type'        => 'checkbox',
                        'class' => 'vcs_helthjem vcs_main',
                        'custom_attributes' => array('data-vcs-show'=>'.vcs_depend.vcs_helthjem'),
                        'description' => sprintf(__('Activate this for Helthjem as a %1$s Shipping method.' ,'woo-vipps'), Vipps::CheckoutName()),
                        'default'     => 'no'
                    ),

                'vcs_helthjem_shopId' => array(
                        'title' => __('Helthjem Shop Id', 'woo-vipps'),
                        'class' => 'vcs_helthjem vcs_depend',
                        'type'        => 'number',
                        'custom_attributes' => array('pattern'=>'[0-9]'),
                        'description' => __('The ShopId provided to you by Helthjem','woo-vipps'),
                        'default'     => '',
                        ),

                'vcs_helthjem_username' => array(
                        'title' => __('Helthjem Username', 'woo-vipps'),
                        'class' => 'vcs_helthjem vcs_depend',
                        'type'        => 'text',
                        'description' => __('The Username provided to you by Helthjem','woo-vipps'),
                        'default'     => '',
                        ),
                'vcs_helthjem_password' => array(
                        'title' => __('Helthjem Password', 'woo-vipps'),
                        'class' => 'vippspw vcs_helthjem vcs_depend',
                        'type'        => 'password',
                        'description' => __('Password provided to you by Helthjem','woo-vipps'),
                        'default'     => '',
                        ),

                );

       /* Support for *certain* external payment methods in Vipps Checkout. IOK 2024-05-27  */
       $externals = [];
       $external_payment_fields = [];
       $allow_external_payments = $this->allow_external_payments_in_checkout();
       if ($allow_external_payments) {
           if (in_array('KCO_Gateway', Vipps::$installed_gateways) || in_array('WC_Gateway_Klarna_Payments', Vipps::$installed_gateways)) {
               $externals['checkout_external_payments_klarna'] = array(
                       'title' => __('Klarna', 'woo-vipps'),
                       'label'       => __('Klarna', 'woo-vipps'),
                       'type'        => 'checkbox',
                       'class' => 'external_payments klarna',
                       'description' => sprintf(__("Allow Klarna as an external payment method in %1\$s",'woo-vipps'), Vipps::CheckoutName()),
                       'default'     => 'no',
                       );
           }
           if (!empty($externals)) {
               $external_payment_fields = [
                   'checkout_external_payment_title' => array(
                           'title' => sprintf(__('External Payment Methods', 'woo-vipps'), Vipps::CheckoutName()),
                           'type'  => 'title',
                           'description' => sprintf(__("Allow certain external payment methods in %1\$s, returning control to WooCommerce for the order", 'woo-vipps'), Vipps::CheckoutName())
                   ),
               ];
               foreach($externals as $k => $def)   $external_payment_fields[$k] = $def;
           }
       }

       $vipps_checkout_widgets_fields = [
           'checkout_widgets' => [
               'title' => sprintf(__('%1$s widgets', 'woo-vipps'), Vipps::CheckoutName()),
               'type'  => 'title',
               'description' => sprintf(__('Widgets are elements shown above the %1$s frame with extra functionality.', 'woo-vipps'), Vipps::CheckoutName()),
           ],
           'checkout_widget_ordernotes' => [
               'title'       => __('Order notes', 'woo-vipps'),
               'label'       => __('Enable the order notes widget', 'woo-vipps'),
               'type'        => 'checkbox',
               'description' => __('A widget to add customer notes with their order.', 'woo-vipps'),
               'default'     => 'yes'
           ],
       ];
/* This seems to have broken some sites with OOM during plugin activation. 2025-05-27 */
//       if (wc_coupons_enabled()) {
           $vipps_checkout_widgets_fields['checkout_widget_coupon'] = [
               'title'       => __('Coupon code', 'woo-vipps'),
               'label'       => __('Enable the coupon code widget', 'woo-vipps'),
               'type'        => 'checkbox',
               'description' => __('A widget to activate coupon codes.', 'woo-vipps'),
               'default'     => 'yes'
           ];
//       }

        $mainfields = array(
            'main_options'             => array(
                'title' => __('Main options', 'woo-vipps'),
                'type'  => 'title',
                'class' => 'tab',
            ),
            'enabled' => array(
                'title'       => __('Enable/Disable', 'woocommerce'),
                'label'       => sprintf(__('Enable %1$s', 'woo-vipps'), Vipps::CompanyName()),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'country' => array(
                'title' => __('Country', 'woo-vipps'),
                'label' => __('Country', 'woo-vipps'),
                'type' => 'select',
                'options' => array(
                    'NO' => __('Norway', 'woo-vipps'),
                    'SE' => __('Sweden', 'woo-vipps'),
                    'FI' => __('Finland', 'woo-vipps'),
                    'DK' => __('Denmark', 'woo-vipps'),
                ),
                'description' => __('Select the country for this merchant serial number. This will determine the appropriate payment method (Vipps or MobilePay).', 'woo-vipps'),
                'default' => $country_code,
            ),

            'payment_method_name' => array(
                'title'       => __('Payment method', 'woo-vipps'),
                'label'       => __('Choose which payment method should be displayed to users at checkout', 'woo-vipps'),
                'type'        => 'select',
                'options' => array(
                    'Vipps' => __('Vipps','woo-vipps'),
                    'MobilePay' => __('MobilePay', 'woo-vipps'),
                ), 
                'description' => __('Choose which payment method should be displayed to users at checkout', 'woo-vipps'),
                'default'     => $payment_method_name,
            ),

            'orderprefix' => array(
                'title' => __('Order-id Prefix', 'woo-vipps'),
                'label'       => __('Order-id Prefix', 'woo-vipps'),
                'type'        => 'string',
                'description' => __('An alphanumeric textstring to use as a prefix on orders from your shop, to avoid duplicate order-ids','woo-vipps'),
                'default'     => $orderprefix
            ),
            'merchantSerialNumber' => array(
                'title' => __('Merchant Serial Number', 'woo-vipps'),
                'label'       => __('Merchant Serial Number', 'woo-vipps'),
                'type'        => 'number',
                'description' => __('Your "Merchant Serial Number" from the Developer tab on https://portal.vippsmobilepay.com','woo-vipps'),
                'default'     => '',
            ),
            'clientId' => array(
                'title' => __('Client Id', 'woo-vipps'),
                'class' => 'vippspw',
                'label'       => __('Client Id', 'woo-vipps'),
                'type'        => 'password',
                'description' => __('Find your account under the "Developer" tab on https://portal.vippsmobilepay.com/ and choose "Show keys". Copy the value of "client_id"','woo-vipps'),
                'default'     => '',
            ),
            'secret' => array(
                'title' => __('Client Secret', 'woo-vipps'),
                'label'       => __('Client Secret', 'woo-vipps'),
                'class' => 'vippspw',
                'type'        => 'password',
                'description' => __('Find your account under the "Developer" tab on https://portal.vippsmobilepay.com/ and choose "show keys". Copy the value of "client_secret"','woo-vipps'),
                'default'     => '',
            ),
            'Ocp_Apim_Key_eCommerce' => array(
                'title' => __('Subscription Key', 'woo-vipps'),
                'label'       => __('Subscription Key', 'woo-vipps'),
                'class' => 'vippspw',
                'type'        => 'password',
                'description' => __('Find your account under the "Developer" tab on https://portal.vippsmobilepay.com/ and choose "show keys". Copy the value of "Vipps-Subscription-Key"','woo-vipps'),
                'default'     => '',
            ),

            'result_status' => array(
                'title'       => sprintf(__('Order status on return from %1$s', 'woo-vipps'), Vipps::CompanyName()),
                'label'       => __('Choose default order status for reserved (not captured) orders', 'woo-vipps'),
                'type'        => 'select',
                'options' => array(
                    'processing' => __('Processing', 'woo-vipps'),
                    'on-hold' => __('On hold','woo-vipps'),
                ), 
                'description' => __('By default, orders that are <b>reserved</b> but not <b>yet captured</b> will now have the order status \'Processing\'. You can capture the sum manually, or by changing the status to \'Complete\'. You should ensure that your workflow is such that the order is not shipped until after this capture.<br><br> The status \'On hold\' can be chosen instead for stores using a workflow where orders are shipped when the status is \'Processing\'. In this case, \'On hold\' will mean "order is reserved but not yet captured".  This is a slightly safer solution, and ensures that the order status will reflect the payment status. <br><br>However, in many stores \'On hold\' has the additional meaning "there is a problem with the order"; and an email is often sent to the customer about this problem. The default is \'Processing\' because of this, and because many plugins and integrations expect orders to be \'Processing\' when the customer has completed payment.', 'woo-vipps'),
                'default'     => 'processing',
            ),

/*
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => sprintf(__('%1$s','woo-vipps'), $payment_method_name),
            ),
*/

            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default' => sprintf(__("Almost done! Remember, there are no fees using %1\$s when shopping online.", 'woo-vipps'), Vipps::CompanyName())
            ),

            'vippsdefault' => array(
                'title'       => sprintf(__('Use %1$s as default payment method on checkout page', 'woo-vipps'), $payment_method_name),
                'label'       => sprintf(__('%1$s is default payment method', 'woo-vipps'), $payment_method_name),
                'type'        => 'checkbox',
                'description' => sprintf(__('Enable this to use %1$s as the default payment method on the checkout page, regardless of order.', 'woo-vipps'), $payment_method_name),
                'default'     => 'yes',
            ),

        );

         $expressfields = array(  
                'express_options' => array(
                        'title' => sprintf(__('Express Checkout', 'woo-vipps')),
                        'type'  => 'title',
                        'class' => 'tab',
                        'description' => sprintf(__("%1\$s allows you to buy products by a single click from the cart page or directly from product or catalog pages. Product will get a 'buy now' button which will start the purchase process immediately.", 'woo-vipps'), Vipps::ExpressCheckoutName())
                        ),

                'cartexpress' => array(
                        'title'       => __('Enable Express Checkout in cart', 'woo-vipps'),
                        'label'       => __('Enable Express Checkout in cart', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('Enable this to allow customers to shop using %1$s directly from the cart with no login or address input needed', 'woo-vipps'), Vipps::ExpressCheckoutName()) . '.<br>' .
                        sprintf(__('Please note that for Express Checkout, shipping must be calculated in a callback from the %1$s app, without any knowledge of the customer. This means that Express Checkout may not be compatible with all Shipping plugins or setup. You should test that your setup works if you intend to provide this feature.', 'woo-vipps'), Vipps::CompanyName()),
                        'default'     => 'yes',
                        ),

                'singleproductexpress' => array(
                        'title'       => __('Enable Express Checkout for single products', 'woo-vipps'),
                        'label'       => __('Enable Express Checkout for single products', 'woo-vipps'),
                        'type'        => 'select',
                        'options' => array(
                            'none' => __('No products','woo-vipps'),
                            'some' => __('Some products', 'woo-vipps'),
                            'all' => __('All products','woo-vipps')
                            ), 
                        'description' => sprintf(__('Enable this to allow customers to buy a product using %1$s directly from the product page. If you choose \'some\', you must enable this on the relevant products', 'woo-vipps'), Vipps::ExpressCheckoutName()),
                        'default'     => 'none',
                        ),
                'singleproductexpressarchives' => array(
                        'title'       => __('Add \'Buy now\' button on catalog pages too', 'woo-vipps'),
                        'label'       => __('Add the button for all relevant products on catalog pages', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('If %1$s is enabled for a product, add the \'Buy now\' button to catalog pages too', 'woo-vipps'), Vipps::ExpressCheckoutName()),
                        'default'     => 'no',
                        ),
                'expresscheckout_termscheckbox' => array(
                        'title'       => sprintf(__('Add terms and conditions checkbox on %1$s', 'woo-vipps'), Vipps::ExpressCheckoutName()),
                        'label'       => sprintf(__('Always ask for confirmation on %1$s', 'woo-vipps'), Vipps::ExpressCheckoutName()),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('When using %1$s, ask the user to confirm that they have read and accepted the stores terms and conditons before proceeding', 'woo-vipps'), Vipps::ExpressCheckoutName()),
                        'default'     => 'no',
                        ),

                'expresscheckout_always_address' => array(
                        'title'       => __('Always ask for address, even if products don\'t need shipping', 'woo-vipps'),
                        'label'       => __('Always ask the user for their address, even if you don\'t need it for shipping', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => __('If the order contains only "virtual" products that do not need shipping, we do not normally ask the user for their address - but check this box to do so anyway.', 'woo-vipps'),
                        'default'     => $default_ask_address_for_express,
                ),

                'enablestaticshipping' => array(
                        'title'       => __('Enable static shipping for Express Checkout', 'woo-vipps'),
                        'label'       => __('Enable static shipping', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => __('If your shipping options do not depend on the customers address, you can enable \'Static shipping\', which will precompute the shipping options when using Express Checkout so that this will be much faster. If you do this and the customer isn\'t logged in, the base location of the store will be used to compute the shipping options for the order. You should only use this if your shipping is actually \'static\', that is, does not vary based on the customers address. So fixed price/free shipping will work. If the customer is logged in, their address as registered in the store will be used, so if your customers are always logged in, you may be able to use this too.', 'woo-vipps'),
                        'default'     => 'no',
                        ),


                'expresscreateuser' => array (
                        'title'       => __('Create new customers on Express Checkout', 'woo-vipps'),
                        'label'       => __('Create new customers on Express Checkout', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => sprintf(__('Enable this to create and login new customers when using express checkout. Otherwise these will all be guest checkouts. If you have "Login with Vipps" installed, this will be the default (unless you have turned off user creation in WooCommerce itself)', 'woo-vipps'), Vipps::CompanyName()),
                        'default'     => $expresscreateuserdefault,
                        ),
                'singleproductbuynowcompatmode' => array(
                        'title'       => __('"Buy now" compatibility mode', 'woo-vipps'),
                        'label'       => __('Activate compatibility mode for all "Buy now" buttons', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => __('Choosing this will use a different method of handling the "Buy now" button on a single product, which will work for more product types and more plugins - while being <i>slightly</i> less smooth. Use this if your product needs more configuration than simple or standard variable products', 'woo-vipps'),
                        'default'     => 'no',
                        ),


                'deletefailedexpressorders' => array(
                        'title'       => __('Delete failed Express Checkout Orders', 'woo-vipps'),
                        'label'       => __('Delete failed Express Checkout Orders', 'woo-vipps'),
                        'type'        => 'checkbox',
                        'description' => __('As Express Checkout orders are anonymous, failed orders will end up as "cancelled" orders with no information in them. Enable this to delete these automatically when cancelled - but test to make sure no other plugin needs them for anything.', 'woo-vipps'),
                        'default'     => 'no',
                        )
        );
        // New shipping in express checkout is available, but merchant has overridden the old shipping callback. Ask what to do! IOK 2020-02-12
       if (has_action('woo_vipps_shipping_methods')) {
            $shippingoptions = array(
                    'newshippingcallback' => array(
                        'title'       => __('Use old-style shipping callback for express checkout', 'woo-vipps'),
                        'label'       => __('Use your current shipping filters', 'woo-vipps'),
                        'type'        => 'select',
                        'options' => array(
                            'none' => __('Select one','woo-vipps'),
                            'old' => __('Keep using old shipping callback with my custom filter', 'woo-vipps'),
                            'new' => __('Use new shipping callback','woo-vipps')
                            ),
                        'description' => sprintf(__('Since version 1.4 this plugin uses a new method of providing shipping methods to %1$s when using Express Checkout. The new method supports metadata in the shipping options, which is neccessary for integration with Bring, Postnord etc. However, the new method is not compatible with the old <code>\'woo_vipps_shipping_methods\'</code> filter, which your site has overridden in a theme or plugin. If you want to, you can continue using this filter and the old method. If you want to disable your filters and use the new method, you can choose this here. ', 'woo-vipps'), Vipps::CompanyName()),
                        'default'     => 'none',
                        )
                    );
              $expressfields = array_merge(array_slice($expressfields ,0,1), $shippingoptions, array_slice($expressfields,1));
       }

       $advancedfields = array(
               'advanced_options' => array(
                   'title' => __('Advanced', 'woo-vipps'),
                   'type'  => 'title',
                    'class' => 'tab',
                   'description' => __("If you have issues with your theme, you might find a setting here that will help. Normally you do not need to change these.", 'woo-vipps')
                   ),

                 'vippsorderattribution' => array(
                     'title'       => __( 'Support WooCommerces Order Attribution API for Checkout and Express Checkout', 'woo-vipps' ),
                     'label'       => __( 'Add support for Order Attribution', 'woo-vipps' ),
                     'type'        => 'checkbox',
                     'default'=> 'no',
                     'description' => __('Turn this on to add support for Woos Order Attribution API for Checkout and Express Checkout. Some stores have reported problems when using this API together with Vipps, so be sure to test this if you turn it on.', 'woo-vipps'),
),

                 'vippsspecialpagetemplate' => array(
                     'title'       => sprintf(__('Override page template used for the special %1$s pages', 'woo-vipps'), Vipps::CompanyName()),
                     'label'       => sprintf(__('Use specific template for %1$s', 'woo-vipps'), Vipps::CompanyName()),
                     'type'        => 'select',
                     'options' =>  $page_templates,
                     'description' => sprintf(__('Use this template from your theme or child-theme to display all the special %1$s pages. You will probably want a full-width template and it should call \'the_content()\' normally.', 'woo-vipps'), Vipps::CompanyName()),
                     'default' => ''),

                 'vippsspecialpageid' =>  array(
                     'title' => sprintf(__('Use a real page ID for the special %1$s pages - neccessary for some themes', 'woo-vipps'), Vipps::CompanyName()),
                     'label' => __('Use a real page ID', 'woo-vipps'),
                     'type'  => 'select',
                     'options' => $page_list,
                     'description' => sprintf(__('Some very few themes do not work with the simulated pages used by this plugin, and needs a real page ID for this. Choose a blank page for this; the content will be replaced, but the template and other metadata will be present. You only need to use this if the plugin seems to break on the special %1$s pages.', 'woo-vipps'), Vipps::CompanyName()),
                     'default'=>''),

                'sendreceipts' => array(
                     'title' => __("Send receipts and order confirmation info to the customers' app on completed purchases.", 'woo-vipps'),
                      'label' => sprintf(__("Send receipts to the customers %1\$s app", 'woo-vipps'), Vipps::CompanyName()),
                      'type'        => 'checkbox',
                      'description' => sprintf(__("If this is checked, a receipt will be sent to %1\$s which will be viewable in the users' app, specifying the order items, shipping et cetera", 'woo-vipps'), Vipps::CompanyName()),
                      'default' => 'yes'
                ),

                'receiptimage' => array (
                        'title'       => sprintf(__('Use this image for the order confirmation link uploaded to the customers\' %1$s app', 'woo-vipps'), Vipps::CompanyName()),
                        'label'       => sprintf(__('Profile image used in the %1$s App', 'woo-vipps'), Vipps::CompanyName()),
                        'type'        => 'woo_vipps_image',
                        'description' => sprintf(__('If set, this image will be uploaded to %1$s and used to profile your store in the %1$s app for links to the order confirmation etc', 'woo-vipps'), Vipps::CompanyName()),
                        'default'     => 0,
                        ),


                'use_flock' => array (
                            'title'       => __('Use flock() to lock orders for Express Checkout', 'woo-vipps'),
                            'label'       => __('Use flock() to lock orders for Express Checkout', 'woo-vipps'),
                            'type'        => 'checkbox',
                            'description' => __('Use the flock() system call to ensure orders are only finalized once. You can use this for normal setups, but probably not on Windows with IIS, and possibly not on distributed filesystems like NFS. If you don\t know what it is, probably do not use it. If you get duplicated shipping lines on some express orders, you may try using this', 'woo-vipps'),
                            'default'     => 'no',
                            ),

                 'developermode' => array ( // DEVELOPERS! DEVELOPERS! DEVELOPERS! DEVE
                     'title'       => __('Enable developer mode', 'woo-vipps'),
                     'label'       => __('Enable developer mode', 'woo-vipps'),
                     'type'        => 'checkbox',
                     'description' => __('Enable this to enter developer mode. This gives you access to the test-api and sometimes other tools not yet ready for general consumption', 'woo-vipps'),
                     'default'     => VIPPS_TEST_MODE ? 'yes' : 'no',
                     ) 


       );

       $developerfields = array(
            'developertitle' => array(
                'title' => __('Developer mode', 'woo-vipps'),
                'type'  => 'title',
                'class' => 'tab',
                'description' => __('These are settings for developers that contain extra features that are normally not useful for regular users, or are not yet ready for primetime', 'woo-vipps'),
                ),

            'testmode' => array(
                'title' => __('Test mode', 'woo-vipps'),
                'label' => __('Enable test mode', 'woo-vipps'),
                'type'  => 'checkbox',
                'description' => sprintf(__('If you enable this, transactions will be made towards the %1$s Test API instead of the live one. No real transactions will be performed. You will need to fill out your test
                    accounts keys below, and you will need to install a special test-mode app from Testflight on a device (which cannot run the regular %1$s app). Contact %1$s\'s technical support if you need this. If you turn this mode off, normal operation will resume. If you have the VIPPS_TEST_MODE defined in your wp-config file, this will override this value. ', 'woo-vipps'), Vipps::CompanyName()),
                'default'     => VIPPS_TEST_MODE ? 'yes' : 'no',
                ),
            'merchantSerialNumber_test' => array(
                'title' => __('Merchant Serial Number', 'woo-vipps'),
                'class' => 'vippspw',
                'label'       => __('Merchant Serial Number', 'woo-vipps'),
                'type'        => 'number',
                'description' => __('Your test account "Merchant Serial Number" from the Developer tab on https://portal.vippsmobilepay.com','woo-vipps'),
                'default'     => '',
                ),
            'clientId_test' => array(
                    'title' => __('Client Id', 'woo-vipps'),
                    'label'       => __('Client Id', 'woo-vipps'),
                    'type'        => 'password',
                    'class' => 'vippspw',
                    'description' => __('Find your test account under the "Developer" tab on https://portal.vippsmobilepay.com/ and choose "Show keys". Copy the value of "client_id"','woo-vipps'),
                    'default'     => '',
                    ),
            'secret_test' => array(
                    'title' => __('Client Secret', 'woo-vipps'),
                    'label'       => __('Client Secret', 'woo-vipps'),
                    'type'        => 'password',
                    'class' => 'vippspw',
                    'description' => __('Find your test account under the "Developer" tab on https://portal.vippsmobilepay.com/ and choose "show keys". Copy the value of "client_secret"','woo-vipps'),
                    'default'     => '',
                    ),
            'Ocp_Apim_Key_eCommerce_test' => array(
                    'title' => __('Subscription Key', 'woo-vipps'),
                    'label'       => __('Subscription Key', 'woo-vipps'),
                    'type'        => 'password',
                    'class' => 'vippspw',
                    'description' => __('Find your test account under the "Developer" tab on https://portal.vippsmobilepay.com/ and choose "show keys". Copy the value of "Vipps-Subscription-Key"','woo-vipps'),
                    'default'     => '',
                    ),
            );
        
       // Add all the standard fields
       foreach($mainfields as $key=>$field) {
          $this->form_fields[$key] = $field;
       }
       foreach($expressfields as $key=>$field) {
          $this->form_fields[$key] = $field;
       }
       foreach($checkoutfields as $key=>$field) {
               $this->form_fields[$key] = $field;
       }


       foreach($external_payment_fields as $key=>$field) {
               $this->form_fields[$key] = $field;
       }

       foreach($vipps_checkout_widgets_fields as $key=>$field) {
               $this->form_fields[$key] = $field;
       }

       foreach($vipps_checkout_shipping_fields as $key=>$field) {
               $this->form_fields[$key] = $field;
       }
       foreach($advancedfields as $key=>$field) {
          $this->form_fields[$key] = $field;
       }

       // The react UI decides whether or not to show the developer fields, however we always have to send this data to the client
       // because otherwise the react UI will not be able to show the correct translations, since they would be missing.
       foreach($developerfields as $key=>$field) {
          $this->form_fields[$key] = $field;
       }
       // Developer mode settings: Only shown when active. IOK 2019-08-30
       if ($this->get_option('developermode') == 'yes' || VIPPS_TEST_MODE) {
           if (VIPPS_TEST_MODE) {
               $this->form_fields['developermode']['description'] .= '<br><b>' . __('VIPPS_TEST_MODE is set to true in your configuration - dev mode is forced', 'woo-vipps') . "</b>";
               $this->form_fields['testmode']['description'] .= '<br><b>' . __('VIPPS_TEST_MODE is set to true in your configuration - test mode is forced', 'woo-vipps') . "</b>";
           }
       }


    }


    // IOK 2018-04-18 utilities for the 'admin notices' interface.
    private function adminwarn($what) {
        add_action('vipps_admin_notices',function() use ($what) {
                echo "<div class='notice notice-warning is-dismissible'><p>$what</p></div>";
                });
    }
    private function adminerr($what) {
        add_action('vipps_admin_notices',function() use ($what) {
                echo "<div class='notice notice-error is-dismissible'><p>$what</p></div>";
                });
    }
    private function adminnotify($what) {
        add_action('vipps_admin_notices',function() use ($what) {
                echo "<div class='notice notice-info is-dismissible'><p>$what</p></div>";
                });
    }

    // Only be available if current currency is NOK IOK 2018-09-19
    // Only be available if current currency is supported NT 2023-12-04
    public function is_available() {
        // This is split into two functions to avoid triggering infinite recursion in filters that override this value below. IOK 2021-10-30
        $ok = $this->standard_is_available();
        $ok = apply_filters('woo_vipps_is_available', $ok, $this);
        return $ok; 
    }

    // True if the alternative Vipps Checkout screen is both available and activated. Returns the page id of the checkout
    // page for convenience. IOK 2021-10-01
    public function vipps_checkout_available () {

        if ($this->get_option('vipps_checkout_enabled') != 'yes') return false;
        if (!$this->standard_is_available()) return false;

        $checkoutid = wc_get_page_id('vipps_checkout');
        if (!$checkoutid) return false;
      
        // Page doesn't exist anymore 
        if (! get_post_status($checkoutid)) {
             delete_option('woocommerce_vipps_checkout_page_id');
             return false;
        }

        // Restrictions on cart are similar to express checkout, but not exactly the same. IOK 2024-01-11
        if (!$this->cart_supports_checkout()) return false;

        // Filter to false if you want to use the standard checkout for whatever reason
        return apply_filters('woo_vipps_checkout_available', $checkoutid, $this);
    }

    // Get supported currencies for payment method NT 2023-12-04
    public function get_supported_currencies($payment_method) {
        switch ($payment_method) {
            case 'Vipps': return array('NOK', 'SEK');
            case 'MobilePay': return array('DKK', 'EUR');
            default: return array();
        }
    }
    
    // Check if payment method supports currency. NT 2023-12-04
    public function payment_method_supports_currency($payment_method, $currency) {
        $ok = in_array($currency, $this->get_supported_currencies($payment_method));
        return $ok;
    }

    //  Basic unfiltered version of "can I use vipps" ? IOK 2021-10-01
    protected function standard_is_available () {
        if (!$this->can_be_activated()) return false;
        if (!parent::is_available()) return false;
        if (!$this->payment_method_supports_currency($this->get_payment_method_name(), get_woocommerce_currency())) return false;
        return true;
    }

    // True if the express checkout feature should be available 
    public function express_checkout_available() {
       if (! $this->is_available()) return false;
       $ok = true;
       $ok = apply_filters('woo_vipps_express_checkout_available', $ok, $this);
       return $ok;
    }

    // IOK 2018-04-20 Initiate payment at Vipps and redirect to the Vipps payment terminal.
    public function process_payment ($order_id) {
        global $woocommerce, $Vipps;
        if (!$order_id) return [];

        do_action('woo_vipps_before_process_payment',$order_id);

        // Get current merchant serial
        $msn = $this->get_merchant_serial();

        // Do a quick check for correct setup first - this is the most critical point IOK 2018-05-11 
        try {
            $at = $this->api->get_access_token($msn);
        } catch (Exception $e) {
            $this->log(sprintf(__('Could not get access token when initiating %1$s payment for order id:','woo-vipps'), $this->get_payment_method_name()) . $order_id .":\n" . $e->getMessage(), 'error');
            wc_add_notice(sprintf(__('Unfortunately, the %1$s payment method is currently unavailable. Please choose another method.','woo-vipps'), $this->get_payment_method_name()),'error');
            return [];
        }


        // From the request, get either    [billing_phone] =>  or [vipps phone]
        $phone = '';
        if (isset($_POST['vippsphone'])) {
            $phone = trim(sanitize_text_field($_POST['vippsphone']));
        }
        if (!$phone && isset($_POST['billing_phone'])) {
            $phone = trim(sanitize_text_field($_POST['billing_phone']));
        }

        // This is for express checkout if we know the customers' phone.
        // thanks to sOndre @ github for reporting , https://github.com/vippsas/vipps-woocommerce/issues/22
        if (!$phone && WC()->customer) {
            $phone = WC()->customer->get_billing_phone();
        }

        // No longer the case for V2 of the API
        if (false && !$phone) {
            wc_add_notice(sprintf(__('You need to enter your phone number to pay with %1$s','woo-vipps'), $this->get_payment_method_name()) ,'error');
            return [];
        }

        $order = wc_get_order($order_id);
        $content = null;

        // Should be impossible, but there we go IOK 2022-04-21
        if (! $order->has_status('pending', 'failed')) {
             $this->log(sprintf(__("Trying to start order %1\$s with status %2\$s - only 'pending' and 'failed' are allowed, so this will fail", 'woo-vipps'), $order_id, $order->get_status()));
             wc_add_notice(sprintf(__('This order cannot be paid with %1$s - please try another payment method or try again later', 'woo-vipps'), $this->get_payment_method_name()), 'error');
             return [];
        }

        // If the Vipps order already has an init-timestamp, we should *not* call init_payment again,
        // in the *normal* case, this is a user who have lost their vipps session, so it suffices to 
        // just return the stored vipps session URL (eg. the user used the Back button.) If abandoned, the
        // order will eventually be cancelled. Changes in the cart will result in a new order anyway.
        if ($order->get_meta('_vipps_init_timestamp')) {
           $oldurl = $order->get_meta('_vipps_orderurl');
           $oldstatus = $order->get_meta('_vipps_status');

           // This isn't actually an expired session but it is the same logic; so we'll keep the
           // text in this 'can't happen' branch
           if (!$oldurl) {
              $this->log(sprintf(__("Order %2\$d was attempted restarted, but had no %1\$s session url stored. Cannot continue!", 'woo-vipps'), Vipps::CompanyName(), $order_id), 'error');
              wc_add_notice(sprintf(__('Order session expired at %1$s, please try again!', 'woo-vipps'), Vipps::CompanyName()), 'error');
              $order->update_status('cancelled', sprintf(__('Cannot restart order at %1$s', 'woo-vipps'), Vipps::CompanyName()));
              return [];
           }

           $order->add_order_note(sprintf(__('%1$s payment restarted','woo-vipps'), $this->get_payment_method_name()));

           return array('result'=>'success','redirect'=>$oldurl);
        }


        // This is needed to ensure that the callbacks from Vipps have access to the customers' session which is important for some plugins.  IOK 2019-11-22
        $this->save_session_in_order($order);

        // Vipps-terminal-page return url to poll/await return
        $returnurl= $Vipps->payment_return_url();
        // If we are using express checkout, use this to handle the address stuff
        // IOK 2018-11-19 also when *not* using express checkout. This allows us to pass the order-id in the return URL and use this as a password in case the sesson has been lost.
        $authtoken = $this->generate_authtoken();

        // IOK 2019-11-19 We have to do this because even though we actually store the order ID in the session, we can a) be redirected to another browser than the one with
        // the session, and b) some plugins wipe the session for guest purchases. 
        // So we might need to restore (enough of the) session to get to the than you page, even if the session is gone
        // or in another castle.
        // IOK 2023-01-23 store the limited session in the order instead and separeted it from the authtoken
        $limited_session = $this->generate_authtoken();
        $returnurl = add_query_arg('ls',$limited_session,$returnurl);
        $returnurl = add_query_arg('id', $order_id, $returnurl);


        try {
            // If the order was 'failed', it isnt any more! yet!
            if ($order->get_status() == 'failed') {
               $order->set_status('pending', __('Setting order status to pending to start payment', 'woo-vipps'));
            }
            // The requestid is actually for replaying the request, but I get 402 if I retry with the same Orderid.
            // Still, if we want to handle transient error conditions, then that needs to be extended here (timeouts, etc)
            $requestid = $order->get_order_key();
            $content =  $this->api->epayment_initiate_payment($phone,$order,$returnurl,$authtoken,$requestid);
        } catch (TemporaryVippsApiException $e) {
            $this->log(sprintf(__('Could not initiate %1$s payment','woo-vipps'), $this->get_payment_method_name()) . ' ' . $e->getMessage(), 'error');
            wc_add_notice(sprintf(__('Unfortunately, the %1$s payment method is temporarily unavailable. Please wait or choose another method.','woo-vipps'), $this->get_payment_method_name()),'error');
            return [];
        } catch (Exception $e) {

            // Special case the "duplicate order id" thing to ensure it doesn't happen again, and if it does, at least
            // log some more info IOK 2022-11-02
            if (preg_match("/Duplicate Order Id/i", $e->getMessage())) {
               do_action('woo_vipps_duplicate_order_id', $order);
               $this->log(sprintf(__("Duplicate Order ID! Please report this to support@wp-hosting.no together with as much info about the order as possible. Express: %1\$s Status: %2\$s User agent: %3\$s", 'woo-vipps'), $order->get_meta('_vipps_express_checkout'), $order->get_status(), $order->get_customer_user_agent()), 'error');
               $order->update_status('cancelled', __('Cannot restart order with same order ID: Must cancel', 'woo-vipps'));
            }

            $this->log(sprintf(__('Could not initiate %1$s payment','woo-vipps'), $this->get_payment_method_name()) . ' ' . $e->getMessage(), 'error');
            wc_add_notice(sprintf(__('Unfortunately, the %1$s payment method is currently unavailable. Please choose another method.','woo-vipps'), $this->get_payment_method_name()),'error');
            return [];
        }

        $url = $content['url'];
        $vippstamp = time();

        // Ensure we only check the status by ajax of our own orders. IOK 2018-05-03
        $sessionorders= WC()->session->get('_vipps_session_orders');
        $sessionorders[$order_id] = 1;
        WC()->session->set('_vipps_session_orders',$sessionorders);
        WC()->session->set('_vipps_pending_order',$order_id); // Send information to the 'please confirm' screen IOK 2018-04-24

        $order = wc_get_order($order_id);
        if ($authtoken) {
            $order->update_meta_data('_vipps_authtoken',wp_hash_password($authtoken));
        }
        if ($limited_session) {
            $order->update_meta_data('_vipps_limited_session',wp_hash_password($limited_session));
        }
        // Store the "session URL" for restarts of the order in the same session context. IOK 2022-11-02 
        $order->update_meta_data('_vipps_init_timestamp',$vippstamp);
        $order->update_meta_data('_vipps_orderurl', $url);
 
        $order->update_meta_data('_vipps_status','INITIATE'); // INITIATE right now
        $order->add_order_note(sprintf(__('%1$s payment initiated','woo-vipps'), $this->get_payment_method_name()));
        $order->add_order_note(sprintf(__('Awaiting %1$s payment confirmation','woo-vipps'), $this->get_payment_method_name()));
        $order->save();

        // Create a signal file that we can check without calling wordpress to see if our result is in IOK 2018-05-04
        try {
            $Vipps->createCallbackSignal($order);
        } catch (Exception $e) {
            // Could not create a signal file, but that's ok.
        }

        do_action('woo_vipps_before_redirect_to_vipps',$order_id);

        // This will send us to a receipt page where we will do the actual work. IOK 2018-04-20
        return array('result'=>'success','redirect'=>$url);
    }


    // This tries to capture a Vipps payment, and resets the status to 'on-hold' if it fails.  IOK 2018-05-07
    public function maybe_capture_payment($orderid) {
        $order = wc_get_order($orderid);
        if ('vipps' != $order->get_payment_method()) return false;
        $ok = 0;

        # Shortcut orders that have been directly captured
        $vippsstatus = $order->get_meta('_vipps_status');
        if ($vippsstatus == 'SALE') {
            return true;
        }

        $remaining = intval($order->get_meta('_vipps_capture_remaining'));

        // Somehow the order status in payment_complete has been set to the 'after order status' or 'complete' by a filter. If so, do not capture.
        // Capture will be done *before* payment_complete if appropriate IOK 2020-09-22
        if (did_action('woocommerce_pre_payment_complete')) {
            if (!$order->needs_processing()) return; // This is fine, we've captured.
        if ($remaining>0) {
                    // Not everything has been captured, but we have reached a capturable status. Complain, do not capture. IOK 2020-09-22
            $this->log(sprintf(__("Filters are setting the payment_complete order status to '%1\$s' - will not capture", 'woo-vipps'), $order->get_status()),'debug');
            $order->add_order_note(sprintf(__('Payment complete set status to "%1$s" - will not capture payments automatically','woo-vipps'), $order->get_status()));
            return false;
        }
        }

        // IOK 2019-10-03 it is now possible to do capture via other tools than Woo, so we must now first check to see if 
        // the order is capturable by getting full payment details.
        try {
                $order = $this->update_vipps_payment_details($order); 
       } catch (Exception $e) {
                //Do nothing with this for now
                $this->log(__("Error getting payment details before doing capture: ", 'woo-vipps') . $e->getMessage(), 'warning');
        }


        try {
            $ok = $this->capture_payment($order);
        } catch (Exception $e) {
            // This is handled in sub-methods so we shouldn't actually hit this IOK 2018-05-07 
        } 
        if ($ok) {
            // Signal other hooked actions that this one actually did something. IOK 2025-02-04
            $order->update_meta_data('_vipps_capture_complete',true);
            $order->save();
        } else  {
            $msg = sprintf(__("Could not capture %1\$s payment for this order!", 'woo-vipps'), $this->get_payment_method_name());
            $order->add_order_note($msg);
            $order->save();
            if (apply_filters('woo_vipps_on_hold_on_failed_capture', true, $order)) {
                $msg = sprintf(__("Could not capture %1\$s payment - status set to", 'woo-vipps'), $this->get_payment_method_name()) . ' ' . __('on-hold','woocommerce');
                $order->set_status('on-hold',$msg);
                $order->save();
                global $Vipps;
                $this->adminerr($msg);
                $Vipps->store_admin_notices();
                return false;
            } 
        }
    }


    // Capture (possibly partially) the order. Only full capture really supported by plugin at this point. IOK 2018-05-07
    // Except that we *do* note that money "refunded" through vipps before capture should be "uncapturable". IOK 2024-11-25
    public function capture_payment($order) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $this->log(sprintf(__('Trying to capture payment on order not made by %1$s:','woo-vipps'), $this->get_payment_method_name()). ' ' . $order->get_id(), 'error');
            $this->adminerr(sprintf(__('Cannot capture payment on orders not made by %1$s','woo-vipps'), $this->get_payment_method_name()));
            return false;
        }

        // Partial capture can happen if the order is edited IOK 2017-12-19
        $captured = intval($order->get_meta('_vipps_captured'));
        $vippsstatus = $order->get_meta('_vipps_status');
        $noncapturable = intval($order->get_meta('_vipps_noncapturable'));  // This money has been marked as not-to-be-captured. It will be cancelled on complete.

        // Ensure 'SALE' direct captured orders work
        if (!$captured && $vippsstatus == 'SALE') { 
            $order = $this->update_vipps_payment_details($order); 
            $captured = intval($order->get_meta('_vipps_captured'));
        }

        $total = round(wc_format_decimal($order->get_total(),'')*100);
        $amount = $total-$captured-$noncapturable; // IOK subtract any amount not to be captured here

        if ($amount<=0) {
            $order->add_order_note(__('Payment already captured','woo-vipps'));
            return true;
        }

        // If we already have captured everything, then we are ok! IOK 2017-05-07
        if ($captured) {
            $remaining = intval($order->get_meta('_vipps_capture_remaining'));
            if (!$remaining) {
                $order->add_order_note(__('Payment already captured','woo-vipps'));
                return true;
            }
        }

        // Each time we succeed, we'll increase the 'capture' transaction id so we don't just capture the same amount again and again. IOK 2018-05-07
        // (but on failre, we don't increase it - and also, we don't really support partial capture yet.) IOK 2018-05-07
        $requestidnr = intval($order->get_meta('_vipps_capture_transid'));
        // IOK 2023-03-13 keep track of failed captures; because some stores automate capturing without paying attention to the result.
        //  some reservations are for only 7 days (or 30 days or 180 days) so some orders will be uncapturable. This will be reset by the
        //  'Show full transaction details' metabox.
        $failures = intval($order->get_meta('_vipps_capture_failures'));
        $failurelimit = 10;
        try {

            // Assume we cannot capture if we have gotten errors 10 times from the API
            if ($failures >= $failurelimit) {
                throw new Exception(sprintf(__("More than %1\$d API exceptions trying to capture order - this order cannot be captured.", 'woo-vipps'), $failures));
            }

            $requestid = $requestidnr . ":" . $order->get_order_key();
            $api = $order->get_meta('_vipps_api');


            if ($api == 'banktransfer') {
                // This is an error - we should not ever get to the 'capture' branch if we are a banktransfer payment.
                // IOK 2024-01-09
                $content = [];
            } elseif ($api == 'epayment') {
                $content =  $this->api->epayment_capture_payment($order,$amount,$requestid);
            } else {
                $content =  $this->api->capture_payment($order,$amount,$requestid);
            }
        } catch (TemporaryVippsApiException $e) {
            $this->log(sprintf(__('Could not capture %1$s payment for order id:', 'woo-vipps'), $this->get_payment_method_name()) . ' ' . $order->get_id() . "\n" .$e->getMessage(),'error');
            $this->adminerr(sprintf(__('%1$s is temporarily unavailable.','woo-vipps'), $this->get_payment_method_name()) . "\n" . $e->getMessage());
            return false;
        } catch (Exception $e) {
            // Keep track of API failures up to a point. IOK 2024-03-13
            if ($failures < $failurelimit) { 
                $order->update_meta_data('_vipps_capture_failures', $failures + 1);
                $order->save();
            }

            $msg = sprintf(__('Could not capture %1$s payment for order_id:','woo-vipps'), $this->get_payment_method_name()) . ' ' . $order->get_id() . "\n" . $e->getMessage();
            $this->log($msg,'error');
            $this->adminerr($msg);
            return false;
        }

        $currency = $order->get_currency();

        // Previously, we got this from the transactionInfo field of the Vipps data - this is no longer provided. IOK 2025-08-12 
        //  We simply have to keep track: There is no way of knowing what the correct values are here yet, as we only get these values async, after
        // the fact. 
        $captured = $amount + intval($order->get_meta('_vipps_captured'));
        $remaining = intval($order->get_meta('_vipps_amount')) - $captured - intval($order->get_meta('_vipps_cancelled'));
        $refundable = $captured - intval($order->get_meta('_vipps_refunded'));

        $order->update_meta_data('_vipps_captured', $captured);
        $order->update_meta_data('_vipps_capture_remaining', $remaining);
        $order->update_meta_data('_vipps_refund_remaining', $refundable);
        $order->update_meta_data('_vipps_capture_timestamp', time());
        $order->add_order_note(sprintf(__('%1$s Payment captured:','woo-vipps'), $this->get_payment_method_name()) . ' ' .  sprintf("%0.2f",$captured/100) . ' ' . $currency);


        // Since we succeeded, the next time we'll start a new transaction.
        $order->update_meta_data('_vipps_capture_transid', $requestidnr+1);
        $order->save();

        return true;
    }

    public function refund_superfluous_capture($order) {
        $status = $order->get_status();
        if ($status != 'completed') {
            $this->log(__('Cannot refund superfluous capture on non-completed order:','woo-vipps'). ' ' . $order->get_id(), 'error');
            $this->adminerr(__('Order not completed, cannot refund superfluous capture','woo-vipps'));
            return false;
        }

        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $this->log(sprintf(__('Trying to refund payment on order not made by %1$s:','woo-vipps'), $this->get_payment_method_name()). ' ' . $order->get_id(), 'error');
            $this->adminerr(sprintf(__('Cannot refund payment on orders not made by %1$s','woo-vipps'), $this->get_payment_method_name()));
            return false;
        }

        try {
                $order = $this->update_vipps_payment_details($order); 
        } catch (Exception $e) {
                //Do nothing with this for now
                $this->log(__("Error getting payment details before doing refund: ", 'woo-vipps') . $e->getMessage(), 'warning');
        }

        $total = round(wc_format_decimal($order->get_total(),'')*100);
        $captured = intval($order->get_meta('_vipps_captured'));
        $to_refund =  intval($order->get_meta('_vipps_refund_remaining'));
        $refunded = intval($order->get_meta('_vipps_refunded'));
        $superfluous = $captured-$total-$refunded;


        if ($captured <= $total) {
            return false;
        }
        $superfluous = $captured-$total-$refunded;
        if ($superfluous<=0) {
            return false;
        }
        $refundvalue = min($to_refund,$superfluous);

        $reason = __("The value of the order is less than the amount captured.", "woo-vipps");

        $ok = 0;
        $currency = $order->get_currency();
        try {
            $ok = $this->refund_payment($order,$refundvalue,'cents');
        } catch (TemporaryVippsApiException $e) {
            $this->log(sprintf(__('Could not refund %1$s payment for order id:', 'woo-vipps'), $this->get_payment_method_name()) . ' ' . $order->get_id() . "\n" .$e->getMessage(),'error');
            return new WP_Error('Vipps',sprintf(__('%1$s is temporarily unavailable.','woo-vipps'), $this->get_payment_method_name()) . ' ' . $e->getMessage());
        } catch (Exception $e) {
            $msg = sprintf(__('Could not refund %1$s payment','woo-vipps'), $this->get_payment_method_name()) . ' ' . $e->getMessage();
            $order->add_order_note($msg);
            $this->log($msg,'error');
            return new WP_Error('Vipps',$msg);
        }

        if ($ok) {
            $order->add_order_note($refundvalue/100 . ' ' . $currency . ' ' . sprintf(__(" refunded through %1\$s:",'woo-vipps'), $this->get_payment_method_name()) . ' ' . $reason);
        } 
        return $ok;
    }

    // Cancel (only completely) a reserved but not yet captured order IOK 2018-05-07
    public function cancel_payment($order) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $this->log(sprintf(__('Trying to cancel payment on order not made by %1$s:','woo-vipps'), $this->get_payment_method_name()). ' ' .$order->get_id(), 'error');
            $this->adminerr(sprintf(__('Cannot cancel payment on orders not made by %1$s','woo-vipps'), $this->get_payment_method_name()));
            return false;
        }
        // If we have captured the order, we can't cancel it. IOK 2018-05-07
        $captured = intval($order->get_meta('_vipps_captured'));
        if ($captured) {
            $msg = sprintf(__('Cannot cancel a captured %1$s transaction - use refund instead', 'woo-vipps'), $this->get_payment_method_name());
            $this->adminerr($msg);
            return false;
        }
        // We'll use the same transaction id for all cancel jobs, as we can only do it completely. IOK 2018-05-07
        // For epayment, partial cancellations will be possible. IOK 2022-11-12
        $api = $order->get_meta('_vipps_api');
        try {
            $requestid = "";
            $api = $order->get_meta('_vipps_api');
            if ($api == 'banktransfer') {
                // If we are here, and the order is somehow not captured, just do nothing. IOK 2024-01-09
                $content = [];
            } elseif ($api == 'epayment') {
                $requestid = 1;
                $content =  $this->api->epayment_cancel_payment($order,$requestid);
            } else {
                $content =  $this->api->cancel_payment($order,$requestid);
            }
        } catch (TemporaryVippsApiException $e) {
            $this->log(sprintf(__('Could not cancel %1$s payment for order_id:', 'woo-vipps'), $this->get_payment_method_name()) . ' ' . $order->get_id() . "\n" .$e->getMessage(),'error');
            $this->adminerr(sprintf(__('%1$s is temporarily unavailable.','woo-vipps'), $this->get_payment_method_name()) . ' ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $msg = sprintf(__('Could not cancel %1$s payment for order id:','woo-vipps'), $this->get_payment_method_name()) . $order->get_id() . "\n" . $e->getMessage();
            $this->log($msg,'error');
            $this->adminerr($msg);
            return false;
        }

        // the epay v2 API would return transactionInfo and Summary with the result, the new epayment api returns nothing.
        // Removed epay branch 2025-08-12 IOK
        $total = intval($order->get_meta('_vipps_amount'));
        $captured = intval($order->get_meta('_vipps_captured'));
#            $cancelled =  $amount + intval($order->get_meta('_vipps_cancelled');
        $cancelled = $total;
        $remaining = $total - $captured - $cancelled;

        // We need to assume it worked. Also, we can't do partial cancels yet, so just cancel everything.
        $order->update_meta_data('_vipps_cancel_timestamp',time());
        $order->update_meta_data('_vipps_cancelled', $cancelled);
        $order->update_meta_data('_vipps_cancel_remaining', $remaining);


        // Set status from Vipps, ignore errors, use statusdata if we have it.
        try {
        $status = $this->get_vipps_order_status($order);
        if ($status) $order->update_meta_data('_vipps_status',$status);
        $order->add_order_note(sprintf(__('%1$s Payment cancelled:','woo-vipps'), $this->get_payment_method_name()));
        $order->save();
        } catch (Exception $e)  {
        }
        return true;
        }

    // Refund (possibly partially) the captured order. IOK 2018-05-07
    // The caller must handle the errors.
    public function refund_payment($order,$amount=0,$cents=false) {
        $pm = $order->get_payment_method();
        if ($pm != 'vipps') {
            $msg = sprintf(__('Trying to refund payment on order not made by %1$s:','woo-vipps'), $this->get_payment_method_name()) . ' ' . $order->get_id();
            $this->log($msg,'error');
            throw new VippsAPIException($msg);
        }


        // If we haven't captured anything, we can't refund IOK 2017-05-07
        $captured = intval($order->get_meta('_vipps_captured'));
        if (!$captured) {
            $msg = sprintf(__('Trying to refund payment on %1$s payment not captured:','woo-vipps'), $this->get_payment_method_name()). ' ' .$order->get_id();
            $this->log($msg,'error');
            throw new VippsAPIException($msg);
        }

        // Each time we succeed, we'll increase the 'refund' transaction id so we don't just refund the same amount again and again. IOK 2018-05-07
        // (but on failre, we don't increase it.) IOK 2018-05-07
        $requestidnr = intval($order->get_meta('_vipps_refund_transid'));
        $requestid = $requestidnr . ":" . $order->get_order_key();

        $api = $order->get_meta('_vipps_api');
        if ($api == 'banktransfer') {
            $msg = sprintf(__("Cannot refund bank transfer order %1\$d", 'woo-vipps'), $order->get_id());
            $this->log($msg, 'error');
            throw new Exception($msg);
        } elseif ($api == 'epayment') {
            $content =  $this->api->epayment_refund_payment($order,$requestid,$amount,$cents);
        } else {
            $content =  $this->api->refund_payment($order,$requestid,$amount,$cents);
        }

        $currency = $order->get_currency();

        // Previously, we got updated transaction info in a transactionInfo field. this is no longer provided,
        // so we have to do dead reckoning. IOK 2025-08-12
        //  We simply have to keep track: There is no way of knowing what the correct values are here yet, as we only get these values async, after
        // the fact. 
        $captured = intval($order->get_meta('_vipps_captured'));

        if (!$amount) {
            $amount = wc_format_decimal($order->get_total(),'');
            $cents = false;
        }
        if ($amount && !$cents) {
            $amount = round($amount * 100);
        }

        $refunded_now  = $amount;
        $refunded = intval($order->get_meta('_vipps_refunded')) + $amount;
        $remaining = $captured - $refunded; 

        $order->update_meta_data('_vipps_refunded', $refunded);
        $order->update_meta_data('_vipps_refund_remaining', $remaining);
        $order->update_meta_data('_vipps_refund_timestamp', time());
        $order->add_order_note(sprintf(__('%1$s Payment Refunded:','woo-vipps'), $this->get_payment_method_name()) . ' ' .  sprintf("%0.2f",$refunded/100) . ' ' . $currency );

        // Since we succeeded, the next time we'll start a new transaction.
        $order->update_meta_data('_vipps_refund_transid', $requestidnr+1);
        $order->save();
        return true;
    }

    // Generate a one-time password for certain callbacks, with some backwards compatibility for PHP 5.6
    public function generate_authtoken($length=32) {
        $token="";
        if (function_exists('random_bytes')) {
            $token = bin2hex(random_bytes($length));
        } elseif  (function_exists('openssl_random_pseudo_bytes')) {
            $token = bin2hex(openssl_random_pseudo_bytes($length));
        } elseif (function_exists('mcrypt_create_iv')) {
            // These aren't "secure" but they are probably ok for this purpose. IOK 2018-05-18
            $indirect = 'mcrypt_create_iv'; // grep-based 7.2 compatibility checkers need to be worked around IOK 2018-10-24
            $token = bin2hex($indirect($length));
        } else {
            // Final fallback
            $token = bin2hex(md5(microtime() . ":" . mt_rand()));
        }

        return $token;
    }

    // Collapse several statuses to a known list IOK 2019-01-23
    // Statuses still in use are annotated. IOK 2025-08-12
    public function interpret_vipps_order_status($status) {
        switch ($status) { 
            case 'INITIATE': // legacy, used by us to indicate a fresh order
            case 'REGISTER':
            case 'REGISTERED':
            case 'CREATED': // Checkout, Epayment
                return 'initiated';
                break;
            case 'RESERVE':
            case 'RESERVED':
            case 'AUTHORISED':
            case 'AUTHORIZED': // Checkout, Epayment
            case 'CAPTURED': // Epayment
            case 'REFUNDED':   // epayment - this is probably authorized, because it will have had that state *before* it was refuned. IOK 2025-08-12
                return 'authorized';
                break;
            case 'SALE':
                return 'complete';
                break;
            case 'CANCEL':
            case 'CANCELLED': // Epayment
            case 'VOID':
            case 'AUTOREVERSAL':
            case 'AUTOCANCEL':
            case 'AUTO_CANCEL':
            case 'RESERVE_FAILED':
            case 'FAILED':
            case 'REJECTED':
            case 'TERMINATED': // Checkout, Epayment
            case 'ABORTED':   // Epayment
            case 'EXPIRED':   // Epayment
                return 'cancelled';
                break;
            }
         // Default should never happen,  but just to ensure we are in our enumeration
         return "initiated";
    }

    // This does not normally call Vipps, so if you need to refresh status, please use callback_check_order_status first. IOK 2019-01-23
    public function check_payment_status($order) {
        if (!$order) return 'cancelled';
        $status = $this->interpret_vipps_order_status($order->get_meta('_vipps_status'));
        // This can happen if the vipps status is set from the back end for instance. IOK 2020-08-14
        if ($order->get_status() == 'pending' && $status != 'initiated') {
           $this->callback_check_order_status($order);
           $order = wc_get_order($order->get_id()); // refresh to get the new status IOK 2021-01-20
           $status = $this->interpret_vipps_order_status($order->get_meta('_vipps_status'));
        }
        return $status;
    }

    // Called by callback_check_order_status and handle_callback to handle the situation where
    // the payment method has been set to something else *after* Vipps has gotten the order.
    // This happens very rarely for people who use Vipps as an external payment method in Klarna, so
    // we only do it for orders that match this. IOK 2023-02-03
    public function reset_erroneous_payment_method($order) {
        // This is only called by methods that are Vipps-specific, but still lets be careful not to touch other orders
        if ($order->get_payment_method() === "kco" && $order->get_meta("_vipps_orderid")) {
            $order->set_payment_method('vipps');

            $express = $order->get_meta('_vipps_express_checkout');
            $checkout = $order->get_meta('_vipps_checkout');
            $order->set_payment_method_title('Vipps');
            if ($express) $order->set_payment_method_title('Vipps Express Checkout');
            if ($checkout) $order->set_payment_method_title('Vipps Checkout');
            $order->save();

            $msg = sprintf(__("Payment method reset to %1\$s - it had been set to KCO while completing the order for %2\$d", 'woo-vipps'), $this->get_payment_method_name(), $order->get_id());
            $this->log($msg, 'debug');
            $order->add_order_note($msg);
        }
    }

    // Check status of order at Vipps, in case the callback has been delayed or failed.   
    // Should only be called if in status 'pending'; it will modify the order when status changes.
    public function callback_check_order_status($order) {
        global $Vipps;
        $orderid = $order->get_id();

        clean_post_cache($order->get_id());
        $order = wc_get_order($orderid); // Ensure a fresh copy is read.

        $oldstatus = $order->get_status();
        $newstatus = $oldstatus;

        if ($oldstatus != 'pending') return $oldstatus;

        // If we are in the process of getting a callback from vipps, don't update anything. Currently, Woo/WP has no locking mechanism,
        // and it isn't feasible to implement one portably. So this reduces somewhat the likelihood of races when this method is called 
        // and callbacks happen at the same time.
        if (!$Vipps->lockOrder($order)) {
            return $oldstatus;
        }

        $oldvippsstatus = $this->interpret_vipps_order_status($order->get_meta('_vipps_status'));
        $vippsstatus = "";

        /* Now read the payment details and update the order with the relevant values, finding the new Vipps status IOK 2021-01-20 */
        $paymentdetails = array();
        try {
            $paymentdetails = $this->get_payment_details($order);
            $newvippsstatus = $paymentdetails['status'];
            $vippsstatus = $this->interpret_vipps_order_status($newvippsstatus);

            // Failsafe for rare bug when using Klarna Checkout with Vipps as an external payment method
            // IOK 2024-01-09 ensure this is called only when order is complete/authorized
            if (in_array($vippsstatus, ['authorized', 'complete'])) {
                $this->reset_erroneous_payment_method($order);
            }

            $order->update_meta_data('_vipps_status',$newvippsstatus);

            // Extract order metadata from either Checkout or Epayment - set below IOK 2025-08-13
            if (!empty($paymentdetails)) {


                // checkout has a string, epayment has an array with upper case "type" and apparently, cardBin IOK 2025-08-12
                $paymentMethod = $paymentdetails['paymentMethod'] ?? "epayment";
                // After normalization, all APIs will have data here.
                $details = $paymentdetails['paymentDetails'];
                if (isset($details['paymentMethod'])) {
                    $paymentMethod = $details['paymentMethod'];
                }
                if (!is_string($paymentMethod)) {
                    // should be WALLET
                    $paymentMethod = $paymentMethod['type'] ?? "epayment";
                }
                $transaction = array();
                $transaction['timeStamp'] = date('Y-m-d H:i:s', time());
                $transaction['amount'] = $details['amount']['value'];
                $transaction['currency'] = $details['amount']['currency'];
                $transaction['status'] = $details['state'];
                $transaction['paymentmethod'] = $paymentMethod;
                $this->order_set_transaction_metadata($order, $transaction);
            }

        } catch (Exception $e) {
            $this->log(sprintf(__("Error getting payment details from %1\$s for order_id:",'woo-vipps'), $this->get_payment_method_name()) . $orderid . "\n" . $e->getMessage(), 'error');
            clean_post_cache($order->get_id());
            $Vipps->unlockOrder($order);
            return $oldstatus;
        }
        $order->save();

        $statuschange = 0;
        if ($oldvippsstatus != $vippsstatus) {
            $statuschange = 1;
        }
        if ($oldstatus == 'pending' && $vippsstatus != 'initiated') {
            $statuschange = 1; // Probably handled by case above always IOK 2025-08-13
        }

        // We have a completed order, but the callback haven't given us the payment details yet - so handle it.
        if ($statuschange && ($vippsstatus == 'authorized' || $vippsstatus=='complete') && $order->get_meta('_vipps_express_checkout')) {

            do_action('woo_vipps_express_checkout_get_order_status', $paymentdetails);
            $address_set = $order->get_meta('_vipps_shipping_set');

            if ($address_set) {
               // Callback has handled the situation, do nothing
            } elseif ($paymentdetails['shippingDetails'] ?? "") {
               // We need to set shipping details here
                $billing = isset($paymentdetails['billingDetails']) ? $paymentdetails['billingDetails'] : false;
                $this->set_order_shipping_details($order,$paymentdetails['shippingDetails'], $paymentdetails['userDetails'], $billing, $paymentdetails);
            } else {
                //  IN THIS CASE we actually need to cancel the order as we have no way of determining whose order this is.
                //  But first check to see if it has customer info! 
                // Cancel any orders where the Checkout session is dead and there is no address info available
                if (!$order->has_shipping_address() && !$order->has_billing_address()) {
                    $this->log(sprintf(__("No shipping details from %1\$s for express checkout for order id:",'woo-vipps'), $this->get_payment_method_name()) . ' ' . $orderid, 'error');
                    $sessiontimeout = time() - (60*90);
                    $then = intval($order->get_meta('_vipps_init_timestamp'));
                    if ($then < $sessiontimeout)  {
                        $this->log(sprintf(__("Order %2\$d has no address info and any %1\$s session is dead - have to cancel.", 'woo-vipps'), Vipps::CheckoutName(), $order->get_id()));
                        $order->update_status('cancelled', sprintf(__('Could not get address info for order from %1$s', 'woo-vipps'), Vipps::CompanyName()));
                        $order->save();
                    } else {
                        // NOOP - the vipps checkout session can still be active, so we need to let it be
                        $this->log(sprintf(__("No address information for order %2\$d, but there still might be an active %1\$s session for it, so do not cancel it.", 'woo-vipps'), Vipps::CheckoutName(), $order->get_id()));
                    }
                }
                clean_post_cache($order->get_id());
                $Vipps->unlockOrder($order);
                return $oldstatus; 
            }
        }

        if ($statuschange) {
            switch ($vippsstatus) {
                case 'authorized':
                    $this->payment_complete($order);
                    break;
                case 'complete':
                    $msg = sprintf(__('Payment captured directly at %1$s', 'woo-vipps'), $this->get_payment_method_name());
                    $msg = $msg . __(" - order does not need processing", 'woo-vipps');
                    $order->add_order_note($msg);
                    $order = $this->update_vipps_payment_details($order, $paymentdetails); 
                    $order->payment_complete();
                    break;
                case 'cancelled':
                    $order->update_status('cancelled', sprintf(__('Order failed or rejected at %1$s', 'woo-vipps'), Vipps::CompanyName()));
                    break;
            }
            $order->save();
            clean_post_cache($order->get_id());
            $newstatus = $order->get_status();
        }
        $Vipps->unlockOrder($order);
        return $newstatus;
    }

    // IOK 2020-01-20 Previously was just a debugging tool, then was used to update postmeta values. Now is used as the main source of info
    // about the order from Vipps; the previous side-effecting is now done by update_vipps_payment_details.
    // IOK 2021-11-24 Because of this, we need to handle 402 and 404 errors differently here now - these *are* results, meaning there is
    // no payment details because the order doesn't exist.
    // IOK 2022-01-19 And now, with the epayment API in use by checkout, we also need to use the poll api because the epayment API does not return user- and shipping data. We do get an order status though.
    // IOK 2025-08-12 And now, with epayment used for Express Checkout also, there *is* user- and shipping-data when using Express Checkout, so we can simplify. We still need to transform the data 
    //   so old hooks and filters can get the input they expect.
    public function get_payment_details($order) {
        $result = array();
        $checkout_session = $order->get_meta('_vipps_checkout_session');
        $express = $order->get_meta('_vipps_express_checkout');

        // IOK 2025-08-12: Three cases; either this is Checkout, in which case we need to get user/shipping-data from the checkout session,
        // or it is Express Checkout or normal payment, in which cases we just retrieve the payment from the epayment API - possibly containing user data
        if ($checkout_session) {
            try {
                $result = $this->api->checkout_get_session_info($order);

                if ($result == 'EXPIRED') {
                    $result = array('status'=>'CANCEL', 'state'=>'CANCEL'); 
                    return $result;
                }

                // For checkout, we are really handling *session states* which we map to *order states*, but sometimes 
                // these indicate a failed order.
                // 'state' can be missing for Checkout, first case is if this is because the session is invalid.
                // The sesssion states are # "SessionCreated" "PaymentInitiated" "SessionExpired" "PaymentSuccessful" "PaymentTerminated"
                if (isset($result['sessionState']) && ($result['sessionState'] == 'SessionTerminated' || $result['sessionState'] == 'SessionExpired')) {
                    $result['state'] = 'CANCEL';
                    $result['status'] = 'CANCEL';
                    $order->add_order_note(sprintf(__('%1$s Order with no order status, so session was never completed; setting status to cancelled', 'woo-vipps'), Vipps::CheckoutName()));
                    return $result;
                }

                // IOK 2023-12-14 at some point, apparently SessionStarted became SessionCreated
                if (isset($result['sessionState']) && ($result['sessionState'] == 'SessionStarted' || $result['sessionState'] == 'SessionCreated')) {
                    // We have no order info, only the session data (this is a checkout order). Therefore, assume it has been started at least.
                    $result['status'] = "INITIATE";
                    $result['state'] = "INITIATE";
                    $created = $order->get_date_created();
                    $timestamp = 0;
                    $now = time();
                    try {
                        $timestamp = $created->getTimestamp();
                    } catch (Exception $e) {
                        // PHP 8 gives ValueError for certain older versions of WooCommerce here. 
                        $timestamp = intval($created->format('U'));
                    }
                    $passed = $now - $timestamp;
                    $minutes = ($passed / 60);

                    // Give up after 120 minutes. Actually, 60 minutes is probably enough: We expire live sessions after 50 mins.
                    if ($minutes > 120) {
                        $this->log(sprintf(__('Checkout order older than 120 minutes with no order status - cancelled as abandoned: %1$s', 'woo-vipps'), $order->get_id()), 'debug');
                        $order->add_order_note(sprintf(__('%1$s Order with no order status, so session was never completed; setting status to cancelled', 'woo-vipps'), Vipps::CheckoutName()));
                        $result['status'] = 'CANCEL';
                        $result['state'] = 'CANCEL';
                    }
                    return $result;
                }


            } catch (VippsAPIException $e) {
                $resp = intval($e->responsecode);
                if ($resp == 402 || $resp == 404) {
                    $result = array('status'=>'CANCEL', 'state'=>'CANCEL'); 
                    return $result;
                } else {
                    throw $e;
                }
            }
        } else {
            try {
                $result = $this->api->epayment_get_payment($order);
            } catch (VippsAPIException $e) {
                $resp = intval($e->responsecode);
                if ($resp == 402 || $resp == 404) {
                    $result = array('status'=>'CANCEL', 'state'=>'CANCEL');
                    return $result;
                } else {
                    $this->log(sprintf(__("Could not get order status from %1\$s using epayment api: ", 'woo-vipps'), Vipps::CompanyName()) . $e->getMessage(), "error");
                    throw $e;
                }
            } catch (Exception $e) {
                $this->log(sprintf(__("Could not get order status from %1\$s using epayment api: ", 'woo-vipps'), Vipps::CompanyName()) . $e->getMessage(), "error");
                $result = array('status'=>'CANCEL', 'state'=>'CANCEL'); 
                return $result;
            }
        }

        if (!$result || $result == "ERROR") {
            $this->log(sprintf(__("Could not get payment results for order %1\$s", 'woo-vipps'), $order->get_id()));
            wp_die(sprintf(__("Could not get payment results for order %1\$s - you may have the wrong MSN for the order. Please check logs for more information", 'woo-vipps'), $order->get_id()));
        }

        // We now have a result which maybe  will contain user and shipping data, which we will need to normalize because it is slightly different in the different
        // APIs, and we have provided filters/hooks that receive this information. IOK 2025-08-12
        // If we now have epayment data, we want to translate this to the ecom 'view' for now. Later, we will do the opposite.
        // We now only need to map Checkout and epayment to the same format, but we will try to keep the normalized result compatible with 
        // ecom, in case consumers have created filters/hooks. IOK 2025-08-12

        // if Checkout, the result will have a sessionState, reference etc, userInfo, shippingDetails, billingDetails and the payment details in a paymentDetails member.
        // if not, the result will *be* a paymentDetails field, but with user*Details* and shippingDetails added. No billingDetails.
        // The end result should have state/status (not neccessarily present with Checkout), userDetails, paymentDetails, shippingDetails and billingDetails, and a transactionSummary.
        // Because that's what the code depending on this has been expecting. IOK 2025-08-12
        if (!$checkout_session) {
            // This should be an ecom result, so move all data (for simplicitys sake) into paymentDetails
            $result['paymentDetails'] = $result;
        } 
        // Ensure we get payment details with state + aggregate, which we do not if the payment method is bank transfer for checkout. IOK 2024-01-09
        // Also add keys and transform for backwards compatibility.
        $result = $this->normalizePaymentDetails($result);

        // if this is *express - not checkout * and there is no user information, this is probably because we only get that when adding the 'address' scope.
        // if we didn't want the address, we now need to ask for user details using the login get_userinfo api. IOK 2025-08-12
        // This is also the only way to get "email_verified", so we may want to add a setting that always calls this if neccessary. IOK 2025-08-13
        if ($express && !$checkout_session && !isset($result['userDetails'])) {
            $sub = isset($result['profile']) && isset($result['profile']['sub']) ? $result['profile']['sub'] : null;
            $userinfo = [];
            if (!$sub) {
                // This should never happen, but be prepared
                $message = sprintf(__("Could not get user info for order %1\$d using the userinfo API: %2\$s. Please use the 'get complete transaction details' on the button to try to recover this. ", 'woo-vipps'), $order->get_id(), "No 'sub' passed for user ID" );
                $order->add_order_note($message);
                $this->log($message , "error");
            } else {
                // If this happens, the merchant *may* be able to retrieve the information from Vipps so add a note for it.
                try {
                    $userinfo = $this->api->get_userinfo($sub);
                } catch (Exception $e) {
                    $message = sprintf(__("Could not get user info for order %1\$d using the userinfo API: %2\$s. Please use the 'get complete transaction details' on the button to try to recover this. ", 'woo-vipps'), $order->get_id(),  $e->getMessage());
                    $order->add_order_note($message);
                    $this->log($message, 'woo-vipps', "error");
                }
            }
            if ($userinfo) {
                $userDetails = array(
                    'email_verified' => $userinfo['email_verified'],
                    'email' => $userinfo['email'],
                    'firstName' => $userinfo['given_name'] ?? '',
                    'lastName' => $userinfo['family_name'] ?? '',
                    'mobileNumber' => $userinfo['phone_number'] ?? '',
                    'phoneNumber' => $userinfo['phone_number'] ?? '',
                    'userId' => $userinfo['phone_number'] ?? '',
                    'sub' => $userinfo['sub']
                );

                $result['userDetails'] = $userDetails;

                // We may have asked for the address of the customer, so add that too, or a dummy.
                if (!isset($result['shippingDetails'])) {
                    $countries=new WC_Countries();
                    $address =[];
                    $address['addressLine1'] = "";
                    $address['addressLine2'] = "";
                    $address['city']  ="";
                    $address['postCode'] = "";
                    $address['country'] = $countries->get_base_country();

                    // This uses other keys than both epayment and checkout, but we'll normalize it later. IOK 2025-08-13
                    if (isset($userinfo['address'])) {
                        $address['addressLine1'] = $userinfo['address']['street_address'];
                        $address['city'] = $userinfo['address']['region'];
                        $address['country'] = $userinfo['address']['country'];
                        $address['postCode'] = $userinfo['address']['postal_code'];
                    }
                    $result['shippingDetails'] = ['address' => $address];
                }
            }
        }

        if ($express || $checkout_session) {
            // For Vipps Checkout version 3 there are no more userDetails, so we will add it, including defaults for anonymous purchases IOK 2023-01-10
            // This will also normalize userDetails, adding 'sub' where required and fields for backwards compatibility. 2025-08-12
            $result = $this->ensure_userDetails($result, $order);

            // After, we need to normalize shipping details or even add them if e.g. using Checkout without address or contact info IOK 2025-08-13
            // Epayment Express Checkout is of course also significantly different from both the old Express and from Checkout in the formatting here. IOK 2025-08-12
            $result = $this->normalizeShippingDetails($result, $order);
        }

        return $result;
    }

    // IOK 2025-08-12 Normalize the shippingDetails field for backwards compatibility, since this is different from old express, new express and checkout.
    function normalizeShippingDetails($result, $order) {

        // We may actually get no shipping details eg. for Checkout when not asking for addresses etc.
        if (!isset($result['shippingDetails'])) {
            $result['shippingDetails']  = ['address' => [] ];
        }
        $details = $result['shippingDetails'];

        $user = $result['userDetails']; // Normalized earlier
        $address = $details['address'] ?? [];

        // Checkout has address info inside shippingDetails, whereas Express Checkout now has it in an address field IOK 2025-08-12
        if (empty($address)) {
            $address['firstName']  = $details['firstName'] ?? "";
            $address['lastName']  = $details['lastName'] ?? ""; 
            $address['email']  = $details['email'] ??  "";
            $address['mobileNumber'] = $details['phoneNumber'] ?? "";
            $address['addressLine1'] = $details['streetAddress'] ?? "";
            $address['addressLine2'] =  "";
            $address['city'] = $details['city'] ?? "";
            $address['postCode'] =  $details['postalCode'] ?? "";
            $address['country'] = $details['country'] ?? "";
        }

        // Need at least a country for VAT so add our own.
        if (!$address['country']) {
            $countries=new WC_Countries();
            $address['country'] = $countries->get_base_country();
        }

        // Normalize from user details when neccessary (mostly for new express)
        $address['firstName'] =  ($address['firstName'] ?? "") ?: $user['firstName'];
        $address['lastName'] =  ($address['lastName'] ?? "")  ?: $user['lastName'];
        $address['email'] =  ($address['email'] ?? "") ?: $user['email'];
        $address['mobileNumber'] =  ($address['mobileNumber'] ?? "") ?: $user['mobileNumber'];

        // phoneNumber is checkout, mobileNumber is epayment
        $address['phoneNumber'] = $address['mobileNumber'];
        // addressline1 and 2 are epayment, streetAddress is checkout
        $address['streetAddress'] =  $address['addressLine1'];
        // postCode is epayment, postalCode is checkout
        $address['postalCode'] =  $address['postCode'];


        // Ensure we have 'address' as in Express/epayment
        $details['address'] = $address;

        // Name change from Checkout/Express to new Express from MethodId to OptionId IOK 2025-08-12
        $details['shippingMethodId'] = $details['shippingMethodId'] ?? ($details['shippingOptionId'] ?? "");
        $details['shippingOptionId'] = $details['shippingMethodId'];

        $result['shippingDetails'] = $details;
        return $result;
    }


    // IOK 2024-01-09 If using Vipps Checkout with the BankTransfer method, which is eg. used in Finland,
    //  we are (currently) not receiving any  'state' or 'aggregate', so add this iff the payment is successful.
    // The reason for this is that this payment type does not actually use the epayment API at all (!)
    // Also moved some other compatibility code here - 
    //  --- reference used to be orderId
    //  --- state used to be status
    //  --- there used to be a transactionSummary field
    //  --- at one point there was a 'transactionAggregate' instead of an 'aggregate'
    private function normalizePaymentDetails($result) {
        // the 'reference' used to be an 'orderId', keep for compatibility.
        $result['orderId']  = $result['reference'];
        if (isset($result['sessionState']) && $result['sessionState'] == 'PaymentSuccessful' && $result['paymentMethod'] == 'BankTransfer') {
            $details = $result['paymentDetails'];
            $details['state'] = 'SALE'; // Payment is actually complete at this point
            $aggregate=[];
            $aggregate['capturedAmount'] = $details['amount'];
            $aggregate['authorizedAmount'] = $details['amount'];
            $aggregate['refundedAmount'] = ['value'=>0, 'currency'=>$details['amount']['currency']];
            $aggregate['cancelledAmount'] = ['value'=>0, 'currency'=>$details['amount']['currency']];
            $details['aggregate'] = $aggregate;
            $result['paymentDetails'] = $details;
        }

        // Sometimes we get the state at top level, sometimes in paymentDetails.
        $state = $result['state'] ?? false;

        // Now, if we don't have payment details, we should have a dead session or something like it. If we do, we can cretae
        // a normalized result. IOK 2023-12-13
        if (isset($result['paymentDetails'])) {
            $details = $result['paymentDetails'];
            $state = $state ?: $details['state'];
            $result['state'] = $state;
            $details['state'] = $state;

            # IOK 2022-01-19 for this, the docs and experience does not agree, so check both
            $aggregate =  (isset($details['transactionAggregate']))  ? $details['transactionAggregate'] : $details['aggregate'];

            // if 'AUTHORISED' and directCapture is set and true, set to SALE which will set the order to complete
            // IOK 2025-08-12: This is never true anymore - directCapture is never set and the SALE state does not seem to exist.
            //  this was an extra feature for merchants with special products however, so keep the logic just in case.
            if (($result['state'] == 'AUTHORISED' || $result['state'] == "AUTHORIZED") && isset($result['directCapture']) && $result['directCapture']) {
                $result['state'] = "SALE";
            }

            # the transactionSummary used to contain the information now present in 'aggregate', so map it back for compatibility.
            $transactionSummary = array();
            // Always NOK at this point, but we also don't care because the order has the currency
            // IOK 2024-03-22 Now supports other currencies, but we still don't care.
            $transactionSummary['capturedAmount'] = isset($aggregate['capturedAmount']) ?   $aggregate['capturedAmount']['value'] : 0;
            $transactionSummary['refundedAmount'] = isset($aggregate['refundedAmount']) ? $aggregate['refundedAmount']['value'] : 0;
            $transactionSummary['cancelledAmount'] =isset($aggregate['cancelledAmount']) ? $aggregate['cancelledAmount']['value'] : 0;
            $transactionSummary['authorizedAmount'] =isset($aggregate['authorizedAmount']) ? $aggregate['authorizedAmount']['value'] : 0;
            $transactionSummary['remainingAmountToCapture'] = $transactionSummary['authorizedAmount'] - $transactionSummary['cancelledAmount'] - $transactionSummary['capturedAmount'];
            $transactionSummary['remainingAmountToRefund'] = $transactionSummary['capturedAmount'] -  $transactionSummary['refundedAmount'];
            // now also reducing remainingAmmountToCancel with cancelledAmount PMB 2024-11-21
            $transactionSummary['remainingAmountToCancel'] = $transactionSummary['authorizedAmount'] -  $transactionSummary['capturedAmount'] - $transactionSummary['cancelledAmount'];

            $result['transactionSummary'] = $transactionSummary;
        }
        // After this, the result will contain a 'state' which used to be a 'status' - map back for compatibility.
        // The reason for the method is that in ecom v2 we needed to calculate this from the transaction history. IOK 2025-08-12
        $result['status'] = $this->get_payment_status_from_payment_details($result);

        // No longer used; was used in later versions of ecom to deduce order status. Added for typewise compatibility.
        $result['transactionLogHistory'] = array();
        // The corresponding epayment log. Filled on-demand by debugging code.
        $result['epaymentLog'] = null;

        return $result;
    }

    // Vipps Checkout v3 does *not* provide userDetails. Vipps Checkout v2 and epayment *does*. But Checkout additionally allows
    // for anonymous purchases, in which case there is *no* user details. In this case we provide an anonymous user so we can actually create an order.
    // To handle this, we provide this utility that ensures we have userDetails no matter the input. For this we use the anonymous filters and "billingDetails" if present
    // if not, we use shippingDetails. IOK 2023-01-10
    // Also, epayment uses mobileNumber and checkout uses phoneNumber, so normalize.
    private function ensure_userDetails($vippsdata, $order) {
        $userDetails = [];

        // If we have userDetails, use it (ecom API with user data requested - Express Checkout
        if (isset($vippsdata['userDetails'])) {
            $userDetails = $vippsdata['userDetails'];
            // This is the verified user information from the app - this is always the customer for Express Checkout, but not for Checkout IOK 2025-08-12
            $sub = "";
            if (isset($vippsdata['profile']) && isset($vippsdata['profile']['sub'])) {
                $sub = $vippsdata['profile']['sub'];
            }
            $userDetails['sub'] = $sub;

        } else if (isset($vippsdata['billingDetails'])) {
            // Otherwise this is now Checkout, and we want to get it from billingDetails preferrably
            $addr = $vippsdata['billingDetails'];
            $phone = $addr['phoneNumber'];
            $userDetails = array(
                    'firstName' => $addr['firstName'],
                    'lastName' => $addr['lastName'],
                    'email' => $addr['email'],
                    'phoneNumber' => $phone,
                    // This is the verified user information from the app - this is always the customer for Express Checkout, but not for Checkout IOK 2025-08-12
                    'sub' => ""
                    );

        // Or use shippingDetails - this is still Checkout
        } else if (isset($vippsdata['shippingDetails'])) {
            $addr = $vippsdata['shippingDetails'];
            $phone = $addr['phoneNumber'];
            $userDetails = array(
                    'firstName' => $addr['firstName'],
                    'lastName' => $addr['lastName'],
                    'email' => $addr['email'],
                    'phoneNumber' => $phone,
                    // This is the verified user information from the app - this is always the customer for Express Checkout, but not for Checkout IOK 2025-08-12
                    'sub' => ""
                    );

        // And it is possible to not require user details in Checkout at all (or if not using express)
        } else {
            $userDetails = array(
                    
                    'firstName' => apply_filters('woo_vipps_anon_customer_first_name', __('Anonymous customer', 'woo-vipps'), $order),
                    'lastName' => apply_filters('woo_vipps_anon_customer_last_name', "", $order),
                    'email' => apply_filters('woo_vipps_anon_customer_email', '', $order),
                    'phoneNumber' => apply_filters('woo_vipps_anon_customer_phone_number', '', $order),
                    'sub' => ""
                    );
        }

        //Normalize the result
        $phone = $userDetails['phoneNumber'] ?? ($userDetails['mobileNumber'] ?? "");
        $userDetails['phoneNumber'] = $phone;
        $userDetails['mobileNumber'] = $phone;

     
        // No longer used, but try to provide it for backwards compatibility
        $userDetails['userId'] = $userDetails['phoneNumber'];
        // This is possible to get iff we have the 'sub', unfortunately it is not passed directly in several of the apis. IOK 2025-08-12
        $userDetails['email_verified'] = ($userDetails['email_verified'] ?? false);

        $vippsdata['userDetails'] = $userDetails;

        return $vippsdata;
    }

    // Update the order with Vipps payment details, either passed or called using the API.
    public function update_vipps_payment_details ($order, $details = null) {
       if (!$details) $details = $this->get_payment_details($order);

       if ($details) {
           if (isset($details['transactionSummary'])) {
               $transactionSummary= $details['transactionSummary'];
               $order->update_meta_data('_vipps_status',$details['status']);
               $order->update_meta_data('_vipps_captured',$transactionSummary['capturedAmount']);
               $order->update_meta_data('_vipps_refunded',$transactionSummary['refundedAmount']);
               $order->update_meta_data('_vipps_capture_remaining',$transactionSummary['remainingAmountToCapture']);
               $order->update_meta_data('_vipps_refund_remaining',$transactionSummary['remainingAmountToRefund']);
               if (isset($details['transactionSummary']['cancelledAmount'])) {
                   $order->update_meta_data('_vipps_cancelled',$transactionSummary['cancelledAmount']);
                   $order->update_meta_data('_vipps_cancel_remaining',$transactionSummary['remainingAmountToCancel']);
               }
           }
           // This is the epayment API - IOK 2022-01-20
           if (isset($details['paymentDetails'])) {
               $d = $details['paymentDetails'];
               if (isset($d['amount'])) {
                   $order->update_meta_data('_vipps_amount', $d['amount']['value']);
               }
               $aggregate =  (isset($d['transactionAggregate']))  ? $d['transactionAggregate'] : $d['aggregate'];
               if ($aggregate) {
                   // capturedAmount, refundedAmount, authorizedAmount, cancelledAmount
                   if (isset($aggregate['authorizedAmount'])) {
                       $order->update_meta_data('_vipps_amount', $aggregate['authorizedAmount']['value']);
                   }
               }
           }
           // Modify payment method name if neccessary
           if (isset($details['paymentMethod']) && $details['paymentMethod'] == 'Card') {
               if ($order->get_meta('_vipps_checkout')) {
                    $order->set_payment_method_title(sprintf(__('Credit Card / %1$s', 'woo-vipps'), Vipps::CheckoutName()));
               }
           }
           if (isset($details['paymentMethod']) && $details['paymentMethod'] == 'BankTransfer') {
               if ($order->get_meta('_vipps_checkout')) {
                    $order->set_payment_method_title(sprintf(__('Bank Transfer/ %1$s', 'woo-vipps'), Vipps::CheckoutName()));
                    $order->update_meta_data('_vipps_api', 'banktransfer');
               }
           }
           $order->save();
       }
       return $order;
    }

    // IOK 2021-01-20 from March 2021 the order_status interface is removed; so we now need to interpret the payment history to find
    // out the order status.
    // IOK 2022-01-19 With the epayment API on the other hand, the payment status is back as 'state'.
    // IOK 2025-08-12 Except for certain payment methods in Checkout. So.
    // IOK 2025-08-12 Removing the ecom support now, so all code will use 'state', as normalized by normalizePaymentDetails
    public function get_payment_status_from_payment_details($details) {
           $status = $details['state'];
           return $status;
    }

    // Get the order status as defined by Vipps; if you have the payment details already, pass them. Will modify the order. IOK 2021-01-20
    public function get_vipps_order_status($order, $statusdata=null) {
        $vippsorderid = $order->get_meta('_vipps_orderid');
        if (!$vippsorderid) {
                $msg = sprintf(__('Could not get %1$s order status - it has no %1$s Order Id. Must cancel.','woo-vipps'), $this->get_payment_method_name());
                $this->log($msg,'error');
                return 'CANCEL'; 
        }
        if (!$statusdata) {
            try { 
                $statusdata = $this->get_payment_details($order);
            } catch (TemporaryVippsApiException $e) {
                $this->log(sprintf(__('Could not get %1$s order status for order id:', 'woo-vipps'), Vipps::CompanyName()) . ' ' . $order->get_id() . "\n" .$e->getMessage(),'error');
                return null;
            } catch (VippsAPIException $e) {
                $msg = sprintf(__('Could not get %1$s order status','woo-vipps'), $this->get_payment_method_name()) . ' ' . $e->getMessage();
                $this->log($msg,'error');
                if (intval($e->responsecode) == 402 || intval($e->responsecode) == 404) {
                    $this->log(sprintf(__('Order does not exist at %1$s - cancelling','woo-vipps'), $this->get_payment_method_name()) . ' ' . $order->get_id(), 'warning');
                    return 'CANCEL'; 
                }
            } catch (Exception $e) {
                $msg = sprintf(__('Could not get %1$s order status for order id:','woo-vipps'), $this->get_payment_method_name()) . ' ' . $order->get_id() . "\n" . $e->getMessage();
                $this->log($msg,'error');
                return null;
            }
        }
        if (!$statusdata) return null;

        $vippsstatus = isset($statusdata['status']) ? $statusdata['status'] : "";

        if (!$vippsstatus) {
            $this->log("Unknown Vipps Status: " . print_r($statusdata, true), 'debug');
        }
        return $vippsstatus;
    }

    // The various Vipps APIs return address info with various keys and formats, so we need to translate all of them 
    // to a canonical format.
    public function canonicalize_vipps_address($address, $user) {
        // eCom has user info only in the user struct
        $firstname = $user['firstName'];
        $lastname = $user['lastName'];
        $email = $user['email'];

        $phone = isset($user['mobileNumber']) ? $user['mobileNumber'] : "";
        if (isset($user['phoneNumber'])) $phone = $user['phoneNumber'];
        if (!$phone && ($address['phoneNumber'] ?? "")) $phone = $address['phoneNumber'];
        if (!$phone && ($address['mobileNumber'] ?? "")) $phone = $address['mobileNumber'];

        if (!isset($address['firstName']) or !$address['firstName']) {
            $address['firstName'] = $firstname;
        }
        if (!isset($address['lastName']) or !$address['lastName']) {
            $address['lastName'] = $lastname;
        }
        // checkout uses phonenumber
        if (!isset($address['phoneNumber']) or !$address['phoneNumber']) {
            $address['phoneNumber'] = $phone;
        }
        // epayment uses mobileNumber
        if (!isset($address['mobileNumber']) or !$address['mobileNumber']) {
            $address['mobileNumber'] = $phone;
        }
        if (!isset($address['email']) or !$address['email']) {
            $address['email'] = $email;
        }

        // epayment
        $addressline1 = isset($address['addressLine1']) ? $address['addressLine1'] : "";
        $addressline2 = isset($address['addressLine2']) ? $address['addressLine2'] : "";
        // checkout
        if (isset($address['streetAddress'])) {
            $addressline1 = $address['streetAddress'];
        }
        if (isset($address['street_address'])) { // From the userinfo api
            $addressline1 = $address['street_address'];
        }
        if ($addressline1 == $addressline2) $addressline2 = '';
        $address['addressLine1'] = $addressline1; // epayment
        $address['addressline2'] = $addressline2;
        $address['streetAddress'] = $addressline1; // Checkout

        $city = "";
        if (isset($address['city'])) { // checkout and epayment
            $city = $address['city'];
        } elseif (isset($address['region'])) { // ecom
            $city = $address['region'];
        }
        $address['city'] = $city;
        $address['region'] = $city;

        $postcode= "";
         if (isset($address['postCode'])) {
            $postcode= $address['postCode']; // epayment
        } elseif (isset($address['postalCode'])){
            $postcode= $address['postalCode']; // checkout
        } elseif (isset($address['postal_code'])) {
            $postcode= $address['postal_code']; // userinfo
        }
        $address['postCode'] = $postcode; // epayment
        $address['zipCode'] = $postcode; // ecom
        $address['postalCode'] = $postcode; // checkout

        // Allow users to modify the address to e.g. handle phone numbers differently IOK 2025-01-20
        return apply_filters('woo_vipps_canonicalize_checkout_address', $address, $user);
    }

    public function set_order_shipping_details($order,$shipping, $user, $billing=false, $alldata=null, $assigncustomer=true) {
        global $Vipps;
        $done = $order->get_meta('_vipps_shipping_set');
        if ($done) return true;
        $order->update_meta_data('_vipps_shipping_set', true);
        $order->save(); // Limit the window for double shipping as much as possible.

        // This is for handling a custom consent checkbox for mailing lists etc. IOK 2023-02-09
        if ($alldata && isset($alldata['customConsentProvided'])) {
           $order->update_meta_data('_vipps_custom_consent_provided', intval($alldata['customConsentProvided']));
        }


        // We get different values from the normal callback and the Checkout callback, so be prepared for several results. IOK 2021-09-02
        // IOK VERIFY this should be normalized now or soon at any rate
        $address = isset($shipping['address']) ? $shipping['address'] : $shipping;;

        // Sometimes we get an empty shipping address! In this case, fill the details with the billing address.
        // This only happens with ecommerce though, so we need to check before canonicalizing. IOK 2022-03-21
        // billing is only present in Checkout, which uses phoneNumber, streetAddress, postalCode etc.
        $shipping_empty = true;
        if ($billing && array_key_exists('streetAddress', $address)) {
            $keys = ['firstName', 'lastName', 'email', 'phoneNumber', 'streetAddress', 'postalCode', 'city', 'country'];
            foreach ($keys as $key) {
                if (isset($address[$key]) && $address[$key]) {
                    $shipping_empty = false; break;
                }
            }
            if ($shipping_empty) {
                foreach ($keys as $key) $address[$key] = $billing[$key];
            }
        }

        if (!$billing) $billing = $address;
        $address = $this->canonicalize_vipps_address($address, $user);
        $billing = $this->canonicalize_vipps_address($billing, $user);

        # Billing.
        $order->set_billing_email($billing['email']);
        $order->set_billing_phone($billing['mobileNumber']);
        $order->set_billing_first_name($billing['firstName']);
        $order->set_billing_last_name($billing['lastName']);
        $order->set_billing_address_1($billing['addressLine1']);
        if ($billing['addressLine2'] ?? false) $order->set_billing_address_2($billing['addressLine2']);
        $order->set_billing_city($billing['city'] ?? "");
        $order->set_billing_postcode($billing['postCode'] ?? "");
        $order->set_billing_country($billing['country'] ?? "");

        # Shipping.
        $order->set_shipping_first_name($address['firstName']);
        $order->set_shipping_last_name($address['lastName']);
        if (version_compare(WC_VERSION, '5.6.0', '>=')) {
            $order->set_shipping_phone($address['mobileNumber']);
        }
        $order->set_shipping_address_1($address['addressLine1']);
        if ($address['addressLine2'] ?? "") $order->set_shipping_address_2($address['addressLine2']);
        $order->set_shipping_city($address['city']);
        $order->set_shipping_postcode($address['postCode']);
        $order->set_shipping_country($address['country']);

        $order->save();

        // This is *essential* to get VAT calculated correctly. That calculation uses the customer, which uses the session, which we will have restored at this point.IOK 2019-10-25
        if (WC()->customer) {
            WC()->customer->set_billing_email($billing['email']);
            WC()->customer->set_email($billing['email']);

            $country = $billing['country'] ?? ($address['country'] ?? "");
            WC()->customer->set_billing_location($country,'',$billing['postalCode'],$billing['region']);
            WC()->customer->set_shipping_location($address['country'],'',$address['postalCode'],$address['region']);
        }

        // Now do shipping, if it exists IOK 2021-09-02
        $method = isset($shipping['shippingMethodId']) ? $shipping['shippingMethodId'] : false;

        $shipping_rate=null;
        $option_table = [];

        if ($method) {
            if (substr($method,0,1) != '$') {
                $shipping_rate = $this->get_legacy_express_checkout_shipping_rate($shipping);
            } else { 
                // Strip suffixes if we have several Express rates mapping to the same Woo rate (eg. for Posten). IOK 2025-05-04
                $matches = [];
                preg_match("!^(?P<key>[^:]+):?(?P<option_index>.+)?$!", $method, $matches);
                $key = $matches['key'] ?? "";
                $option_index = intval(trim($matches['option_index'] ?? "")); // 0 is never an index
                $shipping_table = $order->get_meta('_vipps_express_checkout_shipping_method_table');
                if (is_array($shipping_table) && isset($shipping_table[$key])) {
                    $shipping_rate = @unserialize($shipping_table[$key]);
                    if (!$shipping_rate) {
                        $this->log(sprintf(__("%1\$s: Could not deserialize the chosen shipping method %2\$s for order %3\$d", 'woo-vipps'), Vipps::ExpressCheckoutName(), $method, $order->get_id()), 'error');
                    } else {
                        if ($option_index) {
                           $meta = $shipping_rate->get_meta_data();
                           $option_table = $meta['_vipps_pickupPoints'] ?? [];
                           // force string table IOK 2025-08-15
                           $point =  $option_table["i".$option_index] ?? "";
                           if ($point) {
                               $shipping['pickupPoint'] = $point;
                           }
                           $shipping_rate->add_meta_data('_vipps_pickupPoints', null);
                        }
                        // Empty this when done, but not if there was an error - let the merchant be able to debug. IOK 2020-02-14
                        $order->update_meta_data('_vipps_express_checkout_shipping_method_table', null);
                    }
                } 
            }


            // Possible extra metadata from Vipps Checkout IOK 2023-01-17
            // Store in the order, but also in the shipping rate so it will be visible in the order screen
            // along with the shipping ragte
            if (isset($shipping['pickupPoint'])) {
                $order->update_meta_data('vipps_checkout_pickupPoint', $shipping['pickupPoint']);
		if ($shipping_rate) {
			$pp = $shipping['pickupPoint'];
			$addr = [];
			foreach(['address', 'postalCode', 'city', 'country'] as $key) {
				$v = trim($pp[$key]);
				if (!empty($v)) $addr[] = trim($pp[$key]);
			}

			$shipping_rate->add_meta_data('pickup_location', $pp['name']);
			$shipping_rate->add_meta_data('pickup_address', join(", ", $addr));
			$shipping_rate->add_meta_data('pickup_details', ""); // Not supported by the API unfortunately. IOK 2025-06-07
		}
            }
            if (isset($shipping['timeslot'])) {
                $order->update_meta_data('vipps_checkout_timeslot', $shipping['timeslot']);
                if ($shipping_rate) {
                    $pp = $shipping['timeslot'];
                    $slot = "";
                    $slot .= sprintf(__("Date: %s", 'woo-vipps'), ($pp['date'] ?? ""));
                    $slot .= " " . sprintf(__("Start: %s", 'woo-vipps'), ($pp['start'] ?? ""));
                    $slot .= " " . sprintf(__("End: %s", 'woo-vipps'), ($pp['end'] ?? ""));
                    $shipping_rate->add_meta_data('vipps_delivery_timeslot', $slot);
                    $shipping_rate->add_meta_data('vipps_delivery_timeslot_id', $pp['id']);
                }
            }

            $shipping_rate = apply_filters('woo_vipps_express_checkout_final_shipping_rate', $shipping_rate, $order, $shipping);
            $it = null;       

            $total_shipping = 0;
            $total_shipping_tax = 0;

            if ($shipping_rate) {
                // We may need the order total early on, so start with that
                $ordertotal = $order->get_total() ?: 0;

                // Recover the Shipping Method class
                $methods_classes = WC()->shipping->get_shipping_method_class_names();
                $methodclass = $methods_classes[$shipping_rate->get_method_id()] ?? null;
                $shipping_method = $methodclass ? new $methodclass($shipping_rate->get_instance_id()) : null;
                $is_vipps_checkout_shipping = $shipping_method && is_a($shipping_method, 'VippsCheckout_Shipping_Method');

                // Some Vipps Checkout-specific shipping methods calculate the cost in the Vipps window.
                if ($is_vipps_checkout_shipping && $shipping_method->dynamic_cost) {
                    $vippsamount = intval($order->get_meta('_vipps_amount'));
                    $shipping_tax_rate = floatval($order->get_meta('_vipps_shipping_tax_rates'));
                    $compareamount = $ordertotal * 100;
                    $amountdiff = $vippsamount-$compareamount; // this is the *actual* shipping cost at this point
                    $diffnotax = ($amountdiff / (100 + $shipping_tax_rate)); // Adjusted to correct values actually 
                    $difftax = WC_Tax::round($amountdiff/100 - $diffnotax);
                    $actual = $amountdiff/100 - $difftax;

                    $shipping_rate->set_cost($actual); 
                    $shipping_rate->set_taxes( [ 1 => $difftax] );
                } else {
                    // Noop
                }

                $it = new WC_Order_Item_Shipping();
                $it->set_shipping_rate($shipping_rate);
                $it->set_order_id( $order->get_id() );
                // This should actually have been done by the "set_shipping_rate" call above, but as of 3.9.2 at least, this does not work.
                // Therefore, do it manually/forcefully IOK 2020-02-17
                foreach($shipping_rate->get_meta_data() as $key => $value) {
                    $it->add_meta_data($key,$value,true);
                }
                $it->save();

                $order->add_item($it);

                $total_shipping = $it->get_total() ?: 0;
                $total_shipping_tax = $it->get_total_tax() ?: 0;

                // Try to avoid calculate_totals, because this will recalculate shipping _without checking if the rate
                // in question actually should use tax_. Therefore we will just add the pre-calculated values, so that the
                // value reserved at Vipps and the order total is the same. IOK 2022-10-03
                $order->set_shipping_total($total_shipping);
                $order->set_shipping_tax($total_shipping_tax);

                $order->set_total($ordertotal + $total_shipping + $total_shipping_tax);
                $order->update_taxes(); // Necessary for the admin view only; does not recalculate order.
            }

            // Add an early hook for Vipps Checkout orders with special shipping methods
            if ($shipping_rate) { 
                $metadata = $shipping_rate->get_meta_data();
                if (isset($metadata['type'])) {
                    do_action('woo_vipps_checkout_special_shipping_method', $order, $shipping_rate, $metadata['type']);
                }
            }

            $order->save(); 
            // NB: WE DO NOT CALL CALCULATE TOTALS!
            // THIS WILL CALCULATE SHIPPING FOR TAX IF THERE IS A TAX RATE FOR THE GIVEN AREA WITH THE 'shipping' PROPERTY CHECKED - EVEN IF THE SHIPPING RATE IT SELF IS NOT THUS CONFIGURED.
            // Same thing happens when using the "recalculate" button in the backend
            // This will *only* affect users that choose "No tax" for the shipping rates themselves, which is mostly just wrong, but we want to ensure consistency here since this is hard
            // to debug.
            // $order->calculate_totals(true);
        }


        // If we have the 'expresscreateuser' thing set to true, we will create or assign the order here, as it is the first-ish place where we can.
        // If possible and safe, user will be logged in before being sent to the thankyou screen.  IOK 2020-10-09
        // Same thing for Vipps Checkout, mutatis mutandis. The function below returns false if no customer exists or gets created.
        $customer = false;
        if ($assigncustomer) {
            $customer = Vipps::instance()->express_checkout_get_vipps_customer($order);
        }
        if ($customer) {
            // This would have been used to ensure that we 'enroll' the users the same way as in the Login plugin. Unfortunately, the userId from express checkout isn't
            // the same as the 'sub' we get in Login so that must be a future feature. IOK 2020-10-09
            // IOK 2025-08-13 we do get the 'sub' now, at least for express checkout. For Checkout, we would have to compare the email of the user with the verified email
            // after calling get_userinfo, so we'll leave that be.
            if (class_exists('VippsWooLogin') && $customer && !is_wp_error($customer) && !get_user_meta($customer->get_id(), '_vipps_phone',true)) {
                update_user_meta($customer->get_id(), '_vipps_phone', $billing['phoneNumber']);
                if (isset($user['sub'])) {
                    update_user_meta($userid, '_vipps_id', $user['sub']);
                    update_user_meta($userid, '_vipps_just_connected', 1);
                }
            }

            // Ensure we get any changes made to the order, as it will be re-saved later
            $order = wc_get_order($order->get_id());
        }
        do_action('woo_vipps_set_order_shipping_details', $order, $shipping, $user);
        $order->save(); // I'm not sure why this is neccessary - but be sure.

    }
  
    // Previously, shipping rates were added by creating them here with metadata packed into the shippingMethodId. This is from 1.4.0 only 
    // used when the woo_vipps_shipping_methods filter has been overriden by the merchant. IOK 2020-02-14
    private function get_legacy_express_checkout_shipping_rate($shipping) {
        $method = $shipping['shippingMethodId'];
        list ($rate,$tax) = explode(";",$method);
        // The method ID is encoded in the rate ID but we apparently must still send it to the WC_Shipping_Rate constructor. IOK 2018-06-01
        // Unfortunately, Vipps won't accept long enought 'shipingMethodId' for us to actually stash all the information we need. IOK 2018-06-01
        list ($method,$product) = explode(":",$rate);
        $tax = wc_format_decimal($tax,'');
        $label = $shipping['shippingMethod'];
        $cost = wc_format_decimal($shipping['shippingCost'],''); // This is inclusive of tax
        $costExTax= wc_format_decimal($cost-$tax,'');
        $shipping_rate = new WC_Shipping_Rate($rate,$label,$costExTax,array(array('total'=>$tax)), $method, $product);
        $shipping_rate = apply_filters('woo_vipps_express_checkout_shipping_rate',$shipping_rate,$costExTax,$tax,$method,$product);
        return $shipping_rate;
    }

    // Used by both callback_check_order_status and handle_callback - sets the neccessary order metadata after a successful (or not vipps transaction). IOK 2025-08-13
    public function order_set_transaction_metadata($order, $transaction) {
        // Set Vipps metadata as early as possible
        $vippsstamp = strtotime($transaction['timeStamp']);
        $vippsamount = $transaction['amount'] ?? '';
        $vippscurrency= $transaction['currency'] ?? '';
        $vippsstatus = $transaction['status'];

        $order->update_meta_data('_vipps_callback_timestamp',$vippsstamp);
        $order->update_meta_data('_vipps_amount',$vippsamount);
        $order->update_meta_data('_vipps_currency',$vippscurrency);
        $order->update_meta_data('_vipps_status',$vippsstatus);

        // Checkout only, modify payment method name if neccessary
        if ($transaction['paymentmethod'] == 'Card') {
               if ($order->get_meta('_vipps_checkout')) {
                    $order->set_payment_method_title(sprintf(__('Credit Card / %1$s', 'woo-vipps'), Vipps::CheckoutName()));
               }
        }
        // Checkout only, banktransfers are handled specially so note that
        if ($transaction['paymentmethod'] == 'BankTransfer') {
                $order->set_payment_method_title(sprintf(__('Bank Transfer/ %1$s', 'woo-vipps'), Vipps::CheckoutName()));
                $order->update_meta_data('_vipps_api', 'banktransfer');
        }
    }

    // Handle the callback from Vipps eCom.
    public function handle_callback($result, $order, $ischeckout=false, $iswebhook=false) {
        global $Vipps;

        $vippsorderid = $result['orderId'];
        $merchant= $result['merchantSerialNumber'];
      
        $keyset = $this->get_keyset(); 
        $me = array_keys($keyset);

        if (!in_array($merchant, $me)) {
            $this->log(sprintf(__("%1\$s callback with wrong merchantSerialNumber - might be forged",'woo-vipps'), $this->get_payment_method_name()) . " " .  $order->get_id(), 'warning');
            return false;
        }

        if (!$order) {
            $this->log(sprintf(__("%1\$s callback for unknown order",'woo-vipps'), $this->get_payment_method_name()) . " " .  $order->get_id(), 'warning');
            return false;
        }
        $orderid = $order->get_id();
        // We may need to use poll to get data, depending on the content passed.
        $express = $order->get_meta('_vipps_express_checkout');
        $checkout_session = $order->get_meta('_vipps_checkout_session');

        if ($vippsorderid != $order->get_meta('_vipps_orderid')) {
            $this->log(sprintf(__("Wrong %1\$s Orderid - possibly an attempt to fake a callback ", 'woo-vipps'), Vipps::CompanyName()), 'warning');
            clean_post_cache($order->get_id());
            exit();
        }

        $errorInfo = $result['errorInfo'] ?? '';
        if ($errorInfo) {
            $this->log(sprintf(__("Message in callback from %1\$s for order",'woo-vipps'), $this->get_payment_method_name()) . ' ' . $orderid . ' ' . $errorInfo['errorMessage'],'error');
            $order->add_order_note(sprintf(__("Message from %1\$s: %2\$s",'woo-vipps'), $this->get_payment_method_name(), $errorInfo['errorMessage']));
        }

        // The payment details field is passed in Checkout, not in Express, but none of them are complete, so we fill out the values 
        // depending on which one we are IOK 2025-08-13
        $details = [];
        // Checkout has this as a field, containing *some* of the neccessary data
        if (isset($result['paymentDetails'])) {
            // Checkout. The sesssion states are # "SessionCreated" "PaymentInitiated" "SessionExpired" "PaymentSuccessful" "PaymentTerminated"
            // -- we should only get callbacks for successful sessions actually.
            $details = $result['paymentDetails'];
            $result['state'] = $result['sessionState'] == 'PaymentSuccessful' ? 'AUTHORIZED' : ($result['sessionState'] == 'PaymentTerminated' ? 'TERMINATED' : 'CREATED');
            $details['state']  = $result['state'];
            $details['paymentMethod'] = $result['paymentMethod'];
        } else {
            // This should be an ecom callback; which we need to add a lot of data for to get a valid "paymentDetails".
            $details = [];
            $result['state'] = $result['name'];  // The name of the callback - which should be AUTHORIZED, TERMINATED etc
            $details['state']  = $result['name'];
            $details['amount'] = $result['amount']; // currency, value
            $details['paymentMethod'] = 'epayment';
            $currency = $details['amount']['currency'];
            $nothing  = [ 'currency' => $currency, 'value' => 0];
        } 

        // For both callbacks, set 'aggregate' 
        $currency = $details['amount']['currency'];
        $nothing  = [ 'currency' => $currency, 'value' => 0];
        $aggregate = ['authorizedAmount' => $nothing, 'cancelledAmount' => $nothing, 'capturedAmount' => $nothing, 'refundedAmount' => $nothing];
        if ($details['state'] == 'AUTHORIZED') {
           $aggregate['authorizedAmount'] = $details['amount'];
        }
        $details['aggregate'] = $aggregate;
        $result['paymentDetails'] = $details;

        $result = $this->normalizePaymentDetails($result);
        $details = $result['paymentDetails'];

        $vippsstatus = $result['status']; // Will exist now, because of the normalization IOK 2025-08-13

        // Extract order metadata from either Checkout or Epayment - set below IOK 2025-08-13
        $transaction = array();
        $stamp = ($result['timestamp'] ?? false) ? strtotime($result['timestamp']) : time(); 
        $transaction['timeStamp'] = date('Y-m-d H:i:s', $stamp);
        $transaction['amount'] = $details['amount']['value'];
        $transaction['currency'] = $details['amount']['currency'];
        $transaction['status'] = ($result['state'] ?? $details['state']);
        $transaction['paymentmethod'] = $details['paymentMethod'] ?? "";

        if (!$transaction) {
            $this->log(sprintf(__("Anomalous callback from %1\$s, handle errors and clean up",'woo-vipps'), $this->get_payment_method_name()),'warning');
            clean_post_cache($order->get_id());
            return false;
        }

        $order->add_order_note(sprintf(__('%1$s callback received','woo-vipps'), $this->get_payment_method_name()));
        do_action('woo_vipps_callback_received', $order, $result, $transaction);

        $oldstatus = $order->get_status();
        if ($oldstatus != 'pending') {
            // Actually, we are ok with this order, abort the callback. IOK 2018-05-30
            clean_post_cache($order->get_id());
            return false;
        }

        // If  the callback is late, and we have called get order status, and this is in progress, we'll log it and just drop the callback.
        // We do this because neither Woo nor WP has locking, and it isn't feasible to implement one portably. So this reduces somewhat the likelihood of race conditions
        // when callbacks happen while we are polling for results. IOK 2018-05-30
        if (!$Vipps->lockOrder($order)) {
            clean_post_cache($order->get_id());
            return false;
        }

        // Ensure we use the same session as for the original order from here on. IOK 2019-10-21
        // IOK 2023-07-18 but because of the race condition issue, we cannot guarantee that any changes
        // made to the session here will be saved. Sorry. 
        $Vipps->callback_restore_session($orderid);

        // Set Vipps metadata as early as possible
        $this->order_set_transaction_metadata($order, $transaction);

        $this->log(sprintf(__("%1\$s callback: Handling order: ", 'woo-vipps'), Vipps::CompanyName()) . " " .  $orderid, 'debug');

        // Failsafe for rare bug when using Klarna Checkout with Vipps as an external payment method
        // IOK 2024-01-09 ensure this is called only when order is complete/authorized
        if (in_array($this->interpret_vipps_order_status($vippsstatus), ['authorized', 'complete'])) {
            $this->reset_erroneous_payment_method($order);
        }


        if ($express || $ischeckout) {
            // For Vipps Checkout version 3 there are no more userDetails, so we will add it, including defaults for anonymous purchases IOK 2023-01-10
            // This will also normalize userDetails, adding 'sub' where required and fields for backwards compatibility. 2025-08-12
            if ($ischeckout) {
                $result = $this->ensure_userDetails($result, $order);
            }
            // Some Express Checkout orders aren't really express checkout orders, but normal orders to which we have 
            // added scope name, email, phoneNumber. The reason is that we don't care about the address. But then
            // we also get no user data in the callback, so we must replace the callback with a user info call. IOK 2023-03-10
            if (!isset($result['userDetails'])) {
                // This also calls ensure_userDetails and normalizeShippingDetails - but NB: it could fail, so call only when neccessary.
                $result = $this->get_payment_details($order);
            } 
            // Epayment Express Checkout is of course also significantly different from both the old Express and from Checkout in the formatting here. IOK 2025-08-12
            $result = $this->normalizeShippingDetails($result, $order);

            // We should now always have shipping details.
            if (isset($result['shippingDetails'])) {
                $billing = isset($result['billingDetails']) ? $result['billingDetails'] : false;
                $this->set_order_shipping_details($order,$result['shippingDetails'], $result['userDetails'], $billing, $result);
            }
        }

        // the only status we now care about is AUTHORIZED. Previously we had AUTHORISED and RESERVED and RESERVE as well. And SALE.
        if ($vippsstatus == 'AUTHORIZED') {
            $this->payment_complete($order);
        } else if ($vippsstatus == 'SALE') {
          // Direct capture needs special handling because most of the meta values we use are missing IOK 2019-02-26
          // Actually not supported anymore, but keep logic. IOK 2025-08-13
          $order->add_order_note(sprintf(__('Payment captured directly at %1$s', 'woo-vipps'), $this->get_payment_method_name()));
          $order->payment_complete();
          $this->update_vipps_payment_details($order);
        } else {
            $order->update_status('cancelled', sprintf(__('Callback: Payment cancelled at %1$s', 'woo-vipps'), $this->get_payment_method_name()));
        }
        $order->save();
        clean_post_cache($order->get_id());

        // Restore the session again so that we aren't causing issues with the customer-return branch, which may have to update the session concurrently. IOK 2023-018
        $Vipps->callback_restore_session($orderid);
        $Vipps->unlockOrder($order);

        // Create a signal file (if possible) so the confirm screen knows to check status IOK 2018-05-04
        try {
            $Vipps->createCallbackSignal($order,'ok');
        } catch (Exception $e) {
                // Could not create a signal file, but that's ok.
        }

        // Signal that we in fact handled the order.
        return true;
    }

    // Do the 'payment_complete' logic for non-SALE orders IOK 2020-09-22
    public function payment_complete($order,$transactionid='') {
            // Orders not needing processing can be autocaptured, so try to do so now.
            $autocapture = $this->maybe_complete_payment($order);
            if (!$autocapture) {
               add_filter('woocommerce_payment_complete_order_status', 
                    function ($status, $orderid, $order) {
                       return $this->after_vipps_order_status($order);
                    }, 99, 3);
               $order->add_order_note(sprintf(__('Payment authorized at %1$s', 'woo-vipps'), $this->get_payment_method_name()));
            }
            $order->payment_complete();
    }

    // Hook run by Woo after order is complete (authorized or sale). We'll add receipt info etc here.
    public function order_payment_complete ($orderid) {
        $order = wc_get_order($orderid);
        if (!is_a($order, 'WC_Order')) return false;
        if ($order->get_payment_method() != 'vipps') return false;

        $do_order_management = apply_filters('woo_vipps_order_management_on_payment_complete', true, $orderid);
        if (!$do_order_management) return;

        // We do the actual order management call in a separate request which we call non-blocking to avoid it locking
        // up the users' session on return from the store. This won't work if your Wordpress doesn't support non-blocking calls,
        // so in this case, allow the user to set a longer timeout (5 seconds should be plenty.) IOK 2022-07-01
        // Yes, it will block until timeout even if non-blocking is set. IOK 2022-07-01
        $timeout = apply_filters('woo_vipps_asynch_timeout', 0.5);
        $data = [ 'action'=> 'woo_vipps_order_management','orderid'=>$orderid, 'orderkey' => $order->get_order_key() ];
        $args = array( "method" => "POST", "body"=>$data, "timeout"=>$timeout,  "blocking" => false);
        $url = admin_url('admin-post.php');
        $asynch = wp_remote_request($url,$args);
        if (is_wp_error($asynch))  {
            $this->log(__("Error calling the Order Management API: %1\$s ", 'woo-vipps'), $asynch->get_error_message());
        }
    }

    // The below actions *may* be long-running, and we can't be sure
    // they will happen at callback-time. Some user may be affected, even if using wp-cron.
    // We therefore set them up to be run at shutdown, as soon as payment complete is done
    public function payment_complete_at_shutdown ($orderid, $orderkey) {
        // Ensure consistent environment here
        global $Vipps;
        if (!$Vipps) $Vipps = Vipps::instance();
        $order = wc_get_order($orderid);
        if (!is_a($order, 'WC_Order')) {
            return false;
        }
        if ($order->get_payment_method() != 'vipps') return false;
        if ($order->get_order_key() != wc_clean($orderkey)) {
            return false;
        }
        try {
 
            $sendreceipt = apply_filters('woo_vipps_send_receipt', ($this->get_option('sendreceipts') == 'yes'), $order);
            if ($sendreceipt) {
                $this->api->add_receipt($order);
                $this->order_add_vipps_categories($order);
            }
            do_action('woo_vipps_payment_complete_at_shutdown', $order, $this);
        } catch (Exception $e) {
            // This is/should be non-critical so just log it.
            $this->log(sprintf(__("Could not do all payment-complete actions on %1\$s order %2\$d: %3\$s ", 'woo-vipps'), Vipps::CompanyName(), $orderid,  $e->getMessage()), "error");
        }
    }

    // This is run on payment complete. Per default will it only add a link to the order confirmation page, but 
    // the hook added should be used if selling tickets, bookings etc to add these to the Vipps app receipt; with images if desired (eg. QR images).
    public function order_add_vipps_categories ($order) {
        if (!is_a($order, 'WC_Order')) return;
        
        $none = ['link'=>null, 'image'=>null, 'imagesize'=>null];
        $orderconfirmation = ['link' => $this->get_return_url($order), 'image' => null, 'imagesize'=>null];

        $receipt_image = $this->get_option('receiptimage');
        if ($receipt_image) {
            $orderconfirmation['image'] = intval($receipt_image);
            $orderconfirmation['imagesize'] = 'full';
        }

        // Do these in this order, in case we get terminated at some point during processing
        $default = ['TICKET'=>$none,'ORDER_CONFIRMATION' => $orderconfirmation, 'RECEIPT' => $none,"BOOKING" => $none, "DELIVERY" => $none, "GENERAL" => $none];
        $categories = apply_filters('woo_vipps_add_order_categories', $default, $order, $this);

        foreach ($categories as $category=>$data) {
            if ($data['link']) {
                $this->order_add_vipps_category($category, $order, $data['link'], $data['image'], $data['imagesize']);
            }
            // Let people handle this in other ways if not using filter above IOK 2022-07-01
            do_action('woo_vipps_add_order_category', $category, $order, $this);
            
        }
    }

    // Use the Order Management API to add a link with a category name and an optional image, which is viewable in the order.
    public function order_add_vipps_category($categoryname, $order, $link, $image=null, $imagesize='medium') {
        $imageid = $image ? $this->add_vipps_image($image, $imagesize) : null;
        return $this->api->add_category($order, $link, $imageid, $categoryname);
    }

    // Use the Order Management API to upload an image that can be attached to the order.
    public function add_vipps_image ($imagespec, $size = 'medium')  {
        // Imagespec is an attachmend id (of an image) or a filename (to an image), and a size if using an attachment id.
        $filename = null;
        $imageid = 0;
        $imagefile = "";
        $mime = "";
        $accepted_types = ["image/jpeg", "image/png", "image/jpg"]; // Only accepted types at Vipps
        $vippsid = null;
        $uploads = wp_get_upload_dir();

        if (is_numeric($imagespec)) {
           // Don't send same image twice if we have an id
           $stored = get_post_meta($imagespec, '_vipps_imageid', true);
           if ($stored && !$this->is_test_mode()) {
               return $stored;
           }
           $imageid = intval($imagespec);
           $imagefile = get_attached_file($imageid);
           $mime = get_post_mime_type($imageid);
        }

        if (!is_file($imagefile) || !in_array($mime, $accepted_types)) {
           $this->log(sprintf(__('%1$s is not an image that can be uploaded to %2$s', 'woo-vipps'), $imagefile, Vipps::CompanyName()), 'error');
           $this->log(sprintf(__('File type was %1$s; supported types are %2$s', 'woo-vipps'), $mime, join(", ", $accepted_types)), 'error');
           return null;
        }

        if ($imageid) {
            $intermediate = image_get_intermediate_size($imageid, $size);
            if ($intermediate && isset($intermediate['path'])) {
                $imagefile = join(DIRECTORY_SEPARATOR, [$uploads['basedir'] , $intermediate['path']]);
            }
        }

        if ($imagefile) {
            // Check image dimensions before uploading
            $dimensions = getimagesize($imagefile);
            if ($dimensions && $dimensions[1] < 167) { // [1] is height
                $this->log(sprintf(__('Image %1$s is too small - height %2$dpx (minimum 167px required)', 'woo-vipps'), 
                    $imagefile, $dimensions[1]), 'error');
                return null;
            }

            $vippsid = $this->api->add_image($imagefile);
            if ($vippsid) {
                update_post_meta($imageid, '_vipps_imageid', $vippsid);
            }
        }
        return $vippsid;
    }

    // For the express checkout mechanism, create a partial order without shipping details by simulating checkout->create_order();
    // IOK 2018-05-25
    public function create_partial_order($ischeckout=false) {
        // This is neccessary for some plugins, like Yith Dynamic Pricing, that adds filters to get_price depending on whether or not ischeckout is true.
        // so basically, since we are impersonating WC_Checkout here, we should define this constant too. IOK 2020-07-03
        wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true );

         // In *some* cases you may need to actually load classes and reload the cart, because some plugins do not load when DOING_AJAX.
        do_action('woo_vipps_express_checkout_before_calculate_totals');
        WC()->cart->calculate_fees();
        WC()->cart->calculate_totals();
        do_action('woo_vipps_before_create_express_checkout_order', WC()->cart);

        // We store this in the order so we don't have to access the cart when initiating payment. This allows us to restart orders etc.
        $needs_shipping = WC()->cart->needs_shipping();

        $contents = WC()->cart->get_cart_contents();
        $contents = apply_filters('woo_vipps_create_express_checkout_cart_contents',$contents);
        try {
            $cart_hash = md5(json_encode(wc_clean($contents)) . WC()->cart->total);
            $order = new WC_Order();
            $order->set_status('pending');
            $order->set_payment_method($this);
            if ($ischeckout) {
                $order->update_meta_data('_vipps_api', 'epayment');
                $order->set_payment_method_title('Vipps Checkout');
            } else {
                $order->set_payment_method_title('Vipps Express Checkout');
            }
            // We use 'checkout' as the created_via key as per requests, but allow merchants to use their own. IOK 2022-09-15
            $created_via = apply_filters('woo_vipps_express_checkout_created_via', 'checkout', $order, $ischeckout);
            $order->set_created_via($created_via);

            $dummy = sprintf(__('Vipps Express Checkout', 'woo-vipps')); //  this is so gettext will find this string.
            $dummy = sprintf(__('Vipps Checkout', 'woo-vipps')); //  this is so gettext will find this string.

            $order->update_meta_data('_vipps_express_checkout',1);

            # To help with address fields, scope etc in inititate payment
            $order->update_meta_data('_vipps_needs_shipping', $needs_shipping);

            $order->set_customer_id( apply_filters('woocommerce_checkout_customer_id', get_current_user_id() ) );
            $order->set_currency( get_woocommerce_currency() );
            $order->set_prices_include_tax('yes' === get_option('woocommerce_prices_include_tax') );
            $order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
            $order->set_customer_user_agent( wc_get_user_agent() );
            $order->set_discount_total( WC()->cart->get_discount_total()); 
            $order->set_discount_tax( WC()->cart->get_discount_tax() );
            $order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );

            // Use these methods directly - they should be safe.
            WC()->checkout->create_order_line_items( $order, WC()->cart);
            WC()->checkout->create_order_fee_lines( $order, WC()->cart);
            WC()->checkout->create_order_tax_lines( $order, WC()->cart);
            WC()->checkout->create_order_coupon_lines( $order, WC()->cart);
            do_action('woo_vipps_before_calculate_totals_partial_order', $order);
            $order->calculate_totals(true);

            // Added to support third-party plugins that wants to do stuff with the order before it is saved. IOK 2020-07-03
            do_action('woocommerce_checkout_create_order', $order, array()); 

            $orderid = $order->save(); 

            do_action('woo_vipps_express_checkout_order_created', $orderid);

            // Normally done by the WC_Checkout::create_order method, so call it here too. IOK 2018-11-19
            do_action('woocommerce_checkout_update_order_meta', $orderid, array());

            // It isn't possible to remove the javascript or 'after order notice' actions, because these are added as closures
            // before anything else is run. But we can disable the hook that saves data. IOK 2024-01-18
            if (WC_Gateway_Vipps::instance()->get_option('vippsorderattribution') != 'yes') {
                remove_all_filters( 'woocommerce_order_save_attribution_data');
            }

            // And another one. IOK 2021-11-24
            do_action('woocommerce_checkout_order_created', $order );
        } catch ( Exception $e ) {
            if ( $order && $order instanceof WC_Order ) {
                $order->get_data_store()->release_held_coupons( $order );
                do_action('woocommerce_checkout_order_exception', $order );
            }
            // Any errors gets passed upstream IOK 2021-11-24
            throw $e;
        }
        return $orderid;
    }

    // The order attribution subsystem of Woo requires extra work for our partial orders. IOK 2024-01-09
    // The attribution data is added to the order forms with prefixes (after 'order note') and we need to strip the prefix.
    public function get_order_attribution_data($input_data) {
        $prefix = (string) apply_filters( 'wc_order_attribution_tracking_field_prefix', 'wc_order_attribution_');
        $prefix = trim( $prefix, '_' ) . "_";
        $len = strlen($prefix);
        $params = [];
        foreach($input_data as $key=>$val) {
            $found = strpos($key, $prefix);
            if ($found === 0) {
                $paramkey = substr($key, $len);
                $params[$paramkey] = $val;
            }
        }
        return $params;
    }
             
    public function save_session_in_order($order) {
        // The callbacks from Vipps carry no session cookie, so we must store this in the order and use a special session handler when in a callback.
        // The Vipps class will restore the session from this on callbacks.
        // IOK 2019-10-21
        $sessioncookie = array();
        $sessionhandler = WC()->session;
        if ($sessionhandler && is_a($sessionhandler, 'WC_Session_Handler')) {
            // If customer is actually logged in, take note IOK 2019-10-25
            WC()->session->set('express_customer_id',get_current_user_id());
            WC()->session->save_data();
            $sessioncookie=$sessionhandler->get_session_cookie();
        }  else {
            // This actually can't happen. IOK 2020-04-08. Branch added for debugging only.
        }

        if (!empty($sessioncookie)) {
          // Customer id, session expiration, session-epiring and cookie-hash is the contents. IOK 2019-10-21
          $order->update_meta_data('_vipps_sessiondata',json_encode($sessioncookie));
          $order->save();
        }

    }

    // Using this internally to allow the 'enable' button or not. Checks SSL in addition to currency,
    // is valid_for_use can in principle run on a http version of the page; we only need to have https accessible for callbacks,
    // but if so, admin should definitely be HTTPS so we just check that. IOK 2018-06-06
    public function can_be_activated () {
        if (!is_ssl() && !preg_match("!^https!i",home_url())) return false;
        return true;
    }

    // Used by the ajax thing that 'sets activated' - checks that it can be activated and that all keys are present. IOK 2018-06-06
    function needs_setup() {
        if (!$this->can_be_activated()) return true;
        $required = array('merchantSerialNumber','clientId', 'secret',  'Ocp_Apim_Key_eCommerce'); 
        foreach ($required as $key) {
            if (!$this->get_option($key)) return true;
        }
        return false;
    }

   // Not present in WooCommerce until 3.4.0. Should be deleted when required versions are incremented. IOK 2018-10-26
    public function update_option( $key, $value = '') {
                if ( empty( $this->settings ) ) {
                        $this->init_settings();
                }
                $this->settings[ $key ] = $value;
                return update_option( $this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes');
    }


    public function admin_options() {
        $currency = get_woocommerce_currency(); 
        $payment_method = $this->get_payment_method_name();
        
        if (!$this->can_be_activated()) {
            $this->update_option('enabled', 'no');
        }

        if ($payment_method != "Vipps"): ?> 
        <style>
          #woocommerce_vipps_express_options, #woocommerce_vipps_express_options-table  {
           display: none;
          }
        </style>
        <?php endif; ?>

            <h2 id='vipps-settings-page'><?php echo __(Vipps::CompanyName(),'woo-vipps'); ?> <img style="float:right;max-height:40px;margin-top:-15px" alt="<?php _e($this->title,'woo-vipps'); ?>" src="<?php echo $this->icon; ?>"></h2>
            <?php $this->display_errors(); ?>

            <?php 

            
        if (!$this->payment_method_supports_currency($payment_method, $currency)):
            ?> 
                <div class="inline error">
                <p><strong><?php echo sprintf(__('%1$s does not support your currency.', 'woo-vipps'), $payment_method); ?></strong>
                <br>
                <?php echo sprintf(__('%1$s supported currencies: %2$s', 'woo-vipps'), $payment_method, implode(", ", $this->get_supported_currencies($payment_method))); ?>                
                </p>
                </div>
        <?php endif; ?>

        <?php if (!is_ssl() &&  !preg_match("!^https!i",home_url())): ?>
                <div class="inline error">
                <p><strong><?php _e('Gateway disabled', 'woocommerce'); ?></strong>:
                <?php echo sprintf(__('%1$s requires that your site uses HTTPS.', 'woo-vipps'), $payment_method); ?>
                </p>
                </div>
        <?php endif; ?>
    
        <?php // We will only show the Vipps Checkout options if the user has activated the feature (thus creating the pages involved etc). IOK 2021-10-01
        $vipps_checkout_activated = get_option('woo_vipps_checkout_activated', false);
 
        if (!$vipps_checkout_activated): ?>
        <div id="activate_vipps_checkout"  style="width:95%; background-color: white; border:2px solid #fe5b24; min-height:3rem; padding: 1rem 1rem 1rem 1rem; margin-top:2rem; margin-bottom: 1rem;font-weight:800">
                 <h2><?php printf(__('Use %1$s for all purchases', 'woo-vipps'), Vipps::CheckoutName()) ;?></h2>
          <p><?php printf(__("%1\$s is a new service from %2\$s which replaces the usual WooCommerce checkout page entirely, replacing it with a simplified checkout screen providing payment both with %2\$s and credit card. Additionally, your customers will get the option of providing their address information using their %2\$s app directly.", 'woo-vipps'), Vipps::CheckoutName(), Vipps::CompanyName()); ?></p>
          <p><?php printf(__("To activate %1\$s, just press the button below. Otherwise, %2\$s will of course be available in the regular checkout screen; and you can also offer %3\$s from both the product pages and the shopping cart if you wish.", 'woo-vipps'), Vipps::CheckoutName(), Vipps::CompanyName(), Vipps::ExpressCheckoutName()); ?></p>

          <div style="text-align:center">
                 <a class="button vipps-button vipps-orange" style="background-color: #fe5b24;color:white;border-color:#fe5b24" href="javascript:void(0)" onclick="javascript:activate_vipps_checkout(1)"><?php printf(__('Yes, activate %1$s!','woo-vipps'), Vipps::CheckoutName()); ?></a>
                 <span style="width:30%; height:1rem;display:inline-block"></span>
                 <a class="button vipps-button secondary" href="javascript:void(0)" onclick="javascript:activate_vipps_checkout(0)"><?php _e("No, thank you not right now anyway", 'woo-vipps'); ?></a>
          </div>
<script>
function activate_vipps_checkout(yesno) {
  var nonce = <?php echo json_encode(wp_create_nonce('woo_vipps_activate_checkout')); ?>;
  var referer = jQuery('input[name="_wp_http_referer"]').val();
  var args = { '_wpnonce' : nonce, '_wp_http_referer' : referer, 'activate': yesno, 'action' : 'woo_vipps_activate_checkout_page' }

  jQuery("#activate_vipps_checkout .button.vipps-button").css('cursor', 'wait');
  jQuery("#activate_vipps_checkout .button.vipps-button").prop('inactive', true);
  jQuery("#activate_vipps_checkout .button.vipps-button").prop('disabled', true);
  jQuery("#activate_vipps_checkout .button.vipps-button").addClass('disabled');


  jQuery.ajax(<?php echo json_encode(admin_url('admin-ajax.php')); ?>, { 
            method: 'POST',
            data: args,
            error: function (jqXHR, stat, err) {
            },
            success: function  (data, stat, jqXHR) {
            },
            complete: function (xhr, stat)  {
               document.body.style.cursor = 'default';
               jQuery("#activate_vipps_checkout .button.vipps-button").css('cursor', 'default');
               window.location.replace(window.location.pathname + window.location.search + window.location.hash);
            }
    }
   );
   return false;
}
</script>


        </div>
        <?php endif; ?>
                <table class="form-table">
                <?php $this->generate_settings_html(); ?>
                </table> <?php
    }

    // Validate/mangle input fields 
    function validate_text_field ($key, $value) {
        if ($key != 'orderprefix') return parent::validate_text_field($key,$value);
        $value = preg_replace('![^a-zA-Z0-9]!','',$value);
        return $value;
    }
    function validate_checkbox_field($key,$value) {
        if ($key == 'testmode' && VIPPS_TEST_MODE) {
              return "yes";    
        } else if ($key == 'developermode' && VIPPS_TEST_MODE) {
              return "yes";    
        } else if ($key == 'enabled') { 
              if ($value && $this->can_be_activated()) return 'yes';
              return "no";
        }
        return parent::validate_checkbox_field($key,$value);
    }

    function process_admin_options () {
        // Handle options updates
        $saved = parent::process_admin_options();
        // We may have changed the number of form fields at this point if dev mode was changed 
        // from off to on,so re-initialize the form fields here. IOK 2019-09-03
        $this->init_form_fields();

        // Reinitialize keysets in case user added/changed these
        $this->keyset = null;
        delete_transient('_vipps_keyset');
        $keyset = $this->get_keyset();

        // IOK FIXME check if we are called using ajax for this; and if so add the notifications to a list of notifications 
        // instead of doing the adminerr/adminnotify thing. IOK 2024-01-03

        list($ok,$msg)  = $this->check_connection();
        if ($ok) {
                $this->adminnotify(sprintf(__("Connection to %1\$s is OK", 'woo-vipps'), Vipps::CompanyName()));
        } else {
                $this->adminerr(sprintf(__("Could not connect to %1\$s", 'woo-vipps'), Vipps::CompanyName()) . ": $msg");
        }

        if ($ok) {
            $this->check_webhooks();
            // Try to ensure we have webhooks defined for the epayment-api IOK 2023-12-19
            $hooks = $this->initialize_webhooks();
        }

        // If enabling this, ensure the page in question exists
        if ($this->get_option('vipps_checkout_enabled') == 'yes') {
            update_option('woo_vipps_checkout_activated', true, true); // This must be true here, but still, make sure
            Vipps::instance()->maybe_create_vipps_pages();
        }

        return $saved;
    }

    // Check our stored webhooks for consistency, which means the callback URLs should point to *this* site. If they don't,
    // delete all the ones pointing wrong. If this returns false, you should reinitialize the webhooks. IOK 2023-12-20
    public function check_webhooks () {
        $local_hooks = get_option('_woo_vipps_webhooks');
        if (!$local_hooks) return false;

        $callback = $this->webhook_callback_url();
        $callback_compare = strtok($callback, '?');
        $problems = false;

        // We are going to re-initialize our webhooks after this, but just to ensure we're not keeping any 'stale' hooks,
        // we'll update the local hooks too. IOK 2025-02-13
        $change = false;
        $msns = array_keys($local_hooks);
        foreach($msns as $msn) {
           $hooks = $local_hooks[$msn];
           $ids = array_keys($hooks);
           foreach ($ids as $id) {
               $hook = $hooks[$id];
               $noargs = strtok($hook['url'], '?');
               if ($noargs == $callback_compare) continue; // This hook is good, probably, unless somebody has deleted it
               try {
                   $this->log(sprintf(__("For msn %s we have a webhook %s %s which is pointed the wrong way (%s) for this website", 'woo-vipps'), $msn, $hook['id'], $hook['url'], $callback_compare));
                   $this->api->delete_webhook($msn, $hook['id']); // This isn't - it's pointed the wrong way, which means we have changed name of the site or something
               } catch (Exception $e) {
                   $this->log(sprintf(__("Could not delete webhook for this site with url '%2\$s' : %1\$s", 'woo-vipps'), $e->getMessage(), $noargs), 'error');
               }
               unset($hooks[$id]);
               $change = true;
               $problems = true;
           }
           
           if ($change) {
               if (empty($hooks)) { 
                 unset($local_hooks[$msn]);
               } else {
                 $local_hooks[$msn] = $hooks;
               }
           }
        }

        if ($change) {
           update_option('_woo_vipps_webhooks', $local_hooks, true);
        }

        if ($problems) return false;
        return true;
    }

    // Returns the local webhooks for the given msn. If the url has changed, it will return nothing. IOK 2023-12-19
    public function get_local_webhook($msn) {
        $local_hooks = get_option('_woo_vipps_webhooks');
        $hooks = $local_hooks[$msn] ?? [];
        $callback = $this->webhook_callback_url();
        $callback_compare = strtok($callback, '?');

        foreach ($hooks as $id=>$hook) {
            $noargs = strtok($hook['url'], '?');
            if ($noargs == $callback_compare) {
                return $hook;
            } else {
                // May want to log this somehow
            }
        }
        return null;
    }


    //  This is to be used in deactivate/uninstall - it deletes all webhooks for all MSNs for this instance
    // Unfortunatetly, we can't delete other msn's webhooks or webhooks pointing to other URLs. IOK 2023-12-20
    public function delete_all_webhooks() {
        delete_option('_woo_vipps_webhooks');
        $callback = $this->webhook_callback_url();
        $comparandum = strtok($callback, '?');
        $all_hooks = $this->get_webhooks_from_vipps();
        foreach($all_hooks as $msn => $data) {
            $hooks = $data['webhooks'] ?? [];
            foreach ($hooks as $hook) {
                $id = $hook['id'];
                $url = $hook['url'];
                $noargs = strtok($url, '?');
                if ($noargs != $comparandum) continue; // Some other shops hook, we will ignore it
                $ok = $this->api->delete_webhook($msn,$id);
            }
        }
    }

    // This will initalize the webhooks for this instance, for all MSNs that are configured.
    // Hooks that point to us that we *do not* know the secret for, have to be deleted.
    public function initialize_webhooks() {
       // IOK 2023-12-20 for the epayment api, we need to re-initialize webhooks at this point. 
       try {
           return $this->initialize_webhooks_internal();
       } catch (Exception $e) {
            $this->log(sprintf(__("Could not initialize webhooks for this site: %1\$s", 'woo-vipps'), $e->getMessage()), 'error');
           return [];
       }
    }

    private function initialize_webhooks_internal () {
        $local_hooks = get_option('_woo_vipps_webhooks');
        $all_hooks = $this->get_webhooks_from_vipps();
        $ourselves = $this->webhook_callback_url();
        $keysets = $this->get_keyset();
	
	// Ignore any extra arguments
        $comparandum = strtok($ourselves, '?');

        $change = false;

        // We may need to delete webhooks that have been orphaned. There should be exactly one
        // for this sites' callback, and we need to know its secret. All others should be deleted.
        $delenda = [];


        foreach($all_hooks as $msn => $data) {
            $hooks = $data['webhooks'] ?? [];
            $gotit = false;
            $locals = $local_hooks[$msn] ?? [];

	    foreach ($hooks as $hook) {
		    $id = $hook['id'];
		    $url = $hook['url'];
		    $noargs = strtok($url, '?');
		    if ($noargs != $comparandum) {
			    continue; // Some other shops hook, we will ignore it
		    }
		    $local = $locals[$id] ?? false;

		    // If we haven't gotten our hook yet, but we have a local hook now that we know a secret for, note it and continue
		    if (!$gotit && $local && isset($local['secret']))  {
			    $gotit = $local;
			    continue;
		    }
		    // Now we have a hook for our own msn and url, but either we don't know the secret or it is a duplicate. It should be deleted.
		    $delenda[] = $id;
	    }

            // Delete all the webhooks for this msn that we don't want
            foreach ($delenda as $wrong) {
                $change = true;
                $this->api->delete_webhook($msn,$wrong);
            }

            if ($gotit) {
                // Now if we got a hook, then we should *just* remember that for this msn. 
                $local_hooks[$msn] = array($gotit['id'] => $gotit);
            } else {
                // If not, we don't have a hook for this msn and site, so we need to (try to) create one
                // but only if the MSN is registered for the payment gateway 'vipps' ! IOK 2024-12-03
                $keys = $keysets[$msn] ?? [];
                $gateway = $keys['gw'] ?? 'vipps';

                if ($gateway == 'vipps') {
                    $change = true;
                    $result = $this->api->register_webhook($msn, $ourselves);
                    if ($result) {
                        $local_hooks[$msn] = [$result['id'] => ['id'=>$result['id'], 'url' => $ourselves, 'secret' => $result['secret']]];
                    }
                }
            }
        }
        update_option('_woo_vipps_webhooks', $local_hooks, true);


        if ($change) $all_hooks = $this->get_webhooks_from_vipps();

        return $all_hooks;
    }


    // Checks connection of the 'main' MSN IOK 2023-12-19
    public function check_connection ($msn = null) {
        if (!$msn) {
            $msn = $this->get_merchant_serial();
        }
        $at = $this->get_key($msn);
        $s = $this->get_secret($msn);
        $c = $this->get_clientid($msn);
        if ($at && $s && $c) {



            try {
                // First, test the client id / client secret which will give us an access token
                $token = $this->api->get_access_token($msn,'force');
                if ($token) {
                    // Then, call the webhooks api to check if the msn/sub key is ok
                    try {
                        $this->api->get_webhooks_raw($msn);
                        update_option('woo-vipps-configured', 1, true);
                        return array(true,'');
                    } catch (Exception $e) {
                        $msg = $e->getMessage();
                        if ($msg == "403 Forbidden") {
                            $msg= __("MSN or subscription key (or both) seem to be wrong: ", 'woo-vipps') . $msg;
                        }
                        update_option('woo-vipps-configured', 0, true);
                        return array(false, $msg);
                    }
                }

            } catch (Exception $e) {
                $msg = __("Your client key or secret is wrong.", 'woo-vipps');
                update_option('woo-vipps-configured', 0, true);
                return array(false, $msg);
            }
        }
        return array(false, ''); // No configuration
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

    // Ensure chosen name gets used in the checkout page IOK 2018-09-12
    public function get_title() {
     return apply_filters('woo_vipps_payment_method_title', $this->get_payment_method_name());
    }

    public function get_payment_method_name() {
        return $this->get_option('payment_method_name');
    }

    public function payment_fields() {
        // Use Billing Phone if it is required, otherwise ask for a phone IOK 2018-04-24
        // For v2 of the api, just let Vipps ask for then umber
        // IOK 2019-09-12 removed dead code only used for v1 of api
    print $this->get_option('description');
        return;
    }
    public function validate_fields() {
        return true;
    }


}

