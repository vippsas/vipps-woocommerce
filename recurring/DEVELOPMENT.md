# Vipps MobilePay Recurring - Development docs

## Reusing payment helpers

The integrated plugin loads `payment/` before recurring, even when the one-time
gateway is disabled. Prefer its public helpers, hooks, and registered assets over
moving code or adding another implementation. Keep recurring adapters here and
avoid dependencies on the removable Vipps Checkout classes.

`Vipps::instance()->get_express_checkout_terms_html($enabled)` accepts recurring's
terms setting and retains existing WooCommerce/Vipps filters. Omitting the argument
keeps the one-time terms setting. It returns `null` when the
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

## Express checkout for subscriptions

Use "Express checkout" in customer text and "Express checkout for subscriptions"
in recurring settings. The Norwegian labels are "Hurtigkasse" and "Hurtigkasse for
abonnementer". This flow creates a Recurring agreement and obtains customer details
through Userinfo. Shipping is calculated in the store, not selected in the app.
Keep existing internal `express` identifiers.

`WC_Vipps_Recurring_Express_Checkout` registers session-protected recurring REST
purchase endpoints and buttons using recurring's own behavioral settings. Its script obtains fresh
nonces through Woo AJAX so cached pages do not contain another customer's nonce.
Unresolved tax/shipping/custom required fields use normal shop checkout.

The payment classic controller accepts `VippsClassicCheckoutMethods`; the payment
Blocks controller exposes `registerVippsWidgetPaymentMethod(settings, method,
filters)`. Recurring supplies method data and hooks. Do not copy their SDK logic,
assets, or button appearance settings. Subscription gateway-change screens retain their existing
redirect until their Store API support is verified separately.

Configure the feature under the recurring gateway's "Express checkout for
subscriptions" section. `express_enabled` defaults to `no`; enable it explicitly.
`express_cart_button` and `express_checkout_button` control cart/mini-cart and
checkout placement. `express_product_page_button` controls product-page buttons;
`express_archive_button` controls listing buttons independently.
`express_product_button` selects all/selected subscription products for both
placements. Selected products use `_vipps_recurring_express_button` in the recurring
product tab, independently of one-time product opt-in. The settings UI groups the
four placements under "Configure placement". Changing the master switch changes
all placements; changing a placement enables the master if any placement is on.
The old `no` product selection becomes `all` with product and listing placements
off. Existing `all`/`some` selections retain their previous placements. This
normalization is saved through WooCommerce on the next settings save.
`express_include_cart` controls cart inclusion for
product purchases. `express_terms` defaults to `yes` and uses WooCommerce's terms
checkbox/page. `express_always_address` defaults to `no`; shipping still requires
address scope. New direct endpoint requests enforce the main switch; saved
purchases and background recovery remain available when it is disabled.
The UI groups cart inclusion, terms acceptance and abandoned-order cleanup under
"Advanced settings" within Express. Additional options are shown only while
Express is enabled, matching Payment's settings UI.

`express_cleanup_abandoned_orders` defaults to `no`. The independent
`WC_Vipps_Recurring_Express_Cleanup` policy feeds the existing hourly deletion
worker. It records when profile recovery confirms an unpaid aborted purchase and
retains it for seven days. Discovery also handles older aborted records, using
their last modification time when no abandonment timestamp exists. Before trashing
the initial order and its single pending/cancelled subscription, it checks the
original merchant/API context, a fresh STOPPED/EXPIRED agreement, and every charge.
Only FAILED/CANCELLED charges, or no charges when no initial charge ID is recorded,
permit cleanup. Missing charge records, reservations, payment/refund history,
billing details, renewal history, active subscriptions, profile recovery and API
failures retain the records. Existing purchase/profile locks protect deletion.
Retained candidates leave the shared queue and can be rediscovered on later passes.
The Checkout cleanup option never queues these Express purchases.

Merchant-facing descriptions explain the available choices without API details.
The Express section includes a prominent static-shipping notice and fixed/free
shipping guidance. Technical lifecycle details belong here rather than in settings.

Only button appearance remains shared through `vipps_button_options2` and the
existing renderer. Recurring applies its own brand to the resulting web component.

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
