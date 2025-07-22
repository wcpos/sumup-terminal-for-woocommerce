<?php
/**
 * Provides shared SumUp error-handling functionality.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Abstracts;

use Exception;
use WCPOS\WooCommercePOS\SumUpTerminal\Logger;
use WP_Error;

/**
 * Trait SumUpErrorHandler
 * Provides shared SumUp error-handling functionality.
 */
trait SumUpErrorHandler {
	/**
	 * Process a SumUp exception and return a formatted WP_Error or string.
	 *
	 * @param Exception $e       The exception to handle.
	 * @param string    $context A context string (e.g., 'api', 'gateway').
	 *
	 * @return string|WP_Error A formatted error for WordPress or a string for admin notices.
	 */
	public function handle_sumup_exception( Exception $e, string $context = 'general' ) {
		// Initialize logging.
		$log_message = \sprintf(
			'[%s] SumUp API Exception: %s (Code: %s)',
			$context,
			$e->getMessage(),
			$e->getCode()
		);

		// Log the error.
		Logger::log( $log_message );

		if ( $e instanceof \SumUp\Exceptions\SumUpAuthenticationException ) {
			$status_code = 401; // Unauthorized.
			$error_data  = array(
				'context'    => $context,
				'status'     => $status_code,
				'error_type' => 'authentication',
			);

			// Log detailed error data for debugging.
			Logger::log( $error_data );

			// For admin notices, return a string.
			if ( 'admin' === $context ) {
				return \sprintf(
					__( 'SumUp Authentication Error: %s. Please check your App ID, App Secret, and Authorization Code.', 'sumup-terminal-for-woocommerce' ),
					esc_html( $e->getMessage() )
				);
			}

			// For API responses, return a WP_Error.
			return new WP_Error(
				'sumup_auth_error',
				'SumUp authentication failed: ' . $e->getMessage(),
				$error_data
			);
		}
		if ( $e instanceof \SumUp\Exceptions\SumUpResponseException ) {
			$status_code = 400; // Bad Request (default for response errors).
			$error_data  = array(
				'context'    => $context,
				'status'     => $status_code,
				'error_type' => 'response',
			);

			// Try to get more specific status code if available.
			if ( method_exists( $e, 'getHttpStatusCode' ) ) {
				$status_code          = $e->getHttpStatusCode();
				$error_data['status'] = $status_code;
			}

			// Log detailed error data for debugging.
			Logger::log( $error_data );

			// For admin notices, return a string.
			if ( 'admin' === $context ) {
				return \sprintf(
					__( 'SumUp Response Error (%1$s): %2$s', 'sumup-terminal-for-woocommerce' ),
					esc_html( $status_code ),
					esc_html( $e->getMessage() )
				);
			}

			// For API responses, return a WP_Error.
			return new WP_Error(
				'sumup_response_error',
				$e->getMessage(),
				$error_data
			);
		}
		if ( $e instanceof \SumUp\Exceptions\SumUpSDKException ) {
			$status_code = 500; // Internal Server Error.
			$error_data  = array(
				'context'    => $context,
				'status'     => $status_code,
				'error_type' => 'sdk',
			);

			// Log detailed error data for debugging.
			Logger::log( $error_data );

			// For admin notices, return a string.
			if ( 'admin' === $context ) {
				return \sprintf(
					__( 'SumUp SDK Error: %s. Please contact support if this persists.', 'sumup-terminal-for-woocommerce' ),
					esc_html( $e->getMessage() )
				);
			}

			// For API responses, return a WP_Error.
			return new WP_Error(
				'sumup_sdk_error',
				'SumUp SDK error: ' . $e->getMessage(),
				$error_data
			);
		}

		// For non-SumUp exceptions.
		Logger::log( 'Non-SumUp exception encountered: ' . $e->getMessage() );

		// For non-SumUp exceptions.
		return 'admin' === $context
			? __( 'An unexpected error occurred.', 'sumup-terminal-for-woocommerce' )
			: new WP_Error(
				'general_error',
				'An unexpected error occurred: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
	}
}
