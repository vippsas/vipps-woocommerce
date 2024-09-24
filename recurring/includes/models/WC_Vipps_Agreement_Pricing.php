<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Agreement_Pricing extends WC_Vipps_Model {
	public const TYPE_LEGACY = "LEGACY";
	public const TYPE_VARIABLE = "VARIABLE";

	protected array $valid_types = [
		self::TYPE_LEGACY,
		self::TYPE_VARIABLE
	];

	protected array $required_fields = [
		"VARIABLE" => [ "type", "currency", "suggested_max_amount" ],
		"LEGACY"   => [ "type", "currency", "amount" ],
	];

	public ?string $type = null;
	public ?string $currency = null;
	public ?int $amount = null;
	public ?int $suggested_max_amount = null;
	public ?int $max_amount = null;

	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_type( string $type ): self {
		if ( ! in_array( $type, $this->valid_types, true ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$type is not a valid value for `type` in $class." );
		}

		$this->type = $type;

		return $this;
	}

	public function set_currency( string $currency ): self {
		$this->currency = $currency;

		return $this;
	}

	public function set_amount( int $amount ): self {
		$this->amount = $amount;

		return $this;
	}

	public function set_suggested_max_amount( int $suggested_max_amount ): self {
		$this->suggested_max_amount = $suggested_max_amount;

		return $this;
	}

	public function set_max_amount( int $max_amount ): self {
		$this->max_amount = $max_amount;

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	public function to_array( bool $check_required = false ): array {
		if ( $check_required ) {
			$this->check_required( $this->type );
		}

		return array_merge(
			[
				"type"     => $this->type,
				"currency" => $this->currency,
			],
			$this->conditional( "amount", $this->amount ),
			$this->conditional( "suggestedMaxAmount", $this->suggested_max_amount ),
			$this->conditional( "maxAmount", $this->max_amount )
		);
	}
}
