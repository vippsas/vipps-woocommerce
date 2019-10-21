<?php
class VippsCallbackSessionHandler extends WC_Session {
    public function init() { 
       error_log("Local session handler for callbacks here yaaay");
       return parent::init();
    }
}
