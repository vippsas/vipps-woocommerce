<!-- START_METADATA
---
title: Vipps/MobilePay recurring payments
sidebar_position: 1
description: Provide Vipps recurring payments for WooCommerce.
pagination_next: null
pagination_prev: null
---
END_METADATA -->

# Vipps/MobilePay recurring payments for WooCommerce

![Support and development by Everyday ](./docs/images/everyday.svg#gh-light-mode-only)![Support and development by Everyday](./docs/images/everyday_dark.svg#gh-dark-mode-only)

![Vipps](./docs/images/vipps.png) ![MobilePay](./docs/images/mp.png) *Available for Vipps and MobilePay.*

*This plugin is built and maintained by [Everyday AS](https://everyday.no)
and can be downloaded from the [WordPress plugin site](https://wordpress.org/plugins/vipps-recurring-payments-gateway-for-woocommerce/).
For support, use the [support forum on wordpress.org](https://wordpress.org/support/plugin/vipps-recurring-payments-gateway-for-woocommerce/).*

<!-- START_COMMENT -->
💥 Please use the plugin pages on [https://developer.vippsmobilepay.com](https://developer.vippsmobilepay.com/docs/plugins-ext/recurring-woocommerce/). 💥
<!-- END_COMMENT -->

This is the official
[Vipps/MobilePay recurring payment](https://vippsmobilepay.com/no/online/billing-recurring#kom-i-gang)
plugin for
[WooCommerce](https://woocommerce.com).
It is owned by [Vipps MobilePay](https://vippsmobilepay.com) and maintained by [Everyday AS](https://everyday.no).

Vipps/MobilePay recurring payments is perfect for you if you run a web shop with subscription based services or other products that would benefit from subscriptions.

With Vipps/MobilePay recurring payments you can:

* Sell recurring products (virtual and physical)
* Offer subscription services

See
[How it works](https://developer.vippsmobilepay.com/docs/APIs/recurring-api/how-it-works/recurring-api-howitworks/)
for an overview.

<!-- START_COMMENT -->
## Table of contents

* [Requirements](#requirements)
* [Getting started](#getting-started)
  * [Installation](#installation)
  * [Retrieving Vipps/MobilePay API keys](#retrieving-vipps-mobilepay-api-keys)
  * [Configuration of the plugin](#configuration-of-the-plugin)
  * [Configuring products](#configuring-products)
* [Extending the plugin](#extending-the-plugin)
  * [Constants](#constants)
  * [Filters](#filters)
* [Frequently Asked Questions](#frequently-asked-questions)
<!-- END_COMMENT -->

## Requirements

* WooCommerce 3.3.4 or newer
* [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
* PHP 7.4 or higher
* An SSL certificate must be installed and configured
* Port 443 must not be blocked for outgoing traffic

## Getting started

* Sign up for a merchant agreement and product order from Vipps MobilePay merchant portal.
* After 1-2 days, you will get an email with login details to [portal.vippsmobilepay.com](https://portal.vippsmobilepay.com), where you can get the API credentials.
* Download and install the plugin.
* Configure the plugin.

### Installation

1. Download and activate the plugin from this GitHub repository or [Vipps/MobilePay recurring payments on wordpress.org](https://wordpress.org/plugins/vipps-recurring-payments-gateway-for-woocommerce/)
2. Enable the *Vipps/MobilePay recurring payment* payment method in WooCommerce: *Settings* > *Payments*.
3. Click *Manage* on the payment method.
4. Proceed to [Retrieving Vipps MobilePay API Keys](#retrieving-vipps-mobilepay-api-keys).

![Setup](https://raw.githubusercontent.com/vippsas/vipps-recurring-woocommerce/master/.wordpress-org/screenshot-1.png)

### Retrieving Vipps MobilePay API Keys

Log-in to [portal.vippsmobilepay.com/](https://portal.vippsmobilepay.com/) and get your test API keys, as described in
[API keys](https://developer.vippsmobilepay.com/docs/knowledge-base/api-keys/).

### Configuration of the plugin

1. Fill in the `client_id`, `client_secret` and `Ocp-Apim-Subscription-Key` found in the previous step.
2. That's it! You can now move on to [Configuring products](#configuring-products).

![Settings](https://raw.githubusercontent.com/vippsas/vipps-recurring-woocommerce/master/.wordpress-org/screenshot-2.png)

### Configuring products

Configuring products for use with the *Vipps/MobilePay recurring payment* plugin is not any
different from default WooCommerce, with one exception: The configuration for
whether the product is virtual or physical is important to consider.

If a product is virtual the customer will be charged immediately but if the
product is physical you will have to capture the payment manually through the
order in WooCommerce when you have shipped the product.

In most cases your products should be virtual when using subscriptions, but it is
possible to use the plugin with physical products if you need to do so.

See the
[Vipps/MobilePay Knowledge base](https://developer.vippsmobilepay.com/docs/knowledge-base/)
for more details:
[What is the difference between "Reserve Capture" and "Direct Capture"?](https://developer.vippsmobilepay.com/docs/knowledge-base/reserve-and-capture/#what-is-the-difference-between-reserve-capture-and-direct-capture).

## Extending the plugin

WooCommerce and WooCommerce Subscriptions has a lot of [default actions](https://docs.woocommerce.com/document/subscriptions/develop/action-reference/) that interact with the payment flow, so there should not be any need to extend this plugin directly,
but if you need an action or filter added to the plugin don't hesitate to create an issue on GitHub, and we will look into this as soon as possible.

The plugin is currently in a pre-release phase and will have more filters, actions and features further down the road.

### Constants

Constants can be re-defined by using `define('CONSTANT_NAME', 'value');` in `wp-config.php`.

`WC_VIPPS_RECURRING_RETRY_DAYS`: (integer) default: 4

The amount of days Vipps/MobilePay will retry a charge for before it fails.
See [Charge retries](https://developer.vippsmobilepay.com/docs/APIs/recurring-api/recurring-api-guide/#charge-retries) for more information.

`WC_VIPPS_RECURRING_TEST_MODE`: (boolean) default: false

Enables someone with access to Vipps/MobilePay developer keys to test the plugin. This is not available to regular users. See [#how-can-i-test-that-the-plugin-works-correctly](#how-can-i-test-that-the-plugin-works-correctly).

## Actions

See the code for an up-to-date list of available actions. Search for `do_action`.

### Filters

See the code for an up-to-date list of available filters. Search for `apply_filters`.

Some filters include:

`wc_vipps_recurring_supported_currencies(array $currencies)` - Takes an array of supported currencies in ISO 4217 format (like NOK).

`wc_vipps_recurring_payment_icons(array $icons)` - Takes an array of icons that a WooCommerce payment gateway can have. Currently, it only contains `vipps`, you can replace the image passed here if you want. It is however not recommended unless it follows Vipps MobilePay's design specifications.

`wc_vipps_recurring_show_capture_button(bool $show_capture_button, WC_Order $order)` - Decides whether the direct capture button shall be displayed on an order or not. Prior to version 1.2.1 this filter was called `woocommerce_vipps_recurring_show_capture_button`. `$show_capture_button` contains the current value for displaying the capture button or not. `$order` contains the current `WC_Order` being viewed.

`wc_vipps_recurring_merchant_agreement_url(string $url)` - Allows you to modify the merchant agreement URL.

`wc_vipps_recurring_merchant_redirect_url(string $url)` - Allows you to modify the merchant redirect URL.

`wc_vipps_recurring_transaction_id_for_order(string $transaction_id, WC_Order $order)` - Determines the return value of `WC_Vipps_Recurring_Helper::get_transaction_id_for_order`

`wc_vipps_recurring_create_agreement_data(array $data)` - Allows you to alter the request body sent to the Vipps MobilePay API when a new agreement is being created.

`wc_vipps_recurring_update_agreement_data(array $data)` - Allows you to alter the request body sent to the Vipps MobilePay API when an agreement is being updated.

`wc_vipps_recurring_cancel_agreement_data(array $data)` - Allows you to alter the request body sent to the Vipps MobilePay API when an agreement is cancelled.

`wc_vipps_recurring_create_charge_data(array $data)` - Allows you to alter the request body sent to the Vipps MobilePay API when a new charge is being created.

`wc_vipps_recurring_process_payment_agreement(WC_Vipps_Agreement $agreement, WC_Subscription $subscription, WC_Order $order)` - Allows you to modify the Vipps MobilePay agreement before we send the request to the Vipps MobilePay API. Includes subscription and order in case you need to make some custom logic.

## Frequently Asked Questions

### How can I get help?

If your question is not answered on this page:

* For help with the plugin, use the [support forum on wordpress.org](https://wordpress.org/support/plugin/vipps-recurring-payments-gateway-for-woocommerce/) or [submit an issue](https://github.com/vippsas/vipps-recurring-woocommerce/issues) on GitHub.

* The
  [Vipps MobilePay Knowledge base](https://developer.vippsmobilepay.com/docs/knowledge-base/)
  may also be useful.

### Does this plugin work alongside the "Pay with Vipps and MobilePay for WooCommerce" plugin?

Yes! You can use this plugin at the same time as
[Pay with Vipps and MobilePay for WooCommerce](https://developer.vippsmobilepay.com/docs/plugins-ext/woocommerce/).

### Do I need to have a license for WooCommerce Subscriptions in order to use this plugin?

Yes, you need a [WooCommerce Subscriptions license](https://woocommerce.com/products/woocommerce-subscriptions/) to use this plugin.

### Does this plugin work with the WooCommerce Memberships-plugin?

[WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
and
[WooCommerce Memberships](https://woocommerce.com/products/woocommerce-memberships/)
are able to work together for access to recurring memberships that unlock content.

**Please note:** *WooCommerce Subscriptions* is required in order to use *Vipps/MobilePay recurring payments*, but *WooCommerce Memberships* is not.

You can read about how *WooCommerce Subscriptions* and *WooCommerce Memberships* work together at
[WooCommerce Memberships Subscriptions Integration](https://docs.woocommerce.com/document/woocommerce-memberships-subscriptions-integration/).

### Where can I use Vipps MobilePay?

Vipps MobilePay is available in Norway, Denmark and Finland. Only users who have Vipps MobilePay will be able to pay with Vipps MobilePay.

### How can I test that the plugin works correctly?

If you have access to the Vipps MobilePay test environment, you are able to use the test mode by setting the `WC_VIPPS_RECURRING_TEST_MODE` constant in `wp-config.php`.
See the [getting started](https://developer.vippsmobilepay.com/docs/) guide for details about how to get started using the test environment.

Vipps MobilePay does not offer test accounts for regular users of the plugin, but you can still penny-test the plugin by sending a small amount of money like 1 or 2 NOK using your production keys.
You can then refund or cancel the purchase afterward.

### Why do I have to capture payments for physical products manually?

This is because of the Norwegian law. You are not allowed to charge for a physical product before you ship it, without a valid reason to do so.

See
[Guidelines for the standard sales conditions for consumer purchases of goods over the internet](https://www.forbrukertilsynet.no/english/guidelines/guidelines-the-standard-sales-conditions-consumer-purchases-of-goods-the-internet#chapter-7) for more information.

If you have a valid reason to do so you can use the *Capture payment instantly* option from the *Vipps/MobilePay recurring payments* settings in your product's settings.

### When a renewal happens, why is the order on hold?

This is because when an order is charged in Vipps MobilePay, it takes 2 days before the payment has been fully captured from the customer's bank account.

After 2 days, it will move to the "Processing" status. You can however change the behavior of this by using the "Default status to give pending renewals" option in the plugin settings.

Alternatively, you could look into using WooCommerce "Early renewals": [https://docs.woocommerce.com/document/subscriptions/early-renewal/](https://docs.woocommerce.com/document/subscriptions/early-renewal/) if ensuring the status of a charge is fully completed before a specific date is of utmost importance.


### How do I activate recurring payments in Vipps Checkout on WooCommerce?

1. If you don't have a customer relationship with Vipps, you need to register as a Vipps MobilePay merchant first. Follow the [Getting Started guide](https://developer.vippsmobilepay.com/docs/getting-started/).
2. Download our [recurring plugin](https://wordpress.org/plugins/vipps-recurring-payments-gateway-for-woocommerce/).
3. Activate the plugin

### Can I use this plugin for single payments as well?

If you offer both recurring and single payments, we recommend using our Recurring checkout plugin alongside our [standard checkout plugin](https://developer.vippsmobilepay.com/docs/plugins-ext/woocommerce/).

The Recurring checkout plugin will allow you to process single payments and recurring payments in the same checkout, but you still need our standard checkout plugin to process single payments when no recurring product is present in your shopping cart.

### What shipping options are available for this checkout?

For recurring payments, we only offer static shipping options at the moment. We do not support location based dynamic shipping, yet.

### What payments methods are available for this checkout?

At the moment, it only supports Vipps MobilePay.

## Support

For issues with your plugin, use the [support forum on wordpress.org](https://wordpress.org/support/plugin/vipps-recurring-payments-gateway-for-woocommerce/) or [submit an issue](https://github.com/vippsas/vipps-recurring-woocommerce/issues) on GitHub.
