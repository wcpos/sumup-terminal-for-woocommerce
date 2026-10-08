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
// Pro defines wcpos_pro_requires() and the registration API from its own plugins_loaded hook at
// priority 20; the gate runs after that, and provider registration happens inside it.
expect( 'WCPOS\\WooCommercePOS\\SumUpTerminal\\init' === ( $hooks['plugins_loaded'][30][0] ?? null ), 'gate runs at 30, after Pro loads' );
expect( ! isset( $hooks['plugins_loaded'][11] ), 'nothing runs before Pro loads' );
expect( ! method_exists( Registration::class, 'activation_check' ), 'the Pro-less activation path is gone' );
$registered = array();
$requires_calls = array();
expect( false === Registration::register() && array() === $registered, 'no Pro registers nothing' );
// Conditional declarations happen at runtime, so the first assertions really have no Pro functions.
if ( ! function_exists( 'wcpos_pro_requires' ) ) {
	function wcpos_pro_requires( $version, $file = '' ) { $GLOBALS['requires_calls'][] = array( $version, $file ); return $GLOBALS['supported']; }
	function wcpos_pro_register_server_provider( $gateway, $class ) { $GLOBALS['registered'][] = array( $gateway, $class ); }
	function esc_html__( $text ) { return $text; }
}
$supported = false;
expect( false === Registration::register() && array() === $registered, 'old Pro does not register' );
$requires_calls = array();
$hooks = array();
WCPOS\WooCommercePOS\SumUpTerminal\init();
expect( array( array( '2.0.0', realpath( __DIR__ . '/../../sumup-terminal-for-woocommerce.php' ) ) ) === $requires_calls, 'the gate asks Pro for 2.0.0 and names the plugin file' );
expect( isset( $hooks['admin_notices'] ) && array() === $registered, 'old Pro gets a notice and no gateway' );
$supported = true;
$requires_calls = array();
expect( true === Registration::register(), 'supported Pro registers' );
expect( array( array( Settings::GATEWAY_ID, SumUp_Server_Provider::class ) ) === $registered, 'correct gateway and adapter' );
expect( true === Registration::register() && 1 === count( $registered ), 'registration idempotent' );
$supported = false;
$requires_calls = array();
call_user_func( $activation );
expect( 1 === count( $requires_calls ) && '2.0.0' === $requires_calls[0][0] && realpath( __DIR__ . '/../../sumup-terminal-for-woocommerce.php' ) === $requires_calls[0][1], 'activation hook records the Pro requirement after the PHP check' );
echo "server-registration ok\n";
