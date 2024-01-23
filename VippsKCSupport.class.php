<?php
/*
   This is the VippsKCSupport class, a static class implementing support for the Klarna Checkout plugin, if installed.


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

class VippsKCSupport {

    // To be run in "plugins_loaded" - extend Klarna Checkout with support for the Vipps external payment method.
    public static function init() {
        add_filter( 'kco_wc_gateway_settings',        array('VippsKCSupport','form_fields'));
        add_filter( 'kco_wc_api_request_args',        array('VippsKCSupport','create_order_vipps' ), 90);
        add_filter( 'kco_wc_klarna_order_pre_submit', array('VippsKCSupport','canonicalize_phone_number'), 11 );
        add_action( 'init',                           array('VippsKCSupport','maybe_remove_other_gateway_button' ));
        add_action( 'kco_wc_before_submit',           array('VippsKCSupport','add_vipps_payment_method' ));
        add_action( 'woocommerce_checkout_order_processed', array('VippsKCSupport','reset_default_payment_method'), 10, 3);
        add_action( 'woo_vipps_before_redirect_to_vipps', array('VippsKCSupport','reset_default_payment_method'), 10, 1);
    }

    public static function form_fields( $settings ) {
        $settings['epm_vipps_settings_title'] = array(
                'title' => sprintf(__( 'External Payment Method - %1$s', 'woo-vipps' ), "Vipps"),
                'type'  => 'title',
                );
        $settings['epm_vipps_activate']           = array(
                'title'       => __( 'Activate', 'woo-vipps' ),
                'type'        => 'checkbox',
                'description' => sprintf(__( 'Activate %1$s as an external payment method for Klarna Checkout', 'woo-vipps' ), "Vipps"),
                'default'     => 'yes',
                );
        $settings['epm_vipps_name']           = array(
                'title'       => __( 'Name', 'woo-vipps' ),
                'type'        => 'text',
                'description' => sprintf(__( 'Title for %1$s payment method. This controls the title which the user sees in the checkout form.', 'woo-vipps' ), "Vipps"),
                'default'     => sprintf(__( '%1$s', 'woo-vipps' ), "Vipps"),
                );
        $settings['epm_vipps_description']    = array(
                'title'       => __( 'Description', 'woo-vipps' ),
                'type'        => 'textarea',
                'description' => sprintf(__( 'Description for %1$s payment method. This controls the description which the user sees in the checkout form.', 'woo-vipps' ), "Vipps"),
                'default'     => '',
                );
        $settings['epm_vipps_img_url']        = array(
                'title'       => __( 'Image url', 'woo-vipps' ),
                'type'        => 'text',
                'description' => sprintf(__( 'URL to the %1$s logo', 'woo-vipps' ), "Vipps"),
                'default'     => plugins_url('img/vipps-rgb-sort.png',__FILE__)
                );
        $settings['epm_vipps_disable_button'] = array(
                'title'       => __( 'Disable other gateway button', 'woo-vipps' ),
                'type'        => 'checkbox',
                'description' => __( 'Disables the "Select another Payment method" button on the Klarna Checkout.', 'woo-vipps' ),
                'default'     => 'no',
                );
        return $settings;
    }

    // Add Vipps as Payment Method to the KCO iframe.
    public static function create_order_vipps( $create ) {
        $merchant_urls    = KCO_WC()->merchant_urls->get_urls();
        $confirmation_url = $merchant_urls['confirmation'];

        $kco_settings = get_option( 'woocommerce_kco_settings' );
        $activate = isset( $kco_settings['epm_vipps_activate'] ) ? ($kco_settings['epm_vipps_activate'] == 'yes') : true;

        global $Vipps;
        $activate = apply_filters( 'woo_vipps_activate_kco_external_payment', ($activate && $Vipps->gateway()->is_available()));

        // Klarna will absolutely cache your external payment methods, so to be able to deactivate these based on the
        // cart, we must send this always
        if (!isset($create['external_payment_methods']) || !is_array($create['external_payment_methods'])) {
           $create['external_payment_methods'] = array();
        }
        if (!$activate) return $create;

        $name         = isset( $kco_settings['epm_vipps_name'] ) ? $kco_settings['epm_vipps_name'] : '';
        $image_url    = isset( $kco_settings['epm_vipps_img_url'] ) ? $kco_settings['epm_vipps_img_url'] : '';
        $description  = isset( $kco_settings['epm_vipps_description'] ) ? $kco_settings['epm_vipps_description'] : '';


        $klarna_external_payment = array(
                'name'         => $name,
                'redirect_url' => add_query_arg( 'kco-external-payment', 'vipps', $confirmation_url ),
                'image_url'    => $image_url,
                'description'  => $description,
                );

        if (!isset($create['external_payment_methods']) || !is_array($create['external_payment_methods'])) {
           $create['external_payment_methods'] = array();
        }
        $create['external_payment_methods'][] = $klarna_external_payment;

        // Ensure we don't do Vipps as the default pament method. This is checked in "woocommerce_checkout_order_processed" hook.
        WC()->session->set('vipps_via_klarna', 1);

        return $create;
    }


    // On checkout, before submit, if external payment is selected and Vipps is selected as external payment,
    // using javascript, set payment method to Vipps and ensure terms and conditions are selected too. IOK 
    public static function add_vipps_payment_method() {
        if ( isset( $_GET['kco-external-payment'] ) && 'vipps' == $_GET['kco-external-payment'] ) { ?>
            $('input#payment_method_vipps').prop('checked', true);
            // Check terms and conditions to prevent error.
            $('input#legal').prop('checked', true);
            <?php
            // In case other actions are needed, add more Javascript to this hook
            do_action('woo_vipps_klarna_checkout_support_on_submit_javascript');
        }
    }

    // Ensure, after order is processed, that default payment method isn't set to Vipps
    public static function reset_default_payment_method ($orderid, $post_data=null, $order=null) {  
        // If payment method is Vipps because we did an external payment call from Klarna, ensure Klarna Checkout is default payment method (kco). 2020-05-18 IOK
        if (WC()->session->get('vipps_via_klarna')) {
            WC()->session->set('chosen_payment_method', 'kco');
            WC()->session->set('vipps_via_klarna', 0);
        }
    }

    // Based on settings, remove "Select another payment method"
    public static function maybe_remove_other_gateway_button() {
        $kco_settings   = get_option( 'woocommerce_kco_settings' );
        $disable_button = isset( $kco_settings['epm_vipps_disable_button'] ) ? $kco_settings['epm_vipps_disable_button'] : 'no';
        $remove = ('yes' === $disable_button);

        // Let the user decide whether or not to use the 'use external payment method' button. IOK 2020-05-29
        // This is present for legacy reasons only, and is probably not the one you want. See the 'woo_vipps_activate_kco_exteral_payment' filter instad.  IOK 2021-05-14
        $remove = apply_filters('woo_vipps_remove_klarna_another_payment_button', $remove);

        if ($remove) {
            remove_action( 'kco_wc_after_order_review', 'kco_wc_show_another_gateway_button', 20 );
        }
    }

    // Change phone number format to the one expected by Vipps - no +47
    public static function canonicalize_phone_number( $klarna_order ) {
        if ( isset( $_GET['kco-external-payment'] ) && 'vipps' == $_GET['kco-external-payment'] ) {
            $country_code                         = '47';
            $phone_no                             = $klarna_order->billing_address->phone;
            $telefonnummer1                       = preg_replace( "/^\+{$country_code}/", '', $phone_no );
            $telefonnummer2                       = str_replace( ' ', '', $telefonnummer1 );
            $klarna_order->billing_address->phone = $telefonnummer2;
        }

        return $klarna_order;
    }

}
