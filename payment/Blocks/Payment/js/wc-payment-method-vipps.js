
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

/**
 * Report recoverable integration problems without logging routine checkout traffic.
 */
function warnVippsCheckout(message, data) {
        if (data === undefined) {
                console.warn('[Vipps Checkout Block]', message);
                return;
        }

        console.warn('[Vipps Checkout Block]', message, data);
}

/**
 * Report failures that prevent the SDK or Store API handoff from completing.
 */
function errorVippsCheckout(message, data) {
        if (data === undefined) {
                console.error('[Vipps Checkout Block]', message);
                return;
        }

        console.error('[Vipps Checkout Block]', message, data);
}

/**
 * Use the gateway localization dictionary, with a readable fallback for missing keys.
 */
function translate(key, fallback) {
        return VippsLocale?.[key] || fallback;
}

/**
 * Use the configured brand/title in customer-facing errors.
 */
function getPaymentMethodName() {
        return decodeEntities(settings.title || defaultLabel || '');
}

/**
 * Prefix fallback errors with the configured payment method name.
 */
function getPaymentMethodMessage(message) {
        return `${getPaymentMethodName()} ${message}`.trim();
}

/**
 * Remember an order handoff across navigation. Refresh a restored checkout at most
 * once per path so Back/app-return does not expose a stale cart or order form.
 */
function createVippsCheckoutHandoff() {
        const maxAgeMs = 30 * 60 * 1000;

        /**
         * Read a valid handoff marker; discard expired or malformed session data.
         */
        function get() {
                let raw;

                try {
                        raw = window.sessionStorage.getItem(CHECKOUT_HANDOFF_STORAGE_KEY);
                } catch (error) {
                        warnVippsCheckout('Could not read checkout handoff marker from sessionStorage.', error);
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
                        warnVippsCheckout('Could not parse checkout handoff marker; clearing.', {
                                raw,
                                error
                        });
                        clear();
                        return null;
                }
        }

        /**
         * Record the handoff before giving the SDK a URL, since it may navigate immediately.
         */
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
                        warnVippsCheckout('Could not write checkout handoff marker to sessionStorage.', error);
                        // Checkout must still work when storage is unavailable.
                }
        }

        /**
         * Release local state after completion, cancellation, or failure.
         */
        function clear() {
                try {
                        window.sessionStorage.removeItem(CHECKOUT_HANDOFF_STORAGE_KEY);
                } catch (error) {
                        warnVippsCheckout('Could not clear checkout handoff marker from sessionStorage.', error);
                        // Checkout must still work when storage is unavailable.
                }
        }

        /**
         * Persist the per-path refresh guard before reloading to avoid a reload loop.
         */
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
                        warnVippsCheckout('Could not update checkout handoff reload marker; clearing.', error);
                        clear();
                }

                window.location.reload();
        }

        /**
         * Recognize both back-forward cache restoration and history navigation.
         */
        function wasHistoryRestore(event) {
                const navigation = window.performance?.getEntriesByType?.('navigation')?.[0];
                return event.persisted || navigation?.type === 'back_forward';
        }

        /**
         * Refresh history-restored checkout state; clear the marker on a fresh page load.
         */
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

/**
 * Accept object details and Store API key/value arrays from different response paths.
 */
function normalizeVippsCheckoutPaymentDetails(details) {
        if (!Array.isArray(details)) {
                return details || {};
        }

        return details.reduce((normalized, item) => {
                if (Array.isArray(item) && item.length >= 2) {
                        normalized[item[0]] = item[1];
                        return normalized;
                }

                if (item && typeof item === 'object') {
                        const key = item.key || item.name;

                        if (key) {
                                normalized[key] = item.value;
                        }
                }

                return normalized;
        }, {});
}

/**
 * Current Blocks events expose processingResponse.paymentDetails. Retain older
 * paymentResult shapes and raw Store API shapes for compatibility.
 */
function getVippsCheckoutPaymentDetails(data) {
        const rawDetails = data?.processingResponse?.paymentDetails ||
                data?.processingResponse?.payment_details ||
                data?.paymentResult?.paymentDetails ||
                data?.paymentResult?.payment_details ||
                data?.payment_result?.payment_details ||
                data?.payment_result?.paymentDetails ||
                {};
        const details = normalizeVippsCheckoutPaymentDetails(rawDetails);

        return details;
}

/**
 * Prefer widget-specific payment details; use the normal gateway redirect as a
 * fallback when checkout was processed through the legacy Store API adapter.
 */
function getVippsCheckoutPaymentUrl(data) {
        const details = getVippsCheckoutPaymentDetails(data);
        const paymentUrl = details.vippsPaymentUrl ||
                details.vipps_payment_url ||
                details.paymentUrl ||
                details.payment_url ||
                data?.redirectUrl ||
                data?.redirect_url ||
                '';

        return paymentUrl;
}

