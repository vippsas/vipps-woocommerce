# Vipps Recurring Payments for WooCommerce

This plugin provides support for Vipps recurring payments for WooCommerce.

Document version: 2.0.2.

**We encourage you to create an issue here if you require assistance or run in to a problem.**

# Description

This is the official
[Vipps Recurring Payments](https://www.vipps.no/produkter-og-tjenester/bedrift/faste-betalinger/faste-betalinger/#kom-i-gang)
plugin for
[WooCommerce](https://woocommerce.com).
It is owned by [Vipps AS](https://vipps.no) and maintained by [Everyday AS](https://everyday.no).

Vipps Recurring Payments for WooCommerce is perfect for you if you run a web shop with subscription based services or other products that would benefit from subscriptions.

With Vipps Recurring Payments for WooCommerce you can:

* Sell recurring products (virtual and physical)
* Offer subscription services

See
[How it works](https://github.com/vippsas/vipps-recurring-api/blob/master/vipps-recurring-api-howitworks.md)
for an overview.

![The Vipps Recurring process](https://github.com/vippsas/vipps-recurring-api/blob/master/images/vipps-recurring-process.svg)

# Table of contents

* [ Requirements ](#requirements)
* [ Getting started ](#getting-started)
  * [ Installation ](#installation)
  * [ Retrieving Vipps API keys ](#retrieving-vipps-api-keys)
  * [ Configuration of the plugin ](#configuration-of-the-plugin)
  * [ Configuring products ](#configuring-products)
* [ Extending the plugin ](#extending-the-plugin)
  * [ Constants ](#constants)
  * [ Filters ](#filters)
* [ Frequently Asked Questions ](#frequently-asked-questions)

# Requirements

* WooCommerce 3.3.4 or newer
* [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
* PHP 7.0 or higher
* An SSL certificate must be installed and configured
* Port 443 must not be blocked for outgoing traffic

# Getting started

* Sign up to use ([Vipps på Nett](https://www.vipps.no/signup/vippspanett/))
* Vipps will review the application and perform KYC and AML controls. You may log onto [portal.vipps.no](https://portal.vipps.no) to see the status of your application. This is also where you can retrieve the API credentials used to configure the plugin in WooCommerce after your application has been approved.
* Proceed to "Installation" below

## Installation

1. Download and activate the plugin from this GitHub repository or [Vipps Recurring Payments for WooCommerce on wordpress.org](https://wordpress.org/plugins/vipps-recurring-payments-gateway-for-woocommerce/)
2. Enable the Vipps Recurring Payments ("Vipps faste betalinger") payment method in WooCommerce: `Settings` -> `Payments (Betalinger)`.
3. Click "Manage" on the Vipps Recurring Payments payment method
4. Proceed to "Retrieving Vipps API Keys" below

![Setup](https://raw.githubusercontent.com/vippsas/vipps-recurring-woocommerce/master/wp-org-assets/screenshot-1.png)

## Retrieving Vipps API Keys

The documentation for retrieving your Vipps API Keys can be found
[here](https://github.com/vippsas/vipps-developers/blob/master/vipps-getting-started.md#getting-the-api-keys).

## Configuration of the plugin

1. Fill in the `client_id`, `client_secret` and `Vipps-Subscription-Key` found in the previous step.
2. That's it! You can now move on to "Configuring products"

![Settings](https://raw.githubusercontent.com/vippsas/vipps-recurring-woocommerce/master/wp-org-assets/screenshot-2.png)

## Configuring products

Configuring products for use with the Vipps Recurring Payments plugin is not any
different from default WooCommerce, with one exception: The configuration for
whether the product is virtual or physical is important to consider.

If a product is virtual the customer will be charged immediately but if the
product is physical you will have to capture the payment manually through the
order in WooCommerce when you have shipped the product.

In most cases your products should be virtual when using subscriptions, but it is
possible to use the plugin with physical products if you need to do so.

See the
[Vipps Recurring FAQ](https://github.com/vippsas/vipps-recurring-api/blob/master/vipps-recurring-api-faq.md),
and the
[Vipps eCom FAQ](https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api-faq.md)
for more details:
[What is the difference between "Reserve Capture" and "Direct Capture"?](https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api-faq.md#what-is-the-difference-between-reserve-capture-and-direct-capture).

# Extending the plugin

WooCommerce and WooCommerce Subscriptions has a lot of [default actions](https://docs.woocommerce.com/document/subscriptions/develop/action-reference/) that interact with the payment flow so there should not be any need to extend this plugin directly,
but if you need an action or filter added to the plugin don't hesitate to create an issue on GitHub, and we will look into this as soon as possible.

The plugin is currently in a pre-release phase and will have more filters, actions and features further down the road.

## Constants

Constants can be re-defined by using `define('CONSTANT_NAME', 'value');` in `wp-config.php`.

`WC_VIPPS_RECURRING_RETRY_DAYS`: (integer) default: 3

The amount of days Vipps will retry a charge for before it fails. Documentation can be found [here](https://github.com/vippsas/vipps-recurring-api/blob/master/vipps-recurring-api.md#charge-retries).

`WC_VIPPS_RECURRING_TEST_MODE`: (boolean) default: false

Enables someone with access to Vipps developer keys to test the plugin. This is not available to regular users. See [#how-can-i-test-that-the-plugin-works-correctly](#how-can-i-test-that-the-plugin-works-correctly).

## Filters

Available filters:

`wc_vipps_recurring_supported_currencies(array $currencies)`

- Takes an array of supported currencies in ISO 4217 format (like NOK). Vipps only supports NOK at the moment.

`wc_vipps_recurring_payment_icons(array $icons)`

- Takes an array of icons that a WooCommerce payment gateway can have. Currently it only contains `vipps`, you can replace the image passed here if you want. It is however not recommended unless it follows Vipps' design specifications.

`wc_vipps_recurring_show_capture_button(bool $show_capture_button, WC_Order $order)`

- Decides whether the direct capture button shall be displayed on an order or not. Prior to version 1.2.1 this filter was called `woocommerce_vipps_recurring_show_capture_button`. `$show_capture_button` contains the current decision on whether or not it shall be displayed. `$order` contains the current `WC_Order` being viewed.

`wc_vipps_recurring_merchant_agreement_url(string $url)`

`wc_vipps_recurring_merchant_redirect_url(string $url)`

`wc_vipps_recurring_transaction_id_for_order(string $transaction_id, WC_Order $order)`

- Determines the return value of `WC_Vipps_Recurring_Helper::get_transaction_id_for_order`

# Frequently Asked Questions

## How can I get help?

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

## Does this plugin work alongside the Vipps for WooCommerce plugin?

Yes! You can use this plugin at the same time as
[Vipps for WooCommerce](https://github.com/vippsas/vipps-woocommerce).

## Do I need to have a license for WooCommerce Subscriptions in order to use this plugin?

Yes, you do. Get it
[here](https://woocommerce.com/products/woocommerce-subscriptions/).

## Does this plugin work with the WooCommerce Memberships-plugin?

[WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
and
[WooCommerce Memberships](https://woocommerce.com/products/woocommerce-memberships/)
are able to work together for access to recurring memberships that unlock content.

**WooCommerce Subscriptions is required in order to use Vipps Recurring Payments for WooCommerce, but Memberships is not.**

You can read about how WooCommerce Subscriptions and WooCommerce Memberships work together [here](https://docs.woocommerce.com/document/woocommerce-memberships-subscriptions-integration/).

## Where can I use Vipps?

Vipps is only available in Norway at the moment and only users who have Vipps will be able to pay with Vipps.

## How can I test that the plugin works correctly?

If you have access to the Vipps test environment you are able to use the test mode by setting the `WC_VIPPS_RECURRING_TEST_MODE` constant in `wp-config.php`.
See the [getting started](https://github.com/vippsas/vipps-developers/blob/master/vipps-getting-started.md) guide for details about how to get started using the test environment.

Vipps does not offer test accounts for regular users of the plugin but you can still penny-test the plugin by sending a small amount of money like 1 or 2 NOK using your production keys.
You can then refund or cancel the purchase afterwards.

## Why do I have to capture payments for physical products manually?

This is because of the Norwegian law. You are not allowed to charge for a physical product before you ship it, without a valid reason to do so.

You can read about it [here](https://www.forbrukertilsynet.no/english/guidelines/guidelines-the-standard-sales-conditions-consumer-purchases-of-goods-the-internet#chapter-7).

If you have a valid reason to do so you can use the "Capture payment instantly" option from the "Vipps Recurring Payments" settings in your product's settings.

## When a renewal happens, why is the order on hold?

This is because when an order is charged in Vipps it takes 2 days before the payment has been fully captured from the customer's bank account.

After 2 days it will move to the "Processing" status. You can however change the behaviour of this by using the "Default status to give pending renewals" option in the plugin settings.

Alternatively you could look into using WooCommerce "Early renewals": [https://docs.woocommerce.com/document/subscriptions/early-renewal/](https://docs.woocommerce.com/document/subscriptions/early-renewal/) if ensuring the status of a charge is fully completed before a specific date is of up-most importance.
