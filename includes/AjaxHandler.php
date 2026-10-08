<?php
/**
 * AJAX Handler for SumUp Terminal
 * Handles AJAX requests for admin functionality.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal;

use Exception;

use WCPOS\WooCommercePOS\SumUpTerminal\Services\ProfileService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\TransactionService;

/**
 * Class AjaxHandler.
 */
class AjaxHandler {
	/**
	 * Initialize AJAX handlers.
	 */
	public function __construct() {
		// Admin-only AJAX handlers
		if ( wp_doing_ajax() ) {
			add_action( 'wp_ajax_sumup_pair_reader', array( $this, 'ajax_pair_reader' ) );
			add_action( 'wp_ajax_sumup_unpair_reader', array( $this, 'ajax_unpair_reader' ) );
			
			// Webhook handler (accessible to external servers)
			add_action( 'wp_ajax_sumup_webhook', array( $this, 'ajax_webhook' ) );
			add_action( 'wp_ajax_nopriv_sumup_webhook', array( $this, 'ajax_webhook' ) );
		}
	}

	/**
	 * AJAX handler for pairing a reader.
	 */
	public function ajax_pair_reader(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'], 'sumup_admin_actions' ) ) {
			wp_send_json_error( __( 'Security check failed', 'sumup-terminal-for-woocommerce' ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'sumup-terminal-for-woocommerce' ) );
		}

		$pairing_code = sanitize_text_field( wp_unslash( $_POST['pairing_code'] ?? '' ) );
		$reader_name  = sanitize_text_field( wp_unslash( $_POST['reader_name'] ?? '' ) );

		if ( empty( $pairing_code ) ) {
			wp_send_json_error( __( 'Pairing code is required', 'sumup-terminal-for-woocommerce' ) );
		}

		if ( empty( $reader_name ) ) {
			/* translators: %s: reader pairing code shown on the SumUp terminal. */
			$reader_name = sprintf( __( 'WCPOS Reader %s', 'sumup-terminal-for-woocommerce' ), $pairing_code );
		}

