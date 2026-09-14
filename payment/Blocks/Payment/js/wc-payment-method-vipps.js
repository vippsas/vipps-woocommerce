
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

function logVippsCheckout(message, data) {
        if (data === undefined) {
                console.log('[Vipps Checkout Block]', message);
                return;
        }

        console.log('[Vipps Checkout Block]', message, data);
}

function warnVippsCheckout(message, data) {
        if (data === undefined) {
                console.warn('[Vipps Checkout Block]', message);
                return;
        }

        console.warn('[Vipps Checkout Block]', message, data);
}

function errorVippsCheckout(message, data) {
        if (data === undefined) {
                console.error('[Vipps Checkout Block]', message);
                return;
        }

        console.error('[Vipps Checkout Block]', message, data);
}

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
                        warnVippsCheckout('Could not read checkout handoff marker from sessionStorage.', error);
                        return null;
                }

                if (!raw) {
                        return null;
                }

                try {
                        const handoff = JSON.parse(raw);

                        if (!handoff.createdAt || Date.now() - handoff.createdAt >= maxAgeMs) {
                                logVippsCheckout('Checkout handoff marker expired; clearing.', handoff);
                                clear();
                                return null;
                        }

                        logVippsCheckout('Read checkout handoff marker.', handoff);
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

        function mark(data = {}) {
                try {
                        window.sessionStorage.setItem(CHECKOUT_HANDOFF_STORAGE_KEY, JSON.stringify({
                                createdAt: Date.now(),
                                path: window.location.pathname,
                                orderId: data.orderId || null,
                                paymentReference: data.paymentReference || null,
                                refreshedPaths: []
                        }));
                        logVippsCheckout('Marked checkout handoff.', data);
                } catch (error) {
                        warnVippsCheckout('Could not write checkout handoff marker to sessionStorage.', error);
                        // Checkout must still work when storage is unavailable.
                }
        }

        function clear() {
                try {
                        window.sessionStorage.removeItem(CHECKOUT_HANDOFF_STORAGE_KEY);
                        logVippsCheckout('Cleared checkout handoff marker.');
                } catch (error) {
                        warnVippsCheckout('Could not clear checkout handoff marker from sessionStorage.', error);
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
                        logVippsCheckout('Checkout handoff stale-page reload already used for this path.', {
                                path: window.location.pathname,
                                handoff
                        });
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

                logVippsCheckout('Reloading stale checkout page after handoff.', {
                        path: window.location.pathname,
                        handoff
                });
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

                logVippsCheckout('pageshow while checkout handoff marker exists.', {
                        persisted: event.persisted,
                        navigationType: window.performance?.getEntriesByType?.('navigation')?.[0]?.type,
                        handoff
                });

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

function getVippsCheckoutPaymentDetails(data) {
        const rawDetails = data?.paymentResult?.paymentDetails ||
                data?.paymentResult?.payment_details ||
                data?.payment_result?.payment_details ||
                data?.payment_result?.paymentDetails ||
                {};
        const details = normalizeVippsCheckoutPaymentDetails(rawDetails);

        logVippsCheckout('Extracted checkout payment details.', {
                rawDetails,
                details,
                data
        });

        return details;
}

function getVippsCheckoutPaymentUrl(data) {
        const details = getVippsCheckoutPaymentDetails(data);
        const paymentUrl = details.vippsPaymentUrl ||
                details.vipps_payment_url ||
                details.paymentUrl ||
                details.payment_url ||
                data?.redirectUrl ||
                data?.redirect_url ||
                '';

        logVippsCheckout('Extracted checkout payment URL.', {
                paymentUrl,
                details,
                data
        });

        return paymentUrl;
}

function getVippsCheckoutPaymentReference(data) {
        const details = getVippsCheckoutPaymentDetails(data);
        const paymentReference = details.vippsPaymentReference ||
                details.vipps_payment_reference ||
                details.paymentReference ||
                details.payment_reference ||
                '';

        logVippsCheckout('Extracted checkout payment reference.', {
                paymentReference,
                details,
                data
        });

        return paymentReference;
}

function getVippsCheckoutPaymentUrlFromDetails(details) {
        return details.vippsPaymentUrl ||
                details.vipps_payment_url ||
                details.paymentUrl ||
                details.payment_url ||
                '';
}

function getVippsCheckoutPaymentReferenceFromDetails(details) {
        return details.vippsPaymentReference ||
                details.vipps_payment_reference ||
                details.paymentReference ||
                details.payment_reference ||
                '';
}

function createVippsCheckoutController(setBusy) {
        let currentAttempt = null;
        let nextAttemptId = 0;
        let trigger = null;

        function getTrigger() {
                if (trigger || !window.vipps?.trigger) {
                        logVippsCheckout('Returning existing trigger or no SDK trigger available.', {
                                hasTrigger: Boolean(trigger),
                                hasVipps: Boolean(window.vipps),
                                hasVippsTrigger: Boolean(window.vipps?.trigger)
                        });
                        return trigger;
                }

                if (typeof window.ensureVippsWidgetHostStarted === 'function') {
                        logVippsCheckout('Ensuring Widget SDK host through shared helper.');
                        window.ensureVippsWidgetHostStarted();
                } else if (!window.__vippsWidgetHostStarted && window.vipps?.host) {
                        logVippsCheckout('Starting Widget SDK host from checkout payment method script.');
                        window.vipps.host().start();
                        window.__vippsWidgetHostStarted = true;
                }

                logVippsCheckout('Creating Widget SDK trigger for Checkout Block.');
                trigger = window.vipps
                        .trigger(async () => {
                                logVippsCheckout('Widget SDK trigger resolver invoked.', {
                                        hasAttempt: Boolean(currentAttempt),
                                        attemptId: currentAttempt?.id
                                });

                                if (!currentAttempt) {
                                        throw new Error(getPaymentMethodMessage('checkout attempt is no longer active.'));
                                }

                                const paymentUrl = await currentAttempt.paymentUrlPromise;

                                logVippsCheckout('Widget SDK trigger resolver returning payment URL.', {
                                        attemptId: currentAttempt?.id,
                                        paymentUrl
                                });

                                return paymentUrl;
                        })
                        .on('success', (close, redirectUrl) => {
                                logVippsCheckout('Widget SDK success event.', { redirectUrl });
                                clear();
                                close();

                                if (redirectUrl) {
                                        window.location.assign(redirectUrl);
                                } else {
                                        checkoutHandoff.clear();
                                }
                        })
                        .on('cancel', (close, redirectUrl) => {
                                logVippsCheckout('Widget SDK cancel event.', { redirectUrl });
                                clear();
                                close();

                                if (redirectUrl) {
                                        window.location.assign(redirectUrl);
                                } else {
                                        checkoutHandoff.clear();
                                }
                        })
                        .on('close', () => {
                                logVippsCheckout('Widget SDK close event.');
                                clear();
                                checkoutHandoff.clear();
                        })
                        .on('error', (error) => {
                                errorVippsCheckout('Widget SDK error event.', error);
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

                logVippsCheckout('Began checkout attempt.', {
                        attemptId: currentAttempt.id
                });
                setBusy(true);
                return currentAttempt;
        }

        function open() {
                const vippsTrigger = getTrigger();

                if (!vippsTrigger) {
                        warnVippsCheckout('Widget SDK trigger is unavailable; cannot open trigger.');
                        return Promise.resolve(false);
                }

                logVippsCheckout('Opening Widget SDK trigger.', {
                        attemptId: currentAttempt?.id
                });
                return vippsTrigger.open();
        }

        function resolve(data) {
                if (!currentAttempt) {
                        warnVippsCheckout('Received checkout success without active attempt.', data);
                        return false;
                }

                logVippsCheckout('Resolving checkout attempt from Store API success.', {
                        attemptId: currentAttempt.id,
                        data
                });

                const details = getVippsCheckoutPaymentDetails(data);
                const paymentUrl = getVippsCheckoutPaymentUrl(data);
                const detailsPaymentUrl = getVippsCheckoutPaymentUrlFromDetails(details);

                if (!paymentUrl) {
                        errorVippsCheckout('Missing checkout payment URL in Store API success payload.', {
                                data,
                                details,
                                detailsPaymentUrl,
                                redirectUrl: data?.redirectUrl,
                                redirect_url: data?.redirect_url
                        });
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
                        warnVippsCheckout('Widget SDK trigger disappeared; falling back to full-page redirect.', {
                                paymentUrl
                        });
                        clear();
                        window.location.assign(paymentUrl);
                        return true;
                }

                logVippsCheckout('Resolving Widget SDK trigger payment URL promise.', {
                        attemptId: currentAttempt.id,
                        paymentUrl,
                        detailsPaymentUrl,
                        paymentReference: getVippsCheckoutPaymentReferenceFromDetails(details)
                });
                currentAttempt.resolvePaymentUrl(paymentUrl);
                currentAttempt = null;
                setBusy(false);
                return true;
        }

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

        function clear() {
                logVippsCheckout('Clearing checkout attempt.', {
                        attemptId: currentAttempt?.id
                });
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
                logVippsCheckout('Registered checkout button busy setter.');
                setVippsCheckoutBusy = setBusy;
        }

        if (!vippsCheckoutController) {
                logVippsCheckout('Creating shared checkout controller.');
                vippsCheckoutController = createVippsCheckoutController((busy) => {
                        logVippsCheckout('Setting checkout button busy state.', { busy });
                        setVippsCheckoutBusy(busy);
                });
        }

        return vippsCheckoutController;
}


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

                const unsubscribePaymentSetup = eventRegistration.onPaymentSetup
                        ? eventRegistration.onPaymentSetup(() => {
                        logVippsCheckout('onPaymentSetup invoked.', {
                                activePaymentMethod: propsRef.current.activePaymentMethod,
                                responseTypes: emitResponse?.responseTypes
                        });

                        if (
                                propsRef.current.activePaymentMethod &&
                                propsRef.current.activePaymentMethod !== 'vipps'
                        ) {
                                logVippsCheckout('onPaymentSetup ignored because Vipps is not active.', {
                                        activePaymentMethod: propsRef.current.activePaymentMethod
                                });
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

                        logVippsCheckout('onPaymentSetup returning response.', response);
                        return response;
                })
                        : () => {};

                const unsubscribeCheckoutSuccess = eventRegistration.onCheckoutSuccess
                        ? eventRegistration.onCheckoutSuccess((data) => {
                        logVippsCheckout('onCheckoutSuccess invoked.', data);
                        const controller = getVippsCheckoutController();

                        if (!controller?.hasAttempt()) {
                                logVippsCheckout('onCheckoutSuccess passed through; no active checkout attempt.');
                                return true;
                        }

			if (!controller.resolve(data)) {
				const response = {
					type: emitResponseRef.current.responseTypes.ERROR,
					message: translate('missingPaymentUrl', getPaymentMethodMessage('did not return a payment URL.')),
					retry: true
				};

                                errorVippsCheckout('onCheckoutSuccess returning error response.', {
                                        response,
                                        data
                                });
                                return response;
                        }

			const response = {
				type: emitResponseRef.current.responseTypes.SUCCESS
			};

                        logVippsCheckout('onCheckoutSuccess returning success response.', response);
                        return response;
                })
                        : () => {};

                const unsubscribeCheckoutFail = eventRegistration.onCheckoutFail
                        ? eventRegistration.onCheckoutFail(() => {
                        warnVippsCheckout('onCheckoutFail invoked.');
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
                logVippsCheckout('Rendering checkout button preview/editor placeholder.', {
                        isEditor,
                        isPreview
                });
                return createElement('button', {
                        type: 'button',
                        disabled: true
                }, decodeEntities(settings.title || defaultLabel));
        }

        const isDisabled = disabled || waitingForProcessing || waitingForRedirect || busy;

        const handleClick = async (event) => {
                logVippsCheckout('Checkout Widget button clicked.', {
                        disabled,
                        waitingForProcessing,
                        waitingForRedirect,
                        busy,
                        isDisabled,
                        hasAttempt: controllerRef.current.hasAttempt()
                });

                event.preventDefault();

                if (isDisabled || controllerRef.current.hasAttempt()) {
                        logVippsCheckout('Checkout Widget button click ignored.', {
                                isDisabled,
                                hasAttempt: controllerRef.current.hasAttempt()
                        });
                        return;
                }

                logVippsCheckout('Validating Checkout Block before opening Widget SDK trigger.');
                const validationResult = validate
                        ? await validate()
                        : { hasError: false };

                logVippsCheckout('Checkout Block validation result.', validationResult);

                if (validationResult?.hasError) {
                        logVippsCheckout('Checkout validation failed; Widget SDK trigger will not open.');
                        return;
                }

                const attempt = controllerRef.current.begin();

                if (!attempt) {
                        warnVippsCheckout('Could not begin checkout attempt after validation.');
                        return;
                }

                logVippsCheckout('Opening Widget SDK trigger before Store API submit.', {
                        attemptId: attempt.id
                });
                controllerRef.current.open().catch((error) => {
                        errorVippsCheckout('Widget SDK trigger open promise rejected.', error);
                        controllerRef.current.reject(error);
                });

                if (onSubmit) {
                        logVippsCheckout('Submitting Checkout Block Store API request.');
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

logVippsCheckout('Registered Vipps payment methods.', {
        settings,
        hasVipps: Boolean(window.vipps),
        hasVippsTrigger: Boolean(window.vipps?.trigger),
        hasVippsHost: Boolean(window.vipps?.host)
});


}());
