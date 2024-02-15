<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Charge extends WC_Vipps_Model {
	public const STATUS_PENDING = "PENDING";
	public const STATUS_DUE = "DUE";
	public const STATUS_PROCESSING = "PROCESSING";
	public const STATUS_UNKNOWN = "UNKNOWN";
	public const STATUS_CHARGED = "CHARGED";
	public const STATUS_FAILED = "FAILED";
	public const STATUS_REFUNDED = "REFUNDED";
	public const STATUS_PARTIALLY_REFUNDED = "PARTIALLY_REFUNDED";
	public const STATUS_RESERVED = "RESERVED";
	public const STATUS_PARTIALLY_CAPTURED = "PARTIALLY_CAPTURED";
	public const STATUS_CANCELLED = "CANCELLED";

	protected array $valid_statuses = [
		self::STATUS_PENDING,
		self::STATUS_DUE,
		self::STATUS_PROCESSING,
		self::STATUS_UNKNOWN,
		self::STATUS_CHARGED,
		self::STATUS_FAILED,
		self::STATUS_REFUNDED,
		self::STATUS_PARTIALLY_REFUNDED,
		self::STATUS_RESERVED,
		self::STATUS_PARTIALLY_CAPTURED,
		self::STATUS_CANCELLED,
	];

	public const TYPE_RECURRING = "RECURRING";
	public const TYPE_INITIAL = "INITIAL";

	protected array $valid_types = [
		self::TYPE_RECURRING,
		self::TYPE_INITIAL,
	];

	public const TRANSACTION_TYPE_DIRECT_CAPTURE = "DIRECT_CAPTURE";
	public const TRANSACTION_TYPE_RESERVE_CAPTURE = "RESERVE_CAPTURE";

	protected array $valid_transaction_types = [
		self::TRANSACTION_TYPE_DIRECT_CAPTURE,
		self::TRANSACTION_TYPE_RESERVE_CAPTURE,
	];

	protected array $required_fields = [
		"amount",
		"description",
		"due",
		"retry_days"
	];

	public ?string $id = null;
	public ?string $status = null;
	public ?string $type = null;
	public ?int $amount = null;
	public ?string $transaction_id = null;
	public ?string $transaction_type = null;
	public ?string $description = null;
	public ?string $currency = null;

	/**
	 * @var DateTime|string $due
	 */
	public $due;
	public ?int $retry_days = null;
	public ?string $failure_reason = null;
	public ?string $failure_description = null;
	public ?array $summary = null;
	public ?array $history = null;

	public function set_id( string $id ): self {
		$this->id = $id;

		return $this;
	}

	public function set_amount( int $amount ): self {
		$this->amount = $amount;

		return $this;
	}

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

	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_status( string $status ): self {
		if ( ! in_array( $status, $this->valid_statuses, true ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$status is not a valid value for `status` in $class." );
		}

		$this->status = $status;

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_transaction_type( string $transaction_type ): self {
		if ( ! in_array( $transaction_type, $this->valid_transaction_types, true ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$transaction_type is not a valid value for `transaction_type` in $class." );
		}

		$this->transaction_type = $transaction_type;

		return $this;
	}

	public function set_transaction_id( string $transaction_id ): self {
		$this->transaction_id = $transaction_id;

		return $this;
	}

	public function set_description( ?string $description ): self {
		if ( strlen( $description ) > 45 ) {
			$description = mb_substr( $description, 0, 42 ) . '...';
		}

		$this->description = $description;

		return $this;
	}

	/**
	 * @param DateTime|string $due
	 *
	 * @throws Exception
	 */
	public function set_due( $due ): self {
		if ( is_string( $due ) ) {
			$this->due = new DateTime( $due );
		} else {
			$this->due = $due;
		}


		return $this;
	}

	public function set_retry_days( int $retry_days ): self {
		$this->retry_days = $retry_days;

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
			[
				"amount"      => $this->amount,
				"description" => $this->description,
				"due"         => $this->serialize_value($this->due, 'Y-m-d'),
				"retryDays"   => $this->retry_days
			],
			$this->conditional( "type", $this->type ),
			$this->conditional( "transactionType", $this->transaction_type ),
			$this->conditional( "transactionId", $this->transaction_id )
		);
	}
}