		try {
			$services = $this->get_services();
			$result   = $services['reader']->create( array(
				'pairing_code' => $pairing_code,
				'name'         => $reader_name,
			) );

			if ( $result ) {
				wp_send_json_success( __( 'Reader paired successfully', 'sumup-terminal-for-woocommerce' ) );
			} else {
				wp_send_json_error( __( 'Failed to pair reader. Please check the pairing code and try again.', 'sumup-terminal-for-woocommerce' ) );
			}
		} catch ( Exception $e ) {
			Logger::log( 'Reader pairing failed: ' . $e->getMessage() );
			wp_send_json_error( __( 'Failed to pair reader. Please check the pairing code and try again.', 'sumup-terminal-for-woocommerce' ) );
		}
	}

	/**
	 * AJAX handler for unpairing a reader.
	 */
	public function ajax_unpair_reader(): void {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'], 'sumup_admin_actions' ) ) {
			wp_send_json_error( __( 'Security check failed', 'sumup-terminal-for-woocommerce' ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'sumup-terminal-for-woocommerce' ) );
		}

		$reader_id = sanitize_text_field( $_POST['reader_id'] ?? '' );

		if ( empty( $reader_id ) ) {
			wp_send_json_error( __( 'Reader ID is required', 'sumup-terminal-for-woocommerce' ) );
		}

		try {
			$services = $this->get_services();
			$result   = $services['reader']->destroy( $reader_id );

			if ( $result ) {
				wp_send_json_success( __( 'Reader unpaired successfully', 'sumup-terminal-for-woocommerce' ) );
			} else {
				wp_send_json_error( __( 'Failed to unpair reader', 'sumup-terminal-for-woocommerce' ) );
			}
		} catch ( Exception $e ) {
			Logger::log( 'Reader unpairing failed: ' . $e->getMessage() );
			wp_send_json_error( __( 'Failed to unpair reader', 'sumup-terminal-for-woocommerce' ) );
		}
	}

	/**
	 * AJAX handler for SumUp webhook notifications.
	 * Processes webhook events from SumUp servers.
	 */
	public function ajax_webhook(): void {
		// Get and validate required parameters
		$nonce    = sanitize_text_field( $_GET['nonce'] ?? '' );
		$order_id = absint( $_GET['order_id'] ?? 0 );

		// Validate order ID
		if ( empty( $order_id ) ) {
			Logger::log( 'SumUp Webhook: Missing order_id parameter' );
			http_response_code( 400 );
			exit;
		}

		// Get the order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			Logger::log( 'SumUp Webhook: Invalid order ID: ' . $order_id );
			http_response_code( 404 );
			exit;
		}

		// Validate webhook token for this specific order
		// Note: We can't use wp_verify_nonce() because webhooks come from external servers
		// with no WordPress user session. Instead, we validate the order-specific token.
		$expected_token = $this->generate_webhook_token( $order_id );
		$token_valid    = hash_equals( $expected_token, $nonce );
		
		if ( ! $token_valid ) {
			Logger::log( 'SumUp Webhook: Security check failed for order: ' . $order_id );
			http_response_code( 403 );
			exit;
		}

		// Get the webhook payload from request body
		$raw_payload = file_get_contents( 'php://input' );
		if ( empty( $raw_payload ) ) {
			Logger::log( 'SumUp Webhook: Empty payload received for order: ' . $order_id );
			http_response_code( 400 );
			exit;
		}

		// Parse JSON payload
		$webhook_data = json_decode( $raw_payload, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			Logger::log( 'SumUp Webhook: Invalid JSON payload for order: ' . $order_id );
			http_response_code( 400 );
			exit;
		}

		// Validate webhook structure
		if ( ! isset( $webhook_data['event_type'] ) || ! isset( $webhook_data['payload'] ) ) {
			Logger::log( 'SumUp Webhook: Invalid webhook structure for order: ' . $order_id );
			http_response_code( 400 );
			exit;
		}

		try {
			$this->process_webhook( $order, $webhook_data );
			
			// Return empty 200 response as required by SumUp webhook specification
			http_response_code( 200 );
			exit;
		} catch ( Exception $e ) {
			Logger::log( 'SumUp Webhook processing failed: ' . $e->getMessage() );
			http_response_code( 500 );
			exit;
		}
	}

	/**
	 * Process the SumUp webhook data for an order.
	 *
	 * @param WC_Order $order        The WooCommerce order.
	 * @param array    $webhook_data The webhook payload.
	 */
	private function process_webhook( $order, $webhook_data ): void {
		if ( Legacy_Adoption::is_adopted_order( $order ) ) {
			// Pro adopted this attempt on upgrade; its outcome is Pro's to record.
			Logger::log( 'SumUp Webhook: attempt adopted by WooCommerce POS Pro for order ' . $order->get_id() . '; ignoring.' );
			return;
		}
		$event_type = $webhook_data['event_type'];
		$payload    = $webhook_data['payload'];
		$timestamp  = $webhook_data['timestamp'] ?? gmdate( 'c' );
		$event_time = strtotime( $timestamp );
		$attempt_started = (int) $order->get_meta( '_sumup_attempt_started' );
		if ( $attempt_started && $event_time && $event_time < $attempt_started ) {
			Logger::log( 'SumUp Webhook: Ignoring an event from a previous payment attempt.' );

			return;
		}

		// Handle different event types
		switch ( $event_type ) {
			case 'checkout.status.updated':
				$this->handle_checkout_status_updated( $order, $payload, $timestamp );

				break;

			case 'solo.transaction.updated':
				$this->handle_solo_transaction_updated( $order, $payload, $timestamp );

				break;

			default:
				Logger::log( 'SumUp Webhook: Unknown event type: ' . $event_type );

				break;
		}

		// The old panel's JavaScript completed the order through the form submit once the
		// status was final; that path is gone, so an attempt Pro did not adopt is completed
		// here, on SumUp's authenticated word, never on the unsigned delivery alone.
		$this->complete_unadopted_attempt( $order );

		// Store the complete webhook data for debugging
		$order->update_meta_data( '_sumup_last_webhook', array(
			'event_type' => $event_type,
			'payload'    => $payload,
			'timestamp'  => $timestamp,
			'processed'  => gmdate( 'c' ),
		) );
		$order->save();
	}

	/**
	 * Handle checkout.status.updated webhook event.
	 *
	 * @param WC_Order $order     The WooCommerce order.
	 * @param array    $payload   The webhook payload.
	 * @param string   $timestamp The webhook timestamp.
	 */
	private function handle_checkout_status_updated( $order, $payload, $timestamp ): void {
		$status      = $payload['status']      ?? '';
		$checkout_id = $payload['checkout_id'] ?? '';
		$reference   = $payload['reference']   ?? $payload['client_transaction_id'] ?? '';
		if ( ! $this->webhook_matches_current_attempt( $order, $payload ) ) {
			return;
		}

		if ( empty( $status ) ) {
			Logger::log( 'SumUp Webhook: Missing status in checkout.status.updated payload' );

			return;
		}

		// Store SumUp status in order meta
		$order->update_meta_data( '_sumup_checkout_status', $status );
		$order->update_meta_data( '_sumup_checkout_updated', $timestamp );
		if ( in_array( strtoupper( $status ), array( 'PAID', 'FAILED', 'CANCELLED', 'TIMEOUT', 'EXPIRED' ), true ) ) {
			$order->delete_meta_data( '_sumup_reader_id' );
		}

		// Add order note with status change
		$note_parts   = array();
		$note_parts[] = \sprintf( __( 'SumUp checkout status updated to: %s', 'sumup-terminal-for-woocommerce' ), $status );

		// if ( ! empty( $checkout_id ) ) {
		// 	$note_parts[] = \sprintf( __( 'Checkout ID: %s', 'sumup-terminal-for-woocommerce' ), $checkout_id );
		// }

		// if ( ! empty( $reference ) ) {
		// 	$note_parts[] = \sprintf( __( 'Reference: %s', 'sumup-terminal-for-woocommerce' ), $reference );
		// }

		// if ( isset( $payload['failure_reason'] ) ) {
		// 	$note_parts[] = \sprintf( __( 'Reason: %s', 'sumup-terminal-for-woocommerce' ), $payload['failure_reason'] );
		// }

		$order_note = implode( "\n", $note_parts );
		$order->add_order_note( $order_note );
	}

	/**
	 * Handle solo.transaction.updated webhook event.
	 *
	 * @param WC_Order $order     The WooCommerce order.
	 * @param array    $payload   The webhook payload.
	 * @param string   $timestamp The webhook timestamp.
	 */
	private function handle_solo_transaction_updated( $order, $payload, $timestamp ): void {
		$status         = $payload['status']         ?? '';
		$transaction_id = $payload['transaction_id'] ?? '';
		if ( ! $this->webhook_matches_current_attempt( $order, $payload ) ) {
			return;
		}

		if ( empty( $status ) ) {
			Logger::log( 'SumUp Webhook: Missing status in solo.transaction.updated payload' );

			return;
		}

		// Store SumUp status in order meta
		$order->update_meta_data( '_sumup_transaction_status', $status );
		$order->update_meta_data( '_sumup_transaction_updated', $timestamp );
		if ( in_array( strtoupper( $status ), array( 'SUCCESSFUL', 'FAILED', 'CANCELLED' ), true ) ) {
			$order->delete_meta_data( '_sumup_reader_id' );
		}

		// Add order note with transaction status
		$note_parts   = array();
		$note_parts[] = \sprintf( __( 'SumUp transaction status updated to: %s', 'sumup-terminal-for-woocommerce' ), $status );

		if ( ! empty( $transaction_id ) ) {
			$note_parts[] = \sprintf( __( 'Transaction ID: %s', 'sumup-terminal-for-woocommerce' ), $transaction_id );
		}

		$order_note = implode( "\n", $note_parts );
		$order->add_order_note( $order_note );
	}

	/**
	 * Check that a webhook belongs to the active reader transaction.
	 *
	 * @param WC_Order $order   The WooCommerce order.
	 * @param array    $payload Webhook event payload.
	 *
	 * @return bool
	 */
	/**
	 * Complete an order whose old-panel attempt SumUp reports as successful.
	 *
	 * Only an attempt Pro did not adopt reaches here (process_webhook() returns earlier for
	 * an adopted one). The delivery's status is a hint; the transaction is looked up with
	 * the API key, matched by client transaction id and must be SUCCESSFUL.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function complete_unadopted_attempt( $order ): void {
		if ( $order->is_paid() || ! $order->needs_payment() ) {
			return;
		}
		$checkout    = strtoupper( (string) $order->get_meta( '_sumup_checkout_status' ) );
		$transaction = strtoupper( (string) $order->get_meta( '_sumup_transaction_status' ) );
		$client_id   = (string) $order->get_transaction_id();
		if ( '' === $client_id || ( 'PAID' !== $checkout && 'SUCCESSFUL' !== $transaction ) ) {
			return;
		}
		try {
			$lookup      = $this->lookup_transaction( $client_id );
			$response_id = is_array( $lookup ) ? (string) ( $lookup['client_transaction_id'] ?? '' ) : '';
			$status      = is_array( $lookup ) ? strtoupper( (string) ( $lookup['status'] ?? '' ) ) : '';
			if ( '' === $response_id || ! hash_equals( $client_id, $response_id ) || 'SUCCESSFUL' !== $status ) {
				Logger::log( 'SumUp Webhook: success reported for order ' . $order->get_id() . ' but the transaction lookup did not confirm it; the order stays unpaid.' );
				return;
			}
		} catch ( Exception $e ) {
			Logger::log( 'SumUp Webhook: transaction lookup failed for order ' . $order->get_id() . ': ' . $e->getMessage() );
			return;
		}
		$order->payment_complete( $client_id );
		$order->add_order_note( __( 'Payment completed from the SumUp result for an attempt started on the previous order-pay panel.', 'sumup-terminal-for-woocommerce' ) );
	}

	/**
	 * SumUp's authenticated record of a client transaction.
	 *
	 * @param string $client_id Client transaction id.
	 * @return array|null|\WP_Error
	 */
	protected function lookup_transaction( string $client_id ) {
		return $this->get_services()['transaction']->get_by_client_transaction_id( $client_id );
	}

	private function webhook_matches_current_attempt( $order, $payload ) {
		if ( 'CREATING' === strtoupper( (string) $order->get_meta( '_sumup_checkout_status' ) ) ) {
			Logger::log( 'SumUp Webhook: Ignoring an event received during transaction ID handoff.' );

			return false;
		}
		$current_transaction = (string) $order->get_transaction_id();
		$event_transaction   = (string) ( $payload['client_transaction_id'] ?? $payload['reference'] ?? $payload['transaction_id'] ?? '' );
		if ( empty( $current_transaction ) ) {
			return true;
		}
		if ( empty( $event_transaction ) || ! hash_equals( $current_transaction, $event_transaction ) ) {
			Logger::log( 'SumUp Webhook: Ignoring an event that does not match the active payment attempt.' );

			return false;
		}

		return true;
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

	/**
	 * Get the API key from gateway settings.
	 *
	 * @return string API key.
	 */
	private function get_api_key() {
		$gateway_options = get_option( 'woocommerce_sumup_terminal_for_woocommerce_settings', array() );

		return $gateway_options['api_key'] ?? '';
	}

	/**
	 * Initialize services with the current API key.
	 *
	 * @return array Services array.
	 */
	private function get_services() {
		$api_key = $this->get_api_key();

		$profile_service     = new ProfileService( $api_key );
		$reader_service      = new ReaderService( $api_key );
		$transaction_service = new TransactionService( $api_key );

		// Set the profile service for lazy merchant ID loading.
		$reader_service->set_profile_service( $profile_service );
		$transaction_service->set_profile_service( $profile_service );

		// Note: We no longer fetch merchant_code here to avoid unnecessary API calls.
		// The merchant_code will be fetched lazily when needed and cached.

		return array(
			'profile'     => $profile_service,
			'reader'      => $reader_service,
			'transaction' => $transaction_service,
		);
	}
}
