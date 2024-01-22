<!-- START_METADATA
---
title: Vipps for WooCommerce plugin
sidebar_position: 1
pagination_next: null
pagination_prev: null
---
END_METADATA -->

# Vipps for WooCommerce

![Support and development by WP Hosting ](./docs/images/wphosting.svg#gh-light-mode-only)![Support and development by WP Hosting](./docs/images/wphosting_dark.svg#gh-dark-mode-only)

![Vipps](./docs/images/vipps.png) *Available for Vipps.*

![MobilePay](./docs/images/mp.png) *Available for MobilePay in Finland and Denmark in Q1 2024.*

*This plugin is built and maintained by [WP Hosting](https://www.wp-hosting.no/) and can be downloaded from the [Wordpress plugin site](https://wordpress.org/plugins/woo-vipps/) .*

<!-- START_COMMENT -->
💥 Please use the plugin pages on [https://developer.vippsmobilepay.com](https://developer.vippsmobilepay.com/docs/plugins-ext/woocommerce/). 💥
<!-- END_COMMENT -->

*Official Vipps Express Checkout and Payment for WooCommerce. More than 3.9 million Norwegians use Vipps. Give them a fast and familiar shopping experience.*

This is the official Vipps plugin that provides a direct integration with the Vipps backend. Now you can let your customers choose Vipps directly in the checkout or even do an express checkout from the cart.

You can also do important back office tasks such as capture and refund directly from WooCommerce. Easy for your customer and easy for you.

For more information, see:

* [How Checkout works for WooCommerce](https://developer.vippsmobilepay.com/docs/APIs/checkout-api/checkout-how-it-works-woocommerce/)
* [Vipps.no: We have these WooCommerce plugins available](https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/)
* [Pay with Vipps for WooCommerce](https://wordpress.org/plugins/woo-vipps/)

## Vipps Express Checkout

When you enable Express Checkout, your customers can choose between the regular checkout or to go directly to Vipps. If they choose Vipps, they just submit their phone number, and the rest of the checkout is done in the Vipps app.

Since Vipps knows who the customers are, they don't have to enter all their personal information. The customer just choose the shipping method and accepts the payment. Vipps will send all the necessary info back to the store. Easy, fast and secure.

## Vipps Payment

When you enable this plugin, your customers will be able to choose Vipps as a payment method directly in the checkout. There is no need to go via a third party payment method. If your customer choose Vipps, she fills in her name and address and is then asked to enter her phone number in the Vipps dialogue. Then she confirms the payment in the Vipps app.

## How to get started

* Sign up to use [*Vipps på Nett*](https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/).
* After 1-2 days, you will get an email with login details to [portal.vippsmobilepay.com](https://portal.vippsmobilepay.com/), where you can get the API credentials.
* Download and install the plugin.
* Configure the plugin.

## Installation

1. Install the plugin using the WordPress [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins).
   The plugin can also be installed manually by uploading the plugin files to the `/wp-content/plugins/` directory.
2. Activate the plugin through the *Plugins* screen on WordPress.
3. Go to the *WooCommerce Settings* page, choose *Payment Gateways* (*Betalinger*), and enable Vipps.
4. Go the *Settings* page for the Vipps plugin and enter your Vipps account keys. Your account keys are available in the Vipps Merchant Portal. For information, see [How to get account keys from Merchant Portal](#how-to-get-account-keys-from-merchant-portal).

## How to get account keys from Merchant Portal

1. Sign in to [portal.vippsmobilepay.com](https://portal.vippsmobilepay.com/).
2. In the *Developer* section, choose *Production Keys*. Here you can find the merchant serial number (6 figures).
3. Click on *Show keys* under the API keys column to see *Client ID*, *Client Secret* and *Vipps Subscription Key*.

See:

* [Logging in to the portal](https://developer.vippsmobilepay.com/docs/developer-resources/portal#logging-in)
* [How to find the API keys](https://developer.vippsmobilepay.com/docs/developer-resources/portal#how-to-find-the-api-keys).

## Screenshots

Enable Vipps as a payment method
![Enable Vipps as a payment method](https://raw.github.com/vippsas/vipps-woocommerce/master/wp-org-assets/screenshot-1.png?raw=true "Enable Vipps as a payment method.")

Enter your Vipps account keys and configure the plugin
![Enter your Vipps account keys and configure the plugin](https://raw.github.com/vippsas/vipps-woocommerce/master/wp-org-assets/screenshot-2.png?raw=true "Enter your Vipps account keys and configure the plugin")

## Frequently Asked Questions

### In which countries can I use Vipps?

You can only get paid by users who have Vipps. At the moment Vipps is only available in Norway.

### Does Vipps offer a test environment for Vipps for WooCommerce?

Yes, for developers that want to contribute to the development of the plugin.
Vipps does not offer a test account for normal users of the plugin.

You will need a *Vipps på Nett* account for direct integration with the eCom API, which can be ordered from
[vipps.no](https://vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/).

If you already have a Vipps account for WooCommerce and want to contribute to
the development of the plugin, contact
[customer service](https://vippsmobilepay.com/help)
to *upgrade* your account with access to the test environment.

You will also need to install a special test version of the Vipps app, available
through TestFlight. See
[Test apps](https://developer.vippsmobilepay.com/docs/test-environment/#test-apps)
for more information.

API keys for both the test and production environment are available on
[portal.vippsmobilepay.com](https://portal.vippsmobilepay.com), where you log in.
See [Getting the API keys](https://developer.vippsmobilepay.com/docs/developer-resources/portal/#how-to-find-the-api-keys)
for more information.

To use test mode in WooCommerce, switch *Developer mode* on. There you can input
the API keys for the test environment, and turn test mode on and off.

If you have defined the constant `VIPPS_TEST_MODE` to true, test mode will be forced on.

If this isn't practical for your usage, we recommend that you *test in production*
with a small amount, like 2 NOK. Just refund or cancel the purchase as needed.

### How can I get help if I have any issues?

For issues with your WooCommerce installation you should use the
[support forum on wordpress.org](https://wordpress.org/support/plugin/woo-vipps).
For other issues you should [contact Vipps](https://developer.vippsmobilepay.com/docs/contact).

### Vipps FAQ

See the
[Vipps Knowledge base](https://developer.vippsmobilepay.com/docs/knowledge-base/)
for more help with Vipps eCommerce.

## Requirements

* WooCommerce 3.3.4 or newer is required
* PHP 5.6 or higher is required.
* An SSL Certificate is required.
* The port 443 must not be blocked for outward traffic
