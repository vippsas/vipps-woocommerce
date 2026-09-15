/* global jQuery */
/**
 * SDK adapter for the classic checkout form, using its existing submit button.
 * Load after wc-checkout and vipps-gw. PHP enqueue instructions: Payment/README.md.
 * Order-pay can load this file too, but retains native submission until its
 * existing-order transport is implemented. Never send that form to cart checkout.
 */
jQuery(($) => {
    'use strict';

    if (document.body.classList.contains('woocommerce-order-pay')) {
        return;
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

console.log("Classic checkout scripts loaded");
