<?php

class WC_Vipps_Agreement_Initial_Charge extends WC_Vipps_Model {
	const TRANSACTION_TYPE_RESERVE_CAPTURE = "RESERVE_CAPTURE";
	const TRANSACTION_TYPE_DIRECT_CAPTURE = "DIRECT_CAPTURE";

	protected array $valid_transaction_types = [
		self::TRANSACTION_TYPE_RESERVE_CAPTURE,
		self::TRANSACTION_TYPE_DIRECT_CAPTURE
	];

	protected array $required_fields = [ "amount", "description", "transaction_type" ];

	private int $amount;
	private string $description;
	private string $transaction_type;
	private string $order_id;

	public function set_amount( int $amount ): self {
		$this->amount = $amount;

		return $this;
	}

	public function set_description( string $description ): self {
		$this->description = $description;

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

	public function set_order_id( string $order_id ): self {
		$this->order_id = $order_id;

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	function to_array(): array {
		$this->check_required();

		return [
			"amount"          => $this->amount,
			"description"     => $this->description,
			"transactionType" => $this->transaction_type,
			...$this->conditional( "orderId", $this->order_id )
		];
	}
}
