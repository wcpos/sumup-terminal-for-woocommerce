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
	 * @var null|array Cached profile data for the current request.
	 */
	private $cached_profile = null;

	/**
	 * @var bool Whether we've attempted to load the profile in this request.
	 */
	private $profile_loaded = false;

	/**
	 * Get the current user's profile with caching.
	 *
	 * @param bool $force_refresh Whether to force a fresh API call, bypassing cache.
	 *
	 * @return array|false Profile data or false on failure.
	 */
	public function get_profile( $force_refresh = false ) {
		if ( ! $this->has_api_key() ) {
			return false;
		}

		// Return cached data if available and not forcing refresh
		if ( ! $force_refresh && $this->profile_loaded ) {
			return $this->cached_profile;
		}

		// Try to get from WordPress transient cache first (unless forcing refresh)
		$cache_key = 'sumup_profile_' . md5( $this->api_key );
		if ( ! $force_refresh ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data ) {
				$this->cached_profile = $cached_data;
				$this->profile_loaded = true;

				return $this->cached_profile;
			}
		}

		// Make API call to get fresh data
		$profile = parent::get( '/me' );
		
		// Cache the result (even if false, to avoid repeated failed calls)
		$this->cached_profile = $profile;
		$this->profile_loaded = true;
		
		// Store in WordPress transient for 5 minutes if successful
		if ( false !== $profile ) {
			set_transient( $cache_key, $profile, 5 * MINUTE_IN_SECONDS );
		}

		return $this->cached_profile;
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
	 * Get the merchant code from the user's profile with caching.
	 *
	 * @param bool $force_refresh Whether to force a fresh API call.
	 *
	 * @return false|string Merchant code or false on failure.
	 */
	public function get_merchant_code( $force_refresh = false ) {
		$profile = $this->get_profile( $force_refresh );

		// Check if merchant profile exists and has merchant_code
		if ( $profile && isset( $profile['merchant_profile']['merchant_code'] ) ) {
			$merchant_code = $profile['merchant_profile']['merchant_code'];
			$this->set_merchant_id( $merchant_code );

			return $merchant_code;
		}

		return false;
	}

	/**
	 * Clear the profile cache.
	 * Should be called when the API key changes.
	 */
	public function clear_cache(): void {
		// Clear in-memory cache
		$this->cached_profile = null;
		$this->profile_loaded = false;

		// Clear WordPress transient cache
		$cache_key = 'sumup_profile_' . md5( $this->api_key );
		delete_transient( $cache_key );
	}

	/**
	 * Get the merchant profile information.
	 *
	 * @param bool $force_refresh Whether to force a fresh API call.
	 *
	 * @return array|false Merchant profile data or false on failure.
	 */
	public function get_merchant_profile( $force_refresh = false ) {
		$merchant_code = $this->get_merchant_code( $force_refresh );

		if ( ! $merchant_code ) {
			return false;
		}

		return parent::get( "/merchants/{$merchant_code}" );
	}
}
