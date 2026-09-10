<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/pos-reader-settings.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
use WCPOS\WooCommercePOS\SumUpTerminal\Server\Pos_Reader_Settings;
define( 'ABSPATH', __DIR__ );
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://shop.example/plugins/sumup/'; }
function register_activation_hook( $file, $callback ) {}
require_once __DIR__ . '/../../sumup-terminal-for-woocommerce.php';
$callback = array( Pos_Reader_Settings::class, 'migrate_once' );
expect( in_array( $callback, $GLOBALS['hooks']['plugins_loaded'][30], true ), 'migration hooked after Pro loads' );
enable_pro();
$option = 'woocommerce_pos_settings_payment_gateways';
$GLOBALS['options']['woocommerce_' . Settings::GATEWAY_ID . '_settings'] = array( 'default_reader' => 'one', 'allowed_readers' => array( 'one' ), 'lock_to_default' => 'yes' );
$GLOBALS['options'][ $option ] = array( 'gateways' => array( Settings::GATEWAY_ID => array( 'default_reader' => 'existing' ) ) );
call_user_func( $callback );
expect( array( 'default_reader' => 'existing', 'allowed_readers' => array( 'one' ), 'lock_to_default' => true ) === $GLOBALS['options'][ $option ]['gateways'][ Settings::GATEWAY_ID ], 'migration seeds missing keys only' );
expect( SUTWC_VERSION === get_option( Pos_Reader_Settings::MIGRATED_OPTION ), 'migration flag stores plugin version' );
$GLOBALS['options'][ $option ]['gateways'][ Settings::GATEWAY_ID ]['allowed_readers'] = array( 'pos-choice' );
$GLOBALS['options']['woocommerce_' . Settings::GATEWAY_ID . '_settings']['default_reader'] = 'changed';
$before = $GLOBALS['options'];
Pos_Reader_Settings::migrate_once();
expect( $before === $GLOBALS['options'], 'second migration does not overwrite POS choices' );
echo "pos-reader-migration ok\n";
