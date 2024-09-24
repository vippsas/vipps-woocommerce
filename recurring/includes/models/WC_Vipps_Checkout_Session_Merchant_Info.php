<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session_Merchant_Info extends WC_Vipps_Model {
	protected array $required_fields = [
		"callback_url",
		"return_url",
		"callback_authorization_token"
	];

	public ?string $callback_url = null;
	public ?string $return_url = null;
	public ?string $callback_authorization_token = null;
	public ?string $terms_and_conditions_url = null;

	public function set_callback_url( string $callback_url ): self {
		$this->callback_url = $callback_url;

		return $this;
	}

	public function set_return_url( string $return_url ): self {
		$this->return_url = $return_url;

		return $this;
	}

	public function set_callback_authorization_token( string $callback_authorization_token ): self {
		$this->callback_authorization_token = $callback_authorization_token;

		return $this;
	}

	public function set_terms_and_conditions_url( string $terms_and_conditions_url ): self {
		$this->terms_and_conditions_url = $terms_and_conditions_url;

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
				"callbackUrl"                => $this->callback_url,
				"returnUrl"                  => $this->return_url,
				"callbackAuthorizationToken" => $this->callback_authorization_token,
			],
			$this->conditional( "termsAndConditionsUrl", $this->terms_and_conditions_url ),
		);
	}
}
