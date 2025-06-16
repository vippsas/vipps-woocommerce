=== Pay with Vipps and MobilePay for WooCommerce ===
Contributors: wphostingdev, everydayas, iverok, perwilhelmsen, nikolaidev, lassepladsen, marcuz2k2k
Author: WP Hosting, Everyday AS
Author URI: https://www.wp-hosting.no/
Tags: woocommerce, vipps, mobilepay, recurring payments, subscriptions
Version: 4.1.6
Stable tag: 4.1.6
Requires at least: 6.2
Tested up to: 6.8.1
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 3.3.4
WC tested up to: 9.9.3
License: MIT
License URI: https://choosealicense.com/licenses/mit/
Official Vipps MobilePay payment plugin for WooCommerce.

== Description ==
*Official Vipps MobilePay payment plugin for WooCommerce. Let your Norwegian, Danish, and Finnish customers pay with Vipps and MobilePay for an easy, fast, and familiar shopping experience* 

Vipps is used by more than 77 % of Norway's population (4.2 million users).

MobilePay is used by more than 75 % of Denmark's population (4.4 million users), and approximately 50 % of Finland's population (2.8 million users).

Vipps and MobilePay are payment methods offered by Vipps MobilePay. 

When you enable this plugin, you will choose between offering either Vipps or MobilePay as a payment method for your customers - hence "Vipps/MobilePay" going forward.

This is the official plugin for Vipps/MobilePay Checkout, Vipps/MobilePay ePayments (*Vipps Nettbetaling*), Vipps Express Checkout (*Vipps Hurtigkasse*) and Vipps/MobilePay recurring payments. Increase your conversion rate by letting your customers choose Vipps/MobilePay directly in the checkout or even do an Express Checkout (Vipps only) from the cart or a product page directly.

You can also do important back office tasks such as capture and refund directly from WooCommerce. Easy for your customer and easy for you.

