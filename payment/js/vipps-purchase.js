/* Coordinate the QR/cart landing page with the express flow in vipps.js. */
(() => {
    const purchaseSelector = ".vipps-qr-purchase, .vipps-cart-purchase";
    const buttonSelector = ".vipps-buy-now, .vipps-express-checkout";
    let purchaseStarted = false;

    /** Scope the auto-start marker to this page and its purchase parameters. */
    function getAutoStartStorageKey() {
        return `vipps-auto-start:${window.location.pathname}:${window.location.search}`;
    }

    /** Distinguish a new visit from a reload or history return. */
    function getNavigationType() {
        return window.performance
            ?.getEntriesByType?.("navigation")?.[0]?.type || "navigate";
    }

    /** Check whether this tab already auto-started the same purchase page. */
    function getAutoStartAttempted() {
        try {
            return window.sessionStorage.getItem(getAutoStartStorageKey()) === "true";
        } catch (error) {
            return false;
        }
    }

    /** Remember the attempt before dispatching a click, avoiding reload loops. */
    function setAutoStartAttempted() {
        try {
            window.sessionStorage.setItem(getAutoStartStorageKey(), "true");
        } catch (error) {
            // Checkout must still work when storage is unavailable.
        }
    }

    /** Let a new navigation start a fresh automatic purchase. */
    function clearAutoStartAttempted() {
        try {
            window.sessionStorage.removeItem(getAutoStartStorageKey());
        } catch (error) {
            // Checkout must still work when storage is unavailable.
        }
    }

    /** Find the express button inside the QR or cart purchase container. */
    function getAutoPurchaseButton() {
        const purchase = document.querySelector(purchaseSelector);
        return purchase?.querySelector(buttonSelector);
    }

    /** Start checkout once through the same click path as the fallback button. */
    function startVippsPurchase() {
        if (purchaseStarted) {
            return;
        }

        const purchaseButton = getAutoPurchaseButton();

        if (!purchaseButton) {
            return;
        }

        purchaseStarted = true;
        setAutoStartAttempted();
        purchaseButton.dispatchEvent(new MouseEvent("click", {
            bubbles: true,
            cancelable: true
        }));
    }

    /** Auto-start on a new visit, but expose manual retry after a page restore. */
    function setupAutomaticPurchase() {
        const purchaseButton = getAutoPurchaseButton();

        if (!purchaseButton) {
            return;
        }

        const navigationType = getNavigationType();
        const attempted = getAutoStartAttempted();

        if ((navigationType === "reload" || navigationType === "back_forward") && attempted) {
            setPurchaseFinished();
            return;
        }

        if (navigationType !== "reload" && navigationType !== "back_forward") {
            clearAutoStartAttempted();
        }

        if (purchaseButton.classList.contains("initialized")) {
            startVippsPurchase();
            return;
        }

        document.body.addEventListener("vippsInit", startVippsPurchase, {
            once: true
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", setupAutomaticPurchase, {
            once: true
        });
    } else {
        setupAutomaticPurchase();
    }

    /** Scope UI updates to the attempt's container, or to all purchase views. */
    function getPurchases(wrapper) {
        if (wrapper) {
            const purchase = wrapper.closest?.(purchaseSelector);
            return purchase ? [purchase] : [];
        }

        return Array.from(document.querySelectorAll(purchaseSelector));
    }

    /** Find the waiting messages used by the supported purchase templates. */
    function getWaitingElements() {
        return document.querySelectorAll(
            "#waiting, [data-vipps-purchase-waiting], [data-vipps-qr-waiting]"
        );
    }

    /** Reuse or create an accessible error message within a purchase view. */
    function getErrorElement(purchase) {
        let error = purchase.querySelector("[data-vipps-purchase-error]");

        if (!error) {
            error = document.createElement("p");
            error.className = "vipps-purchase-error woocommerce-error";
            error.setAttribute("data-vipps-purchase-error", "");
            error.setAttribute("role", "alert");
            purchase.append(error);
        }

        return error;
    }

    /** Show waiting state and keep the fallback button out of view during payment. */
    function setPurchaseStarted(wrapper) {
        getPurchases(wrapper).forEach((purchase) => {
            const button = purchase.querySelector(buttonSelector);
            const error = purchase.querySelector("[data-vipps-purchase-error]");

            if (button) {
                button.hidden = false;
                button.style.visibility = "hidden";
            }

            if (error) {
                error.hidden = true;
                error.textContent = "";
            }
        });

        getWaitingElements().forEach((waiting) => {
            waiting.hidden = false;
        });
    }

    /** End waiting state and expose the button for a possible manual retry. */
    function setPurchaseFinished(wrapper) {
        getPurchases(wrapper).forEach((purchase) => {
            const button = purchase.querySelector(buttonSelector);

            if (button) {
                button.hidden = false;
                button.style.visibility = "visible";
            }
        });

        getWaitingElements().forEach((waiting) => {
            waiting.hidden = true;
        });
    }

    /** Replace waiting state with an error when checkout cannot continue. */
    function setPurchaseError(message, wrapper) {
        getPurchases(wrapper).forEach((purchase) => {
            const button = purchase.querySelector(buttonSelector);
            const error = getErrorElement(purchase);

            if (button) {
                button.hidden = true;
            }

            error.textContent = message;
            error.hidden = false;
        });

        getWaitingElements().forEach((waiting) => {
            waiting.hidden = true;
        });
    }

    // vipps.js owns the transaction; this page only mirrors its UI events.
    document.addEventListener("vippsPurchaseStarted", (event) => {
        setPurchaseStarted(event.detail?.wrapper);
    });

    document.addEventListener("vippsPurchaseFinished", (event) => {
        setPurchaseFinished(event.detail?.wrapper);
    });

    document.addEventListener("vippsPurchaseError", (event) => {
        setPurchaseError(
            event.detail?.message || "Vipps checkout failed",
            event.detail?.wrapper
        );
    });

    // Recover waiting state when this script loads after an attempt has begun.
    if (document.body?.dataset.vippsPurchaseActive === "true") {
        setPurchaseStarted();
    }
})();
