=== Checkout with Vipps for WooCommerce ===
Contributors: wphostingdev, iverok, pmbakken, perwilhelmsen
Tags: woocommerce, vipps
Requires at least: 4.7
Tested up to: 5.0.0
Stable tag: trunk
Requires PHP: 5.6
WC requires at least: 3.3.4
WC tested up to: 3.5.1
License: AGPLv3 or later
License URI: http://www.gnu.org/licenses/agpl-3.0.html


== Description ==

*Official Vipps Express Checkout and Payment for WooCommerce. 2.9 millon norwegians use Vipps. Give them a fast and familiar shopping experience.*

This is the official Vipps plugin that provides a direct integration with the Vipps backend. Now you can let your customers choose Vipps directly in the checkout or even do an express checkout from the cart.

You can also do important back office tasks such as capture and refund directly from WooCommerce. Easy for your customer and easy for you.

Read [information from Vipps](https://www.vipps.no/woocommerce ) about the plugin.

=== Vipps Payment ===
When you enable this plugin, your customers will be able to choose Vipps as a payment method in the checkout. There is no need to go via a third party payment method. If your customer choose Vipps, she fills in her name and address and is then asked to enter her phone number in the Vipps dialougue. Then she confirms the payment in the Vipps app. Customer info like name and address is sent to the store from Vipps.

=== Vipps Express Checkout ===
When you enable Express Checkout, your customers can choose between the regular checkout or to go directly to Vipps. If they choose Vipps, they just submit their phone number, and the rest of the checkout is done in the Vipps app.

Since Vipps knows who the customers are, they don't have to enter all their personal information. The customer just choose the shipping method and accepts the payment. Vipps will send all the necessary info back to the store. Easy, fast and secure.

The express checkout can be done in the following ways:

* From the cart
* From the category pages
* From the product page
* From shareable links distributed by email, banners etc
* From QR codes distributed digitally or in print

Settings for the cart, category and product pages can be found in the WooCommerce settings for the Vipps payment gateway.

Shareable links and QR codes can be generated from the Vipps tab on the product page.

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
3. Create shareable links and QR codes

== Contributing on Github ==
This project is hosted on Github at: https://github.com/vippsas/vipps-woocommerce

== Frequently Asked Questions ==

= In which countries can I use Vipps? =
You can only get paid by users who have Vipps. At the moment Vipps is only available in Norway.

= How can I get help if I have any issues? =
For issues with your WooCommerce installation you should use the support forum here on wordpress.org. For other issues you should contact Vipps.

= Why are orders put on-hold and not reserved or completed? =
When the order is on-hold the payment is reserved, but not yet transferred to the merchant. The money must be 'captured' before they are acutally transfered to the merchang. You are normally only allowed to do this at the same time as the order is shipped. You can 'capture' the money explitly on the order screen; but the money will be captured automatically when the order is set to "Processing" or "Complete".

There is an exception for orders where all items are both virtual and downloadable: These are not considered to need processing and will be captured automatically (and go directly to the 'Complete' status). It is possible to customize this property for your needs using the woocommerce_order_item_needs_processing filter.

= What are the requirements? =
* WooCommerce 3.3.4 or newer is required
* PHP 5.6 or higher is required.
* An SSL Certificate is required.

= Filters and Hooks for customization =
There are several filters and hooks you can use to customize the behaviour of this plugin:
 * Filter: 'woo_vipps_is_available': Takes a boolean availability argument and the gateway and must return true or false
 * Filter: 'woo_vipps_express_checkout_available': Takes a boolean availability argument and the gateway and must return true or false. 
 * Filter: 'woo_vipps_cart_express_checkout_button': Recieves a complete button text and the URL needed to proceed to the express checkout page.
 * Filter: 'woo_vipps_express_checkout_banner': Receives a message with an express checkout button and an URL for the same, should return a message for the express checkout banner normally shown on the checkout page
 * Filter: 'woo_vipps_buy_now_button': Takes HTML for the button, and optionally product id, variation id, sku and if the button is to be shown as disabled by default
 * Filter: 'woo_vipps_show_express_checkout' - Takes a boolean, returns whether or not to show the express checkout button
 * Filter: 'woo_vipps_show_single_product_buy_now' - Takes a boolean and a product, returns true if the product should show a 'buy now with vipps' button
 * Filter: 'woo_vipps_show_single_product_buy_now_in_loop' - Like above, but especially for products shown in the loop - catalog pages, archives and so forth
 * Filter: 'woo_vipps_spinner': takes one argument which is a 'wait' spinner for certain pages
 * Filter: 'woo_vipps_shipping_methods': Takes an array of shipping methods, the order and a cart. Should return an array of shipping methods.
 * Filter: 'woo_vipps_shipping_callback_packages': Takes the 'packages' from the cart used to calculate shipping in the shipping details callback
 * Filter: 'woo_vipps_express_checkout_shipping_rate': The shipping rate to add on express checkout. Takes existing shipping_rate, cost ex tax, tax, shipping method and shipping product and must return a shipping_rate.
 * Filter: 'woo_vipps_country_to_code': Takes a country code and a country name.  Should return a two-letter ISO-3166 country code from a given country name
 * Filter: 'woo_vipps_show_capture_button': Takes a boolean and an order and returns whether or not to show the capture button in the backend
 * Filter: 'woo_vipps_captured_statues': Returns a list of the statuses for which Vipps should try a capture when transitioning to them.
 * Filter: 'woo_vipps_transaction_text': Takes a transaction text and an order object, must return a text to be passed to Vipps and displayed to the user along the lines of "Please confirm your order"
 * Filter: 'woo_vipps_special_page_template': Takes a (complete) template path as returned by locate_template and the ID of the Vipps special page, should return a new template path (using locate_template or similar).
 * Action: 'woo_vipps_shipping_details_callback': Takes an order-id and the corresponding vipps order id. Run at the start of the shipping methods callback.
 * Action: 'woo_vipps_restoring_cart': Takes an order and a saved cart contents array, ran after the order has failed or is aborted
 * Action: 'woo_vipps_cart_restored':  Runs after the cart has been restored after the order has been aborted of failed
 ' Action: 'woo_vipps_cart_saved': When redirecting to Vipps, the cart is saved so it can be restored in case the order isn't completed. This action is ran after this has happened.
 * Action: 'woo_vipps_before_redirect_to_vipps': Takes an order-id, called at the end of process_payment right before the redirect to Vipps
 * Action: 'woo_vipps_before_process_payment': Takes an order-id, called at the start of process_payment
 * Action: 'woo_vipps_wait_for_payment_page': Run on the page shown on return from Vipps
 * Action: 'woo_vipps_express_checkout_page': Run on the express checkout page, before redirect to Vipp
 * Action: 'woo_vips_set_order_shipping_details': Takes an order object, shipping details from Vipps and user details from Vipps. Runs after shipping details have been added to the order on return from express checkout.
 * Action: 'woo_vipps_callback': Runs when Vipps does the callback on a successful payment, takes Vipps' data as input. Useful for logging/debugging the callback.
 * Action: 'woo_vipps_express_checkout_get_order_status': Takes the order status returned by Vipps - called when the Vipps callback hasn't happened and we need the order status. Userful for logging.
 

= Shortcodes =
 * [woo_vipps_express_checkout_button] will print the express checkout button if valid
 * [woo_vipps_express_checkout_banner] will print the express checkout banner normally shown on the checkout page for non-logged-in users
 * [woo_vipps_buy_now sku=<SKU> id=<productid> variant=<variant id>] prints a "buy now" button given a SKU or an (product or variant) id. Just the SKU is sufficient.


== Changelog ==

= 2018.11.xx version 1.1.5
* Disable "pay" button on the order listing of aborted express checkout orders for logged-in users (thanks to lykkelig@wp.org for the report)
* Added a failsafe to retrieve the order id on return to the shop even when session has been destroyed or is in another browser
* Added filter 'woo_vipps_special_page_template' for choosing template of special pages

= 2018.11.19 version 1.1.4
* New filter  'woo_vipps_transaction_text' to customize the text sent to Vipps
* Added call to 'woocommerce_checkout_update_order_meta' in create partial order

= 2018.11.12 version 1.1.3
* New action on order shipping details method for express checkout
* New action on callback from Vipps
* New action when checking order status for express checkout
* Removed "!important" from CSS for Vipps-buttons

= 2018.11.05 version 1.1.2 =
* Support 'quantity' when buying products directly
* Support for WP5.0 and WooCommerce 3.5.1 tested
* "Show complete transaction details" button in backend to verify status at Vipps
* Added notification on order screen for orders that have been successfully captured

= 2018.10.29 version 1.1.1 =
* Backwards compatibility to 3.3.x added for direct buys

= 2018.10.29 version 1.1.0 =
* New feature: Buy directly using Vipps Express Checkout from product pages and catalog listings
* New feature: Create 'shareable links' allowing customers to buy directly using Vipps Express Checkout from external links and banners
* New filters, actions, shortcodes
* Support for WooCommerce 3.5.0

= 2018.10.09 version 1.0.7 =
* Improvement: Add 'woo_vipps_show_express_checkout' filter
* Improvement: Add 'woo_vipps_show_capture_button' filter
* Improvement: Add 'woo_vipps_captured_statuses'
* Bugfix/improvement: Changed WC_Gateway_Vipps to be a Singleton (fixes certain hooks being called several times)

= 2018.10.03 version 1.0.6 =
* Fix - Cart is now saved and restored if the payment is aborted or fails
* Improvement: Added hooks for cart save and restore
* Improvement: Added actions 'woo_vipps_before_process_payment', 'woo_vipps_before_redirect_to_vipps'
* Improvement: Added filter 'woo_vipps_shipping_callback_packages'

= 2018.09.25 version 1.0.5 =
* Fix - Shipping details callback returned prices wrongly formatted for some locales
* Fix - "Vipps as default" was always on (Thanks to Jacob von der Lippe for the bug report)
* Fix - Availability of the payment alternative now depends on currency and is subject to filters. Express checkout only shown when available
* Change - The plugin can be activated even if default currency is not NOK (to allow for multi-currency situations)
* Improvement - Filters, hooks and shortcodes added
* Improvement - Give a specific error message if allow_url_fopen is false (thanks to eddiex666 for suggestion)
* Improvement - Shipping details callback will return a zero-cost alternative if no shipping is needed
* Improvement - For order that do not need processing, payment_complete will be called iff capture is successful. This allows for auto-capture of virtual and downloadable products.

= 2018.09.12 version 1.0.4 =
* Change - Added more logging for shipping metods
* Fix - Make Description and Payment Method Name in settings actually affect the checkout page
* Fix - Make plugin work in network installs of WooCommerce for multisite shops (Thanks to Thomas Audunhus for the bug report)

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

