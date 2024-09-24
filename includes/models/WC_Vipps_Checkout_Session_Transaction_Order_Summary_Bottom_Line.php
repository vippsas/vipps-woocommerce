<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session_Transaction_Order_Summary_Bottom_Line extends WC_Vipps_Model {
	protected array $required_fields = [
		"currency"
	];

	public ?string $currency = null;
	public ?int $tip_amount = null;
	public ?int $gift_card_amount = null;
	public ?string $terminal_id = null;
	public ?array $payment_sources = null;
	public ?string $receipt_number = null;

	public function set_currency( string $currency ): self {
		$this->currency = $currency;

		return $this;
	}

	public function set_tip_amount( int $tip_amount ): self {
		$this->tip_amount = $tip_amount;

		return $this;
	}

	public function set_gift_card_amount( int $gift_card_amount ): self {
		$this->gift_card_amount = $gift_card_amount;

		return $this;
	}

	public function set_terminal_id( string $terminal_id ): self {
		$this->terminal_id = $terminal_id;

		return $this;
	}

	public function set_payment_sources( int $gift_card = null, int $card = null, int $voucher = null, int $cash = null ): self {
		$this->payment_sources = array_merge(
			$this->conditional( "giftCard", $gift_card ),
			$this->conditional( "card", $card ),
			$this->conditional( "voucher", $voucher ),
			$this->conditional( "cash", $cash ),
		);

		return $this;
	}

	public function set_receipt_number( string $receipt_number ): self {
		$this->receipt_number = $receipt_number;

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
				"currency" => $this->currency
			],
			$this->conditional( "tipAmount", $this->tip_amount ),
			$this->conditional( "giftCardAmount", $this->gift_card_amount ),
			$this->conditional( "terminalId", $this->terminal_id ),
			$this->conditional( "paymentSources", $this->payment_sources ),
			$this->conditional( "receiptNumber", $this->receipt_number ),
		);
	}
}
