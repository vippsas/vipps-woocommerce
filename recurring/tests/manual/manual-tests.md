# Manual tests

We currently have no testing suite for Vipps/MobilePay Recurring Payments for WooCommerce

It's imperative we test each update manually.

## Testing orders

### Order creation

I should be able to create an order for a subscription product and pay via Vipps/MobilePay with the following configurations:

- Physical products
    - On different intervals (day, week, month, year)
    - On a sale
    - Synchronized renewal
    - Synchronized renewal with a "Sign-up grace period" set
    - I can capture an order through changing the status to "Completed" or "Processing"
    - With the "capture payment instantly" option set
    - With a free trial; the agreement request has a zero-priced period campaign and no initial charge
    - With an initial-payment discount that reduces the order total to zero; the agreement request has a zero-priced campaign and no initial charge
    - With a synchronized renewal, no free trial, no sign-up fee, and a zero initial order line total; the agreement request has neither a campaign nor an initial charge
  
- Digital products
    - On different intervals (day, week, month, year)
    - On a sale
    - Synchronized renewal
    - Synchronized renewal with a "Sign-up grace period" set
    - Synchronized renewal with a "Prorate First Renewal" with "For Virtual Subscription Products Only" set
    - With a free trial; the agreement request has a zero-priced period campaign and no initial charge
    - With an initial-payment discount that reduces the order total to zero; the agreement request has a zero-priced campaign and no initial charge
    
- Variable subscriptions
    - Test both physical and digital

### Refunds

I should be able to refund an order both _partially_ and in _full_.

Additionally, I should be able to refund an order _partially_ and then in _full_ later on.

## Testing subscriptions

### Renewal

I should be able to renew a subscription normally and early.

### Cancellation

I should be able to cancel a subscription, the subscription agreement should then get a "STOPPED" status in Vipps.

### Swapping gateway

- I should be able to swap to Vipps/MobilePay Recurring Payments from any other gateway successfully.
- I should be able to swap to Vipps/MobilePay from Vipps/MobilePay 


### Recurring purchase address handling

Verify these cases in the full manual run after implementation:

- Select billing and shipping addresses in Recurring Checkout that differ from
  the account defaults. The order and subscription must use the selected addresses.
- Complete the same purchase again with a later callback. Confirmed geographic
  fields must remain unchanged, and totals must not be recalculated.
- Apply profile data with different street/city/postcode values. It must not
  replace confirmed order locations or combine parts of two different addresses.
- Apply empty or missing profile fields. Saved contact data must not be erased.
  Missing non-location fields can be filled without replacing existing values.
- Keep an existing customer's saved account addresses unchanged. For a new
  customer, save the accepted order addresses rather than conflicting profile data.
- Select an address with a second line, then a single-line address before approval.
  The stale second line must be cleared. Check multiline streets and phone values
  with and without a leading plus.
- Confirm shop-calculated addresses before agreement creation in the future express
  checkout adapter. Profile completion must not change the locations used for tax
  or shipping. If required location data is unavailable, resolve it on the shop.


### Recurring express-purchase Userinfo recovery

Run these once the customer-facing flow is implemented:

- Inspect the agreement request: profile `scope` is top-level and added only for
  a new express purchase. Normal checkout, hosted Checkout, renewals, switches, and
  gateway changes must not enter this new recovery path.
- Inspect Userinfo requests: the endpoint uses the configured environment and
  agreement `sub`; omit subscription-key/client-secret headers and do not follow
  returned redirects. Do not log access tokens or the full profile.
- Delay `sub` or Userinfo and repeat webhook/status calls. Retry the same purchase
  without duplicate customers/subscriptions or early local activation/capture.
- Check a trial/zero initial payment and a captured initial payment while profile
  completion waits. Preserve real API state and do not cancel captured funds.
- Simulate temporary API failure, malformed/empty profile data, and customer
  creation failure. Keep the pending queue active and use retry backoff.
- Check expired profile access and repeated failures: order notes must explain
  recovery. Assign the customer, fill saved billing details, and use the manual
  recovery order action for an active agreement. Verify normal completion resumes.
- Stop/reject/expire an unpaid agreement, and stop an agreement whose initial
  payment has been captured. Only the unpaid case may follow normal cancellation;
  the paid case must remain available for merchant review and must not activate.
- Run abandoned-subscription deletion and unpaid-order cancellation while customer
  completion is pending. The order/subscription must remain available for recovery.
- Deliver activation/charge events together, and complete without a browser return.
  Customer assignment must precede the normal local payment completion path.
### Recurring express buttons and Widget SDK

Run these cases after the final integration review. Use the existing payment
button and Express placement settings; do not create recurring copies.

- Check product/cart/mini-cart/classic checkout/Blocks placement and per-context
  button style. Repeat with the one-time gateway disabled and Checkout disabled.
- Check classic and Blocks normal recurring checkout on desktop and mobile.
  Verify one desktop host, one order/request, and the unchanged approval URL.
  Check existing-order payment separately. Agreement-change screens retain redirects.
- Check simple/variable subscriptions, quantity/stock validation, initial trials,
  zero initial totals, sign-up fees, coupons, fees and supported subscription groups.
  Verify the exact initial and renewal amounts in WooCommerce and Vipps MobilePay.
- Check that missing tax locations, missing shipping selection, differing renewal
  shipping amounts and custom required fields use shop checkout before agreement creation.
- Check direct product purchase with an existing cart/coupons, compatibility mode,
  terms-dialog cancellation, API cancellation and normal-checkout fallback. Verify
  the selected product is added only once and the earlier cart/coupons return once.
  Custom product forms must use WooCommerce's native submission.
- Check terms enabled/disabled, no terms page, custom form fields, and a direct
  request with `confirmed=1` but no checked terms value. Required terms must fail
  on the server. Check the recurring validation/storage hooks.
- Check cached pages for guest and logged-in users. Nonces must come from the
  current browser's Woo session through the dynamic bootstrap.
- Send duplicate requests/clicks and interrupt an agreement response. Check a
  reload/background retry uses the same order, filtered body and idempotency key.
  Confirm changing the cart does not replace an outstanding purchase. Check two
  workers cannot acquire the same lock and an old worker cannot release a new lock.
- Close the browser during an uncertain response. Verify recovery records the
  existing agreement/charge and continues profile completion. Check the ten-failure/
  twelve-hour stop, local cancellation and merchant/environment-change protection.
- Check original-session-only customer login, other-browser/order-key-only access,
  privileged accounts, login changes during approval, and consumed return tokens.
  Profile waiting must poll; merchant attention must show the processing notice.
- Check Back/bfcache/app-return behavior, recent-purchase confirmation, and a new
  purchase after completed/canceled approval. Each fresh attempt needs fresh identifiers.
- Check the My Account management URL, including a missing configured account page.
