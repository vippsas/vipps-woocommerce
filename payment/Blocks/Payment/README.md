# Checkout payment methods

## Vipps (`vipps`)

`Vipps.class.php` registers the Blocks integration and supplies `vipps_data`.
`js/wc-payment-method-vipps.js` registers its label, description/event subscriber,
and custom place-order button. The button and subscriber share a controller
because WooCommerce renders them as separate components.

### Order creation and widget handoff

1. The button validates checkout, creates a pending payment-URL promise, and starts
   the SDK trigger. The trigger resolver waits for that promise.
2. The button calls WooCommerce's `onSubmit()`. Do not await `trigger.open()` first:
   the trigger needs the URL from the order this submission will create.
3. `onPaymentSetup` supplies the payment method and `vipps_checkout_widget` marker.
   Its success response means request data is ready; it does not mean the customer
   has paid. The PHP bridge currently selects requests by payment method, not by
   this marker.
4. WooCommerce creates/processes the order through the Store API. The PHP bridge
   calls the gateway's normal `process_payment()` unless its pre-process filter
   supplies a result. It places the payment URL/reference in payment details and
   sets the result status to success. This status also tells WooCommerce's later
   legacy fallback that processing has already been handled.
5. `onCheckoutSuccess` reads `processingResponse.paymentDetails`, with support for
   older event/response shapes. It prefers `vippsPaymentUrl` and accepts the normal
   `redirectUrl` as a fallback for a legacy gateway response.
6. The controller resolves the SDK promise with the URL. The observer returns
   `{ type: SUCCESS, redirectUrl: '' }`, completing WooCommerce checkout while
   clearing its automatic redirect. This override applies only to an active
   widget attempt; unrelated success events return `true`.

### Why the empty redirect is required

WooCommerce and the SDK can both receive the same payment URL. Delivering it to
the SDK does not consume or clear WooCommerce's stored redirect. Returning plain
`SUCCESS` or `true` lets WooCommerce complete checkout and navigate to that URL,
interrupting the widget flow.

WooCommerce's success observer handler passes the response to `SET_COMPLETE`.
The completion reducer accepts any string `redirectUrl`, including `''`, in place
of the stored URL. The checkout processor navigates only when its redirect is
nonempty. Preserve the explicit empty string; do not replace it with omission,
`null`, or `true`.

Source references (reviewed September 2026; upstream paths may change):

- [Checkout event contract](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/checkout-flow-and-events/#oncheckoutsuccess)
- [Success observer handling](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/packages/public-api/block-data/checkout/utils.ts)
- [SET_COMPLETE redirect override](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/packages/public-api/block-data/checkout/reducers.ts)
- [Checkout navigation](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/assets/js/base/context/providers/cart-checkout/checkout-processor.ts)
- [Legacy gateway adapter and status guard](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/StoreApi/Legacy.php)

### SDK lifecycle and recovery

`vipps.js` exposes `ensureVippsWidgetHostStarted()` so express and standard checkout
share one desktop host. The Blocks controller can also start the host if the
helper is absent. Host initialization alone does not affect WooCommerce navigation.

The SDK owns payment presentation: desktop normally uses a dialog; mobile/tablet
and desktop fallback paths may navigate. SDK success/cancel handlers close the
dialog and follow the merchant-return URL when supplied. These return URLs serve
a different purpose from the initial payment-session URL. See the
[Widget SDK documentation](https://developer.vippsmobilepay.com/docs/knowledge-base/widget/).

The controller's attempt ends when the URL is delivered. It does not represent
payment settlement. WooCommerce's processing/redirect flags also disable the
button. During the attempt, `setVippsPaymentBusy()` in `vipps.js` shows the same
spinner and overlay as express checkout, reusing the existing `.vippsoverlay`
markup and `body.processing` styles. Each flow has its own owner key so clearing
one cannot hide the other's spinner. URL delivery or failure releases the overlay
for the SDK UI; unmounting the checkout button also releases its overlay owner.
The button exposes `aria-busy` while its attempt is pending.
Missing payment URLs return a retryable checkout error; checkout failures
reject the waiting resolver. If the SDK is unavailable at handoff, the controller
uses full-page navigation directly.

A session-storage handoff marker refreshes stale checkout pages on history restore,
focus, or visibility return. A per-path guard prevents repeated reloads. This is
separate from payment confirmation; order metadata in the marker is optional.

## Express Vipps (`vippsexpress`)

`ExpressCheckoutButton` renders the server shortcode markup. `vipps.js` handles
the express button, calls the express session endpoint, and supplies its URL
directly to the SDK. Although registration maps `paymentMethodId` to `vipps`,
this express flow does not use the standard button's pending Store API promise.
Its confirmation dialog and handoff recovery remain in the shared script.

## Card (`vipps_card`)

`VippsCard.class.php` supplies settings and registers the card component.
`js/wc-payment-method-vipps-card.js` renders the description, title, card logos,
and customized place-order button label. Visibility remains filterable.

The card method uses WooCommerce's standard submit button and legacy Store API
gateway processing. WooCommerce follows the gateway's redirect to begin payment.
It has no widget controller or success observer, so the Vipps widget's empty
redirect override must not be copied into this method.

## Diagnostics and verification

Routine console traces, checkout-store subscriptions, page-exit diagnostics, and
PHP response-dump hooks have been removed. JavaScript warnings/errors remain for
integration failures. PHP validation still throws exceptions for WooCommerce to
surface as checkout errors.

When changing the handoff, verify that widget and legacy-redirect payloads both
deliver the URL, that an active widget success returns an empty redirect, that
success without an active attempt passes through, and that a missing URL returns
a retryable error. On a live site, also verify desktop widget completion/cancel,
mobile navigation, stale-page recovery, and the card method's normal redirect.
