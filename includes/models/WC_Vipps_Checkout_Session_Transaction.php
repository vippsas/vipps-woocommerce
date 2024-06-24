<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session_Transaction extends WC_Vipps_Model {
	protected array $required_fields = [
		"amount",
		"reference",
		"payment_description"
	];

	public ?WC_Vipps_Checkout_Session_Amount $amount = null;
	public ?string $reference = null;
	public ?string $payment_description = null;
	public ?WC_Vipps_Checkout_Session_Transaction_Order_Summary $order_summary = null;

	public function set_amount( $amount ): self {
		return $this->_set_value( "amount", $amount, WC_Vipps_Checkout_Session_Amount::class );
	}

	public function set_reference( string $reference ): self {
		$this->reference = $reference;

		return $this;
	}

	public function set_payment_description( string $payment_description ): self {
		$this->payment_description = $payment_description;

		return $this;
	}

	public function set_order_summary( $order_summary ): self {
		return $this->_set_value( "order_summary", $order_summary, WC_Vipps_Checkout_Session_Transaction_Order_Summary::class );
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
				"amount"             => $this->amount,
				"reference"          => $this->reference,
				"paymentDescription" => $this->payment_description,
			],
			$this->conditional( "orderSummary", $this->order_summary ),
		);
	}
}
