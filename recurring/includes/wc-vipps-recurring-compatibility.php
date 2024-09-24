<?php

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', function () {
	if ( ! get_option( WC_Vipps_Recurring_Helper::OPTION_CONFIGURED, false ) ) {
		return;
	}

	require_once __DIR__ . '/compat/wc-vipps-recurring-all-products-support.php';
	WC_Vipps_Recurring_All_Products_Support::init();

	if ( defined( 'KCO_WC_VERSION' )
	     && class_exists( 'KCO' )
	     && version_compare( KCO_WC_VERSION, '2.0.0', '>=' )
	     && ! has_filter( 'kco_wc_api_request_args', [
			WC_Vipps_Recurring_Kc_Support::class,
			'kcoepm_create_vipps_recurring_order'
		] ) ) {

		require_once __DIR__ . '/compat/wc-vipps-recurring-kc-support.php';
		WC_Vipps_Recurring_Kc_Support::init();
	}
} );

