=== Checkout with Vipps for WooCommerce ===
Contributors: wphostingdev, iverok, pmbakken
Tags: woocommerce, vipps
Requires at least: 4.7
Tested up to: 4.9.6
Stable tag: trunk
Requires PHP: 5.6
WC requires at least: 3.3.4
WC tested up to: 3.4.3
License: AGPLv3 or later
License URI: http://www.gnu.org/licenses/agpl-3.0.html


== Description ==

*Official Vipps Express Checkout and Payment for WooCommerce. 2.9 millon norwegians use Vipps. Give them a fast and familiar shopping experience.*

This is the official Vipps plugin that provides a direct integration with the Vipps backend. Now you can let your customers choose Vipps directly in the checkout or even do an express checkout from the cart.

You can also do important back office tasks such as capture and refund directly from WooCommerce. Easy for your customer and easy for you.

Read [information from Vipps](https://www.vipps.no/woocommerce ) about the plugin.

=== Vipps Express Checkout ===
When you enable Express Checkout, your customers can choose between the regular checkout or to go directly to Vipps. If they choose Vipps, they just submit their phone number, and the rest of the checkout is done in the Vipps app.

Since Vipps knows who the customers are, they don't have to enter all their personal information. The customer just choose the shipping method and accepts the payment. Vipps will send all the necessary info back to the store. Easy, fast and secure.

=== Vipps Payment ===
When you enable this plugin, your customers will be able to choose Vipps as a payment method directly in the checkout. There is no need to go via a third party payment method. If your customer choose Vipps, she fills in her name and address and is then asked to enter her phone number in the Vipps dialougue. Then she confirms the payment in the Vipps app.

=== How to get started ===
* Sign up to use Vipps på Nett ([vipps.no/woocommerce](https://www.vipps.no/bedrift/vipps-pa-nett/woocommerce))
* After 1-2 days you will get an email with login details to Vipps Developer Portal, where you can get the API credentials
* Download and install the plugin
* Configure the plugin

=== How to install the plugin ===
1.  Install the plugin using WordPress’ [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the WooCommerce Settings page and choose Payment Gateways (Betalinger) and enable Vipps.
4. Go the settings page for the Vipps plugin and enter your Vipps account keys.

== Installation ==
1.  Install the plugin using WordPress’ [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory. 
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the WooCommerce Settings page and choose Payment Gateways (Betalinger) and enable Vipps.
4. Go the settings page for the Vipps plugin and enter your Vipps account keys. Your account keys are available in the Vipps Developer Portal (detailed info in the section below)

=== How to get Vipps account keys from Vipps Developer Portal ===
1. Sign in to Vipps Developer Portal at [https://api-portal.vipps.no/](https://api-portal.vipps.no/)
   - Username is sent via email
   - Password is sent via SMS
2. Select the "Applications" tab. Here you can find the merchant/saleunit serial number (6 figures)
3. Click on "View Secret" to see “Client ID” and “Client Secret”
4. Click on customer name (top-right corner) and select "Profile" to see “Default accesstoken” and “Ecommerce API” (click on “Show” to see the primary key)

== Screenshots ==
1. Enable Vipps as a payment method.
2. Enter your Vipps account keys and configure the plugin.

== Contributing on Github ==
This project is hosted on Github at: https://github.com/vippsas/vipps-woocommerce

== Frequently Asked Questions ==

= In which countries can I use Vipps? =
You can only get paid by users who have Vipps. At the moment Vipps is only available in Norway.

= How can I get help if I have any issues? =
For issues with your WooCommerce installation you should use the support forum here on wordpress.org. For other issues you should contact Vipps.

= What are the requirements? =
* WooCommerce 3.3.4 or newer is required
* PHP 5.6 or higher is required.
* An SSL Certificate is required.


== Changelog ==

= 2018.07.06 version 1.0.3 =
* Change - Added more logging for failed access token call

= 2018.07.03 version 1.0.2 =
* Fix	- Uninitialized variable use and wrong call to uninstall - thanks to Rafal Sokolowski for bug reports and patches
* Change- Authtoken now used when verifying express checkout calls

= 2018.06.29 version 1.0.1 = 
* Fix	- Showing whether an order is made with express checkout or checkout in backend 
* Change- Login code temporarily removed

= 1.0 =
* Initial release.

