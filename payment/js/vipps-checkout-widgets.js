// Called when we know the order used to represent the Vipps Session exists
function loadWidgets() {
    jQuery.ajax(VippsConfig['vippsajaxurl'], {
        data: {action: "vipps_checkout_get_widgets"},
        type: "GET",
        cache:false,
        timeout: 0,
        dataType:'html',
        error: function (eh) { console.log("error loading widgets"); },
        success: function (data) {
            jQuery('#vipps_checkout_widget_mount').html(data); 
            initializeWidgets();
            jQuery('.vipps_checkout_widget_wrapper').show();
            jQuery('body').trigger('woo-vipps-checkout-widgets-loaded');
        }
    });
}


// Common initializations for all widgets after load
function initializeWidgets() {
    // widget accordion feature. LP 2025-05-07
    jQuery('.vipps_checkout_widget_title.accordion').on('click', function() {
        jQuery(this).toggleClass('active');
        jQuery(this).next('.vipps_checkout_body').toggle();
    });
    // Coupon code widget button hover, using the css color classes instead of :hover. LP 2025-08-08
    function togglePurple() {
        jQuery(this).toggleClass('vippspurple2');
        jQuery(this).toggleClass('vippspurple-light');
    };
    jQuery('.vipps_checkout_widget_button').on('mouseenter', togglePurple).on('mouseleave', togglePurple);
}

// Custom initialization for default widgets IOK 2025-05-13
jQuery('body').on('woo-vipps-checkout-widgets-loaded', function () {
    if (jQuery('#vipps_checkout_widget_coupon_active_codes_container_codes').children().length > 0) {
        jQuery('#vipps_checkout_widget_coupon_active_codes_container').show();
    }

    // Coupon code widget submit. LP 2025-05-08
    jQuery('#vipps_checkout_widget_coupon_form').on('submit', function(e) {
        e.preventDefault();
        const formdata = new FormData(this);
        let code = formdata.get('code').trim();

        const error = jQuery('#vipps_checkout_widget_coupon_error');
        const success = jQuery('#vipps_checkout_widget_coupon_success');
        const input = jQuery('#vipps_checkout_widget_coupon_code');
        const activeCodesContainer = jQuery('#vipps_checkout_widget_coupon_active_codes_container');
        const activeCodes = jQuery('#vipps_checkout_widget_coupon_active_codes_container_codes');
        function showError() {
            error.show();
            success.hide();
            if (!input.hasClass('error')) input.addClass('error');
            if (input.hasClass('success')) input.removeClass('success');
        }

        function showSuccess() {
            error.hide();
            success.show();
            if (input.hasClass('error')) input.removeClass('error');
            if (!input.hasClass('success')) input.addClass('success');
            input.val('').blur();
            const newCode = jQuery(`
                            <div class="vipps_checkout_widget_coupon_active_code_box" id="vipps_checkout_widget_coupon_active_code_${code}">
                                <span class="vipps_checkout_widget_coupon_active_code">${code}</span>
                            </div>
            `);
            const newCodeDelete = jQuery('<span class="vipps_checkout_widget_coupon_delete">✕</span>');
            newCodeDelete.on('click', deleteActiveCouponCode);
            newCode.append(newCodeDelete);
            activeCodes.append(newCode);
            activeCodesContainer.show();
        }

        if (code) {
            const args = { lock_held: true, data: { 'code': code }   }; 
            args['error'] = function (result) {
                console.log("Problem: " + result['error']);
                showError();
            }
            args['success'] = function (result) {
                console.log("Success: %j", result); 
                if (result['msg'] && result['msg'] == 1) {
                    showSuccess();
                    return;
                }
                showError();
            }

            wooVippsCheckoutCallback( 'submitcoupon', args );
        }
    });

    // Coupon code widget remove active coupon code. LP 2025-05-09
    function deleteActiveCouponCode() {
        const el = jQuery(this);
        const code = el.prev().text();

        const error = jQuery('#vipps_checkout_widget_coupon_error');
        const success = jQuery('#vipps_checkout_widget_coupon_success');
        const input = jQuery('#vipps_checkout_widget_coupon_code');
        const deleteError = jQuery('#vipps_checkout_widget_coupon_delete_error');
        function showError() {
            success.hide();
            error.hide();
            deleteError.show();
            if (input.hasClass('error')) input.removeClass('error');
            if (input.hasClass('success')) input.removeClass('success');
        }

        function showSuccess() {
            el.parent().remove();
            success.hide();
            error.hide();
            deleteError.hide();
            if (jQuery('#vipps_checkout_widget_coupon_active_codes_container_codes').children().length < 1) {
                jQuery('#vipps_checkout_widget_coupon_active_codes_container').hide();
            }
            if (input.hasClass('error')) input.removeClass('error');
            if (input.hasClass('success')) input.removeClass('success');
        }

        if (code) {
            const args = { lock_held: true, data: { 'code': code }   }; 
            args['error'] = function (result) {
                console.log("Problem: " + result['error']);
                showError();
            }
            args['success'] = function (result) {
                console.log("Success: %j", result); 
                if (result['msg'] && result['msg'] == 1) {
                    showSuccess();
                    return;
                }
                showError();
            }

            wooVippsCheckoutCallback( 'removecoupon', args );
        }
    };
    jQuery('.vipps_checkout_widget_coupon_delete').on('click', deleteActiveCouponCode);

    // Order notes widget submit. LP 2025-05-12
    jQuery('#vipps_checkout_widget_ordernotes_form').on('submit', function(e) {
        e.preventDefault();
        const formdata = new FormData(this);
        let notes = formdata.get('notes').trim();

        const error = jQuery('#vipps_checkout_widget_ordernotes_error');
        const success = jQuery('#vipps_checkout_widget_ordernotes_success');
        const input = jQuery('#vipps_checkout_widget_ordernotes_input');

        const args = { lock_held: true, data: { 'notes': notes }   }; 
        args['error'] = function (result) {
            console.log("Problem: " + result['error']);
            error.show();
            success.hide();
            if (!input.hasClass('error')) input.addClass('error');
            if (input.hasClass('success')) input.removeClass('success');
        }
        args['success'] = function (result) {
            console.log("Success: %j", result); 
            error.hide();
            success.show();
            if (input.hasClass('error')) input.removeClass('error');
            if (!input.hasClass('success')) input.addClass('success');
        }
        wooVippsCheckoutCallback( "submitnotes", args );
    });

    jQuery('#vipps_checkout_widget_ordernotes_input').on('input', function() {
        const success = jQuery('#vipps_checkout_widget_ordernotes_success');
        const input = jQuery('#vipps_checkout_widget_ordernotes_input');
        const error = jQuery('#vipps_checkout_widget_ordernotes_error');
        if (input.hasClass('error')) input.removeClass('error');
        if (input.hasClass('success')) input.removeClass('success');
        success.hide();
        error.hide();
    });

});

