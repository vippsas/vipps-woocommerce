<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session_Transaction_Order_Summary_Line extends WC_Vipps_Model {
	protected array $required_fields = [
		"name",
		"id",
		"total_amount",
		"total_amount_excluding_tax",
		"total_tax_amount",
		"tax_percentage"
	];

	public ?string $name = null;
	public ?string $id = null;
	public ?int $total_amount = null;
	public ?int $total_amount_excluding_tax = null;
	public ?int $total_tax_amount = null;
	public ?int $tax_percentage = null;
	public ?array $unit_info = null;
	public ?int $discount = null;
	public ?string $product_url = null;
	public ?bool $is_return = null;
	public ?bool $is_shipping = null;

	public function set_name( string $name ): self {
		$this->name = $name;

		return $this;
	}

	public function set_id( string $id ): self {
		$this->id = $id;

		return $this;
	}

	public function set_total_amount( int $total_amount ): self {
		$this->total_amount = $total_amount;

		return $this;
	}

	public function set_total_amount_excluding_tax( int $total_amount_excluding_tax ): self {
		$this->total_amount_excluding_tax = $total_amount_excluding_tax;

		return $this;
	}

	public function set_total_tax_amount( int $total_tax_amount ): self {
		$this->total_tax_amount = $total_tax_amount;

		return $this;
	}

	public function set_tax_percentage( int $tax_percentage ): self {
		$this->tax_percentage = $tax_percentage;

		return $this;
	}

	public function set_unit_info( int $unit_price, int $quantity, string $quantity_unit ): self {
		$this->unit_info = [
			"unitPrice"    => $unit_price,
			"quantity"     => $quantity,
			"quantityUnit" => $quantity_unit
		];

		return $this;
	}

	public function set_discount( int $discount ): self {
		$this->discount = $discount;

		return $this;
	}

	public function set_product_url( string $product_url ): self {
		$this->product_url = $product_url;

		return $this;
	}

	public function set_return( bool $return ): self {
		$this->is_return = $return;

		return $this;
	}

	public function set_shipping( bool $shipping ): self {
		$this->is_shipping = $shipping;

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
				"name" => $this->name,
				"id" => $this->id,
				"totalAmount" => $this->total_amount,
				"totalAmountExcludingTax" => $this->total_amount_excluding_tax,
				"totalTaxAmount" => $this->total_tax_amount,
				"taxPercentage" => $this->tax_percentage
			],
			$this->conditional("unitInfo", $this->unit_info),
			$this->conditional("discount", $this->discount),
			$this->conditional("productUrl", $this->product_url),
			$this->conditional("isReturn", $this->is_return),
			$this->conditional("isShipping", $this->is_shipping),
		);
	}
}
