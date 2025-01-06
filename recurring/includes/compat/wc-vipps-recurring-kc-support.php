<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Kc_Support {
	/**
	 * Initialize Vipps/MobilePay Recurring KC Support class.
	 */
	public static function init() {
		add_filter( 'kco_wc_gateway_settings', [ __CLASS__, 'form_fields' ] );

		add_filter( 'kco_wc_api_request_args', [
			__CLASS__,
			'create_vipps_recurring_order'
		], 90 );

		add_filter( 'kco_wc_klarna_order_pre_submit', [
			__CLASS__,
			'canonicalize_phone_number'
		], 11 );

		add_action( 'init', [ __CLASS__, 'maybe_remove_other_gateway_button' ] );

		add_action( 'kco_wc_before_submit', [
			__CLASS__,
			'add_vipps_recurring_payment_method'
		] );

		add_action( 'woocommerce_payment_complete', [
			__CLASS__,
			'reset_default_payment_method'
		], 10 );

		add_filter( 'wc_vipps_recurring_transaction_id_for_order', [
			__CLASS__,
			'fix_transaction_id'
		], 10, 2 );

		add_action( 'wc_vipps_recurring_before_process_order_charge', [
			__CLASS__,
			'fix_payment_method_on_subscription'
		] );
	}

	/**
	 * Klarna messes up our transaction id by inserting their own. We don't want theirs!
	 */
	public static function fix_transaction_id( $transaction_id, $order ) {
		$_wc_klarna_order_id = WC_Vipps_Recurring_Helper::get_meta( $order, '_wc_klarna_order_id' );
		if ( $_wc_klarna_order_id === $transaction_id ) {
			return false;
		}

		return $transaction_id;
	}

	/**
	 * Fix 16.02.2022 - KCO did not set the correct external payment method on a subscription after completed payment
	 * KCO 2.6.4, WooCommerce 6.2.0
	 */
	public static function fix_payment_method_on_subscription( $order ) {
		$gateway = WC_Vipps_Recurring::get_instance()->gateway();

		$subscriptions = $gateway->get_subscriptions_for_order( $order );
		foreach ( $subscriptions as $subscription ) {
			if ( $subscription->get_payment_method() === 'kco' && ! empty( WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order ) ) ) {
				$subscription->set_payment_method( $gateway->id );
				$subscription->save();
			}
		}
	}

	/**
	 * Add custom setting fields to Klarna's Vipps/MobilePay Recurring settings.
	 */
	public static function form_fields( $settings ) {
		$settings['epm_vipps_recurring_settings_title'] = [
			'title' => __( 'External Payment Method - Vipps/MobilePay Recurring Payments', 'woo-vipps' ),
			'type'  => 'title',
		];

		$settings['epm_vipps_recurring_activate'] = [
			'title'       => __( 'Activate', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => __( 'Activate Vipps/MobilePay Recurring Payments as an external payment method for Klarna Checkout', 'woo-vipps' ),
			'default'     => 'yes',
		];

		$settings['epm_vipps_recurring_description'] = [
			'title'       => __( 'Description', 'woo-vipps' ),
			'type'        => 'textarea',
			'description' => __( 'Description for Vipps/MobilePay Recurring Payments method. This controls the description which the user sees in the checkout form.', 'woo-vipps' ),
			// translators: {brand}: brand name, Vipps or MobilePay
			'default'     => __( 'Remember: {brand} is always has no fees when paying businesses.', 'woo-vipps' ),
		];

		$settings['epm_vipps_recurring_img_url'] = [
			'title'       => __( 'Image url', 'woo-vipps' ),
			'type'        => 'text',
			'description' => __( 'URL to the Vipps/MobilePay logo', 'woo-vipps' ),
			'default'     => WC_VIPPS_RECURRING_PLUGIN_URL . '/assets/images/{brand}-logo-black.svg'
		];

		$settings['epm_vipps_recurring_disable_button'] = [
			'title'       => __( 'Disable other gateway button', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => __( 'Disables the "Select another Payment method" button in Klarna Checkout.', 'woo-vipps' ),
			'default'     => 'no',
		];

		return $settings;
	}

	/**
	 * Add Vipps/MobilePay Recurring to Klarna Checkout.
	 */
	public static function create_vipps_recurring_order( $create ) {
		$merchant_urls    = KCO_WC()->merchant_urls->get_urls();
		$confirmation_url = $merchant_urls['confirmation'];

		$kco_settings = get_option( 'woocommerce_kco_settings' );

		$activate = ( ! isset( $kco_settings['epm_vipps_recurring_activate'] ) || $kco_settings['epm_vipps_recurring_activate'] === 'yes' )
					&& WC_Vipps_Recurring::get_instance()->gateway_should_be_active();
		$activate = apply_filters( 'wc_vipps_recurring_activate_kco_external_payment', $activate );

		if ( ! isset( $create['external_payment_methods'] ) || ! is_array( $create['external_payment_methods'] ) ) {
			$create['external_payment_methods'] = [];
		}

		if ( ! $activate ) {
			return $create;
		}

		$gateway = WC_Vipps_Recurring::get_instance()->gateway();

		$image_url   = str_replace( '{brand}', $gateway->brand, $kco_settings['epm_vipps_recurring_img_url'] ?? '' );
		$description = str_replace( '{brand}', $gateway->title, $kco_settings['epm_vipps_recurring_description'] ?? '' );

		$gateway = [
			'name'         => $gateway->title,
			'redirect_url' => add_query_arg( [
				'kco-external-payment' => $gateway->id,
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
	 * Add the Vipps/MobilePay Recurring Payments method to Klarna
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

	/**
	 * Reset the default payment method back to KCO after a completed Vipps/MobilePay payment
	 */
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
	 * We need to remove + and all spaces from the phone number before handing it off to the Vipps/MobilePay API.
	 */
	public static function canonicalize_phone_number( $klarna_order ) {
		if ( isset( $_GET['kco-external-payment'] ) && 'vipps_recurring' === $_GET['kco-external-payment'] ) {
			$phone_number                         = preg_replace( "/^\+|\s/", '', $klarna_order->billing_address->phone );
			$klarna_order->billing_address->phone = $phone_number;
		}

		return $klarna_order;
	}
}
