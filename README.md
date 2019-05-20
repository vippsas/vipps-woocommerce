# Vipps for WooCommerce

This repo contains "Checkout with Vipps for WooCommerce". For more information about this product, please see: https://wordpress.org/plugins/woo-vipps/

The Vipps product page is here: https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/

See the main GitHub page for Vipps contact information, etc: https://github.com/vippsas

# Description

*Official Vipps Express Checkout and Payment for WooCommerce. More than 3 millon Norwegians use Vipps. Give them a fast and familiar shopping experience.*

This is the official Vipps plugin that provides a direct integration with the Vipps backend. Now you can let your customers choose Vipps directly in the checkout or even do an express checkout from the cart.

You can also do important back office tasks such as capture and refund directly from WooCommerce. Easy for your customer and easy for you.

Read [information from Vipps](https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/) about the plugin.

# Vipps Express Checkout

When you enable Express Checkout, your customers can choose between the regular checkout or to go directly to Vipps. If they choose Vipps, they just submit their phone number, and the rest of the checkout is done in the Vipps app.

Since Vipps knows who the customers are, they don't have to enter all their personal information. The customer just choose the shipping method and accepts the payment. Vipps will send all the necessary info back to the store. Easy, fast and secure.

# Vipps Payment

When you enable this plugin, your customers will be able to choose Vipps as a payment method directly in the checkout. There is no need to go via a third party payment method. If your customer choose Vipps, she fills in her name and address and is then asked to enter her phone number in the Vipps dialougue. Then she confirms the payment in the Vipps app.

# How to get started
* Sign up to use Vipps på Nett ([vipps.no/woocommerce](https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/))
* After 1-2 days you will get an email with login details to Vipps Developer Portal, where you can get the API credentials
* Download and install the plugin
* Configure the plugin

# Installation
1.  Install the plugin using WordPress’ [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the WooCommerce Settings page and choose Payment Gateways (Betalinger) and enable Vipps.
4. Go the settings page for the Vipps plugin and enter your Vipps account keys. Your account keys are available in the Vipps Developer Portal (detailed info in the section below)

# How to get Vipps account keys from Vipps Developer Portal
1. Sign in to Vipps Developer Portal at [https://api-portal.vipps.no/](https://api-portal.vipps.no/)
   - Username is sent via email
   - Password is sent via SMS
2. Select the "Applications" tab. Here you can find the merchant/saleunit serial number (6 figures)
3. Click on "View Secret" to see “Client ID” and “Client Secret”
4. Click on customer name (top-right corner) and select "Profile" to see “Default accesstoken” and “Ecommerce API” (click on “Show” to see the primary key)

_Please note that you should use the *production* environment (api-portal.vipps.no) for WooCommerce, not the *test* environment (apitest-portal.vipps.no)._

See: [Getting Started](https://github.com/vippsas/vipps-developers/blob/master/vipps-developer-portal-getting-started.md) with the Vipps Developer Portal, and the Vipps eCommerce [FAQ](https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api-faq.md).

# Screenshots
Enable Vipps as a payment method
![Enable Vipps as a payment method](https://raw.github.com/vippsas/vipps-woocommerce/master/wp-org-assets/screenshot-1.png?raw=true "Enable Vipps as a payment method.")

Enter your Vipps account keys and configure the plugin
![Enter your Vipps account keys and configure the plugin](https://raw.github.com/vippsas/vipps-woocommerce/master/wp-org-assets/screenshot-2.png?raw=true "Enter your Vipps account keys and configure the plugin")

# Frequently Asked Questions

## In which countries can I use Vipps?

You can only get paid by users who have Vipps. At the moment Vipps is only available in Norway.

## Does Vipps offer a test environment for Vipps for WooCommerce?

No. We recommend that you "test in production" with a small amount, like 2 NOK.

## How can I get help if I have any issues?

For issues with your WooCommerce installation you should use the [support forum on wordpress.org](https://wordpress.org/support/plugin/woo-vipps). For other issues you should [contact Vipps](https://github.com/vippsas/vipps-developers/blob/master/contact.md).

# What are the requirements?
* WooCommerce 3.3.4 or newer is required
* PHP 5.6 or higher is required.
* An SSL Certificate is required.
