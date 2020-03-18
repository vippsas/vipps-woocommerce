<?php
/*
   This is the VippsKCSupport class, a static class implementing support for the Klarna Checkout plugin, if installed.

    This file is part of the WordPress plugin Checkout with Vipps for WooCommerce
    Copyright (C) 2018 WP Hosting AS

    Checkout with Vipps for WooCommerce is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Checkout with Vipps for WooCommerce is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.


 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class VippsKCSupport {

    // To be run in "plugins_loaded" - extend Klarna Checkout with support for the Vipps external payment method.
    public static function init() {
        add_filter( 'kco_wc_gateway_settings',        array('VippsKCSupport','form_fields'));
        add_filter( 'kco_wc_api_request_args',        array('VippsKCSupport','create_order_vipps' ));
        add_filter( 'kco_wc_klarna_order_pre_submit', array('VippsKCSupport','canonicalize_phone_number'), 11 );
        add_action( 'init',                           array('VippsKCSupport','maybe_remove_other_gateway_button' ));
        add_action( 'kco_wc_before_submit',           array('VippsKCSupport','add_vipps_payment_method' ));
    }

    public static function form_fields( $settings ) {
        $settings['epm_vipps_settings_title'] = array(
                'title' => __( 'External Payment Method - Vipps', 'woo-vipps' ),
                'type'  => 'title',
                );
        $settings['epm_vipps_name']           = array(
                'title'       => __( 'Name', 'woo-vipps' ),
                'type'        => 'text',
                'description' => __( 'Title for Vipps payment method. This controls the title which the user sees in the checkout form.', 'woo-vipps' ),
                'default'     => __( 'Vipps', 'woo-vipps' ),
                );
        $settings['epm_vipps_description']    = array(
                'title'       => __( 'Description', 'woo-vipps' ),
                'type'        => 'textarea',
                'description' => __( 'Description for Vipps payment method. This controls the description which the user sees in the checkout form.', 'woo-vipps' ),
                'default'     => '',
                );
        $settings['epm_vipps_img_url']        = array(
                'title'       => __( 'Image url', 'woo-vipps' ),
                'type'        => 'text',
                'description' => __( 'URL to the Vipps logo', 'woo-vipps' ),
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
        $name         = isset( $kco_settings['epm_vipps_name'] ) ? $kco_settings['epm_vipps_name'] : '';
        $image_url    = isset( $kco_settings['epm_vipps_img_url'] ) ? $kco_settings['epm_vipps_img_url'] : '';
        $description  = isset( $kco_settings['epm_vipps_description'] ) ? $kco_settings['epm_vipps_description'] : '';

        $klarna_external_payment = array(
                'name'         => $name,
                'redirect_url' => add_query_arg( 'kco-external-payment', 'vipps', $confirmation_url ),
                'image_url'    => $image_url,
                'description'  => $description,
                );

        $klarna_external_payment            = array( $klarna_external_payment );
        $create['external_payment_methods'] = $klarna_external_payment;

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

    // Based on settings, remove "Select another payment method"
    public static function maybe_remove_other_gateway_button() {
        $kco_settings   = get_option( 'woocommerce_kco_settings' );
        $disable_button = isset( $kco_settings['epm_vipps_disable_button'] ) ? $kco_settings['epm_vipps_disable_button'] : 'no';
        if ( 'yes' === $disable_button ) {
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
