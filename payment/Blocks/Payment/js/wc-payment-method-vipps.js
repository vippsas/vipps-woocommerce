
(function () { 


// Imports
const { __ } = wp.i18n;
const { decodeEntities }  = wp.htmlEntities;
const { getSetting }  = wc.wcSettings;
const { registerPaymentMethod }  = wc.wcBlocksRegistry;
const { registerExpressPaymentMethod }  = wc.wcBlocksRegistry;
const { applyFilters } = wp.hooks;
const { createElement, useEffect, useRef, useState } = wp.element;

//( 'hookName', content, arg1, arg2, ... )


// Data
const settings = getSetting('vipps_data', {});
const defaultLabel = VippsLocale['Vipps'];
const label = decodeEntities(settings.title) || defaultLabel;
const iconsrc = settings.iconsrc;
const CHECKOUT_HANDOFF_STORAGE_KEY = 'vippsCheckoutPaymentHandoff';
let vippsCheckoutController = null;
let setVippsCheckoutBusy = () => {};

function translate(key, fallback) {
        return VippsLocale?.[key] || fallback;
}

function getPaymentMethodName() {
        return decodeEntities(settings.title || defaultLabel || '');
}

function getPaymentMethodMessage(message) {
        return `${getPaymentMethodName()} ${message}`.trim();
}

function createVippsCheckoutHandoff() {
        const maxAgeMs = 30 * 60 * 1000;

        function get() {
                let raw;

                try {
                        raw = window.sessionStorage.getItem(CHECKOUT_HANDOFF_STORAGE_KEY);
                } catch (error) {
                        return null;
                }

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
                } catch (error) {
                        clear();
                        return null;
                }
        }

        function mark(data = {}) {
                try {
                        window.sessionStorage.setItem(CHECKOUT_HANDOFF_STORAGE_KEY, JSON.stringify({
                                createdAt: Date.now(),
                                path: window.location.pathname,
                                orderId: data.orderId || null,
                                paymentReference: data.paymentReference || null,
                                refreshedPaths: []
                        }));
                } catch (error) {
                        // Checkout must still work when storage is unavailable.
                }
        }

        function clear() {
                try {
                        window.sessionStorage.removeItem(CHECKOUT_HANDOFF_STORAGE_KEY);
                } catch (error) {
                        // Checkout must still work when storage is unavailable.
                }
        }

        function reloadOnceForStalePage() {
                const handoff = get();

                if (!handoff) {
                        return;
                }

                const refreshedPaths = Array.isArray(handoff.refreshedPaths)
                        ? handoff.refreshedPaths
                        : [];

                if (refreshedPaths.includes(window.location.pathname)) {
                        return;
                }

                try {
                        window.sessionStorage.setItem(CHECKOUT_HANDOFF_STORAGE_KEY, JSON.stringify({
                                ...handoff,
                                refreshedPaths: [...refreshedPaths, window.location.pathname]
                        }));
                } catch (error) {
                        clear();
                }

                window.location.reload();
        }

        function wasHistoryRestore(event) {
                const navigation = window.performance?.getEntriesByType?.('navigation')?.[0];
                return event.persisted || navigation?.type === 'back_forward';
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

        window.addEventListener('pageshow', handlePageshow);
        window.addEventListener('focus', reloadOnceForStalePage);
        document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                        reloadOnceForStalePage();
                }
        });

        return { mark, clear, reloadOnceForStalePage };
}

const checkoutHandoff = createVippsCheckoutHandoff();

function getVippsCheckoutPaymentDetails(data) {
        return data?.paymentResult?.paymentDetails ||
                data?.payment_result?.payment_details ||
                {};
}

function getVippsCheckoutPaymentUrl(data) {
        const details = getVippsCheckoutPaymentDetails(data);
        return details.vippsPaymentUrl ||
                details.vipps_payment_url ||
                '';
}

function getVippsCheckoutPaymentReference(data) {
        const details = getVippsCheckoutPaymentDetails(data);
        return details.vippsPaymentReference ||
                details.vipps_payment_reference ||
                '';
}

