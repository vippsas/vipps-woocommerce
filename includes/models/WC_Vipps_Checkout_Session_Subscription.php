<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session_Subscription extends WC_Vipps_Model {
	protected array $required_fields = [
		"reference",
		"product_name",
		"pricing",
		"interval",
		"merchant_agreement_url"
	];

	public ?string $product_name = null;
	public ?string $product_description = null;
	public ?WC_Vipps_Checkout_Session_Amount $amount = null;
	public ?WC_Vipps_Agreement_Interval $interval = null;
	public ?string $merchant_agreement_url = null;
	public ?WC_Vipps_Agreement_Initial_Charge $initial_charge = null;
	public ?WC_Vipps_Agreement_Campaign $campaign = null;

	public function set_product_name( string $product_name ): self {
		$this->product_name = $product_name;

		return $this;
	}

	public function set_product_description( string $product_description ): self {
		$this->product_description = $product_description;

		return $this;
	}

	public function set_amount( $amount ): self {
		return $this->_set_value( 'amount', $amount, WC_Vipps_Checkout_Session_Amount::class );
	}

	public function set_interval( $interval ): self {
		return $this->_set_value( 'interval', $interval, WC_Vipps_Agreement_Interval::class );
	}

	public function set_merchant_agreement_url( string $merchant_agreement_url ): self {
		$this->merchant_agreement_url = $merchant_agreement_url;

		return $this;
	}

	public function set_campaign( $campaign ): self {
		return $this->_set_value( 'campaign', $campaign, WC_Vipps_Agreement_Campaign::class );
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
				"productName"          => $this->product_name,
				"amount"               => $this->amount,
				"interval"             => $this->interval,
				"merchantAgreementUrl" => $this->merchant_agreement_url,
			],
			$this->conditional( "productDescription", $this->product_description ),
			$this->conditional( "campaign", $this->campaign ),
		);
	}
}
