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
class ReaderService extends HttpClient {
	/**
	 * Get all readers for the merchant.
	 *
	 * @return array|false Readers data or false on failure.
	 */
	public function get_all() {
		if ( ! $this->get_merchant_id() ) {
			return false;
		}

		$result = parent::get( "/merchants/{$this->get_merchant_id()}/readers" );
		
		if ( $result ) {
			// Check if response has 'items' structure
			if ( isset( $result['items'] ) && \is_array( $result['items'] ) ) {
				return $result['items'];
			}

			return $result;
		}

		return false;
	}

	/**
	 * Get a specific reader by ID.
	 *
	 * @param string $reader_id Reader ID.
	 *
	 * @return array|false Reader data or false on failure.
	 */
	public function get_reader( $reader_id ) {
		if ( ! $this->get_merchant_id() ) {
			return false;
		}

		return parent::get( "/merchants/{$this->get_merchant_id()}/readers/{$reader_id}" );
	}

	/**
	 * Create/register a new reader.
	 *
	 * @param array $data Reader data.
	 *
	 * @return array|false Reader data or false on failure.
	 */
	public function create( array $data ) {
		if ( ! $this->get_merchant_id() ) {
			return false;
		}

		return parent::post( "/merchants/{$this->get_merchant_id()}/readers", $data );
	}

	/**
	 * Delete/unregister a reader.
	 *
	 * @param string $reader_id Reader ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function destroy( $reader_id ) {
		if ( ! $this->get_merchant_id() ) {
			return false;
		}

		$response = parent::delete( "/merchants/{$this->get_merchant_id()}/readers/{$reader_id}" );

		return $response && ( $response['success'] ?? true );
	}

	/**
	 * Initiate a checkout on a specific reader.
	 *
	 * @param string $reader_id     Reader ID.
	 * @param array  $checkout_data Checkout data.
	 *
	 * @return array|false Checkout response or false on failure.
	 */
	public function checkout( $reader_id, $checkout_data ) {
		if ( ! $this->get_merchant_id() ) {
			return false;
		}

		return parent::post(
			"/merchants/{$this->get_merchant_id()}/readers/{$reader_id}/checkout",
			$checkout_data
		);
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
		if ( ! $this->has_api_key() || ! $this->get_merchant_id() ) {
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
		
		$checkout_data['return_url'] = add_query_arg( array(
			'action'   => 'sumup_webhook',
			'nonce'    => $webhook_token,
			'order_id' => $order->get_id(),
		), admin_url( 'admin-ajax.php' ) );

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
	 * Cancel/terminate a checkout on a reader.
	 *
	 * Note: This is an asynchronous operation. The API only confirms the terminate
	 * request was accepted, but actual termination may take time. If successful,
	 * a webhook will be sent to the return_url with status "FAILED".
	 *
	 * @param string $reader_id Reader ID.
	 *
	 * @return bool True if terminate request was accepted, false if rejected.
	 */
	public function cancel_checkout( $reader_id ) {
		if ( ! $this->get_merchant_id() ) {
			return false;
		}

		// According to SumUp API docs, terminate action is sent to the reader
		// This is asynchronous - no confirmation of actual termination
		// No request body is required for terminate
		$response = parent::post( "/merchants/{$this->get_merchant_id()}/readers/{$reader_id}/terminate" );

		// SumUp API returns HTTP 204 (no content) for terminate requests
		// We consider it successful if the request was sent (no network error)
		// The actual termination result will come via webhook to return_url
		return ! is_wp_error( $response );
	}

	/**
	 * Get the status of a reader.
	 *
	 * @param string $reader_id Reader ID.
	 *
	 * @return array|false Reader status or false on failure.
	 */
	public function get_status( $reader_id ) {
		if ( ! $this->get_merchant_id() ) {
			return false;
		}

		return parent::get( "/merchants/{$this->get_merchant_id()}/readers/{$reader_id}/status" );
	}

	/**
	 * Connect to a reader.
	 *
	 * @param string $reader_id Reader ID.
	 *
	 * @return array|false Connection response or false on failure.
	 */
	public function connect( $reader_id ) {
		if ( ! $this->get_merchant_id() ) {
			return false;
		}

		return parent::post( "/merchants/{$this->get_merchant_id()}/readers/{$reader_id}/connect" );
	}

	/**
	 * Disconnect from a reader.
	 *
	 * @param string $reader_id Reader ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function disconnect( $reader_id ) {
		if ( ! $this->get_merchant_id() ) {
			return false;
		}

		$response = parent::post( "/merchants/{$this->get_merchant_id()}/readers/{$reader_id}/disconnect" );

		return $response && ( $response['success'] ?? true );
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
