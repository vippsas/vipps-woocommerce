/* Shared loading UI for express checkout and the Checkout Block. */
(() => {
    const busyOwners = new Set();

    /** Reuse the existing overlay markup and CSS, including gateway branding. */
    function ensureSpinner() {
        let overlay = document.querySelector(".vippsoverlay");
        if (overlay) {
            return overlay;
        }

        const slug = String(window.VippsConfig?.paymentMethodSlug || "")
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9_-]+/g, "-")
            .replace(/^-+|-+$/g, "");
        const spinner = document.createElement("div");
        spinner.id = "floatingCirclesG";
        spinner.className = `vippsspinner ${slug}`.trim();

        for (let i = 1; i <= 8; i += 1) {
            const circle = document.createElement("div");
            circle.className = "f_circleG";
            circle.id = `frotateG_${String(i).padStart(2, "0")}`;
            spinner.append(circle);
        }

        overlay = document.createElement("div");
        overlay.className = "vippsoverlay";
        overlay.append(spinner);
        document.body.append(overlay);
        return overlay;
    }

    /**
     * CSS shows the overlay while body.processing is present. Track each flow
     * separately so clearing one flow cannot hide another flow's loading UI.
     */
    window.setVippsPaymentBusy = (busy, owner) => {
        if (busy) {
            ensureSpinner();
            busyOwners.add(owner);
        } else {
            busyOwners.delete(owner);
        }
        document.body.classList.toggle("processing", busyOwners.size > 0);
    };
})();
(() => {
    /**
     * Share one desktop widget host between express checkout and the Blocks method.
     * Leave the flag unset when the SDK is unavailable so a later caller can retry.
     * Starting a host does not suppress WooCommerce's own checkout redirect.
     */
    function ensureVippsWidgetHostStarted() {
        if (window.__vippsWidgetHostStarted) {
            return;
        }

        if (!window.vipps?.host) {
            return;
        }

        window.vipps.host().start();
        window.__vippsWidgetHostStarted = true;
    }

    window.ensureVippsWidgetHostStarted = ensureVippsWidgetHostStarted;
})();
(() => {
    /** Build the reusable WooCommerce confirmation dialog before any checkout starts. */
    function createVippsMobilepayDialog() {
        const actionDialog = document.createElement("dialog");
        const actionForm = document.createElement("form");
        const actionHtml = document.createElement("div");
        const actionMessage = document.createElement("div");
        const dialogActions = document.createElement("div");
        const actionCancel = document.createElement("button");
        const actionConfirm = document.createElement("vipps-mobilepay-button");

        actionDialog.className = "vipps-mobilepay-dialog";
        actionDialog.id = "action-required-dialog";
        actionForm.id = "vippsdata";
        actionForm.className = "woocommerce-checkout";
        actionForm.method = "dialog";
        actionHtml.id = "action-required-html";
        actionMessage.id = "action-required-message";
        actionMessage.className = "vipps-form-message";
        actionMessage.setAttribute("role", "alert");
        actionMessage.setAttribute("aria-live", "polite");
        actionMessage.hidden = true;
        dialogActions.className = "dialog-actions";

        actionCancel.id = "action-required-cancel";
        actionCancel.type = "button";
        actionCancel.className = "dialog-close";
        actionCancel.setAttribute("aria-label", window.VippsLocale?.cancel || "Cancel");
        actionCancel.title = window.VippsLocale?.cancel || "Cancel";
        actionCancel.textContent = "×";

        actionConfirm.id = "action-required-confirm";
        actionConfirm.setAttribute("brand", "vipps");
        actionConfirm.setAttribute("language", "no");
        actionConfirm.setAttribute("verb", "continue");
        actionConfirm.setAttribute("variant", "primary");
        actionConfirm.setAttribute("type", "button");
        actionConfirm.setAttribute("branded", "true");
        actionConfirm.setAttribute("rounded", "true");

        dialogActions.append(actionConfirm);
        actionForm.append(actionHtml, actionMessage, dialogActions);
        actionDialog.append(actionCancel, actionForm);
        document.body.append(actionDialog);

        return {
            dialog: actionDialog,
            form: actionForm,
            html: actionHtml,
            message: actionMessage,
            cancel: actionCancel,
            confirm: actionConfirm
        };
    }

    window.createVippsMobilepayDialog = createVippsMobilepayDialog;
})();
/*
 * Keeps payment-handoff detection independent from the checkout implementation.
 * The factory is exposed only because this page is currently using classic
 * script tags rather than JavaScript modules.
 */
