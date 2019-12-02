=== Checkout with Vipps for WooCommerce ===
Contributors: wphostingdev, iverok, pmbakken, perwilhelmsen
Tags: woocommerce, vipps
Requires at least: 4.7
Tested up to: 5.3.0
Stable tag: trunk
Requires PHP: 5.6
WC requires at least: 3.3.4
WC tested up to: 3.8.0
License: AGPLv3 or later
License URI: http://www.gnu.org/licenses/agpl-3.0.html


== Description ==

*Official Vipps Express Checkout and Payment for WooCommerce. 2.9 millon norwegians use Vipps. Give them a fast and familiar shopping experience.*

This is the official Vipps plugin that provides a direct integration with the Vipps backend. Now you can let your customers choose Vipps directly in the checkout or even do an express checkout from the cart.

You can also do important back office tasks such as capture and refund directly from WooCommerce. Easy for your customer and easy for you.

Read [information from Vipps](https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/) about the plugin.

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
* Sign up to use Vipps på Nett ([WooCommerce](https://www.vipps.no/produkter-og-tjenester/bedrift/ta-betalt-paa-nett/ta-betalt-paa-nett/woocommerce/))
* After 1-2 days you will get an email with login details to Vipps Developer Portal, where you can get the API credentials
* Download and install the plugin
* Configure the plugin

=== How to install the plugin ===
1.  Install the plugin using WordPress’ [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the WooCommerce Settings page and choose Payment Gateways (Betalinger) and enable Vipps.
4. Go the settings page for the Vipps plugin and enter your Vipps account keys. Your account keys are available in the Vipps Developer Portal (detailed info in the section below)


== Installation ==
1.  Install the plugin using WordPress’ [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory. 
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the WooCommerce Settings page and choose Payment Gateways (Betalinger) and enable Vipps.
4. Go the settings page for the Vipps plugin and enter your Vipps account keys. Your account keys are available in the Vipps Developer Portal (detailed info in the section below)

=== How to get Vipps account keys from Vipps Developer Portal ===
1. Sign in to the Vipps Portal at [https://portal.vipps.no/](https://portal.vipps.no/) using Bank ID
2. Select the "Utvikler" ("Developer") tab and choose Production Keys. Here you can find the merchant serial number (6 figures)
3. Click on "Show keys" under the API keys column to see “Client ID”, “Client Secret” and “Vipps Subscription Key”

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

From version 1.1.11 on, you can choose "Processing" as the end state instead of "On Hold", but be aware that these orders will only have been reserved, not captured; so you should always then capture before shipping.

= Can I refund orders or part of orders using Vipps =
Yes, you can do refunds, including partial refunds, using the standard WooCommerce mechanism (https://docs.woocommerce.com/document/woocommerce-refunds/). Additionally, if you cancel an order that was already captured, the money will be refunded for the whole order. If automatic refund through the Vipps API should fail, you will need to refund manually; in this case an error message to this effect will be displayed and the order annotated.

= What is 'compatibility mode' in the settings? =
Some plugins add new features to products or entirely new product types to WooCommerce; which the 'Express Checkout' function may not be able to handle. It can be possible to fix this using hooks and filters, but if you choose this feature, express checkout will be done in a different manner which is very much more likely to work for a given plugin. The cost is that the process will be _slightly_ less smooth.

= Why is my shipping wrong when using express checkout? =
Some shipping plugins and setups are not compatible with Vipps Express Checkout. 

We do our testing with the standard Woo shipping mechanism and Bring Fraktguiden for WooCommerce, and these should work. For any other plugin or setup we can unfortunately give no guarantee.

It is therefore a very good idea to provide a fallback shipping method if you want to use Express Checkout. There is also a filter 'woo_vipps_shipping_methods' that can be used to customize what is sent to Vipps.

It is therefore also important that you test your setup if you are using express checkout.

= I'd like to use sequential order numbers at Vipps instead of the WooCommerce order-ids using a sequential order number plugin. Does this plugins support that?
Yes, though you need to ensure that the order-id's you produce like this are unique for your Vipps account, and you currently have to use a filter in your themes' functions.php file. We recommend using a prefix for your order ids, so a filter that will work with sequential order numbers would look like

`add_filter('woo_vipps_order_id', function ($default, $prefix, $order) {
    return $prefix . $order->get_order_number();
}, 10, 3);`

= Firewall ports =
Ensure *outgoing* traffic to port 443 is open. This is used to communicate with Vipps servers.

= Does Vipps offer a test environment for Vipps for WooCommerce? =

Yes, but you will need a separate account, and you will need to install a special test version of the Vipps app, available trough Testflight. For your test account, the keys will be at https://portal-test.vipps.no; you will configure these in the developer mode settings.

Contact Vipps for access to the test app. This app must be installed on a device that does not have the normal Vipps app installed, or there will be conflicts.

To use test mode, switch "Developer mode" on. There you can input the test keys from portal-test.vipps.no and turn test mode on and off.

If this isn't practical for your usage, we recommend that you "test in production" with a small amount, like 2 NOK. Just refund or cancel the purchase as needed.


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
 * Filter: 'woo_vipps_captured_statuses': Returns a list of the statuses for which Vipps should try a capture when transitioning to them.
 * Filter: 'woo_vipps_transaction_text': Takes a transaction text and an order object, must return a text to be passed to Vipps and displayed to the user along the lines of "Please confirm your order"
 * Filter: 'woo_vipps_special_page_template': Takes a (complete) template path as returned by locate_template and the ID of the Vipps special page, should return a new template path (using locate_template or similar).
 * Filter: 'woo_vipps_order_failed_redirect': Takes an empty string or an url and an order id. If URL is returned, go there on cancelled or failed orders.
 * Filter: 'woo_vipps_product_supports_express_checkout': Takes a boolean and a product, returns true if the product can be bought with expres checkout
 * Filter: 'woo_vipps_cart_supports_express_checkout': Takes a boolean and a cart, returns true if the cart can be bought with expres checkout
 * Filter: 'woo_vipps_express_checkout_supported_product_types': Returns a list of product types (as strings) that can be bought with express checkout
 * Filter: 'woo_vipps_orderid': Takes default Vipps orderid, the order prefix, and an order object. Must return an unique (at Vipps) order ID with 30 chars or less. Default is the prefix + orderid, e.g. 'Woo364'.
 * Action: 'woo_vipps_shipping_details_callback_order': Takes an order-id and the corresponding vipps order id. Run at the start of the shipping methods callback.
 * Action: 'woo_vipps_restoring_cart': Takes an order and a saved cart contents array, ran after the order has failed or is aborted
 * Action: 'woo_vipps_cart_restored':  Runs after the cart has been restored after the order has been aborted of failed
 ' Action: 'woo_vipps_cart_saved': When redirecting to Vipps, the cart is saved so it can be restored in case the order isn't completed. This action is ran after this has happened.
 * Action: 'woo_vipps_before_redirect_to_vipps': Takes an order-id, called at the end of process_payment right before the redirect to Vipps
 * Action: 'woo_vipps_before_create_express_checkout_order': Takes the cart to do express checkout for, run before the order is created 
 * Filter : 'woo_vipps_create_express_checkout_cart_contents': Takes a cart contents array from which an express checkout order will be created . Should return a like array.
 * Action: 'woo_vipps_express_checkout_order_created': Takes an order ID, run right after an express checkout order has been created, but before it is processed'
 * Action: 'woo_vipps_before_process_payment': Takes an order-id, called at the start of process_payment
 * Action: 'woo_vipps_wait_for_payment_page': Run on the page shown on return from Vipps
 * Action: 'woo_vipps_express_checkout_page': Run on the express checkout page, before redirect to Vipp
 * Action: 'woo_vipps_set_order_shipping_details': Takes an order object, shipping details from Vipps and user details from Vipps. Runs after shipping details have been added to the order on return from express checkout.
 * Action: 'woo_vipps_callback': Runs when Vipps does the callback on a successful payment, takes Vipps' data as input. Useful for logging/debugging the callback.
 * Action: 'woo_vipps_express_checkout_get_order_status': Takes the order status returned by Vipps - called when the Vipps callback hasn't happened and we need the order status. Userful for logging.
 * Action: 'woo_vipps_vipps_callback': Is ran when the Vipps callback happen, with the decoded and raw POST from Vipps. Useful for logging. 
 * Action: 'woo_vipps_shipping_details_callback': Is ran when Vipps does the shipping details callback on express checkout. Takes decoded and raw POST from Vipps, and the callback args. For debugging.
 * Action: 'woo_vipps_shipping_details_before_cart_creation': Run after order is updated but before a cart is created to do shipping calculations. Takes an order, The order-id at Vipps and the callback arguments from Vipps
 * Filter: 'woo_vipps_transaction_text_shop_id': This is used to identify your shop in the transaction texts sent to Vipps (and shown to the user). Default is home_url(), but there is a length limit, so this filter allows you to keep it short.

= Shortcodes =
 * [woo_vipps_express_checkout_button] will print the express checkout button if valid
 * [woo_vipps_express_checkout_banner] will print the express checkout banner normally shown on the checkout page for non-logged-in users
 * [woo_vipps_buy_now sku=<SKU> id=<productid> variant=<variant id>] prints a "buy now" button given a SKU or an (product or variant) id. Just the SKU is sufficient.

= Javascript filters and actions =
From version 1.1.13 you can also modify the javascript using the new WP hooks library for javascript:
 * 'vippsBuySingleProduct' - action which is run whenever a customer tries to buy a single product using express checkout
 * 'vippsBuySingleProductCompatMode' - filter which should return true or false, if true, the compatibility mode action will be run instead of the standard ajax.
 * 'vippsBuySingleProductCompatModeAction' - filter which should return a javascript function to run when buying a product and compatibility mode is on. Will normally press the "Buy" button for you.
 * 'vippsRemoveErrorMessages' - runs when Vipps error messages are to be removed.
 * 'vippsErrorMessage' - runs for every Vipps error message added with Javascript. Takes the message as an argument
 * 'vippsAddErrorMessage' - runs when an error message is about to be added. Takes the message as an argument
 * 'vippsInit'  - runs when a page with a Vipps button is initialzed
 * 'vippsStatusCheckErrorHandler' - A filter that should return function taking a statustext and an error object. It receives the default error handler, and is called when checking the order status with ajax for some reason ends up in an error.

== Changelog ==

= 2019.12.02 version 1.3.3 =
 * Add callback session handling to normal non-express checkout flow as well
 * Ensure local_pickup does not get set as default, even if free - unless it is the only option

= 2019.11.20 version 1.3.2 =
 * Ensured free_shipping is set as default shipping method in Express Checkout if the user qualifies. Thanks to Emily Bakke for reporting.
 * When using express checkout, use the customers' phone number as default if we know it https://github.com/vippsas/vipps-woocommerce/issues/22. Thanks to sOndre for reporting.

= 2019.11.13 version 1.3.1 =
 * Added code to ensure that wpml doesn't ruin callback urls with extra slashes

= 2019.11.04 version 1.3.0 =
 * Finally, added a session handler for the Vipps callbacks so these happen with the same session and context as the user interactions. Furthermore added handling for coupons and any previous chosen shipping method in the shipping callback, and added sorting to this so cheapest appear first.
 * Changed logic of maybe_delete_order so that it just marks the order as deleted and then does the actual delete 10 minutes later. This way, hooks and stuff can manipulate the order before it is deleted.

= 2019.10.21 version 1.2.5 =
 * Added protection against too long 'transactionText' values, and a new filter 'woo_vipps_transaction_text_shop_id' which can be used to provide a short name for the store (default is home_url()). Thanks to Marco1970 on wp.org for reporting.

= 2019.10.14 version 1.2.4 =
 *  Added action 'woo_vipps_shipping_details_before_cart_creation' to assist sites where the Cart cannot be manipulated when no session is active.

= 2019.10.07 version 1.2.3 =
 * Added actions and filters when creating Express Checkout order: 'woo_vipps_before_create_express_checkout_order' 'woo_vipps_create_express_checkout_cart_contents' and 'woo_vipps_express_checkout_order_created'
 * Added a call to 'get_payment_details' before capturing or refunding money - this ensures that orders are synched with Vipps before this is done and eliminates a difficult error that previously only was fixable by pressing "Show complete transaction details".

= 2019.09.16 version 1.2.2 =
 * Ensure situations where the first and second addresslines are duplicates are silently fixed
 * Add option for deleting Express Checkout orders that are abandoned (from 'Pending' to 'Cancelled' without any customer or address info)
 * Add 'developer mode' and in this, 'test mode' with a separate set of keys for testing Vipps. Remember, you will also need to install a test Vipps app from Testflight on a separate device from your normal Vipps-device.


= 2019.08.26 version 1.2.1 =
* Tested with WordPress 5.3, WooCommerce 3.7 and Bring Fraktguiden for WooCommerce 1.6.5

= 2019.08.06 version 1.2.0 =
* Removed separate Access Key subscription, now only one subscription key is required
* Documentation updated to reflect that the keys are now to be fetched from portal.vipps.no, and that the separate Access Key subscription is no longer neccessary


= 2019.06.24 version 1.1.18 =
* Version bump
* New filter woo_vipps_orderid - lets you generate your own (unique) orderid at Vipps
* New javascript filter for customizing the status check error handler

= 2019.06.17 version 1.1.17 =
* Changed documentation and screenshots to correspond with new subscription key setup at Vipps

= 2019.05.21 version 1.1.16 =
* Disabled Vipps metabox for non-vipps shop_orders
* Fixed bug in Express Checkout for single variable products - thanks to Gaute Terland Nilsen @ Easyweb for the report
* Fixed links in readme.txt

= 2019.04.29 version 1.1.15 =
* Renamed second of two actions in the shipping details callback
* Tested with WooCommerce 3.6.2 and WordPress 3.5.2
* Changed help text for Ocp_Apim_Key_AccessToken to reflect that for some users, the eCommerce key should be used

= 2019.04.08 version 1.1.14 =
* Wrapped "Buy now"-text in buttons in a SPAN to allow for easier styling
* Fixed error in instantiating Shipping Rates for Express Checkout orders (Thanks to Gaute Terland Nilsen @ ewn.no for reporting and debugging)
* Small bugfixes

= 2019.03.11 version 1.1.13 =
* New compatibility mode for "Buy now" for products that need special configuration. You can choose this in the backend, and the purchase will be made in a more compatible manner which is _slightly_ less smooth.
* Disable "Buy now" buttons if products or cart have total value 0 - Vipps can't handle free products.
* Added support for WP hooks in javascript (with backwards compatibility for 4.7-4.9).
* New filters/actions: 'woo_vipps_single_product_compat_mode', 'woo_vipps_single_product_buy_now_classes'
* Javascript filters/actions: 'vippsBuySingleProduct' 'vippsBuySingleProductCompatMode' 'vippsBuySingleProductCompatModeAction' 'vippsRemoveErrorMessages' 'vippsErrorMessage' 'vippsAddErrorMessage' 'vippsInit'
* Add a transaction ID (the prefix pluss the order id) to orders
* Fix title of "Buy now with Vipps" button (thanks to @redaksjonen)

= 2019.02.26 version 1.1.12 =
* Fixes bugs/issues with direct capture and the SALE status at Vipps
* Fix bug that could lead to shipping address being set twice when doing express checkout
* Tested to Woo 3.5.5 and WP 5.1

= 2019.02.04 version 1.1.11 =
* Improvements to logging
* Tested with 5.1 beta 
* Changed the express checkout shortcode methods to ignore the backend settings for express checkout - now only the cart will be verified when a short code is used
* In this version, it is possible to choose Processing as a non-captured status (with capture done either manually or on Complete), where shipping must be done after capture. This changes the status change hook usage in the plugin.
* To facilitate custom order statuses and flow, use payment status instead with only four values (initiated/authorized/complete/cancelled) to check results
* Bugs in the shipping callback for Express Checkout fixed


= 2019.01.01 version 1.1.10 =
* Ensure order edits don't confuse the captured amount. Make 'amount' required argument to Api's capture_payment, and make it be in cents only
* Added button to refund any accidently over-captured amount on a completed order
* As a sideeffect of retrieving the complete payment history, update the order with the status and postmeta values directly from Vipps
* Fixed spelling error in 'woo_vipps_set_order_shipping_details'

= 2018.12.17 version 1.1.9 =
* Error in WC_Logger usage fixed (thanks to (Thanks to Espen Espelund @netthandelsgruppen.no for the report as well as the rest of the issues covered in this update)
* Improved logging in general (Thanks to patch from E. Espelund) 
* Fix 404 response on certain pages 
* Added code to try to reduce impact of race conditions on persistent object caches 
* Moved shipping-update into critical section in callback handler 

= 2018.12.11 version 1.1.8 =
* Fix bug in express checkout for logged-in customers

= 2018.12.10 version 1.1.7 =
* Typo in 1.1.6 fixed

= 2018.12.10 version 1.1.6 =
* Added filter 'woo_vipps_order_failed_redirect'
* Added filters 'woo_vipps_express_checkout_supported_product_types', ''woo_vipps_product_supports_express_checkout', ''woo_vipps_order_failed_redirect' to control product types that cannot be bought by express checkout
* Added lots of wc_nocache_headers() to avoid caching where sessions etc are missing
* Added new action 'woo_vipps_vipps_callback'
* Improved security in shipping details callback (Thanks to Espen Espelund @netthandelsgruppen.no for the report)

= 2018.11.26 version 1.1.5
* Disable "pay" button on the order listing of aborted express checkout orders for logged-in users (thanks to lykkelig@wp.org for the report)
* Added a failsafe to retrieve the order id on return to the shop even when session has been destroyed or is in another browser
* Added filter 'woo_vipps_special_page_template' for choosing template of special pages
* Added no-cache headers to return-from-vipps page
* Added text about shipping and express checkout

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

