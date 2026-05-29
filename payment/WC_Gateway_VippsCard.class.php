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
require_once(dirname(__FILE__) . "/WC_Gateway_Vipps.class.php");


class WC_Gateway_VippsCard extends WC_Gateway_Vipps {
    public $form_fields = null;
    public $dev_form_fields = null;
    public $id = 'vipps_card';
    public $icon = ''; 
    public $has_fields = true;
    public $method_title = '';
    public $title = '';
    public $method_description = "";

    private static $instance = null;
    // This returns the singleton instance of this class
    public static function instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
    } 

    public function __construct() {
        $this->testapiurl = 'https://apitest.vipps.no';
        $this->apiurl = 'https://api.vipps.no';
        
        $this->method_description = sprintf(__('Offer card payments through %1$s as a payment method', 'woo-vipps'), Vipps::CompanyName());
        $this->method_title = sprintf(__('Card payments with %1$s','woo-vipps'), Vipps::CompanyName());
        $this->title = sprintf(__('Card payments with %1$s','woo-vipps'), Vipps::CompanyName());

        $this->icon = plugins_url('img/vmp-logo.png',__FILE__);

        $this->init_form_fields();
        $this->init_settings();

        $this->api = new VippsApi($this);
        $this->supports = array('products','refunds');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options') );

    }

    public function is_available() {
        if (!WC_Payment_Gateway::is_available()) return false;
        if (!$this->can_be_activated()) return false;
        if ($this->is_test_mode()) return false; // Only available in 'real' mode. IOK 2026-05-27

        $configured =  get_option('woo-vipps-configured', false);
        $ok = $configured;

        $ok = $ok && $this->payment_method_supports_currency($this->get_payment_method_name(), get_woocommerce_currency()) ;

        $ok = apply_filters('woo_vipps_card_payment_is_available', $ok, $this);
        return $ok;
    }


    public function get_icon () {
        $src =  plugins_url('img/cclogos.svg',__FILE__);
        return '<img src="' . esc_attr($src) . '" alt="' .  $this->get_title() . '">';
    }


    public function get_option($key, $empty_value = null ) {
        // Our own values
        if (isset($this->form_fields[$key])) return parent::get_option($key, $empty_value);

        // Or passthrough to our sister gateway
        $value = WC_Gateway_Vipps::instance()->get_option($key, $empty_value);
        return $value;
    }

    // Ensure chosen name gets used in the checkout page IOK 2018-09-12
    public function get_title() {
        $title = sprintf(__('Card payment with %1$s', 'woo-vipps'),  $this->get_payment_method_name());
        return apply_filters('woo_vipps_card_payment_method_title', $title);
    }

    public function init_form_fields() {
        $this->form_fields = array( 
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'woocommerce' ),
                    'label'       => sprintf(__('Enable card payments with %1$s', 'woo-vipps'), Vipps::CompanyName()),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                    ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    /* translators: company name */
                    'default' => sprintf(__('Pay with credit or debit card through %1$s', 'woo-vipps'), Vipps::CompanyName()),
                    ),

                );
    }

    public function process_admin_options () {
        // Handle options updates in the default class (not in _Vipps)
        $saved = WC_Payment_Gateway::process_admin_options();
        return;
    }



}

