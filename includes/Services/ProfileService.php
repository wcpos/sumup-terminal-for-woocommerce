<?php
/**
 * SumUp Profile Service
 * Handles profile-related SumUp API operations.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

/**
 * Class ProfileService.
 */
class ProfileService extends HttpClient {
	/**
	 * Get the current user's profile.
	 *
	 * @return array|false Profile data or false on failure.
	 */
	public function get_profile() {
		\WCPOS\WooCommercePOS\SumUpTerminal\Logger::log( 'ProfileService Debug: has_api_key() = ' . ( $this->has_api_key() ? 'TRUE' : 'FALSE' ) );
		
		if ( ! $this->has_api_key() ) {
			\WCPOS\WooCommercePOS\SumUpTerminal\Logger::log( 'ProfileService Debug: No API key available' );

			return false;
		}

		\WCPOS\WooCommercePOS\SumUpTerminal\Logger::log( 'ProfileService Debug: Making API call to /v0.1/me' );
		$result = parent::get( '/v0.1/me' );
		\WCPOS\WooCommercePOS\SumUpTerminal\Logger::log( 'ProfileService Debug: API call returned: ' . ( $result ? 'DATA' : 'FALSE' ) );
		
		return $result;
	}

	/**
	 * Test if the API key is valid by attempting to get the profile.
	 *
	 * @return bool True if API key is valid, false otherwise.
	 */
	public function test_api_key() {
		$profile = $this->get_profile();

		return false !== $profile;
	}

	/**
	 * Get the merchant code from the user's profile.
	 *
	 * @return false|string Merchant code or false on failure.
	 */
	public function get_merchant_code() {
		$profile = $this->get_profile();

		// Debug logging
		\WCPOS\WooCommercePOS\SumUpTerminal\Logger::log( 'ProfileService Debug: Profile API call result: ' . ( $profile ? 'SUCCESS' : 'FAILED' ) );
		if ( $profile ) {
			\WCPOS\WooCommercePOS\SumUpTerminal\Logger::log( 'ProfileService Debug: Has merchant_profile? ' . ( isset( $profile['merchant_profile'] ) ? 'YES' : 'NO' ) );
			if ( isset( $profile['merchant_profile'] ) ) {
				\WCPOS\WooCommercePOS\SumUpTerminal\Logger::log( 'ProfileService Debug: Has merchant_code in merchant_profile? ' . ( isset( $profile['merchant_profile']['merchant_code'] ) ? 'YES' : 'NO' ) );
			}
		}

		// Check if merchant profile exists and has merchant_code
		if ( $profile && isset( $profile['merchant_profile']['merchant_code'] ) ) {
			$merchant_code = $profile['merchant_profile']['merchant_code'];
			$this->set_merchant_id( $merchant_code );

			\WCPOS\WooCommercePOS\SumUpTerminal\Logger::log( 'ProfileService Debug: Found merchant_code: ' . $merchant_code );

			return $merchant_code;
		}

		return false;
	}

	/**
	 * Get the merchant profile information.
	 *
	 * @return array|false Merchant profile data or false on failure.
	 */
	public function get_merchant_profile() {
		$merchant_code = $this->get_merchant_code();

		if ( ! $merchant_code ) {
			return false;
		}

		return parent::get( "/v0.1/merchants/{$merchant_code}" );
	}
}
