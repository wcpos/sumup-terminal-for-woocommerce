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
