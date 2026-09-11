<?php
/**
 * Settings for the SumUp Terminal integration.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal;

/**
 * Settings.
 */
class Settings {
	/** Gateway ID shared by WooCommerce options and Pro's provider registry. */
	public const GATEWAY_ID = 'sumup_terminal_for_woocommerce';

	/** Read the API key actually stored by this gateway, not the legacy secret_key getter. */
	public static function api_key(): string {
		return (string) ( self::get_gateway_settings()['api_key'] ?? '' );
	}

	/** Read the affiliate credentials shared by POS checkout modes. */
	public static function affiliate(): array {
		$settings = self::get_gateway_settings();
		return array(
			'app_id' => (string) ( $settings['affiliate_app_id'] ?? '' ),
			'key' => (string) ( $settings['affiliate_key'] ?? '' ),
		);
	}

	/** Read the POS mode, preserving cloud checkout for a configured default reader. */
	public static function get_wcpos_connection(): string {
		$settings = self::get_gateway_settings();
		return $settings['wcpos_connection'] ?? ( ! empty( $settings['default_reader'] ) ? 'server' : 'device' );
	}

	/**
	 * Get the Gateway settings.
	 */
	public static function get_gateway_settings() {
		// Retrieve and return the gateway settings.
		return get_option( 'woocommerce_sumup_terminal_for_woocommerce_settings', array() );
	}

	/**
	 * Get the Stripe Terminal API key.
	 */
	public static function get_api_key() {
		$settings = self::get_gateway_settings();
		if ( isset( $settings['test_mode'] ) && 'yes' === $settings['test_mode'] ) {
			return $settings['test_secret_key'] ?? '';
		}

		return $settings['secret_key'] ?? '';
	}

	/**
	 * Get the Stripe webhook secret.
	 */
	public static function get_webhook_secret() {
		$settings = self::get_gateway_settings();
		if ( isset( $settings['test_mode'] ) && 'yes' === $settings['test_mode'] ) {
			return $settings['test_webhook_secret'] ?? '';
		}

		return $settings['webhook_secret'] ?? '';
	}
}
