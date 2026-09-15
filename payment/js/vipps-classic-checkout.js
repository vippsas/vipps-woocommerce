/* global jQuery */
/**
 * SDK adapter for the classic checkout form, using its existing submit button.
 * Load after wc-checkout and vipps-gw. PHP enqueue instructions: Payment/README.md.
 * The order-pay branch uses WooCommerce's existing-order Store API route. Never
 * send an order-pay form to the cart checkout endpoint.
 */
jQuery(($) => {
    'use strict';

    if (document.body.classList.contains('woocommerce-order-pay')) {
        initOrderPay($);
        return;
    }

    /**
     * Pay an existing order through POST /wc/store/v1/checkout/{id}. PHP must
     * localize VippsOrderPayConfig with an authorized order id, addresses, and
     * a Store API nonce. The order key/email are sent for guest authorization.
     */
    function initOrderPay($) {
        const config = window.VippsOrderPayConfig || {};
        const $form = $('#order_review').first();
        if (!$form.length || !config.orderId || !config.endpoint ||
            !config.billingAddress || !config.shippingAddress ||
            !window.vipps?.trigger || !window.vipps?.host ||
            typeof window.ensureVippsWidgetHostStarted !== 'function' ||
            typeof window.setVippsPaymentBusy !== 'function' ||
            $form.data('vippsOrderPay')) {
            return;
        }
        $form.data('vippsOrderPay', true);

        const owner = 'order-pay';
        let attempt = null;

        function message(key, fallback) {
            return window.VippsLocale?.[key] || fallback;
        }

        function notice(text) {
            return $('<div>').addClass('woocommerce-error').text(text).prop('outerHTML');
        }

        function showError(text) {
            const markup = notice(text);
            $form.find('.woocommerce-NoticeGroup-checkout').remove();
            $form.prepend($('<div>').addClass('woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout')
                .attr({ role: 'alert', tabindex: '-1' }).html(markup));
            $form.find('.woocommerce-NoticeGroup-checkout').trigger('focus');
        }

        function setBusy(busy) {
            window.setVippsPaymentBusy(busy, owner);
            $form.toggleClass('processing', busy).attr('aria-busy', busy ? 'true' : 'false');
            $form.find(':input').prop('disabled', busy);
        }

        function urlFromResponse(response) {
            const result = response?.payment_result || response?.paymentResult || {};
            const details = result.payment_details || result.paymentDetails || [];
            const normalized = Array.isArray(details)
                ? details.reduce((out, item) => { if (item?.key) out[item.key] = item.value; return out; }, {})
                : details;
            return normalized.vippsPaymentUrl || normalized.vipps_payment_url ||
                result.redirect_url || result.redirectUrl || response?.redirect || '';
        }

        function validUrl(value) {
            try {
                const url = new URL(value, window.location.href);
                return ['https:', 'http:'].includes(url.protocol) ? url.href : '';
            } catch {
                return '';
            }
        }

        function release() {
            if (!attempt) return;
            attempt = null;
            setBusy(false);
            $form.find(':input').prop('disabled', false);
            $form.removeClass('processing').removeAttr('aria-busy');
        }

        function navigate(url) {
            setBusy(false);
            window.location.assign(url || window.location.href);
        }

        function requestPayment(current) {
            const paymentData = current.paymentData;

            return $.ajax({
                type: 'POST',
                url: config.endpoint,
                data: JSON.stringify({
                    key: config.orderKey || undefined,
                    billing_email: config.billingEmail || undefined,
                    billing_address: config.billingAddress,
                    shipping_address: config.shippingAddress,
                    payment_method: 'vipps',
                    payment_data: paymentData
                }),
                contentType: 'application/json',
                dataType: 'json',
                headers: config.nonce ? { Nonce: config.nonce } : undefined
            }).then((response) => {
                if (attempt !== current) return null;
                const url = validUrl(urlFromResponse(response));
                if (!url) {
                    release();
                    showError(message('missingPaymentUrl', 'The order did not return a payment URL.'));
                    return null;
                }
                current.phase = 'payment';
                current.paymentUrl = url;
                setBusy(false);
                if (current.closed) {
                    navigate('');
                    return null;
                }
                return url;
            }).catch(() => {
                if (attempt !== current) return null;
                release();
                showError(message('orderPayFailed', 'Could not start payment for this order. Please try again.'));
                return null;
            });
        }

        function submit(event) {
            event.preventDefault();
            if (attempt) return false;
            if ($form.find('[name="payment_method"]:checked').val() !== 'vipps') return true;

            const paymentData = $form.serializeArray()
                .filter(({ name }) => !['payment_method', 'woocommerce-pay-nonce', '_wpnonce'].includes(name))
                .map(({ name, value }) => ({ key: name, value }));
            paymentData.push({ key: 'vipps_checkout_widget', value: '1' });
            attempt = { phase: 'submitting', closed: false, paymentUrl: '', paymentData };
            const current = attempt;
            setBusy(true);
            try {
                window.ensureVippsWidgetHostStarted();
                current.trigger = window.vipps.trigger(() => requestPayment(current))
                    .on('success', (close, url) => { close(); navigate(url || current.paymentUrl); })
                    .on('cancel', (close, url) => { close(); navigate(url || window.location.href); })
                    .on('close', () => {
                        if (attempt !== current) return;
                        if (current.phase === 'submitting') {
                            current.closed = true;
                        } else {
                            navigate('');
                        }
                    })
                    .on('error', () => {
                        if (current.paymentUrl) navigate(current.paymentUrl);
                        else if (current.phase === 'submitting') current.closed = true;
                        else release();
                    });
                Promise.resolve(current.trigger.open()).catch(() => {
                    if (attempt === current && !current.paymentUrl) {
                        release();
                        showError(message('widgetStartFailed', 'Could not start the payment. Please try again.'));
                    }
                });
            } catch {
                release();
                showError(message('widgetStartFailed', 'Could not start the payment. Please try again.'));
            }
            return false;
        }

        $form.on('submit.vippsOrderPay', submit);
    }

    const params = window.wc_checkout_params;
    const $form = $('form.checkout').first();
    if (!params?.checkout_url || !$form.length || document.querySelector('.wc-block-checkout')) {
        return;
    }
    if ($form.data('vippsClassicCheckout')) {
        return;
    }
    $form.data('vippsClassicCheckout', true);

    const owner = 'classic-checkout';
    let activeAttempt = null;
    let updatingCheckout = false;
    let handedOff = false;

    /** Keep new messages in the gateway's existing localization dictionary. */
    function translate(key, fallback) {
        return window.VippsLocale?.[key] || fallback;
    }

    /** Only server-provided checkout notices are HTML; local messages are escaped. */
    function errorMarkup(message) {
        return $('<div>').addClass('woocommerce-error').text(message).prop('outerHTML');
    }

    /** Use WooCommerce's notice/focus handling when supplied by its submit hook. */
    function showError(attempt, messages) {
        if (typeof attempt.checkout?.submit_error === 'function') {
            attempt.checkout.submit_error(messages);
        } else {
            $form.find('.woocommerce-NoticeGroup-checkout').remove();
            const $notice = $('<div>')
                .addClass('woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout')
                .attr({ role: 'alert', tabindex: '-1' })
                .html(messages);
            $form.prepend($notice);
            $notice.trigger('focus');
            $(document.body).trigger('checkout_error', [messages]);
        }
    }

    /**
     * Snapshot before disabling fields. Keep them locked through the SDK session
     * so edits or another payment method cannot submit a competing order/payment.
     * Remember prior disabled states, including fields replaced by checkout updates.
     */
    function lockForm(attempt) {
        $form.addClass('processing').attr('aria-busy', 'true');
        $form.find(':input').each(function () {
            if (!attempt.fields.has(this)) {
                attempt.fields.set(this, this.disabled);
            }
            this.disabled = true;
        });
    }

    /** Release the shared overlay independently of the form's submission guard. */
    function stopSpinner() {
        window.setVippsPaymentBusy(false, owner);
        $form.removeAttr('aria-busy');
    }

    /** Known checkout failures can be retried without discarding form values. */
    function release(attempt) {
        if (activeAttempt !== attempt) {
            return;
        }
        activeAttempt = null;
        stopSpinner();
        $form.removeClass('processing');
        attempt.fields.forEach((disabled, field) => {
            field.disabled = disabled;
        });
        // Clear our reference first: close() may synchronously emit another event.
        attempt.trigger?.close();
    }

    /**
     * A timeout/malformed success may hide an already-created payment. Do not
     * automatically POST again or silently enable another submission. Reloading
     * lets WooCommerce restore its session/order state before the user retries.
     */
    function requireReload(attempt, message) {
        if (activeAttempt !== attempt) {
            return;
        }
        attempt.phase = 'uncertain';
        stopSpinner();
        const link = $('<a>').attr('href', window.location.href)
            .text(translate('reloadCheckout', 'Reload checkout'));
        showError(attempt, errorMarkup(message) + link.prop('outerHTML'));
        // submit_error removes processing; restore the guard for this uncertain result.
        lockForm(attempt);
        $form.removeAttr('aria-busy');
        attempt.trigger?.close();
    }

    /** Navigate once, retaining locked state until the page has actually left. */
    function navigate(attempt, url) {
        if (activeAttempt !== attempt || attempt.phase === 'leaving') {
            return;
        }
        attempt.phase = 'leaving';
        handedOff = true;
        stopSpinner();
        if (url) {
            window.location.assign(url);
        } else {
            window.location.reload();
        }
    }

    /** A close during submission waits for the outstanding request, not another POST. */
    function finishWidget(attempt, close, redirectUrl) {
        if (activeAttempt !== attempt || ['uncertain', 'leaving'].includes(attempt.phase)) {
            return;
        }
        if (attempt.phase === 'submitting') {
            attempt.closed = true;
            if (close) {
                close();
            }
            return;
        }
        // Set the phase before close(), which may emit a nested close event.
        navigate(attempt, redirectUrl);
        if (close) {
            close();
        }
    }

    /**
     * If the SDK fails after a request was sent, wait for its result. A known URL
     * can use full-page fallback without creating a second order or payment.
     */
    function sdkError(attempt) {
        if (activeAttempt !== attempt || ['uncertain', 'leaving'].includes(attempt.phase)) {
            return;
        }
        if (attempt.paymentUrl) {
            navigate(attempt, attempt.paymentUrl);
        } else if (attempt.requestSent) {
            attempt.sdkFailed = true;
        } else {
            release(attempt);
            showError(attempt, errorMarkup(translate('widgetStartFailed', 'Could not start the payment. Please try again.')));
        }
    }

    /** The response URL is supplied by our gateway; reject unusable URL schemes. */
    function getPaymentUrl(result) {
        const value = result.vippsPaymentUrl || result.redirect;
        if (typeof value !== 'string' || !value.trim()) {
            return null;
        }
        try {
            const url = new URL(value, window.location.href);
            return ['https:', 'http:'].includes(url.protocol) ? url.href : null;
        } catch {
            return null;
        }
    }

    /**
     * The SDK calls this resolver from open(). WooCommerce still creates/resumes
     * and validates the order; only the browser handling of the result is replaced.
     */
    async function createSession(attempt) {
        let result;
        try {
            attempt.requestSent = true;
            result = await $.ajax({
                type: 'POST',
                url: params.checkout_url,
                data: attempt.formData,
                dataType: 'json'
            });
        } catch {
            requireReload(attempt, translate('checkoutResultUnknown',
                'The checkout result could not be confirmed. Check your order before trying again.'));
            return null;
        }

        if (activeAttempt !== attempt) {
            return null;
        }
        if (result?.result === 'failure') {
            release(attempt);
            if (result.reload === true) {
                window.location.reload();
                return null;
            }
            showError(attempt, typeof result.messages === 'string' && result.messages
                ? result.messages
                : errorMarkup(translate('checkoutFailed', 'Checkout failed. Please check your details and try again.')));
            if (result.refresh === true) {
                $(document.body).trigger('update_checkout');
            }
            return null;
        }

        const paymentUrl = result?.result === 'success' ? getPaymentUrl(result) : null;
        if (!paymentUrl) {
            requireReload(attempt, translate('missingPaymentUrl',
                'Checkout did not return a payment URL. Reload checkout to check your order before trying again.'));
            return null;
        }

        attempt.paymentUrl = paymentUrl;
        attempt.phase = 'payment';
        handedOff = true;
        stopSpinner();
        if (attempt.closed || attempt.sdkFailed) {
            navigate(attempt, attempt.closed ? null : paymentUrl);
            return null;
        }
        return paymentUrl;
    }

    /**
     * This must return false synchronously. Returning a Promise would allow core
     * checkout.js to also POST and redirect. Do not intercept its success event:
     * returning false there enters core's generic checkout error path.
     */
    function submitVipps(event, checkout) {
        if (event.result === false) {
            return false;
        }
        if (activeAttempt) {
            event.stopImmediatePropagation();
            return false;
        }
        if ($form.find('input[name="payment_method"]:checked').val() !== 'vipps' ||
            !window.vipps?.trigger || !window.vipps?.host ||
            typeof window.ensureVippsWidgetHostStarted !== 'function' ||
            typeof window.setVippsPaymentBusy !== 'function') {
            return;
        }

        event.stopImmediatePropagation();
        if (updatingCheckout || (checkout?.xhr && checkout.xhr.readyState !== 4)) {
            showError({ checkout }, errorMarkup(translate('checkoutUpdating',
                'Checkout totals are updating. Please wait, then place your order again.')));
            // Core's submit handler clears its update timer before invoking us.
            // Restart it so an update that had been queued is not lost.
            $(document.body).trigger('update_checkout');
            return false;
        }

        const fields = $form.serializeArray().filter(({ name }) => name !== 'vipps_checkout_widget');
        fields.push({ name: 'vipps_checkout_widget', value: '1' });
        const attempt = {
            checkout,
            formData: $.param(fields).replace(/'/g, '%27'),
            fields: new Map(),
            phase: 'submitting',
            requestSent: false,
            trigger: null
        };
        activeAttempt = attempt;
        lockForm(attempt);
        $form.find('.woocommerce-NoticeGroup-checkout').remove();

        try {
            window.setVippsPaymentBusy(true, owner);
            window.ensureVippsWidgetHostStarted();
            attempt.trigger = window.vipps.trigger(() => createSession(attempt))
                .on('success', (close, url) => finishWidget(attempt, close, url))
                .on('cancel', (close, url) => finishWidget(attempt, close, url))
                .on('close', () => finishWidget(attempt))
                .on('error', () => sdkError(attempt));
            Promise.resolve(attempt.trigger.open()).catch(() => sdkError(attempt));
        } catch {
            sdkError(attempt);
        }
        return false;
    }

    $form.on('checkout_place_order_vipps.vippsClassic', submitVipps);
    $(document.body)
        .on('update_checkout.vippsClassic', () => { updatingCheckout = true; })
        .on('updated_checkout.vippsClassic', () => {
            updatingCheckout = false;
            if (activeAttempt) {
                lockForm(activeAttempt);
                if (activeAttempt.phase !== 'submitting') {
                    $form.removeAttr('aria-busy');
                }
            }
        });

    // A full navigation reconstructs the page normally. A bfcache restoration
    // retains this controller, so reload once to reconcile the pending/paid order.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && (activeAttempt || handedOff)) {
            window.location.reload();
        }
    });
});