// Global function defined for widgets
function wooVippsCheckoutCallback( action, args ) {
    let successhandler = args['success'] ? args['success'] : function (data) { console.log ("Callback Success: %j", data); };
    let errorhandler = args['error'] ? args['error'] : function (data) { console.log ("Callback Error: %j", data); };
    let lock_held = args['lock_held'] ? 1 : 0;
    let callbackdata = args['data'] ? args['data'] : {};

    let data = { 'action': 'vipps_checkout_callback', 'callback_action': action, 'vipps_checkout_sec' : jQuery('#vipps_checkout_sec').val(), 
                 'orderid' : jQuery('#vippsorderid').val(),
                  callbackdata, lock_held};

    if (lock_held) {
       wp.hooks.doAction('vippsCheckoutLockSession');
    }
    jQuery("body").css("cursor", "progress");
    jQuery("body").addClass('processing');

    jQuery.ajax(VippsConfig['vippsajaxurl'],
                    {   cache:false,
                        timeout: 0,
                        dataType:'json',

                        data: data,
                        method: 'POST', 
                        error: function (xhr, statustext, error) {
                           return errorhandler({error: statustext});
                        },
                        'success': function (result,statustext, xhr) {
                           if (!result['success']) return errorhandler(result['data']);
                           return successhandler(result['data']);
                        },
                        'complete': function (xhr, statustext) {
                           if (lock_held) wp.hooks.doAction('vippsCheckoutUnlockSession');
                           jQuery("body").css("cursor", "default");
                           jQuery("body").removeClass('processing');
                        }
                    });
}
