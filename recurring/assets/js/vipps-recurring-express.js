/* Recurring purchase data only. The payment plugin owns dialogs, validation, host and loading UI. */
jQuery(($) => {
    'use strict';

    const config = window.VippsRecurringExpress;
    if (!config || !window.createVippsMobilepayDialog || !window.createVippsConfirmationValidation) return;
    const storageKey = 'vippsRecurringExpressAttempt';
    if (document.body.classList.contains('woocommerce-order-received') || document.querySelector('#vipps-mobilepay-recurring-app')) {
        try { window.sessionStorage.removeItem(storageKey); } catch { /* Storage is optional. */ }
    }

    const owner = 'recurring-express';
    let active = null;
    let ui = null;
    let sessionConfig = null;

    const handoff = window.createVippsPaymentHandoff({ storageKey: 'vippsRecurringExpressHandoff' });

    function attemptId() {
        let id;

        try { id = window.sessionStorage.getItem(storageKey); } catch { /* Private browsing. */ }

        if (!id) {
            id = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;

            try { window.sessionStorage.setItem(storageKey, id); } catch { /* Keep the in-memory attempt. */ }
        }

        return id;
    }

    function navigate(attempt, url) {
        if (active !== attempt || attempt.leaving) return;

        attempt.leaving = true;
        window.setVippsPaymentBusy(false, owner);
        handoff.mark({ orderId: attempt.orderId });

        window.location.assign(url || attempt.returnUrl || window.location.href);
    }

    function showError(message, needsReload = true) {
        const notice = document.createElement('div');
        notice.className = 'woocommerce-error';
        notice.setAttribute('role', 'alert');
        notice.textContent = message || config.error;

        const reload = document.createElement('a');
        reload.href = window.location.href;
        reload.textContent = window.VippsLocale?.reloadCheckout || 'Reload checkout';

        if (needsReload) notice.append(' ', reload);

        (document.querySelector('.woocommerce-notices-wrapper') || document.querySelector('main') || document.body).prepend(notice);
    }

    function confirmation(attempt, html) {
        if (!ui) {
            ui = window.createVippsMobilepayDialog();
            // Each adapter keeps its own references; avoid duplicate IDs on the same page.
            ui.dialog.querySelectorAll('[id]').forEach((element) => { element.id = `recurring-${element.id}`; });
            ui.dialog.id = 'recurring-confirmation-dialog';
        }

        ui.html.innerHTML = html;
        const validation = window.createVippsConfirmationValidation(ui, {
            validate: (valid, form) => window.wp.hooks.applyFilters('vippsValidateExpressCheckoutForm', valid, form, attempt)
        });

        validation.clear();

        return new Promise((resolve) => {
            let settled = false;
            const finish = (value) => {
                if (settled) return;
                settled = true;
                ui.confirm.removeEventListener('click', confirm);
                ui.form.removeEventListener('submit', confirm);
                ui.cancel.removeEventListener('click', cancel);
                ui.dialog.removeEventListener('cancel', cancel);
                ui.dialog.close();
                resolve(value);
            };

            const confirm = (event) => {
                event.preventDefault();
                if (validation.validate()) finish(Object.fromEntries(new FormData(ui.form)));
            };

            const cancel = (event) => { event.preventDefault(); finish(null); };
            ui.confirm.addEventListener('click', confirm);
            ui.form.addEventListener('submit', confirm);
            ui.cancel.addEventListener('click', cancel);
            ui.dialog.addEventListener('cancel', cancel);
            ui.dialog.showModal();
        });
    }

    async function request(attempt) {
        attempt.requesting = true;
        window.setVippsPaymentBusy(true, owner);

        try {
            if (!sessionConfig) {
                sessionConfig = window.fetch(config.sessionUrl, { credentials: 'same-origin', cache: 'no-store' })
                    .then((response) => response.json()).then((result) => {
                        if (!result.success || !result.data?.nonce) throw new Error(config.error);
                        return result.data;
                    });
            }

            const session = await sessionConfig;
            const response = await window.fetch(session.endpoint + (attempt.data.cancel ? '/abort' : (attempt.data.product_id ? '/product' : '')), {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': session.restNonce, 'X-Vipps-Recurring-Nonce': session.nonce },
                body: JSON.stringify(attempt.data)
            });

            const result = await response.json();

            // A late response must not reopen or navigate away from a dismissed modal.
            if (attempt.closed) return null;

            if (!response.ok) throw new Error(result.message || config.error);

            if (result.cancelled) {
                active = null;
                try { window.sessionStorage.removeItem(storageKey); } catch { /* Storage is optional. */ }
                return null;
            }

            if (result.redirect) { navigate(attempt, result.redirect); return null; }

            if (typeof result.confirmationHtml === 'string') {
                window.setVippsPaymentBusy(false, owner);
                const fields = await confirmation(attempt, result.confirmationHtml);

                if (fields === null) {
                    attempt.data.cancel = true;

                    return request(attempt);
                }

                attempt.data.post = { ...fields, confirmed: '1' };
                return request(attempt);
            }

            if (typeof result.paymentUrl !== 'string' || !result.paymentUrl) throw new Error(config.error);
            attempt.paymentUrl = result.paymentUrl;
            attempt.returnUrl = result.returnUrl;
            handoff.mark();

            if (attempt.sdkFailed) {
                navigate(attempt, attempt.paymentUrl);
                return null;
            }

            return result.paymentUrl;
        } catch (error) {
            // Preserve the attempt ID after an uncertain request. Reload resumes its server record.
            attempt.failed = true;
            if (!attempt.closed) showError(error.message);

            return null;
        } finally {
            attempt.requesting = false;
            if (attempt.closed && active === attempt) active = null;
            window.setVippsPaymentBusy(false, owner);
        }
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('.vipps-recurring-express');

        if (!button) return;
        if (!window.vipps?.trigger) return; // The existing checkout link remains usable.

        event.preventDefault();

        if (active) return;

        const productId = Number(button.dataset.productId || 0);
        const data = { attempt: attemptId(), context: button.dataset.context || 'cart' };

        if (productId) {
            data.product_id = productId;

            const form = button.closest('form.cart');
            const fields = form ? new FormData(form) : new FormData();

            if (form && !form.reportValidity()) return;

            if (form?.classList.contains('variations_form') && !Number(fields.get('variation_id'))) {
                showError(config.selectOptions, false);

                return;
            }

            // Product add-ons/custom forms need WooCommerce's own POST and validation.
            const extra = Array.from(fields.keys()).some((name) => !name.startsWith('attribute_') &&
                !['quantity', 'variation_id', 'product_id', 'add-to-cart', 'woocommerce-add-to-cart-nonce', '_wp_http_referer'].includes(name));

            if (extra && form) {
                const submit = form.querySelector('button[type="submit"], input[type="submit"]');

                if (form.requestSubmit) form.requestSubmit(submit || undefined);
                else form.submit();
                return;
            }

            data.quantity = fields.get('quantity') || 1;
            data.variation_id = fields.get('variation_id') || 0;
            data.variation = Object.fromEntries(Array.from(fields).filter(([name]) => name.startsWith('attribute_')));
        }

        const attempt = { data, paymentUrl: '', returnUrl: '' };
        active = attempt;

        const finish = (_close, url) => {
            if (attempt.failed || attempt.leaving || attempt.closed) return;
            if (!attempt.paymentUrl) { attempt.closed = true; return; }

            const target = new URL(url || attempt.returnUrl, window.location.href);
            target.searchParams.set('pw', '1');
            window.showVippsSuccessRedirectOverlay(owner);
            // Keep the SDK iframe in place until parent navigation takes over.
            navigate(attempt, target.href);
        };

        const dismiss = (close, cancelled = false) => {
            if (attempt.leaving) { close?.(); return; }
            attempt.closed = true;
            window.setVippsPaymentBusy(false, owner);
            handoff.clear();
            // Closing preserves the pending agreement so the next click resumes it.
            if (cancelled) {
                try { window.sessionStorage.removeItem(storageKey); } catch { /* Storage is optional. */ }
            }
            if (!attempt.requesting && active === attempt) active = null;
            close?.();
        };

        const failed = () => {
            if (attempt.failed || attempt.leaving || attempt.closed || active !== attempt) return;
            if (attempt.paymentUrl) navigate(attempt, attempt.paymentUrl);
            else attempt.sdkFailed = true;
        };

        try {
            window.ensureVippsWidgetHostStarted();
            attempt.trigger = window.vipps.trigger(() => request(attempt), { cancelPaymentOnClose: false })
                .on('success', finish).on('cancel', (close) => dismiss(close, true))
                .on('close', () => dismiss()).on('error', failed);

            Promise.resolve(attempt.trigger.open()).catch(failed);
        } catch {
            window.setVippsPaymentBusy(false, owner);
            showError(config.error);
        }
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted && active) window.location.reload();
    });
});
