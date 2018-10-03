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

// This file contains various javascript code for Vipps-specific functionality in the backend. 
//
(function () {

 if  (pagenow == 'product') {
     // Called on the product edit screen by a button.
     function vipps_create_shareable_link() {
      jQuery('#vipps-share-link').attr('disabled','disabled');

      jQuery('#vipps-shareable-link-error').text('');
      jQuery('.vipps-shareable-link-error').hide();


      var prodid = jQuery('#vipps_sharelink_id').val();
      var varid=0;
      var isvariant = false;
      if (!prodid) {
         jQuery('#vipps-share-link').removeAttr('disabled');
         return false; 
      }
      var varselector = jQuery('#vipps_sharelink_variant');
      if (varselector.length > 0) isvariant = true;
      if (isvariant) {
        varid = varselector.val();
        if (!varid) {
          jQuery('#vipps-share-link').removeAttr('disabled');
          return false;
        }
      }
      var nonce = jQuery('#vipps_share_sec').val();
      var data = { 'action': 'vipps_create_shareable_link', 'prodid':prodid,'varid':varid, 'vipps_share_sec':nonce };

      jQuery.ajax(ajaxurl, { "method": "POST", "data":data, "cache":false, "dataType": "json", "timeout":0,
        "error": function (xhr, statustext, error) {
           jQuery('#vipps-share-link').removeAttr('disabled');
           console.log("Error creating shareable link" + statustext + " " + error);
           jQuery('#vipps-shareable-link-error').text(' : ' +error);
           jQuery('.vipps-shareable-link-error').show();
        },
        "success": function (result, statustext, xhr) {
          jQuery('#vipps-share-link').removeAttr('disabled');
          if (result["ok"]) {
             console.log("Shareable link created ");
             jQuery('#woo_vipps_shareables').show();
             var newrow = jQuery('<tr>');
             if (result['variant']) newrow.append('<td>'+result['variant']+'</td>');
             var link = jQuery('#woo_vipps_shareable_link_template').clone();
             link.removeAttr('id');
             link.find('a').text(result['url']);
             link.find('input').attr('value',result['key']);
             newrow.append('<td>'+link.html()+'</td>');
             var actions = jQuery('#woo_vipps_shareable_command_template').clone();
             actions.removeAttr('id');
             newrow.append('<td align=center>' + actions.html() + '<td>');
             jQuery('#woo_vipps_shareables tbody').append(newrow);
          } else {
             console.log("Error creating shareable link " + result['msg']);
             jQuery('#vipps-shareable-link-error').text(' : ' +result['msg']);
             jQuery('.vipps-shareable-link-error').show();
          }
         },
      });

      return false;
     } 

     jQuery(document).ready(function () {
       // Require a variant to have been selected in order for the shareable-link thing to work
       jQuery('#vipps_sharelink_variant').change(function () {
         if (jQuery(this).val()) {
           jQuery('#vipps-share-link').removeAttr('disabled');
         } else {
           jQuery('#vipps-share-link').attr('disabled','disabled');
         }
       });
       jQuery('#vipps-share-link').click(vipps_create_shareable_link);
       
       jQuery('#woo_vipps_shareables .shareable').click(function () {
         jQuery('<input type=hidden/>').appendTo('body').val(jQuery(this).text()).select(); 
         document.execCommand('copy');
       }); 
       jQuery('#woo_vipps_shareables .copyaction').click(function () {
         var link = jQuery(this).closest('tr').find('.shareable');
         jQuery('<input type=hidden/>').appendTo('body').val(link.text()).select();
         document.execCommand('copy');
       });

     });


 }
})();


console.log("Vipps admin scripts loaded");
