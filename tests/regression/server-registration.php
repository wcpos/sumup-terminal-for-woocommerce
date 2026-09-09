<?php
function expect( $condition, $message = 'expectation failed' ) { if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }
require_once __DIR__ . '/stubs/wcpos-pro-server.php';
require_once __DIR__ . '/../../includes/Settings.php';
expect( file_exists( __DIR__ . '/../../includes/Server/Registration.php' ), 'registration is missing' );
require_once __DIR__ . '/../../includes/Server/Registration.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Server\Registration;
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider;
use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
define( 'ABSPATH', __DIR__ );
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://shop.example/plugins/sumup/'; }
function register_activation_hook( $file, $callback ) { $GLOBALS['activation'] = $callback; }
function register_deactivation_hook( $file, $callback ) {}
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['hooks'][$hook][$priority][] = $callback; }
require_once __DIR__ . '/../../sumup-terminal-for-woocommerce.php';
expect( array( Registration::class, 'register' ) === ( $hooks['plugins_loaded'][30][0] ?? null ), 'register after Pro public functions load' );
expect( 'WCPOS\\WooCommercePOS\\SumUpTerminal\\init' === $hooks['plugins_loaded'][11][0], 'legacy init priority unchanged' );
$registered = array();
$requires_calls = array();
expect( false === Registration::register() && array() === $registered, 'no Pro is legacy only' );
Registration::activation_check( '/plugin.php' );
expect( array() === $requires_calls, 'activation without Pro is a no-op' );
// Conditional declarations happen at runtime, so the first assertions really have no Pro functions.
if ( ! function_exists( 'wcpos_pro_requires' ) ) {
	function wcpos_pro_requires( $version, $file = '' ) { $GLOBALS['requires_calls'][] = array( $version, $file ); return $GLOBALS['supported']; }
	function wcpos_pro_register_server_provider( $gateway, $class ) { $GLOBALS['registered'][] = array( $gateway, $class ); }
}
$supported = false;
expect( false === Registration::register() && array() === $registered, 'old Pro does not register' );
$requires_calls = array();
Registration::activation_check( '/plugin.php' );
expect( array( array( '1.11.0', '' ), array( '1.11.0', '/plugin.php' ) ) === $requires_calls, 'old Pro persists activation notice' );
$supported = true;
$requires_calls = array();
Registration::activation_check( '/plugin.php' );
expect( array( array( '1.11.0', '' ) ) === $requires_calls, 'supported Pro needs no notice' );
expect( true === Registration::register(), 'supported Pro registers' );
expect( array( array( Settings::GATEWAY_ID, SumUp_Server_Provider::class ) ) === $registered, 'correct gateway and adapter' );
expect( true === Registration::register() && 1 === count( $registered ), 'registration idempotent' );
$supported = false;
$requires_calls = array();
call_user_func( $activation );
expect( 2 === count( $requires_calls ) && realpath( __DIR__ . '/../../sumup-terminal-for-woocommerce.php' ) === $requires_calls[1][1], 'activation hook checks Pro after PHP check' );
echo "server-registration ok\n";
