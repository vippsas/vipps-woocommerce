# Vipps for WooCommerce

<!-- START_COMMENT -->
💥 Please use the plugin pages on [https://developer.vippsmobilepay.com](https://developer.vippsmobilepay.com/docs/vipps-plugins/). 💥
<!-- END_COMMENT -->


This repo contains *Checkout with Vipps for WooCommerce*. For detailed information about this product, please see
[Pay with Vipps for WooCommerce](https://wordpress.org/plugins/woo-vipps/).

The Vipps product page is [Vipps WoCommerce plugins](https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/).
To contact Vipps, see the [contact us](https://developer.vippsmobilepay.com/docs/vipps-developers/contact/) page.

This plugin is also hosted on [GitHub](https://github.com/vippsas/vipps-woocommerce).

## Description

*Official Vipps Express Checkout and Payment for WooCommerce. More than 3.9 million Norwegians use Vipps. Give them a fast and familiar shopping experience.*

This is the official Vipps plugin that provides a direct integration with the Vipps backend. Now you can let your customers choose Vipps directly in the checkout or even do an express checkout from the cart.

You can also do important back office tasks such as capture and refund directly from WooCommerce. Easy for your customer and easy for you.

Read [information from Vipps](https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/) about the plugin.

## Vipps Express Checkout

When you enable Express Checkout, your customers can choose between the regular checkout or to go directly to Vipps. If they choose Vipps, they just submit their phone number, and the rest of the checkout is done in the Vipps app.

Since Vipps knows who the customers are, they don't have to enter all their personal information. The customer just choose the shipping method and accepts the payment. Vipps will send all the necessary info back to the store. Easy, fast and secure.

## Vipps Payment

When you enable this plugin, your customers will be able to choose Vipps as a payment method directly in the checkout. There is no need to go via a third party payment method. If your customer choose Vipps, she fills in her name and address and is then asked to enter her phone number in the Vipps dialogue. Then she confirms the payment in the Vipps app.

## How to get started

* Sign up to use Vipps på Nett ([vipps.no/woocommerce](https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/))
* After 1-2 days you will get an email with login details to Vipps Developer Portal, where you can get the API credentials
* Download and install the plugin
* Configure the plugin

## Installation

1. Install the plugin using WordPress’ [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the WooCommerce Settings page and choose Payment Gateways (Betalinger) and enable Vipps.
4. Go the settings page for the Vipps plugin and enter your Vipps account keys. Your account keys are available in the Vipps Developer Portal (detailed info in the section below)

## How to get Vipps account keys from Vipps Developer Portal

1. Sign in to the Vipps Portal at [https://portal.vipps.no/](https://portal.vipps.no/) using Bank ID
2. Select the "Utvikler" ("Developer") tab and choose Production Keys. Here you can find the merchant serial number (6 figures)
3. Click on "Show keys" under the API keys column to see “Client ID”, “Client Secret” and “Vipps Subscription Key”

For more information, see:
* [Getting Started](https://developer.vippsmobilepay.com/docs/vipps-developers/vipps-getting-started)
* [API Keys](https://developer.vippsmobilepay.com/docs/vipps-developers/common-topics/api-keys)
* [eCom FAQ](https://developer.vippsmobilepay.com/docs/APIs/ecom-api/vipps-ecom-api-faq)

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

You will need a "Vipps på nett" account for direct integration with the Vipps
eCom API v2, which can be ordered
[here](https://vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/).
TestFlight
If you already have a Vipps account for WooCommerce, and want to contribute to
the development of the plugin, you can contact
[customer service](https://www.vipps.no/kontakt-oss/)
to "upgrade" your account with access to the test environment.

You will also need to install a special test version of the Vipps app, available
through TestFlight. See
[Vipps test apps](https://developer.vippsmobilepay.com/docs/vipps-developers/test-environment/#vipps-test-apps)
for more information.

API keys for both the test and production environment are available on
[portal.vipps.no](https://portal.vipps.no), where you log in with BankID.
See [Getting the API keys](https://developer.vippsmobilepay.com/docs/vipps-developers/vipps-getting-started#getting-the-api-keys)
in the
[Getting started](https://developer.vippsmobilepay.com/docs/vipps-developers/vipps-getting-started)
guide for more information.

To use test mode in WooCommerce, switch "Developer mode" on. There you can input
the API keys for the test environment, and turn test mode on and off.

If you have defined the constant `VIPPS_TEST_MODE` to true, test mode will be forced on.

If this isn't practical for your usage, we recommend that you "test in production"
with a small amount, like 2 NOK. Just refund or cancel the purchase as needed.

### How can I get help if I have any issues?

For issues with your WooCommerce installation you should use the [support forum on wordpress.org](https://wordpress.org/support/plugin/woo-vipps). For other issues you should [contact Vipps](https://developer.vippsmobilepay.com/docs/vipps-developers/contact).

### Vipps FAQ

See the
[Vipps FAQ](https://developer.vippsmobilepay.com/docs/vipps-developers/faqs/)
for more help with Vipps eCommerce.

## Requirements

* WooCommerce 3.3.4 or newer is required
* PHP 5.6 or higher is required.
* An SSL Certificate is required.
* The port 443 must not be blocked for outward traffic
