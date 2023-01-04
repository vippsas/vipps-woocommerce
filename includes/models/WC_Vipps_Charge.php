<?php

class WC_Vipps_Charge extends WC_Vipps_Model {
	const STATUS_PENDING = "PENDING";
	const STATUS_DUE = "DUE";
	const STATUS_RESERVED = "RESERVED";
	const STATUS_CHARGED = "CHARGED";
	const STATUS_PARTIALLY_CAPTURED = "PARTIALLY_CAPTURED";
	const STATUS_FAILED = "FAILED";
	const STATUS_CANCELLED = "CANCELLED";
	const STATUS_PARTIALLY_REFUNDED = "PARTIALLY_REFUNDED";
	const STATUS_REFUNDED = "REFUNDED";
	const STATUS_PROCESSING = "PROCESSING";

	protected array $valid_statuses = [
		self::STATUS_PENDING,
		self::STATUS_DUE,
		self::STATUS_RESERVED,
		self::STATUS_CHARGED,
		self::STATUS_PARTIALLY_CAPTURED,
		self::STATUS_FAILED,
		self::STATUS_CANCELLED,
		self::STATUS_PARTIALLY_REFUNDED,
		self::STATUS_REFUNDED,
		self::STATUS_PROCESSING,
	];

	const TRANSACTION_TYPE_RECURRING = "RECURRING";
	const TRANSACTION_TYPE_INITIAL = "INITIAL";

	protected array $valid_transaction_types = [
		self::TRANSACTION_TYPE_RECURRING,
		self::TRANSACTION_TYPE_INITIAL,
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
	public ?DateTime $due = null;
	public ?int $retry_days = null;
	public ?string $order_id = null;
	public ?string $failure_reason = null;
	public ?string $failure_description = null;
	public ?array $summary = null;
	public ?array $history = null;

	public function set_amount( int $amount ): self {
		$this->amount = $amount;

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_transaction_type( string $transaction_type ): self {
		if ( ! in_array( $transaction_type, $this->valid_transaction_types ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$transaction_type is not a valid value for `transaction_type` in $class." );
		}

		$this->transaction_type = $transaction_type;

		return $this;
	}

	public function set_description( string $description ): self {
		$this->description = $description;

		return $this;
	}

	public function set_due( DateTime $due ): self {
		$this->due = $due;

		return $this;
	}

	public function set_retry_days( int $retry_days ): self {
		$this->retry_days = $retry_days;

		return $this;
	}

	public function set_order_id( int $order_id ): self {
		$this->order_id = $order_id;

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	public function to_array( $check_required = true ): array {
		if ( $check_required ) {
			$this->check_required();
		}

		return [
			"amount"      => $this->amount,
			"description" => $this->description,
			"due"         => $this->due,
			"retryDays"   => $this->retry_days,
			...$this->conditional( "transactionType", $this->transaction_type ),
			...$this->conditional( "orderId", $this->order_id ),
		];
	}
}
