=== Vipps Recurring Payments for WooCommerce ===
Contributors: Vipps, EverydayAS
Tags: vipps, recurring payments, subscriptions, woocommerce, woocommerce subscriptions
Requires at least: 5.0
Tested up to: 5.8
Stable tag: trunk
Requires PHP: 7.0
License: AGPLv3.0 or later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

== Description ==

_Official Vipps Recurring Payments plugin for WooCommerce. 4 million Norwegians use Vipps. Give them a fast and familiar shopping experience._

This is the official Vipps Recurring Payments plugin for WooCommerce. It is owned by [Vipps AS](https://vipps.no) and maintained by [Everyday AS](https://everyday.no).

**We encourage you to create an issue here or on the [GitHub page](https://github.com/vippsas/vipps-recurring-woocommerce/issues) if you require assistance or run in to a problem.**

== Vipps Recurring Payments ==

Vipps Recurring Payments for WooCommerce is perfect for you if you run a web shop with subscription based services or other products that would benefit from subscriptions.

With Vipps Recurring Payments for WooCommerce you can:

* Sell recurring products (virtual and physical)
* Offer subscription services

See [How it works](https://github.com/vippsas/vipps-recurring-api/blob/master/vipps-recurring-api-howitworks.md) for an overview.

<img src="https://raw.githubusercontent.com/vippsas/vipps-recurring-api/master/images/vipps-recurring-process.svg?sanitize=true" alt="Vipps Recurring Process" />

== Requirements ==

* WooCommerce 3.3.4 or newer
* [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
* PHP 7.0 or higher
* An SSL certificate must be installed and configured
* Port 443 must not be blocked for outgoing traffic

== Getting started ==

* Sign up to use Vipps på Nett ([WooCommerce](https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/))
* Vipps will review the application and perform KYC and AML controls. You may log onto [portal.vipps.no](https://portal.vipps.no) to see the status of your application. This is also where you can retrieve the API credentials used to configure the plugin in WooCommerce after your application has been approved.
* Proceed to "Installation" below

== Installation ==

To use the plugin you need to do the following:

1. Download and activate the plugin from this GitHub repository or Wordpress.org
2. Enable the Vipps Recurring Payments ("Vipps faste betalinger") payment method in WooCommerce: `Settings` -> `Payments (Betalinger)`.
3. Click "Manage" on the Vipps Recurring Payments payment method
4. Proceed to "Retrieving Vipps API Keys" below

= Retrieving Vipps API Keys =

The documentation for retrieving your Vipps API Keys can be found [here](https://github.com/vippsas/vipps-developers/blob/master/vipps-getting-started.md#getting-the-api-keys).

= Configuration of the plugin =

1. Fill in the `client_id`, `client_secret` and `Vipps-Subscription-Key` found in the previous step.
2. That's it! You can now move on to "Configuring products"

= Configuring products =

Configuring products for use with the Vipps Recurring Payments plugin is not any
different from default WooCommerce, with one exception: The configuration for
whether or not the product is virtual or physical is important to consider.

If a product is virtual the customer will be charged immediately but if the
product is physical you will have to capture the payment manually through the
order in WooCommerce when you have shipped the product.

In most cases your products should be virtual when using subscriptions but it is
possible to use the plugin with physical products if you need to do so.

See the
[Vipps Recurring FAQ](https://github.com/vippsas/vipps-recurring-api/blob/master/vipps-recurring-api-faq.md),
and the
[Vipps eCom FAQ](https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api-faq.md)
for more details:
[What is the difference between "Reserve Capture" and "Direct Capture"?](https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api-faq.md#what-is-the-difference-between-reserve-capture-and-direct-capture).

== Screenshots ==

1. Setup and activate the payment gateway in WooCommerce
2. Configure the plugin settings

== Frequently Asked Questions ==

= How can I get help? =

If your question is not answered on this page:

* For help with the plugin: Please use the [support forum on wordpress.org](https://wordpress.org/support/plugin/vipps-recurring-payments-gateway-for-woocommerce/) or [submit an issue](https://github.com/vippsas/vipps-recurring-woocommerce/issues) on GitHub.
* For help with Vipps: Please see the
  [contact us](https://github.com/vippsas/vipps-developers/blob/master/contact.md)
  page, and also the main
  [Vipps GitHub page](https://github.com/vippsas).
  The
  [Vipps Recurring FAQ](https://github.com/vippsas/vipps-recurring-api/blob/master/vipps-recurring-api-faq.md)
  and the
  [Vipps eCom FAQ](https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api-faq.md) may also be useful.

= Does this plugin work alongside the Vipps for WooCommerce plugin? =

Yes! You can use this plugin at the same time as [Vipps for WooCommerce](https://github.com/vippsas/vipps-woocommerce).

= Do I need to have a license for WooCommerce Subscriptions in order to use this plugin? =

Yes, you do. Get it
[here](https://woocommerce.com/products/woocommerce-subscriptions/).

= Does this plugin work with the WooCommerce Memberships-plugin? =

[WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
and
[WooCommerce Memberships](https://woocommerce.com/products/woocommerce-memberships/)
are able to work together for access to recurring memberships that unlock content.

**WooCommerce Subscriptions is required in order to use Vipps Recurring Payments for WooCommerce, but Memberships is not.**

You can read about how WooCommerce Subscriptions and WooCommerce Memberships work together [here](https://docs.woocommerce.com/document/woocommerce-memberships-subscriptions-integration/).

= How can I get help if I have any issues? =

For issues with the plugin you can submit an issue on GitHub or ask on the support forum on wordpress.org. For other unrelated issues you should [contact Vipps](https://github.com/vippsas/vipps-developers/blob/master/contact.md).

= Where can I use Vipps? =

Vipps is only available in Norway at the moment and only users who have Vipps will be able to pay with Vipps.

= How can I test that the plugin works correctly? =

If you have access to the Vipps test environment you are able to use the test mode by setting the `WC_VIPPS_RECURRING_TEST_MODE` constant in `wp-config.php`.
See the [getting started](https://github.com/vippsas/vipps-developers/blob/master/vipps-getting-started.md) guide for details about how to get started using the test environment.

Vipps does not offer test accounts for regular users of the plugin but you can still penny-test the plugin by sending a small amount of money like 1 or 2 NOK using your production keys.
You can then refund or cancel the purchase afterwards.

= Why do I have to capture payments for physical products manually? =

This is because of the Norwegian law. You are not allowed to charge for a physical product before you ship it, without a valid reason to do so.

You can read about it [here](https://www.forbrukertilsynet.no/english/guidelines/guidelines-the-standard-sales-conditions-consumer-purchases-of-goods-the-internet#chapter-7).

If you have a valid reason to do so you can use the "Capture payment instantly" option from the "Vipps Recurring Payments" settings in your product's settings.

= When a renewal happens, why is the order on hold? =

This is because when an order is charged in Vipps it takes 2 days before the payment has been fully captured from the customer's bank account.

After 2 days it will move to the "Processing" status. You can however change the behaviour of this by using the "Default status to give pending renewals" option in the plugin settings.

Alternatively you could look into using WooCommerce "Early renewals": [https://docs.woocommerce.com/document/subscriptions/early-renewal/](https://docs.woocommerce.com/document/subscriptions/early-renewal/) if ensuring the status of a charge is fully completed before a specific date is of up-most importance.

== Changelog ==

= 1.11.0 =
* Added: You can now pay for single payment products in the same shopping cart as a subscription.

= 1.10.0 =
* Refactor: Rewrote our admin notification system to allow for dismissible alerts.
* Added: A campaign banner for Login with Vipps. This will last for 10 days, and it is fully dismissible.

= 1.9.1 =
* Fix: Renewal orders will no longer transition to `completed` when the setting for this is turned off.
* Fix: Fixed a problem where our `_vipps_recurring_waiting_for_gateway_change` was never processed for orders where the subscription was not `wc-active`. Added `wc-on-hold` and `wc-pending` to this list.

= 1.9.0 =
* Enhancement: Added a setting for what sort order we should check charges by.
* Enhancement: Added a setting for how many charges we should check at a time.
* Enhancement: Default amount of charges to check per wp-cron run is now 10.
* Change: Removed extra sortable columns from pending charges and failed charges tables. Sorting by meta value does not work in WooCommerce's wc_get_orders.
* Fix: Order by random now works as expected in our periodic status check for charges.

= 1.8.4 =
* Fix: The 1.8.2 migration will no longer throw a fatal error for some installations if a subscription does not exist on an order.

= 1.8.3 =
* Fix: Fixed an issue where an invalid "my account" page link would cause checkout to not work.

= 1.8.2 =
* Enhancement: Added `_vipps_recurring_latest_failed_charge_reason` and `_vipps_recurring_latest_failed_charge_description` to subscriptions.
* Enhancement: Migrate the aforementioned data.
* Enhancement: Update tested up to, now 5.8.

= 1.8.1 =
* Fix: Added an exit condition after attempting to redirect to the Cancelled Vipps Purchase page. This should in theory make this redirect work every single time.
* Fix: No longer clean up necessary data like charge id or whether the charge is pending on renewals.
* Fix: Force re-checking of older charges that are affected by the aforementioned fix.
* Change: We now check 8 charges at the same time instead of 5.

= 1.8.0 =
* Fix: Cancelled agreements can not be updated, thus we can not say that they have been cancelled in the description. Ensure we run the "maybe_update_subscription_details_in_app" code before we cancel an agreement, and only if the agreement is still "ACTIVE".
* Fix: Ensure we only ever cancel an agreement once.
* Fix: It's no longer possible to end up with more than one Vipps agreement by changing gateway twice or more on accident (this bug was introduced in 1.7.0).
* Enhancement: We now cancel DUE charges in Vipps when you change an order's status to 'Cancelled' or 'Failed'.

= 1.7.0 =
* Fix: Add missing Norwegian translation for "No failed charges found.".
* Fix: Failed charges now appear properly in the "Failed charges" list.
* Fix: Fixed an edge case when changing payment method to Vipps as a customer, when already using Vipps.
* Fix: Support upgrading to a different product variation. Price is now passed along properly and a campaign is applied in order to not confuse the customer.
* Fix: Update agreement in app when downgrading or not prorating a subscription.
* Fix: Do not allow multiple subscriptions in a single Vipps payment when other gateways that support it are enabled.
* Fix: No longer show the "capture" button on an order where the charge has failed previously.
* Fix: Delete meta values from renewal and resubscribe orders to make sure we get fresh charges.
* Fix: WooCommerce's automatic retry system should now work as expected on renewal orders. We now override the behaviour of `wcs_is_scheduled_payment_attempt` in our `check_charge_status` function.
* Enhancement: The subscription description is now prefixed in the Vipps app with "[Pending cancellation]", "[Cancelled]" and "[On hold]" depending on the subscription's status.
* Enhancement: Added a setting per product for what source should be used for an agreement's description. You can now choose between product title, product short description, or custom text.
* Enhancement: Display sign up fees in a better way in the Vipps app. Use a campaign with a price of 0,- until the next payment date.
* Enhancement: Update item name and description in app for delayed in app updates.
* Enhancement: Added a link to explain the various possible failure reasons.
* Enhancement: Ability to change gateway from the admin dashboard, this means setting another Vipps agreement ID manually. This should also allow using the REST API to create a subscription.
* Enhancement: Bring style on notices and in product tabs up to par with the Vipps e-com plugin.

= 1.6.2 =
* Fix: Swapping payment gateway to Vipps now works again after the recent WooCommerce and WooCommerce Subscription updates.

= 1.6.1 =
* Fix: 'free' agreements where the agreement status is 'STOPPED' should not be completed.
* Fix: Do not attempt to fetch charge for synchronised renewals at initial order.
* Fix: Add 'completed' to 'woocommerce_valid_order_statuses_for_payment_complete' conditionally instead of rolling our own logic.
* Fix: Remove visibility modifier from `const` to fix support for PHP 7.0.

= 1.6.0 =
* Enhancement: There's now an overview of failed charges and their failure reason on the Settings -> Vipps Recurring Payments page.
* Refactor: Use the Helper class for most of our frequently used meta keys.
* Refactor: Renamed the class filenames, class names remain untouched.
* Fix: Redirect to last known agreement confirmation URL if the agreement is pending. This prevents being stuck in checkout.

= 1.5.5 =
* Fix: No longer attempt to charge a non existent agreement when a renewal is attempted on a subscription that does not have a Vipps agreement.
* Fix: Multibyte characters like 'æøå' in product titles work, these characters caused an internal server error when json encoding.

= 1.5.4 =
* Fix: Cancelled or expired "zero amount" payments getting stuck in the checking queue

= 1.5.3 =
* Fix: A rare edge case where an order was never captured for a long time and the order would not disappear from the status checker tool.
* Fix: A rare edge case where a new pending renewal order would not fetch the Charge ID properly when hitting the capture payment button.
* Fix: Fixed an issue where 100% off coupons did not automatically complete the initial purchase
* Enhancement: The status checker tool now shows the latest status from the API as a column value
* Enhancement: We now validate Vipps API details when you save your settings

= 1.5.2 =
* Fix: Fixed a bug where you were able to checkout the same order more than once while a Vipps agreement is not expired. This fixes problems with multiple charges occurring.

= 1.5.1 =
* Fix: We're no longer trying to cancel an agreement in Vipps when the status is anything other than `ACTIVE`.
* Fix: No longer throw an error and retry with "latest charge" when we hit a rate limit when checking a single charge.

= 1.5.0 =
* Fix: No longer mark an order as "Failed" if a charge's status is "FAILED" unless an agreement is also "EXPIRED" or "STOPPED" (a user can be prompted to swap card or top-up bank account in-app).
* Fix: No longer attempt to cancel an already cancelled charge.
* Fix: We should no longer hit the Vipps API rate-limit as a result of a renewal order.
* Fix: Properly set the `_charge_id` meta key when capturing a payment.
* Fix: Changing of gateways to Vipps Recurring Payments would sometimes cancel the new agreement instead of the old one due to a race condition.
* Fix: Multiples of the same product in the same subscription will now be charged correctly based on the total order price.
* Tweak: Updated the `WC_VIPPS_RECURRING_RETRY_DAYS` constant to 4 days by default.
* Enhancement: Bolstered compatibility with WC < 3.0. Created a bunch of helper functions and doubled down on our backwards compatibility with fetching IDs and fetching plus updating meta.
* Enhancement: If you update a subscription's order item prices this will now be reflected in the app.

= 1.4.6 =
* Fix: Fix a bug when an agreement is created twice on the same order. We didn't fetch the right charge ID. This is a temporary fix I will have to revisit later on a proper setup.

= 1.4.5 =
* Fix: Changed how we make requests to minimize the amount of times we hit Vipps' rate limits. If you have gotten a lot of exceptions lately
that look like this: `HTTP Response Error: (recurring/v2/agreements/:id/charges) with request body: Array` it's because of rate limits.
* Enhancement: Errors caused by HTTP requests now have more debug information attached to them.

= 1.4.4 =
* Enhancement: Added an option to move renewal orders from "processing" to "completed" when the charge completes in Vipps.

= 1.4.3 =
* Fix: Truncate agreement description if it's longer than 100 characters and truncate productName if it's longer than 45.

= 1.4.2 =
* Fix: Truncate initialCharge description if it's longer than 45 characters.

= 1.4.1 =
* Fix: Added a polyfill for the PHP `array_key_last` function for hosts with PHP < 7.3.0.

= 1.4.0 =
* Enhancement: Added a "Default status to give orders with a reserved charge" option.
* Enhancement: Improved safety of reserved charges, they should never be put in an unrecoverable state anymore. Un-nested payment logic so we should never run into a similar case again.
* Fix: Refunding a reversed charge now works, just like pending charges in version 1.3.9.
* Change: "Default status to give pending renewal orders" default is now "processing" instead of "on hold"
* Change: Removed "completed" as a possible default status for the "Default status to give pending renewal orders" setting as this status is conventionally used for orders that are completely finished.
