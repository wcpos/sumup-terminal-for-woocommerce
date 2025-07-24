<?php
/**
 * AJAX Handler for SumUp Terminal
 * Handles AJAX requests for admin functionality.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal;

use Exception;

use WCPOS\WooCommercePOS\SumUpTerminal\Services\ProfileService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService;

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
			
			// Payment AJAX handlers (available to both admin and frontend users)
			add_action( 'wp_ajax_sumup_create_checkout', array( $this, 'ajax_create_checkout' ) );
			add_action( 'wp_ajax_nopriv_sumup_create_checkout', array( $this, 'ajax_create_checkout' ) );
			add_action( 'wp_ajax_sumup_cancel_checkout', array( $this, 'ajax_cancel_checkout' ) );
			add_action( 'wp_ajax_nopriv_sumup_cancel_checkout', array( $this, 'ajax_cancel_checkout' ) );
			add_action( 'wp_ajax_sumup_check_payment_status', array( $this, 'ajax_check_payment_status' ) );
			add_action( 'wp_ajax_nopriv_sumup_check_payment_status', array( $this, 'ajax_check_payment_status' ) );
			
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

		$pairing_code = sanitize_text_field( $_POST['pairing_code'] ?? '' );

		if ( empty( $pairing_code ) ) {
			wp_send_json_error( __( 'Pairing code is required', 'sumup-terminal-for-woocommerce' ) );
		}

		try {
			$services = $this->get_services();
			$result   = $services['reader']->create( array(
				'pairing_code' => $pairing_code,
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
	 * AJAX handler for creating a reader checkout.
	 */
	public function ajax_create_checkout(): void {
		// Skip nonce validation for POS environment due to user context switching
		// Instead, verify that this is a valid AJAX request and has required parameters
		if ( ! wp_doing_ajax() ) {
			wp_send_json_error( __( 'Invalid request', 'sumup-terminal-for-woocommerce' ) );
		}

		$reader_id = sanitize_text_field( $_POST['reader_id'] ?? '' );
		$order_id  = absint( $_POST['order_id'] ?? 0 );

		if ( empty( $reader_id ) ) {
			wp_send_json_error( __( 'Reader ID is required', 'sumup-terminal-for-woocommerce' ) );
		}

		if ( empty( $order_id ) ) {
			wp_send_json_error( __( 'Order ID is required', 'sumup-terminal-for-woocommerce' ) );
		}

		// Get the order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( __( 'Invalid order', 'sumup-terminal-for-woocommerce' ) );
		}

		try {
			$services = $this->get_services();

			// Create checkout on specific reader - ReaderService will handle checkout_data construction
			$result = $services['reader']->create_checkout_for_order( $order, $reader_id );

			if ( $result ) {
				// Extract transaction ID from SumUp API response
				$transaction_id = null;
				if ( isset( $result['data']['client_transaction_id'] ) ) {
					$transaction_id = $result['data']['client_transaction_id'];
				}

				// Store the transaction ID for this order
				if ( $transaction_id ) {
					$order->set_transaction_id( $transaction_id );
					Logger::log( 'SumUp transaction ID saved: ' . $transaction_id . ' for order: ' . $order_id );
				}
				
				$order->update_meta_data( '_sumup_reader_id', $reader_id );
				$order->save();

				wp_send_json_success( array(
					'message'        => __( 'Payment started successfully. Please follow instructions on the card reader.', 'sumup-terminal-for-woocommerce' ),
					'transaction_id' => $transaction_id,
					'reader_id'      => $reader_id,
				) );
			} else {
				wp_send_json_error( __( 'Failed to start payment on reader', 'sumup-terminal-for-woocommerce' ) );
			}
		} catch ( Exception $e ) {
			Logger::log( 'Reader checkout creation failed: ' . $e->getMessage() );
			wp_send_json_error( __( 'Failed to start payment. Please try again.', 'sumup-terminal-for-woocommerce' ) );
		}
	}

	/**
	 * AJAX handler for cancelling a reader checkout.
	 */
	public function ajax_cancel_checkout(): void {
		// Skip nonce validation for POS environment due to user context switching
		// Instead, verify that this is a valid AJAX request and has required parameters
		if ( ! wp_doing_ajax() ) {
			wp_send_json_error( __( 'Invalid request', 'sumup-terminal-for-woocommerce' ) );
		}

		$reader_id = sanitize_text_field( $_POST['reader_id'] ?? '' );
		$order_id  = absint( $_POST['order_id'] ?? 0 );

		if ( empty( $reader_id ) ) {
			wp_send_json_error( __( 'Reader ID is required', 'sumup-terminal-for-woocommerce' ) );
		}

		try {
			$services = $this->get_services();

			// Cancel checkout on reader
			$result = $services['reader']->cancel_checkout( $reader_id );

			if ( $result ) {
				// Clear reader metadata if we have an order
				if ( $order_id ) {
					$order = wc_get_order( $order_id );
					if ( $order ) {
						$order->delete_meta_data( '_sumup_reader_id' );
						$order->save();
					}
				}

				wp_send_json_success( array(
					'message'   => __( 'Cancellation request sent to reader. Please wait for confirmation on the device.', 'sumup-terminal-for-woocommerce' ),
					'reader_id' => $reader_id,
				) );
			} else {
				wp_send_json_error( __( 'Failed to send cancellation request to reader', 'sumup-terminal-for-woocommerce' ) );
			}
		} catch ( Exception $e ) {
			Logger::log( 'Reader checkout cancellation failed: ' . $e->getMessage() );
			wp_send_json_error( __( 'Failed to send cancellation request. Please try again.', 'sumup-terminal-for-woocommerce' ) );
		}
	}

	/**
	 * AJAX handler for checking payment status.
	 */
	public function ajax_check_payment_status(): void {
		// Skip nonce validation for POS environment due to user context switching
		// Instead, verify that this is a valid AJAX request and has required parameters
		if ( ! wp_doing_ajax() ) {
			wp_send_json_error( __( 'Invalid request', 'sumup-terminal-for-woocommerce' ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );

		if ( empty( $order_id ) ) {
			wp_send_json_error( __( 'Order ID is required', 'sumup-terminal-for-woocommerce' ) );
		}

		// Get the order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( __( 'Invalid order', 'sumup-terminal-for-woocommerce' ) );
		}

		// Get the checkout status from order meta
		$checkout_status  = $order->get_meta( '_sumup_checkout_status' );
		$checkout_updated = $order->get_meta( '_sumup_checkout_updated' );

		// If no status is set yet, the payment is still pending
		if ( empty( $checkout_status ) ) {
			wp_send_json_success( array(
				'status'           => 'PENDING',
				'message'          => __( 'Waiting for payment confirmation...', 'sumup-terminal-for-woocommerce' ),
				'continue_polling' => true,
			) );

			return;
		}

		// Handle different statuses
		switch ( strtoupper( $checkout_status ) ) {
			case 'PAID':
				wp_send_json_success( array(
					'status'           => 'PAID',
					'message'          => __( 'Payment successful! Processing order...', 'sumup-terminal-for-woocommerce' ),
					'continue_polling' => false,
					'submit_form'      => true,
				) );

				break;

			case 'FAILED':
			case 'CANCELLED':
			case 'TIMEOUT':
				wp_send_json_success( array(
					'status'  => $checkout_status,
					'message' => \sprintf(
						__( 'Payment %s. Please try again.', 'sumup-terminal-for-woocommerce' ),
						strtolower( $checkout_status )
					),
					'continue_polling' => false,
					'submit_form'      => false,
				) );

				break;

			default:
				// For other statuses (like PENDING, IN_PROGRESS), continue polling
				wp_send_json_success( array(
					'status'  => $checkout_status,
					'message' => \sprintf(
						__( 'Payment status: %s', 'sumup-terminal-for-woocommerce' ),
						$checkout_status
					),
					'continue_polling' => true,
				) );

				break;
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
		$event_type = $webhook_data['event_type'];
		$payload    = $webhook_data['payload'];
		$timestamp  = $webhook_data['timestamp'] ?? gmdate( 'c' );

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

		if ( empty( $status ) ) {
			Logger::log( 'SumUp Webhook: Missing status in checkout.status.updated payload' );

			return;
		}

		// Store SumUp status in order meta
		$order->update_meta_data( '_sumup_checkout_status', $status );
		$order->update_meta_data( '_sumup_checkout_updated', $timestamp );

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

		if ( empty( $status ) ) {
			Logger::log( 'SumUp Webhook: Missing status in solo.transaction.updated payload' );

			return;
		}

		// Store SumUp status in order meta
		$order->update_meta_data( '_sumup_transaction_status', $status );
		$order->update_meta_data( '_sumup_transaction_updated', $timestamp );

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

		$profile_service = new ProfileService( $api_key );
		$reader_service  = new ReaderService( $api_key );

		// Set merchant ID if available.
		$merchant_code = $profile_service->get_merchant_code();
		if ( $merchant_code ) {
			$reader_service->set_merchant_id( $merchant_code );
		}

		return array(
			'profile' => $profile_service,
			'reader'  => $reader_service,
		);
	}
}
