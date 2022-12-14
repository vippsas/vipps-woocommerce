<?php

class WC_Vipps_Agreement extends WC_Vipps_Model {
	const STATUS_ACTIVE = "ACTIVE";
	const STATUS_PENDING = "PENDING ";
	const STATUS_STOPPED = "STOPPED ";
	const STATUS_EXPIRED = "EXPIRED";

	protected array $valid_statuses = [
		self::STATUS_ACTIVE,
		self::STATUS_PENDING,
		self::STATUS_STOPPED,
		self::STATUS_EXPIRED,
	];

	protected array $required_fields = [
		"pricing",
		"interval",
		"merchant_agreement_url",
		"merchant_redirect_url",
		"product_name"
	];

	private string $id;
	private string $status;
	private WC_Vipps_Agreement_Campaign $campaign;
	private WC_Vipps_Agreement_Pricing $pricing;
	private string $phone_number;
	private WC_Vipps_Agreement_Initial_Charge $initial_charge;
	private WC_Vipps_Agreement_Interval $interval;
	private bool $is_app;
	private string $merchant_agreement_url;
	private string $merchant_redirect_url;
	private string $product_name;
	private string $product_description;
	private string $scope;
	private bool $skip_landing_page;

	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_status( string $status ): self {
		if ( ! in_array( $status, $this->valid_statuses ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$status is not a valid value for `status` in $class." );
		}

		$this->status = $status;

		return $this;
	}

	public function set_campaign( WC_Vipps_Agreement_Campaign $campaign ): self {
		$this->campaign = $campaign;

		return $this;
	}

	public function set_pricing( WC_Vipps_Agreement_Pricing $pricing ): self {
		$this->pricing = $pricing;

		return $this;
	}

	public function set_phone_number( string $phone_number ): self {
		$this->phone_number = $phone_number;

		return $this;
	}

	public function set_initial_charge( WC_Vipps_Agreement_Initial_Charge $initial_charge ): self {
		$this->initial_charge = $initial_charge;

		return $this;
	}

	public function set_interval( WC_Vipps_Agreement_Interval $interval ): self {
		$this->interval = $interval;

		return $this;
	}

	public function set_is_app( bool $is_app ): self {
		$this->is_app = $is_app;

		return $this;
	}

	public function set_merchant_agreement_url( string $merchant_agreement_url ): self {
		$this->merchant_agreement_url = $merchant_agreement_url;

		return $this;
	}

	public function set_merchant_redirect_url( string $merchant_redirect_url ): self {
		$this->merchant_redirect_url = $merchant_redirect_url;

		return $this;
	}

	public function set_product_name( string $product_name ): self {
		$this->product_name = $product_name;

		return $this;
	}

	public function set_product_description( string $product_description ): self {
		$this->product_description = $product_description;

		return $this;
	}

	public function set_scope( string $scope ): self {
		$this->scope = $scope;

		return $this;
	}

	public function set_skip_landing_page( bool $skip_landing_page ): self {
		$this->skip_landing_page = $skip_landing_page;

		return $this;
	}

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	public function to_array(): array {
		$this->check_required();

		return [
			"product_name" => $this->product_name,
			"pricing"      => $this->pricing->to_array(),
			"interval"     => $this->interval->to_array(),
			...$this->conditional( "status", $this->status ),
			...$this->conditional( "campaign", $this->campaign ),
			...$this->conditional( "phoneNumber", $this->phone_number ),
			...$this->conditional( "initialCharge", $this->initial_charge ),
			...$this->conditional( "isApp", $this->is_app ),
			...$this->conditional( "merchantAgreementUrl", $this->merchant_agreement_url ),
			...$this->conditional( "merchantRedirectUrl", $this->merchant_redirect_url ),
			...$this->conditional( "productDescription", $this->product_description ),
			...$this->conditional( "scope", $this->scope ),
			...$this->conditional( "skipLandingPage", $this->skip_landing_page ),
		];
	}
}
