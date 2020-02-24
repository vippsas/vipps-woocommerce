=== Vipps Recurring Payments for WooCommerce ===
Contributors: Vipps, Everyday
Donate link:
Tags: vipps, recurring payments, subscriptions, woocommerce
Requires at least: 5.0.0
Tested up to: 5.3.2
Stable tag: trunk
Requires PHP: 7.1
License: AGPLv3.0 or later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

Get paid through recurring payments with Vipps and WooCommerce.

== Vipps Recurring Payments for WooCommerce ==

**This plugin is currently a test pilot (pre-release). It is very likely that you will encounter bugs or scenarios that are not yet supported.**

**We encourage you to create an issue here or on the WordPress plugin page if you require assistance or run in to a problem.**

For Vipps contact information check the main Vipps GitHub page: [https://github.com/vippsas](https://github.com/vippsas)

== Description ==

This is the official Vipps Recurring Payments plugin for WooCommerce. It is owned by Vipps AS and maintained by Everyday AS.

Vipps Recurring Payments for WooCommerce is perfect for you if you run a web shop with subscription based services or other products that would benefit from subscriptions.

With Vipps Recurring Payments for WooCommerce you can:

* Sell recurring products (virtual and physical)
* Offer subscription services

== Requirements ==

* WooCommerce 3.3.4 or newer
* [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
* PHP 7.1 or higher
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

== Retrieving Vipps API Keys ==

The documentation for retrieving your Vipps API Keys can be found [here](https://github.com/vippsas/vipps-developers/blob/master/vipps-getting-started.md#getting-the-api-keys).

== Configuration of the plugin ==

1. Fill in the `Client ID`, `Secret Key` and `Subscription Key` found in the previous step. You should fill the fields with the prefix "Live" if you are using the production keys.
2. That's it! You can now move on to "Configuring products"

== Screenshots ==

1. Setup and activate the payment gateway in WooCommerce
2. Configure the plugin settings

== Frequently Asked Questions ==

= Does this plugin work alongside the Vipps for WooCommerce plugin? =

Yes! You can use both plugins at the same time alongside each other.

= Do I need to have a license for WooCommerce Subscriptions in order to use this plugin? =

Yes, you do.

= Does this plugin work with the WooCommerce Memberships-plugin? =

No, it's for WooCommerce Subscriptions only.

You can however use both WooCommerce Subscriptions and WooCommerce Memberships at the same time as explained [here](https://docs.woocommerce.com/document/woocommerce-memberships-subscriptions-integration/).

= How can I get help if I have any issues? =

For issues with the plugin you can submit an issue on GitHub or ask on the support forum on wordpress.org. For other unrelated issues you should [contact Vipps](https://github.com/vippsas/vipps-developers/blob/master/contact.md).

= Where can I use Vipps? =

Vipps is only available in Norway at the moment and only users who have Vipps will be able to pay with Vipps.

= How can I test that the plugin works correctly? =

If you have access to the Vipps test environment you are able to use the test mode found in the plugin settings.
See the [getting started](https://github.com/vippsas/vipps-developers/blob/master/vipps-getting-started.md) guide for details about how to get started using the test environment.

Vipps does not offer test accounts for regular users of the plugin but you can still penny-test the plugin by sending a small amount of money like 1 or 2 NOK using your production keys.
You can then refund or cancel the purchase afterwards.

== Changelog ==

1.0.2 - Initial WordPress plugin repository release
