<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Recurring_Exception extends Exception {

	/** sanitized/localized error message */
	protected string $localized_message;

	public $response_code;
	public $is_idempotent_error = false;

	/**
	 * Setup exception
	 *
	 * @param string $error_message Full response
	 * @param string $localized_message user-friendly translated error message
	 *
	 * @since 4.0.2
	 */
	public function __construct( $error_message = '', $localized_message = '' ) {
		$this->localized_message = $localized_message;
		parent::__construct( $error_message );
	}

	/**
	 * Returns the localized message.
	 *
	 * @return string
	 * @since 4.0.2
	 */
	public function getLocalizedMessage(): string {
		return $this->localized_message;
	}
}

// Temporary errors like 500-class errors
class WC_Vipps_Recurring_Temporary_Exception extends WC_Vipps_Recurring_Exception {
	//
}

// Misconfigurations
class WC_Vipps_Recurring_Config_Exception extends WC_Vipps_Recurring_Exception {
	//
}

// Missing required value
class WC_Vipps_Recurring_Missing_Value_Exception extends WC_Vipps_Recurring_Exception {
	//
}

// Invalid value value
class WC_Vipps_Recurring_Invalid_Value_Exception extends WC_Vipps_Recurring_Exception {
	//
}
