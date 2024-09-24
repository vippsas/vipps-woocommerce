<?php

defined( 'ABSPATH' ) || exit;

class WC_Vipps_Checkout_Session_Customer extends WC_Vipps_Model {
	public ?string $first_name = null;
	public ?string $last_name = null;
	public ?string $email = null;
	public ?string $phone_number = null;
	public ?string $street_address = null;
	public ?string $city = null;
	public ?string $postal_code = null;
	public ?string $country = null;

	public function set_first_name( string $first_name ): self {
		$this->first_name = $first_name;

		return $this;
	}

	public function set_last_name( string $last_name ): self {
		$this->last_name = $last_name;

		return $this;
	}

	public function set_email( string $email ): self {
		$this->email = $email;

		return $this;
	}

	public function set_phone_number( string $phone_number ): self {
		$this->phone_number = $phone_number;

		return $this;
	}

	public function set_street_address( string $street_address ): self {
		$this->street_address = $street_address;

		return $this;
	}

	public function set_city( string $city ): self {
		$this->city = $city;

		return $this;
	}

	public function set_postal_code( string $postal_code ): self {
		$this->postal_code = $postal_code;

		return $this;
	}

	public function set_country( string $country ): self {
		$this->country = $country;

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
			$this->conditional( "firstName", $this->first_name ),
			$this->conditional( "lastName", $this->last_name ),
			$this->conditional( "email", $this->email ),
			$this->conditional( "phoneNumber", $this->phone_number ),
			$this->conditional( "streetAddress", $this->street_address ),
			$this->conditional( "city", $this->city ),
			$this->conditional( "postalCode", $this->postal_code ),
			$this->conditional( "country", $this->country ),
		);
	}
}
