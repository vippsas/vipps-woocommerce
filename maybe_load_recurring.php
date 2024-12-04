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
   require_once(dirname(__FILE__) . "/recurring/recurring.php");

   // We need to do the old plugin de/activation logic for the standalone plugin here:
   // If we are here and we have not been previously activated, we should call the "activate" hook and note that we have been activated.
   // If we are here and we were previously activated, but subscriptions are not active, we should call the *deactivate* hook and reset the database setting.
   // This must be done fairly late, so we are going to use "wp_loaded". IOK 2024-12-04
   if (!$previously_activated) {
       if (!woo_vipps_ajax_cron_or_rest ()) {
           error_log("We have loaded the code but not previously activated the plugin.");
           add_action('wp_loaded', function () {
               error_log("wp loaded. Activating recurring");
               WC_Vipps_Recurring::get_instance()->activate();
               update_option('woo_vipps_recurring_payments_activation', WC_VIPPS_RECURRING_VERSION);
           });
       }
   } else if (!$subscriptions_exist) {
       if (!woo_vipps_ajax_cron_or_rest ()) {
           error_log("We have loaded the code but woo subscriptions are not present.");
           add_action('wp_loaded', function () {
               error_log("wp loaded. dectivating.");
               WC_Vipps_Recurring::get_instance()->deactivate();
               delete_option('woo_vipps_recurring_payments_activation');
           });
       }
   } else {
       if (!woo_vipps_ajax_cron_or_rest ()) {
           error_log("We have loaded the code, but we are already activated");
       }
   }


}, 1);

