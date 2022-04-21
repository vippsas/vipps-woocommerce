<?php

defined( 'ABSPATH' ) || exit;

if ( WC_Vipps_Recurring::get_instance()->gateway->enabled !== 'yes' ) {
	return;
}

add_action( 'wp_loaded', function () {
	if ( defined( 'KCO_WC_VERSION' )
	     && class_exists( 'KCO' )
	     && version_compare( KCO_WC_VERSION, '2.0.0', '>=' )
	     && ! has_filter( 'kco_wc_api_request_args', 'kcoepm_create_vipps_recurring_order' ) ) {
		require_once __DIR__ . '/compat/wc-vipps-recurring-kc-support.php';
		WC_Vipps_Recurring_Kc_Support::init();
	}

	if ( class_exists( 'WCS_ATT_Cart' ) ) {
		require_once __DIR__ . '/compat/wc-vipps-recurring-all-products-support.php';
		WC_Vipps_Recurring_All_Products_Support::init();
	}
} );
