<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Agreement_Initial_Charge extends WC_Vipps_Model {
	public const TRANSACTION_TYPE_RESERVE_CAPTURE = "RESERVE_CAPTURE";
	public const TRANSACTION_TYPE_DIRECT_CAPTURE = "DIRECT_CAPTURE";

	protected array $valid_transaction_types = [
		self::TRANSACTION_TYPE_RESERVE_CAPTURE,
		self::TRANSACTION_TYPE_DIRECT_CAPTURE
	];

	protected array $required_fields = [ "amount", "description", "transaction_type" ];

	public ?int $amount = null;
	public ?string $description = null;
	public ?string $transaction_type = null;
	public ?string $order_id = null;
	public ?string $external_id = null;

	public function set_amount( int $amount ): self {
		$this->amount = $amount;

		return $this;
	}

	public function set_description( string $description ): self {
		if ( strlen( $description ) > 45 ) {
			$description = mb_substr( $description, 0, 42 ) . '...';
		}

		$this->description = $description;

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

	public function set_order_id( string $order_id ): self {
		$this->order_id = $order_id;

		return $this;
	}

	public function set_external_id( string $external_id ): self {
		$this->external_id = $external_id;

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
				"amount"          => $this->amount,
				"description"     => $this->description,
				"transactionType" => $this->transaction_type,
			],
			$this->conditional( "orderId", $this->order_id ),
			$this->conditional( "externalId", $this->external_id )
		);
	}
}
