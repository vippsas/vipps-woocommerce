<?php
// This file just creates some exception types for error handling. IOK 2018-04-18
class VippsAPIException extends Exception {
 public $responsecode = null;
}

// This is for 502 bad gateway, timeouts and other errors we can expect to recover from
class TemporaryVippsAPIException extends VippsAPIException {
}

// This is for non-temporary problems with the keys and so forth
class VippsAPIConfigurationException extends VippsAPIExceptions {
}

