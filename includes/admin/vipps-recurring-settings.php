<?php

defined( 'ABSPATH' ) || exit;

return apply_filters(
	'wc_vipps_recurring_settings',
	[
		'enabled'                => [
			'title'       => __( 'Enable/Disable', 'woo-vipps-recurring' ),
			'label'       => __( 'Enable Vipps Recurring Payments', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title'                  => [
			'title'       => __( 'Title', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woo-vipps-recurring' ),
			'default'     => __( 'Vipps', 'woo-vipps-recurring' ),
		],
		'description'            => [
			'title'       => __( 'Description', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woo-vipps-recurring' ),
			'default'     => __( 'Pay with Vipps.', 'woo-vipps-recurring' ),
		],
		'client_id'              => [
			'title'       => __( 'client_id', 'woo-vipps-recurring' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_key'             => [
			'title'       => __( 'client_secret', 'woo-vipps-recurring' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'subscription_key'       => [
			'title'       => __( 'Vipps-Subscription-Key', 'woo-vipps-recurring' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps developer portal.', 'woo-vipps-recurring' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'logging'                => [
			'title'       => __( 'Logging', 'woo-vipps-recurring' ),
			'label'       => __( 'Log debug messages', 'woo-vipps-recurring' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woo-vipps-recurring' ),
			'default'     => 'no',
		],
		'cancelled_order_page'   => [
			'type'             => 'page_dropdown',
			'title'            => __( 'Cancelled order redirect page', 'woo-vipps-recurrinsg' ),
			'description'      => __( 'The page to redirect cancelled orders to.', 'woo-vipps-recurring' ),
			'show_option_none' => __( 'Create a new page', 'woo-vipps-recurring' )
		],
		'default_renewal_status' => [
			'type'        => 'select',
			'title'       => __( 'Default status to give pending renewals', 'woo-vipps-recurring' ),
			'description' => __( 'When a renewal happens we have to wait a few days for the money to be drawn from the customer. You might want such orders to be on hold, or maybe you require them to be processing or completed. A failed charge will still become "failed" regardless of this setting.', 'woo-vipps-recurring' ),
			'default'     => 'wc-on-hold',
			'options'     => array_filter( wc_get_order_statuses(), static function ( $key ) {
				return in_array( $key, [ 'wc-processing', 'wc-on-hold', 'wc-completed' ] );
			}, ARRAY_FILTER_USE_KEY )
		],
	]
);
