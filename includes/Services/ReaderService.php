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
		Logger::log( 'ReaderService Debug: get_all() called' );
		
		if ( ! $this->get_merchant_id() ) {
			Logger::log( 'ReaderService Debug: No merchant ID available' );

			return false;
		}

		Logger::log( 'ReaderService Debug: Fetching readers for merchant: ' . $this->get_merchant_id() );
		$result = parent::get( "/v0.1/merchants/{$this->get_merchant_id()}/readers" );
		
		if ( $result ) {
			Logger::log( 'ReaderService Debug: Raw API response: ' . wp_json_encode( $result ) );
			
			// Check if response has 'items' structure
			if ( isset( $result['items'] ) && \is_array( $result['items'] ) ) {
				Logger::log( 'ReaderService Debug: Found items array with ' . \count( $result['items'] ) . ' readers' );

				return $result['items'];
			}
			Logger::log( 'ReaderService Debug: No items array found, returning raw response' );
			Logger::log( 'ReaderService Debug: get_all() returned: DATA (' . \count( $result ) . ' items)' );

			return $result;
		}
		Logger::log( 'ReaderService Debug: get_all() returned: FALSE' );

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

		return parent::get( "/v0.1/merchants/{$this->get_merchant_id()}/readers/{$reader_id}" );
	}

	/**
	 * Create/register a new reader.
	 *
	 * @param array $data Reader data.
	 *
	 * @return array|false Reader data or false on failure.
	 */
	public function create( array $data ) {
		Logger::log( 'ReaderService Debug: create() called with data: ' . wp_json_encode( $data ) );
		
		if ( ! $this->get_merchant_id() ) {
			Logger::log( 'ReaderService Debug: create() failed - no merchant ID' );

			return false;
		}

		Logger::log( 'ReaderService Debug: Pairing reader for merchant: ' . $this->get_merchant_id() );
		$result = parent::post( "/v0.1/merchants/{$this->get_merchant_id()}/readers", $data );
		Logger::log( 'ReaderService Debug: create() returned: ' . ( $result ? 'SUCCESS' : 'FAILED' ) );
		
		return $result;
	}

	/**
	 * Delete/unregister a reader.
	 *
	 * @param string $reader_id Reader ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function destroy( $reader_id ) {
		Logger::log( 'ReaderService Debug: destroy() called for reader: ' . $reader_id );
		
		if ( ! $this->get_merchant_id() ) {
			Logger::log( 'ReaderService Debug: destroy() failed - no merchant ID' );

			return false;
		}

		Logger::log( 'ReaderService Debug: Unpairing reader for merchant: ' . $this->get_merchant_id() . ', reader: ' . $reader_id );
		$response = parent::delete( "/v0.1/merchants/{$this->get_merchant_id()}/readers/{$reader_id}" );

		$success = $response && ( $response['success'] ?? true );
		Logger::log( 'ReaderService Debug: destroy() returned: ' . ( $success ? 'SUCCESS' : 'FAILED' ) );
		
		return $success;
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
		Logger::log( 'ReaderService Debug: checkout() called for reader: ' . $reader_id . ' with data: ' . wp_json_encode( $checkout_data ) );
		
		if ( ! $this->get_merchant_id() ) {
			Logger::log( 'ReaderService Debug: checkout() failed - no merchant ID' );

			return false;
		}

		Logger::log( 'ReaderService Debug: Creating checkout for merchant: ' . $this->get_merchant_id() . ', reader: ' . $reader_id );
		$result = parent::post(
			"/v0.1/merchants/{$this->get_merchant_id()}/readers/{$reader_id}/checkout",
			$checkout_data
		);
		Logger::log( 'ReaderService Debug: checkout() returned: ' . ( $result ? 'SUCCESS' : 'FAILED' ) );
		
		return $result;
	}

	/**
	 * Create a terminal checkout for a WooCommerce order.
	 *
	 * @param WC_Order $order       The WooCommerce order.
	 * @param string   $webhook_url Optional webhook URL for transaction notifications.
	 *
	 * @return array|false Checkout data or false on failure.
	 */
	public function create_checkout_for_order( $order, $webhook_url = '' ) {
		if ( ! $this->has_api_key() || ! $this->get_merchant_id() ) {
			return false;
		}

		// Get the first available reader
		$readers = $this->get_all();
		if ( ! $readers || empty( $readers ) ) {
			return false; // No readers available
		}

		$reader_id = $readers[0]['id']; // Use the first reader

		// Prepare checkout data
		$checkout_data = array(
			'amount'   => \floatval( $order->get_total() ),
			'currency' => $order->get_currency(),
		);

		// Add webhook URL if provided
		if ( ! empty( $webhook_url ) ) {
			$checkout_data['webhook_url'] = $webhook_url;
		}

		return $this->checkout( $reader_id, $checkout_data );
	}

	/**
	 * Cancel/terminate a checkout on a reader.
	 *
	 * @param string $reader_id Reader ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function cancel_checkout( $reader_id ) {
		Logger::log( 'ReaderService Debug: cancel_checkout() called for reader: ' . $reader_id );
		
		if ( ! $this->get_merchant_id() ) {
			Logger::log( 'ReaderService Debug: cancel_checkout() failed - no merchant ID' );

			return false;
		}

		Logger::log( 'ReaderService Debug: Terminating checkout for merchant: ' . $this->get_merchant_id() . ', reader: ' . $reader_id );
		// According to SumUp API docs, terminate action is sent to the reader
		$response = parent::post( "/v0.1/merchants/{$this->get_merchant_id()}/readers/{$reader_id}/terminate", array(
			'action' => 'terminate',
		) );

		$success = false !== $response;
		Logger::log( 'ReaderService Debug: cancel_checkout() returned: ' . ( $success ? 'SUCCESS' : 'FAILED' ) );
		
		return $success;
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

		return parent::get( "/v0.1/merchants/{$this->get_merchant_id()}/readers/{$reader_id}/status" );
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

		return parent::post( "/v0.1/merchants/{$this->get_merchant_id()}/readers/{$reader_id}/connect" );
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

		$response = parent::post( "/v0.1/merchants/{$this->get_merchant_id()}/readers/{$reader_id}/disconnect" );

		return $response && ( $response['success'] ?? true );
	}
}
