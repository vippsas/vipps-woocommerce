<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wc_vipps_recurring_settings',
	[
		'enabled'              => [
			'title'       => __( 'Enable/Disable', 'woo-vipps-recurring' ),
			'label'       => __( 'Enable Vipps Recurring Payments', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title'                => [
			'title'       => __( 'Title', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woo-vipps-recurring' ),
			'default'     => __( 'Vipps', 'woo-vipps-recurring' ),
			'desc_tip'    => true,
		],
		'description'          => [
			'title'       => __( 'Description', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woo-vipps-recurring' ),
			'default'     => __( 'Pay with Vipps.', 'woo-vipps-recurring' ),
			'desc_tip'    => true,
		],
		'client_id'            => [
			'title'       => __( 'client_id', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_key'           => [
			'title'       => __( 'client_secret', 'woo-vipps-recurring' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'subscription_key'     => [
			'title'       => __( 'Vipps-Subscription-Key', 'woo-vipps-recurring' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'logging'              => [
			'title'       => __( 'Logging', 'woo-vipps-recurring' ),
			'label'       => __( 'Log debug messages', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woo-vipps-recurring' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
		'cancelled_order_page' => [
			'type'             => 'page_dropdown',
			'title'            => __( 'Cancelled order redirect page', 'woo-vipps-recurring' ),
			'description'      => __( 'The page to redirect cancelled orders to.', 'woo-vipps-recurring' ),
			'desc_tip'         => true,
			'show_option_none' => __( 'Create a new page', 'woo-vipps-recurring' )
		],
	]
);
