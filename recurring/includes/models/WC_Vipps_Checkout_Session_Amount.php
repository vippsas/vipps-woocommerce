<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session_Amount extends WC_Vipps_Model {
	protected array $required_fields = [
		"value",
		"currency",
	];

	public ?int $value = null;
	public ?string $currency = null;

	public function set_value( int $value ): self {
		$this->value = $value;

		return $this;
	}

	public function set_currency( string $currency ): self {
		$this->currency = $currency;

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	public function to_array( bool $check_required = false ): array {
		if ( $check_required ) {
			$this->check_required();
		}

		return [
			"value"    => $this->value,
			"currency" => $this->currency,
		];
	}
}