/**
 * Read optional reference metadata for the handoff marker; it is not needed to open the SDK.
 */
function getVippsCheckoutPaymentReference(data) {
        const details = getVippsCheckoutPaymentDetails(data);
        const paymentReference = details.vippsPaymentReference ||
                details.vipps_payment_reference ||
                details.paymentReference ||
                details.payment_reference ||
                '';

        return paymentReference;
}

/**
 * Bridge the custom button and the checkout event subscriber with one pending URL
 * promise. WooCommerce creates the order; the SDK waits for its payment URL.
 * An attempt covers order submission and URL delivery, not the whole payment session.
 */
function createVippsCheckoutController(setBusy) {
        let currentAttempt = null;
        let nextAttemptId = 0;
        let trigger = null;

        /**
         * Reuse one trigger and start the shared host once. The resolver waits for Store API
         * completion; SDK success/cancel callbacks own subsequent merchant-return navigation.
         */
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

                                const paymentUrl = await currentAttempt.paymentUrlPromise;

                                return paymentUrl;
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

                                // A null resolver URL is the SDK's deliberate no-session signal.
                                if (
                                        window.vipps?.InvalidTriggerUrlError &&
                                        error instanceof window.vipps.InvalidTriggerUrlError &&
                                        error.url === null
                                ) {
                                        return;
                                }

                                errorVippsCheckout('Widget SDK error event.', error);
                        });

                return trigger;
        }

        /**
         * Create one pending URL promise and disable the button to prevent duplicate submissions.
         */
        function begin() {
                if (currentAttempt) {
                        warnVippsCheckout('Checkout attempt already active; refusing to begin another.', currentAttempt);
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

        /**
         * Start the SDK resolver before submitting checkout; do not await URL delivery here.
         */
        function open() {
                const vippsTrigger = getTrigger();

                if (!vippsTrigger) {
                        warnVippsCheckout('Widget SDK trigger is unavailable; cannot open trigger.');
                        return Promise.resolve(false);
                }

                return vippsTrigger.open();
        }

        /**
         * Deliver the successful Store API payment URL to the SDK, or navigate directly if
         * the SDK is unavailable. Return false for missing URLs so Blocks can display an error.
         */
        function resolve(data) {
                if (!currentAttempt) {
                        warnVippsCheckout('Received checkout success without active attempt.');
                        return false;
                }

                const paymentUrl = getVippsCheckoutPaymentUrl(data);

                if (!paymentUrl) {
                        errorVippsCheckout('Missing checkout payment URL in Store API success payload.');
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
                        warnVippsCheckout('Widget SDK trigger disappeared; falling back to full-page redirect.');
                        clear();
                        window.location.assign(paymentUrl);
                        return true;
                }

                currentAttempt.resolvePaymentUrl(paymentUrl);
                currentAttempt = null;
                setBusy(false);
                return true;
        }

        /**
         * Reject the waiting SDK resolver when WooCommerce fails, then release the attempt.
         */
        function reject(error) {
                warnVippsCheckout('Rejecting checkout attempt.', {
                        attemptId: currentAttempt?.id,
                        error
                });

                if (currentAttempt) {
                        currentAttempt.rejectPaymentUrl(error || new Error(getPaymentMethodMessage('checkout failed.')));
                }

                clear();
        }

        /**
         * Release local state after completion, cancellation, or failure.
         */
        function clear() {
                currentAttempt = null;
                setBusy(false);
        }

        /**
         * Identify whether this controller owns a pending order submission.
         */
        function hasAttempt() {
                return Boolean(currentAttempt);
        }

        return { begin, open, resolve, reject, clear, hasAttempt };
}

/**
 * Share the controller between Content and the custom button, which Blocks mounts
 * as separate components. Keep the button state setter available to event callbacks.
 */
function getVippsCheckoutController(setBusy) {
        if (setBusy) {
                setVippsCheckoutBusy = setBusy;
        }

        if (!vippsCheckoutController) {
                vippsCheckoutController = createVippsCheckoutController((busy) => {
                        // Use the same overlay as express checkout while the Store API
                        // creates the order. resolve/reject/clear release it for the SDK UI.
                        window.setVippsPaymentBusy(busy, 'checkout-block');
                        setVippsCheckoutBusy(busy);
                });
        }

        return vippsCheckoutController;
}

/**
 * Render the description and subscribe to Blocks checkout events. Payment setup
 * supplies request metadata; checkout success hands off the URL and suppresses
 * WooCommerce navigation only for an active widget attempt. Refs keep subscriptions
 * using current props; effect cleanup removes observers when Blocks unmounts content.
 */
const Content = (props) => {
	const { eventRegistration, emitResponse } = props;
	const propsRef = useRef(props);
	const emitResponseRef = useRef(emitResponse);

	propsRef.current = props;
	emitResponseRef.current = emitResponse;

	useEffect(() => {
			if (!eventRegistration) {
				return;
			}

                // SUCCESS here means payment data is ready, not that payment has been collected.
                const unsubscribePaymentSetup = eventRegistration.onPaymentSetup
                        ? eventRegistration.onPaymentSetup(() => {

                        if (
                                propsRef.current.activePaymentMethod &&
                                propsRef.current.activePaymentMethod !== 'vipps'
                        ) {
                                return true;
			}

			const response = {
				type: emitResponseRef.current.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						payment_method: 'vipps',
                                                vipps_checkout_widget: '1'
                                        }
                                }
                        };

                        return response;
                })
                        : () => {};

                const unsubscribeCheckoutSuccess = eventRegistration.onCheckoutSuccess
                        ? eventRegistration.onCheckoutSuccess((data) => {
                        const controller = getVippsCheckoutController();

                        if (!controller?.hasAttempt()) {
                                return true;
                        }

			if (!controller.resolve(data)) {
				const response = {
					type: emitResponseRef.current.responseTypes.ERROR,
					message: translate('missingPaymentUrl', getPaymentMethodMessage('did not return a payment URL.')),
					retry: true
				};

                                return response;
                        }

			const response = {
				type: emitResponseRef.current.responseTypes.SUCCESS,
                                // WooCommerce completes checkout after this callback. An explicit
                                // empty redirect clears its stored URL; the widget owns navigation.
                                // SET_COMPLETE checks typeof redirectUrl === 'string', so omitting
                                // this field (or returning true) would retain the gateway redirect.
                                // See Payment/README.md for the WooCommerce source references.
                                redirectUrl: ''
			};

                        return response;
                })
                        : () => {};

                // Let WooCommerce display its checkout error; release the waiting SDK resolver.
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
	}, [eventRegistration]);

        var content = createElement(
		'div',
		null,
		decodeEntities(settings.description || '')
	);
       return applyFilters('woo_vipps_checkout_description', content, settings);
};