(() => {
    /** Track a payment handoff so a page revisited after an app switch is refreshed. */
    function createVippsPaymentHandoff(options = {}) {
        const storageKey = options.storageKey || "vippsPaymentHandoff";
        const maxAgeMs = options.maxAgeMs || 30 * 60 * 1000;
        const disabledSelector = options.disabledSelector ||
            "[data-checkout-button], [data-cart-button]";
        const disabledButtonStates = new Map();

        /** Read only a valid, recent marker; stale or malformed state is discarded. */
        function get() {
            try {
                const raw = sessionStorage.getItem(storageKey);
                if (!raw) {
                    return null;
                }

                const handoff = JSON.parse(raw);

                if (!handoff.createdAt || Date.now() - handoff.createdAt >= maxAgeMs) {
                    clear();
                    return null;
                }

                return handoff;
            } catch {
                clear();
                return null;
            }
        }

        /** Record that an order has a payment URL and may outlive this page view. */
        function mark(result = {}) {
            try {
                sessionStorage.setItem(storageKey, JSON.stringify({
                    createdAt: Date.now(),
                    path: location.pathname,
                    paymentReference: result.paymentReference || result.reference || null,
                    refreshedPaths: []
                }));
            } catch {
                // Storage is optional; the payment URL must still reach the SDK.
            }
        }

        /** Remove the marker when the local attempt ends, including after cancellation. */
        function clear() {
            try {
                sessionStorage.removeItem(storageKey);
            } catch {
                // There is no persisted handoff to clear when storage is blocked.
            }
        }

        /** Prevent competing cart or checkout actions while payment is active. */
        function activate() {
            if (document.documentElement.classList.contains("payment-handoff-active")) {
                return;
            }

            document.documentElement.classList.add("payment-handoff-active");
            document.querySelectorAll(disabledSelector).forEach((button) => {
                disabledButtonStates.set(button, {
                    disabled: "disabled" in button ? button.disabled : undefined,
                    disabledAttribute: button.getAttribute("disabled"),
                    ariaDisabled: button.getAttribute("aria-disabled")
                });

                if ("disabled" in button) {
                    button.disabled = true;
                }

                button.setAttribute("disabled", "");
                button.setAttribute("aria-disabled", "true");
            });
        }

        /** Restore exactly the button states that existed before the handoff. */
        function deactivate() {
            if (!document.documentElement.classList.contains("payment-handoff-active")) {
                return;
            }

            document.documentElement.classList.remove("payment-handoff-active");
            disabledButtonStates.forEach((state, button) => {
                if (state.disabled !== undefined) {
                    button.disabled = state.disabled;
                }

                if (state.disabledAttribute === null) {
                    button.removeAttribute("disabled");
                } else {
                    button.setAttribute("disabled", state.disabledAttribute);
                }

                if (state.ariaDisabled === null) {
                    button.removeAttribute("aria-disabled");
                } else {
                    button.setAttribute("aria-disabled", state.ariaDisabled);
                }
            });
            disabledButtonStates.clear();
        }

        /** Refresh a revisited page once per path to obtain current order state. */
        function reloadOnceForStalePage() {
            const handoff = get();

            if (!handoff) {
                return;
            }

            const refreshedPaths = Array.isArray(handoff.refreshedPaths)
                ? handoff.refreshedPaths
                : [];

            if (refreshedPaths.includes(location.pathname)) {
                return;
            }

            try {
                sessionStorage.setItem(storageKey, JSON.stringify({
                    ...handoff,
                    refreshedPaths: [...refreshedPaths, location.pathname]
                }));
            } catch {
                // Reload only when the marker can prevent a reload loop.
                return;
            }

            location.reload();
        }

        /** Identify back navigation and bfcache restores that can show stale HTML. */
        function wasHistoryRestore(event) {
            const navigation = performance.getEntriesByType("navigation")[0];
            return event.persisted || navigation?.type === "back_forward";
        }

        /** Refresh restored pages; discard a marker on an ordinary fresh load. */
        function handlePageshow(event) {
            const handoff = get();

            if (!handoff) {
                return;
            }

            if (wasHistoryRestore(event)) {
                reloadOnceForStalePage();
                return;
            }

            clear();
        }

        window.addEventListener("pageshow", handlePageshow);
        window.addEventListener("focus", reloadOnceForStalePage);
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible") {
                reloadOnceForStalePage();
            }
        });

        return {
            mark,
            get,
            clear,
            activate,
            deactivate,
            reloadOnceForStalePage
        };
    }

    window.createVippsPaymentHandoff = createVippsPaymentHandoff;
})();
/*
 * This implements express checkout for WooCommerce using the Vipps Mobilepay Widget SDK
 */
