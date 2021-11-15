<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Kc_Support {
	/**
	 * Initialize Vipps Recurring KC Support class.
	 */
	public static function init() {
		add_filter( 'kco_wc_gateway_settings', [ 'WC_Vipps_Recurring_Kc_Support', 'form_fields' ] );
		add_filter( 'kco_wc_api_request_args', [
			'WC_Vipps_Recurring_Kc_Support',
			'create_vipps_recurring_order'
		], 90 );
		add_filter( 'kco_wc_klarna_order_pre_submit', [
			'WC_Vipps_Recurring_Kc_Support',
			'canonicalize_phone_number'
		], 11 );
		add_action( 'init', [ 'WC_Vipps_Recurring_Kc_Support', 'maybe_remove_other_gateway_button' ] );
		add_action( 'kco_wc_before_submit', [ 'WC_Vipps_Recurring_Kc_Support', 'add_vipps_recurring_payment_method' ] );
		add_action( 'woocommerce_checkout_order_processed', [
			'WC_Vipps_Recurring_Kc_Support',
			'reset_default_payment_method'
		], 10, 3 );
	}

	/**
	 * Add custom setting fields to Klarna's Vipps Recurring settings.
	 *
	 * @param $settings
	 *
	 * @return mixed
	 */
	public static function form_fields( $settings ) {
		$settings['epm_vipps_recurring_settings_title'] = [
			'title' => __( 'External Payment Method - Vipps Recurring Payments', 'woo-vipps-recurring' ),
			'type'  => 'title',
		];

		$settings['epm_vipps_recurring_activate'] = [
			'title'       => __( 'Activate', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => __( 'Activate Vipps Recurring Payments as an external payment method for Klarna Checkout', 'woo-vipps-recurring' ),
			'default'     => 'yes',
		];

		$settings['epm_vipps_recurring_name'] = [
			'title'       => __( 'Name', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'Title for Vipps Recurring Payments method. This controls the title which the user sees in the checkout form.', 'woo-vipps-recurring' ),
			'default'     => __( 'Vipps', 'woo-vipps-recurring' ),
		];

		$settings['epm_vipps_recurring_description'] = [
			'title'       => __( 'Description', 'woo-vipps-recurring' ),
			'type'        => 'textarea',
			'description' => __( 'Description for Vipps Recurring Payments method. This controls the description which the user sees in the checkout form.', 'woo-vipps-recurring' ),
			'default'     => '',
		];

		$settings['epm_vipps_recurring_img_url'] = [
			'title'       => __( 'Image url', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'URL to the Vipps logo', 'woo-vipps-recurring' ),
			'default'     => plugins_url( 'assets/images/vipps-rgb-black.png', __FILE__ )
		];

		$settings['epm_vipps_recurring_disable_button'] = [
			'title'       => __( 'Disable other gateway button', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => __( 'Disables the "Select another Payment method" button in Klarna Checkout.', 'woo-vipps-recurring' ),
			'default'     => 'no',
		];

		return $settings;
	}

	/**
	 * Compatibility code to create a Vipps Recurring order in Klarna.
	 *
	 * @param $create
	 *
	 * @return mixed
	 */
	public static function create_vipps_recurring_order( $create ) {
		$merchant_urls = KCO_WC()->merchant_urls->get_urls();
		$confirmation_url = $merchant_urls['confirmation'];

//		// todo: REMOVE ME
//		$confirmation_url = str_replace( 'http://wordpress.test', 'https://98e1-141-0-97-106.ngrok.io', $confirmation_url );
//		// todo: END REMOVE ME

		$kco_settings = get_option( 'woocommerce_kco_settings' );
		$activate     = ! isset( $kco_settings['epm_vipps_recurring_activate'] ) || $kco_settings['epm_vipps_recurring_activate'] === 'yes';

		global $vipps_recurring;
		$activate = apply_filters( 'wc_vipps_recurring_activate_kco_external_payment', ( $activate && $vipps_recurring->gateway->is_available() ) );

		if ( ! isset( $create['external_payment_methods'] ) || ! is_array( $create['external_payment_methods'] ) ) {
			$create['external_payment_methods'] = [];
		}

		if ( ! $activate ) {
			return $create;
		}

		$name        = $kco_settings['epm_vipps_recurring_name'] ?? '';
		$image_url   = $kco_settings['epm_vipps_recurring_img_url'] ?? '';
		$description = $kco_settings['epm_vipps_recurring_description'] ?? '';


		$klarna_external_payment = [
			'name'         => $name,
			'redirect_url' => add_query_arg( 'kco-external-payment', 'vipps_recurring', $confirmation_url ),
			'image_url'    => $image_url,
			'description'  => $description,
		];

		if ( ! isset( $create['external_payment_methods'] ) || ! is_array( $create['external_payment_methods'] ) ) {
			$create['external_payment_methods'] = [];
		}
		$create['external_payment_methods'][] = $klarna_external_payment;

		// Ensure we don't do Vipps as the default payment method. This is checked in "woocommerce_checkout_order_processed" hook.
		WC()->session->set( 'vipps_recurring_via_klarna', 1 );

		return $create;
	}

	/**
	 * Add the Vipps Recurring Payments method to Klarna
	 */
	public static function add_vipps_recurring_payment_method() {
		if ( isset( $_GET['kco-external-payment'] ) && 'vipps_recurring' === $_GET['kco-external-payment'] ) { ?>
			$('input#payment_method_vipps_recurring').prop('checked', true);
			$('input#legal').prop('checked', true);
			<?php
			// In case other actions are needed, add more Javascript to this hook
			do_action( 'wc_vipps_recurring_klarna_checkout_support_on_submit_javascript' );
		}
	}

	/**
	 * Reset default payment method.
	 *
	 * @param $order_id
	 * @param $post_data
	 * @param $order
	 */
	public static function reset_default_payment_method( $order_id, $post_data, $order ) {
		if ( WC()->session->get( 'vipps_recurring_via_klarna' ) ) {
			WC()->session->set( 'chosen_payment_method', 'kco' );
			WC()->session->set( 'vipps_recurring_via_klarna', 0 );
		}
	}

	/**
	 * If the setting to remove "select an other gateway" is enabled we have to remove this button.
	 */
	public static function maybe_remove_other_gateway_button() {
		$kco_settings   = get_option( 'woocommerce_kco_settings' );
		$disable_button = $kco_settings['epm_vipps_recurring_disable_button'] ?? 'no';
		$remove         = ( 'yes' === $disable_button );

		// Let the user decide whether to use the 'use external payment method' button
		// This is present for legacy reasons only, and is probably not the one you want. See the 'woo_vipps_activate_kco_external_payment' filter instead.
		$remove = apply_filters( 'wc_vipps_recurring_remove_klarna_another_payment_button', $remove );

		if ( $remove ) {
			remove_action( 'kco_wc_after_order_review', 'kco_wc_show_another_gateway_button', 20 );
		}
	}

	/**
	 * @param $klarna_order
	 *
	 * We need to remove +47 and all spaces from the phone number before handing it off to the Vipps API.
	 *
	 * @return mixed
	 */
	public static function canonicalize_phone_number( $klarna_order ) {
		if ( isset( $_GET['kco-external-payment'] ) && 'vipps_recurring' === $_GET['kco-external-payment'] ) {
			$phone_number                         = preg_replace( "/^\+47|[\s]/", '', $klarna_order->billing_address->phone );
			$klarna_order->billing_address->phone = $phone_number;
		}

		return $klarna_order;
	}
}
