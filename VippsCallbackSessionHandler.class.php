<?php
class VippsCallbackSessionHandler extends WC_Session_Handler {
    protected $callbackorder = 0;
    protected $sessiondata=null;

    public function init() { 
       global $Vipps;
       error_log("In init: " . $Vipps->callbackorder);
       $this->callbackorder = $Vipps->callbackorder;
       return parent::init();
    }

    public function get_session_cookie() {
       error_log("Session for {$this->callbackorder}");
       if (!$this->callbackorder) return false;
       $order = wc_get_order($this->callbackorder);
       error_log("Order for {$this->callbackorder}");
       if (empty($order) && is_wp_error($order))  {
          error_log("That's no order or something");
       }
       $sessionjson = $order->get_meta('_vipps_sessiondata');
       error_log("Session ->$sessionjson<-");
       if (empty($sessionjson)) return false;
       $sessiondata = @json_decode($sessionjson,true);
       if (empty($sessiondata)) return false;
       list($customer_id, $session_expiration, $session_expiring, $cookie_hash) = $sessiondata;
       if (empty($customer_id)) return false;
       // Validate hash.
       $to_hash = $customer_id . '|' . $session_expiration;
       $hash    = hash_hmac( 'md5', $to_hash, wp_hash( $to_hash ) );
       if ( empty( $cookie_hash ) || ! hash_equals( $hash, $cookie_hash ) ) {
            return false;
       }
       $this->sessiondata = $sessiondata;
       return array($customer_id, $session_expiration, $session_expiring, $cookie_hash); 
    }

    public function has_session () {
        error_log("Checking has_session");
        return !empty($this->sessiondata);
    }
 
    public function forget_session() {
          error_log("Forgetting session");
          if (!$this->has_session()) return;
          $order = wc_get_order($this->callbackorder);
          if (empty($order) && is_wp_error($order)) return false;
          $order->delete_meta_data('_vipps_sessiondata');
          wc_empty_cart();
          $this->_data        = array();
          $this->_dirty       = false;
          $this->_customer_id = $this->generate_customer_id();
    }

}
