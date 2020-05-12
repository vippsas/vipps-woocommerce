<?php

defined( 'ABSPATH' ) || exit;

require_once( __DIR__ . '/class-wc-vipps-recurring-exceptions.php' );

/**
 * Class VippsRecurringApi
 */
class VippsRecurringApi {
	/**
	 * @var $gateway
	 */
	public $gateway;

	/**
	 * Amount of days to add to due_at for a charge in the recurring API.
	 * Currently this value has to be 6 days or more as per Vipps' specification.
	 * https://github.com/vippsas/vipps-recurring-api/blob/master/vipps-recurring-api.md#charge-states
	 *
	 * @var int $due_minimum_days
	 */
	public $due_minimum_days = WC_VIPPS_RECURRING_CHARGE_DUE_DAYS_PADDING;

	/**
	 * Amount of days to retry a payment for when creating a charge
	 *
	 * @var int $retry_days
	 */
	public $retry_days = WC_VIPPS_RECURRING_RETRY_DAYS;

	/**
	 * VippsRecurringApi constructor.
	 *
	 * @param $gateway
	 */
	public function __construct( $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Get an API Access Token or create a new one
	 *
	 * @param int $force
	 *
	 * @return mixed|null
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function get_access_token( $force = 0 ) {
		// First, get a stored token if it exists
		$stored = get_transient( '_vipps_app_token' );

		if ( ! $force && $stored && $stored['expires_on'] > time() ) {
			return $stored['access_token'];
		}

		// Otherwise, get it from vipps - this might throw errors
		$fresh = $this->get_access_token_from_vipps();

		if ( ! $fresh ) {
			return null;
		}

		$token  = $fresh['access_token'];
		$expire = $fresh['expires_in'] / 2;
		set_transient( '_vipps_app_token', $fresh, $expire );

		return $token;
	}

	/**
	 * Fetch Access Token from Vipps
	 *
	 * @return mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	private function get_access_token_from_vipps() {
		try {
			return $this->http_call( 'accessToken/get', 'POST' );
		} catch ( WC_Vipps_Recurring_Exception $e ) {
			WC_Vipps_Recurring_Logger::log( __( 'Could not get Vipps access token', 'woo-vipps-recurring' ) . ' ' . $e->getMessage() );

			throw $e;
		} catch ( Exception $e ) {
			WC_Vipps_Recurring_Logger::log( __( 'Could not get Vipps access token', 'woo-vipps-recurring' ) . ' ' . $e->getMessage() );

			throw new WC_Vipps_Recurring_Exception( $e->getMessage() );
		}
	}

	/**
	 * @return string
	 */
	public function generate_idempotency_key(): string {
		return wp_generate_password( 24, false, false );
	}

	/**
	 * @param $agreement_body
	 *
	 * @return mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function create_agreement( $agreement_body ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		return $this->http_call( 'recurring/v2/agreements', 'POST', $agreement_body, $headers );
	}

	/**
	 * @param $agreement_id
	 *
	 * @return mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function get_agreement( $agreement_id ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		return $this->http_call( 'recurring/v2/agreements/' . $agreement_id, 'GET', [], $headers );
	}

	/**
	 * @param $agreement
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function get_price_from_agreement( $agreement ) {
		$amount = $agreement['price'];

		if ( isset( $agreement['campaign'] ) ) {
			$now = new DateTime();
			$end = new DateTime( $agreement['campaign']['end'] );

			if ( $end > $now ) {
				$amount = $agreement['campaign']['campaignPrice'];
			}
		}

		return $amount;
	}

	/**
	 * @param $agreement
	 *
	 * @return mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function cancel_agreement( $agreement ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		$data = [
			'productName'        => $agreement['productName'],
			'price'              => $agreement['price'],
			'productDescription' => $agreement['productDescription'],
			'status'             => 'STOPPED',
		];

		return $this->http_call( 'recurring/v2/agreements/' . $agreement['id'], 'PUT', $data, $headers );
	}

	/**
	 * @param $agreement
	 * @param $order
	 * @param $idempotence_key
	 * @param null $amount
	 *
	 * @return mixed
	 * @throws WC_Vipps_Recurring_Exception
	 * @throws Exception
	 */
	public function create_charge( $agreement, $order, $idempotence_key, $amount = null ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'  => 'Bearer ' . $token,
			'Idempotent-Key' => $idempotence_key,
		];

		$has_price_changed = false;
		$agreement_price   = $this->get_price_from_agreement( $agreement );

		if ( $amount !== null ) {
			$has_price_changed = $amount !== $agreement_price;
		} else {
			$amount = $agreement_price;
		}

		$due_at = date( 'Y-m-d', time() + ( 24 * 3600 * $this->due_minimum_days ) );

		$data = [
			'amount'          => $amount,
			'currency'        => $order->get_currency(),
			'description'     => $agreement['productDescription'],
			'due'             => $due_at,
			'hasPriceChanged' => $has_price_changed,
			'retryDays'       => $this->retry_days,
		];

		return $this->http_call( 'recurring/v2/agreements/' . $agreement['id'] . '/charges', 'POST', $data, $headers );
	}

