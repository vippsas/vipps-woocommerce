<?php

defined( 'ABSPATH' ) || exit;

// if uninstall not called from WordPress exit
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Only remove ALL product and page data if WC_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 *
 * Now support toggling deletion upon uninstall from plugin settings menu. LP 2025-10-06
 */
require_once(dirname(__FILE__) . '/woo-vipps.php');
global $Vipps;
if ( (defined( 'WC_REMOVE_ALL_DATA' ) && true === WC_REMOVE_ALL_DATA) ||
    ($Vipps && $Vipps->gateway()->get_option( 'delete_settings_on_uninstall' ) === 'yes')
) {
    global $wpdb;

    // Delete options.
    $options = ['woocommerce_vipps_recurring_settings','woocommerce_vipps_settings', 'woo-vipps-configured', 'vipps_badge_options', '_vipps_dismissed_notices', 'woo_vipps_checkout_activated'];
    foreach($options as $option) {
	error_log("Deleting woo-vipps option: $option");
	delete_option($option);
    }
    error_log("Deleting woo-vipps recurring-like options");
    $wpdb->query( 'DELETE FROM wp_options WHERE option_name LIKE "vipps_recurring_dismissed_%";' );
}
