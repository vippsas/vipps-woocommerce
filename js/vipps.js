/*

This file is part of the plugin Checkout with Vipps for WooCommerce
Copyright (c) 2019 WP-Hosting AS

MIT License

Copyright (c) 2019 WP-Hosting AS

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

jQuery( document ).ready( function() {

 // This is for WooCommerce Product Bundles, which potentially have *several* variant-products or configurable products, so
 // the standard Woo hooks don't apply. Luckily, we have other, simpler events to use.
 // For this to work, we also have to force the compat-mode thing on. IOK 2020-04-21
 jQuery('.cart.bundle_data').on('woocommerce-product-bundle-hide', function (e, variant) {
     jQuery('form .button.single-product.vipps-buy-now').removeClass('variation-found');
     jQuery('form .button.single-product.vipps-buy-now').attr('disabled','disabled');
     jQuery('form .button.single-product.vipps-buy-now').addClass('disabled');
     // We don't know why the button is hidden, so ensure we use compatibility-mode IOK 2020-04-21
     jQuery('.button.single-product.vipps-buy-now').addClass('compat-mode');
     removeErrorMessages();
 });
 jQuery('.cart.bundle_data').on('woocommerce-product-bundle-show', function (e, variant) {
     jQuery('form .button.single-product.vipps-buy-now').addClass('variation-found');
     jQuery('form .button.single-product.vipps-buy-now').removeAttr('disabled');
     jQuery('form .button.single-product.vipps-buy-now').removeClass('disabled');
     removeErrorMessages();
 });
 
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
	 vippsInit();
 });
 // Allow other guys to do this too
 jQuery('body').on('vippsInit', function () {
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

   if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
      wp.hooks.doAction('vippsBuySingleProduct', element, event);
   }

   var compatMode = jQuery(element).hasClass('compat-mode');
   if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
      compatMode = wp.hooks.applyFilters('vippsBuySingleProductCompatMode', compatMode,  element, event);
   }

   //  In compatibility mode, we delegate to the existing buy now button instead of doing the logic
   //  ourselves. This allows more filters and actions in existing plugins to run. IOK 2019-02-26
   if (compatMode) {
       var form =   jQuery(element).closest('form');
       var otherbutton =  form.find('.single_add_to_cart_button').first(); 
       var compatAction = function () {
           form.prepend('<input type=hidden id="vipps_compat_mode" name="vipps_compat_mode" value="1">');
           if (otherbutton.length>0) otherbutton.click();
        }
       // If your theme or product is weird enough, you may need this
       if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
         compatAction = wp.hooks.applyFilters('vippsBuySingleProductCompatModeAction', compatAction, element, event);
       }
       compatAction();
       return false;
   }

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
    var quantity = jQuery(form).find('input[name="quantity"]');
    data['quantity'] = (quantity.length>0) ? quantity.val() : 1;
    data['product_id'] = (prodid.length>0) ? prodid.val() : 0;
    data['variation_id'] = (varid.length>0) ? varid.val() : 0;
    // Earlier versions, no variation:
    if (prodid.length == 0) {
      prodid = jQuery(form).find('button[name="add-to-cart"]');  
      data['product_id'] =  (prodid.length>0) ? prodid.val() : 0;
    }
   }
   data['action'] = 'vipps_buy_single_product';

   jQuery.ajax(VippsConfig['vippsajaxurl'], {
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
   if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
      wp.hooks.doAction('vippsRemoveErrorMessages');
   }
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

 // Hooks for the button itself
 function vippsInit() {
   jQuery('.button.single-product.vipps-buy-now:not(.initialized)').click(buySingleProduct);
   jQuery('.button.single-product.vipps-buy-now').addClass('initialized');
   if (typeof wp !== 'undefined' && typeof wp.hooks !== 'undefined') {
      wp.hooks.doAction('vippsInit');
   }
 }
 
 vippsInit();
 
});
