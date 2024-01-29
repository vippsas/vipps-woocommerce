<?php

defined( 'ABSPATH' ) || exit;

return apply_filters(
	'wc_vipps_recurring_settings',
	[
		'enabled'                          => [
			'title'       => __( 'Enable/Disable', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'label'       => __( 'Enable Vipps/MobilePay Recurring Payments', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'brand'                         => [
			'title'       => __( 'Brand', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'select',
			'description' => __( 'Controls the payment flow brand (Vipps or MobilePay).', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => 'vipps',
			'options'     => [
				'vipps'     => 'Vipps',
				'mobilepay' => 'MobilePay'
			]
		],
		'description'                      => [
			'title'       => __( 'Description', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout. {brand} is substituted with either Vipps or MobilePay.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			// translators: {brand}: brand title (Vipps or MobilePay)
			'default'     => __( 'Pay with {brand}.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
		],
		'merchant_serial_number'           => [
			'title'       => __( 'Merchant Serial Number (MSN)', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant Serial Number your Vipps/MobilePay developer portal.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'client_id'                        => [
			'title'       => __( 'client_id', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your Vipps/MobilePay developer portal.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_key'                       => [
			'title'       => __( 'client_secret', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps/MobilePay developer portal.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'subscription_key'                 => [
			'title'       => __( 'Ocp-Apim-Subscription-Key', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps/MobilePay developer portal.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'cancelled_order_page'             => [
			'type'             => 'page_dropdown',
			'title'            => __( 'Cancelled order redirect page', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description'      => __( 'The page to redirect cancelled orders to.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'show_option_none' => __( 'Create a new page', 'vipps-recurring-payments-gateway-for-woocommerce' )
		],
		'default_reserved_charge_status'   => [
			'type'        => 'select',
			'title'       => __( 'Default status to give orders with a reserved charge', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'The status to give orders when the charge is reserved in Vipps/MobilePay (i.e. tangible goods). Notice: This option only counts for newly signed agreements by the customer. Use the setting below to set the default status for renewal orders.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => 'wc-on-hold',
			'options'     => array_filter( wc_get_order_statuses(), static function ( $key ) {
				return in_array( $key, [ 'wc-processing', 'wc-on-hold' ] );
			}, ARRAY_FILTER_USE_KEY )
		],
		'default_renewal_status'           => [
			'type'        => 'select',
			'title'       => __( 'Default status to give pending renewal orders', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'When a renewal order happens we have to wait a few days for the money to be drawn from the customer. This settings controls the status to give these renewal orders before the charge completes.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => 'wc-processing',
			'options'     => array_filter( wc_get_order_statuses(), static function ( $key ) {
				return in_array( $key, [ 'wc-processing', 'wc-on-hold' ] );
			}, ARRAY_FILTER_USE_KEY )
		],
		'transition_renewals_to_completed' => [
			'type'        => 'checkbox',
			'title'       => __( 'Transition order status for renewals to "completed"', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'label'       => __( 'Transition order status for renewals to "completed"', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'This option will make sure order statuses always transition to "completed" when the renewal charge is completed in Vipps/MobilePay.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => 'no',
		],
		'check_charges_amount'             => [
			'type'        => 'number',
			'title'       => __( 'Amount of charges to check per status check', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'The amount of charges to check the status for in wp-cron per scheduled event. It is recommended to keep this between 5 and 100. The higher the value, the more performance issues you may run into.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => 10,
		],
		'check_charges_sort_order'         => [
			'type'        => 'select',
			'title'       => __( 'Status checking sort order for charges', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'The sort order we use when checking charges in wp-cron. Random sort order is the best for most use cases. Oldest first may be useful if you use synchronized renewals.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => 'rand',
			'options'     => [
				'rand' => __( 'Random', 'vipps-recurring-payments-gateway-for-woocommerce' ),
				'asc'  => __( 'Oldest first', 'vipps-recurring-payments-gateway-for-woocommerce' ),
				'desc' => __( 'Newest first', 'vipps-recurring-payments-gateway-for-woocommerce' )
			]
		],
		'logging'                          => [
			'title'       => __( 'Logging', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'label'       => __( 'Log debug messages', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => 'yes',
		],
	]
);
