<?php

abstract class WC_Vipps_Model {
	protected array $required_fields = [];

	public function __construct() {
		return $this;
	}

	abstract function to_array(): array;

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 */
	protected function check_required(?string $keyed_by = null): void {
		$class = get_class( $this );

		$fields = !$keyed_by ? $this->required_fields : $this->required_fields[$keyed_by];

		foreach ( $fields as $value ) {
			if ( ! $this->{$value} ) {
				throw new WC_Vipps_Recurring_Missing_Value_Exception( "Incorrect usage. Required value $value is missing in $class." );
			}
		}
	}

	protected function conditional( string $name, $value ): array {
		if ( ! $value ) {
			return [];
		}

		if ( is_subclass_of( $value, WC_Vipps_Model::class ) ) {
			$value = $value->to_array();
		}

		return [ $name => $value ];
	}
}
