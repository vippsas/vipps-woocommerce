# Vipps MobilePay Recurring - Development docs

## Reusing payment helpers

The integrated plugin loads `payment/` before recurring, even when the one-time
gateway is disabled. Prefer its public helpers, hooks, and registered assets over
moving code or adding another implementation. Keep recurring adapters here and
avoid dependencies on the removable Vipps Checkout classes.

`Vipps::instance()->get_express_checkout_terms_html()` uses the active one-time
terms setting and existing WooCommerce/Vipps filters. It returns `null` when the
terms prompt is disabled. The dialog and validation factories are available through
`vipps-gw` in the existing `payment/js/vipps.js`; their styles remain in the existing
payment stylesheet. Check enqueue conditions before using them in a recurring flow.
New callers supply purchase state and any required server-side terms validation.

Use syntax/diff checks during implementation and the existing manual-test guidance
for full verification. Do not add a standalone unit-test harness for this work.

## Profile recovery

New express purchases request profile data and mark completion as required. The
status checker uses `WC_Vipps_Recurring_Profile` before local activation/capture.
Ordinary recurring checkout and hosted Checkout do not enter this profile path.

When completion needs attention, inspect the order notes. For an active agreement,
assign the correct customer, fill billing name/email/phone, then use the order
action **Retry recurring completion with saved billing details**. Stopped
agreements with an initial capture/refund require separate payment review; do not
delete them or create another payment to resolve missing customer data.

Profile retries are independent of the browser and tied to the existing agreement.
No new unit-test harness is used. Run the recovery cases in the existing manual
guide during the deferred full payment verification.

## Express purchase adapters

`WC_Vipps_Recurring_Express_Checkout` registers session-protected recurring REST
purchase endpoints and the existing-settings buttons. Its script obtains fresh
nonces through Woo AJAX so cached pages do not contain another customer's nonce.
Unresolved tax/shipping/custom required fields use normal shop checkout.

The payment classic controller accepts `VippsClassicCheckoutMethods`; the payment
Blocks controller exposes `registerVippsWidgetPaymentMethod(settings, method,
filters)`. Recurring supplies method data and hooks. Do not copy their SDK logic,
assets, or settings. Subscription gateway-change screens retain their existing
redirect until their Store API support is verified separately.

`wc_vipps_recurring_express_checkout_validation($post, $errors)` validates custom
confirmation data. `wc_vipps_recurring_express_checkout_order_created($order, $post)`
stores accepted custom values. Server-side terms validation runs independently.

Uncertain agreement creation reuses the frozen request and idempotency key through
a background job. After ten failures or twelve hours, or after local cancellation,
the merchant must reconcile the purchase before creating another agreement.
This time limit is an internal recovery policy. The order notes identify this
case separately from a missing customer profile.

## Updating language files

The easiest way to update language files is to first and foremost generate an up-to-date .pot file using `wp-cli`:

```bash
wp i18n make-pot . languages/vipps-recurring-payments-gateway-for-woocommerce.pot --exclude=node_modules,assets/build
```

Now you can translate the existing languages, or create new ones, using Loco translate or a similar plugin or tool.

Remember to make a JSON file from each language after editing the `.po` files. The JSON files are used by the front-end.

```bash
wp i18n make-json languages --no-purge --use-map=build.map.json
```
