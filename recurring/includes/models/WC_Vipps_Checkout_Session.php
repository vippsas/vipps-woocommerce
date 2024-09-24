<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session extends WC_Vipps_Model {
	public const TYPE_SUBSCRIPTION = "SUBSCRIPTION";
	public const TYPE_PAYMENT = "PAYMENT";

	protected array $valid_types = [
		self::TYPE_SUBSCRIPTION,
		self::TYPE_PAYMENT,
	];

	protected array $required_fields = [
		"SUBSCRIPTION" => [ "type", "subscription" ],
		"PAYMENT"      => [ "type", "transaction" ],
	];

	public ?string $type = null;
	public ?WC_Vipps_Checkout_Session_Customer $prefill_customer = null;
	public ?WC_Vipps_Checkout_Session_Merchant_Info $merchant_info = null;
	public ?WC_Vipps_Checkout_Session_Configuration $configuration = null;
	public ?WC_Vipps_Checkout_Session_Subscription $subscription = null;
	public ?WC_Vipps_Checkout_Session_Transaction $transaction = null;

	/**
	 * @throws WC_Vipps_Recurring_Invalid_Value_Exception
	 */
	public function set_type( string $type ): self {
		if ( ! in_array( $type, $this->valid_types, true ) ) {
			$class = get_class( $this );
			throw new WC_Vipps_Recurring_Invalid_Value_Exception( "$type is not a valid value for `type` in $class." );
		}

		$this->type = $type;

		return $this;
	}

	public function set_prefill_customer( WC_Vipps_Checkout_Session_Customer $prefill_customer ): self {
		$this->prefill_customer = $prefill_customer;

		return $this;
	}

	public function set_merchant_info( WC_Vipps_Checkout_Session_Merchant_Info $merchant_info ): self {
		$this->merchant_info = $merchant_info;

		return $this;
	}

	public function set_configuration( WC_Vipps_Checkout_Session_Configuration $configuration ): self {
		$this->configuration = $configuration;

		return $this;
	}

	public function set_subscription( WC_Vipps_Checkout_Session_Subscription $subscription ): self {
		$this->subscription = $subscription;

		return $this;
	}

	public function set_transaction( $transaction ): self {
		return $this->_set_value( "transaction", $transaction, WC_Vipps_Checkout_Session_Transaction::class );
	}

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	public function to_array( bool $check_required = false ): array {
		if ( $check_required ) {
			$this->check_required( $this->type );
		}

		return array_merge(
			[
				"type" => $this->type,
			],
			$this->conditional( "prefillCustomer", $this->prefill_customer, true ),
			$this->conditional( "merchantInfo", $this->merchant_info ),
			$this->conditional( "configuration", $this->configuration ),
			$this->conditional( "subscription", $this->subscription ),
			$this->conditional( "transaction", $this->transaction ),
		);
	}
}
