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
		if ( ! $this->has_api_key() ) {
			return false;
		}

		return parent::get( '/me' );
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

		// Check if merchant profile exists and has merchant_code
		if ( $profile && isset( $profile['merchant_profile']['merchant_code'] ) ) {
			$merchant_code = $profile['merchant_profile']['merchant_code'];
			$this->set_merchant_id( $merchant_code );

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

		return parent::get( "/merchants/{$merchant_code}" );
	}
}
