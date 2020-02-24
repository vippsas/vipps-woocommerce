<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wc_vipps_recurring_settings',
	[
		'enabled'               => [
			'title'       => __( 'Enable/Disable', 'woo-vipps-recurring' ),
			'label'       => __( 'Enable Vipps Recurring Payments', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title'                 => [
			'title'       => __( 'Title', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woo-vipps-recurring' ),
			'default'     => __( 'Vipps', 'woo-vipps-recurring' ),
			'desc_tip'    => true,
		],
		'description'           => [
			'title'       => __( 'Description', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woo-vipps-recurring' ),
			'default'     => __( 'Pay with Vipps.', 'woo-vipps-recurring' ),
			'desc_tip'    => true,
		],
		'client_id'             => [
			'title'       => __( 'Live Client ID', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_key'            => [
			'title'       => __( 'Live Secret Key', 'woo-vipps-recurring' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'subscription_key'      => [
			'title'       => __( 'Live Subscription Key', 'woo-vipps-recurring' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'testmode'              => [
			'title'       => __( 'Test mode', 'woo-vipps-recurring' ),
			'label'       => __( 'Enable Test Mode', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woo-vipps-recurring' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
		'test_client_id'        => [
			'title'       => __( 'Test Client ID', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'test_secret_key'       => [
			'title'       => __( 'Test Secret Key', 'woo-vipps-recurring' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'test_subscription_key' => [
			'title'       => __( 'Test Subscription Key', 'woo-vipps-recurring' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'logging'               => [
			'title'       => __( 'Logging', 'woo-vipps-recurring' ),
			'label'       => __( 'Log debug messages', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woo-vipps-recurring' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
	]
);
