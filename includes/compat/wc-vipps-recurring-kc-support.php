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

		add_action( 'woocommerce_payment_complete', [
			'WC_Vipps_Recurring_Kc_Support',
			'reset_default_payment_method'
		], 10 );
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
			'default'     => WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/images/vipps-rgb-black.png'
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
	 * Add Vipps Recurring to Klarna Checkout.
	 *
	 * @param $create
	 *
	 * @return mixed
	 */
	public static function create_vipps_recurring_order( $create ) {
		$merchant_urls    = KCO_WC()->merchant_urls->get_urls();
		$confirmation_url = $merchant_urls['confirmation'];

		$kco_settings = get_option( 'woocommerce_kco_settings' );

		$activate = ! isset( $kco_settings['epm_vipps_recurring_activate'] ) || $kco_settings['epm_vipps_recurring_activate'] === 'yes';
		$activate = apply_filters( 'wc_vipps_recurring_activate_kco_external_payment', ( $activate && WC_Vipps_Recurring::get_instance()->gateway->is_available() ) );

		if ( ! isset( $create['external_payment_methods'] ) || ! is_array( $create['external_payment_methods'] ) ) {
			$create['external_payment_methods'] = [];
		}

		if ( ! $activate ) {
			return $create;
		}

		$name        = $kco_settings['epm_vipps_recurring_name'] ?? '';
		$image_url   = $kco_settings['epm_vipps_recurring_img_url'] ?? '';
		$description = $kco_settings['epm_vipps_recurring_description'] ?? '';

		$gateway = [
			'name'         => $name,
			'redirect_url' => add_query_arg( [
				'kco-external-payment' => 'vipps_recurring',
				'order_id'             => $create['agreement_id'] ?? '{checkout.order.id}'
			], $confirmation_url ),
			'image_url'    => $image_url,
			'description'  => $description,
		];

		$create['external_payment_methods'][] = $gateway;

		WC()->session->set( 'vipps_via_klarna', 1 );

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
			// In case other actions are needed we can add more Javascript to this hook
			do_action( 'wc_vipps_recurring_klarna_checkout_support_on_submit_javascript' );
		}
	}

	public static function reset_default_payment_method() {
		if ( WC()->session && WC()->session->get( 'vipps_via_klarna' ) ) {
			WC()->session->set( 'chosen_payment_method', 'kco' );
			WC()->session->set( 'vipps_via_klarna', 0 );
		}
	}

	/**
	 * If the setting to remove "select another gateway" is enabled we have to remove that button.
	 */
	public static function maybe_remove_other_gateway_button() {
		$kco_settings   = get_option( 'woocommerce_kco_settings' );
		$disable_button = isset( $kco_settings['epm_vipps_recurring_disable_button'] ) &&
						  $kco_settings['epm_vipps_recurring_disable_button'] === 'yes';

		if ( $disable_button ) {
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
