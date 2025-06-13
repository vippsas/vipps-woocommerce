<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Files.FileName

define( 'WC_VIPPS_RECURRING_VERSION', '2.1.9' );

/**
 * Polyfills
 */
if ( ! function_exists( 'array_key_first' ) ) {
	function array_key_first( array $arr ) {
		foreach ( $arr as $key => $unused ) {
			return $key;
		}

		return null;
	}
}

if ( ! function_exists( 'array_key_last' ) ) {
	function array_key_last( array $array ) {
		end( $array );

		return key( $array );
	}
}

require_once __DIR__ . '/includes/wc-vipps-recurring-helper.php';
require_once __DIR__ . '/includes/wc-vipps-recurring-logger.php';
require_once __DIR__ . '/includes/wc-vipps-recurring-admin-notices.php';
require_once __DIR__ . '/includes/wc-vipps-recurring.php';

/*
 * Required minimums and constants
 */
define( 'WC_VIPPS_RECURRING_MIN_PHP_VER', '7.4.0' );
define( 'WC_VIPPS_RECURRING_MIN_WC_VER', '3.0.0' );
define( 'WC_VIPPS_RECURRING_MAIN_FILE', __FILE__ );
#define( 'WC_VIPPS_RECURRING_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_VIPPS_RECURRING_PLUGIN_URL', plugins_url("", __FILE__));
define( 'WC_VIPPS_RECURRING_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

/* Note that this is the integrated version of the plugin IOK 2025-01-06*/
define( 'WC_VIPPS_RECURRING_INTEGRATED', true);

/*
 * Amount of days to retry a payment when creating a charge in the Vipps/MobilePay API
 */
if ( ! defined( 'WC_VIPPS_RECURRING_RETRY_DAYS' ) ) {
    define( 'WC_VIPPS_RECURRING_RETRY_DAYS', 4 );
}

/*
 * Whether to put the plugin into test mode. This is only useful for developers.
 */
if ( ! defined( 'WC_VIPPS_RECURRING_TEST_MODE' ) ) {
    define( 'WC_VIPPS_RECURRING_TEST_MODE', false );
}

WC_Vipps_Recurring::register_hooks();
if ( get_option( WC_Vipps_Recurring_Helper::OPTION_CHECKOUT_ENABLED, false ) ) {
    require_once __DIR__ . '/includes/wc-vipps-recurring-checkout.php';
    WC_Vipps_Recurring_Checkout::register_hooks();
}

require_once __DIR__ . '/includes/wc-vipps-recurring-compatibility.php';