function createVippsCheckoutController(setBusy) {
        let currentAttempt = null;
        let nextAttemptId = 0;
        let trigger = null;

        function getTrigger() {
                if (trigger || !window.vipps?.trigger) {
                        return trigger;
                }

                if (typeof window.ensureVippsWidgetHostStarted === 'function') {
                        window.ensureVippsWidgetHostStarted();
                } else if (!window.__vippsWidgetHostStarted && window.vipps?.host) {
                        window.vipps.host().start();
                        window.__vippsWidgetHostStarted = true;
                }

                trigger = window.vipps
                        .trigger(async () => {
                                if (!currentAttempt) {
                                        throw new Error(getPaymentMethodMessage('checkout attempt is no longer active.'));
                                }

                                return currentAttempt.paymentUrlPromise;
                        })
                        .on('success', (close, redirectUrl) => {
                                clear();
                                close();

                                if (redirectUrl) {
                                        window.location.assign(redirectUrl);
                                } else {
                                        checkoutHandoff.clear();
                                }
                        })
                        .on('cancel', (close, redirectUrl) => {
                                clear();
                                close();

                                if (redirectUrl) {
                                        window.location.assign(redirectUrl);
                                } else {
                                        checkoutHandoff.clear();
                                }
                        })
                        .on('close', () => {
                                clear();
                                checkoutHandoff.clear();
                        })
                        .on('error', (error) => {
                                clear();
                                checkoutHandoff.clear();

                                if (
                                        window.vipps?.InvalidTriggerUrlError &&
                                        error instanceof window.vipps.InvalidTriggerUrlError &&
                                        error.url === null
                                ) {
                                        return;
                                }

                                console.error(error);
                        });

                return trigger;
        }

        function begin() {
                if (currentAttempt) {
                        return null;
                }

                let resolvePaymentUrl;
                let rejectPaymentUrl;
                const paymentUrlPromise = new Promise((resolve, reject) => {
                        resolvePaymentUrl = resolve;
                        rejectPaymentUrl = reject;
                });

                currentAttempt = {
                        id: ++nextAttemptId,
                        paymentUrlPromise,
                        resolvePaymentUrl,
                        rejectPaymentUrl
                };

                setBusy(true);
                return currentAttempt;
        }

        function open() {
                const vippsTrigger = getTrigger();

                if (!vippsTrigger) {
                        return Promise.resolve(false);
                }

                return vippsTrigger.open();
        }

        function resolve(data) {
                if (!currentAttempt) {
                        return false;
                }

                const paymentUrl = getVippsCheckoutPaymentUrl(data);

                if (!paymentUrl) {
                        currentAttempt.rejectPaymentUrl(new Error(
                                translate('missingPaymentUrl', getPaymentMethodMessage('did not return a payment URL.'))
                        ));
                        clear();
                        return false;
                }

                checkoutHandoff.mark({
                        orderId: data?.orderId,
                        paymentReference: getVippsCheckoutPaymentReference(data)
                });

                if (!window.vipps?.trigger) {
                        clear();
                        window.location.assign(paymentUrl);
                        return true;
                }

                currentAttempt.resolvePaymentUrl(paymentUrl);
                currentAttempt = null;
                setBusy(false);
                return true;
        }

        function reject(error) {
                if (currentAttempt) {
                        currentAttempt.rejectPaymentUrl(error || new Error(getPaymentMethodMessage('checkout failed.')));
                }

                clear();
        }

        function clear() {
                currentAttempt = null;
                setBusy(false);
        }

        function hasAttempt() {
                return Boolean(currentAttempt);
        }

        return { begin, open, resolve, reject, clear, hasAttempt };
}

function getVippsCheckoutController(setBusy) {
        if (setBusy) {
                setVippsCheckoutBusy = setBusy;
        }

        if (!vippsCheckoutController) {
                vippsCheckoutController = createVippsCheckoutController((busy) => {
                        setVippsCheckoutBusy(busy);
                });
        }

        return vippsCheckoutController;
}


const Content = (props) => {
        const { eventRegistration, emitResponse } = props;
        const propsRef = useRef(props);

        propsRef.current = props;

        useEffect(() => {
                if (!eventRegistration) {
                        return;
                }

                const unsubscribePaymentSetup = eventRegistration.onPaymentSetup
                        ? eventRegistration.onPaymentSetup(() => {
                        if (
                                propsRef.current.activePaymentMethod &&
                                propsRef.current.activePaymentMethod !== 'vipps'
                        ) {
                                return true;
                        }

                        return {
                                type: emitResponse.responseTypes.SUCCESS,
                                meta: {
                                        paymentMethodData: {
                                                payment_method: 'vipps',
                                                vipps_checkout_widget: '1'
                                        }
                                }
                        };
                })
                        : () => {};

                const unsubscribeCheckoutSuccess = eventRegistration.onCheckoutSuccess
                        ? eventRegistration.onCheckoutSuccess((data) => {
                        const controller = getVippsCheckoutController();

                        if (!controller?.hasAttempt()) {
                                return true;
                        }

                        if (!controller.resolve(data)) {
                                return {
                                        type: emitResponse.responseTypes.ERROR,
                                        message: translate('missingPaymentUrl', getPaymentMethodMessage('did not return a payment URL.')),
                                        retry: true
                                };
                        }

                        return {
                                type: emitResponse.responseTypes.SUCCESS
                        };
                })
                        : () => {};

                const unsubscribeCheckoutFail = eventRegistration.onCheckoutFail
                        ? eventRegistration.onCheckoutFail(() => {
                        getVippsCheckoutController().reject(new Error('Checkout failed'));
                        return true;
                })
                        : () => {};

                return () => {
                        unsubscribePaymentSetup();
                        unsubscribeCheckoutSuccess();
                        unsubscribeCheckoutFail();
                };
        }, [eventRegistration, emitResponse]);

        var content = createElement(
		'div',
		null,
		decodeEntities(settings.description || '')
	);
       return applyFilters('woo_vipps_checkout_description', content, settings);
};

