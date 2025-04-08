<?php

defined( 'ABSPATH' ) || exit;

require_once( __DIR__ . '/wc-vipps-recurring-exceptions.php' );

/**
 * Class WC_Vipps_Recurring_Api
 */
class WC_Vipps_Recurring_Api {
	public WC_Gateway_Vipps_Recurring $gateway;

	/**
	 * Amount of days to retry a payment for when creating a charge
	 */
	public int $retry_days = WC_VIPPS_RECURRING_RETRY_DAYS;


	public function __construct( WC_Gateway_Vipps_Recurring $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * @return mixed|string|null
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function get_access_token( bool $force_fresh = false ) {
		$stored = get_transient( '_vipps_recurring_token' );

		if ( ! $force_fresh && $stored && $stored['expires_on'] > time() ) {
			return $stored['access_token'];
		}

		$token = $this->get_access_token_from_vipps();

		if ( ! $token ) {
			return null;
		}

		set_transient( '_vipps_recurring_token', $token, $token['expires_in'] / 2 );

		return $token['access_token'];
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	private function get_access_token_from_vipps(): array {
		try {
			return $this->http_call( 'accessToken/get', 'POST' );
		} catch ( WC_Vipps_Recurring_Temporary_Exception $e ) {
			WC_Vipps_Recurring_Logger::log( 'Could not get Vipps/MobilePay access token ' . $e->getMessage() );

			throw $e;
		} catch ( Exception $e ) {
			WC_Vipps_Recurring_Logger::log( 'Could not get Vipps/MobilePay access token ' . $e->getMessage() );

			throw new WC_Vipps_Recurring_Config_Exception( $e->getMessage() );
		}
	}

	public function generate_idempotency_key(): string {
		return wp_generate_password( 24, false );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function create_agreement( WC_Vipps_Agreement $agreement, string $idempotency_key ): array {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'   => 'Bearer ' . $token,
			'Idempotency-Key' => $idempotency_key,
		];

		$data = apply_filters( 'wc_vipps_recurring_create_agreement_data', $agreement->to_array( true ) );

		return $this->http_call( 'recurring/v3/agreements', 'POST', $data, $headers );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function get_agreement( string $agreement_id ): WC_Vipps_Agreement {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		return new WC_Vipps_Agreement( $this->http_call( 'recurring/v3/agreements/' . $agreement_id, 'GET', [], $headers ) );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function update_agreement( string $agreement_id, WC_Vipps_Agreement $agreement, string $idempotency_key ): void {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'   => 'Bearer ' . $token,
			'Idempotency-Key' => $idempotency_key,
		];

		$data = apply_filters( 'wc_vipps_recurring_update_agreement_data', $agreement->to_array() );

		$this->http_call( 'recurring/v3/agreements/' . $agreement_id, 'PATCH', $data, $headers );
	}

	/**
	 * @throws Exception
	 */
	private function get_price_from_agreement( WC_Vipps_Agreement $agreement ): ?int {
		$amount = $agreement->pricing->amount;

		if ( $agreement->campaign ) {
			$now = new DateTime();
			$end = $agreement->campaign->end;

			if ( $end > $now ) {
				$amount = $agreement->campaign->price;
			}
		}

		return $amount;
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function cancel_agreement( string $agreement_id, string $idempotency_key ): void {
		$agreement = $this->get_agreement( $agreement_id );
		if ( $agreement->status !== WC_Vipps_Agreement::STATUS_ACTIVE ) {
			return;
		}

		$token = $this->get_access_token();

		$headers = [
			'Authorization'   => 'Bearer ' . $token,
			'Idempotency-Key' => $idempotency_key,
		];

		$data = apply_filters( 'wc_vipps_recurring_cancel_agreement_data', [
			'status' => WC_Vipps_Agreement::STATUS_STOPPED,
		] );

		$this->http_call( 'recurring/v3/agreements/' . $agreement_id, 'PATCH', $data, $headers );
	}

	/**
	 * @return mixed|string|null
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function capture_reserved_charge( WC_Vipps_Agreement $agreement, WC_Vipps_Charge $charge, string $idempotency_key ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'   => 'Bearer ' . $token,
			'Idempotency-Key' => $idempotency_key,
		];

		return $this->http_call( 'recurring/v3/agreements/' . $agreement->id . '/charges/' . $charge->id . '/capture', 'POST', $charge->to_array(), $headers );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 * @throws Exception
	 */
	public function create_charge( WC_Vipps_Agreement $agreement, string $idempotency_key, ?int $amount = null ): array {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'   => 'Bearer ' . $token,
			'Idempotency-Key' => $idempotency_key,
		];

		if ( $amount === null ) {
			$amount = $this->get_price_from_agreement( $agreement );
		}

		// minimum of 2 days
		$due_at = date( 'Y-m-d', time() + 3600 * 24 * 2 );

		$charge = ( new WC_Vipps_Charge() )->set_amount( $amount )
		                                   ->set_description( $agreement->product_name ?? get_bloginfo() )
		                                   ->set_transaction_type( WC_Vipps_Charge::TRANSACTION_TYPE_DIRECT_CAPTURE )
		                                   ->set_due( $due_at )
		                                   ->set_retry_days( $this->retry_days );

		$data = apply_filters( 'wc_vipps_recurring_create_charge_data', $charge->to_array() );

		return $this->http_call( 'recurring/v3/agreements/' . $agreement->id . '/charges', 'POST', $data, $headers );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function cancel_charge( string $agreement_id, string $charge_id, string $idempotency_key ): void {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'   => 'Bearer ' . $token,
			'Idempotency-Key' => $idempotency_key,
		];

		$this->http_call( 'recurring/v3/agreements/' . $agreement_id . '/charges/' . $charge_id, 'DELETE', [], $headers );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function get_charge( string $agreement_id, string $charge_id ): WC_Vipps_Charge {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		return new WC_Vipps_Charge( $this->http_call( 'recurring/v3/agreements/' . $agreement_id . '/charges/' . $charge_id, 'GET', [], $headers ) );
	}

	/**
	 * @return mixed|string|null
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function refund_charge( string $agreement_id, string $charge_id, ?int $amount = null, ?string $reason = null ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'   => 'Bearer ' . $token,
			'Idempotency-Key' => $this->generate_idempotency_key(),
		];

		if ( $reason !== null && strlen( $reason ) > 99 ) {
			$reason = mb_substr( $reason, 0, 90 );
		}

		$data = [
			'description' => $reason ?: 'Refund',
		];

		if ( $amount !== null ) {
			$data = array_merge( $data, [
				'amount' => $amount,
			] );
		}

		$data = apply_filters( 'wc_vipps_recurring_refund_charge_data', $data );

		return $this->http_call( 'recurring/v3/agreements/' . $agreement_id . '/charges/' . $charge_id . '/refund', 'POST', $data, $headers );
	}

	/**
	 * @return WC_Vipps_Charge[]
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function get_charges_for( string $agreement_id ): array {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		return array_map( static function ( $charge ) {
			return new WC_Vipps_Charge( $charge );
		}, $this->http_call( 'recurring/v3/agreements/' . $agreement_id . '/charges', 'GET', [], $headers ) );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function get_userinfo( WC_Vipps_Agreement $agreement ): array {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		$endpoint = str_replace( $this->get_base_url(), '', $agreement->userinfo_url );

		return $this->http_call( $endpoint, 'GET', [], $headers );
	}

	/**
	 * @return array
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function get_webhooks( string $msn ): array {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'          => 'Bearer ' . $token,
			'Merchant-Serial-Number' => $msn,
		];

		return $this->http_call( 'webhooks/v1/webhooks', 'GET', [], $headers );
	}

	/**
	 * @return array
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function register_webhook( string $msn ): array {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'          => 'Bearer ' . $token,
			'Merchant-Serial-Number' => $msn,
		];

		$callback_url = $this->gateway->webhook_callback_url();

		return $this->http_call( 'webhooks/v1/webhooks', 'POST', [
			'url'    => $callback_url,
			'events' => [
				'recurring.agreement-activated.v1',
				'recurring.agreement-rejected.v1',
				'recurring.agreement-stopped.v1',
				'recurring.agreement-expired.v1',
				'recurring.charge-reserved.v1',
				'recurring.charge-captured.v1',
				'recurring.charge-canceled.v1',
				'recurring.charge-failed.v1',
			]
		], $headers );
	}

	/**
	 * @param string $id
	 *
	 * @return array|string
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function delete_webhook( string $msn, string $id ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'          => 'Bearer ' . $token,
			'Merchant-Serial-Number' => $msn,
		];

		return $this->http_call( 'webhooks/v1/webhooks/' . $id, 'DELETE', [], $headers );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function checkout_poll( string $endpoint ) {
		if ( ! str_starts_with( $endpoint, 'http' ) ) {
			$endpoint = 'checkout/v3/session/' . $endpoint;
		}

		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		return $this->http_call( $endpoint, 'GET', [], $headers );
	}

	/**
	 * @throws WC_Vipps_Recurring_Missing_Value_Exception
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function checkout_initiate( WC_Vipps_Checkout_Session $checkout_session ): WC_Vipps_Checkout_Session_Response {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		$response = $this->http_call( 'checkout/v3/session', 'POST', $checkout_session->to_array(), $headers );

		return new WC_Vipps_Checkout_Session_Response( $response );
	}

	private function get_base_url(): string {
		if ( $this->get_test_mode() ) {
			return 'https://apitest.vipps.no';
		}

		return 'https://api.vipps.no';
	}

	private function get_test_mode(): bool {
		return $this->gateway->get_option( 'test_mode' ) === "yes" || WC_VIPPS_RECURRING_TEST_MODE;
	}

	/**
	 * @return mixed|string|null
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	private function http_call( string $endpoint, string $method, array $data = [], array $headers = [], $encoding = 'json' ) {
//		WC_Vipps_Recurring_Logger::log( sprintf( "Calling Vipps API endpoint: [%s] %s", $method, $endpoint ) );

		$url = $endpoint;
		if ( ! str_starts_with( $endpoint, "http" ) ) {
			$url = $this->get_base_url() . '/' . $endpoint;
		}

		$client_id              = $this->gateway->get_option( "client_id" );
		$secret_key             = $this->gateway->get_option( "secret_key" );
		$subscription_key       = $this->gateway->get_option( "subscription_key" );
		$merchant_serial_number = $this->gateway->get_option( "merchant_serial_number" );

		if ( $this->get_test_mode() ) {
			$client_id              = $this->gateway->get_option( "test_client_id" );
			$secret_key             = $this->gateway->get_option( "test_secret_key" );
			$subscription_key       = $this->gateway->get_option( "test_subscription_key" );
			$merchant_serial_number = $this->gateway->get_option( "test_merchant_serial_number" );
		}

		if ( ! $subscription_key || ! $secret_key || ! $client_id ) {
			throw new WC_Vipps_Recurring_Config_Exception( __( 'Your Vipps/MobilePay Recurring Payments gateway is not correctly configured.', 'woo-vipps' ) );
		}

		$system_plugin_version = WC_VIPPS_RECURRING_VERSION;
		$checkout_enabled      = get_option( WC_Vipps_Recurring_Helper::OPTION_CHECKOUT_ENABLED, false );

		if ( $checkout_enabled ) {
			$system_plugin_version = $system_plugin_version . '/checkout';
		}

		$headers = array_merge( [
			'client_id'                   => $client_id,
			'client_secret'               => $secret_key,
			'Ocp-Apim-Subscription-Key'   => $subscription_key,
			'Merchant-Serial-Number'      => $merchant_serial_number,
			'Vipps-System-Name'           => 'woocommerce',
			'Vipps-System-Version'        => get_bloginfo( 'version' ) . '/' . ( defined( 'WC_VERSION' ) ? WC_VERSION : '0.0.0' ),
			'Vipps-System-Plugin-Name'    => 'woo-vipps-recurring',
			'Vipps-System-Plugin-Version' => $system_plugin_version
		], $headers );

		if ( $encoding === 'url' || $method === 'GET' ) {
			$data_encoded = http_build_query( $data );
		} else {
			$data_encoded = json_encode( $data );
		}

		$data_len              = strlen( $data_encoded );
		$headers['Connection'] = 'close';

		if ( $method !== 'GET' ) {
			$headers['Content-length'] = $data_len;
			$headers['Content-type']   = $encoding === 'url' ? 'application/x-www-form-urlencoded' : 'application/json';
		}

		$args            = [];
		$args['method']  = $method;
		$args['headers'] = $headers;

		if ( $method !== 'GET' ) {
			$args['body'] = $data_encoded;
		}

		if ( $method === 'GET' && $data_encoded ) {
			$url .= "?$data_encoded";
		}

		$response = wp_remote_request( $url, $args );

		// throw WP error as a WC_Vipps_Recurring_Exception if response is not valid
		$default_error = '';
		if ( is_wp_error( $response ) ) {
			$default_error = "500 " . $response->get_error_message();
		}

		// Parse the result, converting it to exceptions if necessary
		return $this->handle_http_response( $response, $args['body'] ?? "<empty>", $endpoint, $default_error );
	}

	/**
	 * @param $response
	 * @param $request_body
	 * @param $endpoint
	 * @param $default_error
	 *
	 * @return mixed|string|null
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	private function handle_http_response( $response, $request_body, $endpoint, $default_error ) {
		// no response from Vipps
		if ( ! $response ) {
			$error_msg = __( 'No response from Vipps/MobilePay', 'woo-vipps' );
			WC_Vipps_Recurring_Logger::log( sprintf( 'HTTP Response Temporary Error: %s with request body: %s', $error_msg, $request_body ) );

			throw new WC_Vipps_Recurring_Temporary_Exception( $error_msg );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		$body = wp_remote_retrieve_body( $response );
		if ( $body ) {
			$body = json_decode( $body, true );
		}

		// As long as the status code is less than 300 and greater than 199 we can return the body
		if ( $status < 300 && $status > 199 ) {
			return $body;
		}

		// Rate limiting, temporary error
		if ( $status === 429 ) {
			$error_msg = __( "We hit Vipps/MobilePay's rate limit, we will retry later.", 'woo-vipps' );
			throw new WC_Vipps_Recurring_Temporary_Exception( $error_msg );
		}

		// error handling
		$error_msg                     = $default_error ?? '';
		$is_idempotent_error           = false;
		$is_merchant_not_allowed_error = false;
		$is_url_validation_error       = false;

		if ( $body ) {
			if ( isset( $body['message'] ) ) {
				$error_msg = $body['message'];
			}

			if ( isset( $body['error_description'] ) ) {
				$error_msg = $body['error_description'];
			}

			if ( isset( $body['title'] ) ) {
				$error_msg = $body['title'];

				if ( isset( $body['detail'] ) ) {
					$error_msg .= ' ' . $body['detail'];
				}
			}

			$error_msg = trim( $error_msg );

			/*
			"type":"https://example.com/validation-error",
			"title":"Your request parameters didn't validate.",
			"detail":"The request body contains one or more errors",
			"instance":"123e4567-e89b-12d3-a456-426655440000",
			"status":"400",
			"extraDetails":[
			  {
			     "name":"amount",
			     "reason":"Must be a positive integer larger than 100"
			  },
			  {
			     "name":"URL",
			     "reason":"Must use HTTPS and validate according to the API specification"
			  }
			]
			 */


			// todo: implement new logic
			/*
				{
					"type": "about:blank",
					"title": "Not Found",
					"status": 404,
					"detail": "Agreement not found.",
					"instance": "/vipps-recurring-merchant-api/v3/agreements/agr_GqnvsH0",
					"contextId": "32900600-1dd4-47dc-9a02-28c9e6491c65"
				}
			 */

//			if ( isset( $body['message'] ) ) {
//				$error_msg = $body['message'];
//			} elseif ( isset( $body['error'] ) ) {
//				// access token
//				$error_msg = $body['error'];
//			} elseif ( is_array( $body ) ) {
//				$error_msg = '';
//				foreach ( $body as $entry ) {
//					if ( isset( $entry['code'] ) ) {
//						$error_msg .= $entry['field'] . ': ' . $entry['message'] . "\n";
//
//						if ( $entry['code'] === 'idempotentkey.hash.mismatch' ) {
//							$is_idempotent_error = true;
//						}
//
//						if ( $entry['code'] === 'merchant.not.allowed.for.recurring.operation' ) {
//							$is_merchant_not_allowed_error = true;
//						}
//
//						if ( $entry['code'] === 'merchantagreementurl.apacheurlvalidation' ) {
//							$is_url_validation_error = true;
//						}
//					}
//				}
//			} else {
//				$error_msg = $body;
//			}
		}

		$localized_msg = $error_msg;
		if ( $is_merchant_not_allowed_error ) {
			/* translators: Link to a GitHub readme about the error */
			$localized_msg = sprintf( __( 'Recurring payments is not yet activated for this sale unit. Read more <a href="%s" target="_blank">here</a>', 'woo-vipps' ), 'https://developer.vippsmobilepay.com/docs/APIs/recurring-api/recurring-api-faq/#why-do-i-get-the-error-merchantnotallowedforrecurringoperation' );
		}

		if ( $is_url_validation_error ) {
			$localized_msg = __( 'Your WordPress URL is not passing Merchant Agreement URL validation. Is your website publicly accessible?', 'woo-vipps' );
		}

		if ( is_array( $request_body ) ) {
			$request_body = json_encode( $request_body );
		}

		if ( is_array( $body ) ) {
			$body = json_encode( $body );
		}

		WC_Vipps_Recurring_Logger::log( sprintf( 'HTTP Response Error (%s): %s (%s) with request body: %s. The response was: %s', $status, $error_msg, $endpoint, $request_body, $body ) );

		$exception                      = new WC_Vipps_Recurring_Exception( $error_msg, $localized_msg );
		$exception->response_code       = $status;
		$exception->is_idempotent_error = $is_idempotent_error;

		throw $exception;
	}
}
