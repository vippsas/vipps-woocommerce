<?php

defined( 'ABSPATH' ) || exit;

return apply_filters(
	'wc_vipps_recurring_settings',
	[
		'enabled'                          => [
			'title'       => __( 'Enable/Disable', 'woo-vipps' ),
			'label'       => sprintf( /* translators: %s: Vipps MobilePay */ __( 'Enable %s Recurring Payments', 'woo-vipps' ), Vipps::CompanyName() ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title_brand'                      => [
			'type'  => 'title',
			'title' => __( 'Payment method settings', 'woo-vipps' ),
		],
		'brand'                            => [
			'title'       => __( 'Payment method', 'woo-vipps' ),
			'type'        => 'select',
			'description' => __( 'Choose Vipps or MobilePay.', 'woo-vipps' ),
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
			'description' => __( 'Automatically collect reserved MobilePay payments before the reservations expire.', 'woo-vipps' ),
			'default'     => 'no',
		],
		'description'                      => [
			'title'       => __( 'Checkout description', 'woo-vipps' ),
			'type'        => 'text',
			'description' => __( 'The payment method description customers see at checkout. Use {brand} to insert Vipps or MobilePay.', 'woo-vipps' ),
			// translators: {brand}: brand title (Vipps or MobilePay)
			'default'     => __( 'Pay with {brand}.', 'woo-vipps' ),
		],
		'title_express_checkout'            => [
			'type'        => 'title',
			'title'       => __( 'Express checkout for subscriptions', 'woo-vipps' ),
			'description' => sprintf( /* translators: %s: Vipps MobilePay */ __( 'Let customers start a subscription from a product page, the cart or checkout using their details from %s.', 'woo-vipps' ), Vipps::CompanyName() )
				. '<br><br><strong>' . __( 'Important! Express checkout for subscriptions currently supports only static shipping.', 'woo-vipps' ) . '</strong> '
				. sprintf( /* translators: %s: Vipps MobilePay */ __( 'Use fixed-price or free shipping. Customers cannot choose shipping in the %s app.', 'woo-vipps' ), Vipps::CompanyName() ),
		],
		'express_enabled'                   => [
			'title'       => __( 'Enable/Disable', 'woo-vipps' ),
			'label'       => __( 'Enable Express checkout', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => __( 'Turning this off hides the buttons. Purchases already started can still finish.', 'woo-vipps' ),
			'default'     => 'no',
		],
		'express_cart_button'               => [
			'title'   => __( 'Cart and mini-cart', 'woo-vipps' ),
			'label'   => __( 'Cart and mini-cart', 'woo-vipps' ),
			'type'    => 'checkbox',
			'default' => 'yes',
		],
		'express_checkout_button'           => [
			'title'   => __( 'Checkout page', 'woo-vipps' ),
			'label'   => __( 'Checkout page', 'woo-vipps' ),
			'type'    => 'checkbox',
			'default' => 'yes',
		],
		'express_product_button'            => [
			'title'       => __( 'Supported products', 'woo-vipps' ),
			'type'        => 'select',
			'description' => sprintf( /* translators: %s: product tab name */ __( 'For selected products, enable Express checkout in the product\'s %s tab.', 'woo-vipps' ), sprintf( /* translators: %s: Vipps MobilePay */ __( '%s Recurring Payments', 'woo-vipps' ), Vipps::CompanyName() ) ),
			'default'     => 'all',
			'options'     => [
				'all'  => __( 'All subscription products', 'woo-vipps' ),
				'some' => __( 'Selected subscription products', 'woo-vipps' ),
			],
		],
		'express_product_page_button'       => [
			'title'   => __( 'Product pages', 'woo-vipps' ),
			'label'   => __( 'Product pages', 'woo-vipps' ),
			'type'    => 'checkbox',
			'default' => 'yes',
		],
		'express_archive_button'            => [
			'title'       => __( 'Catalog pages', 'woo-vipps' ),
			'label'       => __( 'Catalog pages', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => __( 'Show buttons on shop and category pages for the supported products above. Customers must open products with options before purchase.', 'woo-vipps' ),
			'default'     => 'yes',
		],
		'express_include_cart'              => [
			'title'       => __( 'Other cart items', 'woo-vipps' ),
			'label'       => __( 'Include other cart items', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => __( 'Include items already in the cart when customers buy from a product page.', 'woo-vipps' ),
			'default'     => 'no',
		],
		'express_terms'                     => [
			'title'       => __( 'Terms and conditions', 'woo-vipps' ),
			'label'       => __( 'Require terms acceptance', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => __( 'Set your terms and conditions page in WooCommerce settings.', 'woo-vipps' ),
			'default'     => 'no',
		],
		'express_always_address'            => [
			'title'       => __( 'Customer address', 'woo-vipps' ),
			'label'       => __( 'Always request an address', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => __( 'Also request an address for products that do not need shipping.', 'woo-vipps' ),
			'default'     => 'no',
		],
		'express_cleanup_abandoned_orders'  => [
			'title'       => __( 'Abandoned orders', 'woo-vipps' ),
			'label'       => __( 'Clean up abandoned orders', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => __( 'Move cancelled or expired orders to the trash after seven days. Orders with payments, customer details or an unconfirmed payment status are kept.', 'woo-vipps' ),
			'default'     => 'no',
		],
		// todo: remove the checkout settings in a future release.
		'title_checkout'                   => [
			'type'        => 'title',
			'title'       => __( 'Checkout settings', 'woo-vipps' ),
			'description' => __( 'Replace the WooCommerce checkout with Vipps MobilePay Checkout. Customers share their billing and shipping details from the app. <br><br><strong>Important!</strong> Checkout for recurring payments <strong>only supports static shipping</strong> at the moment.', 'woo-vipps' ),
		],
		'checkout_enabled'                 => [
			'title'   => __( 'Enable/Disable', 'woo-vipps' ),
			'label'   => __( 'Enable Vipps/MobilePay Checkout', 'woo-vipps' ),
			'type'    => 'checkbox',
			'default' => 'no',
		],
		'checkout_cleanup_abandoned_orders'  => [
			'title'       => __( 'Clean up changed or abandoned orders', 'woo-vipps' ),
			'label'       => __( 'Clean up changed or abandoned orders', 'woo-vipps' ),
			'description' => __( 'Move abandoned Checkout orders and orders with changed contents to the trash. Checks run every hour.', 'woo-vipps' ),
			'type'        => 'checkbox',
			'default'     => 'no',
		],
		'title_api'                        => [
			'type'        => 'title',
			'title'       => __( 'API settings', 'woo-vipps' ),
			'description' => sprintf( /* translators: %s: Vipps MobilePay */ __( 'These settings control the connection between your store and %s.', 'woo-vipps' ), Vipps::CompanyName() ),
		],
		'merchant_serial_number'           => [
			'title'       => __( 'Merchant Serial Number (MSN)', 'woo-vipps' ),
			'type'        => 'text',
			'description' => sprintf( /* translators: %s: Vipps MobilePay */ __( 'Find your Merchant Serial Number in the %s developer portal.', 'woo-vipps' ), Vipps::CompanyName() ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'client_id'                        => [
			'title'       => __( 'client_id', 'woo-vipps' ),
			'type'        => 'text',
			'description' => sprintf( /* translators: %s: Vipps MobilePay */ __( 'Get your API keys from your %s developer portal.', 'woo-vipps' ), Vipps::CompanyName() ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_key'                       => [
			'title'       => __( 'client_secret', 'woo-vipps' ),
			'type'        => 'password',
			'description' => sprintf( /* translators: %s: Vipps MobilePay */ __( 'Get your API keys from your %s developer portal.', 'woo-vipps' ), Vipps::CompanyName() ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'subscription_key'                 => [
			'title'       => __( 'Ocp-Apim-Subscription-Key', 'woo-vipps' ),
			'type'        => 'password',
			'description' => sprintf( /* translators: %s: Vipps MobilePay */ __( 'Get your API keys from your %s developer portal.', 'woo-vipps' ), Vipps::CompanyName() ),
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
			'title'       => __( 'Order ID prefix', 'woo-vipps' ),
			'label'       => __( 'Order ID prefix', 'woo-vipps' ),
			'description' => __( 'Add letters or numbers before each payment reference to keep references unique across stores.', 'woo-vipps' ),
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
			'title'       => __( 'Order status on payment reservation', 'woo-vipps' ),
			'description' => __( 'The order status when the first payment is reserved but not yet collected. Renewal orders use the setting below.', 'woo-vipps' ),
			'default'     => 'wc-on-hold',
			'options'     => array_filter( wc_get_order_statuses(), static function ( $key ) {
				return in_array( $key, [ 'wc-processing', 'wc-on-hold' ] );
			}, ARRAY_FILTER_USE_KEY )
		],
		'default_renewal_status'           => [
			'type'        => 'select',
			'title'       => __( 'Renewal order status while awaiting payment', 'woo-vipps' ),
			'description' => __( 'The status for renewal orders while waiting for payment. Payment can take a few days.', 'woo-vipps' ),
			'default'     => 'wc-processing',
			'options'     => array_filter( wc_get_order_statuses(), static function ( $key ) {
				return in_array( $key, [ 'wc-processing', 'wc-on-hold' ] );
			}, ARRAY_FILTER_USE_KEY )
		],
		'transition_renewals_to_completed' => [
			'type'        => 'checkbox',
			'title'       => __( 'Automatically complete paid renewal orders', 'woo-vipps' ),
			'label'       => __( 'Automatically complete paid renewal orders', 'woo-vipps' ),
			'description' => sprintf( /* translators: %s: Vipps MobilePay */ __( 'Set renewal orders to "Completed" when %s confirms payment.', 'woo-vipps' ), Vipps::CompanyName() ),
			'default'     => 'no',
		],
		'title_cron'                       => [
			'type'        => 'title',
			'title'       => __( 'Background checks', 'woo-vipps' ),
			'description' => __( 'Check payment status in the background to keep orders up to date.', 'woo-vipps' ),
		],
		'check_charges_amount'             => [
			'type'        => 'number',
			'title'       => __( 'Payments per check', 'woo-vipps' ),
			'description' => __( 'The number of payments to check at a time. Use 5 to 100. Higher values can slow down your store.', 'woo-vipps' ),
			'default'     => 10,
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
			'description' => __( 'Save messages to the WooCommerce logs to help with troubleshooting.', 'woo-vipps' ),
			'default'     => 'yes',
		],
		'test_mode'                        => [
			'title'       => __( 'Test mode', 'woo-vipps' ),
			'label'       => __( 'Enable test mode', 'woo-vipps' ),
			'type'        => 'checkbox',
			'description' => sprintf( /* translators: %s: Vipps MobilePay */ __( 'Use the %s test environment to try payments.', 'woo-vipps' ), Vipps::CompanyName() ),
			'default'     => 'no',
			'disabled'    => WC_VIPPS_RECURRING_TEST_MODE,
			'desc_tip'    => WC_VIPPS_RECURRING_TEST_MODE ? __( 'Test mode is controlled by WC_VIPPS_RECURRING_TEST_MODE.', 'woo-vipps' ) : null,
		],
	]
);
