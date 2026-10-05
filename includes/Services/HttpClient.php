<?php
/**
 * SumUp HTTP Client
 * Base class for making HTTP requests to the SumUp API.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

use WCPOS\WooCommercePOS\SumUpTerminal\Logger;

/**
 * Class HttpClient.
 */
class HttpClient {
	/**
	 * @var string The SumUp API Key.
	 */
	protected $api_key;

	/**
	 * @var string The SumUp API base URL.
	 */
	protected $base_url;

	/**
	 * @var string The SumUp API version.
	 */
	protected $api_version;

	/**
	 * @var string The merchant ID (retrieved from profile).
	 */
	protected $merchant_id;

	/**
	 * Why the last request failed.
	 *
	 * Callers return `false` to the POS and still owe the cashier the reason SumUp gave; the
	 * HTTP status is kept in the error data so a 4xx can be told from an outage.
	 *
	 * @var null|\WP_Error
	 */
	protected $last_error = null;

	/**
	 * Constructor for the HTTP client.
	 *
	 * @param string $api_key     The SumUp API key.
	 * @param string $base_url    The SumUp API base URL.
	 * @param string $api_version The SumUp API version.
	 */
	public function __construct( $api_key = '', $base_url = '', $api_version = 'v0.1' ) {
		// Use development URL if defined, otherwise use production URL
		if ( empty( $base_url ) ) {
			$base_url = \defined( 'SUMUP_API_BASE_URL' ) ? SUMUP_API_BASE_URL : 'https://api.sumup.com';
		}
		
		$this->api_key     = $api_key;
		$this->base_url    = $base_url;
		$this->api_version = $api_version;
	}

	/**
	 * Set the merchant ID.
	 *
	 * @param string $merchant_id Merchant ID.
	 */
	public function set_merchant_id( $merchant_id ): void {
		$this->merchant_id = $merchant_id;
	}

	/**
	 * Get the merchant ID.
	 *
	 * @return string Merchant ID.
	 */
	public function get_merchant_id() {
		return $this->merchant_id;
	}

	/**
	 * Check if the API key is set.
	 *
	 * @return bool True if API key is set.
	 */
	public function has_api_key() {
		return ! empty( $this->api_key );
	}

	/**
	 * Get the API version prefix for endpoints.
	 *
	 * @return string API version prefix (e.g., '/v0.1')
	 */
	protected function get_api_version_prefix() {
		return '/' . $this->api_version;
	}

	/**
	 * Get the authorization headers.
	 *
	 * @return array Authorization headers.
	 */
	protected function get_headers() {
		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( ! empty( $this->api_key ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		return $headers;
	}

	/**
	 * Make a GET request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $params   Query parameters.
	 *
	 * @return array|false Response data or false on failure.
	 */
	protected function get( $endpoint, $params = array() ) {
		$url = $this->base_url . $this->get_api_version_prefix() . $endpoint;

		if ( ! empty( $params ) ) {
			$url .= '?' . http_build_query( $params );
		}

		$args = array(
			'method'  => 'GET',
			'headers' => $this->get_headers(),
			'timeout' => 30,
		);

		return $this->make_request( $url, $args, 'GET', $endpoint, $params );
	}

	/**
	 * Make a POST request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 *
	 * @return array|false Response data or false on failure.
	 */
	protected function post( $endpoint, $data = array() ) {
		$url = $this->base_url . $this->get_api_version_prefix() . $endpoint;

		$args = array(
			'method'  => 'POST',
			'headers' => $this->get_headers(),
			'body'    => wp_json_encode( $data ),
			'timeout' => 30,
		);

		return $this->make_request( $url, $args, 'POST', $endpoint, $data );
	}

	/**
	 * Make a PUT request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 *
	 * @return array|false Response data or false on failure.
	 */
	protected function put( $endpoint, $data = array() ) {
		$url = $this->base_url . $this->get_api_version_prefix() . $endpoint;

		$args = array(
			'method'  => 'PUT',
			'headers' => $this->get_headers(),
			'body'    => wp_json_encode( $data ),
			'timeout' => 30,
		);

		return $this->make_request( $url, $args, 'PUT', $endpoint, $data );
	}

	/**
	 * Make a DELETE request.
	 *
	 * @param string $endpoint API endpoint.
	 *
	 * @return array|false Response data or false on failure.
	 */
	protected function delete( $endpoint ) {
		$url = $this->base_url . $this->get_api_version_prefix() . $endpoint;

		$args = array(
			'method'  => 'DELETE',
			'headers' => $this->get_headers(),
			'timeout' => 30,
		);

		return $this->make_request( $url, $args, 'DELETE', $endpoint );
	}

	/**
	 * Make the actual HTTP request.
	 *
	 * @param string $url      Request URL.
	 * @param array  $args     Request arguments.
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint for logging.
	 * @param array  $data     Request data for logging.
	 *
	 * @return array|false Response data or false on failure.
	 */
	private function make_request( $url, $args, $method, $endpoint, $data = array() ) {
		$this->last_error = null;
		$response         = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::log( "SumUp API request failed ($method $endpoint): " . $response->get_error_message() );
			$this->last_error = $response;

			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$detail        = self::error_detail( $body );
			$error_message = "SumUp API error (HTTP $code) for $method $endpoint: " . $detail;
			Logger::log( $error_message );
			$this->last_error = new \WP_Error( 'sumup_http_' . $code, $detail, array( 'status' => (int) $code ) );

			return false;
		}

		if ( '' === trim( $body ) ) {
			return array( 'success' => true );
		}

		$decoded = json_decode( $body, true );

		return null !== $decoded ? $decoded : false;
	}

	/**
	 * Why the last request failed, or null when it succeeded.
	 *
	 * @return null|\WP_Error
	 */
	public function last_error() {
		return $this->last_error;
	}

	/**
	 * The one line SumUp's error body carries. The Readers API answers a refused checkout
	 * with `{"errors":{"total_amount":["this merchant can only accept 'EUR'"]}}`; the
	 * OAuth and v0.1 endpoints use `message` or `error_description`. Whatever the shape,
	 * the cashier gets SumUp's words, not "request failed".
	 *
	 * @param string $body Response body.
	 */
	private static function error_detail( $body ): string {
		$details = json_decode( $body, true );
		if ( ! is_array( $details ) ) {
			return (string) $body;
		}
		$lines = array();
		foreach ( array( 'message', 'error_description' ) as $key ) {
			if ( isset( $details[ $key ] ) && is_scalar( $details[ $key ] ) ) {
				$lines[] = (string) $details[ $key ];
			}
		}
		if ( isset( $details['errors'] ) && is_array( $details['errors'] ) ) {
			array_walk_recursive(
				$details['errors'],
				static function ( $value ) use ( &$lines ) {
					if ( is_scalar( $value ) ) {
						$lines[] = (string) $value;
					}
				}
			);
		}
		return $lines ? implode( '; ', $lines ) : (string) $body;
	}

	/**
	 * Sanitize headers for logging (remove sensitive data).
	 *
	 * @param array $headers Headers array.
	 *
	 * @return array Sanitized headers.
	 */
	private function sanitize_headers_for_log( $headers ) {
		$sanitized = $headers;

		if ( isset( $sanitized['Authorization'] ) ) {
			$sanitized['Authorization'] = 'Bearer ***';
		}

		return $sanitized;
	}
}
