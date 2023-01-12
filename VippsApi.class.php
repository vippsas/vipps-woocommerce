<?php
/*
   This is the VippsApi  class, a delegate in WC_Payment_Gateway that handles the actual communication with Vipps.
   The parameters are fetched from the containing class. IOK 2018-05-11


This file is part of the plugin Checkout with Vipps for WooCommerce
Copyright (c) 2019 WP-Hosting AS

MIT License

Copyright (c) 2019 WP-Hosting AS

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.


 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
require_once(dirname(__FILE__) . "/VippsAPIException.class.php");

class VippsApi {
    public $gateway;

    public function __construct($gateway) {
        $this->gateway = $gateway;
    }

    // These abstraction gets the correct client id and so forth based on whether or not test mode is on
    public function get_merchant_serial() {
        return $this->gateway->get_merchant_serial();
    }
    public function get_clientid() {
        return $this->gateway->get_clientid();
    }
    public function get_secret() {
        return $this->gateway->get_secret();
    }
    public function get_key() {
        $key = $this->gateway->get_key(); 
        return $key;
    }
    public function get_orderprefix() {
        return $this->gateway->get_orderprefix();
    }


    public function get_option($optionname) {
        return $this->gateway->get_option($optionname);
    }
    public function log($what,$type='info') {
        return $this->gateway->log($what,$type);
    }

    // Get an App access token if neccesary. Returns this or throws an error. IOK 2018-04-18
    public function get_access_token($force=0) {
        // First, get a stored token if it exists
        $stored = get_transient('_vipps_app_token');
        if (!$force && $stored && $stored['expires_on'] > time()) {
            return $stored['access_token'];
        }
        // Otherwise, get it from vipps - this might throw errors 
        $fresh = $this->get_access_token_from_vipps();
        if (!$fresh) return null;

        $at = $fresh['access_token'];
        $expire = $fresh['expires_in']/2;
        set_transient('_vipps_app_token',$fresh,$expire);
        return $at;
    }

    // Fetch an access token if possible from the Vipps Api IOK 2018-04-18
    private function get_access_token_from_vipps() { 
        $clientid=$this->get_clientid();
        $secret=$this->get_secret();
        $at = $this->get_key();
        $command = 'accessToken/get';
        try {
            $result = $this->http_call($command,array(),'POST',array('client_id'=>$clientid,'client_secret'=>$secret,'Ocp-Apim-Subscription-Key'=>$at),'url');
            return $result;
        } catch (TemporaryVippsAPIException $e) {
            $this->log(__("Could not get Vipps access token",'woo-vipps') .' '. $e->getMessage(), 'error');
            throw $e;
        } catch (Exception $e) {
            $this->log(__("Could not get Vipps access token",'woo-vipps') .' '. $e->getMessage(). "\n" . $e->getMessage(), 'error');
            throw new VippsAPIConfigurationException($e->getMessage());
        }
    }

    # Order Management API functions
    // 200, 400, 401, 404, 409, 409 invalid params also
    public function add_image ($image, $is_bytes=false){
        $command = 'order-management/v1/images/';
        $bytes = $image;


        if (!$is_bytes){
            if ($image && is_readable($image)) {
                $bytes = file_get_contents($image);
                if (!$bytes) {
                    $this->log(__("Could not read image file: ",'woo-vipps') .' '. $image, 'error');
                    return false;
                }
            }
        }

       

        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;

        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        // imageid =  ^[0-9A-Za-z-_\.] - 128 chars
        $imageid = hash('sha512',$bytes); // Yields 128 hex chars
        $base64 = base64_encode($bytes);
        $args = ['imageId'=>$imageid,'src'=>$base64,'type'=>'base64'];

        try {
            $res = $this->http_call($command,$args,'POST',$headers,'json'); 
            return $res['imageId'];
        } catch (Exception $e) {
            // Previous versions of the API returned 400 for duplicate images, future will use 409;
            // in both cases we can just return the imageid because of how we created it. IOK 2022-06-28
            $duperror = false;
            if (is_a($e, 'VippsApiException') && $e->responsecode == 400) {
                $msg = $e->getMessage();
                if (preg_match("!duplicate!i", $msg)) {
                   $duperror = true;
                }
            }
            if (is_a($e, 'VippsApiException') && $e->responsecode == 409) {
                   $duperror = true;
            }
            if ($duperror) return $imageid;

            $this->log(__("Could not send image to Vipps: ", 'woo-vipps') . $e->getMessage(), 'error');
            return false;
        }
    }
    public function add_receipt ($order) {
        if ($order->get_meta('_vipps_receipt_sent')) {
            return true;
        }
        $vippsid = $order->get_meta('_vipps_orderid');
        if (!$vippsid) {
           $this->log(sprintf(__("Cannot add receipt for order %d: No vipps id present", 'woo-vipps'), $order->get_id()), 'error');
           return false;
        }
        // Currently ecom or recurring - we are only doing ecom for now IOK 2022-06-20
        $paymenttype = apply_filters('woo_vipps_receipt_type', 'ecom', $order);

        $command = 'order-management/v2/' . $paymenttype . '/receipts/' . $vippsid;
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();

        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;

        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        try {
            $receiptdata =  [];
            $orderlines = [];
            $bottomline = ['currency'=>'NOK',  'tipAmount'=>0, 'giftCardAmount'=>0, 'terminalId'=>'woocommerce'];

            $bottomline['currency'] == $order->get_currency();
            $giftcardamount = apply_filters('woo_vipps_order_giftcard_amount', 0, $order);
            $tipamount = apply_filters('woo_vipps_order_tip_amount', 0, $order);
            $bottomline['tipAmount'] = round($tipamount*100);
            $bottomline['giftCardAmount'] = round($giftcardamount*100);
            $bottomline['terminalId'] = apply_filters('woo_vipps_order_terminalid', 'woocommerce', $order);

            foreach ($order->get_items() as $key => $order_item) {
                $orderline = [];
                $prodid = $order_item->get_product_id(); // sku can be tricky
                $totalNoTax = $order_item->get_total();
                $tax = $order_item->get_total_tax();
                $total = $tax+$totalNoTax;
                $subtotalNoTax = $order_item->get_subtotal();
                $subtotalTax = $order_item->get_subtotal_tax();
                $subtotal = $subtotalNoTax + $subtotalTax;
                $quantity = $order_item->get_quantity();
                $unitprice = $subtotal/$quantity;
                // Must do this to avoid rounding errors, since we get floats instead of money here :(
                $discount = round(100*$subtotal) - round(100*$total);
                if ($discount < 0) $discount = 0;
                $product = wc_get_product($prodid);
                $url = home_url("/");
                if ($product) {
                    $url = get_permalink($prodid);
                }
                if ($subtotalNoTax == 0) {
                    $taxpercentage = 0;
                } else {
                    $taxpercentage = (($subtotal - $subtotalNoTax) / $subtotalNoTax)*100;
                }
                $taxpercentage = round($taxpercentage);
                $unitInfo = [];
                $orderline['name'] = $order_item->get_name();
                $orderline['id'] = $prodid;
                $orderline['totalAmount'] = round($total*100);
                $orderline['totalAmountExcludingTax'] = round($totalNoTax*100);
                $orderline['totalTaxAmount'] = round($tax*100);

                $orderline['taxPercentage'] = $taxpercentage;
                $unitinfo['unitPrice'] = round($unitprice*100);
                $unitinfo['quantity'] = $quantity;
                $unitinfo['quantityUnit'] = 'PCS';
                $orderline['unitInfo'] = $unitinfo;
                $orderline['discount'] = $discount;
                $orderline['productUrl'] = $url;
                $orderline['isShipping'] = false;
                $orderlines[] = $orderline;
            }

            // Handle shipping
            foreach( $order->get_items( 'shipping' ) as $item_id => $order_item ){
                $shippingline =  [];
                $orderline['name'] = $order_item->get_name();
                $orderline['id'] = $order_item->get_method_id();
                if (method_exists($order_item, 'get_instance_id')) {
                   $orderline['id'] .= ":" . $order_item->get_instance_id();
                }

                $totalNoTax = $order_item->get_total();
                $tax = $order_item->get_total_tax();
                $total = $tax+$totalNoTax;
                $subtotalNoTax =$totalNoTax;
                $subtotalTax = $tax;
                $subtotal = $subtotalNoTax + $subtotalTax;

                if ($subtotalNoTax == 0) {
                    $taxpercentage = 0;
                }  else {
                    $taxpercentage = (($subtotal - $subtotalNoTax) / $subtotalNoTax)*100;
                }
                $taxpercentage = round($taxpercentage);

                $orderline['totalAmount'] = round($total*100);
                $orderline['totalAmountExcludingTax'] = round($totalNoTax*100);
                $orderline['totalTaxAmount'] = round($tax*100);
                $orderline['taxPercentage'] = $taxpercentage;

                $unitinfo  = [];

                $unitinfo['unitPrice'] = round($total*100);
                $unitinfo['quantity'] = 1;
                $unitinfo['quantityUnit'] = 'PCS';
                $orderline['unitInfo'] = $unitinfo;
                $discount = 0;
                $orderline['discount'] = $discount;
                $orderline['isShipping'] = true;
                $orderlines[] = $orderline;
            }
            
            $receiptdata['orderLines'] = $orderlines;
            $receiptdata['bottomLine'] = $bottomline;

            $res = $this->http_call($command,$receiptdata,'POST',$headers,'json'); 
            $order->update_meta_data('_vipps_receipt_sent', true);
            $order->save();
            $this->log(sprintf(__("Receipt for order %d sent to Vipps ", 'woo-vipps'), $order->get_id()), 'info');
            return true;
        } catch (Exception $e) {
            $this->log(__("Could not send receipt to Vipps: ", 'woo-vipps') . $e->getMessage(), 'error');
            return false;
        }
    }
    public function add_category($order, $link, $imageid, $categorytype="GENERAL", $paymenttype="ecom") {
        $vippsid = $order->get_meta('_vipps_orderid');
        if (!$vippsid) {
           $this->log(sprintf(__("Cannot add category for order %d: No vipps id present", 'woo-vipps'), $order->get_id()), 'error');
           return false;
        }

        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();

        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;

        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;


        // Currently ecom or recurring - we are only doing ecom for now IOK 2022-06-20
        $paymenttype = apply_filters('woo_vipps_receipt_type', 'ecom', $order);
        $command = "order-management/v2/$paymenttype/categories/$vippsid";

       $args = ['category'=>$categorytype, 'orderDetailsUrl' => $link ];
       if ($imageid) {
           $args['imageId'] = $imageid;
       }
       try {
            $res = $this->http_call($command,$args,'PUT',$headers,'json'); 
            return true;
        } catch (Exception $e) {
            $this->log(sprintf(__("Could not add category %s to Vipps: ", 'woo-vipps'), $categorytype) . $e->getMessage(), 'error');
            return false;
        }
    }

    public function get_receipt($order, $paymenttype = "ecom") {
        $vippsid = $order->get_meta('_vipps_orderid');
        if (!$vippsid) {
           $this->log(sprintf(__("Cannot add category for order %d: No vipps id present", 'woo-vipps'), $order->get_id()), 'error');
           return false;
        }
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();

        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;

        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        // Currently ecom or recurring - we are only doing ecom for now IOK 2022-06-20
        $paymenttype = apply_filters('woo_vipps_receipt_type', 'ecom', $order);
        $command = "order-management/v2/$paymenttype/$vippsid";
       try {
            $res = $this->http_call($command,[],'GET',$headers);
            return $res;
        } catch (Exception $e) {
            $this->log(sprintf(__("Could not get receipt data for order %s from Vipps: ", 'woo-vipps'), $order->get_id()) . $e->getMessage(), 'error');
            return false;
        }

    }


    # End Order Management API functions

    public function initiate_payment($phone,$order,$returnurl,$authtoken,$requestid) {
        $command = 'Ecomm/v2/payments';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        $prefix = $this->get_orderprefix();
        $static_shipping = $order->get_meta('_vipps_static_shipping');

        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        // We will use this to retrieve the orders in the callback, since the prefix can change in the admin interface. IOK 2018-05-03
        // This is really for the new epayment api only, but we do this to ensure we use the same logic. For short prefixes and order numbers.
        // Pad orderid with 0 to the left so the entire vipps-orderid/reference is at least 8 chars long. IOk 2022-04-06
        $orderid = $order->get_id();
        $woovippsid = $prefix . $orderid;
        $len = strlen($woovippsid);
        if ($len < 8) { # max is 50 so that would probably not be an issue
            $padwith =  8  - strlen($prefix);
            $paddedid = str_pad("".$orderid, $padwith, "0", STR_PAD_LEFT);
            $woovippsid = $prefix . $paddedid;
        }
        $vippsorderid =  apply_filters('woo_vipps_orderid', $woovippsid, $prefix, $order);

        $order->update_meta_data('_vipps_prefix',$prefix);
        $order->update_meta_data('_vipps_orderid', $vippsorderid);
        $order->set_transaction_id($vippsorderid); // The Vipps order id is probably the clossest we are getting to a transaction ID IOK 2019-03-04
        $order->delete_meta_data('_vipps_static_shipping'); // Don't need this any more
        $order->save();

        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;

        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        $callback = $this->gateway->payment_callback_url($authtoken);
        $fallback = $returnurl;

        $transaction = array();
        $transaction['orderId'] = $vippsorderid;
        // Ignore refOrderId - for child-transactions 
        $transaction['amount'] = round(wc_format_decimal($order->get_total(),'') * 100); 
        $shop_identification = apply_filters('woo_vipps_transaction_text_shop_id', home_url());
        $transactionText =  __('Your order from','woo-vipps') . ' ' . $shop_identification;
        $transaction['transactionText'] = apply_filters('woo_vipps_transaction_text', $transactionText, $order);

        // The limit for the transaction text is 100. Ensure we don't go over. Thanks to Marco1970 on wp.org for reporting this. IOK 2019-10-17
        $length = strlen($transaction['transactionText']);
        if ($length>99) {
          $this->log(__('The transaction text is too long! We are using a shorter transaction text to allow the transaction text to go through, but please check the \'woo_vipps_transaction_text_shop_id\' filter so that you can use a shorter name for your store', 'woo-vipps'));
          $transaction['transactionText'] = substr($transaction['transactionText'],0,90); // Add some slack if this happens. IOK 2019-10-17
        }

        $transaction['timeStamp'] = $date;


        $data = array();
        $data['customerInfo'] = array('mobileNumber' => $phone); 
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch, 'callbackPrefix'=>$callback, 'fallBack'=>$fallback);

        $express = $this->gateway->express_checkout;
        if ($express) {
            $shippingcallback = $this->gateway->shipping_details_callback_url($authtoken);
            if ($authtoken) {
                $data['merchantInfo']['authToken'] = "Basic " . base64_encode("Vipps" . ":" . $authtoken);
            }
            $data['merchantInfo']["paymentType"] = "eComm Express Payment";
            $data['merchantInfo']["consentRemovalPrefix"] = $this->gateway->consent_removal_callback_url();
            $data['merchantInfo']['shippingDetailsPrefix'] = $shippingcallback;

            if ($static_shipping) {
                $data['merchantInfo']['staticShippingDetails'] = $static_shipping["shippingDetails"];
            }

            // Support new, more explicit checkout flow IOK 2021-10-13
            $explicit_option = $this->gateway->get_option('useExplicitCheckoutFlow');
            $explicit_flow =  ($explicit_option == 'yes');
            if ($explicit_option == 'shipping' && WC()->cart) {
                $explicit_flow =  WC()->cart->needs_shipping();
            }
            $explicit_flow = apply_filters('woo_vipps_use_explicit_checkout_flow',  $explicit_flow, $order);

            if ($explicit_flow) {
               $transaction['useExplicitCheckoutFlow'] = true;
            }

        }
        $data['transaction'] = $transaction;


        $this->log("Initiating Vipps session for $vippsorderid", 'debug');

        $data = apply_filters('woo_vipps_initiate_payment_data', $data);

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // This is Vipps Checkout IOK 2021-06-19
    // Updated for V3 2023-01-09
    public function initiate_checkout($customerinfo,$order,$returnurl,$authtoken,$requestid) {
        $command = 'checkout/v3/session';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $clientid = $this->get_clientid();
        $secret = $this->get_secret();
        $subkey = $this->get_key();
        $static_shipping = $order->get_meta('_vipps_static_shipping');

        $merch = $this->get_merchant_serial();
        $prefix = $this->get_orderprefix();
        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        // We will use this to retrieve the orders in the callback, since the prefix can change in the admin interface. IOK 2018-05-03
        // Pad orderid with 0 to the left so the entire vipps-orderid/reference is at least 8 chars long. IOk 2022-04-06
        $orderid = $order->get_id();
        $woovippsid = $prefix . $orderid;
        $len = strlen($woovippsid);
        if ($len < 8) { # max is 50 so that would probably not be an issue
            $padwith =  8  - strlen($prefix);
            $paddedid = str_pad("".$orderid, $padwith, "0", STR_PAD_LEFT);
            $woovippsid = $prefix . $paddedid;
        }
        $vippsorderid =  apply_filters('woo_vipps_orderid', $woovippsid, $prefix, $order);


        $order->update_meta_data('_vipps_prefix',$prefix);
        $order->update_meta_data('_vipps_orderid', $vippsorderid);
        $order->set_transaction_id($vippsorderid); // The Vipps order id is probably the clossest we are getting to a transaction ID IOK 2019-03-04
#        $order->delete_meta_data('_vipps_static_shipping'); // Don't need this any more
        $order->save();

        $headers = array();

        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vippscheckout';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        $headers['client_id'] = $clientid;
        $headers['client_secret'] = $secret;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['merchant-Serial-Number'] = $merch;

        // The string returned is a prefix ending with callback=, for v3 we need to send a complete URL
        // so we just add the callback type here.
        $callback = $this->gateway->payment_callback_url($authtoken) . "checkout";
        $fallback = $returnurl;

        $transaction = array();
        $currency = $order->get_currency();
        $transaction['reference'] = $vippsorderid;
        // Ignore refOrderId - for child-transactions 
        $transaction['amount'] = array('value' => round(wc_format_decimal($order->get_total(),'') * 100), 'currency' => $currency);
        $shop_identification = apply_filters('woo_vipps_transaction_text_shop_id', home_url());
        $transactionText =  __('Confirm your order from','woo-vipps') . ' ' . $shop_identification;
        $transaction['paymentDescription'] = apply_filters('woo_vipps_transaction_text', $transactionText, $order);

        // The limit for the transaction text is 100. Ensure we don't go over. Thanks to Marco1970 on wp.org for reporting this. IOK 2019-10-17
        $length = strlen($transaction['paymentDescription']);
        if ($length>99) {
          $this->log('The transaction text is too long! We are using a shorter transaction text to allow the transaction text to go through, but please check the \'woo_vipps_transaction_text_shop_id\' filter so that you can use a shorter name for your store', 'woo-vipps');
          $transaction['paymentDescription'] =  __('Confirm your order','woo-vipps');
          $transaction['paymentDescription'] = substr($transaction['paymentDescription'],0,90); // Add some slack if this happens. IOK 2019-10-17
        }

        # This have to exist, but we'll not check it now.
        if (! function_exists("wc_terms_and_conditions_page_id")) {
            $this->log(__("You need a newer version of WooCommerce to use Vipps Checkout!", 'woo-vipps'), 'error');;
            throw new Exception(__("You need a newer version of WooCommerce to use Vipps Checkout!", 'woo-vipps'));
        }
        $termsAndConditionsUrl = get_permalink(wc_terms_and_conditions_page_id());
        $data = array();

        $data['merchantInfo'] = array('callbackAuthorizationToken'=>$authtoken, 'callbackUrl'=>$callback, 'returnUrl'=>$fallback);

        if (!empty($termsAndConditionsUrl)) {
            $data['merchantInfo']['termsAndConditionsUrl'] = $termsAndConditionsUrl; 
        } else {
            $this->log(__("Your site does not have a Terms and Conditions page defined - starting Vipps Checkout anyway, but this should be defined", 'woo-vipps'));
        }
        $data['transaction'] = $transaction;

        ## Vipps Checkout Shipping
        $needs_shipping =  WC()->cart->needs_shipping();
        $shippingcallback = $this->gateway->shipping_details_callback_url($authtoken);
        $shippingcallback .= "/v3/checkout/" . $vippsorderid . "/shippingDetails"; # because this is how eCom v2 does it.
        if ($needs_shipping) {
            $logistics = array();
            if ($static_shipping) {
                $logistics['fixedOptions'] = $static_shipping["shippingDetails"];
                unset($logistics['dynamicOptionsCallback']);
            } else {
                $logistics['dynamicOptionsCallback'] = $shippingcallback;
            }

            // Add integration data if present
            $integrations = array();
            $gw = $this->gateway;
            if ($gw->get_option('vcs_porterbuddy') == 'yes') {
               $porterbuddy = array();
               $porterbuddy['publicToken'] = $gw->get_option('vcs_porterbuddy_publicToken');
               $porterbuddy['apiKey'] = $gw->get_option('vcs_porterbuddy_apiKey');
               $origin = array();
               $origin['name'] = get_bloginfo('name');
               $origin['phoneNumber'] =  $gw->get_option('vcs_porterbuddy_phoneNumber');
               $origin['email'] = get_option('admin_email');
               $address = array();
               $address['streetAddress'] = join(", ", [WC()->countries->get_base_address(), WC()->countries->get_base_address_2()]);
               $address['postalCode'] = WC()->countries->get_base_postcode();
               $address['city'] = WC()->countries->get_base_city();
               $address['country'] = WC()->countries->get_base_country();

               $origin['address'] = $address;

               $porterbuddy['origin'] = apply_filters('woo_vipps_porterbuddy_origin', $origin);
               $integrations['porterbuddy'] = $porterbuddy;
            }
// FIXME
            // 'integrations': 'porterbuddy', 'instabox', 'helthjem'
            //  instabox => clientId, clientSecret
            //  helthjem => username, password, shopId

            if (!empty($integrations))  {
               $logistics['integrations'] = $integrations;
            }

            $data['logistics'] = $logistics;
        }


        if (!empty($customerinfo)) {
            $data['prefillCustomer'] = $customerinfo;
        }

        // From v3: Certain data moved to a 'configuration' field
        $configuration = [];
        $configuration['elements'] = "Full";
        $configuration['customerInteraction'] = apply_filters('woo_vipps_checkout_customerInteraction', 'CUSTOMER_NOT_PRESENT', $orderid);
        $configuration['userFlow'] = "WEB_REDIRECT"; // Change to NATIVE_REDIRECT for apps in below filter
        // Require consent of email and openid sub - really for login
        $configuration['requireUserInfo'] = apply_filters('woo_vipps_checkout_requireUserInfo', true, $orderid);

        // ISO-3166 Alpha 2 country list
        $countries = array_keys((new WC_Countries())->get_allowed_countries());
        $allowed_countries = apply_filters('woo_vipps_checkout_countries', $countries, $orderid);
        if ($allowed_countries) {
            $configuration['countries'] = ['supported' => $allowed_countries ];
        } else {
       
        }

        if (!$needs_shipping) {
            $nocontacts = $this->gateway->get_option('noContactFields') == 'yes';
            $noaddress = $this->gateway->get_option('noAddressFields') == 'yes';
            if ($noaddress) {
                $configuration['elements'] = false;
            }
//          AddressFields cannot be enabled while ContactFields is disabled
            if ($noaddress && $nocontacts) {
                $configuration['elements'] = "PaymentOnly";
            }
        }
        $data['configuration'] = $configuration;

        $data = apply_filters('woo_vipps_initiate_checkout_data', $data);

        error_log("Data is " . print_r($data, true)); // FIXME
        $this->log("data is " . print_r($data, true), 'debug');

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 

        return $res;
    }

    // Poll the sessionPollingURL gotten from the checkout API
    public function poll_checkout ($pollingurl) {
        $command = $pollingurl;

        $data = array();
        $headers = array();
        $clientid = $this->get_clientid();
        $secret = $this->get_secret();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();

        $headers['client_id'] = $clientid;
        $headers['client_secret'] = $secret;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['merchant-Serial-Number'] = $merch;

        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;


        try {
            $res = $this->http_call($command,$data,'GET',$headers,'json'); 
        } catch (VippsAPIException $e) {
            if ($e->responsecode == 400) {
                // No information yet.
                return array('sessionState'=>'PaymentInitiated');
            } else if ($e->responsecode == 404) {
                return 'EXPIRED';
            } else {
                $this->log(sprintf(__("Error polling status - error message %s", 'woo-vipps'), $e->getMessage()));
                // We can't dom uch more than this so just return ERROR
                return 'ERROR';

            } 
        } catch (Exception $e) {
            $this->log(sprintf(__("Error polling status - error message %s", 'woo-vipps'), $e->getMessage()));
            // We can't dom uch more than this so just return ERROR
            return 'ERROR';
        }

        return $res;
    }



    // Capture a payment made. Amount is in cents and required. IOK 2018-05-07
    public function capture_payment($order,$amount,$requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');

        $command = 'Ecomm/v2/payments/'.$orderid.'/capture';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;

        $transaction = array();
        // Ignore refOrderId - for child-transactions 
        $transaction['amount'] = round($amount);

        $shop_identification = apply_filters('woo_vipps_transaction_text_shop_id', home_url());

        $transaction['transactionText'] = __('Order capture for order','woo-vipps') . ' ' . $orderid . ' ' . $shop_identification;

        // The limit for the transaction text is 100. Ensure we don't go over. Thanks to Marco1970 on wp.org for reporting this. IOK 2019-10-17
        $length = strlen($transaction['transactionText']);
        if ($length>99) {
          $this->log('The transaction text is too long! We are using a shorter transaction text to allow the transaction text to go through, but please check the \'woo_vipps_transaction_text_shop_id\' filter so that you can use a shorter name for your store', 'woo-vipps');
          $transaction['transactionText'] = __('Order capture for order','woo-vipps') . ' ' . $orderid;
          $transaction['transactionText'] = substr($transaction['transactionText'],0,90); // Add some slack if this happens. IOK 2019-10-17
        }
        


        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch);
        $data['transaction'] = $transaction;

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Cancel a reserved but not captured payment IOK 2018-05-07
    public function cancel_payment($order,$requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');

        $command = 'Ecomm/v2/payments/'.$orderid.'/cancel';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;

        $transaction = array();
        $transaction['transactionText'] = __('Order cancel for order','woo-vipps') . ' ' . $orderid . ' ';

        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch);
        $data['transaction'] = $transaction;

        $res = $this->http_call($command,$data,'PUT',$headers,'json'); 
        return $res;
    }

    // Refund a captured payment.  IOK 2018-05-08
    public function refund_payment($order,$requestid=1,$amount=0,$cents=false) {
        $orderid = $order->get_meta('_vipps_orderid');
        $amount = $amount ? $amount : wc_format_decimal($order->get_total(),'');

        $command = 'Ecomm/v2/payments/'.$orderid.'/refund';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;


        // Ignore refOrderId - for child-transactions 
        $transaction = array();
        // If we have passed the value as 'øre' we don't need to calculate any more.
        if ($cents) {
            $transaction['amount'] = $amount;
        } else { 
            $transaction['amount'] = round($amount * 100); 
        }
        $transaction['transactionText'] = __('Refund for order','woo-vipps') . ' ' . $orderid;


        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $merch);
        $data['transaction'] = $transaction;

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Used to retrieve shipping and user details for express checkout orders where relevant and the callback isn't coming.
    public function payment_details ($order) {
	$requestid=0;
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'Ecomm/v2/payments/'.$orderid.'/details';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-Request-Id'] = $requestid;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;


        $data = array();

        $res = $this->http_call($command,$data,'GET',$headers,'json'); 
        return $res;
    }

    // Support for then new epayment API, which is also used by Checkout
    // Cancel a reserved but not captured payment IOK 2018-05-07
    // Currently must cancel the entire amount, but partial cancel will be possible.
    public function epayment_cancel_payment($order,$requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'epayment/v1/payments/'.$orderid.'/cancel';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }

        $clientid = $this->get_clientid();
        $secret = $this->get_secret();
        $headers = array();        
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;
        $headers['Idempotency-Key'] = $requestid;
        
        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        $modificationAmount = $order->get_meta('_vipps_amount');
        $modificationCurrency = $order->get_currency();

        $data = array();
        $data['modificationAmount'] =  array('value'=>$modificationAmount, 'currency'=>$modificationCurrency);

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }
    // Support for then new epayment API, which is also used by Checkout
    // Capture (a part of) reserved but not captured payment IOK 2018-05-07
    public function epayment_capture_payment($order, $amount, $requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'epayment/v1/payments/'.$orderid.'/capture';
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }

        $clientid = $this->get_clientid();
        $secret = $this->get_secret();
        $headers = array();        
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;
        $headers['Idempotency-Key'] = $requestid;
        
        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        $modificationAmount = round($amount);
        $modificationCurrency = $order->get_currency();

        $data = array();
        $data['modificationAmount'] =  array('value'=>$modificationAmount, 'currency'=>$modificationCurrency);

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Support for then new epayment API, which is also used by Checkout
    // Refund (a part of) captured payment IOK 2018-05-07
    public function epayment_refund_payment($order, $requestid, $amount, $cents) {
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'epayment/v1/payments/'.$orderid.'/refund';

        # null amount means the entire thing
        $amount = $amount ? $amount : wc_format_decimal($order->get_total(),'');

        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'];
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }

        $clientid = $this->get_clientid();
        $secret = $this->get_secret();
        $headers = array();        
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;
        $headers['Idempotency-Key'] = $requestid;
        
        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        // If we have passed the value as 'øre' we don't need to calculate any more, but woo is weird so we might need to
        $modificationAmount = round($amount);
        if ($cents) {
            $modificationAmount = round($amount);
        } else { 
            $modificationAmount = round($amount * 100); 
        }
        $modificationCurrency = $order->get_currency();

        $data = array();
        $data['modificationAmount'] =  array('value'=>$modificationAmount, 'currency'=>$modificationCurrency);

        $res = $this->http_call($command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // For the new epayment API, also used by checkout, return payment details (but not the payment log). Equivalent to the old get-status + metainfo.
    public function epayment_get_payment ($order) {
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'epayment/v1/payments/'.$orderid;

        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();        
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;
        
        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        $data = array();

        $res = $this->http_call($command,$data,'GET',$headers);
        return $res;
    }
    // For the new epayment API, also used by checkout, return payment log (as for old payment_details. Will be used for debugging.
    // epayment api.
    public function epayment_get_payment_log ($order) {
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'epayment/v1/payments/'.$orderid . "/events";

        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();        
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;
        
        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        $data = array();

        $res = $this->http_call($command,$data,'GET',$headers);
        return $res;
    }

    // the QR api 2022-04-13. PUT is update (on id), DELETE is deletion (of id), GET is get the .. thing. Arguments would be Accept for image/png or image/svg+xml
    public function get_merchant_redirect_qr_entry ($id,$accept="text/targetUrl") {
        return $this->call_qr_merchant_redirect("GET", $id, null, $accept);
    }
    public function get_all_merchant_redirect_qr () {
        return $this->call_qr_merchant_redirect("GET", "", null, "text/targetUrl");
    }
    public function create_merchant_redirect_qr ($id,$url){
        $action = "POST";
        return $this->call_qr_merchant_redirect($action, $id, $url);
    }
    public function update_merchant_redirect_qr ($id, $url) {
        $action = "PUT";
        return $this->call_qr_merchant_redirect($action, $id, $url);
    }
    public function delete_merchant_redirect_qr ($id) {
        $action = "DELETE";
        return $this->call_qr_merchant_redirect($action, $id, $url);
    }
    private function call_qr_merchant_redirect($action, $id, $url=null, $accept='image/svg+xml') {
        $command = 'qr/v1/merchant-redirect/';
        if ($action != "POST") $command .= $id;
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();        
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;
        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;

        $headers['Accept'] = $accept;

        $data = array();
        if ($id)  $data['id']  = $id;
        if ($url) $data['redirectUrl'] = $url;

        $res = $this->http_call($command,$data,$action,$headers, 'json');

        return $res;
    }

    // This isn't really neccessary since we can do this using just the fetch apis, but we'll do it anyway. 
    // The URLs here are valid for just one hour, so this should be called right after an update.
    public function get_merchant_redirect_qr ($url, $accept = "image/svg+xml") {
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $merch = $this->get_merchant_serial();
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$merch) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = array();        
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $merch;
        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;
        $headers['Accept'] = $accept;

        $res = $this->http_call($url,[],'GET',$headers);
        return $res;
    }


    // Conveniently call Vipps IOK 2018-04-18
    private function http_call($command,$data,$verb='GET',$headers=null,$encoding='url'){
        $url = "";
        if (preg_match("/^http/i", $command)) {
            $url = $command;
        } else {
            $server=$this->gateway->apiurl();
            $url = $server . "/" . $command; 
        }

        if (!$headers) $headers=array();
        $date = gmdate('c');
        $data_encoded = '';
        if ($encoding == 'url' || $verb == 'GET') {
            $data_encoded = http_build_query($data);
        } else {
            $data_encoded = json_encode($data);
        }
        $data_len = strlen ($data_encoded);
        $http_response_header = null;

        $headers['Connection'] = 'close';
        if ($verb=='POST' || $verb == 'PATCH' || $verb == 'PUT') {
            $headers['Content-length'] = $data_len;
            if ($encoding == 'url') {
                $headers['Content-type'] = 'application/x-www-form-urlencoded';
            } else {
                $headers['Content-type'] = 'application/json';
            }
        }
        $args = array();
        $args['method'] = $verb;
        $args['headers'] = $headers;
        if ($verb == 'POST' || $verb == 'PATCH' || $verb == 'PUT') {
            $args['body'] = $data_encoded;
        }
        if ($verb == 'GET' && $data_encoded) {
            $url .= "?$data_encoded";
        }

        $return = wp_remote_request($url,$args);
        $headers = array();
        $content=NULL;
        $response=0;

        if (is_wp_error($return))  {
            $headers['status'] = "500 " . $return->get_error_message();
            $response = 500;
        } else {
            $response = wp_remote_retrieve_response_code($return);
            $message =  wp_remote_retrieve_response_message($return);


            $headers = wp_remote_retrieve_headers($return);
            $headers['status'] = "$response $message";
            $contenttext = wp_remote_retrieve_body($return);

            if ($contenttext) {
                $content = @json_decode($contenttext,true);
                // Assume we always get json, except for when we don't. IOK 2022-04-22. 
                if (!$content && !empty($contenttext) && !preg_match("!json!i", $headers['content-type'])){
                    $content = array('message' => $contenttext);
                }
            }
        }

        // Parse the result, converting it to exceptions if neccessary. IOK 2018-05-11
        return $this->handle_http_response($response,$headers,$content);
    }

    // Read the response from Vipps - if any - and convert errors (null results, results over 299)
    // to Exceptions IOK 2018-05-11
    private function handle_http_response ($response, $headers, $content) {
        // This would be an error in the URL or something - or a network outage IOK 2018-04-24
        // we will assume it is temporary (ie, no response).
        if (!$response) {
            $msg = __('No response from Vipps', 'woo-vipps');
            throw new TemporaryVippsAPIException($msg);
        }

        // Good result!
        if ($response < 300) {
            return $content; 
        }

        // Now errorhandling. Default to use just the error header IOK 2018-05-11
        $msg = $headers['status'];

        // Sometimes we get one type of error, sometimes another, depending on which layer explodes. IOK 2018-04-24
        if ($content) {
            // From initiate payment, at least some times. IOK 2018-06-18
            if (isset($content['message'])) {
                $msg = $content['message'];
            // From the receipt api
            } elseif (isset($content['detail'])) {
                $msg = "$response";
                $msg .= (isset($content['title'])) ?  (" " . $content['title']) : "";
                $msg .= ": " .  $content['detail'];
            } elseif (isset($content['errors'])) {
                $msg = print_r($content['errors'], true);
            } elseif (isset($content['error'])) {
                // This seems to be only for the Access Token, which is a separate application IOK 2018-05-11
                $msg = $content['error'];
            } elseif (isset($content['ResponseInfo'])) {
                // This seems to be an error in the API layer. The error is in this elements' ResponseMessage
                $msg = $response  . ' ' .  $content['ResponseInfo']['ResponseMessage'];
            } elseif (isset($content['errorInfo'])) {
                $msg = $response  . ' ' .  $content['errorInfo']['errorMessage'];
            } elseif (isset($content['type'])) {
                // The epayment API, version 1
                $msg = "$response ";
                $msg .= $content['title'];
                if (isset($content['detail'])) $msg .= " - " . $content['detail'];
            } else {
                // Otherwise, we get a simple array of objects with error messages.  Grab them all.
                $msg = '';
                if (is_array($content)) {
                    foreach($content as $entry) {
                        $msg .= $response  . ' ' .   @$entry['errorMessage'] . "\n";
                    }
                } else {
                    // At this point, we have no idea what we have got, so just stringify it IOK 2021-11-04
                    $msg = $response . " " .  print_r($msg, true);
                }
            }
        }

        // 502's are Bad Gateway which means that Vipps is busy. IOK 2018-05-11
        if (intval($response) == 502) {
            $exception = new TemporaryVippsAPIException($msg);
        } else {
            $exception = new VippsApiException($msg);
        }

        $exception->responsecode = intval($response);
        throw $exception;
    }

}
