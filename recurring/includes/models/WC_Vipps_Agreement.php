<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Agreement extends WC_Vipps_Model {
	public const STATUS_ACTIVE = "ACTIVE";
	public const STATUS_PENDING = "PENDING";
	public const STATUS_STOPPED = "STOPPED";
	public const STATUS_EXPIRED = "EXPIRED";

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
		"product_name",
		"external_id",
	];

	public ?string $id = null;

	public ?string $external_id = null;

	/**
	 * @var DateTime|string $start
	 */
	public $start;
	/**
	 * @var DateTime|string $start
	 */
	public $stop;
	public ?string $status = null;
	public ?WC_Vipps_Agreement_Campaign $campaign = null;
	public ?WC_Vipps_Agreement_Pricing $pricing = null;
	public ?string $phone_number = null;
	public ?WC_Vipps_Agreement_Initial_Charge $initial_charge = null;
	public ?WC_Vipps_Agreement_Interval $interval = null;
	public ?bool $is_app = null;
	public ?string $merchant_agreement_url = null;
	public ?string $merchant_redirect_url = null;
	public ?string $product_name = null;
	public ?string $product_description = null;
	public ?string $scope = null;
	public ?bool $skip_landing_page = null;
	public ?string $sub = null;
	public ?string $userinfo_url = null;

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
	 * @param DateTime|string $start
	 *
	 * @throws Exception
	 */
	public function set_start( $start ): self {
		if ( is_string( $start ) ) {
			$this->start = new DateTime( $start );
		} else {
			$this->start = $start;
		}

		return $this;
	}

	/**
	 * @param DateTime|string $stop
	 *
	 * @throws Exception
	 */
	public function set_stop( $stop ): self {
		if ( is_string( $stop ) ) {
			$this->stop = new DateTime( $stop );
		} else {
			$this->stop = $stop;
		}

		return $this;
	}

	public function set_id( string $id ): self {
		$this->id = $id;

		return $this;
	}

	public function set_external_id( string $external_id ): self {
		$this->external_id = $external_id;

		return $this;
	}

	/**
	 * @param WC_Vipps_Agreement_Campaign|array $campaign
	 */
	public function set_campaign( $campaign ): self {
		return $this->_set_value( 'campaign', $campaign, WC_Vipps_Agreement_Campaign::class );
	}

	/**
	 * @param WC_Vipps_Agreement_Pricing|array $pricing
	 */
	public function set_pricing( $pricing ): self {
		return $this->_set_value( 'pricing', $pricing, WC_Vipps_Agreement_Pricing::class );
	}

	public function set_phone_number( string $phone_number ): self {
		$this->phone_number = $phone_number;

		return $this;
	}

	/**
	 * @param WC_Vipps_Agreement_Initial_Charge|array $initial_charge
	 */
	public function set_initial_charge( $initial_charge ): self {
		return $this->_set_value( 'initial_charge', $initial_charge, WC_Vipps_Agreement_Initial_Charge::class );
	}

	/**
	 * @param WC_Vipps_Agreement_Interval|array $interval
	 */
	public function set_interval( $interval ): self {
		return $this->_set_value( 'interval', $interval, WC_Vipps_Agreement_Interval::class );
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
		if ( strlen( $product_name ) > 45 ) {
			$product_name = mb_substr( $product_name, 0, 42 ) . '...';
		}

		$this->product_name = $product_name;

		return $this;
	}

	public function set_product_description( string $product_description ): self {
		if ( strlen( $product_description ) > 100 ) {
			$product_description = mb_substr( $product_description, 0, 97 ) . '...';
		}

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

	public function set_sub( string $sub ): self {
		$this->sub = $sub;

		return $this;
	}

	public function set_userinfo_url( string $userinfo_url ): self {
		$this->userinfo_url = $userinfo_url;

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
				"productName" => $this->product_name,
				"pricing"     => $this->pricing ? $this->pricing->to_array( $check_required ) : null,
				"interval"    => $this->interval ? $this->interval->to_array( $check_required ) : null,
			],
			$this->conditional( "start", $this->start ),
			$this->conditional( "stop", $this->stop ),
			$this->conditional( "id", $this->id ),
			$this->conditional( "externalId", $this->external_id ),
			$this->conditional( "status", $this->status ),
			$this->conditional( "campaign", $this->campaign ),
			$this->conditional( "phoneNumber", $this->phone_number ),
			$this->conditional( "initialCharge", $this->initial_charge ),
			$this->conditional( "isApp", $this->is_app ),
			$this->conditional( "merchantAgreementUrl", $this->merchant_agreement_url ),
			$this->conditional( "merchantRedirectUrl", $this->merchant_redirect_url ),
			$this->conditional( "productDescription", $this->product_description ),
			$this->conditional( "scope", $this->scope ),
			$this->conditional( "skipLandingPage", $this->skip_landing_page ),
			$this->conditional( "sub", $this->sub ),
			$this->conditional( "userinfoUrl", $this->userinfo_url ),
		);
	}
}
