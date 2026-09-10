<?php
/**
 * Mirror gateway reader choices into WooCommerce POS settings.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Server;

use WCPOS\WooCommercePOS\SumUpTerminal\Settings;

/** Gateway settings are the source; POS settings are the mirror. */
final class Pos_Reader_Settings {
	/** Version recorded after the one-time seed. */
	public const MIGRATED_OPTION = 'sutwc_pos_reader_settings_migrated';
	/** Free owns the payment gateway settings option. */
	private const SETTINGS_OPTION = 'woocommerce_pos_settings_payment_gateways';

	/** Seed missing POS choices once, after Pro has loaded. */
	public static function migrate_once(): void {
		if ( ! Registration::pro_supported() || get_option( self::MIGRATED_OPTION ) ) {
			return;
		}
		self::mirror( Settings::get_gateway_settings(), false );
		update_option( self::MIGRATED_OPTION, SUTWC_VERSION, false );
	}

	/**
	 * Mirror saved choices without replacing unrelated gateway settings.
	 *
	 * @param array $settings  Saved gateway options.
	 * @param bool  $overwrite Replace existing POS reader choices on explicit save.
	 */
	public static function mirror( array $settings, bool $overwrite = true ): void {
		if ( ! Registration::pro_supported() ) {
			return;
		}
		$options = get_option( self::SETTINGS_OPTION, array() );
		$gateway = $options['gateways'][ Settings::GATEWAY_ID ] ?? array();
		$default = (string) ( $settings['default_reader'] ?? '' );
		$values = array(
			'default_reader' => $default,
			'allowed_readers' => array_values(
				array_filter(
					(array) ( $settings['allowed_readers'] ?? array() ),
					static function ( $id ) {
						return is_string( $id ) && '' !== $id;
					}
				)
			),
			'lock_to_default' => '' !== $default && 'yes' === ( $settings['lock_to_default'] ?? 'no' ),
		);
		$options['gateways'][ Settings::GATEWAY_ID ] = $overwrite ? array_replace( $gateway, $values ) : $gateway + $values;
		update_option( self::SETTINGS_OPTION, $options );
	}
}
