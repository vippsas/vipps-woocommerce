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
 function doExpressCheckout () {
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
    },
    "success": function (result, statustext, xhr) {
     if (result["ok"]) {
       console.log("We created the order!");
       jQuery('#do-express-checkout').hide();
       jQuery("#waiting").hide();
       jQuery("#success").show();
       jQuery("#failure").hide();
       jQuery("#error").hide();
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
     }
    },
    "timeout": 0
   });
 }

 var buttons = jQuery('#do-express-checkout');
 if (buttons.length == 0) {
  doExpressCheckout();
 } else {
  jQuery('#do-express-checkout').click(doExpressCheckout);
 }


});
