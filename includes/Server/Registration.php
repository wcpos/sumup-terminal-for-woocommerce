<?php
/**
 * Registration for Pro server checkout.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Server;

use WCPOS\WooCommercePOS\SumUpTerminal\Settings;

/** Register only when the shared Pro contract is available. */
final class Registration {
	// First Pro version with wcpos_pro_register_server_provider() and the shared server handler.
	public const REQUIRED_PRO_VERSION = '1.11.0';
	/** Whether this request registered the provider.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/** Check Pro before autoloading the adapter or its parent. */
	public static function pro_supported(): bool {
		return function_exists( 'wcpos_pro_register_server_provider' ) && function_exists( 'wcpos_pro_requires' ) && wcpos_pro_requires( self::REQUIRED_PRO_VERSION );
	}

	/** Register once after Pro public functions load. */
	public static function register(): bool {
		if ( ! self::pro_supported() ) {
			return false;
		}
		if ( ! self::$registered ) {
			wcpos_pro_register_server_provider( Settings::GATEWAY_ID, SumUp_Server_Provider::class );
			self::$registered = true;
		}
		return true;
	}

	/**
	 * Record Pro's notice for an installed but unsupported Pro.
	 *
	 * @param string $plugin_file Plugin entry point.
	 */
	public static function activation_check( string $plugin_file ): void {
		if ( function_exists( 'wcpos_pro_requires' ) && ! wcpos_pro_requires( self::REQUIRED_PRO_VERSION ) ) {
			wcpos_pro_requires( self::REQUIRED_PRO_VERSION, $plugin_file );
		}
	}
}