(() => {
    const vippsSdk = window.vipps;
    const config = window.VippsConfig || {};
    const locale = window.VippsLocale || {};
    const body = document.body;
    const paymentHandoff = typeof window.createVippsPaymentHandoff === "function"
        ? window.createVippsPaymentHandoff()
        : null;

    if (!vippsSdk) {
        console.error("vipps: Widget SDK is not available");
        return;
    }

    if (typeof window.createVippsMobilepayDialog !== "function") {
        console.error("vipps: Dialog module is not available");
        return;
    }

    const dialogUi = window.createVippsMobilepayDialog();
    const checkout = createCheckoutController();

    /** Prefix diagnostic warnings so checkout failures are easy to find. */
    function warnVippsExpress(message, data) {
        if (data === undefined) {
            console.warn("[Vipps Express]", message);
            return;
        }

        console.warn("[Vipps Express]", message, data);
    }

    /** Prefix diagnostic errors while retaining optional response details. */
    function errorVippsExpress(message, data) {
        if (data === undefined) {
            console.error("[Vipps Express]", message);
            return;
        }

        console.error("[Vipps Express]", message, data);
    }

    /** Resolve localized text and substitute the plugin's positional values. */
    function translate(key, fallback, ...values) {
        let message = locale[key] || fallback;

        values.forEach((value) => {
            message = message.replace("%s", String(value));
        });

        return message;
    }

    /** Notify the automatic-purchase page about an attempt's UI state. */
    function emitVippsPurchaseEvent(name, detail = {}) {
        document.dispatchEvent(new CustomEvent(name, { detail }));
    }

    // Hook vippsInit to woocommerce/product-collection render event. LP 29.11.2024
    // IOK 2026-01-14 available from woo 9.4 - so the buy now block will not be available until that version
    body.addEventListener("wc-blocks_product_list_rendered", () => {
        body.dispatchEvent(new Event("vippsInit"));
    });

    // Allow other code to trigger and observe this lifecycle event through
    // either the native or jQuery event API.
    body.addEventListener("vippsInit", handleVippsInit, true);
    if (window.jQuery) {
        window.jQuery(body).on("vippsInit", handleVippsInit);
    }
    document.addEventListener("click", handlePurchaseClick);

    bindVariationEvents();
    subscribeToCartChanges();
    body.dispatchEvent(new Event("vippsInit"));

    /** Run initialization once when both native and jQuery listeners see an event. */
    function handleVippsInit(event) {
        const eventObject = event?.originalEvent || event;

        if (eventObject.__vippsInitHandled) {
            return;
        }

        eventObject.__vippsInitHandled = true;
        vippsInit();
    }

    /** Mark current buttons ready and notify integrations registered with WP hooks. */
    function vippsInit() {
        document.querySelectorAll(
            ".button.single-product.vipps-buy-now, .vipps-express-checkout"
        ).forEach((wrapper) => {
            wrapper.classList.add("initialized");
        });

        wp.hooks.doAction("vippsInit");
    }

    /** Own one express attempt from order creation through SDK handoff or retry. */
    function createCheckoutController() {
        let locked = false;
        let currentAttempt = null;
        let nextAttemptId = 0;
        let dialogBusy = false;
        let dialogConfirmed = false;
        let paymentSucceeded = false;

        if (typeof window.ensureVippsWidgetHostStarted === "function") {
            window.ensureVippsWidgetHostStarted();
        } else {
            vippsSdk.host().start();
        }

        // Resolve a fresh URL for each SDK opening, including a retry after
        // the confirmation form adds fields to the same transaction.
        const trigger = vippsSdk.trigger(async () => {
            if (!currentAttempt) {
                warnVippsExpress("Trigger resolver has no active attempt; returning null.");
                return null;
            }

            const attemptId = currentAttempt.id;
            let result;

            // The attempt is locked while WooCommerce creates the order and
            // requests a payment session; no payment URL exists yet.
            result = await createPaymentSession(
                currentAttempt.transaction,
                currentAttempt.path
            );

            if (!currentAttempt || currentAttempt.id !== attemptId) {
                warnVippsExpress("Ignoring stale express response.", {
                    attemptId,
                    currentAttempt
                });
                return null;
            }

            currentAttempt.lastResponse = result;

            if (Number(result.ok) === 1) {
                if (!result.url) {
                    // Order creation reported success, but payment cannot start.
                    errorVippsExpress("Successful express checkout response has no payment URL.", {
                        result,
                        currentAttempt
                    });
                    const button = currentAttempt.button;
                    const message = translate(
                        "missingPaymentUrl",
                        "Successful checkout response has no payment URL"
                    );
                    clearAttempt();
                    showError(message, button);
                    throw new Error(message);
                }

                // The order has a payment URL, but payment is still in progress.
                // Keep the attempt locked and the purchase button hidden beneath
                // the desktop modal (or while the customer switches apps).
                paymentHandoff?.mark(result);
                // The SDK is taking over, so the order creation spinner can stop.
                // Do not emit vippsPurchaseFinished at this handoff.
                setPurchaseButtonsBusy(false);
                paymentHandoff?.activate();
                return result.url;
            }

            if (Number(result.ok) === 2) {
                // WooCommerce needs more input before it can return a payment URL.
                // Keep this attempt so confirmation can retry the request.
                showActionRequiredDialog(result.html || "");
                return null;
            }

            if (Number(result.ok) === 0) {
                errorVippsExpress("Express checkout endpoint returned failure.", {
                    result
                });
                const button = currentAttempt.button;
                clearAttempt();
                showError(
                    result.msg || translate("expressCheckoutFailed", "Express checkout failed"),
                    button
                );

                if (result.url) {
                    window.location.assign(result.url);
                }

                throw new Error(
                    result.msg || translate("expressCheckoutFailed", "Express checkout failed")
                );
            }

            const button = currentAttempt.button;
            const message = translate(
                "unexpectedCheckoutResponse",
                "Unexpected express checkout response"
            );
            errorVippsExpress("Unexpected express checkout response.", {
                result
            });
            clearAttempt();
            showError(message, button);
            throw new Error(message);
        });

        trigger
            .on("success", (close, redirectUrl) => {
                // Payment succeeded. Keep the purchase hidden while the SDK
                // closes its modal and the return URL takes over.
                paymentSucceeded = true;
                if (redirectUrl) {
                    showSuccessRedirectOverlay();
                }
                close();

                if (redirectUrl) {
                    window.location.assign(redirectUrl);
                }
            })
            .on("cancel", (close, redirectUrl) => {
                // Payment was canceled; the local attempt can end and retry can
                // become available if the customer stays on this page.
                clearAttempt();
                close();

                if (redirectUrl) {
                    window.location.assign(redirectUrl);
                }
            })
            .on("close", () => {
                // A close without success ends the local attempt. Closing after
                // success must not reveal the purchase button again.
                if (!paymentSucceeded) {
                    clearAttempt();
                }
            })
            .on("error", (error) => {
                errorVippsExpress("Widget SDK error event.", error);
                if (
                    error instanceof vippsSdk.InvalidTriggerUrlError &&
                    error.url === null
                ) {
                    return;
                }

                const button = currentAttempt?.button;
                if (button) {
                    // The SDK could not complete the handoff. End the attempt
                    // and show the error so the customer can retry.
                    clearAttempt();
                    showError(
                        error.message || translate("vippsCheckoutFailed", "Vipps checkout failed"),
                        button
                    );
                }
                console.error(error);
            });

        // A history restore can bring back this document with its redirect
        // overlay intact, even when sessionStorage is unavailable.
        window.addEventListener("pageshow", (event) => {
            if (event.persisted && paymentSucceeded && !paymentHandoff?.get()) {
                window.location.reload();
            }
        });

        /** Cover the old page while navigation to the completed order is pending. */
        function showSuccessRedirectOverlay() {
            window.setVippsPaymentBusy(true, "express-redirect");

            const overlay = document.querySelector(".vippsoverlay");
            overlay.style.position = "fixed";
            overlay.style.inset = "0";
            overlay.style.zIndex = "2147483647";
            overlay.style.pointerEvents = "auto";
            overlay.style.background = "rgba(255, 255, 255, 0.88)";
            overlay.classList.add("vipps-redirecting");

            let status = overlay.querySelector(".vipps-redirect-status");
            if (!status) {
                status = document.createElement("p");
                status.className = "vipps-redirect-status";
                status.setAttribute("role", "status");
                status.setAttribute("aria-live", "polite");
                overlay.insertBefore(status, overlay.querySelector(".vippsspinner"));
            }

            status.textContent = translate(
                "paymentSuccessfulRedirecting",
                "Payment successful. Redirecting…"
            );
        }

        /** Lock a new attempt and show loading state before requesting an order. */
        function begin(transaction, button, event, path) {
            if (locked) {
                warnVippsExpress("Attempt already locked; refusing new attempt.", {
                    transaction,
                    button,
                    path
                });
                return false;
            }

            // A new attempt starts before the order request; prevent duplicate
            // clicks through the payment handoff until this attempt ends.
            locked = true;
            paymentSucceeded = false;
            currentAttempt = {
                id: ++nextAttemptId,
                transaction,
                path,
                button,
                event,
                lastResponse: null
            };
            setPurchaseButtonsBusy(true);
            body.dataset.vippsPurchaseActive = "true";
            emitVippsPurchaseEvent("vippsPurchaseStarted", {
                wrapper: button
            });
            return true;
        }

        /** Carry confirmed form fields into the next order request. */
        function addPostData(extraPostData) {
            if (!currentAttempt) {
                throw new Error("No active checkout attempt");
            }

            currentAttempt.transaction.post = {
                ...(currentAttempt.transaction.post || {}),
                ...extraPostData
            };
        }

        /** Let the SDK request a URL and open payment, handling launch failures. */
        async function start() {
            if (!currentAttempt) {
                throw new Error("No active checkout attempt");
            }

            try {
                await trigger.open();
            } catch (error) {
                errorVippsExpress("Trigger open threw.", error);
                if (
                    error instanceof vippsSdk.InvalidTriggerUrlError &&
                    error.url === null
                ) {
                    return;
                }

                const button = currentAttempt?.button;
                if (button) {
                    clearAttempt();
                    showError(
                        error.message || translate("vippsCheckoutFailed", "Vipps checkout failed"),
                        button
                    );
                }
                console.error(error);
            }
        }

        /** Release local state and announce that the page can offer a retry. */
        function clearAttempt() {
            const attempt = currentAttempt;
            locked = false;
            currentAttempt = null;
            dialogBusy = false;
            paymentHandoff?.clear();
            paymentHandoff?.deactivate();
            setPurchaseButtonsBusy(false);
            dialogUi.confirm.disabled = false;
            dialogUi.confirm.removeAttribute("aria-disabled");

            if (attempt) {
                delete body.dataset.vippsPurchaseActive;
                emitVippsPurchaseEvent("vippsPurchaseFinished", {
                    wrapper: attempt.button
                });
            }
        }

        /** Present server-requested fields while keeping the order attempt active. */
        function showActionRequiredDialog(html) {
            dialogUi.html.innerHTML = html;
            clearDialogValidation();
            dialogUi.confirm.disabled = false;
            dialogUi.confirm.removeAttribute("aria-disabled");
            dialogBusy = false;
            dialogConfirmed = false;
            dialogUi.dialog.showModal();
        }

        /** Close the form; its close listener performs common cancellation cleanup. */
        function cancelActionRequired() {
            if (dialogUi.dialog.open) {
                dialogUi.dialog.close();
            }
        }

        dialogUi.cancel.addEventListener("click", cancelActionRequired);
        dialogUi.dialog.addEventListener("close", () => {
            // Escape, the cancel button, and programmatic closes all arrive here.
            // Only confirmation continues the current order request.
            if (!dialogConfirmed) {
                clearAttempt();
            }
        });
        dialogUi.confirm.addEventListener("click", async () => {
            if (dialogBusy || !currentAttempt) {
                return;
            }

            if (!validateConfirmationForm()) {
                return;
            }

            dialogBusy = true;
            dialogUi.confirm.disabled = true;
            dialogUi.confirm.setAttribute("aria-disabled", "true");

            addPostData({
                ...serializeForm(dialogUi.form),
                confirmed: 1
            });

            dialogConfirmed = true;
            dialogUi.dialog.close();
            await start();
        });

        dialogUi.form.addEventListener("input", clearDialogValidation);
        dialogUi.form.addEventListener("change", clearDialogValidation);

        return { begin, addPostData, start };

        /** Combine browser, terms, and integration validation before resubmitting. */
        function validateConfirmationForm() {
            clearDialogValidation();

            let validation = validateTermsAndConditions(dialogUi.form);

            if (!dialogUi.form.checkValidity()) {
                dialogUi.form.reportValidity();
                validation = false;
                setDialogValidationMessage(translate(
                    "correctHighlightedFields",
                    "Please correct the highlighted fields."
                ));
            }

            validation = wp.hooks.applyFilters(
                "vippsValidateExpressCheckoutForm",
                validation,
                dialogUi.form,
                currentAttempt
            );

            if (!validation && !dialogUi.message.textContent) {
                setDialogValidationMessage(translate(
                    "checkFormBeforeContinuing",
                    "Please check the form before continuing."
                ));
            }

            if (validation) {
                clearDialogValidation();
            }

            return Boolean(validation);
        }

        /** Require acceptance when WooCommerce included a terms checkbox. */
        function validateTermsAndConditions(form) {
            const termsBoxes = Array.from(
                form.querySelectorAll('.input-checkbox[name="terms"], input[name="terms"]')
            );

            if (termsBoxes.length === 0) {
                return true;
            }

            const accepted = termsBoxes.some((termsBox) => termsBox.checked);
            termsBoxes.forEach((termsBox) => {
                const container = termsBox.closest(".validate-required");

                if (container) {
                    container.classList.toggle("woocommerce-invalid", !accepted);
                    container.classList.toggle(
                        "woocommerce-invalid-required-field",
                        !accepted
                    );
                }
            });

            if (!accepted) {
                setDialogValidationMessage(translate(
                    "termsAndConditionsError",
                    "Please accept the terms and conditions."
                ));
            }

            return accepted;
        }

        /** Remove previous validation feedback before another confirmation attempt. */
        function clearDialogValidation() {
            dialogUi.message.textContent = "";
            dialogUi.message.hidden = true;

            dialogUi.form
                .querySelectorAll(".woocommerce-invalid, .woocommerce-invalid-required-field")
                .forEach((element) => {
                    element.classList.remove(
                        "woocommerce-invalid",
                        "woocommerce-invalid-required-field"
                    );
                });
        }

        /** Show a single accessible explanation for invalid confirmation input. */
        function setDialogValidationMessage(message) {
            dialogUi.message.textContent = message;
            dialogUi.message.hidden = false;
        }
    }

    /** Send the normalized express transaction to the plugin's REST endpoint. */
    async function createPaymentSession(transaction, path) {
        const result = await wp.apiFetch({
            path,
            method: "POST",
            headers: {
                "Accept-Language": `${config.vippslocale || "nb_NO"}, *`
            },
            data: transaction
        });

        return result;
    }

    // Using the Vipps SDK, get a payment URL from the new REST endpoint and let
    // the SDK start the express checkout session. IOK 2026-09-04
    /** Route clicks from supported express buttons to the correct order flow. */
    async function handlePurchaseClick(event) {
        const wrapper = event.target.closest?.(
            ".button.single-product.vipps-buy-now.initialized, " +
            ".vipps-express-checkout:not(body)"
        );

        if (!wrapper || !wrapper.querySelector("vipps-mobilepay-button")) {
            return;
        }

        event.preventDefault();

        if (body.classList.contains("processing")) {
            return;
        }

        if (wrapper.classList.contains("vipps-express-checkout")) {
            await handleCartPurchase(wrapper, event);
            return;
        }

        await handleSingleProductPurchase(wrapper, event);
    }

    /** Start checkout for the current cart after legacy extension hooks run. */
    async function handleCartPurchase(wrapper, event) {
        if (wrapper.classList.contains("disabled") || wrapper.hasAttribute("disabled")) {
            showError(translate(
                "cartCheckoutUnavailable",
                "Cannot start express checkout: cart checkout is unavailable"
            ), wrapper);
            return;
        }

        removeErrorMessages();

        wp.hooks.doAction("vippsBuyCart", wrapper, event);

        const transaction = wp.hooks.applyFilters(
            "vippsBuyCartData",
            transactionFromPostData({}),
            wrapper,
            event
        );

        // Cart express checkout gets products from the WooCommerce cart session,
        // but still sends cookies and post metadata. IOK 2026-09-04
        const path = wrapper.dataset.sec
            ? `/woo-vipps/v1/express_checkout?sec=${encodeURIComponent(wrapper.dataset.sec)}`
            : "/woo-vipps/v1/express_checkout";

        if (!checkout.begin(transaction, wrapper, event, path)) {
            return;
        }

        await checkout.start();
    }

    /** Build a one-product order, preserving compatibility hooks and fallbacks. */
    async function handleSingleProductPurchase(wrapper, event) {
        if (wrapper.classList.contains("disabled") || wrapper.hasAttribute("disabled")) {
            showVariationMessage(wrapper);
            return;
        }

        removeErrorMessages();

        wp.hooks.doAction("vippsBuySingleProduct", wrapper, event);

        let compatMode = wrapper.classList.contains("compat-mode");
        compatMode = wp.hooks.applyFilters(
            "vippsBuySingleProductCompatMode",
            compatMode,
            wrapper,
            event
        );

        if (compatMode) {
            runCompatibilityAction(wrapper, event);
            return;
        }

        // First build the legacy data structure so existing hooks can still see
        // the same input as before. IOK 2026-09-04
        let transaction = buildTransaction(wrapper);
        if (!transaction) {
            return;
        }

        const legacyData = transaction.legacyData;
        // Finally, create a hook for even weirder themes.
        transaction = transactionFromLegacyData(wp.hooks.applyFilters(
            "vippsBuySingleProductData",
            legacyData,
            wrapper,
            event
        ));

        // If filters and fallbacks could not identify the product, do not start
        // an empty transaction against the REST endpoint. IOK 2026-09-04
        if (!hasProductIdentifier(transaction)) {
            showError(translate(
                "productIdentifiersMissing",
                "Cannot buy product: product id, variation id and sku are missing"
            ), wrapper);
            return;
        }

        if (!checkout.begin(transaction, wrapper, event, "/woo-vipps/v1/express_checkout_single")) {
            return;
        }

        await checkout.start();
    }

    /** Reject one-product requests that cannot identify a WooCommerce product. */
    function hasProductIdentifier(transaction) {
        return Boolean(
            transaction?.product_id ||
            transaction?.variation_id ||
            transaction?.sku
        );
    }

    /** Gather product and form data in the shape expected by older filters. */
    function buildTransaction(wrapper) {
        // Older buttons may carry product data directly on the button. Preserve
        // both jQuery-style underscore attributes and modern dataset names.
        // IOK 2026-09-04
        const legacyData = getElementData(wrapper);

        if (legacyData.product_id || legacyData.product_sku) {
            return { legacyData };
        }

        const form = wrapper.closest("form.cart");
        if (!form) {
            showError(translate(
                "productFormNotFound",
                "Cannot buy product: product form not found"
            ), wrapper);
            return null;
        }

        // Initialize with the entire content of the form if we have it.
        for (const [name, value] of new FormData(form).entries()) {
            if (name !== "add-to-cart") {
                legacyData[name] = value;
            }
        }

        const productDataButton = form.querySelector("button[data-product_id], button[data-product-id]");
        legacyData.product_id = legacyData.product_id ||
            form.querySelector('[name="product_id"]')?.value ||
            form.querySelector('[name="add-to-cart"]')?.value ||
            getElementData(productDataButton).product_id || "";
        legacyData.variation_id = legacyData.variation_id ||
            form.querySelector('[name="variation_id"]')?.value || "";
        legacyData.quantity = legacyData.quantity || "1";
        legacyData.product_sku = legacyData.product_sku ||
            legacyData.sku ||
            form.querySelector('[name="sku"]')?.value ||
            form.closest(".product")?.querySelector(".sku")?.textContent.trim() || "";
        delete legacyData.sku;

        return { legacyData };
    }

    /** Normalize HTML data attributes from current and older button markup. */
    function getElementData(element) {
        if (!element) {
            return {};
        }

        const data = {};
        Array.from(element.attributes || []).forEach((attribute) => {
            if (!attribute.name.startsWith("data-")) {
                return;
            }

            const name = attribute.name.slice(5).replace(/-/g, "_");
            data[name] = attribute.value;
        });

        return {
            ...data,
            product_id: data.product_id || element.dataset?.productId || "",
            variation_id: data.variation_id || element.dataset?.variationId || "",
            product_sku: data.product_sku || element.dataset?.productSku || "",
            quantity: data.quantity || element.dataset?.quantity || ""
        };
    }

    /** Move filtered legacy fields into the REST endpoint's transaction shape. */
    function transactionFromLegacyData(data) {
        const source = data || {};
        const post = { ...(source.post || {}) };
        const mainFields = new Set([
            "product_id",
            "variation_id",
            "product_sku",
            "sku",
            "quantity",
            "post",
            "action",
            "add-to-cart"
        ]);

        Object.entries(source).forEach(([name, value]) => {
            if (!mainFields.has(name)) {
                post[name] = value;
            }
        });

        // The REST endpoint expects the product identifiers at top level, while
        // the rest of the serialized WooCommerce form data travels as post data.
        // IOK 2026-09-04
        return {
            product_id: source.product_id || "",
            variation_id: source.variation_id || "",
            sku: source.sku || source.product_sku || "",
            quantity: source.quantity || "1",
            ...transactionFromPostData(post)
        };
    }

    /** Attach browser context shared by cart and single-product requests. */
    function transactionFromPostData(post) {
        return {
            cookies: getCookies(),
            post: addOrderAttributionData(post)
        };
    }

    /** Pass eligible browser cookies to the express endpoint for session context. */
    function getCookies() {
        const cookies = {};

        document.cookie.split(";").forEach((cookie) => {
            const separator = cookie.indexOf("=");
            const name = separator >= 0 ? cookie.slice(0, separator).trim() : cookie.trim();

            if (!name || name === "wordpress_test_cookie" || name.startsWith("wp-settings")) {
                return;
            }

            const value = separator >= 0 ? cookie.slice(separator + 1) : "";
            cookies[decodeCookieValue(name)] = decodeCookieValue(value);
        });

        return cookies;
    }

    /** Decode a cookie component without failing on malformed percent escapes. */
    function decodeCookieValue(value) {
        try {
            return decodeURIComponent(value);
        } catch {
            return value;
        }
    }

    /** Include WooCommerce attribution fields when the merchant enabled them. */
    function addOrderAttributionData(post) {
        // order attribution can be turned on or off in the settings because of some sites having issues with it in the past. IOK 2026-09-10
        if (config.expressOrderAttribution  != "yes") {
            return post;
        }
        const attribution = window.wc_order_attribution;

        if (
            !attribution?.params ||
            !attribution?.fields ||
            typeof attribution.getAttributionData !== "function"
        ) {
            return post;
        }

        const data = attribution.getAttributionData();
        const prefix = attribution.params.prefix || "";

        // WooCommerce form checkout posts these as prefixed fields, so keep the
        // same shape for backwards compatibility. IOK 2026-09-04
        Object.keys(attribution.fields).forEach((key) => {
            const postKey = `${prefix}${key}`;
            if (!(postKey in post)) {
                post[postKey] = data?.[key] ?? "";
            }
        });

        return post;
    }

    /** Delegate buying to the normal add-to-cart button for compatible plugins. */
    function runCompatibilityAction(wrapper, event) {
        const form = wrapper.closest("form");
        const addToCartButton = form?.querySelector(".single_add_to_cart_button");

        if (!form || !addToCartButton) {
            showError(translate(
                "productFormNotFound",
                "Cannot add product: product form not found"
            ), wrapper);
            return;
        }

        // In compatibility mode, we delegate to the existing buy now button
        // instead of doing the logic ourselves. This allows more filters and
        // actions in existing plugins to run. IOK 2019-02-26
        let action = () => {
            let input = form.querySelector("input[name='vipps_compat_mode']");
            if (!input) {
                input = document.createElement("input");
                input.type = "hidden";
                input.name = "vipps_compat_mode";
                form.prepend(input);
            }
            input.value = "1";
            addToCartButton.click();
        };

        action = wp.hooks.applyFilters(
            "vippsBuySingleProductCompatModeAction",
            action,
            wrapper,
            event
        );

        setPurchaseButtonsBusy(true);
        action();
    }

    /** Convert confirmation fields to post data, excluding add-to-cart controls. */
    function serializeForm(form) {
        const data = {};

        for (const [name, value] of new FormData(form).entries()) {
            if (name !== "add-to-cart") {
                data[name] = value;
            }
        }

        return data;
    }

    /** Synchronize button loading state with the shared page overlay. */
    function setPurchaseButtonsBusy(busy) {
        document.querySelectorAll(".vipps-buy-now, .vipps-express-checkout").forEach((wrapper) => {
            wrapper.classList.toggle("loading", busy);

            if (busy) {
                wrapper.dataset.vippsBusy = "true";
                wrapper.setAttribute("disabled", "");
                wrapper.setAttribute("inactive", "inactive");
                wrapper.setAttribute("aria-disabled", "true");
            } else {
                delete wrapper.dataset.vippsBusy;
                wrapper.removeAttribute("disabled");
                wrapper.removeAttribute("inactive");
                wrapper.removeAttribute("aria-disabled");
                wrapper.classList.remove("loading");

                if (wrapper.classList.contains("disabled")) {
                    wrapper.setAttribute("disabled", "");
                }
            }
        });

        window.setVippsPaymentBusy(busy, "express");
    }

    /** Follow WooCommerce variation and bundle availability on product pages. */
    function bindVariationEvents() {
        const $ = window.jQuery;
        if (!$) return;

        $(body).on("found_variation", (event, variation) => {
            const form = event.target.closest?.("form.cart");
            const wrapper = form?.querySelector(".vipps-buy-now");
            if (!wrapper) return;

            wrapper.classList.add("variation-found");
            setPurchaseButtonDisabled(wrapper, !variation?.is_purchasable ||
                !variation?.is_in_stock || !variation?.variation_is_visible);
            removeErrorMessages();
        });

        $(body).on("reset_data", (event) => {
            const form = event.target.closest?.("form.cart");
            const wrapper = form?.querySelector(".vipps-buy-now");
            if (!wrapper) return;

            wrapper.classList.remove("variation-found");
            setPurchaseButtonDisabled(wrapper, true);
            removeErrorMessages();
        });

        $(body).on("wc_variation_form", () => {
            body.dispatchEvent(new Event("vippsInit"));
        });

        $(body).on("woocommerce-product-bundle-hide", () => {
            document.querySelectorAll("form .vipps-buy-now").forEach((wrapper) => {
                wrapper.classList.add("compat-mode");
                setPurchaseButtonDisabled(wrapper, true);
            });
            removeErrorMessages();
        });

        $(body).on("woocommerce-product-bundle-show", () => {
            document.querySelectorAll("form .vipps-buy-now").forEach((wrapper) => {
                setPurchaseButtonDisabled(wrapper, false);
            });
            removeErrorMessages();
        });
    }

    /** Keep wrapper styling and its disabled attribute in agreement. */
    function setPurchaseButtonDisabled(wrapper, disabled) {
        wrapper.classList.toggle("disabled", disabled);

        if (disabled) {
            wrapper.setAttribute("disabled", "");
        } else {
            wrapper.removeAttribute("disabled");
        }
    }

    /** Update the mini-cart checkout link when the Store API supplies a new URL. */
    function subscribeToCartChanges() {
        if (!wp.data) return;

        let previousCheckoutUrl = "";
        wp.data.subscribe(() => {
            const button = document.querySelector(
                "a.wp-block-woocommerce-mini-cart-checkout-button-block"
            );
            if (!button) return;

            const cart = wp.data.select("wc/store/cart")?.getCartData?.();
            const checkoutUrl = cart?.extensions?.["woo-vipps"]?.checkout_url;
            if (!checkoutUrl || checkoutUrl === previousCheckoutUrl) return;

            previousCheckoutUrl = checkoutUrl;
            button.href = checkoutUrl;
        });
    }

    /** Explain whether a variation is unselected or unavailable. */
    function showVariationMessage(wrapper) {
        const params = window.wc_add_to_cart_variation_params;
        const message = wrapper.classList.contains("variation-found")
            ? params?.i18n_unavailable_text || translate(
                "variationUnavailable",
                "This product variation is not available."
            )
            : params?.i18n_make_a_selection_text || translate(
                "variationSelectionRequired",
                "Please choose a product variation."
            );
        showError(message, wrapper);
    }

    /** Remove prior Vipps errors before a new attempt or error is shown. */
    function removeErrorMessages() {
        document.querySelectorAll(".woocommerce-error.vipps-error").forEach((element) => {
            element.remove();
        });
        document.querySelector("#vipps-error-dialog")?.close();

        emitDocumentEvent("woo-vipps-remove-errors");
        wp.hooks.doAction("vippsRemoveErrorMessages");
    }

    /** Publish an error to the purchase page, integrations, and modal UI. */
    function showError(message, wrapper) {
        removeErrorMessages();

        emitVippsPurchaseEvent("vippsPurchaseError", {
            message: String(message),
            wrapper
        });

        let markup = `<p><ul class="woocommerce-error vipps-error vipps-default-error-message vipps-buy-now-error"><li>${escapeHtml(message)}</li></ul></p>`;
        markup = wp.hooks.applyFilters("vippsErrorMessage", markup, wrapper);

        emitDocumentEvent("woo-vipps-error-message", [markup, wrapper]);
        wp.hooks.doAction("vippsAddErrorMessage", markup, wrapper);

        showErrorDialog(markup);
    }

    /** Present checkout errors where cart and mini-cart layouts can show them. */
    function showErrorDialog(markup) {
        const dialog = ensureErrorDialog();
        dialog.content.innerHTML = markup;
        dialog.dialog.showModal();
    }

    // Cart and minicart layouts often render awkwardly with inline WooCommerce
    // errors, so show checkout errors in a small dialog instead. IOK 2026-09-04
    /** Reuse one modal error container across repeated purchase attempts. */
    function ensureErrorDialog() {
        let dialog = document.querySelector("#vipps-error-dialog");
        if (dialog) {
            return {
                dialog,
                content: dialog.querySelector(".vipps-error-dialog-content")
            };
        }

        dialog = document.createElement("dialog");
        dialog.id = "vipps-error-dialog";
        dialog.className = "vipps-mobilepay-dialog vipps-error-dialog";

        const close = document.createElement("button");
        close.type = "button";
        close.className = "dialog-close";
        close.setAttribute("aria-label", locale.close || "Close");
        close.title = locale.close || "Close";
        close.textContent = "×";

        const content = document.createElement("div");
        content.className = "vipps-error-dialog-content";

        close.addEventListener("click", () => dialog.close());
        dialog.append(close, content);
        document.body.append(dialog);

        return { dialog, content };
    }

    /** Notify integrations through both native and jQuery event systems. */
    function emitDocumentEvent(name, args = []) {
        document.dispatchEvent(new CustomEvent(name, { detail: args }));

        if (window.jQuery) {
            window.jQuery(document).trigger(name, args);
        }
    }

    /** Treat server and validation messages as text before building error HTML. */
    function escapeHtml(value) {
        const element = document.createElement("div");
        element.textContent = String(value || "");
        return element.innerHTML;
    }
})();
