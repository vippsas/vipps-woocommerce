<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Agreement_Campaign extends WC_Vipps_Model {
	public const TYPE_PRICE_CAMPAIGN = "PRICE_CAMPAIGN";
	public const TYPE_PERIOD_CAMPAIGN = "PERIOD_CAMPAIGN";
	public const TYPE_PERIOD_CAMPAIGN_V3 = "PeriodCampaignV3";
	public const TYPE_EVENT_CAMPAIGN = "EVENT_CAMPAIGN";
	public const TYPE_FULL_FLEX_CAMPAIGN = "FULL_FLEX_CAMPAIGN";
	public const TYPE_LEGACY_CAMPAIGN = "LEGACY_CAMPAIGN";

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

	/**
	 * @var DateTime|string $end
	 */
	public $end;

	public ?string $explanation = null;

	/**
	 * @var DateTime|string $event_date
	 */
	public $event_date;
	public ?string $event_text = null;
	public ?WC_Vipps_Agreement_Campaign_Period $period = null;
	public ?WC_Vipps_Agreement_Interval $interval = null;

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

	public function set_price( int $price ): self {
		$this->price = $price;

		return $this;
	}

	/**
	 * @param DateTime|string $end
	 *
	 * @throws Exception
	 */
	public function set_end( $end ): self {
		if ( is_string( $end ) ) {
			$this->end = new DateTime( $end );
		} else {
			$this->end = $end;
		}

		return $this;
	}

	public function set_explanation( string $explanation ): self {
		$this->explanation = $explanation;

		return $this;
	}

	/**
	 * @param DateTime|string $event_date
	 *
	 * @throws Exception
	 */
	public function set_event_date( $event_date ): self {
		if ( is_string( $event_date ) ) {
			$this->event_date = new DateTime( $event_date );
		} else {
			$this->event_date = $event_date;
		}

		return $this;
	}

	public function set_event_text( string $event_text ): self {
		$this->event_text = $event_text;

		return $this;
	}

	/**
	 * @param array|WC_Vipps_Agreement_Campaign_Period $period
	 */
	public function set_period( $period ): self {
		if ( is_array( $period ) ) {
			$period = new WC_Vipps_Agreement_Campaign_Period( $period );
		}

		$this->period = $period;

		return $this;
	}

	/**
	 * @param array|WC_Vipps_Agreement_Interval $interval
	 */
	public function set_interval( $interval ): self {
		if ( is_array( $interval ) ) {
			$interval = new WC_Vipps_Agreement_Interval( $interval );
		}

		$this->interval = $interval;

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
				"type"  => $this->type,
				"price" => $this->price,
			],
			$this->conditional( "end", $this->end ),
			$this->conditional( "explanation", $this->explanation ),
			$this->conditional( "eventDate", $this->event_date ),
			$this->conditional( "eventText", $this->event_text ),
			$this->conditional( "period", $this->period ),
			$this->conditional( "interval", $this->interval ),
		);
	}
}
