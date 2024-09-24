<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session_Response extends WC_Vipps_Model {
	protected array $required_fields = [
		"token",
		"checkout_frontend_url",
		"polling_url"
	];

	public ?string $token = null;
	public ?string $checkout_frontend_url = null;
	public ?string $polling_url = null;

	public function set_token( string $token ): self {
		$this->token = $token;

		return $this;
	}

	public function set_checkout_frontend_url( string $checkout_frontend_url ): self {
		$this->checkout_frontend_url = $checkout_frontend_url;

		return $this;
	}

	public function set_polling_url( string $polling_url ): self {
		$this->polling_url = $polling_url;

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
				"token"               => $this->token,
				"checkoutFrontendUrl" => $this->checkout_frontend_url,
				"pollingUrl"          => $this->polling_url,
			],
		);
	}
}
