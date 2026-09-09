(() => {
    const purchaseSelector = ".vipps-qr-purchase, .vipps-cart-purchase";
    const buttonSelector = ".vipps-buy-now, .vipps-express-checkout";
    let purchaseStarted = false;

    function getAutoPurchaseButton() {
        const purchase = document.querySelector(purchaseSelector);
        return purchase?.querySelector(buttonSelector);
    }

    function startVippsPurchase() {
        if (purchaseStarted) {
            return;
        }

        const purchaseButton = getAutoPurchaseButton();

        if (!purchaseButton) {
            return;
        }

        purchaseStarted = true;
        purchaseButton.dispatchEvent(new MouseEvent("click", {
            bubbles: true,
            cancelable: true
        }));
    }

    function setupAutomaticPurchase() {
        const purchaseButton = getAutoPurchaseButton();

        if (!purchaseButton) {
            return;
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

    function getPurchases(wrapper) {
        if (wrapper) {
            const purchase = wrapper.closest?.(purchaseSelector);
            return purchase ? [purchase] : [];
        }

        return Array.from(document.querySelectorAll(purchaseSelector));
    }

    function getWaitingElements() {
        return document.querySelectorAll(
            "#waiting, [data-vipps-purchase-waiting], [data-vipps-qr-waiting]"
        );
    }

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

    if (document.body?.dataset.vippsPurchaseActive === "true") {
        setPurchaseStarted();
    }
})();
