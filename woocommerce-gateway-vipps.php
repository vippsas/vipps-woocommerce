<?php
/*
   Plugin Name: Vipps for WooCommerce
   Plugin URI: https://wordpress.org/woocommerce-gateway-vipps
   Description: Offer Vipps as a payment method for WooCommerce
   Author: Iver Odin Kvello, Pål Martin Bakken
   Author URI: https://www.wp-hosting.no/
   Text-domain: woocommerce-gateway-vipps
   Domain Path: /languages
   Version: 0.9
   License: AGPLv3 or later
   License URI: http://www.gnu.org/licenses/agpl-3.0.html

    This file is part of the WordPress plugin Vipps for WooCommerce
    Copyright (C) 2018 WP Hosting AS

    Article Adopter is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Article Adopter is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.



 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
// This is currently not available. Affects creation of accounts for express checkout too.
define('VIPPS_LOGIN', false);

// Only be active if Woocommerce is active IOK 2018-06-05
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    require_once(dirname(__FILE__) . "/Vipps.class.php");

    /* Instantiate the singleton, stash it in a global and add hooks. IOK 2018-02-07 */
    global $Vipps;
    $Vipps = new Vipps();
    register_activation_hook(__FILE__,array($Vipps,'activate'));
    register_uninstall_hook(__FILE__,array($Vipps,'uninstall'));

    if (is_admin()) {
        add_action('admin_init',array($Vipps,'admin_init'));
        add_action('admin_menu',array($Vipps,'admin_menu'));
    } else {
        add_action('wp_footer', array($Vipps,'footer'));
    }
    add_action('init',array($Vipps,'init'));
    add_action( 'plugins_loaded', array($Vipps,'plugins_loaded'));

}


?>
