<?php

defined( 'ABSPATH' ) || exit;

return apply_filters(
	'wc_vipps_recurring_settings',
	[
		'enabled'                          => [
			'title'       => __( 'Enable/Disable', 'woo-vipps' ),
			'label'       => __( 'Enable Vipps/MobilePay Recurring Payments', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title_brand'                      => [
			'type'  => 'title',
			'title' => __( 'Brand settings', 'woo-vipps' ),
		],
		'brand'                            => [
			'title'       => __( 'Brand', 'woo-vipps' ),
			'type'        => 'select',
			'description' => __( 'Controls the payment flow brand (Vipps or MobilePay).', 'woo-vipps' ),
			'default'     => '', // We set this in the init_form_fields function
			'options'     => [
				WC_Vipps_Recurring_Helper::BRAND_VIPPS     => __( 'Vipps', 'woo-vipps' ),
				WC_Vipps_Recurring_Helper::BRAND_MOBILEPAY => __( 'MobilePay', 'woo-vipps' )
			]
		],
		'auto_capture_mobilepay'           => [
			'type'        => 'checkbox',
			'title'       => __( 'Automatically capture payments made with MobilePay', 'woo-vipps' ),
			'label'       => __( 'Automatically capture payments made with MobilePay', 'woo-vipps' ),
			'description' => __( 'If this option is checked we will start automatically capturing MobilePay payments. This prevents reservations from being cancelled after 7 days.', 'woo-vipps' ),
			'default'     => 'no',
		],
		'description'                      => [
			'title'       => __( 'Description', 'woo-vipps' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout. {brand} is substituted with either Vipps or MobilePay.', 'woo-vipps' ),
			// translators: {brand}: brand title (Vipps or MobilePay)
			'default'     => __( 'Pay with {brand}.', 'woo-vipps' ),
		],
		'title_checkout'                   => [
			'type'        => 'title',
			'title'       => __( 'Checkout settings', 'woo-vipps' ),
			'description' => __( 'Vipps MobilePay Checkout replaces the normal WooCommerce checkout with an easier and more seamless checkout that allows you to pay with Vipps MobilePay (and soon also a credit card). Your customers will be able to provide their billing/shipping details directly from the Vipps MobilePay app. <br><br><strong>Important!</strong> Checkout for recurring payments <strong>only supports static shipping</strong> at the moment.', 'woo-vipps' ),
		],
		'checkout_enabled'                 => [
			'title'   => __( 'Enable/Disable', 'woo-vipps' ),
			'label'   => __( 'Enable Vipps/MobilePay Checkout', 'woo-vipps' ),
			'type'    => 'checkbox',
			'default' => 'no',
		],
		'checkout_cleanup_abandoned_orders'  => [
			'title'       => __( 'Cleanup changed/abandoned orders', 'woo-vipps' ),
			'label'       => __( 'Cleanup changed/abandoned orders', 'woo-vipps' ),
			'description' => __( 'Automatically clean up Checkout orders where the order was abandoned, or the specification changed (runs every hour).', 'woo-vipps' ),
			'type'        => 'checkbox',
			'default'     => 'no',
		],
		'title_api'                        => [
			'type'        => 'title',
			'title'       => __( 'API settings', 'woo-vipps' ),
			'description' => __( 'These settings control the connection between your store and Vipps MobilePay.', 'woo-vipps' ),
		],
		'merchant_serial_number'           => [
			'title'       => __( 'Merchant Serial Number (MSN)', 'woo-vipps' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant Serial Number your Vipps/MobilePay developer portal.', 'woo-vipps' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'client_id'                        => [
			'title'       => __( 'client_id', 'woo-vipps' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your Vipps/MobilePay developer portal.', 'woo-vipps' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_key'                       => [
			'title'       => __( 'client_secret', 'woo-vipps' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps/MobilePay developer portal.', 'woo-vipps' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'subscription_key'                 => [
			'title'       => __( 'Ocp-Apim-Subscription-Key', 'woo-vipps' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your Vipps/MobilePay developer portal.', 'woo-vipps' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'title_orders'                     => [
			'type'        => 'title',
			'title'       => __( 'Order settings', 'woo-vipps' ),
			'description' => __( 'Orders, renewals and status settings.', 'woo-vipps' ),
		],
		'order_prefix'                     => [
			'type'        => 'text',
			'title'       => __( 'OrderId prefix', 'woo-vipps' ),
			'label'       => __( 'OrderId prefix', 'woo-vipps' ),
			'description' => __( 'An alphanumeric text string to use as a prefix on checkout orders from your shop, to avoid duplicate order ids.', 'woo-vipps' ),
			'default'     => ''
		],
		'continue_shopping_link_page'      => [
			'type'             => 'page_dropdown',
			'title'            => __( '"Continue shopping" link', 'woo-vipps' ),
			'description'      => __( 'The page to redirect customers to when they click "Continue shopping" after a cancelled payment.', 'woo-vipps' ),
			'show_option_none' => __( '[Default] Shop or homepage', 'woo-vipps' )
		],
		'default_reserved_charge_status'   => [
			'type'        => 'select',
			'title'       => __( 'Default status to give orders with a reserved charge', 'woo-vipps' ),
			'description' => __( 'The status to give orders when the charge is reserved in Vipps/MobilePay (i.e. tangible goods). Notice: This option only counts for newly signed agreements by the customer. Use the setting below to set the default status for renewal orders.', 'woo-vipps' ),
			'default'     => 'wc-on-hold',
			'options'     => array_filter( wc_get_order_statuses(), static function ( $key ) {
				return in_array( $key, [ 'wc-processing', 'wc-on-hold' ] );
			}, ARRAY_FILTER_USE_KEY )
		],
		'default_renewal_status'           => [
			'type'        => 'select',
			'title'       => __( 'Default status to give pending renewal orders', 'woo-vipps' ),
			'description' => __( 'When a renewal order happens we have to wait a few days for the money to be drawn from the customer. This settings controls the status to give these renewal orders before the charge completes.', 'woo-vipps' ),
			'default'     => 'wc-processing',
			'options'     => array_filter( wc_get_order_statuses(), static function ( $key ) {
				return in_array( $key, [ 'wc-processing', 'wc-on-hold' ] );
			}, ARRAY_FILTER_USE_KEY )
		],
		'transition_renewals_to_completed' => [
			'type'        => 'checkbox',
			'title'       => __( 'Transition order status for renewals to "completed"', 'woo-vipps' ),
			'label'       => __( 'Transition order status for renewals to "completed"', 'woo-vipps' ),
			'description' => __( 'This option will make sure order statuses always transition to "completed" when the renewal charge is completed in Vipps/MobilePay.', 'woo-vipps' ),
			'default'     => 'no',
		],
		'title_cron'                       => [
			'type'        => 'title',
			'title'       => __( 'Cron settings', 'woo-vipps' ),
			'description' => __( 'We use webhooks in most instances, but they are not guaranteed to be reliable. In those cases we rely on cron.', 'woo-vipps' ),
		],
		'check_charges_amount'             => [
			'type'        => 'number',
			'title'       => __( 'Amount of charges to check per status check', 'woo-vipps' ),
			'description' => __( 'The amount of charges to check the status for in wp-cron per scheduled event. It is recommended to keep this between 5 and 100. The higher the value, the more performance issues you may run into.', 'woo-vipps' ),
			'default'     => 10,
		],
		'check_charges_sort_order'         => [
			'type'        => 'select',
			'title'       => __( 'Status checking sort order for charges', 'woo-vipps' ),
			'description' => __( 'The sort order we use when checking charges in wp-cron. Random sort order is the best for most use cases. Oldest first may be useful if you use synchronized renewals.', 'woo-vipps' ),
			'default'     => 'rand',
			'options'     => [
				'rand' => __( 'Random', 'woo-vipps' ),
				'asc'  => __( 'Oldest first', 'woo-vipps' ),
				'desc' => __( 'Newest first', 'woo-vipps' )
			]
		],
		'title_developer'                  => [
			'type'        => 'title',
			'title'       => __( 'Developer settings', 'woo-vipps' ),
			'description' => __( 'Developer, test, and debugging options.', 'woo-vipps' ),
		],
		'logging'                          => [
			'title'       => __( 'Logging', 'woo-vipps' ),
			'label'       => __( 'Log debug messages', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woo-vipps' ),
			'default'     => 'yes',
		],
		'test_mode'                        => [
			'title'       => __( 'Test mode', 'woo-vipps' ),
			'label'       => __( 'Enable test mode', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => __( 'Enabling this will route all API requests to the Vipps MobilePay test API.', 'woo-vipps' ),
			'default'     => 'no',
			'disabled'    => WC_VIPPS_RECURRING_TEST_MODE,
			'desc_tip'    => WC_VIPPS_RECURRING_TEST_MODE ? __( 'This value is being overriden by WC_VIPPS_RECURRING_TEST_MODE.', 'woo-vipps' ) : null,
		],
	]
);
