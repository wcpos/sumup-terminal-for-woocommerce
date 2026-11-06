<?php
/**
 * Registration for Pro server checkout.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Server;

use WCPOS\WooCommercePOS\SumUpTerminal\Settings;

/** Register the server and device providers with Pro. */
final class Registration {
	// First Pro release the extension runs on: the shared payments base and the order-pay panel.
	public const REQUIRED_PRO_VERSION = '2.0.0';
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
			if ( 'device' === Settings::get_wcpos_connection() && function_exists( 'wcpos_pro_register_device_provider' ) ) {
				wcpos_pro_register_device_provider( Settings::GATEWAY_ID, SumUp_Device_Provider::class );
			}
			self::$registered = true;
		}
		return true;
	}
}