	/**
	 * @param $agreement_id
	 * @param $charge_id
	 *
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function cancel_charge( $agreement_id, $charge_id ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		$this->http_call( 'recurring/v2/agreements/' . $agreement_id . '/charges/' . $charge_id, 'DELETE', [], $headers );
	}

	/**
	 * @param $agreement_id
	 * @param $charge_id
	 *
	 * @return mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function get_charge( $agreement_id, $charge_id ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		return $this->http_call( 'recurring/v2/agreements/' . $agreement_id . '/charges/' . $charge_id, 'GET', [], $headers );
	}

	/**
	 * @param $agreement_id
	 * @param $charge_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function refund_charge( $agreement_id, $charge_id, $amount = null, $reason = null ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization'  => 'Bearer ' . $token,
			'Idempotent-Key' => $this->generate_idempotency_key(),
		];

		$data = [
			'description' => $reason ?? 'Refund',
		];

		if ( $amount !== null ) {
			$data = array_merge( $data, [
				'amount' => $amount,
			] );
		}

		return $this->http_call( 'recurring/v2/agreements/' . $agreement_id . '/charges/' . $charge_id . '/refund', 'POST', $data, $headers );
	}

	/**
	 * @param $agreement_id
	 *
	 * @return mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	public function get_charges_for( $agreement_id ) {
		$token = $this->get_access_token();

		$headers = [
			'Authorization' => 'Bearer ' . $token,
		];

		return $this->http_call( 'recurring/v2/agreements/' . $agreement_id . '/charges', 'GET', [], $headers );
	}

	/**
	 * @param $endpoint
	 * @param $method
	 * @param array $data
	 * @param array $headers
	 *
	 * @return mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	private function http_call( $endpoint, $method, $data = [], $headers = [] ) {
		$url = $this->gateway->api_url . '/' . $endpoint;

		$client_id        = $this->gateway->client_id;
		$secret_key       = $this->gateway->secret_key;
		$subscription_key = $this->gateway->subscription_key;

		$headers = array_merge( [
			'client_id'                 => $client_id,
			'client_secret'             => $secret_key,
			'Ocp-Apim-Subscription-Key' => $subscription_key,
			'Content-Type'              => 'application/json',
		], $headers );

		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => $headers,
			'body'    => $method === 'GET' ? $data : json_encode( $data ),
		];

		$response = wp_safe_remote_post( $url, $args );

		// Parse the result, converting it to exceptions if necessary
		return $this->handle_http_response( $response );
	}

	/**
	 * @param $response
	 *
	 * @return mixed
	 * @throws WC_Vipps_Recurring_Exception
	 */
	private function handle_http_response( $response ) {
		// no response from Vipps
		if ( ! $response ) {
			$msg = __( 'No response from Vipps', 'woo-vipps-recurring' );
			throw new WC_Vipps_Recurring_Exception( $msg );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( $response['body'], true );

		// As long as the status code is less than 300 and greater than 199 we can return the body
		if ( $status < 300 && $status > 199 ) {
			return $body;
		}

		// error handling
		$msg                           = '';
		$is_idempotent_error           = false;
		$is_merchant_not_allowed_error = false;

		if ( $body ) {
			if ( isset( $body['message'] ) ) {
				$msg = $body['message'];
			} elseif ( isset( $body['error'] ) ) {
				// access token
				$msg = $body['error'];
			} elseif ( is_array( $body ) ) {
				$msg = '';
				foreach ( $body as $entry ) {
					if ( isset( $entry['code'] ) ) {
						$msg .= $entry['field'] . ': ' . $entry['message'] . "\n";

						if ( $entry['code'] === 'idempotentkey.hash.mismatch' ) {
							$is_idempotent_error = true;
						}

						if ( $entry['code'] === 'merchant.not.allowed.for.recurring.operation' ) {
							$is_merchant_not_allowed_error = true;
						}
					}
				}
			} else {
				$msg = $body;
			}
		}

		$localized_msg = '';
		if ( $is_merchant_not_allowed_error ) {
			/* translators: Link to a GitHub readme about the error */
			$localized_msg = sprintf( __( 'Recurring payments is not yet activated for this sale unit. Read more <a href="%s" target="_blank">here</a>', 'woo-vipps-recurring' ), 'https://github.com/vippsas/vipps-recurring-api/blob/master/vipps-recurring-api-faq.md#why-do-i-get-the-error-merchantnotallowedforrecurringoperation' );
		}

		WC_Vipps_Recurring_Logger::log( 'Error: ' . $msg );

		$exception                      = new WC_Vipps_Recurring_Exception( $msg, $localized_msg );
		$exception->response_code       = $status;
		$exception->is_idempotent_error = $is_idempotent_error;

		throw $exception;
	}
}
