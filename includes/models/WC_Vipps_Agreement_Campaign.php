<?php

class WC_Vipps_Agreement_Campaign extends WC_Vipps_Model {
	const TYPE_PRICE_CAMPAIGN = "PRICE_CAMPAIGN";
	const TYPE_PERIOD_CAMPAIGN = "PERIOD_CAMPAIGN";
	const TYPE_EVENT_CAMPAIGN = "EVENT_CAMPAIGN";
	const TYPE_FULL_FLEX_CAMPAIGN = "FULL_FLEX_CAMPAIGN";
	const TYPE_LEGACY_CAMPAIGN = "LEGACY_CAMPAIGN";

	protected array $valid_types = [
		self::TYPE_PRICE_CAMPAIGN,
		self::TYPE_PERIOD_CAMPAIGN,
		self::TYPE_EVENT_CAMPAIGN,
		self::TYPE_FULL_FLEX_CAMPAIGN,
		self::TYPE_LEGACY_CAMPAIGN,
	];

	protected array $required_fields = [
		"PRICE_CAMPAIGN"     => [ "type", "price", "end" ],
		"PERIOD_CAMPAIGN"    => [ "type", "price", "end", "period" ],
		"EVENT_CAMPAIGN"     => [ "type", "price", "event_date", "event_text" ],
		"FULL_FLEX_CAMPAIGN" => [ "type", "price", "end", "interval" ],
		"LEGACY_CAMPAIGN"    => [ "type", "price", "end" ],
	];

	public ?string $type = null;
	public ?int $price = null;
	public ?DateTime $end = null;
	public ?string $explanation = null;
	public ?DateTime $event_date = null;
	public ?string $event_text = null;
	public ?WC_Vipps_Agreement_Interval $period = null;
	public ?WC_Vipps_Agreement_Interval $interval = null;

	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_type( string $type ): self {
		if ( ! in_array( $type, $this->valid_types ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$type is not a valid value for `type` in $class." );
		}

		$this->type = $type;

		return $this;
	}

	public function set_price( int $price ): self {
		$this->price = $price;

		return $this;
	}

	public function set_end( DateTime $end ): self {
		$this->end = $end;

		return $this;
	}

	public function set_explanation( string $explanation ): self {
		$this->explanation = $explanation;

		return $this;
	}

	public function set_event_date( DateTime $event_date ): self {
		$this->event_date = $event_date;

		return $this;
	}

	public function set_event_text( string $event_text ): self {
		$this->event_text = $event_text;

		return $this;
	}

	public function set_period( WC_Vipps_Agreement_Interval $period ): self {
		$this->period = $period;

		return $this;
	}

	public function set_interval( WC_Vipps_Agreement_Interval $interval ): self {
		$this->interval = $interval;

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	public function to_array( $check_required = true ): array {
		if ( $check_required ) {
			$this->check_required( $this->type );
		}

		return [
			"type"  => $this->type,
			"price" => $this->price,
			"end"   => $this->conditional( "end", $this->end ),
			...$this->conditional( "explanation", $this->explanation ),
			...$this->conditional( "eventDate", $this->event_date ),
			...$this->conditional( "eventText", $this->event_text ),
			...$this->conditional( "period", $this->period ),
			...$this->conditional( "interval", $this->interval ),
		];
	}
}
