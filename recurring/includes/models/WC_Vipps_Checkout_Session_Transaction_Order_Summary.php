<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session_Transaction_Order_Summary extends WC_Vipps_Model {
	protected array $required_fields = [
		"order_lines",
		"order_bottom_line"
	];

	public ?array $order_lines = null;
	public ?WC_Vipps_Checkout_Session_Transaction_Order_Summary_Bottom_Line $order_bottom_line = null;

	public function set_order_lines( array $order_lines ): self {
		$this->order_lines = $order_lines;

		return $this;
	}

	public function set_order_bottom_line( $order_bottom_line ): self {
		return $this->_set_value( "order_bottom_line", $order_bottom_line, WC_Vipps_Checkout_Session_Transaction_Order_Summary_Bottom_Line::class );
	}

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	public function to_array( bool $check_required = false ): array {
		if ( $check_required ) {
			$this->check_required();
		}

		return [
			"orderLines"      => $this->order_lines,
			"orderBottomLine" => $this->order_bottom_line,
		];
	}
}
