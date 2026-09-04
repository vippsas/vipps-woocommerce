(() => {
    function createVippsMobilepayDialog() {
        const actionDialog = document.createElement("dialog");
        const actionForm = document.createElement("form");
        const actionHtml = document.createElement("div");
        const dialogActions = document.createElement("div");
        const actionCancel = document.createElement("button");
        const actionConfirm = document.createElement("vipps-mobilepay-button");

        actionDialog.className = "vipps-mobilepay-dialog";
        actionDialog.id = "action-required-dialog";
        actionForm.id = "vippsdata";
        actionForm.className = "woocommerce-checkout";
        actionForm.method = "dialog";
        actionHtml.id = "action-required-html";
        dialogActions.className = "dialog-actions";

        actionCancel.id = "action-required-cancel";
        actionCancel.type = "button";
        actionCancel.className = "dialog-close";
        actionCancel.setAttribute("aria-label", "Cancel");
        actionCancel.title = "Cancel";
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
        actionForm.append(actionHtml, dialogActions);
        actionDialog.append(actionCancel, actionForm);
        document.body.append(actionDialog);

        return {
            dialog: actionDialog,
            form: actionForm,
            html: actionHtml,
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
    function createVippsPaymentHandoff(options = {}) {
        const storageKey = options.storageKey || "vippsPaymentHandoff";
        const maxAgeMs = options.maxAgeMs || 30 * 60 * 1000;
        const disabledSelector = options.disabledSelector ||
            "[data-checkout-button], [data-cart-button]";

        function get() {
            const raw = sessionStorage.getItem(storageKey);

            if (!raw) {
                return null;
            }

            try {
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

        function mark(result = {}) {
            sessionStorage.setItem(storageKey, JSON.stringify({
                createdAt: Date.now(),
                path: location.pathname,
                paymentReference: result.paymentReference || result.reference || null,
                refreshedPaths: []
            }));
        }

        function clear() {
            sessionStorage.removeItem(storageKey);
        }

        function setDisabled(disabled) {
            document.querySelectorAll(disabledSelector).forEach((button) => {
                if ("disabled" in button) {
                    button.disabled = disabled;
                }

                if (disabled) {
                    button.setAttribute("disabled", "");
                    button.setAttribute("aria-disabled", "true");
                } else {
                    button.removeAttribute("disabled");
                    button.removeAttribute("aria-disabled");
                }
            });
        }

        function activate() {
            document.documentElement.classList.add("payment-handoff-active");
            setDisabled(true);
        }

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

            sessionStorage.setItem(storageKey, JSON.stringify({
                ...handoff,
                refreshedPaths: [...refreshedPaths, location.pathname]
            }));

            location.reload();
        }

        function wasHistoryRestore(event) {
            const navigation = performance.getEntriesByType("navigation")[0];
            return event.persisted || navigation?.type === "back_forward";
        }

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

    // Hook vippsInit to woocommerce/product-collection render event. LP 29.11.2024
    // IOK 2026-01-14 available from woo 9.4 - so the buy now block will not be available until that version
    body.addEventListener("wc-blocks_product_list_rendered", () => {
        body.dispatchEvent(new Event("vippsInit"));
    });

    // Allow other guys to do this too
    body.addEventListener("vippsInit", vippsInit);
    if (window.jQuery) {
        window.jQuery(body).on("vippsInit", vippsInit);
    }
    document.addEventListener("click", handlePurchaseClick);

    bindVariationEvents();
    subscribeToCartChanges();
    vippsInit();

    function vippsInit() {
        document.querySelectorAll(".button.single-product.vipps-buy-now").forEach((wrapper) => {
            wrapper.classList.add("initialized");
        });

        if (window.wp?.hooks) {
            window.wp.hooks.doAction("vippsInit");
        }
    }

    function createCheckoutController() {
        let locked = false;
        let currentAttempt = null;
        let nextAttemptId = 0;
        let dialogBusy = false;

        vippsSdk.host().start();

        const trigger = vippsSdk.trigger(async () => {
            if (!currentAttempt) {
                return null;
            }

            const attemptId = currentAttempt.id;
            let result;

            result = await createPaymentSession(currentAttempt.transaction);

            if (!currentAttempt || currentAttempt.id !== attemptId) {
                return null;
            }

            currentAttempt.lastResponse = result;

            if (Number(result.ok) === 1) {
                if (!result.url) {
                    clearAttempt();
                    throw new Error("Successful checkout response has no payment URL");
                }

                paymentHandoff?.mark(result);
                clearAttempt();
                paymentHandoff?.activate();
                return result.url;
            }

            if (Number(result.ok) === 2) {
                showActionRequiredDialog(result.html || "");
                return null;
            }

            if (Number(result.ok) === 0) {
                const button = currentAttempt.button;
                clearAttempt();
                showError(result.msg || "Express checkout failed", button);

                if (result.url) {
                    window.location.assign(result.url);
                }

                throw new Error(result.msg || "Express checkout failed");
            }

            clearAttempt();
            throw new Error("Unexpected express checkout response");
        });

        trigger
            .on("success", (close, redirectUrl) => {
                clearAttempt();
                close();

                if (redirectUrl) {
                    window.location.assign(redirectUrl);
                }
            })
            .on("cancel", (close, redirectUrl) => {
                clearAttempt();
                close();

                if (redirectUrl) {
                    window.location.assign(redirectUrl);
                }
            })
            .on("close", clearAttempt)
            .on("error", (error) => {
                if (
                    error instanceof vippsSdk.InvalidTriggerUrlError &&
                    error.url === null
                ) {
                    return;
                }

                const button = currentAttempt?.button;
                if (button) {
                    clearAttempt();
                    showError(error.message || "Vipps checkout failed", button);
                }
                console.error(error);
            });

        function begin(transaction, button, event) {
            if (locked) {
                return false;
            }

            locked = true;
            currentAttempt = {
                id: ++nextAttemptId,
                transaction,
                button,
                event,
                lastResponse: null
            };
            setPurchaseButtonsBusy(true);
            return true;
        }

        function addPostData(extraPostData) {
            if (!currentAttempt) {
                throw new Error("No active checkout attempt");
            }

            currentAttempt.transaction.post = {
                ...(currentAttempt.transaction.post || {}),
                ...extraPostData
            };
        }

        async function start() {
            if (!currentAttempt) {
                throw new Error("No active checkout attempt");
            }

            try {
                await trigger.open();
            } catch (error) {
                if (
                    error instanceof vippsSdk.InvalidTriggerUrlError &&
                    error.url === null
                ) {
                    return;
                }

                const button = currentAttempt?.button;
                if (button) {
                    clearAttempt();
                    showError(error.message || "Vipps checkout failed", button);
                }
                console.error(error);
            }
        }

        function clearAttempt() {
            locked = false;
            currentAttempt = null;
            dialogBusy = false;
            setPurchaseButtonsBusy(false);
            dialogUi.confirm.disabled = false;
            dialogUi.confirm.removeAttribute("aria-disabled");
        }

        function showActionRequiredDialog(html) {
            dialogUi.html.innerHTML = html;
            dialogUi.confirm.disabled = false;
            dialogUi.confirm.removeAttribute("aria-disabled");
            dialogBusy = false;
            dialogUi.dialog.showModal();
        }

        function cancelActionRequired() {
            if (dialogUi.dialog.open) {
                dialogUi.dialog.close();
            }

            clearAttempt();
        }

        dialogUi.cancel.addEventListener("click", cancelActionRequired);
        dialogUi.confirm.addEventListener("click", async () => {
            if (dialogBusy || !currentAttempt) {
                return;
            }

            // Server-provided dialog HTML may contain required WooCommerce
            // checkout fields, so let browser validation run before continuing.
            // IOK 2026-09-04
            if (!dialogUi.form.checkValidity()) {
                dialogUi.form.reportValidity();
                return;
            }

            dialogBusy = true;
            dialogUi.confirm.disabled = true;
            dialogUi.confirm.setAttribute("aria-disabled", "true");

            addPostData({
                ...serializeForm(dialogUi.form),
                confirmed: 1
            });

            dialogUi.dialog.close();
            await start();
        });

        return { begin, addPostData, start };
    }

    async function createPaymentSession(transaction) {
        const response = await fetch(
            config.vippsresturl || "/wp-json/woo-vipps/v1/express_checkout_single",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "X-WooVipps": "yes",
                    "Accept-Language": `${config.vippslocale || "nb_NO"}, *`
                },
                body: JSON.stringify(transaction)
            }
        );

        if (!response.ok) {
            throw new Error(`Express checkout request failed (${response.status})`);
        }

        return response.json();
    }

    // Using the Vipps SDK, get a payment URL from the new REST endpoint and let
    // the SDK start the express checkout session. IOK 2026-09-04
    async function handlePurchaseClick(event) {
        const wrapper = event.target.closest?.(".button.single-product.vipps-buy-now.initialized");

        if (!wrapper || !wrapper.querySelector("vipps-mobilepay-button")) {
            return;
        }

        event.preventDefault();

        if (body.classList.contains("processing")) {
            return;
        }

        if (wrapper.classList.contains("disabled") || wrapper.hasAttribute("disabled")) {
            showVariationMessage(wrapper);
            return;
        }

        removeErrorMessages();

        if (window.wp?.hooks) {
            window.wp.hooks.doAction("vippsBuySingleProduct", wrapper, event);
        }

        let compatMode = wrapper.classList.contains("compat-mode");
        if (window.wp?.hooks) {
            compatMode = window.wp.hooks.applyFilters(
                "vippsBuySingleProductCompatMode",
                compatMode,
                wrapper,
                event
            );
        }

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
        if (window.wp?.hooks) {
            transaction = transactionFromLegacyData(window.wp.hooks.applyFilters(
                "vippsBuySingleProductData",
                legacyData,
                wrapper,
                event
            ));
        } else {
            transaction = transactionFromLegacyData(legacyData);
        }

        // If filters and fallbacks could not identify the product, do not start
        // an empty transaction against the REST endpoint. IOK 2026-09-04
        if (!hasProductIdentifier(transaction)) {
            showError("Cannot buy product: product id, variation id and sku are missing", wrapper);
            return;
        }

        if (!checkout.begin(transaction, wrapper, event)) {
            return;
        }

        await checkout.start();
    }

    function hasProductIdentifier(transaction) {
        return Boolean(
            transaction?.product_id ||
            transaction?.variation_id ||
            transaction?.sku
        );
    }

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
            showError("Cannot buy product: product form not found", wrapper);
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

        // Future: move post fields named attribute_* into a separate variations field.
        // The REST endpoint expects the product identifiers at top level, while
        // the rest of the serialized WooCommerce form data travels as post data.
        // IOK 2026-09-04
        return {
            product_id: source.product_id || "",
            variation_id: source.variation_id || "",
            sku: source.sku || source.product_sku || "",
            quantity: source.quantity || "1",
            cookies: getCookies(),
            post: addOrderAttributionData(post)
        };
    }

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

    function decodeCookieValue(value) {
        try {
            return decodeURIComponent(value);
        } catch {
            return value;
        }
    }

    function addOrderAttributionData(post) {
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

    function runCompatibilityAction(wrapper, event) {
        const form = wrapper.closest("form");
        const addToCartButton = form?.querySelector(".single_add_to_cart_button");

        if (!form || !addToCartButton) {
            showError("Cannot add product: product form not found", wrapper);
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

        if (window.wp?.hooks) {
            action = window.wp.hooks.applyFilters(
                "vippsBuySingleProductCompatModeAction",
                action,
                wrapper,
                event
            );
        }

        setPurchaseButtonsBusy(true);
        action();
    }

    function serializeForm(form) {
        const data = {};

        for (const [name, value] of new FormData(form).entries()) {
            if (name !== "add-to-cart") {
                data[name] = value;
            }
        }

        return data;
    }

    function setPurchaseButtonsBusy(busy) {
        document.querySelectorAll(".vipps-buy-now").forEach((wrapper) => {
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

        body.classList.toggle("processing", busy);
        if (busy) {
            ensureSpinner();
        }
    }

    // The spinner used to be printed by PHP. Create the same markup on demand
    // if it is not already present; CSS controls visibility using body.processing.
    // IOK 2026-09-04
    function ensureSpinner() {
        let overlay = document.querySelector(".vippsoverlay");
        if (overlay) {
            return overlay;
        }

        const spinner = document.createElement("div");
        spinner.id = "floatingCirclesG";
        spinner.className = `vippsspinner ${sanitizeCssSlug(config.paymentMethodSlug || "")}`.trim();

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

    function sanitizeCssSlug(value) {
        return String(value)
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9_-]+/g, "-")
            .replace(/^-+|-+$/g, "");
    }

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
            vippsInit();
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

    function setPurchaseButtonDisabled(wrapper, disabled) {
        wrapper.classList.toggle("disabled", disabled);

        if (disabled) {
            wrapper.setAttribute("disabled", "");
        } else {
            wrapper.removeAttribute("disabled");
        }
    }

    function subscribeToCartChanges() {
        if (!window.wp?.data) return;

        let previousCheckoutUrl = "";
        window.wp.data.subscribe(() => {
            const button = document.querySelector(
                "a.wp-block-woocommerce-mini-cart-checkout-button-block"
            );
            if (!button) return;

            const cart = window.wp.data.select("wc/store/cart")?.getCartData?.();
            const checkoutUrl = cart?.extensions?.["woo-vipps"]?.checkout_url;
            if (!checkoutUrl || checkoutUrl === previousCheckoutUrl) return;

            previousCheckoutUrl = checkoutUrl;
            button.href = checkoutUrl;
        });
    }

    function showVariationMessage(wrapper) {
        const params = window.wc_add_to_cart_variation_params;
        const message = wrapper.classList.contains("variation-found")
            ? params?.i18n_unavailable_text || "This product variation is not available."
            : params?.i18n_make_a_selection_text || "Please choose a product variation.";
        showError(message, wrapper);
    }

    function removeErrorMessages() {
        document.querySelectorAll(".woocommerce-error.vipps-error").forEach((element) => {
            element.remove();
        });

        emitDocumentEvent("woo-vipps-remove-errors");
        if (window.wp?.hooks) {
            window.wp.hooks.doAction("vippsRemoveErrorMessages");
        }
    }

    function showError(message, wrapper) {
        removeErrorMessages();

        let markup = `<p><ul class="woocommerce-error vipps-error vipps-default-error-message vipps-buy-now-error"><li>${escapeHtml(message)}</li></ul></p>`;
        if (window.wp?.hooks) {
            markup = window.wp.hooks.applyFilters("vippsErrorMessage", markup, wrapper);
        }

        emitDocumentEvent("woo-vipps-error-message", [markup, wrapper]);
        if (window.wp?.hooks) {
            window.wp.hooks.doAction("vippsAddErrorMessage", markup, wrapper);
        }

        wrapper?.insertAdjacentHTML("afterend", markup);
    }

    function emitDocumentEvent(name, args = []) {
        document.dispatchEvent(new CustomEvent(name, { detail: args }));

        if (window.jQuery) {
            window.jQuery(document).trigger(name, args);
        }
    }

    function escapeHtml(value) {
        const element = document.createElement("div");
        element.textContent = String(value || "");
        return element.innerHTML;
    }
})();
