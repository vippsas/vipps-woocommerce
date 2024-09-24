<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session_Configuration extends WC_Vipps_Model {
	public const CUSTOMER_INTERACTION_PRESENT = "CUSTOMER_PRESENT";
	public const CUSTOMER_INTERACTION_NOT_PRESENT = "CUSTOMER_NOT_PRESENT";

	protected array $valid_customer_interactions = [
		self::CUSTOMER_INTERACTION_PRESENT,
		self::CUSTOMER_INTERACTION_NOT_PRESENT,
	];

	public const ELEMENTS_FULL = "Full";
	public const ELEMENTS_PAYMENT_AND_CONTACT_INFO = "PaymentAndContactInfo";
	public const ELEMENTS_PAYMENT_ONLY = "PaymentOnly";

	protected array $valid_elements = [
		self::ELEMENTS_FULL,
		self::ELEMENTS_PAYMENT_AND_CONTACT_INFO,
		self::ELEMENTS_PAYMENT_ONLY,
	];

	public const USER_FLOW_WEB_REDIRECT = "WEB_REDIRECT";
	public const USER_FLOW_NATIVE_REDIRECT = "NATIVE_REDIRECT";

	protected array $valid_user_flows = [
		self::USER_FLOW_WEB_REDIRECT,
		self::USER_FLOW_NATIVE_REDIRECT,
	];

	public ?string $customer_interaction = null;
	public ?string $elements = null;
	public ?array $countries = null;
	public ?string $user_flow = null;
	public ?bool $require_user_info = null;
	public ?array $custom_consent = null;
	public ?bool $show_order_summary = null;


	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_customer_interaction( string $customer_interaction ): self {
		if ( ! in_array( $customer_interaction, $this->valid_customer_interactions, true ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$customer_interaction is not a valid value for `customer_interaction` in $class." );
		}

		$this->customer_interaction = $customer_interaction;

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_elements( string $elements ): self {
		if ( ! in_array( $elements, $this->valid_elements, true ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$elements is not a valid value for `elements` in $class." );
		}

		$this->elements = $elements;

		return $this;
	}

	// Available: NO, DK, FI
	public function set_countries( array $countries ): self {
		$this->countries = $countries;

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_user_flow( string $user_flow ): self {
		if ( ! in_array( $user_flow, $this->valid_user_flows, true ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$user_flow is not a valid value for `user_flow` in $class." );
		}

		$this->user_flow = $user_flow;

		return $this;
	}

	public function set_require_user_info( bool $require_user_info ): self {
		$this->require_user_info = $require_user_info;

		return $this;
	}

	public function set_custom_consent( string $text, bool $required ): self {
		$this->custom_consent = [
			"text"     => $text,
			"required" => $required
		];

		return $this;
	}

	public function set_show_order_summary( bool $show_order_summary ): self {
		$this->show_order_summary = $show_order_summary;

		return $this;
	}


	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	public function to_array( bool $check_required = false ): array {
		if ( $check_required ) {
			$this->check_required();
		}

		return array_merge(
			$this->conditional( "termsAndConditionsUrl", $this->customer_interaction ),
			$this->conditional( "elements", $this->elements ),
			$this->conditional( "countries", $this->countries ),
			$this->conditional( "userFlow", $this->user_flow ),
			$this->conditional( "requireUserInfo", $this->require_user_info ),
			$this->conditional( "customConsent", $this->custom_consent ),
			$this->conditional( "showOrderSummary", $this->show_order_summary ),
		);
	}
}
