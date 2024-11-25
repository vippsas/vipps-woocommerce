<?php
/*
   This is the VippsApi  class, a delegate in WC_Payment_Gateway that handles the actual communication with Vipps.
   The parameters are fetched from the containing class. IOK 2018-05-11


This file is part of the plugin Pay with Vipps and MobilePay for WooCommerce
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
    public function get_clientid($msn="") {
        return $this->gateway->get_clientid($msn);
    }
    public function get_secret($msn="") {
        return $this->gateway->get_secret($msn);
    }
    public function get_key($msn="") {
        return $this->gateway->get_key($msn); 
    }
    // Orderprefix is the same for all MSN (currently)
    public function get_orderprefix() {
        return $this->gateway->get_orderprefix();
    }

    public function get_option($optionname) {
        return $this->gateway->get_option($optionname);
    }
    public function log($what,$type='info') {
        return $this->gateway->log($what,$type);
    }

    // All methods get these headers, adding meta info, access token etc
    public function get_headers($msn="") {
        if (!$msn) $msn =$this->get_merchant_serial();
        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? '127.0.0.1' ;
        $at = $this->get_access_token($msn);
        $subkey = $this->get_key($msn);

        if (!$msn || !$at || !$subkey) {
           return null;
        }

        $headers = array();
        $headers['Authorization'] = 'Bearer ' . $at;
        $headers['X-TimeStamp'] = $date;
        $headers['X-Source-Address'] = $ip;
        $headers['Ocp-Apim-Subscription-Key'] = $subkey;
        $headers['Merchant-Serial-Number'] = $msn;

        $headers['Vipps-System-Name'] = 'woocommerce';
        $headers['Vipps-System-Version'] = get_bloginfo( 'version' ) . "/" . WC_VERSION;
        $headers['Vipps-System-Plugin-Name'] = 'woo-vipps';
        $headers['Vipps-System-Plugin-Version'] = WOO_VIPPS_VERSION;
        return $headers;
    }

    // List all webhooks registered for the given MSN IOK 2023-12-19
    public function get_webhooks($msn="") {
        $command = "webhooks/v1/webhooks";
        if (!$msn) $msn = $this->get_merchant_serial(); 
        $headers = $this->get_headers($msn);
        $args = [];
        try {
            $res = $this->http_call($msn,$command,$args,'GET',$headers,'json');
            return $res;
        } catch (Exception $e) {
            $this->log(sprintf(__("Could not get webhooks for merchant serial number %1\$s: ", 'woo-vipps'), $msn) . $e->getMessage(), 'error');
            return false;
        }
    }
    // Try to register a webhook for the site and the MSN passed
    public function register_webhook($msn, $callback, $events=null) {
        $command = "webhooks/v1/webhooks";
        if (!$msn) $msn = $this->get_merchant_serial();
        $headers = $this->get_headers($msn);
        // We want the authorized event, and all the "no longer relevant" events. IOK 2023-12-19
        if (!$events) {
             $events = ['epayments.payment.authorized.v1', 'epayments.payment.aborted.v1', 'epayments.payment.expired.v1', 'epayments.payment.terminated.v1'];
        }
        $args = ['url'=>$callback, 'events'=>$events];
        try {
            $res = $this->http_call($msn,$command,$args,'POST',$headers,'json');
            return $res;
        } catch (Exception $e) {
            $this->log(sprintf(__("Could not register webhooks for merchant serial number %1\$s callback %2\$s: ", 'woo-vipps'), $msn, $callback) . $e->getMessage(), 'error');
            return false;
        }
    }
    // Delete a webhook with the given id
    public function delete_webhook($msn, $id) {
        $command = "webhooks/v1/webhooks/" . $id;
        if (!$msn) $msn = $this->get_merchant_serial();
        $headers = $this->get_headers($msn);
        $args = [];
        try {
            $res = $this->http_call($msn,$command,$args,'DELETE',$headers,'json');
            return $res;
        } catch (Exception $e) {
            $this->log(sprintf(__("Could not delete webhook for merchant serial number %1\$s id %2\$s: ", 'woo-vipps'), $msn, $id) . $e->getMessage(), 'error');
            return false;
        }
    }

    // Get an App access token if neccesary. Returns this or throws an error. IOK 2018-04-18
    // IOK 2023-12-19 changed this so the system could use several MSNs in the future.
    public function get_access_token($msn="",$force=0) {
        $msn = $msn ?? $this->get_merchant_serial();
        $transientname = '_vipps_app_token' . '_' . sanitize_title($msn);
        // First, get a stored token if it exists
        $stored = get_transient($transientname);
        if (!$force && $stored && $stored['expires_on'] > time()) {
            return $stored['access_token'];
        }
        // Otherwise, get it from vipps - this might throw errors 
        $fresh = $this->get_access_token_from_vipps($msn);
        if (!$fresh) return null;

        $at = $fresh['access_token'];
        $expire = $fresh['expires_in']/2;
        set_transient($transientname,$fresh,$expire);
        return $at;
    }

    // Fetch an access token if possible from the Vipps Api IOK 2018-04-18
    private function get_access_token_from_vipps($msn="") { 
        $clientid=$this->get_clientid($msn);
        $secret=$this->get_secret($msn);
        $subkey  = $this->get_key($msn);

        $command = 'accessToken/get';
        try {
            $args = array('client_id'=>$clientid,'client_secret'=>$secret,'Ocp-Apim-Subscription-Key'=>$subkey);
            $result = $this->http_call($msn,$command,array(),'POST',$args,'url');
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
                
                // Check image dimensions
                $imageinfo = getimagesize($image);
                if ($imageinfo && $imageinfo[1] < 167) {
                    $this->log(__("Image height too small - minimum height requirement is 167px", 'woo-vipps'), 'error');
                    return false;
                }
            }
        }

        $msn = $this->get_merchant_serial(); 
        $headers = $this->get_headers($msn);

        // imageid =  ^[0-9A-Za-z-_\.] - 128 chars
        $imageid = hash('sha512',$bytes); // Yields 128 hex chars
        $base64 = base64_encode($bytes);
        $args = ['imageId'=>$imageid,'src'=>$base64,'type'=>'base64'];

        try {
            $res = $this->http_call($msn,$command,$args,'POST',$headers,'json'); 
            return $res['imageId'];
        } catch (Exception $e) {
            if ($this->is_duplicate_error($e)) return $imageid;
            
            if ($this->is_image_size_error($e)) {
                $this->log(__("Image rejected by Vipps - minimum height requirement is 167px", 'woo-vipps'), 'error');
                return false;
            }

            $this->log(__("Could not send image to Vipps: ", 'woo-vipps') . $e->getMessage(), 'error');
            return false;
        }
    }

    private function is_duplicate_error($e) {
        if (!is_a($e, 'VippsApiException')) return false;
        return ($e->responsecode == 409) || 
               ($e->responsecode == 400 && preg_match("!duplicate!i", $e->getMessage()));
    }

    private function is_image_size_error($e) {
        if (!is_a($e, 'VippsApiException')) return false;
        return $e->responsecode == 400 && 
               (strpos($e->getMessage(), 'height') !== false || 
                strpos($e->getMessage(), 'size') !== false);
    }

    // Used by the add_receipt API call as well as epayment_initiate_payment. The latter will use this
    // if we have the shipping information - that is, we are using the normal woo checkout flow. IOK 2023-12-13
    public function get_receipt_data($order) {
        $receiptdata =  [];
        try {
            $orderlines = [];
            $bottomline = ['tipAmount'=>0, 'giftCardAmount'=>0, 'terminalId'=>'woocommerce'];
            $bottomline['currency'] = $order->get_currency();
            $giftcardamount = apply_filters('woo_vipps_order_giftcard_amount', 0, $order);
            $tipamount = apply_filters('woo_vipps_order_tip_amount', 0, $order);
            $bottomline['tipAmount'] = round($tipamount*100);
            $bottomline['giftCardAmount'] = round($giftcardamount*100);
            $bottomline['terminalId'] = apply_filters('woo_vipps_order_terminalid', 'woocommerce', $order);
            $bottomline['receiptNumber'] = strval($order->get_id());

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
                $taxpercentageraw = 0;
                if ($subtotalNoTax > 0) {
                    $taxpercentageraw = (($subtotal - $subtotalNoTax) / $subtotalNoTax)*100;
                }
                $taxrate = abs(round(100*$taxpercentageraw));
                $taxpercentage = abs(round($taxpercentageraw));
                $unitInfo = [];
                $orderline['name'] = $order_item->get_name();
                $orderline['id'] = strval($prodid);
                $orderline['totalAmount'] = round($total*100);
                $orderline['totalAmountExcludingTax'] = round($totalNoTax*100);
                $orderline['totalTaxAmount'] = round($tax*100);

                $orderline['taxRate'] = $taxrate;
                $unitinfo['unitPrice'] = round($unitprice*100);
                $unitinfo['quantity'] = strval($quantity);
                $unitinfo['quantityUnit'] = 'PCS';
                $orderline['unitInfo'] = $unitinfo;
                $orderline['discount'] = $discount;
                $orderline['productUrl'] = $url;
                $orderline['isShipping'] = false;
                $orderlines[] = $orderline;
            }

            foreach($order->get_items('fee') as $key=>$order_item) {
                $orderline = [];
                $totalNoTax = $order_item->get_total();
                $tax = $order_item->get_total_tax();
                $total = $tax+$totalNoTax;
                $quantity = 1;
                $taxpercentageraw = 0;
                if ($totalNoTax > 0) { 
                    $taxpercentageraw = (($total - $totalNoTax) / $totalNoTax)*100;
                }
                $taxrate = abs(round(100*$taxpercentageraw));
                $taxpercentage = abs(round($taxpercentageraw));
                $unitInfo = [];
                $orderline['name'] = $order_item->get_name();
                $orderline['id'] = substr(sanitize_title($orderline['name']), 0, 254);
                $orderline['totalAmount'] = round($total*100);
                $orderline['totalAmountExcludingTax'] = round($totalNoTax*100);
                $orderline['totalTaxAmount'] = round($tax*100);
                $orderline['discount'] = 0;

                $orderline['taxRate'] = $taxrate;
                $orderlines[] = $orderline;
            }


            // Handle shipping
            foreach( $order->get_items( 'shipping' ) as $item_id => $order_item ){
                $shippingline =  [];
                $orderline['name'] = $order_item->get_name();
                $orderline['id'] = strval($order_item->get_method_id());
                if (method_exists($order_item, 'get_instance_id')) {
                    $orderline['id'] .= ":" . strval($order_item->get_instance_id());
                }

                $totalNoTax = $order_item->get_total();
                $tax = $order_item->get_total_tax();
                $total = $tax+$totalNoTax;
                $subtotalNoTax =$totalNoTax;
                $subtotalTax = $tax;
                $subtotal = $subtotalNoTax + $subtotalTax;

                $taxpercentageraw = 0;
                if ($subtotalNoTax > 0) {
                    $taxpercentageraw = (($subtotal - $subtotalNoTax) / $subtotalNoTax)*100;
                }
                $taxpercentage = abs(round($taxpercentageraw));
                $taxrate= abs(round($taxpercentageraw * 100));

                $orderline['totalAmount'] = round($total*100);
                $orderline['totalAmountExcludingTax'] = round($totalNoTax*100);
                $orderline['totalTaxAmount'] = round($tax*100);
                $orderline['taxRate'] = $taxrate;

                $unitinfo  = [];

                $unitinfo['unitPrice'] = round($total*100);
                $unitinfo['quantity'] = strval(1);
                $unitinfo['quantityUnit'] = 'PCS';
                $orderline['unitInfo'] = $unitinfo;
                $discount = 0;
                $orderline['discount'] = $discount;
                $orderline['isShipping'] = true;
                $orderlines[] = $orderline;
            }

            $receiptdata['orderLines'] = $orderlines;
            $receiptdata['bottomLine'] = $bottomline;

        } catch (Exception $e) {
            $this->log(sprintf(__('Cannot create receipt for order %1$d: %2$s', 'woo-vipps'), $order->get_id(), $e->getMessage()), 'error');
        }
        return $receiptdata;
    }

    public function add_receipt ($order) {
        if ($order->get_meta('_vipps_receipt_sent')) {
            return true;
        }
        $vippsid = $order->get_meta('_vipps_orderid');
        if (!$vippsid) {
           $this->log(sprintf(__("Cannot add receipt for order %1\$d: No vipps id present", 'woo-vipps'), $order->get_id()), 'error');
           return false;
        }
        // Currently ecom or recurring - we are only doing ecom for now IOK 2022-06-20
        // please note that 'ecom' applies to both ecom and epayment. IOK 2023-12-13
        $paymenttype = apply_filters('woo_vipps_receipt_type', 'ecom', $order);
        $command = 'order-management/v2/' . $paymenttype . '/receipts/' . $vippsid;
        $msn = $this->get_merchant_serial();
        $headers = $this->get_headers($msn);

        $receiptdata = $this->get_receipt_data($order);
        if (empty($receiptdata)) {
            $this->log(__("Could not send receipt to Vipps: ", 'woo-vipps') . $order->getId(), 'error');
            return false;
        }
    }

    // Note that paymenttype 'ecom' applies to both ecom and epayment. IOK 2023-12-13
    public function add_category($order, $link, $imageid, $categorytype="GENERAL", $paymenttype="ecom") {
        $vippsid = $order->get_meta('_vipps_orderid');
        if (!$vippsid) {
           $this->log(sprintf(__("Cannot add category for order %1\$d: No vipps id present", 'woo-vipps'), $order->get_id()), 'error');
           return false;
        }

        $date = gmdate('c');
        $ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? '127.0.0.1' ;
        $at = $this->get_access_token();
        $subkey = $this->get_key();
        $msn = $this->get_merchant_serial();
        $headers = $this->get_headers($msn);

        // Currently ecom or recurring - we are only doing ecom for now IOK 2022-06-20
        // Note that 'ecom' applies to both ecom and epayment. IOK 2023-12-13
        $paymenttype = apply_filters('woo_vipps_receipt_type', 'ecom', $order);
        $command = "order-management/v2/$paymenttype/categories/$vippsid";

       $args = ['category'=>$categorytype, 'orderDetailsUrl' => $link ];
       if ($imageid) {
           $args['imageId'] = $imageid;
       }
       try {
            $res = $this->http_call($msn,$command,$args,'PUT',$headers,'json'); 
            return true;
        } catch (Exception $e) {
            $this->log(sprintf(__("Could not add category %1\$s to Vipps: ", 'woo-vipps'), $categorytype) . $e->getMessage(), 'error');
            return false;
        }
    }

    // Note that paymenttype 'ecom' applies to both ecom and epayment. IOK 2023-12-13
    public function get_receipt($order, $paymenttype = "ecom") {
        $vippsid = $order->get_meta('_vipps_orderid');
        if (!$vippsid) {
           $this->log(sprintf(__("Cannot add category for order %1\$d: No vipps id present", 'woo-vipps'), $order->get_id()), 'error');
           return false;
        }
        $msn = $this->get_merchant_serial();
        $headers = $this->get_headers($msn);

        // Currently ecom or recurring - we are only doing ecom for now IOK 2022-06-20
        // Note that 'ecom' applies to both ecom and epayment. IOK 2023-12-13
        $paymenttype = apply_filters('woo_vipps_receipt_type', 'ecom', $order);
        $command = "order-management/v2/$paymenttype/$vippsid";
       try {
            $res = $this->http_call($msn,$command,[],'GET',$headers);
            return $res;
        } catch (Exception $e) {
            $this->log(sprintf(__("Could not get receipt data for order %1\$s from Vipps: ", 'woo-vipps'), $order->get_id()) . $e->getMessage(), 'error');
            return false;
        }

    }
    # End Order Management API functions

    // Initiate payment via the epayment API; Express Checkout will still use Ecomm/v2 IOK 2023-12-13
    public function epayment_initiate_payment($phone,$order,$returnurl,$authtoken,$requestid=1) {
        $command = 'epayment/v1/payments';
        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        $prefix = $this->get_orderprefix();
        $static_shipping = $order->get_meta('_vipps_static_shipping');
        $needs_shipping = $order->get_meta('_vipps_needs_shipping');

        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }

        $headers = $this->get_headers($msn);
        $headers['Idempotency-Key'] = $requestid;

        // IOK 2023-12-13 This is currently always false for epayment_initiate_payment but let's think ahead
        // Code for static shipping, shipping callback etc would need to be added. 
        $express = $order->get_meta('_vipps_express_checkout');

        // We will use this to retrieve the orders in the callback, since the prefix can change in the admin interface. IOK 2018-05-03
        // This is really for the new epayment api only, but we do this to ensure we use the same logic. For short prefixes and order numbers.
        // Pad orderid with 0 to the left so the entire vipps-orderid/reference is at least 8 chars long. IOK 2022-04-06
        $orderid = $order->get_id();
        $woovippsid = $prefix . $orderid;
        $len = strlen($woovippsid);
        if ($len < 8) { # max is 50 so that would probably not be an issue
            $padwith =  8  - strlen($prefix);
            $paddedid = str_pad("".$orderid, $padwith, "0", STR_PAD_LEFT);
            $woovippsid = $prefix . $paddedid;
        }
        $vippsorderid =  apply_filters('woo_vipps_orderid', $woovippsid, $prefix, $order);

        $order->update_meta_data('_vipps_api', 'epayment');
        $order->update_meta_data('_vipps_prefix',$prefix);
        $order->update_meta_data('_vipps_orderid', $vippsorderid);
        $order->set_transaction_id($vippsorderid); // The Vipps order id is probably the clossest we are getting to a transaction ID IOK 2019-03-04
        $order->delete_meta_data('_vipps_static_shipping'); // Don't need this any more
        $order->save();

        $callback = $this->gateway->payment_callback_url($authtoken, $orderid);
        $fallback = $returnurl;


        $data = [];
        $data['reference'] = $vippsorderid;
        $data['paymentMethod'] = ['type' => 'WALLET']; // This is the Vipps MobilePay app. CARD is credit card, must then use userFlow WEB_REDIRECT
        $data['amount'] = ['currency' => $order->get_currency(), 'value' => round(wc_format_decimal($order->get_total(),'') * 100)]; 
        $data['returnUrl'] = $fallback;

        $data['customer'] = [];
        // Allow filters to use CUSTOMER_PRESENT if using in store situation with the user physically present IOK 2023-12-13
        $data['customer']['customerInteraction'] = apply_filters('woo_vipps_customerInteraction', 'CUSTOMER_NOT_PRESENT', $orderid);
        if ($phone) {
            $phonenr = Vipps::normalizePhoneNumber($phone, $order->get_billing_country());
            if ($phonenr) {
                $data['customer']['phoneNumber']  = $phonenr;
            }
            $data['customer'] = apply_filters('woo_vipps_payment_customer_data',$data['customer'],$orderid);
        }

        // Store the original orderid as metadata, so we can retrieve it if neccessary IOK 2023-12-21
        $metadata = [];
        $metadata['orderid'] = $orderid;
        $metadata = apply_filters('woo_vipps_payment_metadata', $metadata, $orderid);
        $data['metadata'] = $metadata;

        // User information data to ask for. During normal checkout, we won't ask at all, but for Express Checkout we would do name, email, phoneNumber.
        // Currently, use a filter to allow merchants to do this. Can also add 'Address', which would give all data but not the shipment flow.
        // Values are name, address, email, phoneNumber, birthData and nin, if the company provides this to the merchant. 
        // IOK 2023-12-13
        $scope = array();
        $scope = apply_filters('woo_vipps_payment_scope', $scope, $orderid);
        if (!empty($scope)) {
                $data['profile'] = [];
                $data['profile']['scope'] = join(" ", $scope);
        }
        // WEB_REDIRECT is the normal flow; requires a returnUrl. PUSH_MESSAGE requires a valid customer (phone number)
        // NATIVE_REDIRECT is for automatic app switch between a native app and the Vipps MobilePay app.
        // QR returns a QR code that can be scanned to complete the payment. IOK 2023-12-13
        $data['userFlow'] = apply_filters('woo_vipps_payment_user_flow', 'WEB_REDIRECT', $orderid);

        // Some control over the QR
        if ($data['userFlow'] == 'QR') {
            // Formats are IMAGE/SVG+XML, TEXT/TARGETURL, IMAGE/PNG
            $data['qrFormat'] = ['format' => apply_filters('woo_vipps_payment_qr_format', 'IMAGE/SVG+XML', $orderid),
                                 'size' => apply_filters('woo_vipps_payment_qr_size', 1024, $orderid)];
        }


        $shop_identification = apply_filters('woo_vipps_transaction_text_shop_id', home_url());
        $transactionText =  __('Confirm your order from','woo-vipps') . ' ' . $shop_identification;
        $data['paymentDescription'] = apply_filters('woo_vipps_transaction_text', $transactionText, $order);
        // The limit for the transaction text is 100. Ensure we don't go over. Thanks to Marco1970 on wp.org for reporting this. IOK 2019-10-17
        $length = strlen($data['paymentDescription']);
        if ($length>99) {
          $this->log('The transaction text is too long! We are using a shorter transaction text to allow the transaction text to go through, but please check the \'woo_vipps_transaction_text_shop_id\' filter so that you can use a shorter name for your store', 'woo-vipps');
          $data['paymentDescription'] = substr($data['paymentDescription'],0,90); // Add some slack if this happens. IOK 2019-10-17
        }

        // Epayment can send the receipt already in the initiate call, so lets do it. IOK 2023-12-23
        if (!$express) {
            $receiptdata = $this->get_receipt_data($order);
            if (!empty($receiptdata)) {
               $data['receipt'] = $receiptdata;
               $order->update_meta_data('_vipps_receipt_sent', true);
               $order->save();
            }
        }

        if ($data['receipt'] ?? false) {
            // Please note: If expiresAt is added, a receipt must also be added.
            // expiresAt -- control expiry of payment., must be more than 10 minutes, less than 28 days.
            // format is RFC 3339 so yyyy-mm-ddTH:i:sZ gmt. 
            // These are for payments that can wait for fullfillment for quite a while, not very well suited for normal Woo stores where stock is
            // an issue. IOK 2023-12-13
            $expiresAt = apply_filters('woo_vipps_payment_expires_at', false, $orderid);
            if ($expiresAt !== false) {
                if (is_string($expiresAt)) {
                    $expiresAt = gmdate('Y-m-d\TH:i:s\Z', strtotime($expiresAt));
                } elseif (is_int($expiresAt) && $expiresAt > time()) {
                    $expiresAt = gmdate('Y-m-d\TH:i:s\Z', $expiresAt);
                } else {
                    $expiresAt = false;
                }
            }
            if ($expiresAt) $data['expiresAt'] = $expiresAt;
        }

        // Arbitrary metadata that will be retrieved in payment responses. Please note, key length is <= 100, value <= 500, max elements is 5
        $metadata_raw = [];
        $metadata_filtered = apply_filters('woo_vipps_payment_metadata', $metadata_raw, $orderid);
        $metadata = [];
        $i = 0;
        foreach($metadata_filtered as $key => $value) {
            if (strlen($key)>100 || strlen($value) > 500) {
               $this->log(sprintf(__('Could not add key %1$s to payment metadata of order %2$s - key or value is too long (100, 500 respectively)', 'woo-vipps'), $key, $orderid));
               continue;
            }
            $i++;
            if ($i > 5) {
               $this->log(sprintf(__('Could not add all keys to the payment metadata of order %1$s - only 5 items are allowed', 'woo-vipps'), $orderid));
               break;
            }
            $metadata[$key] = $value;
        }
        if (!empty($metadata)) {
           $data['metadata'] = $metadata;
        }
       
        $this->log("Initiating Vipps MobilePay epayment session for $vippsorderid", 'debug');
        $data = apply_filters('woo_vipps_epayment_initiate_payment_data', $data);

        // Now for QR, this value will be an URL to the QR code, or the target URL. If the flow is PUSH_MESSAGE, nothing will be returned.
        $res = $this->http_call($msn,$command,$data,'POST',$headers,'json');

        // Backwards compatibility: Previous API returned this as an URL. We also get a 'reference' back, the Vipps Order Id
        $res['url'] = $res['redirectUrl'] ?? false;
        return $res;
    }



    public function initiate_payment($phone,$order,$returnurl,$authtoken,$requestid) {
        $command = 'Ecomm/v2/payments';
        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);

        $prefix = $this->get_orderprefix();
        $static_shipping = $order->get_meta('_vipps_static_shipping');
        $needs_shipping = $order->get_meta('_vipps_needs_shipping');

        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
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
        $order->save();

        $headers = $this->get_headers($msn);

        $headers['X-Request-Id'] = $requestid;

        $callback = $this->gateway->payment_callback_url($authtoken, $orderid);
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
        $date = gmdate('c');
        $transaction['timeStamp'] = $date;


        $data = array();
        $data['customerInfo'] = array('mobileNumber' => $phone); 
        $data['merchantInfo'] = array('merchantSerialNumber' => $msn, 'callbackPrefix'=>$callback, 'fallBack'=>$fallback);

        $express = $order->get_meta('_vipps_express_checkout');

        // If we are not to ask for the address, we must change this to a normal "eComm Regular Payment" and add a scope,
        // which will give us a sub from which we can get user data from the userInfo api. This is a temporary situation. IOK 2023-03-10
        //
        // The old "explicit shipping" option which is now the only option - if set to "yes", always ask for address
        $explicit_option = ($this->gateway->get_option('useExplicitCheckoutFlow') == "yes");
        // Merchant may always need it
        $always_address = ($this->gateway->get_option('expresscheckout_always_address') == "yes");

        $ask_for_address = apply_filters('woo_vipps_express_checkout_ask_for_address', ($needs_shipping || $always_address || $explicit_option), $order); 

        if ($express && $ask_for_address) {

            // Express Checkout! Except if this order doesn't need shipping, and the merchant doesn't care about the
            // address. If so, we'll just add a scope to the initiate_payment branch which will allow us to fetch
            // user data (except address then) from getDetails and then userInfo. This is then via the eCommerce api, and not the
            // eCom api so that's interesting too.
            

            $shippingcallback = $this->gateway->shipping_details_callback_url($authtoken, $orderid);
            if ($authtoken) {
                $data['merchantInfo']['authToken'] = "Basic " . base64_encode("Vipps" . ":" . $authtoken);
            }
            $data['merchantInfo']["paymentType"] = "eComm Express Payment";
            $data['merchantInfo']["consentRemovalPrefix"] = $this->gateway->consent_removal_callback_url();
            $data['merchantInfo']['shippingDetailsPrefix'] = $shippingcallback;

            if ($static_shipping) {
                $data['merchantInfo']['staticShippingDetails'] = $static_shipping["shippingDetails"];
            }

        } else {
             $data['merchantInfo']["paymentType"] = "eComm Regular Payment";

             // We will not normally ask for user info, but if this is an express checkout order, where we don't 
             // want the address, then we have to add "name", "email" and "phoneNumber" to the scope. A filter will allow
             // advanced users to add scope for their own usage.
             // When scope has been added, it is possible to get a 'sub' value from the payment details, which for several weeks
             // can be used to retrieve user information from the user info API. IOK 2023-03-10
            // IOK 2023-03-10 'scope' deterines for what data we ask the customer.
            // possible values, name, address, email, phoneNumber, birthDate, nin and accountNumbers (last ones are of course restricted)
             $scope = array();
             if ($express) {
                $scope = ["name", "email", "phoneNumber"]; // And not 'address'
             }
             $scope = apply_filters('woo_vipps_express_checkout_scope', $scope, $order);
             if (!empty($scope)) {
                $transaction['scope'] = join(" ", $scope);
             }
        }
        $data['transaction'] = $transaction;


        $this->log("Initiating Vipps MobilePay ecomm session for $vippsorderid", 'debug');

        $data = apply_filters('woo_vipps_initiate_payment_data', $data);

        $res = $this->http_call($msn,$command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // This is Vipps Checkout IOK 2021-06-19
    // Updated for V3 2023-01-09
    public function initiate_checkout($customerinfo,$order,$returnurl,$authtoken,$requestid) {
        $command = 'checkout/v3/session';
        $static_shipping = $order->get_meta('_vipps_static_shipping');
        $needs_shipping = $order->get_meta('_vipps_needs_shipping');

        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        $clientid = $this->get_clientid($msn);
        $secret = $this->get_secret($msn);
        $prefix = $this->get_orderprefix();
        // Don't go on with the order, but don't tell the customer too much. IOK 2018-04-24
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
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

        $headers = $this->get_headers($msn);
        // Required for Checkout
        $headers['client_id'] = $clientid;
        $headers['client_secret'] = $secret;

        // The string returned is a prefix ending with callback=, for v3 we need to send a complete URL
        // so we just add the callback type here.
        $callback = $this->gateway->payment_callback_url($authtoken,$orderid) . "checkout";
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
          $transaction['paymentDescription'] = substr($transaction['paymentDescription'],0,90); // Add some slack if this happens. IOK 2019-10-17
        }


        # This have to exist, but we'll not check it now.
        if (! function_exists("wc_terms_and_conditions_page_id")) {
            $msg = sprintf(__('You need a newer version of WooCommerce to use %1$s!', 'woo-vipps'), Vipps::CheckoutName());
            $this->log($msg, 'error');;
            throw new Exception($msg);
        }
        $termsAndConditionsUrl = get_permalink(wc_terms_and_conditions_page_id());
        $data = array();

        $data['merchantInfo'] = array('callbackAuthorizationToken'=>$authtoken, 'callbackUrl'=>$callback, 'returnUrl'=>$fallback);

        if (!empty($termsAndConditionsUrl)) {
            $data['merchantInfo']['termsAndConditionsUrl'] = $termsAndConditionsUrl; 
        } else {
            $this->log(sprintf(__('Your site does not have a Terms and Conditions page defined - starting %1$s anyway, but this should be defined', 'woo-vipps'), Vipps::CheckoutName()));
        }

        ## Vipps Checkout Shipping
        $shippingcallback = $this->gateway->shipping_details_callback_url($authtoken, $orderid);
        $shippingcallback .= "/v3/checkout/" . $vippsorderid . "/shippingDetails"; # because this is how eCom v2 does it.
        $gw = $this->gateway;
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

            if ($gw->get_option('vcs_helthjem') == 'yes') {
               $helthjem = array();
               $helthjem['username'] = $gw->get_option('vcs_helthjem_username');
               $helthjem['password'] = $gw->get_option('vcs_helthjem_password');
               $helthjem['shopId'] = $gw->get_option('vcs_helthjem_shopId');
               $integrations['helthjem'] = $helthjem;
            }
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
        $configuration['requireUserInfo'] = apply_filters('woo_vipps_checkout_requireUserInfo', $gw->get_option('requireUserInfo_checkout') == 'yes' , $orderid);


        // IOK 2023-12-22 and we can add an order summary, so do so by default
        $summarize = apply_filters('woo_vipps_checkout_show_order_summary', true, $order);
        // IOK 2024-01-09 Fix this as soon as the EUR bug is fixed!
        if ($summarize) {
            $ordersummary = $this->get_receipt_data($order);
            // This is different in the receipt api, the epayment api and in checkout.
            $ordersummary['orderBottomLine'] = $ordersummary['bottomLine'];
            unset($ordersummary['bottomLine']);

            // A bug in the Vipps Checkout API will not allow for several order lines with the same
            // product id (for instance, with different custom text etc). IOK 2024-01-26
            // FIXME when this is fixed at Vipps
            $orderlines = $ordersummary['orderLines'];
            $seen = [];
            $newlines = [];
            foreach($orderlines as $orderline) {
                $productid = $orderline['id'];
                if (isset($seen[$productid])) {
                    $seen[$productid]++;
                    $orderline['id'] = $orderline['id'] . ":" . $seen[$productid];
                } else {
                    $seen[$productid] = 0;
                }
                $newlines[] = $orderline;
            }
            $ordersummary['orderLines'] = $newlines;

            // Don't finalize the receipt number - we just want to show this rn.
            unset($ordersummary['orderBottomLine']['receiptNumber']);
            if (!empty($ordersummary)) {
                $transaction['orderSummary'] = $ordersummary;
                $configuration['showOrderSummary'] = true;
            }
        }
 
        // ISO-3166 Alpha 2 country list
        $countries = array_keys((new WC_Countries())->get_allowed_countries());
        $allowed_countries = apply_filters('woo_vipps_checkout_countries', $countries, $orderid);
        if ($allowed_countries) {
            $configuration['countries'] = ['supported' => $allowed_countries ];
        } else {
       
        }

        // External payment methods IOK 2024-05-13 
        // Should return a map from other_method => ['gw'=>'gateway key or any or empty string]
        $other_payment_methods = apply_filters('woo_vipps_checkout_external_payment_methods', VippsCheckout::instance()->external_payment_methods(), $order);
        if (!empty($other_payment_methods)) {
            $others = [];
            foreach ($other_payment_methods as $methodkey => $methoddata) {
                $chooseanother = ['action'=>'vipps_gw', 'o'=>$orderid];
                $chooseanother['cb'] = wp_create_nonce('vipps_gw');
                $chooseanother['gw'] = ($methoddata['gw'] ?? "");
                $others[] = ['paymentMethod' => $methodkey, 'redirectUrl'=> add_query_arg($chooseanother,admin_url("admin-post.php")) ];
            }
            if (!empty($others)) {
                $configuration['externalPaymentMethods'] =  $others;
            }
        }

        // Custom consent checkbox, for integration with Mailchimp etc . 
        $customconsenttext = apply_filters('woo_vipps_checkout_consent_query', "");
        $customconsentrequired = apply_filters('woo_vipps_checkout_consent_required', false);
        if ($customconsenttext) {
            $customconsent = [];
            $customconsent['text'] = $customconsenttext;
            $customconsent['required'] = $customconsentrequired;
            $configuration['customConsent']  = $customconsent;
        }

        if (!$needs_shipping) {
            $nocontacts = $this->gateway->get_option('noContactFields') == 'yes';
            $noaddress = $this->gateway->get_option('noAddressFields') == 'yes';
            if ($noaddress) {
                $configuration['elements'] = "PaymentAndContactInfo";
            }
//          AddressFields cannot be enabled while ContactFields is disabled
            if ($noaddress && $nocontacts) {
                $configuration['elements'] = "PaymentOnly";
            }
        }
        $data['configuration'] = $configuration;
        $data['transaction'] = $transaction;

        $data = apply_filters('woo_vipps_initiate_checkout_data', $data);

        $this->log("Initiating Checkout session for $vippsorderid", 'debug');
        $res = $this->http_call($msn,$command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Poll the sessionPollingURL gotten from the checkout API
    public function poll_checkout ($pollingurl) {
        $command = $pollingurl;

        $data = array();
        $headers = array();
        $msn = $this->get_merchant_serial();
        $clientid = $this->get_clientid($msn);
        $secret = $this->get_secret($msn);

        $headers = $this->get_headers($msn);
        // Required for checkout
        $headers['client_id'] = $clientid;
        $headers['client_secret'] = $secret;


        try {
            $res = $this->http_call($msn,$command,$data,'GET',$headers,'json'); 
            // This is not a 404, but the session is still expired. IOK 2023-10-16
            if (($res['sessionState'] ?? "") == 'SessionExpired') {
                return 'EXPIRED';  
            }

        } catch (VippsAPIException $e) {
            if ($e->responsecode == 400) {
                // No information yet.
                return array('sessionState'=>'PaymentInitiated');
            } else if ($e->responsecode == 404) {
                return 'EXPIRED';
            } else {
                $this->log(sprintf(__("Error polling status - error message %1\$s", 'woo-vipps'), $e->getMessage()));
                // We can't dom uch more than this so just return ERROR
                return 'ERROR';

            } 
        } catch (Exception $e) {
            $this->log(sprintf(__("Error polling status - error message %1\$s", 'woo-vipps'), $e->getMessage()));
            // We can't dom uch more than this so just return ERROR
            return 'ERROR';
        }

        return $res;
    }



    // Capture a payment made. Amount is in cents and required. IOK 2018-05-07
    public function capture_payment($order,$amount,$requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');

        $command = 'Ecomm/v2/payments/'.$orderid.'/capture';
        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = $this->get_headers($msn);
        $headers['X-Request-Id'] = $requestid;

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
        $data['merchantInfo'] = array('merchantSerialNumber' => $msn);
        $data['transaction'] = $transaction;

        $res = $this->http_call($msn,$command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Cancel a reserved but not captured payment IOK 2018-05-07
    public function cancel_payment($order,$requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');

        $command = 'Ecomm/v2/payments/'.$orderid.'/cancel';
        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = $this->get_headers($msn);
        $headers['X-Request-Id'] = $requestid;

        $transaction = array();
        $transaction['transactionText'] = __('Order cancel for order','woo-vipps') . ' ' . $orderid . ' ';

        $data = array();
        $data['merchantInfo'] = array('merchantSerialNumber' => $msn);
        $data['transaction'] = $transaction;

        $res = $this->http_call($msn,$command,$data,'PUT',$headers,'json'); 
        return $res;
    }

    // Refund a captured payment.  IOK 2018-05-08
    public function refund_payment($order,$requestid=1,$amount=0,$cents=false) {
        $orderid = $order->get_meta('_vipps_orderid');
        $amount = $amount ? $amount : wc_format_decimal($order->get_total(),'');

        $command = 'Ecomm/v2/payments/'.$orderid.'/refund';
        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = $this->get_headers($msn);
        $headers['X-Request-Id'] = $requestid;

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
        $data['merchantInfo'] = array('merchantSerialNumber' => $msn);
        $data['transaction'] = $transaction;

        $res = $this->http_call($msn,$command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Used to retrieve shipping and user details for express checkout orders where relevant and the callback isn't coming.
    public function payment_details ($order) {
	$requestid=0;
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'Ecomm/v2/payments/'.$orderid.'/details';
        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = $this->get_headers($msn);
        $headers['X-Request-Id'] = $requestid;

        $data = array();

        $res = $this->http_call($msn,$command,$data,'GET',$headers,'json'); 
        return $res;
    }

    // Support for then new epayment API, which is also used by Checkout
    // Cancel a reserved but not captured payment IOK 2018-05-07
    // Currently must cancel the entire amount, but partial cancel will be possible.
    public function epayment_cancel_payment($order,$requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'epayment/v1/payments/'.$orderid.'/cancel';
        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }

        $headers = $this->get_headers($msn);
        $headers['Idempotency-Key'] = $requestid;

        // The only current allowed argument is "cancelTransactionOnly" which will, if true, only cancel
        // non-authorized transactions. We don't need that, but we have to send *something* or we get type errors. IOK 2024-11-25
        $data = array('cancelTransactionOnly' => false); 
     
        $res = $this->http_call($msn,$command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Support for then new epayment API, which is also used by Checkout
    // Capture (a part of) reserved but not captured payment IOK 2018-05-07
    public function epayment_capture_payment($order, $amount, $requestid=1) {
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'epayment/v1/payments/'.$orderid.'/capture';
        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }

        $clientid = $this->get_clientid();
        $secret = $this->get_secret();
        $headers = $this->get_headers($msn);
        $headers['Idempotency-Key'] = $requestid;
        
        $modificationAmount = round($amount);
        $modificationCurrency = $order->get_currency();

        $data = array();
        $data['modificationAmount'] =  array('value'=>$modificationAmount, 'currency'=>$modificationCurrency);

        $res = $this->http_call($msn,$command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // Support for then new epayment API, which is also used by Checkout
    // Refund (a part of) captured payment IOK 2018-05-07
    public function epayment_refund_payment($order, $requestid, $amount, $cents) {
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'epayment/v1/payments/'.$orderid.'/refund';

        # null amount means the entire thing
        $amount = $amount ? $amount : wc_format_decimal($order->get_total(),'');

        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }

        $headers = $this->get_headers($msn);
        $headers['Idempotency-Key'] = $requestid;
        
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

        $res = $this->http_call($msn,$command,$data,'POST',$headers,'json'); 
        return $res;
    }

    // For the new epayment API, also used by checkout, return payment details (but not the payment log). Equivalent to the old get-status + metainfo.
    // Takes either an order object or the Vipps orderid as argument.
    public function epayment_get_payment ($order, $msn='') {
        if (is_a($order, 'WC_Order')) {
            $orderid = $order->get_meta('_vipps_orderid');
        } else {
            $orderid = $order;
        }
        $command = 'epayment/v1/payments/'.$orderid;

        if (!$msn) {
            $msn = $this->get_merchant_serial();
        }

        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = $this->get_headers($msn);

        $data = array();

        $res = $this->http_call($msn,$command,$data,'GET',$headers);
        return $res;
    }

    // For the new epayment API, also used by checkout, return payment log (as for old payment_details. Will be used for debugging.
    // epayment api.
    public function epayment_get_payment_log ($order) {
        $orderid = $order->get_meta('_vipps_orderid');
        $command = 'epayment/v1/payments/'.$orderid . "/events";

        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = $this->get_headers($msn);

        $data = array();

        $res = $this->http_call($msn,$command,$data,'GET',$headers);
        return $res;
    }

    // Implement part of the userInfo api, just to be able to get  user data from Express Orders that aren't express
    // orders (because they didn't need shipping). In the future, will probably be used more + for integration with Login With Vipps
    public function get_userinfo($sub) {
        $command = "vipps-userinfo-api/userinfo" . "/" . $sub;
        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = $this->get_headers($msn);
        
        $data = array();
        
        $res = $this->http_call($msn,$command,$data,'GET',$headers);
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
        $msn = $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = $this->get_headers($msn);
        $headers['Accept'] = $accept;

        $data = array();
        if ($id)  $data['id']  = $id;
        if ($url) $data['redirectUrl'] = $url;

        $res = $this->http_call($msn,$command,$data,$action,$headers, 'json');

        return $res;
    }

    // This isn't really neccessary since we can do this using just the fetch apis, but we'll do it anyway. 
    // The URLs here are valid for just one hour, so this should be called right after an update.
    public function get_merchant_redirect_qr ($url, $accept = "image/svg+xml") {
        $msn= $this->get_merchant_serial();
        $subkey = $this->get_key($msn);
        if (!$subkey) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        if (!$msn) {
            throw new VippsAPIConfigurationException(__('The Vipps gateway is not correctly configured.','woo-vipps'));
            $this->log(__('The Vipps gateway is not correctly configured.','woo-vipps'),'error');
        }
        $headers = $this->get_headers($msn);
        $headers['Accept'] = $accept;

        $res = $this->http_call($msn, $url,[],'GET',$headers);
        return $res;
    }

    // Conveniently call Vipps IOK 2018-04-18
    private function http_call($msn,$command,$data,$verb='GET',$headers=null,$encoding='url'){
        $url = "";
        if (preg_match("/^http/i", $command)) {
            $url = $command;
        } else {
            $server=$this->gateway->apiurl($msn);
            $url = $server . "/" . $command; 
        }

        if (!$headers) $headers=array();
        $date = gmdate('c');
        $data_encoded = '';
        if ($encoding == 'url' || $verb == 'GET') {
            $data_encoded = http_build_query($data);
        } else {
            $data_encoded = json_encode($data, JSON_THROW_ON_ERROR);
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
            // can't happen, but be sure
            if (is_string($content)) {
                $msg .= " " . $content;
            // From initiate payment, at least some times. IOK 2018-06-18
            } elseif (isset($content['message'])) {
                $msg .= " " . $content['message'];
            // From the receipt api
            } elseif (isset($content['detail'])) {
                $msg = "$response";
                $msg .= (isset($content['title'])) ?  (" " . $content['title']) : "";
                $msg .= ": " .  $content['detail'];
                if (isset($content['extraDetails'])) {
                  $msg = "Extra details: " . print_r($content['extraDetails'], true);
                }
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
                if (isset($content['extraDetails'])) $msg .= " - " . print_r($content['extraDetails'], true);
            } else {
                // Otherwise, we get a simple array of objects with error messages.  Grab them all.
                $msg = '';
                if (is_array($content)) {
                    foreach($content as $entry) {
                        if (is_string($entry)) {
                            // This started happening august 2023. 
                            $msg .= $entry . "\n";
                        } elseif (is_array($entry)) {
                            $msg .= $response  . ' ' .   @$entry['errorMessage'] . "\n";
                        } else {
                            $msg = $response . " " .  print_r($content, true);
                        }
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
