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
   jQuery('form .button.single-product.vipps-buy-now').addClass('variation-found');
   if (purchasable) {
    jQuery('form .button.single-product.vipps-buy-now').removeAttr('disabled');
    jQuery('form .button.single-product.vipps-buy-now').removeClass('disabled');
    removeErrorMessages();
   } else {
    jQuery('form .button.single-product.vipps-buy-now').attr('disabled','disabled');
    jQuery('form .button.single-product.vipps-buy-now').addClass('disabled');
    removeErrorMessages();
   }
 });
 jQuery('body').on('reset_data', function () {
    jQuery('form .button.single-product.vipps-buy-now').removeClass('variation-found');
    jQuery('form .button.single-product.vipps-buy-now').attr('disabled','disabled');
    jQuery('form .button.single-product.vipps-buy-now').addClass('disabled');
    removeErrorMessages();
 });
 // If this is triggered, somebody just loaded a variation form, so we need to redo the button init scripts
 jQuery('body').on('wc_variation_form', function () {
	 console.log("WC variation form loaded");
	 vippsInit();
 });


 // Using ajax, get a session and a key and redirect to the "buy single product using express checkout" page.
 function buySingleProduct (event) {
   event.preventDefault(); 

   var element = jQuery(this);
   if (jQuery('body').hasClass('processing')) return; // Trying to stop doublecclickers. IOK 2018-09-27

   if (jQuery(element).hasClass('disabled'))  {
     if (typeof wc_add_to_cart_variation_params != 'undefined') { 
        if (jQuery(element).hasClass('variation-found'))  {
          window.alert(wc_add_to_cart_variation_params.i18n_unavailable_text);
        } else {
          window.alert(wc_add_to_cart_variation_params.i18n_make_a_selection_text);
        }
     } else {
	     console.log("Missing the wc_add_to_cart_variation_params");
     }
     return false;
   }

   jQuery('body').addClass('processing');

   removeErrorMessages();
   jQuery(element).attr('disabled','disabled');
   jQuery(element).attr('inactive','inactive');
   jQuery(element).addClass('disabled');
   jQuery(element).addClass('loading');

   var data  =  {};

   if (element.data('product_id') || element.data('product_sku')) {
     data = element.data();
   } else {
     var form = element.closest('form.cart');
     if (!form) {
       jQuery(element).removeClass('disabled');
       jQuery(element).removeClass('loading');
       jQuery(element).removeAttr('disabled');
       jQuery(element).removeAttr('inactive');
       jQuery('body').removeClass('processing');
       addErrorMessage('Cannot add product - unknown error');
       return false;
    }
    var prodid = jQuery(form).find('input[name="product_id"]');
    var varid = jQuery(form).find('input[name="variation_id"]');
    data['product_id'] = (prodid.length>0) ? prodid.val() : 0;
    data['variation_id'] = (varid.length>0) ? varid.val() : 0;
   }
   data['action'] = 'vipps_buy_single_product';

   jQuery.ajax(vippsajaxurl, {
    "method": "POST",
    "data":data,
    "cache":false,
    "dataType": "json",
    "error": function (xhr, statustext, error) {
      console.log("Error creating express checkout:" + statustext + " " + error);
      addErrorMessage(error, element);
      jQuery(element).prop('disabled',true);
      jQuery(element).prop('inactive',true);
      jQuery('body').removeClass('processing');
    },
    "success": function (result, statustext, xhr) {
     if (result["ok"]) {
       console.log("We created the order!");
       window.location.href = result["url"];
     } else {
       console.log("Failure!");
       jQuery(element).removeClass('disabled');
       jQuery(element).removeClass('loading');
       jQuery(element).removeAttr('disabled');
       jQuery(element).removeAttr('inactive');
       addErrorMessage(result['msg'],element);
       jQuery('body').removeClass('processing');
     }
    },
    "timeout": 0
   });

  }

 // Remove old error messages
 function removeErrorMessages () {
   jQuery('.woocommerce-error.vipps-error').fadeOut(300, function () {  jQuery(this).remove(); });
   jQuery(document).trigger('woo-vipps-remove-errors');
 }

 // And add new ones
 function addErrorMessage(msg,element) {
   // Allow developers to customize error message by hiding vipps-default-error-message and hooking woo-vipps-error-message <messsage>,<element>
   var msg = "<p><ul class='woocommerce-error vipps-error vipps-default-error-message vipps-buy-now-error'><li>"+msg+"!</li></ul></p>";
   jQuery(document).trigger('woo-vipps-error-message',[msg, element]);
   jQuery(msg).hide().insertAfter(element).fadeIn(300);
   jQuery('.woocommerce-error.vipps-error').click(removeErrorMessages);
 }

 // Hooks for the button itself
 function vippsInit() {
   jQuery('.button.single-product.vipps-buy-now:not(.initialized)').click(buySingleProduct);
   jQuery('.button.single-product.vipps-buy-now').addClass('initialized');
 }
 
 vippsInit();
 
});
