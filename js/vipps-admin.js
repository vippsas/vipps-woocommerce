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

// This file contains various javascript code for Vipps-specific functionality in the backend. 
//
(function () {

 if (pagenow == 'woocommerce_page_wc-settings') {
  jQuery(document).ready(function ()  {
   jQuery('input.vippspw').focus( function () { jQuery(this).attr('type','text') });; 
   jQuery('input.vippspw').focusout( function () { jQuery(this).attr('type','password');  });
  });
 }

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
             add_shareable_commands(newrow);
          } else {
             console.log("Error creating shareable link " + result['msg']);
             jQuery('#vipps-shareable-link-error').text(' : ' +result['msg']);
             jQuery('.vipps-shareable-link-error').show();
          }
         },
      });

      return false;
     } 

     function add_shareable_commands(element) {
       if (!element) element = jQuery('#woo_vipps_shareables');
       element.find('.shareable').click(function () {
         jQuery('<input type=hidden/>').appendTo('body').val(jQuery(this).text()).select(); 
         document.execCommand('copy');
       }); 
       element.find('.copyaction').click(function () {
         var link = jQuery(this).closest('tr').find('.shareable');
         jQuery('<input type=hidden/>').appendTo('body').val(link.text()).select();
         document.execCommand('copy');
       });
       element.find('.deleteaction').click(function () {
         var link = jQuery(this).closest('tr').find('.shareable');
         link.toggleClass('deleted');
         if (link.hasClass('deleted')) {
          link.siblings('.deletemarker').attr('name','woo_vipps_shareable_delenda[]');
         } else {
          link.siblings('.deletemarker').removeAttr('name');
         }

         if (jQuery('a.shareable.deleted').length>0) {
          jQuery('#vipps-shareable-link-delete-message').show();
         } else {
          jQuery('#vipps-shareable-link-delete-message').hide();
         }

       });
       element.find('.qraction').click(function () {
          var key = jQuery(this).closest('tr').find('.shareable').siblings('input').val();
          var url = encodeURIComponent(jQuery(this).closest('tr').find('.shareable').text());
          var nonce = jQuery('#vipps_share_sec').val();
          var postid= jQuery('#post_ID').val();
          var source = ajaxurl + '?vipps_share_sec=' + nonce + '&action=vipps_link_qr&key=' + key + '&id=' + postid + '&url='+url;
        
          var y = window.outerHeight / 4 + window.screenY - (320/2);
          var x = window.outerWidth / 2 + window.screenX - (320/2);

          var qrwin = window.open('','_blank','height=320,width=320,menubar=no,location=no,resizable=yes,scrollbars=yes,status=yes,copyhistory=no,top='+y+',left='+x);
          qrwin.document.write('<p style="position:fixed;top:50%;left:50%"><i>loading...</i></p>');
          qrwin.location.href = source;
          qrwin.title ="QR Code";

       });
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
       add_shareable_commands();
     });


 }
})();

function VippsGetPaymentDetails(orderid,nonce) {
    var source = ajaxurl + '?vipps_paymentdetails_sec='+nonce+'&action=vipps_payment_details&orderid='+orderid;
    var y = window.outerHeight / 4 + window.screenY - (320/2);
    var x = window.outerWidth / 2 + window.screenX - (320/2);
    var qrwin = window.open('','_blank','height=320,width=640,menubar=no,location=no,resizable=yes,scrollbars=yes,status=yes,copyhistory=no,top='+y+',left='+x);
    qrwin.document.write('<p style="position:fixed;top:50%;left:50%"><i>loading...</i></p>');
    qrwin.location.href = source;
    qrwin.title ="Payment details";
}



console.log("Vipps admin scripts loaded");
