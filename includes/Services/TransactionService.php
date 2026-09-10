<?php
/**
 * SumUp Transaction Service
 * Retrieves authoritative transaction results from the SumUp API.
 *
 * @package SumUpTerminalForWooCommerce
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

/**
 * Retrieves SumUp transaction results by client transaction ID.
 */
class TransactionService extends HttpClient {
	/**
	 * Profile service used for merchant lookup.
	 *
	 * @var null|ProfileService Profile service instance for merchant lookup.
	 */
	private $profile_service;

	/**
	 * Constructor for the transaction service.
	 *
	 * @param string $api_key  SumUp API key.
	 * @param string $base_url SumUp API base URL.
	 */
	public function __construct( $api_key = '', $base_url = '' ) {
		parent::__construct( $api_key, $base_url, 'v2.1' );
	}

	/**
	 * Set the profile service used to resolve the merchant code.
	 *
	 * @param ProfileService $profile_service Profile service instance.
	 */
	public function set_profile_service( ProfileService $profile_service ): void {
		$this->profile_service = $profile_service;
	}

	/**
	 * Retrieve a transaction by the client ID returned by reader checkout.
	 *
	 * @param string $client_transaction_id Client transaction ID.
	 *
	 * @return array|false Transaction data or false when unavailable.
	 */
	/**
	 * Look a transaction up for the server checkout, distinguishing "nothing yet" from failure.
	 *
	 * SumUp answers HTTP 404 while no transaction exists for a checkout (nobody has acted on
	 * the device yet); the generic client folds that into `false`, which reads like an outage.
	 *
	 * @param string $client_transaction_id Client transaction ID.
	 * @return array|null|\WP_Error Transaction, null when SumUp has none yet, or an error.
	 */
	public function find_by_client_transaction_id( string $client_transaction_id ) {
		if ( '' === $client_transaction_id || ! $this->has_api_key() || ! $this->profile_service ) {
			return new \WP_Error( 'sumup_api_error', 'SumUp transaction lookup is not configured.' );
		}
		$merchant_code = $this->profile_service->get_merchant_code();
		if ( ! $merchant_code ) {
			return new \WP_Error( 'sumup_api_error', 'SumUp merchant code is unavailable.' );
		}
		$response = wp_remote_request(
			$this->base_url . '/v2.1/merchants/' . rawurlencode( $merchant_code ) . '/transactions?client_transaction_id=' . rawurlencode( $client_transaction_id ),
			array(
				'method' => 'GET',
				'headers' => $this->get_headers(),
				'timeout' => 30,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $code ) {
			return null;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			return new \WP_Error( 'sumup_api_error', sprintf( 'SumUp transaction lookup failed (HTTP %d).', $code ), array( 'status' => $code ) );
		}
		return $body;
	}

	/**
	 * Legacy lookup: a transaction array, or false on any failure (including SumUp's 404 while waiting).
	 *
	 * @param string $client_transaction_id Client transaction ID.
	 * @return array|false
	 */
	public function get_by_client_transaction_id( $client_transaction_id ) {
		if ( empty( $client_transaction_id ) || ! $this->has_api_key() || ! $this->profile_service ) {
			return false;
		}

		$merchant_code = $this->profile_service->get_merchant_code();
		if ( ! $merchant_code ) {
			return false;
		}

		return parent::get(
			"/merchants/{$merchant_code}/transactions",
			array( 'client_transaction_id' => $client_transaction_id )
		);
	}
}
