<?php
/**
 * SumUp Reader Service
 * Handles reader-related SumUp API operations.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

use WCPOS\WooCommercePOS\SumUpTerminal\Logger;

/**
 * Class ReaderService.
 */
class ReaderService {
	/**
	 * @var ReaderApiClientInterface Reader API client.
	 */
	private $client;

	/**
	 * Constructor for the reader service.
	 *
	 * @param string                        $api_key SumUp API key.
	 * @param null|ReaderApiClientInterface $client  Reader API client.
	 */
	public function __construct( $api_key = '', ?ReaderApiClientInterface $client = null ) {
		$this->client = $client ? $client : ReaderApiClientFactory::create( $api_key );
	}

	/**
	 * Set the profile service for lazy loading merchant ID.
	 *
	 * @param ProfileService $profile_service Profile service instance.
	 */
	public function set_profile_service( ProfileService $profile_service ): void {
		$this->client->set_profile_service( $profile_service );
	}

	public function get_all() {
		return $this->client->get_all();
	}

	public function get_reader( $reader_id ) {
		return $this->client->get_reader( $reader_id );
	}

	public function create( array $data ) {
		return $this->client->create( $data );
	}

	public function destroy( $reader_id ) {
		return $this->client->destroy( $reader_id );
	}

	public function checkout( $reader_id, $checkout_data ) {
		return $this->client->checkout( $reader_id, $checkout_data );
	}

	public function cancel_checkout( $reader_id ) {
		return $this->client->cancel_checkout( $reader_id );
	}

	public function get_status( $reader_id ) {
		return $this->client->get_status( $reader_id );
	}

	public function connect( $reader_id ) {
		return $this->client->connect( $reader_id );
	}

	public function disconnect( $reader_id ) {
		return $this->client->disconnect( $reader_id );
	}

	public function set_merchant_id( $merchant_id ): void {
		$this->client->set_merchant_id( $merchant_id );
	}

	public function get_merchant_id() {
		return $this->client->get_merchant_id();
	}

	public function has_api_key() {
		return $this->client->has_api_key();
	}

	/**
	 * Create a terminal checkout for a WooCommerce order.
	 *
	 * @param WC_Order $order     The WooCommerce order.
	 * @param string   $reader_id The specific reader ID to use for the checkout.
	 *
	 * @return array|false Checkout data or false on failure.
	 */
	public function create_checkout_for_order( $order, $reader_id ) {
		if ( ! $this->client->has_api_key() ) {
			return false;
		}

		// Validate that the reader ID is provided
		if ( empty( $reader_id ) ) {
			Logger::log( 'ReaderService: create_checkout_for_order() called without reader_id' );

			return false;
		}

		// Prepare checkout data according to SumUp API v0.1 specification
		$checkout_data = array(
			'total_amount' => array(
				'value'      => (int) round( $order->get_total() * 100 ), // Convert to minor units (cents)
				'currency'   => $order->get_currency(),
				'minor_unit' => 2, // Most currencies use 2 decimal places
			),
		);

		// Add optional description
		$checkout_data['description'] = \sprintf(
			__( 'Order #%s', 'sumup-terminal-for-woocommerce' ),
			$order->get_order_number()
		);

		// Construct webhook URL using WordPress AJAX with user-independent token and order ID
		$webhook_token = $this->generate_webhook_token( $order->get_id() );

		$checkout_data['return_url'] = add_query_arg(
			array(
				'action'   => 'sumup_webhook',
				'nonce'    => $webhook_token,
				'order_id' => $order->get_id(),
			),
			admin_url( 'admin-ajax.php' )
		);

		$result = $this->checkout( $reader_id, $checkout_data );

		// If successful, save the transaction ID to the order
		if ( $result && isset( $result['data']['client_transaction_id'] ) ) {
			$transaction_id = $result['data']['client_transaction_id'];
			$order->set_transaction_id( $transaction_id );
			$order->save();

			Logger::log( 'SumUp transaction ID saved: ' . $transaction_id . ' for order: ' . $order->get_id() );
		}

		return $result;
	}

	/**
	 * Generate a webhook token for a specific order.
	 * This is user-independent and suitable for external webhook validation.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return string The webhook token.
	 */
	private function generate_webhook_token( $order_id ) {
		// Create a deterministic token based on order ID and WordPress salts
		// This is user-independent but specific to this WordPress installation
		$data = 'sumup_webhook_' . $order_id . wp_salt( 'nonce' );

		return substr( wp_hash( $data ), 0, 10 );
	}
}
