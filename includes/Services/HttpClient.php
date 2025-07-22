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
	 * @var string The merchant ID (retrieved from profile).
	 */
	protected $merchant_id;

	/**
	 * Constructor for the HTTP client.
	 *
	 * @param string $api_key  The SumUp API key.
	 * @param string $base_url The SumUp API base URL.
	 */
	public function __construct( $api_key = '', $base_url = 'https://api.sumup.com' ) {
		$this->api_key  = $api_key;
		$this->base_url = $base_url;
		
		// Debug logging
		Logger::log( 'HttpClient Debug: Initialized with API key: ' . ( ! empty( $api_key ) ? 'PRESENT (' . substr( $api_key, 0, 10 ) . '...)' : 'EMPTY' ) );
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
		$url = $this->base_url . $endpoint;

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
		$url = $this->base_url . $endpoint;

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
		$url = $this->base_url . $endpoint;

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
		$url = $this->base_url . $endpoint;

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
		// Log the request.
		Logger::log( "SumUp API Request: $method $endpoint" );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::log( "SumUp API request failed ($method $endpoint): " . $response->get_error_message() );

			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		// Log the response.
		Logger::log( "SumUp API Response ($method $endpoint): HTTP $code" );

		if ( $code < 200 || $code >= 300 ) {
			$error_details = json_decode( $body, true );
			$error_message = "SumUp API error (HTTP $code) for $method $endpoint";
			
			if ( $error_details && isset( $error_details['message'] ) ) {
				$error_message .= ": " . $error_details['message'];
			}
			
			if ( $error_details && isset( $error_details['error_description'] ) ) {
				$error_message .= ": " . $error_details['error_description'];
			}
			
			if ( ! $error_details || ( ! isset( $error_details['message'] ) && ! isset( $error_details['error_description'] ) ) ) {
				$error_message .= ": " . $body;
			}
			
			Logger::log( $error_message );

			return false;
		}

		$decoded = json_decode( $body, true );

		return null !== $decoded ? $decoded : false;
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
