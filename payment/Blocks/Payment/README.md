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

## Classic shortcode checkout

`vipps-classic-checkout.js` uses the existing WooCommerce form and the optional
branded `#vipps-classic-checkout-submit` submit button. It handles
`checkout_place_order_vipps` synchronously, suppresses core's default AJAX request,
and submits the full checkout form to `wc_checkout_params.checkout_url` from the
SDK resolver. WooCommerce still performs validation, creates/resumes the order,
and invokes the gateway. This adapter owns the response, so core does not also
redirect to the payment URL. It does not emit the classic success hook, whose
normal consumer would redirect or enter its generic error branch.

The script reuses the shared host and spinner. Form fields are serialized before
being disabled; their prior disabled states are restored on a known checkout
failure. The form stays locked after URL delivery until the dialog exits, so
another payment cannot be submitted behind the dialog. A close/cancel without a
return URL reloads checkout; a close during submission waits for the request to
finish first. Restoring an old attempt from the back-forward cache also reloads.

Server checkout errors remain visible through WooCommerce's error presenter when
available, with a notice fallback for versions that do not pass the checkout
controller into the hook. Refresh/reload responses are honored. An unconfirmed
network result or unusable success response offers a reload link and blocks a
second POST; it never automatically retries a possibly successful payment request.
If the SDK fails after submission, a valid returned payment URL can be followed
directly without creating another payment.

The branded button is selected by the checked `payment_method` radio. For Vipps,
the script removes its `hidden` class and hides `#place_order`; for every other
gateway it restores the normal button. The switch is delegated so it survives
WooCommerce replacing the payment section after `updated_checkout`. The native
button remains the fallback when the branded button or JavaScript is unavailable.
The button uses the same form-submit path, so it does not bypass WooCommerce
validation or create a second payment request.

On `woocommerce-order-pay` pages it uses the existing-order Store API route rather
than cart checkout AJAX. It localizes `VippsOrderPayConfig` before this script runs:

```js
window.VippsOrderPayConfig = {
    orderId: 123,
    orderKey: 'wc_order_key_for_guest_links',
    billingEmail: 'customer@example.com',
    endpoint: '/wp-json/wc/store/v1/checkout/123',
    nonce: 'store-api-nonce',
    billingAddress: { first_name: '', last_name: '', address_1: '', city: '', state: '', postcode: '', country: '', email: '' },
    shippingAddress: { first_name: '', last_name: '', address_1: '', city: '', state: '', postcode: '', country: '' }
};
```
The adapter sends the selected Vipps method and form fields as
`payment_data`, then reads the URL from `payment_result.payment_details` or its
redirect fallback. It never sends the pay form to cart checkout AJAX.

Before sending a Vipps request, it checks the same `terms-field` marker and
`terms` checkbox used by WooCommerce's native pay-for-order handler. Missing
terms are displayed using standard WooCommerce error-notice markup, and the page
scrolls to the notice. Store API failures, missing payment URLs, and SDK startup
failures use the same notice path. The notice container is refreshed and focused
so the customer can correct the form and retry.

The order-pay form remains native when this configuration or the SDK is missing.
Its existing submit handler is prevented only for an active Vipps SDK attempt.
Success hands the URL to the SDK; cancellation/close reloads the same authorized
pay URL, and failures restore the form for retry. The adapter never automatically
reposts an uncertain request.
Blocks and non-Vipps methods retain their existing handlers. If the main plugin
already attaches a Vipps-specific classic submit handler, consolidate that handler
with this one so it cannot initiate a second payment independently.

### Loading and dependencies

Load `vipps-classic-checkout.js` only on the classic checkout and
`woocommerce-order-pay` pages. Order-pay must remain enabled when the configured
checkout page uses Blocks, because WooCommerce renders the pay form through the
classic shortcode template in that case. The script depends on `jquery`,
`wc-checkout`, and `vipps-gw`; the latter must load `vipps.js` and the SDK before
the customer submits. Do not load this adapter on the Checkout Block itself.
