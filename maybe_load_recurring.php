<?php
/* Load support for Vipps MobilePay Recurring payments, but only if it has been activated before, or if WooCommerce Subscriptions is loaded.
   Don't load it if the Vipps Mobilepay Recurring plugin is still loaded. Run activation and deactivation hooks if neccessary. */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*
if ($recurring_active) {
   update_option('woo_vipps_recurring_payments', 1);
}
$recurring_activated = get_option('woo_vipps_recurring_payments');
*/

// We will not do activation or deactivation if this is ajax, rest or cron. 
function woo_vipps_ajax_cron_or_rest () {
   if (defined("DOING_AJAX") && DOING_AJAX) return true;
   if (function_exists('wp_is_json_request') && wp_is_json_request()) return true;
   if (function_exists('wp_doing_cron') && wp_doing_cron()) return true;
   return false;
}

// Load very early in plugins_loaded, since the Recurring feature will add events on precedence 10 itself.
add_action('plugins_loaded', function () {
   $subscriptions_exist = class_exists('WC_Subscriptions');
   $woo_exists = function_exists('WC');
   $vipps_recurring_exists = defined('WC_VIPPS_RECURRING_VERSION');

   if ($vipps_recurring_exists) {
       error_log("Vipps recurring already supported - plugin must be loaded");
       return false;
   }
   // IF WOOCOMMERCE SUBSCRIPTIONS, OR PREVIOUSLY ACTIVE
   if ($woo_exists) {
       error_log("Loading recurring support");
       require_once(dirname(__FILE__) . "/recurring/recurring.php");
   }
}, 1);

