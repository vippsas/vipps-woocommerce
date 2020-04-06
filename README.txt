=== Vipps Recurring Payments for WooCommerce ===
Contributors: Vipps, EverydayAS
Tags: vipps, recurring payments, subscriptions, woocommerce, woocommerce subscriptions
Requires at least: 5.0.0
Tested up to: 5.3.2
Stable tag: trunk
Requires PHP: 7.0
License: AGPLv3.0 or later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

== Description ==

**This plugin is currently a test pilot (pre-release). It is very likely that you will encounter bugs or scenarios that are not yet supported.**

**Please keep up to date with updates as soon as they happen. They are very important in this pre-release period.**

**We encourage you to create an issue here or on the [GitHub page](https://github.com/vippsas/vipps-recurring-woocommerce/issues) if you require assistance or run in to a problem.**

For Vipps contact information check the main Vipps GitHub page: [https://github.com/vippsas](https://github.com/vippsas).

This is the official Vipps Recurring Payments plugin for WooCommerce. It is owned by [Vipps AS](https://vipps.no) and maintained by [Everyday AS](https://everyday.no).

Vipps Recurring Payments for WooCommerce is perfect for you if you run a web shop with subscription based services or other products that would benefit from subscriptions.

With Vipps Recurring Payments for WooCommerce you can:

* Sell recurring products (virtual and physical)
* Offer subscription services

== Requirements ==

* WooCommerce 3.3.4 or newer
* [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
* PHP 7.0 or higher
* An SSL certificate must be installed and configured
* Port 443 must not be blocked for outgoing traffic

== Getting started ==

* Sign up to use ([Vipps på Nett](https://www.vipps.no/signup/vippspanett/))
* After 1-2 days you will get an email with login details to Vipps Developer Portal. This is where you can retrieve the API credentials used to configure the plugin in WooCommerce.
* Proceed to "Installation" below

== Installation ==

To use the plugin you need to do the following:

1. Download and activate the plugin from this GitHub repository or Wordpress.org
2. Enable the Vipps Recurring Payments ("Vipps faste betalinger") payment method in WooCommerce -> Settings -> Payments (Betalinger).
3. Click "Manage" on the Vipps Recurring Payments payment method
4. Proceed to "Retrieving Vipps API Keys" below

= Retrieving Vipps API Keys =

The documentation for retrieving your Vipps API Keys can be found [here](https://github.com/vippsas/vipps-developers/blob/master/vipps-getting-started.md#getting-the-api-keys).

= Configuration of the plugin =

1. Fill in the `client_id`, `client_secret` and `Vipps-Subscription-Key` found in the previous step.
2. That's it! You can now move on to "Configuring products"

= Configuring products =

Configuring products for use with the Vipps Recurring Payments plugin is not any different from default WooCommerce, with one exception.

The configuration for whether or not the product is virtual or physical is important to consider.
If a product is virtual the customer will be charged immediately but if the product is physical you will have to capture the payment manually through the order in WooCommerce when you have shipped the product.

In most cases your products should be virtual when using subscriptions but it is possible to use the plugin with physical products if you need to do so.

== Screenshots ==

1. Setup and activate the payment gateway in WooCommerce
2. Configure the plugin settings

== Frequently Asked Questions ==

= Does this plugin work alongside the Vipps for WooCommerce plugin? =

Yes! You can use both plugins at the same time alongside each other.

= Do I need to have a license for WooCommerce Subscriptions in order to use this plugin? =

Yes, you do.

= Does this plugin work with the WooCommerce Memberships-plugin? =

WooCommerce Subscriptions and WooCommerce Memberships are able to work together for access to recurring memberships that unlock content.

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

This is because when an order is charged in Vipps it takes 6 days before the payment has been fully captured from the customer's bank account.

After 6 days it will move to the "Processing" status.

To counteract this you could look into using WooCommerce "Early renewals": [https://docs.woocommerce.com/document/subscriptions/early-renewal/](https://docs.woocommerce.com/document/subscriptions/early-renewal/)

== Changelog ==

= 1.2.0 =
* Enhancement: Added an admin options area for "Vipps Recurring Payments". From here you can force check the status of all pending Vipps subscription orders.
* Change: The cron jobs now run every minute instead of every five minutes. This means it now checks 5 orders every minute.
* Enhancement: Added page for when an order was cancelled as explained [here](https://github.com/vippsas/vipps-recurring-woocommerce/issues/6). You can configure where this goes in the plugin settings.

= 1.1.3 =
* Fix: Fixed a critical bug where physical purchases with discounts would be charged in full instead of the discounted price when manually captured.

= 1.1.2 =
* Fix: Creation of payments sometimes failed due to an invalid Idempotency key. No longer use special chars in this key.

= 1.1.1 =
* Fix: `wp_get_scheduled_event()` is not available in WP < 5.1, so we should not use this for earlier versions. Instead we now use `wp_get_schedule()` which has been available since WP 2.1.0.

= 1.1.0 =
* Fix: The action for woocommerce_thankyou should now be first in the pecking order.
* Enhancement: Added a custom cron schedule so we can check pending payments every 5 minutes. This is because we check only 5 at a time, and every hour would take too long. This ratio will continue to be tweaked as we go.
* Change: Renamed `WC_VIPPS_RECURRING_CHARGE_BEFORE_DUE_DAYS` to `WC_VIPPS_RECURRING_CHARGE_DUE_DAYS_PADDING` and clarify what it does. We do not actually want anyone to change this unless they know what they're doing.
* Enhancement: Add a useful product option for products that are both digital and physical that allows instantly capturing a payment.

= 1.0.9 =
* Fix: Fix manual captures. This is in many ways 1.0.8's lost brother.

= 1.0.8 =
* Fix: Fixed critical bug with renewals not working due to a specific meta key not being set on automatic renewal. Update ASAP.
