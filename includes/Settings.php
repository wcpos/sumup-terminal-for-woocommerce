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

	/**
	 * Read the POS mode. Nothing saved means the cloud: a Solo paired to the store works from
	 * every till, while Bluetooth needs the native app and leaves a web or desktop till with a
	 * hidden tile (found on the first physical Solo run, 2026-10-05).
	 */
	public static function get_wcpos_connection(): string {
		if ( ! self::device_mode_available() ) {
			return 'server';
		}
		return self::get_gateway_settings()['wcpos_connection'] ?? 'server';
	}

	/** Whether the installed WCPOS Pro carries the device-provider contract this adapter needs. */
	public static function device_mode_available(): bool {
		return \function_exists( 'wcpos_pro_register_device_provider' );
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
