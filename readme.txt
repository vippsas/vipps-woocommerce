=== Pay with Vipps and MobilePay for WooCommerce ===
Contributors: wphostingdev, everydayas, iverok, perwilhelmsen, nikolaidev, lassepladsen, marcuz2k2k
Author: WP Hosting, Everyday AS
Author URI: https://www.wp-hosting.no/
Tags: woocommerce, vipps, mobilepay, recurring payments, subscriptions
Version: 5.3.0
Stable tag: 5.3.0
Requires at least: 6.3
Tested up to: 6.9.4
Requires PHP: 8.0
Requires Plugins: woocommerce
WC requires at least: 8.0.0
WC tested up to: 10.6.0
License: MIT
License URI: https://choosealicense.com/licenses/mit/
Official Vipps MobilePay payment plugin for WooCommerce.

== Description ==
*Official Vipps MobilePay payment plugin for WooCommerce. Let your Norwegian, Danish, and Finnish customers pay with Vipps and MobilePay for an easy, fast, and familiar shopping experience* 

Vipps is used by more than 93 % of Norway's population (4.2 million users).

MobilePay is used by more than 92 % of Denmark's population (4.4 million users), and approximately 59 % of Finland's population (2.8 million users).

Vipps and MobilePay are payment methods offered by Vipps MobilePay. 

When you enable this plugin, you will choose between offering either Vipps or MobilePay as a payment method for your customers - hence "Vipps/MobilePay" going forward.

This is the official plugin for Vipps/MobilePay Checkout, Vipps/MobilePay ePayments (*Vipps Nettbetaling*), Vipps MobilePay Express (*Vipps MobilePay Hurtigkasse*) and Vipps/MobilePay recurring payments. Increase your conversion rate by letting your customers choose Vipps/MobilePay directly in the checkout or even do an Express Checkout (Vipps only) from the cart or a product page directly.

You can also do important back office tasks such as capture and refund directly from WooCommerce. Easy for your customer and easy for you.

