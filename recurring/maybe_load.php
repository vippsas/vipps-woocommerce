<?php
/* Load support for Vipps MobilePay Recurring payments, but only if it has been activated before, or if WooCommerce Subscriptions is loaded.
   Don't load it if the Vipps Mobilepay Recurring plugin is still loaded. Run activation and deactivation hooks if neccessary. */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


// We will not do activation or deactivation if this is ajax, rest or cron. 
function woo_vipps_ajax_cron_or_rest () {
   if (defined("DOING_AJAX") && DOING_AJAX) return true;
   if (function_exists('wp_is_json_request') && wp_is_json_request()) return true;
   if (function_exists('wp_doing_cron') && wp_doing_cron()) return true;
   return false;
}

/* If the standalone plugin has its "deactivate" method called, then we will need to re-activate it. IOK 2024-12-04 */
add_action( 'deactivate_plugin', function($plugin, $network_deactivating ) {
        if (basename($plugin) == 'woo-vipps-recurring.php') {
            delete_option('woo_vipps_recurring_payments_activation');
        }
},10,2);


// Load very early in plugins_loaded, since the Recurring feature will add events on precedence 10 itself.
add_action('plugins_loaded', function () {
    $woo_exists = function_exists('WC');
    if (!$woo_exists) return false;

    $vipps_recurring_exists = defined('WC_VIPPS_RECURRING_VERSION');
    /* If the standalone plugin has been loaded, note that it has been activated, but do nothing else */
    if ($vipps_recurring_exists) {
        update_option('woo_vipps_recurring_payments_activation', WC_VIPPS_RECURRING_VERSION);
        return false;
    }

    /* We will load support now if either the plugin has been activated, or if subscriptions exist */
    $subscriptions_exist = class_exists('WC_Subscriptions');
    $previously_activated = get_option('woo_vipps_recurring_payments_activation');
    if (!$subscriptions_exist && !$previously_activated) {
        return false;
    }

    // It should now be safe to load the recurring support.
    require_once(dirname(__FILE__) . "/recurring.php");

    // We need to do the old plugin de/activation logic for the standalone plugin here:
    // If we are here and we have not been previously activated, we should call the "activate" hook and note that we have been activated.
    // If we are here and we were previously activated, but subscriptions are not active, we should call the *deactivate* hook and reset the database setting.
    // This must be done fairly late, so we are going to use "wp_loaded". IOK 2024-12-04
    if (!$previously_activated) {
        if (!woo_vipps_ajax_cron_or_rest ()) {
            add_action('wp_loaded', function () {
                WC_Vipps_Recurring::get_instance()->activate();
                update_option('woo_vipps_recurring_payments_activation', WC_VIPPS_RECURRING_VERSION);
            });
        }
    } else if (!$subscriptions_exist) {
        if (!woo_vipps_ajax_cron_or_rest ()) {
            add_action('wp_loaded', function () {
                WC_Vipps_Recurring::get_instance()->deactivate();
                delete_option('woo_vipps_recurring_payments_activation');
            });
        }
    } 



}, 1);

/* Mark the Vipps MobilePay Recurring-plugin as needing update, so that we can inform that it is no longer required IOK 2024-12-20 */
add_filter( 'all_plugins', function ( $plugins ) {
                foreach ( $plugins as $plugin=> $plugin_data ) {
                        if (basename($plugin) == 'woo-vipps-recurring.php') {
                                // Necessary to properly display notice within row.
                                $plugins[$plugin]['update'] = 1;
                        }
                }
                return $plugins;
        }
);
add_action('after_plugin_row', function ($plugin) {
                global $wp_list_table;
                if (!$wp_list_table) return;
                if (basename($plugin) != 'woo-vipps-recurring.php')  return;
                $columns_count = $wp_list_table->get_column_count();

                $notice = "";
                if (is_plugin_active($plugin)) {
                    $notice = sprintf(__( 'This plugin can now be <strong>deactivated</strong> because its functionality is now included in <strong>%1$s</strong>. After deactivation, it can not be activated again while that plugin is active, but exactly the same functionality will be provided.', 'woo-vipps' ), __("Pay with Vipps and MobilePay for WooCommerce", 'woo-vipps')); 
                } else {
                    $notice = sprintf(__( 'This plugin can no longer be activated because its functionality is now included in <strong>%1$s</strong>. It is recommended to <strong>delete</strong> it.', 'woo-vipps' ), __("Pay with Vipps and MobilePay for WooCommerce", 'woo-vipps'));
                }
                echo '<tr class="plugin-update-tr"><td colspan="' . esc_attr( $columns_count ) . '" class="plugin-update"><div class="update-message notice inline notice-error notice-alt"><p>' . wp_kses_post( $notice ) . '</p></div></td></tr>';
        }
);