const Label = props => {
        const { PaymentMethodLabel } = props.components;
        let textlabel = createElement( 'span', null, decodeEntities(settings.title || ''));
        let icon = createElement('img', { style: {display: 'inline-block'}, alt: decodeEntities(settings.title || ''), title: decodeEntities(settings.title || ''), className: 'vipps-payment-logo', src:iconsrc});
        let label =  createElement(PaymentMethodLabel, { text: textlabel, icon: icon });
        return applyFilters('woo_vipps_checkout_label', label, settings);
};

const ExpressCheckoutButton = props => {
 var expressbutton = createElement('div', {dangerouslySetInnerHTML: {__html: settings.expressbutton  },  className: 'vipps-express-container'}, null);
 return applyFilters('woo_vipps_checkout_block_express_button', expressbutton, settings);
}

const VippsCheckoutPlaceOrderButton = (props) => {
        const {
                validate,
                onSubmit,
                disabled,
                waitingForProcessing,
                waitingForRedirect,
                isEditor,
                isPreview
        } = props;
        const [busy, setBusy] = useState(false);
        const controllerRef = useRef(null);

        if (!controllerRef.current) {
                controllerRef.current = getVippsCheckoutController(setBusy);
        }

        if (isEditor || isPreview) {
                return createElement('button', {
                        type: 'button',
                        disabled: true
                }, decodeEntities(settings.title || defaultLabel));
        }

        const isDisabled = disabled || waitingForProcessing || waitingForRedirect || busy;

        const handleClick = async (event) => {
                event.preventDefault();

                if (isDisabled || controllerRef.current.hasAttempt()) {
                        return;
                }

                const validationResult = validate
                        ? await validate()
                        : { hasError: false };

                if (validationResult?.hasError) {
                        return;
                }

                const attempt = controllerRef.current.begin();

                if (!attempt) {
                        return;
                }

                controllerRef.current.open().catch((error) => {
                        controllerRef.current.reject(error);
                });

                if (onSubmit) {
                        onSubmit();
                }
        };

        const buttonProps = {
                type: 'button',
                brand: settings.brand || 'vipps',
                language: settings.language || 'no',
                verb: 'continue',
                variant: 'primary',
                branded: 'true',
                rounded: 'true',
                stretched: 'true',
                className: 'vipps-checkout-widget-button',
                disabled: isDisabled ? true : undefined,
                'aria-disabled': isDisabled ? 'true' : 'false',
                onClick: handleClick
        };

        return createElement('vipps-mobilepay-button', buttonProps);
};

const canMakeExpressPayment = (args) => {
 var candoit = settings.show_express_checkout;
 return applyFilters('woo_vipps_checkout_block_show_express_checkout', candoit, settings);
};

const canMakePayment = (args) => {
 var candoit = true;
 return applyFilters('woo_vipps_checkout_block_show_vipps', candoit, settings);
};

/**
 * Vipps  payment method config object.
 */
const VippsPaymentMethod = {
      name: 'vipps',
      label: createElement(Label, null),
      content: createElement(Content, null),
      edit: createElement(Content, null),
      placeOrderButton: VippsCheckoutPlaceOrderButton,
      icons: null,
      canMakePayment: canMakePayment,
      ariaLabel: label
};
const VippsExpressPaymentMethod = {
      name: 'vippsexpress',
      content: createElement(ExpressCheckoutButton, null),
      edit: createElement(ExpressCheckoutButton, null),
      paymentMethodId: 'vipps',
      canMakePayment: canMakeExpressPayment,
};


registerPaymentMethod(VippsPaymentMethod);
registerExpressPaymentMethod(VippsExpressPaymentMethod);


}());
