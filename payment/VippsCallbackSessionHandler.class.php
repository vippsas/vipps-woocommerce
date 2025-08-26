<?php
/*
   This class implements a Woocommerce Session Handler that works with callbacks from Vipps to the store
   so that the customers session will be in effect when calculating shipping, calculating VAT and so forth. IOK 2019-10-22


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

class VippsCallbackSessionHandler extends WC_Session_Handler {
    protected $callbackorder = 0;
    protected $sessiondata=null;

    public function init() { 
        global $Vipps;
        $this->callbackorder = $Vipps->callbackorder;
        return parent::init();
    }

    // Unfortunately, we need to override all of this, because
    // the is_session_cookie_valid() method is *private*. On the 
    // parent class, this checks whether or not the user is logged-in,
    // and if so, if this session is for the same user. But these callbacks
    // should not have any user privileges, so they are *not* logged in. Therefore we only
    // check the expiry of the cookie. IOK 2022-03-14
    public function init_session_cookie() {
        $cookie = $this->get_session_cookie();

        if ( $cookie ) {
            // Customer ID will be an MD5 hash id this is a guest session.
            $this->_customer_id        = $cookie[0];
            $this->_session_expiration = $cookie[1];
            $this->_session_expiring   = $cookie[2];
            $this->_has_cookie         = true;
            $this->_data               = $this->get_session_data();

            if ( ! $this->is_session_cookie_valid_ignoring_loggedinness() ) {
                $this->destroy_session();
                $this->set_session_expiration();
            }

            // We also won't do the logged-in update check, because we aren't logged in.

            // Update session if its close to expiring.
            if ( time() > $this->_session_expiring ) {
                $this->set_session_expiration();
                $this->update_session_timestamp( $this->_customer_id, $this->_session_expiration );
            }
        } else {
            $this->set_session_expiration();
            $this->_customer_id = $this->generate_customer_id();
            $this->_data        = $this->get_session_data();
        }
    }

    // We can't override is_session_cookie_valid, which checks whether or not we are logged in.
    // Therefore, call this instead in init_session_cookie. IOK 2022-03-14
    private function is_session_cookie_valid_ignoring_loggedinness() {
        // If session is expired, session cookie is invalid.
        if ( time() > $this->_session_expiration ) {
            return false;
        }
        return true;
    }



    public function get_session_cookie() {
        if (!$this->callbackorder) return false;
        $order = wc_get_order($this->callbackorder);
        if (empty($order) && is_wp_error($order))  {
            return false;
        }
        $sessionjson = $order->get_meta('_vipps_sessiondata');
        if (empty($sessionjson)) return false;
        $sessiondata = @json_decode($sessionjson,true);
        if (empty($sessiondata)) return false;
        list($customer_id, $session_expiration, $session_expiring, $cookie_hash) = $sessiondata;
        if (empty($customer_id)) return false;
        $this->sessiondata = $sessiondata;
        // If passed as an actual cookie, we would verify the cookie_hash here, but since this is
        // stored in the order object to which the user has no access, we don't. 
        // (Change triggered by change of logic here for Woo.) IOK 2025-08-26
        return array($customer_id, $session_expiration, $session_expiring, $cookie_hash); 
    }

    public function has_session () {
        return !empty($this->sessiondata);
    }

    public function forget_session() {
        if (!$this->has_session()) return;
        $order = wc_get_order($this->callbackorder);
        if (empty($order) && is_wp_error($order)) return false;
        $order->delete_meta_data('_vipps_sessiondata');
        wc_empty_cart();
        $this->_data        = array();
        $this->_dirty       = false;
        $this->_customer_id = $this->generate_customer_id();
    }

    // This is only used for callbacks, so *never* set cookies.
    public function set_customer_session_cookie( $set ) {
        return;
    }

}
