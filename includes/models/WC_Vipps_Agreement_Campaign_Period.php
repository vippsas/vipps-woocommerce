<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Agreement_Campaign_Period extends WC_Vipps_Model {
	public const UNIT_YEAR = "YEAR";
	public const UNIT_MONTH = "MONTH";
	public const UNIT_WEEK = "WEEK";
	public const UNIT_DAY = "DAY";

	protected array $valid_units = [
		self::UNIT_YEAR,
		self::UNIT_MONTH,
		self::UNIT_WEEK,
		self::UNIT_DAY
	];

	protected array $required_fields = [ "unit", "count" ];

	public ?string $unit = null;
	public ?int $count = null;

	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_unit( string $unit ): self {
		if ( ! in_array( $unit, $this->valid_units ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$unit is not a valid value for `unit` in $class." );
		}

		$this->unit = $unit;

		return $this;
	}

	public function set_count( int $count ): self {
		$this->count = $count;

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
			"unit"  => $this->unit,
			"count" => $this->count
		];
	}
}