Read [information from Vipps](https://developer.vippsmobilepay.com/docs/plugins/woocommerce/) about the plugin.

=== Vipps/MobilePay Checkout ===
With Vipps/MobilePay Checkout enabled in the plugin, you will get a complete checkout in your webshop, designed by Vipps MobilePay. It contains regular Vipps/MobilePay payments, a card payment option for those that can't or won't use Vipps/MobilePay, as well as the ability to get the shipping address of the customer in an easy way. Read more about [Vipps Checkout here](https://vippsmobilepay.com/en/online/checkout)

=== Vipps/MobilePay ePayment ===
When you enable this plugin, your customers will be able to choose Vipps/MobilePay as a payment method in the checkout. There is no need to go via a third party payment method. If your customer choose Vipps/MobilePay, they fill in their name and address and is then asked to enter their phone number in the Vipps/MobilePay dialogue. They then confirms the payment in the Vipps/MobilePay app. Customer info like name and address is sent to the store from Vipps MobilePay.

== Vipps/MobilePay recurring payments ==

Vipps/MobilePay recurring payments is perfect for you if you run a web shop with subscription based services or other products that would benefit from subscriptions.

With Vipps/MobilePay recurring payments you can:

* Sell recurring products (virtual and physical)
* Offer subscription services

See [How it works](https://developer.vippsmobilepay.com/docs/APIs/recurring-api/how-it-works/recurring-api-howitworks/) for an overview.

Recurring payments requires [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) and a Vipps MobilePay MSN with recurring payments added.

=== Mobilepay Reservations are currently for 14 days ===
When a payment is completed with Vipps Mobilepay, the money will be reserved, but only transferred to the merchant when the order is set to "Complete" or the money is captured manually. *For Mobilepay, this reservation period is 14 days*, so you will need to ship and fulfill orders before this; or to make an agreement with the customer to capture the money before this period is over. For Vipps, the period is 180 days. For payments made by credit card in Vipps/MobilePay Checkout, the period can again be as short as 7 days.
For details, please read the [developer FAQ](https://developer.vippsmobilepay.com/docs/knowledge-base/reserve-and-capture/#reserve-and-capture-faq).

If the order only contains virtual and downloadable products, the plugin will capture the order automatically and set the order to "Completed" as is the standard WooCommerce rule.

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
* Sign up in the [Vipps MobilePay portal](https://portal.vippsmobilepay.com) and choose your product.
* After 1-2 days you will get an email with login details to Vipps Business Portal, where you can get the API credentials
* Download and install the plugin
* Configure the plugin

=== How to install the plugin ===
1.  Install the plugin using WordPress’ [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the WooCommerce Settings page and choose Payment Gateways (Betalinger) and enable Vipps.
4. Go the settings page for the Vipps plugin and enter your Vipps account keys. Your account keys are available in the Vipps Business Portal (detailed info in the section below)


== Installation ==
1.  Install the plugin using WordPress’ [built-in installer](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins). The plugin can also be installed manually by upload the plugin files to the /wp-content/plugins/ directory. 
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to the WooCommerce Settings page and choose Payment Gateways (Betalinger) and enable Vipps.
4. Go the settings page for the Vipps plugin and enter your Vipps account keys. Your account keys are available in the Vipps Business Portal (detailed info in the section below)

=== How to get Vipps account keys from Vipps Business Portal ===
1. Sign in to the Vipps Portal at [https://portal.vippsmobilepay.com/](https://portal.vippsmobilepay.com/) using Bank ID
2. Select the "Utvikler" ("Developer") tab and choose Production Keys. Here you can find the merchant serial number (6 figures)
3. Click on "Show keys" under the API keys column to see “Client ID”, “Client Secret” and “Vipps Subscription Key”

== Screenshots ==
1. Enable Vipps Checkout as your checkout.
2. Enable Vipps as a payment method.
3. Enter your Vipps account keys and configure the plugin.
4. Setup and activate the recurring payment gateway in WooCommerce.
5. Configure the plugin settings for recurring payments.

== Contributing on Github ==
This project is hosted on Github at: https://github.com/vippsas/vipps-woocommerce

== Upgrade Notice ==
Version 4.1.6
Added support for All Products for WooCommerce Subscriptions
Version 4.1.4,4.1.5
Handle issue with tax being an empty string for free shipping methods
Version 4.1.3
Make CSS for Checkout widgets more specific
Version 4.1.2
Put customer order notes from Checkout in the correct place
Version 4.1.1
Hotfixes for the recurring subsystem; fix for a possible memory issue
Version 4.1.0
Optionally support coupons and order notes in Vipps Checkout 
Version 4.0.15
Fix small bug in shipping handling in the cart
Version 4.0.14
Support Pickup Locations in Vipps MobilePay Checkout using the new pickup locations API
Version 4.0.13
Support recalculation of order value in Vipps Checkout when address/customer information changes, recalculating correct VAT in certain situations.

== Frequently Asked Questions ==

= In which countries can I use Vipps/MobilePay? =
Vipps is currently only available in Norway (77 % user adoption in 2022)
MobilePay is currently only available in Denmark and Finland (75 % and 36 % user adoption in 2022, respectively).

= How can I get help if I have any issues? =
For issues with your WooCommerce installation you should use the support forum here on wordpress.org. For other issues you should contact Vipps MobilePay.

= Why are orders put on-hold and not processing or completed? =
This was the old default of this plugin until version 2.0. The newer default is now 'processing', but this also means that orders that are 'processing' will have their payment reserved, but not transferred to the merchant. You can still choose the status on-hold in the options screen.

If you choose on-hold, orders with this status will have the payment reserved, but not yet transferred to the merchant. The money must be 'captured' before they are actually transferred to the merchant. You are normally only allowed to do this at the same time as the order is shipped. You can 'capture' the money explicitly on the order screen; but the money will be captured automatically when the order is set to "Processing" or "Complete".

If you use the default or choose "processing", the same applies to this status: The order will be reserved, but not captured. You can do the capture manually, or it will automatically happen when the order is set to "Complete". Please note that you should ensure that your workflow is then so that the order is captured just before the package is shipped.

There is an exception for orders where all items are both virtual and downloadable: These are not considered to need processing and will be captured automatically (and go directly to the 'Complete' status). It is possible to customize this property for your needs using the woocommerce_order_item_needs_processing filter.

= Can I refund orders or part of orders using Vipps/MobilePay =
Yes, you can do refunds, including partial refunds, using the standard WooCommerce mechanism (https://docs.woocommerce.com/document/woocommerce-refunds/). Additionally, if you cancel an order that was already captured, the money will be refunded for the whole order if the order is not too old. For older orders, you must use the refund mechanism explicitly. This is a safety feature.
 If automatic refund through the Vipps API should fail, you will need to refund manually; in this case an error message to this effect will be displayed and the order annotated.

= What is 'compatibility mode' in the settings? =
Some plugins add new features to products or entirely new product types to WooCommerce; which the 'Express Checkout' function may not be able to handle. It can be possible to fix this using hooks and filters, but if you choose this feature, express checkout will be done in a different manner which is very much more likely to work for a given plugin. The cost is that the process will be _slightly_ less smooth.

= Why is my shipping wrong when using express checkout? =
It may be that the shipping method you are using some how does not work when calculated from the Vipps app, where the customer is somewhat anonymous. However, since version 1.4.0 this problem ought to be greatly reduced, so if you still have this problem, report this on the forum and we'll try to fix it. 

If you have shipping methods that add additional information on the 'normal' checkout page they will not be able to provide that information to Express Checkout plugin, since that page is bypassed. You may be able to add those options on a different page; but you may want to remove those options when using Express Checkout.

Formerly, there was a filter used to work around this, namely 'woo_vipps_shipping_methods'. This still works, but if you use it, it will disable the 'new' shipping method calculation. You may still customize the Express Checkout shipping; the new filter is called 'woo_vipps_shipping_rates'.

To be sure, you should test your shipping methods in Express Checkout before going live.

= I'd like to use sequential order numbers at Vipps instead of the WooCommerce order-ids using a sequential order number plugin. Does this plugins support that?
Yes, though you need to ensure that the order-id's you produce like this are unique for your Vipps account, and you currently have to use a filter in your themes' functions.php file. We recommend using a prefix for your order ids, so a filter that will work with sequential order numbers would look like

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
Ensure *outgoing* traffic to port 443 is open. This is used to communicate with Vipps servers.

= Does Vipps offer a test environment for Vipps for WooCommerce? =

Yes, but you will need a separate account, and you will need to install a special test version of the Vipps app, available trough Testflight. For your test account, the keys will be at https://portal-test.vipps.no; you will configure these in the developer mode settings.

Contact Vipps for access to the test app. This app must be installed on a device that does not have the normal Vipps app installed, or there will be conflicts.

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
 * Filter 'woo_vipps_express_checkout_shipping_rates' which replaces the 'woo_vipps_shipping_methods'. It takes a list of shipping methods, and order, and a cart. The format of the shipping methods is an array of 'rate' which is a WC_Shipping_Rate object, 'priority' which is an integer and the sort-order Vipps will use to display the alternatives, and 'default', which is a boolean: This will be the default choice
 * Filter: 'woo_vipps_default_shipping_method' taking the default shipping method ID, a list of the shipping methods available (as a table from method id to WC_Shipping_Rate object) and the order. Return a shipping rate id, like 'local_pickup:5'
 * Filter:  'woo_vipps_vipps_formatted_shipping_methods'. This will take an array of the methods to be sent to Vipps, formatted as required by Vipps. This is mostly for debugging.
 * Filter: 'woo_vipps_shipping_callback_packages': Takes the 'packages' from the cart used to calculate shipping in the shipping details callback
 * Filter  'woo_vipps_express_checkout_final_shipping_rate': Takes an WC_Shipping_Rate object, the order, and the shipping info from Vipps. Must return a WC_Shipping_Rate object which will be added to the order.
 * Filter: 'woo_vipps_country_to_code': Takes a country code and a country name.  Should return a two-letter ISO-3166 country code from a given country name
 * Filter: 'woo_vipps_show_capture_button': Takes a boolean and an order and returns whether or not to show the capture button in the backend
 * Filter: 'woo_vipps_captured_statuses': Returns a list of the statuses for which Vipps should try a capture when transitioning to them.
 * Filter: 'woo_vipps_transaction_text': Takes a transaction text and an order object, must return a text to be passed to Vipps and displayed to the user along the lines of "Please confirm your order"
 * Filter: 'woo_vipps_special_page_template': Takes a (complete) template path as returned by locate_template and the ID of the Vipps special page, should return a new template path (using locate_template or similar).
 * Filter: 'woo_vipps_order_failed_redirect': Takes an empty string or an url and an order id. If URL is returned, go there on cancelled or failed orders.
 * Filter: 'woo_vipps_product_supports_express_checkout': Takes a boolean and a product, returns true if the product can be bought with express checkout
 * Filter: 'woo_vipps_cart_supports_express_checkout': Takes a boolean and a cart, returns true if the cart can be bought with express checkout
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
 * Action: 'woo_vipps_express_checkout_get_order_status': Takes the order status returned by Vipps - called when the Vipps callback hasn't happened and we need the order status. Useful for logging.
 * Action: 'woo_vipps_vipps_callback': Is ran when the Vipps callback happen, with the decoded and raw POST from Vipps. Useful for logging. 
 * Action: 'woo_vipps_shipping_details_callback': Is ran when Vipps does the shipping details callback on express checkout. Takes decoded and raw POST from Vipps, and the callback args. For debugging.
 * Action: 'woo_vipps_shipping_details_before_cart_creation': Run after order is updated but before a cart is created to do shipping calculations. Takes an order, The order-id at Vipps and the callback arguments from Vipps
 * Filter: 'woo_vipps_transaction_text_shop_id': This is used to identify your shop in the transaction texts sent to Vipps (and shown to the user). Default is home_url(), but there is a length limit, so this filter allows you to keep it short.

= Shortcodes =
 * [woo_vipps_express_checkout_button] will print the express checkout button if valid
 * [woo_vipps_express_checkout_banner] will print the express checkout banner normally shown on the checkout page for non-logged-in users
 * [woo_vipps_buy_now sku=<SKU> id=<productid> variant=<variant id>] prints a "buy now" button given a SKU or an (product or variant) id. Just the SKU is sufficient.

== Extending the Order Management API integration ==
From version 1.10.0, this plugin implements the Vipps Order Management API, sending a receipt to the customers' app, and sending the order confirmation link as the Order Confirmation link category.  You can, using this api, send over an image and a link for the categories receipt (RECEIPT), ticket (TICKET), shipping (DELIVERY), booking (BOOKING) and a general category (GENERAL).

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
 * 'vippsRemoveErrorMessages' - runs when Vipps error messages are to be removed.
 * 'vippsErrorMessage' - runs for every Vipps error message added with Javascript. Takes the message as an argument
 * 'vippsAddErrorMessage' - runs when an error message is about to be added. Takes the message as an argument
 * 'vippsInit'  - runs when a page with a Vipps button is initialzed
 * 'vippsStatusCheckErrorHandler' - A filter that should return function taking a statustext and an error object. It receives the default error handler, and is called when checking the order status with ajax for some reason ends up in an error.

== Changelog ==
= 2025-06-16 version 4.1.6 =
Added support for All Products for WooCommerce Subscriptions

= 2025-06-11 version 4.1.5 =
More issues with empty string cost values handled

= 2025-06-10 version 4.1.4 =
For some shipping methods, tax, or cost, were returned as "" instead of "0" or 0, which caused fatal errors in both Express Checkout shipping handling and epayment sessions

= 2025-06-02 version 4.1.3 =
Make CSS for Checkout Widgets more specific, add hook for enqueing scripts for checkout

= 2025-06-02 version 4.1.2 =
Ensure customer order notes from Checkout ends up in the right place

= 2025-05-27 version 4.1.1 =
Hotfixes for the recurring subsystem

= 2025-05-26 version 4.1.0 =
Support order notes and coupons in Vipps Checkout. This can be turned off in the Vipps Checkout settings.
Developers can add more widgets using filters; including for actions that modify the session.

= 2025-05-12 version 4.0.15 =
Fix small bug in shipping handling in the cart(s).

= 2025-05-12 version 4.0.14 =
Support Pickup Locations in Vipps MobilePay Checkout

= 2025-05-05 version 4.0.13 =
Support recalculation of order value in Vipps Checkout when address/customer information changes, recalculating correct VAT in certain situations.

= 2025-04-23 version 4.0.11, 4.0.12 =
Add support for pickup points, lead time and home delivery timeslots in Vipps Checkout

= 2025-04-09 version 4.0.10 =
Fix render bug in the shortcode woo_vipps_buy_now.

= 2025-04-08 version 4.0.9 =
Support pickup-points in Vipps Checkout.
A new filter,
`$pickup_points = apply_filters('woo_vipps_shipping_method_pickup_points', [], $rate, $shipping_method, $order);`
will allow merchants to add pickup points to shipping methods, as arrays with keys id, name, address, city, postalCode and country. It is also possible to add an array of openingHours (as strings).

= 2025-04-08 version 4.0.8 =
Various fixes in recurring-subsystem

= 2025-03-24 version 4.0.7 =
Slight improvements in order summary handling

= 2025-03-19 version 4.0.6 =
Added the QR api for Mobilpay sites
Minor changes to texts 

= 2025-02-17 version 4.0.5 =
Fix webhooks handling for some edge cases
New and improved Wizard for new users

= 2025-02-04 version 4.0.4 =
Ensure WooCommerce uses the singleton for the payment gateway
Protect against errors when cancelling non-captured amounts from completed orders.

= 2025-02-03 version 4.0.3 =
Fix webhooks-initializing when recurring is present, and provide better feedback when testing connection to Vipp

= 2025-01-20 version 4.0.2 =
Fixes for crash on after_plugin_row

= 2025-01-20 version 4.0.1 =
Recurring updated to  2.1.4 

= 2025-01-13 version 4.0.0 =
This version integrates the Vipps MobilePay Recurring Payments plugin, adding support for recurring payments. (Recurring payments requires WooCommerce Subscriptions and a Vipps MobilePay MSN with recurring payments added).
Fixes some spurious warnings

= 2024-12-18 version 3.0.9 =
Fix wrapper of Express Checkout button on the terms-and-condition page
Preliminary Swedish translations

= 2024-12-09 version 3.0.8 =
Support for the Product Collection block with the new Buy-now block for Vipps Express checkout. This new block being standard from Woo 9.5, support for the old "All products" block is removed. The other legacy collection blocks are still supported, since the framework for those is rather easier to maintain. If you are using the All Products block and want support for Express Checkout, we suggest moving on to the new Product Collection block.

= 2024-12-02 version 3.0.7 =
If an order has been edited so that its value is less than the reserved amount, cancel the rest of the reserved amount after capture
Also, if an order that has not been captured yet needs repayment (in the Processing state) we now allow this. The money can only be refunded after capture, but in this case we will release the "refunded" money as soon as the order has been set to "complete".
The On-Site messaging badge block will now use the 3.0 Block API

Change epayment_cancel_payment logic to match documentation
Update the Badge block to the latest specifications and enable it for MobilePay

= 2024-11-18 version 3.0.6 =
Two extremely dumb errors fixed that interacted with 3.0.5 to disable Vipps Checkout. Sorry.

= 2024-11-18 version 3.0.5 =
Fixes compatibility issues with Checkout, Checkout for Recurring, WooCommerce Subscriptions and Gutenberg Checkout block

= 2024-11-12 version 3.0.4 =
Fixes an issue with too small images being uploaded to Vipps for orders
Does actions requiring translations in after_set_theme instead of plugins_loaded to avoid triggering "doing it wrong"-errors in WP 6.7.

= 2024-10-28 version 3.0.3 =
Fix issue with orders with zero-value fees
Fix issue with not being able to turn off Klarna in Checkout
Change settings-setup to require country to be selected first

= 2024-10-14 version 3.0.2 =
Fix support of Klarna Payments as an external payment method

= 2024-10-07 version 3.0.1 =
The Gutenberg Checkout block has changed it's treatment of the return value of Process Payment, which is now required to be an array (earlier documentation specified a NULL) even in case of errors. This caused problems with Vipps/Mobilpay to get turned into fatal errors; fixed in this version.

= 2024-09-16 version 3.0.0 =
In this version, we are introducing an all-new settings screen reached from the Vipps Mobilpay menu. The old settings page will redirect to this. It should look and feel familiar, but we're going to use this page to hopefully improve the configuraton experience as the features improve and the settings grow more complicated.
We also support the new block-based product editor from this version on.

To be able to do this, we are increasing the required version of Wordpress to version 6.2. If you are unable to upgrade wordpress to this version, you can still download versions from the 2.1.x branch on wordpress.org - but we'll only add essential and security fixes to this branch.

= 2024-09-10 version 2.1.10 =
Fix CSS on QR-code page

= 2024-09-09 version 2.1.9 =
Handle coupons for Vipps Checkout

= 2024-08-26 version 2.1.8 =
Change API to use taxRate instead of taxPercent to allow for VAT change in Finland 1. sep 2024.

= 2024-08-16 version 2.1.7 =
Allow Klarna as external payment method in Checkout
Fix issue with session handling that would break shipping in Checkout in Woo 9.2

= 2024-08-15 version 2.1.6 =
Stop refunding cancelled orders when they are older than 30 days as a safety measure. This can be changed by the 'woo_vipps_cancel_refund_days_threshold' filter.

= 2024-07-29 version 2.1.5 =
Fix error handling when receiving callbacks to unknown orders
Fix trying to use Woos logger when woo hasn't been loaded yet

= 2024-06-18 version 2.1.4 =
Fix untranslateable string and a sprintf format string with a bug in it (Thanks Knut Sparhell for reporting)

= 2024-06-10 version 2.1.3 =
Fix annoying regression where VippsCheckout would trigger the "Unknown order" branch on the thank you page

= 2024-06-07 version 2.1.1, 2.1.2 =
Fix issue where session was not active when computing checkout fields
Fix previous fix for older php versions
Bump required php version to 7.0

= 2024-06-05 version 2.1.0 =
Removed support for Instabox in Vipps Checkout Shipping
Added support for external payment methods in some markets
Changed gateway registering to use class name instead of instantiated objects to prevent unintended breakage


= 2024-05-21 version 2.0.11 =
Fixed some utranslatable strings and changed MobilePay reservation time notices to 14 days

= 2024-04-22 version 2.0.10 =
Added nocache-headers for nginx
Fixed an issue that could delay order confirmations in rare situations
Added a failsafe for certain situations that could destroy sessions in shipping computation

= 2024-03-25 version 2.0.9 =
Fixed some places where NOK were hard-coded in as currency.

= 2024-03-18 version 2.0.8 =
Create a limit of 10 attempts to capture an order; do not call API after this. The order will be uncapturable. It is possible to reset this by pressing "Get complete transaction details" in the Vipps metabox for the order.
Fix in the logic for deleting webhooks

= 2024-03-11 version 2.0.7 =
Minor updates and language

= 2024-02-19 version 2.0.6 =
Made sure the filters for the_title on the checkout page works even with too few arguments
Added notice and warning for MobilePay that capture must be done within 14 days

= 2024-01-25 version 2.0.5 =
Add workaround for Orderline issue with Checkout
Use translate.wp.org for translations

= 2024-01-25 version 2.0.3, 2.0.4  =
Fix default payment status
More protection against issues where settings are wrong and the plugin tries to instantiate webhooks

= 2024-01-24 version 2.0.1, 2.0.2 =
Fix bug in uninstall hook and activation hook when settings are wrong in the database

= 2024-01-23 version 2.0.0 =
Support MobilePay as a payment method in Finland
Use the Epayment api for all transactions other than Vipps Express Checkout

= 2024-01-17 version 1.14.21 and 1.14.23 =
Disable support for order attribution by default - it can be added in the "Advanced" settings. Some sites got crashes due to memory use.

= 2024-01-16 version 1.14.21 and 1.14.22 =
Minor fix for 8.5.1 and express checkout

= 2024-01-15 version 1.14.20 =
Support Order Attribution in Vipps Checkout and Express Checkout

= 2023-12-14 version 1.14.19 =
Support for Mailchimp for WooCommerce, fixed regressions

= 2023-12-14 version 1.14.18 =
Debugging information added for situations where an order may be spuriously cancelled
Fix regression error in activate/deactivate actions

= 2023-12-13 version 1.14.17 =
Fix for issues with stored admin notices in newer woos, small css fix

= 2023-11-27 version 1.14.16 =
Minor fixes, refactoring Checkout support for further features

= 2023-10-16 version 1.14.15 =
Stop zeroing out addressline 2 in checkout
Fix polling when sessions are very long-lived in woo

= 2023-10-06 version 1.14.14 =
Fix sanitizion of output in Buy-Now button code; thanks to Darius Sveikauskas for reporting

= 2023-09-18 version 1.14.13 =
Check that images uploaded to receipts are either jpeg or png

= 2023-08-22 version 1.14.12 =
Fix edge cases where orders were wrongly cancelled in Vipps Checkout

= 2023-08-22 version 1.14.11 =
More filters/hooks: 
   `do_action('woo_vipps_before_thankyou', $orderid, $order);`
Runs before the thankyou page is reached, and can be used to finalize orders created using Checkout or Express Checkout, plus
   `apply_filters('woo_vipps_express_checkout_new_username', '', $email, $userdata, $order);`
Which can be used to customize the username for new users created using express checkout or Vipps Checkout.
Supports MailerLite – WooCommerce integration (woo-mailerlite) in Vipps Checkout
Fix crashing bug interaction with the Add-on WooCommerce – MailPoet plugin

= 2023-07-31 version 1.14.10 =
Handle the new Thankyou-page behavious in Vipps Checkout too, by adding a new option to register/log in users
Prepare to handle other Checkout flows

= 2023-07-19 version 1.14.9 =
Support Free shipping for Porterbuddy in Vipps Checkout
Fix issue with Woo 5.8.x and above where Express Checkout required email confirmation before the thankyou page was shown.

= 2023-06-22 version 1.14.8 =
Fix back button on express checkout

= 2023-06-12 version 1.14.7 =
Add protection against caches ignoring nocache-headers and rename the "limited session" parameter to something sane.

= 2023-05-31 version 1.14.6 =
Ensured admin notices do not crash newer versions of WooCommerce when Vipps is triggered with no current screen

= 2023.04.25 version  1.14.5 =
Changed descriptions on Bring shipping methods for Vipps Checkout
Fixed typo which made the "Confirm terms and conditions" screen always appear if started from the Cart

= 2023.03.16 version  1.14.4 =
Apparently some setup got called woocommerce_init_shipping more than once, which crashed on the new Checkout shipping methods.

= 2023.03.16 version  1.14.3 =
Bugfix for some shipping methods in Vipps Checkout

= 2023.03.13 version  1.14.2 =
Ensures that orders that don't need shipping do not ask for addresses from customers unless you explicitly want to.
Small bugfixes too.

= 2023.03.06 version 1.14.1 =
Set require_userInfo to false per default for Elemenor and other users of pre_handle_404
Bugfixes

= 2023.02.14 version 1.14.0 =
Support Vipps Checkout version 3 with extended support for shipping methods in Vipps Checkout, allowing for the selection of pickup points and more.
Remove default title on Vipps Checkout page
Added filter to support for extra consent checkbox in Vipps Checkout

= 2023.02.06 version 1.13.5 =
Add failsafe for rare bug affecting some external payment method purchases with Klarna Checkout

= 2023.01.25 version 1.13.4 =
Remove lookup of orderid based on Vipps-orderid from database to improve speed and remove issues with transients etc.

= 2023.01.02 version 1.13.3 =
Workaround for WooCommerce Smart Coupons bug
Let Vipps Checkout handle the WooCommerce endpoints for thankyou etc, for better Elementor compatibility
Support for Pixel Your Site-like mechanisms for Vipps Checkout

= 2022.12.21 version 1.13.2 =
Fix for a php8 issue with unset options for the badge feature

= 2022.12.13 version 1.13.1 =
Changes required for newer version of epayment-api
Changes in localization for blocks

= 2022.12.12 version 1.13.0 =
Added support for HPOS (https://woocommerce.com/document/high-performance-order-storage/)
Added protection from Vipps being ran while WooCommerce is deactivated

= 2022.11.28 version 1.12.1 =
Added support for ecommerce-tracking in GAv4 and AdWords for Monster Insights, plus extra support for Express Checkout for Pixel Your Site.

= 2022.11.21 version 1.12.0 =
Added support for Vipps On-Site Messaging badges 

= 2022.11.09 version 1.11.7 =
Fix issue with duplicate order ID's, but for real this time

= 2022.11.07 version 1.11.6 =
Fix issue with duplicate order ID's

= 2022.10.24 version 1.11.5 =
Reorganization of banner code

= 2022.10.10 version 1.11.4 =
New option for turning off receipts, better support for tax-free shipping

= 2022.10.05 version 1.11.3 =
* Fix admin javascript bug that kept dismissible banners non-dismissed

= 2022.09.29 version 1.11.2 =
* Fix string concatenation bug crashing php8.x

= 2022.09.27 version 1.11.1 =
* Fix default-setting of new settings; the old method broke on php8.0

= 2022.09.26 version 1.11.0 =
* Improved settings-screen with a tabbed view and organization
* Filters for shipping options to add pickup-point, brand, description etc for shipping options not supported by standard Woo
* Adding option for dropping contact and address fields for Vipps Checkout

= 2022.09.19 version 1.10.3 =
* Fix _created_via so it will just say 'checkout' to be consistent with other payment gateways
* Fix transaction text so it does not refer to confirming the transaction; this is better because it is also displayed on order history page

= 2022.08.26 version 1.10.2 =
* Fix typo in version numbers and type of VAT percentage for order management API

= 2022.08.17 version 1.10.1 =
* Small change in Order Management API following changes to shipping handling

= 2022.07.04 version 1.10.0 =
* Added support for the Order Management Api, which stores the receipt and other order information in the customers' app. See the 'woo_vipps_add_order_categories' filter for extending the information passedd.

= 2022.06.28 version 1.9.3 =
* Added compatibility for Dibs/Nets Easy Payment gateway, which made certain untenable assumptionts
* Fix coupon handling in express checkout - thanks to @kimbertelsen for debugging

= 2022.06.20 version 1.9.2 =
* Make extra double sure we dont get session cookies in callbacks

= 2022.06.13 version 1.9.1 =
* Improve compatibility with shipping modules
* Fix breakage in Vipps support for the All Products block
* Change SVG buttons to current elements

= 2022.05.30 version 1.9.0 =
* Support for Vipps QR-api

= 2022.05.25 version 1.8.22 =
* Ensure the Snap Pixel for WooCommerce plugin does not break express checkout by outputting pixels when it shouldn't. Thanks to @optiflow at wp.org for detailed error reporting.
* Testing for WP 6.0.0

= 2022.04.07 version 1.8.21 =
* Protect "process_payment" from being called repeatedly when this is not allowed
* From Johnny Oskarsson:
    - Improve compatibility with headless themes
    - New filters for the data sent in `initiate_payment` and `initiate_checkout`, which allows for more control over the return URL which is especially important for headless themes.

= 2022.04.06 version 1.8.20 =
* Ensure alternative browsers work on mobile on Checkout so that "unknown order" does not happen on order return
* Ensure Vipps transaction references are at least 8 chars long

= 2022.03.24 version 1.8.19 =
* Fix race condition in code that checks order status when callback hasn't happened

= 2022.03.22 version 1.8.18 =
* Fix name errors in express checkout

= 2022.03.21 version 1.8.17 =
* Handle error case when shipping address is received empty in callbacks

= 2022.03.14 version 1.8.16 =
* Ensure Express Checkout and Checkout session handling still works for logged-in users in newer versions of Woo

= 2022.03.07 version 1.8.14 =
* Change payment method title to Credit Card if this was used on the Vipps Checkout page

= 2022.03.01 version 1.8.13 =
* Typo that accidentally destroyed the Woocommerce endpoints page fixed (thanks to @stivenson2005 at wp.org)

= 2022.02.28 version 1.8.12 =
* Support for Vipps Checkout alternative checkout page

= 2022.02.09 version 1.7.25 =
* Fix in the loading of a file that caused crashes when woocomerce was not active

= 2022.01.18 version 1.7.24 =
* Minor bugfixes 

= 2021.12.20 version 1.7.23 =
* Minor bugfixes

= 2021.12.13 version 1.7.22 =
* Support variations where one of the dimensions is 'any'

= 2021.11.29  version 1.7.21 =
* Accept two-letter country names now being sent by Vipps
* Ensure Express Checkout orders are handled correctly by WooCommerces' cancel-unpaid-orders thing
* When checking Vipps orders stuck in 'pending', also cancel them if they don't exist etc
* Update the "partial orders" used for Express Checkout so they will work more like normal orders.
* Change how redirects to Vipps work to avoid having the back-button work badly on Safari

= 2021.11.22  version 1.7.20 =
* Adds a dismissible banner for Vipps Recurring Payments if it has never been installed and WooCommerce Subscriptions *is* installed.

= 2021.11.17  version 1.7.19 =
* Make sure failed orders get set to 'pending' when restarting payment with Vipps

= 2021.11.15  version 1.7.18 =
 Add support for Woocommerce Subscriptions manual renewals using a filter and a developer setting.
 Fix issue where customer was assumed to exist even when doing cron jobs for rescuing dead orders
 Add support for testing the callback handler remotely

= 2021.10.19  version 1.7.17 =
 Fix CSS issue caused by previous version

= 2021.10.18  version 1.7.16 =
 Support a more explicit, multi-step address selection flow in Express checkout, toggleable in the settings
 Small CSS fixes for certain themes

= 2021.10.05  version 1.7.15 =
 Fix various php8-related bugs

= 2021.10.04  version 1.7.14 =
 Add extra supports for plugins like Yith WooCommerce Name Your Price and move compatibility hacks into a separate file
 Support non-standard pretty permalinks by stopping redirect_canonical after a special page has been requested.

= 2021.09.13 version 1.7.13 =
 Ensure refunds of 0 NOK are not handled by Vipps

= 2021.08.23 version 1.7.12 =
 The cron job that checks the status of abandoned orders have since 1.7.10 restored these orders' session to check the status as correctly as possible.
It seems this may ruin the session of other active users however, and we can't have that, so now the status is checked without restoring the session.

= 2021.08.09 version 1.7.11 =
 Updating for latest versions of WP and Woo

= 2021.06.22 version 1.7.10 =
 Added a fail safe for situations where the Vipps callback fails and the customer does not return to the store

= 2021.06.14 version 1.7.9 =
 Tiny change in "No shipping required" setup

= 2021.05.18 version 1.7.8 =
 Fix error-handling when creating new customers in express checkout

= 2021.05.18 version 1.7.7 =
 Fix integration with Klarna Checkout so Vipps can be disabled as external payment method when appropriate

= 2021.04.19 version 1.7.6 =
 Version bump for new version of WooCommerce and Blocks

= 2021.03.29 version 1.7.5 =
 Increase priority of handling special pages to avoid 404-handlers in themes and plugins to handle them first

= 2021.03.24 version 1.7.4 =
 Fix the undismissable banner issue by ensuring browsers won't have cached the admin javascript

= 2021.03.22 version 1.7.3 =
 Remainder that the Login with Vipps plugin exists

= 2021.03.15 version 1.7.2 =
 Fixes javascript problem on express checkout screen that could be caused by plugin interactions

= 2021.03.01 version 1.7.1 =
 Compatibility with Woo 5.0.0 and WP 5.7.0

= 2021.01.25 version 1.7.0 =
 Stop using the deprecated payment status interface. This is a quite large rewrite that should be invisible to uses.

= 2021.01.18 version 1.6.9 =
 Bugfixes for user creation in express checkout

= 2021.01.05 version 1.6.8  =
 Bugfix for Gutenberg blocks

= 2020.12.22 version 1.6.7  =
 Bugfix for user login in express checkout

= 2020.12.18 version 1.6.6  =
 Bugfix for Woo gutenberg blocks (other than all products)

= 2020.12.14 version 1.6.5  =
 Login and user creation synchronized better with Login with Vipps
 Tested on newest versions

= 2020.11.25 version 1.6.4  =
 More sanitation added

= 2020.11.24 version 1.6.3  =
 Updated WP/Woo versions

= 2020.11.16 version 1.6.2  =
 Bugs fixed: undefined variable removed (thanks to kimmenbert @ github for reporting)
 Correct version of the plugin reported to the Vipps-api
 Ensure all products are in stock when using Express Checkout (thanks to lykkelig @ wp.org for reporting)

= 2020.11.02 version 1.6.1  =
 Bugs fixed:  WPML support reenabled thanks to a bug report by @kodeks, user creation improved thanks to @henmor

= 2020.10.19 version 1.6.0  =
 Integrate with Login with Vipps and provide again the "create users when using express checkout" checkbox. If you choose too, this will then create (and log in) users when using express checkout.
Only users without privileges will be logged in like that. If you install Login with Vipps, this will be set as the default choice.

= 2020.10.05 version 1.5.2  =
 Fixed deletion of cancelled orders; thanks to @alarsen2 for reporting

= 2020.09.28 version 1.5.1  =
 Fixed issue with payment_complete not being called. Thanks to espen @ nhg for reporting and debugging

= 2020.09.14 version 1.5.0  =
 Added support for WooCommerce Gutenberg Blocks: the Checkout block, the All Products blocks and other product blocks.

= 2020.07.29 version 1.4.11  =
 Added the WOOCOMMERCE_CHECKOUT constant to create_partial_order, because some plugins act differently on checkout and on normal page views.
 Improved compatibility with KCO external payments

= 2020.07.01 version 1.4.10  =
 Added yet another call to calculate_totals on the cart after the woocommerce_cart_loaded_from_session action

= 2020.07.01 version 1.4.9  =
Fixed a bug in the session-restore code for Express Checkout that could affect pricing of shipping
Added a do-action call to 'woocommerce_cart_loaded_from_session' in callbacks to allow dynamic pricing plugins to run their code

= 2020.06.29 version 1.4.8 =
 * Fixed a bug in express checkout shipping calculations where cart totals could be wrong 
 * Changed license from AGPLv3 (http://www.gnu.org/licenses/agpl-3.0.html) to MIT (https://choosealicense.com/licenses/mit/)
 * Added filter to remove Vipps as option in Klarna Checkout, and added a check for unsupported carts.
 * Added filter 'woo_vipps_payment_return_url' to allow plugins to add extra arguments to return URL
 * Changed display logic of WooCommerce status messages on Express Checkout screen to allow themes that rewrite these to not show error messages when they don't apply

= 2020.05.25 version 1.4.7 =
 * Support themes that use their own templating-mechanism by allowing the use of a real page ID to handle the Vipps special pages
 * Improve the race-condition avoidance code, and make it possible to implement real locking for systems that support it (open the developer settings to enable)
 * If Klarna Checkout uses Vipps as external payment gateway, ensure that Klarna Checkout is still the default payment method afterwards
 * Change order prefix to contain the sitename of the store if possible to aid with support

= 2020.04.27 version 1.4.6 =
 * More robustness and checks in callbacks

= 2020.04.27 version 1.4.5 =
 * Changed all uses of "WC_Cart" to use the main WC-cart
 * Changed the shipping callback to use the real cart from the Session; with no "temporary" cart created from the order
 * Changed cart saving and restoring for single product express checkout to use PHP serialization
 * Experimentally support the 'bundle' product type of WooCommerce Product Bundles

= 2020.04.06 version 1.4.4 =
 * Changed freight calculation to ensure plugins that override the WC_Cart's class will continue working.
 * Changed handling of signal file cleanup and deletion of cancelled express checkout orders to wp-cron to avoid problems on sites with heavy load

= 2020.03.23 version 1.4.3 =
 * Added support for Klarna Checkout based on the code provided by Krokedil at https://github.com/krokedil/klarna-checkout-vipps-external-payment-method
 * Added support for static shipping: Much quicker express checkout if your shipping method don't depend on the customers address (or if your customers are logged in.) This precalculates the shipping options before sending the user to Vipps, but be aware: All the options must be static (like fixed price and so forth).

= 2020.03.03 version 1.4.2 =
 * Tiny change to avoid logging non-errors if no shipping method has been chosen at all

= 2020.02.25 version 1.4.1 =
 * Bugfix: The template chooser mechanism caused WP_DEBUG to print out error messages if the option wasn't set.

= 2020.02.24 version 1.4.0 =
 * Properly round prices with wc_format_decimal (Thanks to Shattique @ netthandlesgruppen for reporting)
 * Ensure 'spinner' on page forwarding to Vipps is centered
 * New option in settings: Override the page template used for the Vipps special pages (choose 'full width' and so forth here.)
 * New shipping handling of shipping callbacks in Express Checkout makes shipping methods using meta data work - that is, all shipping methods that have added meta data for for instance integration with transport companies and so forth.t
 * DEPRECATION: The filter 'woo_vipps_shipping_methods' is _deprecated_ as of version 1.4.0. If you use it, it will continue to work as before, but it will disable the new Express Checkout Shipping mechanism, and thus will not support metadata in the shipping methods; which certain shipping methods need - in particular those with integration with other services. A notice will be printed on the admin screen, and an option will be shown in the settings that will allow you to silence this warning (and then everything will work as before) or to disable this filter if you prefer to use the new method.
 * New filter 'woo_vipps_express_checkout_shipping_rates' which replaces the filter above, taking the same arguments (a list of shipping methods, and order, and a cart). The format of the shipping methods is however different: They will consist of an array of 'rate' which is a WC_Shipping_Rate object, 'priority' which is an integer and the sort-order Vipps will use to display the alternatives, and 'default', which is a boolean: This will be the default choice
 * New filter 'woo_vipps_default_shipping_method' taking the default shipping method ID, a list of the shipping methods available (as a table from method id to WC_Shipping_Rate object) and the order. Return a shipping rate id, like 'local_pickup:5'.
 * New filter 'woo_vipps_vipps_formatted_shipping_methods'. This will take an array of the methods to be sent to Vipps, formatted as required by Vipps. This is mostly for debugging.
 * DEPRECATION: the filter 'woo_vipps_express_checkout_shipping_rate' will only be applied if you use the old method of doing shipping for express checkout; thus you will have to have overridden the  'woo_vipps_shipping_methods' filter too. This is replaced by the filter 'woo_vipps_express_checkout_final_shipping_rate', which takes an WC_Shipping_Rate object, the order and the shipping info from Vipps and must return a WC_Shipping_Rate object.
 * Replace use of file_get_contents with the WP http api methods, thus there is no longer any requirement for having allow_url_fopen true

= 2020.02.07 version 1.3.7 =
 * Improved error-handling in validate-express-checkout; thanks to lykkelig @wp.org

= 2020.02.03 version 1.3.6 =
 * Added protection for repeat orders from customers who don't get enough feedback that their order is complete
 * Added terms-and-conditions checkbox to the interstitial screen on Vipps Express Checkout
 * Added filters and hooks to facilitate validation of an express checkout order
 * Fixed fee calculations for carts in express checkout (thanks to Shattique @ netthandelsgruppen for reporting & fixing)

= 2020.01.22 version 1.3.5 =
 * Tested up to version 3.9.0 of WooCommerce and 5.3.2 of WP

= 2018.06 =
Initial version - changelog truncated
