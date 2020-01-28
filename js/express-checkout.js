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

jQuery(document).ready(function () {
 var validating = 0;

 function validateExpressCheckout () {
    var termscheck = validateTermsAndConditions();
    var validation = termscheck;
    if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
         validation = wp.hooks.applyFilters('vippsValidateExpressCheckoutForm', validation);
    }
    return validation;
 }

 function validateTermsAndConditions () {
   var termsbox = jQuery('.input-checkbox[name="terms"]');
   if (termsbox.length == 0) return true;
   if (termsbox.is(':checked')) return true;
   termsbox.closest('.validate-required').addClass('woocommerce-invalid');
   termsbox.closest('.validate-required').addClass('woocommerce-invalid-required-field ');
   var message= VippsCheckoutMessages['termsAndConditionsError'];
   addErrorMessage(message, jQuery('#vippsdata'));
   return false;
 }

  // Remove old error messages
 function removeErrorMessages () {
   jQuery('.woocommerce-error.vipps-error').fadeOut(300, function () {  jQuery(this).remove(); });
   jQuery(document).trigger('woo-vipps-remove-errors');
   if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
      wp.hooks.doAction('vippsRemoveErrorMessages');
   }
   validating=0;
   jQuery('.woocommerce-invalid woocommerce-invalid-required-field').removeClass('woocommerce-invalid woocommerce-invalid-required-field');
   jQuery('.woocommerce-invalid').removeClass('woocommerce-invalid');
   jQuery('.woocommerce-invalid-required-field').removeClass('woocommerce-invalid-required-field');
 }

 // And add new ones
 function addErrorMessage(msg,element) {
   // Allow developers to customize error message by hiding vipps-default-error-message and hooking woo-vipps-error-message <messsage>,<element>
   var msg = "<p><ul class='woocommerce-error vipps-error vipps-default-error-message vipps-buy-now-error'><li>"+msg+"!</li></ul></p>";
   if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
      msg = wp.hooks.applyFilters('vippsErrorMessage',msg, element);
   }
   jQuery(document).trigger('woo-vipps-error-message',[msg, element]);
   if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
      wp.hooks.doAction('vippsAddErrorMessage', msg, element);
   }

   jQuery(msg).hide().insertAfter(element).fadeIn(300);
   jQuery('.woocommerce-error.vipps-error').click(removeErrorMessages);
 }

 

 function doExpressCheckout () {
   if (validating) return false;
   validating=true;
   removeErrorMessages();
   var ok = validateExpressCheckout();
   if (!ok) {
    jQuery('.vipps-express-checkout').blur();
    validating=false;
    return false;
   }

   jQuery('#do-express-checkout').prop('disabled',true);
   jQuery('#do-express-checkout').prop('inactive',true);
   jQuery('body').addClass('processing');

   var data  = jQuery("#vippsdata").serialize();
   jQuery.ajax(vippsajaxurl, {
    "method": "POST",
    "data":data,
    "cache":false,
    "dataType": "json",
    "error": function (xhr, statustext, error) {
      console.log("Error creating express checkout:" + statustext + " " + error);
      jQuery('#do-express-checkout').hide();
      jQuery("#waiting").hide();
      jQuery("#success").hide();
      jQuery("#failure").hide();
      jQuery("#error").show();
      jQuery('body').removeClass('processing');
      jQuery('.vipps-express-checkout').blur();
      validating=0;
    },
    "success": function (result, statustext, xhr) {
     if (result["ok"]) {
       console.log("We created the order!");
       jQuery('#do-express-checkout').hide();
       jQuery("#waiting").hide();
       jQuery("#success").show();
       jQuery("#failure").hide();
       jQuery("#error").hide();
       jQuery('.vipps-express-checkout').blur();
       validating=0;
       window.location.href = result["url"];
     } else {
       console.log("Failure!");
       jQuery('#do-express-checkout').hide();
       jQuery('#failure').html(result['msg']);
       jQuery("#waiting").hide();
       jQuery("#success").hide(); 
       jQuery("#failure").show();
       jQuery("#error").hide();
       jQuery('body').removeClass('processing');
       jQuery('.vipps-express-checkout').blur();
       validating=0;
     }
    },
    "timeout": 0
   });
 }

 // No interaction neccessary => just buy stuff
 var buttons = jQuery('#do-express-checkout');
 if (buttons.length == 0) {
   doExpressCheckout();
 } else {
   buttons.click(doExpressCheckout);   
   jQuery('input').change(removeErrorMessages);
 }
 


});
