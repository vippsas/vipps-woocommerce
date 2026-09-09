(() => {
    let purchaseStarted = false;

    function startVippsQrPurchase() {
        if (purchaseStarted) {
            return;
        }

        const form = document.querySelector(".vipps-qr-purchase");
        const purchaseButton = form?.querySelector(".vipps-buy-now");

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
        const form = document.querySelector(".vipps-qr-purchase");
        const purchaseButton = form?.querySelector(".vipps-buy-now");

        if (!purchaseButton) {
            return;
        }

        if (purchaseButton.classList.contains("initialized")) {
            startVippsQrPurchase();
            return;
        }

        document.body.addEventListener("vippsInit", startVippsQrPurchase, {
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
            const purchase = wrapper.closest?.(".vipps-qr-purchase");
            return purchase ? [purchase] : [];
        }

        return Array.from(document.querySelectorAll(".vipps-qr-purchase"));
    }

    function getWaitingElements() {
        return document.querySelectorAll(
            "#waiting, [data-vipps-qr-waiting]"
        );
    }

    function getErrorElement(purchase) {
        let error = purchase.querySelector("[data-vipps-qr-error]");

        if (!error) {
            error = document.createElement("p");
            error.className = "vipps-qr-error woocommerce-error";
            error.setAttribute("data-vipps-qr-error", "");
            error.setAttribute("role", "alert");
            purchase.append(error);
        }

        return error;
    }

    function setPurchaseStarted(wrapper) {
        getPurchases(wrapper).forEach((purchase) => {
            const button = purchase.querySelector(".vipps-buy-now");
            const error = purchase.querySelector("[data-vipps-qr-error]");

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
            const button = purchase.querySelector(".vipps-buy-now");

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
            const button = purchase.querySelector(".vipps-buy-now");
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

console.log("qr loaded");