Read [information from Vipps MobilePay](https://developer.vippsmobilepay.com/docs/plugins/woocommerce/) about the plugin.

=== Vipps/MobilePay Checkout ===
With Vipps/MobilePay Checkout enabled in the plugin, you will get a complete checkout in your webshop, designed by Vipps MobilePay. It contains regular Vipps/MobilePay payments, a card payment option for those that can't or won't use Vipps/MobilePay, as well as the ability to get the shipping address of the customer in an easy way. Read more about [Vipps MobilePay Checkout here](https://vippsmobilepay.com/en/online/checkout)

=== Vipps/MobilePay ePayment ===
When you enable this plugin, your customers will be able to choose Vipps/MobilePay as a payment method in the checkout. There is no need to go via a third party payment method. If your customer choose Vipps/MobilePay, they fill in their name and address and is then asked to enter their phone number in the Vipps/MobilePay dialogue. They then confirms the payment in the Vipps/MobilePay app. Customer info like name and address is sent to the store from Vipps MobilePay.

== Vipps/MobilePay recurring payments ==

Vipps/MobilePay recurring payments is perfect for you if you run a web shop with subscription based services or other products that would benefit from subscriptions.

With Vipps/MobilePay recurring payments you can:

* Sell recurring products (virtual and physical)
* Offer subscription services

See [How it works](https://developer.vippsmobilepay.com/docs/APIs/recurring-api/how-it-works/) for an overview.

Recurring payments requires [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) and a Vipps MobilePay MSN with recurring payments added.

=== MobilePay Reservations are currently for 14 days ===
When a payment is completed with Vipps MobilePay, the money will be reserved, but only transferred to the merchant when the order is set to "Complete" or the money is captured manually. *For MobilePay, this reservation period is 14 days*, so you will need to ship and fulfill orders before this; or to make an agreement with the customer to capture the money before this period is over. For Vipps, the period is 180 days. For payments made by credit card in Vipps/MobilePay Checkout, the period can again be as short as 7 days.
For details, please read the [developer FAQ](https://developer.vippsmobilepay.com/docs/knowledge-base/reserve-and-capture/#reserve-and-capture-faq).

If the order only contains virtual and downloadable products, the plugin will capture the order automatically and set the order to "Completed" as is the standard WooCommerce rule.

=== Vipps MobilePay Express ===
When you enable Vipps MobilePay Express, your customers can choose between the regular checkout or to go directly to Vipps or MobilePay. If they choose Vipps or MobilePay, they just submit their phone number, and the rest of the checkout process is done in the Vipps or MobilePay app.

Since Vipps MobilePay knows who the customers are, they don’t have to enter all their personal information. The customer just choose the shipping method and accepts the payment. Vipps MobilePay will send all the necessary info back to the store. Easy, fast and secure.

The express checkout can be done in the following ways:

* From the cart
* From the category pages
* From the product page
* From shareable links distributed by email, banners, etc.
* From QR codes distributed digitally or in print

Settings for the cart, category and product pages can be found in the WooCommerce settings for the Vipps MobilePay payment gateway.

Shareable links and QR codes can be generated from the Vipps/MobilePay tab on the product page.

=== How to get started ===
* Sign up in the [Vipps MobilePay portal](https://portal.vippsmobilepay.com) and choose your product.
* After 1-2 days you will get an email with login details to Vipps MobilePay Business Portal, where you can get the API credentials
* Download and install the plugin
* Configure the plugin

=== How to install the plugin ===
1.  Install the plugin using WordPress’ [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the WooCommerce Settings page and choose Payment Gateways (Betalinger) and enable Vipps/MobilePay.
4. Go the settings page for the Vipps MobilePay plugin and enter your Vipps MobilePay account keys. Your account keys are available in the Vipps Business Portal (detailed info in the section below)


== Installation ==
1.  Install the plugin using WordPress’ [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory. 
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the WooCommerce Settings page and choose Payment Gateways (Betalinger) and enable Vipps/MobilePay.
4. Go the settings page for the Vipps MobilePay plugin and enter your Vipps MobilePay account keys. Your account keys are available in the Vipps MobilePay Business Portal (detailed info in the section below)

=== How to get Vipps MobilePay account keys from Vipps MobilePay Business Portal ===
1. Sign in to the Vipps MobilePay Portal at [https://portal.vippsmobilepay.com/](https://portal.vippsmobilepay.com/) using Bank ID
2. Select the "Utvikler" ("Developer") tab and choose Production Keys. Here you can find the merchant serial number (6 figures)
3. Click on "Show keys" under the API keys column to see “Client ID”, “Client Secret” and “Vipps MobilePay Subscription Key”

== Screenshots ==
1. Enable Vipps/MobilePay Checkout as your checkout.
2. Enable Vipps/MobilePay as a payment method.
3. Enter your Vipps MobilePay account keys and configure the plugin.
4. Setup and activate the recurring payment gateway in WooCommerce.
5. Configure the plugin settings for recurring payments.

== Contributing on Github ==
This project is hosted on Github at: https://github.com/vippsas/vipps-woocommerce

== Upgrade Notice ==
Version 5.3.0
Aborted Vipps MobilePay orders are now "failed" instead of "cancelled" and can be retried as a new Vipps MobilePay session
Error that occasionally sent the user to the homepage instead of Checkout fixed

== Frequently Asked Questions ==

= In which countries can I use Vipps/MobilePay? =
Vipps is currently only available in Norway (93 % user adoption in 2022) and Sweden.
MobilePay is currently only available in Denmark and Finland (92 % and 59 % user adoption in 2025, respectively).

= How can I get help if I have any issues? =
For issues with your WooCommerce installation, use the support forum here on wordpress.org. For other issues you should contact Vipps MobilePay.

= Why are orders put on-hold and not processing or completed? =
This was the old default of this plugin until version 2.0. The newer default is now 'processing', but this also means that orders that are 'processing' will have their payment reserved, but not transferred to the merchant. You can still choose the status on-hold in the options screen.

If you choose on-hold, orders with this status will have the payment reserved, but not yet transferred to the merchant. The money must be 'captured' before they are actually transferred to the merchant. You are normally only allowed to do this at the same time as the order is shipped. You can 'capture' the money explicitly on the order screen; but the money will be captured automatically when the order is set to "Processing" or "Complete".

If you use the default or choose "processing", the same applies to this status: The order will be reserved, but not captured. You can do the capture manually, or it will automatically happen when the order is set to "Complete". Please note that you should ensure that your workflow is then so that the order is captured just before the package is shipped.

There is an exception for orders where all items are both virtual and downloadable: These are not considered to need processing and will be captured automatically (and go directly to the 'Complete' status). It is possible to customize this property for your needs using the woocommerce_order_item_needs_processing filter.

= Can I refund orders or part of orders using Vipps/MobilePay =
Yes, you can do refunds, including partial refunds, using the standard WooCommerce mechanism (https://docs.woocommerce.com/document/woocommerce-refunds/). Additionally, if you cancel an order that was already captured, the money will be refunded for the whole order if the order is not too old. For older orders, you must use the refund mechanism explicitly. This is a safety feature.
 If automatic refund through the Vipps MobilePay API should fail, you will need to refund manually; in this case an error message to this effect will be displayed and the order annotated.

= What is 'compatibility mode' in the settings? =
Some plugins add new features to products or entirely new product types to WooCommerce; which the 'Express Checkout' function may not be able to handle. It can be possible to fix this using hooks and filters, but if you choose this feature, express checkout will be done in a different manner which is very much more likely to work for a given plugin. The cost is that the process will be _slightly_ less smooth.

= Why is my shipping wrong when using express checkout? =
It may be that the shipping method you are using some how does not work when calculated from the Vipps/MobilePay app, where the customer is somewhat anonymous. However, since version 1.4.0 this problem ought to be greatly reduced, so if you still have this problem, report this on the forum and we'll try to fix it. 

If you have shipping methods that add additional information on the 'normal' checkout page they will not be able to provide that information to Express Checkout plugin, since that page is bypassed. You may be able to add those options on a different page; but you may want to remove those options when using Express Checkout.

Formerly, there was a filter used to work around this, namely 'woo_vipps_shipping_methods'. This still works, but if you use it, it will disable the 'new' shipping method calculation. You may still customize the Express Checkout shipping; the new filter is called 'woo_vipps_shipping_rates'.

To be sure, you should test your shipping methods in Express Checkout before going live.

= I'd like to use sequential order numbers at Vipps MobilePay instead of the WooCommerce order-ids using a sequential order number plugin. Does this plugins support that?
Yes, though you need to ensure that the order-id's you produce like this are unique for your Vipps MobilePay account, and you currently have to use a filter in your themes' functions.php file. We recommend using a prefix for your order ids, so a filter that will work with sequential order numbers would look like

`add_filter('woo_vipps_orderid', function ($default, $prefix, $order) {
    return $prefix . $order->get_order_number();
}, 10, 3);`

= Do I need to have a license for WooCommerce Subscriptions in order to use recurring payments? =

Yes, you do. Get it
[here](https://woocommerce.com/products/woocommerce-subscriptions/).

= Does the recurring payment part of the plugin work with the WooCommerce Memberships-plugin? =

[WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)
and
[WooCommerce Memberships](https://woocommerce.com/products/woocommerce-memberships/)
are able to work together for access to recurring memberships that unlock content.

**WooCommerce Subscriptions is required in order to use Vipps/MobilePay recurring payments, but Memberships is not.**

You can read about how WooCommerce Subscriptions and WooCommerce Memberships work together [here](https://docs.woocommerce.com/document/woocommerce-memberships-subscriptions-integration/).


= When I use recurring payments, why do I have to capture payments for physical products manually? =

This is because of the Norwegian law. You are not allowed to charge for a physical product before you ship it, without a valid reason to do so.

You can read about it [here](https://www.forbrukertilsynet.no/english/guidelines/guidelines-the-standard-sales-conditions-consumer-purchases-of-goods-the-internet#chapter-7).

If you have a valid reason to do so you can use the "Capture payment instantly" option from the "Vipps/MobilePay recurring payments" settings in your product's settings.

= When I use recurring payments and a renewal happens, why is the order on hold? =

This is because when an order is charged in Vipps MobilePay it takes 2 days before the payment has been fully captured from the customer's bank account.

After 2 days it will move to the "Processing" status. You can however change the behaviour of this by using the "Default status to give pending renewals" option in the plugin settings.

Alternatively you could look into using WooCommerce "Early renewals": [https://docs.woocommerce.com/document/subscriptions/early-renewal/](https://docs.woocommerce.com/document/subscriptions/early-renewal/) if ensuring the status of a charge is fully completed before a specific date is of up-most importance.

= Firewall ports =
Ensure *outgoing* traffic to port 443 is open. This is used to communicate with Vipps MobilePay servers.

= Does Vipps MobilePay offer a test environment for Vipps MobilePay for WooCommerce? =

Yes, but you will need a separate account, and you will need to install a special test version of the Vipps/MobilePay app, available trough Testflight. For your test account, the keys will be at https://portal-test.vipps.no; you will configure these in the developer mode settings.

Contact Vipps MobilePay for access to the test app. This app must be installed on a device that does not have the normal Vipps/MobilePay app installed, or there will be conflicts.

To use test mode, switch "Developer mode" on. There you can input the test keys from portal-test.vipps.no and turn test mode on and off.

If this isn't practical for your usage, we recommend that you "test in production" with a small amount, like 2 NOK. Just refund or cancel the purchase as needed.


= What are the requirements? =
* WooCommerce 3.3.4 or newer is required
* PHP 7.4 or higher is required.
* An SSL Certificate is required.

= Filters and Hooks for customization =
There are several filters and hooks you can use to customize the behaviour of this plugin:
 * Filter: 'woo_vipps_is_available': Takes a boolean availability argument and the gateway and must return true or false
 * Filter: 'woo_vipps_express_checkout_available': Takes a boolean availability argument and the gateway and must return true or false. 
 * Filter: 'woo_vipps_cart_express_checkout_button': Receives a complete button text and the URL needed to proceed to the express checkout page.
 * Filter: 'woo_vipps_express_checkout_banner': Receives a message with an express checkout button and an URL for the same, should return a message for the express checkout banner normally shown on the checkout page
 * Filter: 'woo_vipps_buy_now_button': Takes HTML for the button, and optionally product id, variation id, sku and if the button is to be shown as disabled by default
 * Filter: 'woo_vipps_show_express_checkout' - Takes a boolean, returns whether or not to show the express checkout button
 * Filter: 'woo_vipps_show_single_product_buy_now' - Takes a boolean and a product, returns true if the product should show a 'buy now with vipps' button
 * Filter: 'woo_vipps_show_single_product_buy_now_in_loop' - Like above, but especially for products shown in the loop - catalog pages, archives and so forth
 * Filter: 'woo_vipps_spinner': takes one argument which is a 'wait' spinner for certain pages
 * Filter 'woo_vipps_express_checkout_shipping_rates' which replaces the 'woo_vipps_shipping_methods'. It takes a list of shipping methods, and order, and a cart. The format of the shipping methods is an array of 'rate' which is a WC_Shipping_Rate object, 'priority' which is an integer and the sort-order Vipps MobilePay will use to display the alternatives, and 'default', which is a boolean: This will be the default choice
 * Filter: 'woo_vipps_default_shipping_method' taking the default shipping method ID, a list of the shipping methods available (as a table from method id to WC_Shipping_Rate object) and the order. Return a shipping rate id, like 'local_pickup:5'
 * Filter:  'woo_vipps_vipps_formatted_shipping_methods'. This will take an array of the methods to be sent to Vipps MobilePay, formatted as required by Vipps MobilePay. This is mostly for debugging.
 * Filter: 'woo_vipps_shipping_callback_packages': Takes the 'packages' from the cart used to calculate shipping in the shipping details callback
 * Filter  'woo_vipps_express_checkout_final_shipping_rate': Takes an WC_Shipping_Rate object, the order, and the shipping info from Vipps MobilePay. Must return a WC_Shipping_Rate object which will be added to the order.
 * Filter: 'woo_vipps_country_to_code': Takes a country code and a country name.  Should return a two-letter ISO-3166 country code from a given country name
 * Filter: 'woo_vipps_show_capture_button': Takes a boolean and an order and returns whether or not to show the capture button in the backend
 * Filter: 'woo_vipps_captured_statuses': Returns a list of the statuses for which Vipps MobilePay should try a capture when transitioning to them.
 * Filter: 'woo_vipps_transaction_text': Takes a transaction text and an order object, must return a text to be passed to Vipps MobilePay and displayed to the user along the lines of "Please confirm your order"
 * Filter: 'woo_vipps_special_page_template': Takes a (complete) template path as returned by locate_template and the ID of the Vipps MobilePay special page, should return a new template path (using locate_template or similar).
 * Filter: 'woo_vipps_order_failed_redirect': Takes an empty string or an url and an order id. If URL is returned, go there on cancelled or failed orders.
 * Filter: 'woo_vipps_product_supports_express_checkout': Takes a boolean and a product, returns true if the product can be bought with express checkout
 * Filter: 'woo_vipps_cart_supports_express_checkout': Takes a boolean and a cart, returns true if the cart can be bought with express checkout
 * Filter: 'woo_vipps_express_checkout_supported_product_types': Returns a list of product types (as strings) that can be bought with express checkout
 * Filter: 'woo_vipps_orderid': Takes default Vipps MobilePay orderid, the order prefix, and an order object. Must return an unique (at Vipps MobilePay) order ID with 30 chars or less. Default is the prefix + orderid, e.g. 'Woo364'.
 * Action: 'woo_vipps_shipping_details_callback_order': Takes an order-id and the corresponding vipps order id. Run at the start of the shipping methods callback.
 * Action: 'woo_vipps_restoring_cart': Takes an order and a saved cart contents array, ran after the order has failed or is aborted
 * Action: 'woo_vipps_cart_restored':  Runs after the cart has been restored after the order has been aborted of failed
 ' Action: 'woo_vipps_cart_saved': When redirecting to Vipps MobilePay, the cart is saved so it can be restored in case the order isn't completed. This action is ran after this has happened.
 * Action: 'woo_vipps_before_redirect_to_vipps': Takes an order-id, called at the end of process_payment right before the redirect to Vipps MobilePay
 * Action: 'woo_vipps_before_create_express_checkout_order': Takes the cart to do express checkout for, run before the order is created 
 * Filter : 'woo_vipps_create_express_checkout_cart_contents': Takes a cart contents array from which an express checkout order will be created . Should return a like array.
 * Action: 'woo_vipps_express_checkout_order_created': Takes an order ID, run right after an express checkout order has been created, but before it is processed'
 * Action: 'woo_vipps_before_process_payment': Takes an order-id, called at the start of process_payment
 * Action: 'woo_vipps_wait_for_payment_page': Run on the page shown on return from Vipps MobilePay
 * Action: 'woo_vipps_express_checkout_page': Run on the express checkout page, before redirect to Vipps MobilePay
 * Action: 'woo_vipps_set_order_shipping_details': Takes an order object, shipping details from Vipps MobilePay and user details from Vipps MobilePay. Runs after shipping details have been added to the order on return from express checkout.
 * Action: 'woo_vipps_callback': Runs when Vipps MobilePay does the callback on a successful payment, takes Vipps MobilePay data as input. Useful for logging/debugging the callback.
 * Action: 'woo_vipps_express_checkout_get_order_status': Takes the order status returned by Vipps MobilePay - called when the Vipps MobilePay callback hasn't happened and we need the order status. Useful for logging.
 * Action: 'woo_vipps_vipps_callback': Is ran when the Vipps MobilePay callback happen, with the decoded and raw POST from Vipps MobilePay. Useful for logging. 
 * Action: 'woo_vipps_shipping_details_callback': Is ran when Vipps MobilePay does the shipping details callback on express checkout. Takes decoded and raw POST from Vipps MobilePay, and the callback args. For debugging.
 * Action: 'woo_vipps_shipping_details_before_cart_creation': Run after order is updated but before a cart is created to do shipping calculations. Takes an order, The order-id at Vipps MobilePay and the callback arguments from Vipps MobilePay
 * Filter: 'woo_vipps_transaction_text_shop_id': This is used to identify your shop in the transaction texts sent to Vipps MobilePay (and shown to the user). Default is home_url(), but there is a length limit, so this filter allows you to keep it short.

= Shortcodes =
 * [woo_vipps_express_checkout_button] will print the express checkout button if valid
 * [woo_vipps_express_checkout_banner] will print the express checkout banner normally shown on the checkout page for non-logged-in users
 * [woo_vipps_buy_now sku=<SKU> id=<productid> variant=<variant id>] prints a "buy now" button given a SKU or an (product or variant) id. Just the SKU is sufficient.

== Extending the Order Management API integration ==
From version 1.10.0, this plugin implements the Vipps MobilePay Order Management API, sending a receipt to the customers' app, and sending the order confirmation link as the Order Confirmation link category.  You can, using this api, send over an image and a link for the categories receipt (RECEIPT), ticket (TICKET), shipping (DELIVERY), booking (BOOKING) and a general category (GENERAL).

For instance, if you have a page or url for tracking shipping, you can add this to the customers' app by extending the 'woo_vipps_add_order_categories' filter like so:

   `add_filter('woo_vipps_add_order_categories', function ($categories, $order, $gateway) {
       $shippingpagedata = array(
         'link' => <your shipping URL here>, 
         'image' => <filename or attachment ID of your illustration for shipping here, if required>,
         'imagesize' => <for attachments, the image size to use>);
       $categories['DELIVERY'] = $shippingpagedata;
       return $categories;
   }, 10, 3);`

You can similarily send ticket information (with e.g. a QR code) for the TICKET or BOOKING category and so forth.

= Javascript filters and actions =
From version 1.1.13 you can also modify the javascript using the new WP hooks library for javascript:
 * 'vippsBuySingleProduct' - action which is run whenever a customer tries to buy a single product using express checkout
 * 'vippsBuySingleProductCompatMode' - filter which should return true or false, if true, the compatibility mode action will be run instead of the standard ajax.
 * 'vippsBuySingleProductCompatModeAction' - filter which should return a javascript function to run when buying a product and compatibility mode is on. Will normally press the "Buy" button for you.
 * 'vippsRemoveErrorMessages' - runs when Vipps MobilePay error messages are to be removed.
 * 'vippsErrorMessage' - runs for every Vipps MobilePay error message added with Javascript. Takes the message as an argument
 * 'vippsAddErrorMessage' - runs when an error message is about to be added. Takes the message as an argument
 * 'vippsInit'  - runs when a page with a Vipps MobilePay button is initialzed
 * 'vippsStatusCheckErrorHandler' - A filter that should return function taking a statustext and an error object. It receives the default error handler, and is called when checking the order status with ajax for some reason ends up in an error.

== Changelog ==
= 2026-03-xx Version 5.3.0 =
Aborted Vipps MobilePay orders are now "failed" instead of "cancelled" and can be retried as a new Vipps MobilePay session
Error that occasionally sent the user to the homepage instead of Checkout fixed

= 2026-03-11 Version 5.2.2 =
Fix CSS issues in the standard Checkout

= 2026-03-09 Version 5.2.1 =
Fix: Only show the recurring Checkout when there's a recurring product in your shopping cart.

= 2026-03-02 Version 5.2.0 =
Bump required versions of php, WP and Woo to reasonably modern versions
Fixed all blocks to be compatible with the new iframe based block editor
Ensure Pickup Locations are editable if any are defined since these are available in Express and Checkout
Make the new interactivity based minicart work correctly with Checkout

= 2026-02-23 version 5.1.6 =
Fix customer prefill in Checkout
Add block to support new interactivity-API based minicart for Express Checkout
Handle swedish and finnish phone numbers correctly when canonicalizing
Support Tutor LMS in Express and Checkout
All blocks updated to version 3 
Show Express Checkout button in cart if the settings say so, even with Vipps Checkout active
Suppress permission warnings

= 2026-02-11 version 5.1.5 =
Suppress REST warning about permission callback

= 2026-02-04 version 5.1.4 =
Fix translations
Fix customer prefill in checkout when customer is known

= 2026-02-04 version 5.1.3 =
Fix: javascript crash in backend

= 2026-02-03 version 5.1.2  =
Allow the Buy Now block to be inserted in all contexts, providing a button to buy an arbitrary product via Express on any page
Minor improvements for compatibility for translation plugins
Improve error-handling when a shipping method has been paid for in Vipps MobilePay Express, but cannot be added to the Woo order

= 2026-01-19 version 5.1.1  =
Fix link to the settings-page for the login app
Fix rendering of Buy-Now block in Product Collections block
Improve user interface of Buy-Now block
Style improvements for the express checkout block
Improve logging for shipping for express checkout

= 2026-01-06 version 5.1.0  =
Fix errors preventing order completion in certain situations using Checkout and Klarna Payments
New option in settings/advanced to modify phone numbers in Express or Checkout so that they either get an added "+" or the country prefix is removed
New, more space-efficient buttons and a new button configurator interface

= 2025-12-16 version 5.0.14  =
Fixed fatal typo in express checkout
= 2025-12-16 version 5.0.13  =
Fix: language in Checkout when using multilang plugins.
Fix: encoding issue in Express checkout.
Fix: manual Woo refund triggering Vipps MobilePay refund when the entire remaining amount is refunded

= 2025-11-24 version 5.0.12  =
Added new filter for adding options to Express Checkout, 'woo_vipps_modify_express_checkout_rate'. This for adding options like delivery times etc to your custom shipping methods and rates

= 2025-11-17 version 5.0.11  =
Added filters for selecting "user language" when necesssary
Improved handling of chosen shipping method for Checkout
Improving Mailchimp integration when using Express Checkout

= 2025-11-10 version 5.0.10  =
Fixed expiry of checkout sessions when WooCommerce times out an order
Added support for TranslatePress to "get customer language"
Changed headers for Express Checkout orders in the API to allow for better logging

= 2025-10-27 version 5.0.9 =
Fixed incompatibility notice in the checkout block

= 2025-10-20 version 5.0.8 =
Fix for Checkout Posti shipping method crash.
Fix a bug where a log line did not show order id correctly.

= 2025-10-13 version 5.0.7 =
New feature for deleting all settings when deactivating plugin (under settings/advanced).
Changed loading of certain web component scripts to async.
Fix more logging print bugs

= 2025-10-10 version 5.0.6 =
Fix a bug in a rare logging print

= 2025-09-30 version 5.0.5 =
Handle edge-case for rounding of shipping prices in checkout and express

= 2025-09-30 version 5.0.4 =
Make express checkout provide addresses for virtual orders again (if requested)

= 2025-09-22 version 5.0.3 =
Properly handle shipping when coupons are added
Fix spurious error logs when express checkout orders are cancelled.

= 2025-09-15 version 5.0.2 =
Fix Checkout widgets on mobile
Fix phone numbers being stripped when using external payment methods

= 2025-09-01 version 5.0.1 =
Fix buttons for Express for finland

= 2025-09-01 version 5.0.0 =
Fix for session handling for shipping in Express checkout
Now supports New Express Checkout, including support for pickup locations

= 2018.06 version 1.0 =
Changelog trunated -- see payment/changelog.txt for the full log