/**
 * Render the configured method name and logo through WooCommerce styling and plugin filters.
 */
const Label = props => {
        const { PaymentMethodLabel } = props.components;
        let textlabel = createElement( 'span', null, decodeEntities(settings.title || ''));
        let icon = createElement('img', { style: {display: 'inline-block'}, alt: decodeEntities(settings.title || ''), title: decodeEntities(settings.title || ''), className: 'vipps-payment-logo', src:iconsrc});
        let label =  createElement(PaymentMethodLabel, { text: textlabel, icon: icon });
        return applyFilters('woo_vipps_checkout_label', label, settings);
};

/**
 * Render the server-generated express button markup. vipps.js handles its clicks
 * and session creation independently of the standard Checkout Block submission.
 */
const ExpressCheckoutButton = props => {
 var expressbutton = createElement('div', {dangerouslySetInnerHTML: {__html: settings.expressbutton  },  className: 'vipps-express-container'}, null);
 return applyFilters('woo_vipps_checkout_block_express_button', expressbutton, settings);
}

/**
 * Validate the Checkout Block, create a pending attempt, start the SDK, and then
 * call WooCommerce onSubmit to create/process the order through the Store API.
 * Awaiting trigger.open before onSubmit would deadlock the resolver waiting for that order.
 * Editor/preview rendering must not start a payment.
 */
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

        useEffect(() => () => {
                // Blocks can unmount the button when the selected method changes.
                window.setVippsPaymentBusy(false, 'checkout-block');
        }, []);

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
                        warnVippsCheckout('Could not begin checkout attempt after validation.');
                        return;
                }

                controllerRef.current.open().catch((error) => {
                        errorVippsCheckout('Widget SDK trigger open promise rejected.', error);
                        controllerRef.current.reject(error);
                });

                if (onSubmit) {
                        onSubmit();
                } else {
                        warnVippsCheckout('No onSubmit prop available on Vipps checkout button.');
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
                className: `vipps-checkout-widget-button${busy ? ' loading' : ''}`,
                disabled: isDisabled ? true : undefined,
                'aria-disabled': isDisabled ? 'true' : 'false',
                'aria-busy': busy ? 'true' : 'false',
                onClick: handleClick
        };

	return createElement('vipps-mobilepay-button', buttonProps);
};

/**
 * Combine the server cart/settings eligibility with the existing express visibility filter.
 */
const canMakeExpressPayment = (args) => {
 var candoit = settings.show_express_checkout;
 return applyFilters('woo_vipps_checkout_block_show_express_checkout', candoit, settings);
};

/**
 * Preserve the plugin visibility filter; server-side availability is supplied by the PHP integration.
 */
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
