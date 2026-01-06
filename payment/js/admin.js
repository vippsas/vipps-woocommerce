/*

This file is part of the plugin Pay with Vipps and MobilePay for WooCommerce
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

        // Image upload stuff
        jQuery('body').on( 'click', '.woo-vipps-image-upload', function(e){
            e.preventDefault();
            var button = jQuery(this),
                custom_uploader = wp.media({
                    library : {
                        type : 'image'
                    },
                    button: {
                    },
                    multiple: false
                }).on('select', function() {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();

                    url = false;
                    if (attachment['url']) {
                        url = attachment.url;
                    }
                    if (attachment['sizes'] && attachment['sizes']['thumbnail']) {
                        url = attachment.sizes.thumbnail.url;
                    }
                    if (!url) return;

                    button.find('img').attr('src', url);
                    button.find('img').show();
                    button.find('span').hide();
                    button.next().next().val(attachment.id);
                    button.next().show();
                }).open();
        });
        jQuery('body').on('click', '.woo-vipps-image-remove', function(e){
            e.preventDefault();
            var button = jQuery(this);
            button.next().val('');
            button.prev().find('img').attr('src', '');
            button.prev().find('img').hide();
            button.prev().find('span').show();
            button.hide();
        });
    }

    /* Make the Vipps Checkout shipping options only show if the method in question is activated */
    if (pagenow == 'woocommerce_page_wc-settings') {
        if (jQuery('#vipps-settings-page').length > 0) {
            function vipps_cs_showhide_shipping (e) {
                jQuery('input.vcs_main').each(function () {
                    let showit = jQuery(this).data('vcs-show');
                    if (jQuery(this).is(':checked')) {
                        jQuery(showit).closest('tr').show();
                    } else {
                        jQuery(showit).closest('tr').hide();
                    }
                })

            }
            jQuery('input.vcs_main').change(vipps_cs_showhide_shipping);
            vipps_cs_showhide_shipping();
     }
    }

    /* Tab-ify the settings page */
    if (pagenow == 'woocommerce_page_wc-settings') {

        if (jQuery('#vipps-settings-page').length > 0) {
            let toptitle = jQuery('h3.wc-settings-sub-title').parent().find('h2').first();
            let tabholder = jQuery('<div id="vippstabholder" class="vippstabholder"></div>').insertAfter(toptitle);

            /* Pick out all the h3 subheadings, wrap their following elements in a div, and move them to the tabholder above */
            jQuery('h3.wc-settings-sub-title.tab').each(function () {
                let curid = jQuery(this).attr('id');
                let optionstab = jQuery('<div id="' + curid + '-table' + '" class="vippsoptions vippstabs ' + curid + '"></div>').insertAfter(jQuery(this));
                jQuery(this).nextUntil('h3.wc-settings-sub-title.tab,p.submit').each(function () {
                    optionstab.append(jQuery(this));
                });
                jQuery(this).attr('tabindex', -1);
                jQuery(this).attr('aria-selected', false);
                jQuery(this).attr('title', jQuery(this).text());

                tabholder.append(jQuery(this));
            });
            /* Add a final empty h3 for the   a e s t h e t i c s   */
            tabholder.append(jQuery('<h3 id="lasttab" class="wc-settings-sub-title tab last"></h3>'));

            /* Navigate tabs using left + right key */
            jQuery(document).keydown(function (e) {
                if (e.target.parentElement.id == "vippstabholder") {
                    if (e.key == "ArrowRight") {
                        e.preventDefault();
                        let next = jQuery(e.target).next('h3.wc-settings-sub-title');
                        if (next.length == 0 || next.attr('id') == 'lasttab') {
                            jQuery('#vippstabholder h3').first().click();
                        } else  {
                            next.click();
                        }
                    } else if (e.key == "ArrowLeft") {
                        e.preventDefault();
                        let current = jQuery(e.target);
                        console.log("id " + e.target.id);
                        if (e.target.id == 'woocommerce_vipps_main_options') {
                            current = jQuery('#vippstabholder h3.wc-settings-sub-title#lasttab');
                        }
                        let prev= current.prev('h3.wc-settings-sub-title');
                        if (prev.length>0) {
                            prev.click();
                        }
                    }
                }
            });


            /* Set the main options tab to active */
            let thetab = 'woocommerce_vipps_main_options';
            let tabselected = window.location.hash ? window.location.hash.match(/tab:(.+)/) : false;
            if (tabselected && tabselected.length>0) {
                thetab = tabselected[1];
            }
            let idselector = '#' + thetab;
            let fieldselector = '.vippsoptions.vippstabs.' + thetab;
            jQuery(idselector + ',' + fieldselector).addClass('active');
            jQuery(idselector).attr('aria-selected', true);
            jQuery(idselector).attr('tabindex', 0);
            jQuery(idselector).focus();


            /* Make the tab headers active */
            jQuery('div#vippstabholder h3.wc-settings-sub-title').click(function (e) {
                e.preventDefault();

                let curid = jQuery(this).attr('id');
                if (curid == 'lasttab') return;

                if (curid == 'woocommerce_vipps_main_options') {
                    history.pushState(null, null, ' ')
                } else {
                    history.pushState({}, "", "#tab:"+curid);
                }

                jQuery('#vippstabholder .active').removeClass('active');
                jQuery('.vippsoptions.vippstabs.active').removeClass('active');

                jQuery(this).addClass('active');
                jQuery('.vippsoptions.vippstabs.' + curid).addClass('active');


                jQuery('#vippstabholder h3').attr('tabindex', -1);
                jQuery('#vippstabholder h3').attr('aria-selected', false);
                jQuery(this).attr('aria-selected', true);
                jQuery(this).attr('tabindex', 0);
                jQuery(this).focus();


            });
            // Integrate with history
            addEventListener('hashchange', function (e) {
                jQuery('#vippstabholder .active').removeClass('active');
                jQuery('.vippsoptions.vippstabs.active').removeClass('active');
                let thetab = 'woocommerce_vipps_main_options';
                let tabselected = window.location.hash ? window.location.hash.match(/tab:(.+)/) : false;
                if (tabselected && tabselected.length>0) {
                    thetab = tabselected[1];
                }   

                let idselector = '#' + thetab;
                let fieldselector = '.vippsoptions.vippstabs.' + thetab;

                jQuery(idselector + ',' + fieldselector).addClass('active');
                jQuery(idselector).attr('aria-selected', true);
                jQuery(idselector).attr('tabindex', 0);
                jQuery(idselector).focus();

            });

        }
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

            jQuery.ajax(ajaxurl, {
                "method": "POST",
                "data":data,
                "cache":false,
                "dataType": "json",
                "timeout":0,
                "headers": {"Accept-Language": `${VippsConfig['vippslocale']}, *`},
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

    if (pagenow == 'vipps_qr_code') {
        function select_url_bit (which) {
            let all = jQuery('body.post-type-vipps_qr_code.wp-admin .url-section .url-options .url-option');
            let thisone  = jQuery('body.post-type-vipps_qr_code.wp-admin .url-section .url-options .url-option.' + which);
            all.removeClass('active');
            thisone.addClass('active');
            all.find('input,select').prop('required', false);
            thisone.find('input,select').prop('required', true);
        }
        jQuery(document).ready(function () {
            jQuery('.url-section .link-selector .vipps_urltype').on('change', function () {
                select_url_bit(jQuery(this).val());
            });
            select_url_bit(jQuery('.url-section .link-selector .vipps_urltype:checked').val());
        });
    }

    if (pagenow == 'shop_order' || pagenow == 'woocommerce_page_wc-orders') {
      console.log("Shop order page!");

      jQuery(document).ready(function () {
       jQuery('button.vipps-action').click(function (e) {
          console.log("Clickety");
          e.preventDefault();

          let button = jQuery(this);
          if (button.hasClass('disabled')) return;
          button.attr('disabled', 'disabled');
          button.addClass('disabled');
 
          let nonce = VippsConfig['vippssecnonce'];


          let orderid  = jQuery(this).data('orderid');
          let action = jQuery(this).data('action');
 
          let data = { 'action': 'woo_vipps_order_action', 'do': action, 'orderid':orderid, 'vipps_sec':nonce };

          console.log("Ajaxurl " + ajaxurl + " and data %j", data);
 
          jQuery.ajax(ajaxurl, {
                 "method": "POST",
                 "data":data,
                 "cache":false,
                 "dataType": "json",
                 "timeout":0,
                 "headers": {"Accept-Language": `${VippsConfig['vippslocale']}, *`},
                 "error": function (xhr, statustext, error) {
                     button.removeAttr('disabled');
                     button.removeClass('disabled');
                     console.log("Error performing Vipps action " + statustext + " " + error);
                     alert("Error performing Vipps action " + statustext + " " + error);
                  },
                 "success": function (result, statustext, xhr) {
                     window.location.reload();
                  }
          });
       });
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

// Handle permanent notice dismissal
jQuery(document).ready(function () {
    jQuery('.notice-vipps .notice-dismiss').click(function () { 
        let nonce = VippsConfig['vippssecnonce'];
        let key = jQuery(this).closest('.notice').data('key');
        if (! key) return;
        let data = { 'action': 'vipps_dismiss_notice', 'vipps_sec':nonce, 'key': key, headers: {"Accept-Language": `${VippsConfig['vippslocale']}, *`} };
        jQuery.ajax(ajaxurl, { "method": "POST", "data":data, "cache":false, "dataType": "json", "timeout":0 });
    });
});


jQuery(document).ready(function () {
  var unsynch = jQuery('#vipps_unsynchronized_qr_codes');
  if (unsynch.length > 0) {
      jQuery('#vipps_unsynchronized_qr_codes').dialog({
        dialogClass: 'wp-dialog',
        autoOpen: false,
        draggable: true,
        width: 'auto',
        modal: true,
        resizable: true,
        closeOnEscape: true,

        position: {
          my: "top+10%",
          at: "top",
          of: window,
          collision: 'fit'
        },

        open: function () {
          // close dialog by clicking the overlay behind it
          jQuery('.ui-widget-overlay').bind('click', function(){
            jQuery('#vipps_unsynchronized_qr_codes').dialog('close');
          })
        },
        create: function () {
          // style fix for WordPress admin
          jQuery('.ui-dialog-titlebar-close').addClass('ui-button');
        },
      });
      // bind a button or a link to open the dialog
      jQuery('a.open_unsynchronized_qr_codes').click(function(e) {
        e.preventDefault();
        jQuery('#vipps_unsynchronized_qr_codes').dialog('open');
      });
    }
});


console.log("Vipps admin scripts loaded - v1.12.3");
