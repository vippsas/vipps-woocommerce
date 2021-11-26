<?php

global $vipps_recurring;

if ( defined( 'KCO_WC_VERSION' ) && $vipps_recurring->gateway->enabled === 'yes' && class_exists( 'KCO' )
     && version_compare( KCO_WC_VERSION, '2.0.0', '>=' )
     && ! has_filter( 'kco_wc_api_request_args', 'kcoepm_create_vipps_recurring_order' ) ) {
	require_once __DIR__ . '/compat/wc-vipps-recurring-kc-support.php';
	WC_Vipps_Recurring_Kc_Support::init();
}
