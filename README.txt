=== Vipps Recurring Payments for WooCommerce ===
Contributors: Vipps, EverydayAS
Tags: vipps, recurring payments, subscriptions, woocommerce, woocommerce subscriptions
Requires at least: 5.0.0
Tested up to: 5.4.1
Stable tag: trunk
Requires PHP: 7.0
License: AGPLv3.0 or later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

== Description ==

**This plugin is currently a test pilot (pre-release). It is very likely that you will encounter bugs or scenarios that are not yet supported.**

**Please keep up to date with updates as soon as they happen. They are very important in this pre-release period.**

**We encourage you to create an issue here or on the [GitHub page](https://github.com/vippsas/vipps-recurring-woocommerce/issues) if you require assistance or run in to a problem.**

This plugin provides support for Vipps recurring payments for WooCommerce.

This is the official Vipps Recurring Payments plugin for WooCommerce. It is owned by [Vipps AS](https://vipps.no) and maintained by [Everyday AS](https://everyday.no).

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

* Sign up to use ([Vipps på Nett](https://www.vipps.no/signup/vippspanett/))
* After 1-2 days you will get an email with login details to Vipps Developer Portal. This is where you can retrieve the API credentials used to configure the plugin in WooCommerce.
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

Alternatively you you could look into using WooCommerce "Early renewals": [https://docs.woocommerce.com/document/subscriptions/early-renewal/](https://docs.woocommerce.com/document/subscriptions/early-renewal/) if ensuring the status of a charge is fully completed before a specific date is of up-most importance.

== Changelog ==

= 1.3.9 =
* Enhancement: Error handling and logging has been completely redone in order to more easily debug future errors.

= 1.3.8 =
* Fix: Solved an issue where it would attempt to swap gateway even though it had already finished doing so. This time it would throw an internal server error because it's passing a blank Agreement ID to the Vipps API.
* Fix: No longer use the `WC_VERSION` constant in a way that would throw an error if WooCommerce is missing (It shouldn't ever be missing, but just in case)

= 1.3.7 =
* Enhancement: Re-throw WP_Error if it somehow ends up in Api->handle_http_response so we can see why something failed.

= 1.3.6 =
* Enhancement: Reduced the amount of superfluous order notes.
* Fix: Increased atomicity for purchases that are not yet done. Due to a bug with the previously added feature in 1.3.2 about selection of default order status, the order would move to that status before it's considered done. This has been fixed.

= 1.3.5 =
* Fix: Properly deal with cancelled payment gateway change requests in Vipps, do not change gateway if the user cancelled.
* Enhancement: Use the PATCH method for cancellation of agreements in the Vipps API instead of PUT.
