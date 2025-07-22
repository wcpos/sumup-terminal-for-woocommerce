<?php
/**
 * AJAX Handler for SumUp Terminal
 * Handles AJAX requests for admin functionality.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal;

use Exception;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\CheckoutService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ProfileService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\WebhookService;

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
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sumup_payment_actions' ) ) {
			wp_send_json_error( __( 'Security check failed', 'sumup-terminal-for-woocommerce' ) );
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

			// Prepare checkout data
			$checkout_data = array(
				'amount'   => \floatval( $order->get_total() ),
				'currency' => $order->get_currency(),
			);

			// Add webhook URL for transaction notifications
			$webhook_url                  = home_url( '/wp-json/sumup-terminal/v1/webhook' );
			$checkout_data['webhook_url'] = $webhook_url;

			// Create checkout on specific reader
			$result = $services['reader']->checkout( $reader_id, $checkout_data );

			if ( $result ) {
				// Store the checkout reference for this order
				$order->update_meta_data( '_sumup_checkout_reference', $result['id'] ?? '' );
				$order->update_meta_data( '_sumup_reader_id', $reader_id );
				$order->save();

				wp_send_json_success( array(
					'message'     => __( 'Payment started successfully. Please follow instructions on the card reader.', 'sumup-terminal-for-woocommerce' ),
					'checkout_id' => $result['id'] ?? '',
					'reader_id'   => $reader_id,
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
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'sumup_payment_actions' ) ) {
			wp_send_json_error( __( 'Security check failed', 'sumup-terminal-for-woocommerce' ) );
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
				// Clear checkout metadata if we have an order
				if ( $order_id ) {
					$order = wc_get_order( $order_id );
					if ( $order ) {
						$order->delete_meta_data( '_sumup_checkout_reference' );
						$order->delete_meta_data( '_sumup_reader_id' );
						$order->save();
					}
				}

				wp_send_json_success( array(
					'message'   => __( 'Payment cancelled successfully.', 'sumup-terminal-for-woocommerce' ),
					'reader_id' => $reader_id,
				) );
			} else {
				wp_send_json_error( __( 'Failed to cancel payment', 'sumup-terminal-for-woocommerce' ) );
			}
		} catch ( Exception $e ) {
			Logger::log( 'Reader checkout cancellation failed: ' . $e->getMessage() );
			wp_send_json_error( __( 'Failed to cancel payment. Please try again.', 'sumup-terminal-for-woocommerce' ) );
		}
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

		$profile_service  = new ProfileService( $api_key );
		$checkout_service = new CheckoutService( $api_key );
		$reader_service   = new ReaderService( $api_key );
		$webhook_service  = new WebhookService( $api_key );

		// Set merchant ID if available.
		$merchant_code = $profile_service->get_merchant_code();
		if ( $merchant_code ) {
			$checkout_service->set_merchant_id( $merchant_code );
			$reader_service->set_merchant_id( $merchant_code );
			$webhook_service->set_merchant_id( $merchant_code );
		}

		return array(
			'profile'  => $profile_service,
			'checkout' => $checkout_service,
			'reader'   => $reader_service,
			'webhook'  => $webhook_service,
		);
	}
}
