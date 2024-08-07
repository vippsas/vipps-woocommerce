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
		'title_brand'                      => [
			'type'  => 'title',
			'title' => __( 'Brand settings', 'vipps-recurring-payments-gateway-for-woocommerce' ),
		],
		'brand'                            => [
			'title'       => __( 'Brand', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'select',
			'description' => __( 'Controls the payment flow brand (Vipps or MobilePay).', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => '', // We set this in the init_form_fields function
			'options'     => [
				WC_Vipps_Recurring_Helper::BRAND_VIPPS     => __( 'Vipps', 'vipps-recurring-payments-gateway-for-woocommerce' ),
				WC_Vipps_Recurring_Helper::BRAND_MOBILEPAY => __( 'MobilePay', 'vipps-recurring-payments-gateway-for-woocommerce' )
			]
		],
		'auto_capture_mobilepay'           => [
			'type'        => 'checkbox',
			'title'       => __( 'Automatically capture payments made with MobilePay', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'label'       => __( 'Automatically capture payments made with MobilePay', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'If this option is checked we will start automatically capturing MobilePay payments. This prevents reservations from being cancelled after 7 days.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => 'no',
		],
		'description'                      => [
			'title'       => __( 'Description', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout. {brand} is substituted with either Vipps or MobilePay.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			// translators: {brand}: brand title (Vipps or MobilePay)
			'default'     => __( 'Pay with {brand}.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
		],
		'title_checkout'                   => [
			'type'        => 'title',
			'title'       => "<span style='color: #ff9800;' title='Use at your own risk'>[BETA]</span> " . __( 'Checkout settings', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'Vipps MobilePay Checkout replaces the normal WooCommerce checkout with an easier and more seamless checkout that allows you to pay with Vipps MobilePay (and soon also a credit card). Your customers will be able to provide their billing/shipping details directly from the Vipps MobilePay app.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
		],
		'checkout_enabled'                 => [
			'title'   => __( 'Enable/Disable', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'label'   => __( 'Enable Vipps/MobilePay Checkout', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'    => 'checkbox',
			'default' => 'no',
		],
		'order_prefix' => [
			'type'        => 'text',
			'title' => __('OrderId prefix', 'vipps-recurring-payments-gateway-for-woocommerce'),
			'label'       => __('OrderId prefix', 'vipps-recurring-payments-gateway-for-woocommerce'),
			'description' => __('An alphanumeric text string to use as a prefix on checkout orders from your shop, to avoid duplicate order ids.','vipps-recurring-payments-gateway-for-woocommerce'),
			'default'     => ''
		],
		'title_api'                        => [
			'type'        => 'title',
			'title'       => __( 'API settings', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'These settings control the connection between your store and Vipps MobilePay.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
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
		'title_orders'                     => [
			'type'        => 'title',
			'title'       => __( 'Order settings', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'Orders, renewals and status settings.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
		],
		'continue_shopping_link_page'      => [
			'type'             => 'page_dropdown',
			'title'            => __( '"Continue shopping" link', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description'      => __( 'The page to redirect customers to when they click "Continue shopping" after a cancelled payment.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'show_option_none' => __( '[Default] Shop or homepage', 'vipps-recurring-payments-gateway-for-woocommerce' )
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
		'title_cron'                       => [
			'type'        => 'title',
			'title'       => __( 'Cron settings', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'We use webhooks in most instances, but they are not guaranteed to be reliable. In those cases we rely on cron.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
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
		'title_developer'                  => [
			'type'        => 'title',
			'title'       => __( 'Developer settings', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'description' => __( 'Developer, test, and debugging options.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
		],
		'logging'                          => [
			'title'       => __( 'Logging', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'label'       => __( 'Log debug messages', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => 'yes',
		],
		'test_mode'                        => [
			'title'       => __( 'Test mode', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'label'       => __( 'Enable test mode', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Enabling this will route all API requests to the Vipps MobilePay test API.', 'vipps-recurring-payments-gateway-for-woocommerce' ),
			'default'     => 'no',
			'disabled'    => WC_VIPPS_RECURRING_TEST_MODE,
			'desc_tip'    => WC_VIPPS_RECURRING_TEST_MODE ? __( 'This value is being overriden by WC_VIPPS_RECURRING_TEST_MODE.', 'vipps-recurring-payments-gateway-for-woocommerce' ) : null,
		],
	]
);
