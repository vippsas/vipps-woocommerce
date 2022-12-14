<?php

class WC_Vipps_Agreement_Interval extends WC_Vipps_Model {
	const UNIT_YEAR = "YEAR";
	const UNIT_MONTH = "MONTH";
	const UNIT_WEEK = "WEEK";
	const UNIT_DAY = "DAY";

	protected array $valid_units = [
		self::UNIT_YEAR,
		self::UNIT_MONTH,
		self::UNIT_WEEK,
		self::UNIT_DAY
	];

	protected array $required_fields = [ "unit", "count" ];

	private string $unit;
	private int $count;

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
	function to_array(): array {
		$this->check_required();

		return [
			"unit"  => $this->unit,
			"count" => $this->count,
		];
	}
}
