/*
    This file is part of the WordPress plugin Checkout with Vipps for WooCommerce
    Copyright (C) 2018 WP Hosting AS

    Checkout with Vipps for WooCommerce is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Checkout with Vipps for WooCommerce is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

jQuery( document ).ready( function() {
 // Hooks for the 'buy now with vipps' button on product pages etc
 jQuery('body').on('found_variation', function (e,variation) {
   var purchasable=true;
   if ( ! variation.is_purchasable || ! variation.is_in_stock || ! variation.variation_is_visible ) {
     purchasable = false;
   }
   if (purchasable) {
    jQuery('form .button.single-product.vipps-express-checkout').removeAttr('disabled');
    jQuery('form .button.single-product.vipps-express-checkout').removeClass('disabled');
   } else {
    jQuery('form .button.single-product.vipps-express-checkout').attr('disabled','disabled');
    jQuery('form .button.single-product.vipps-express-checkout').addClass('disabled');
   }
 });
 jQuery('body').on('reset_data', function () {
    jQuery('form .button.single-product.vipps-express-checkout').attr('disabled','disabled');
    jQuery('form .button.single-product.vipps-express-checkout').addClass('disabled');
 });

 // Remove old error messages
 function removeErrorMessages () {
   jQuery('.woocommerce-error.vipps-error').fadeOut(300, function () {  jQuery(this).remove(); });
   jQuery(document).trigger('woo-vipps-remove-errors');
 }

// Hooks for the button itself
 jQuery('.button.single-product.vipps-express-checkout').click(function () {
   removeErrorMessages();
   var button = jQuery(this);

   // Allow developers to customize error message by hiding vipps-default-error-message and hooking woo-vipps-error-message <messsage>,<element>
   var msg = "<p><ul class='woocommerce-error vipps-error vipps-default-error-message vipps-express-checkout-error'><li>Something went wrong!</li></ul></p>";
   jQuery(document).trigger('woo-vipps-error-message',[msg, button]);
   jQuery(msg).hide().insertAfter(button).fadeIn(300);

   jQuery('.woocommerce-error.vipps-error').click(removeErrorMessages);

 });
 
});
