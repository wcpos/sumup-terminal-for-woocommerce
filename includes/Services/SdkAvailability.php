<?php
/**
 * Official SumUp SDK availability checks.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

class SdkAvailability {
	public const MINIMUM_PHP_VERSION_ID = 80200;
	public const PREFIXED_SUMUP_CLASS    = 'WCPOS\\WooCommercePOS\\SumUpTerminal\\Vendor\\SumUpSdk\\SumUp\\SumUp';

	public static function is_php_version_supported(): bool {
		return PHP_VERSION_ID >= self::MINIMUM_PHP_VERSION_ID;
	}

	public static function is_sdk_available(): bool {
		return self::is_php_version_supported() && class_exists( self::PREFIXED_SUMUP_CLASS );
	}

	public static function get_status_message(): string {
		if ( self::is_sdk_available() ) {
			return __( 'This site is using the official SumUp PHP SDK for supported Terminal API operations.', 'sumup-terminal-for-woocommerce' );
		}

		if ( ! self::is_php_version_supported() ) {
			return sprintf(
				/* translators: 1: current PHP version. */
				__( 'Your server is running PHP %1$s. The official SumUp PHP SDK requires PHP 8.2 or newer, so this plugin is using its WordPress HTTP compatibility client. Payments can still work normally. Upgrade to PHP 8.2+ to use the official SumUp SDK integration.', 'sumup-terminal-for-woocommerce' ),
				PHP_VERSION
			);
		}

		return __( 'The official SumUp PHP SDK is not bundled in this build, so this plugin is using its WordPress HTTP compatibility client. Payments can still work normally.', 'sumup-terminal-for-woocommerce' );
	}
}
