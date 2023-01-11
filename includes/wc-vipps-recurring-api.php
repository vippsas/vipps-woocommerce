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
			WC_Vipps_Recurring_Logger::log( 'Could not get Vipps access token ' . $e->getMessage() );

			throw $e;
		} catch ( Exception $e ) {
			WC_Vipps_Recurring_Logger::log( 'Could not get Vipps access token ' . $e->getMessage() );

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

		return $this->http_call( 'recurring/v3/agreements', 'POST', $agreement->to_array(true), $headers );
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
	public function update_agreement( string $agreement_id, WC_Vipps_Agreement $agreement ): void {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		$this->http_call( 'recurring/v3/agreements/' . $agreement_id, 'PATCH', $agreement->to_array(), $headers );
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
	public function cancel_agreement( string $agreement_id ): void {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		$data = [
			'status' => WC_Vipps_Agreement::STATUS_STOPPED,
		];

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

		$data = [
			'amount'      => $amount,
			'description' => $agreement->product_description,
			'due'         => $due_at,
			'retryDays'   => $this->retry_days,
		];

		return $this->http_call( 'recurring/v3/agreements/' . $agreement->id . '/charges', 'POST', $data, $headers );
	}

	/**
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	public function cancel_charge( string $agreement_id, string $charge_id ): void {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
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
	 * @return mixed|string|null
	 * @throws WC_Vipps_Recurring_Config_Exception
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws WC_Vipps_Recurring_Temporary_Exception
	 */
	private function http_call( string $endpoint, string $method, array $data = [], array $headers = [] ) {
		$url = $this->gateway->api_url . '/' . $endpoint;

		$client_id        = $this->gateway->client_id;
		$secret_key       = $this->gateway->secret_key;
		$subscription_key = $this->gateway->subscription_key;

		if ( ! $subscription_key || ! $secret_key || ! $client_id ) {
			throw new WC_Vipps_Recurring_Config_Exception( __( 'Your Vipps Recurring Payments gateway is not correctly configured.', 'woo-vipps-recurring' ) );
		}

		$headers = array_merge( [
			'client_id'                   => $client_id,
			'client_secret'               => $secret_key,
			'Ocp-Apim-Subscription-Key'   => $subscription_key,
			'Content-Type'                => 'application/json',
			'Vipps-System-Name'           => 'woocommerce',
			'Vipps-System-Version'        => get_bloginfo( 'version' ) . '/' . ( defined( 'WC_VERSION' ) ? WC_VERSION : '0.0.0' ),
			'Vipps-System-Plugin-Name'    => 'woo-vipps-recurring',
			'Vipps-System-Plugin-Version' => WC_VIPPS_RECURRING_VERSION
		], $headers );

		$body = $method === 'GET' ? $data : json_encode( $data );

		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => $headers,
			'body'    => $body,
		];

		$response = wp_safe_remote_post( $url, $args );

		// throw WP error as a WC_Vipps_Recurring_Exception if response is not valid
		$default_error = '';

		if ( is_wp_error( $response ) ) {
			$default_error = "500 " . $response->get_error_message();
		}

		// Parse the result, converting it to exceptions if necessary
		return $this->handle_http_response( $response, $body, $endpoint, $default_error );
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
			$error_msg = __( 'No response from Vipps', 'woo-vipps-recurring' );
			WC_Vipps_Recurring_Logger::log( sprintf( 'HTTP Response Temporary Error: %s with request body: %s', $error_msg, $request_body ) );

			throw new WC_Vipps_Recurring_Temporary_Exception( $error_msg );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		$body = wp_remote_retrieve_body( $response );
		if ( $body ) {
			$body = json_decode( $body, true );
		}

		// As long as the status code is less than 300 and greater than 199 we can return the body
		if ( $status < 300 ) {
			return $body;
		}

		// Rate limiting, temporary error
		if ( $status === 429 ) {
			$error_msg = __( "We hit Vipps' rate limit, we will retry later.", 'woo-vipps-recurring' );
			throw new WC_Vipps_Recurring_Temporary_Exception( $error_msg );
		}

		// error handling
		$error_msg                     = $default_error;
		$is_idempotent_error           = false;
		$is_merchant_not_allowed_error = false;
		$is_url_validation_error       = false;

		if ( $body ) {
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

		$localized_msg = '';
		if ( $is_merchant_not_allowed_error ) {
			/* translators: Link to a GitHub readme about the error */
			$localized_msg = sprintf( __( 'Recurring payments is not yet activated for this sale unit. Read more <a href="%s" target="_blank">here</a>', 'woo-vipps-recurring' ), 'https://github.com/vippsas/vipps-recurring-api/blob/master/vipps-recurring-api-faq.md#why-do-i-get-the-error-merchantnotallowedforrecurringoperation' );
		}

		if ( $is_url_validation_error ) {
			$localized_msg = __( 'Your WordPress URL is not passing Merchant Agreement URL validation. Is your website publicly accessible?', 'woo-vipps-recurring' );
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
