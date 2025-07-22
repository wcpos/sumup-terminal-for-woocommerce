<?php
/**
 * Plugin Name: SumUp Terminal for WooCommerce
 * Description: Adds SumUp Terminal support to WooCommerce for in-person payments.
 * Version:     0.0.2
 * Author:      kilbot
 * Author URI:  https://kilbot.com/
 * License:     GPL v2 or later
 * Text Domain: sumup-terminal-for-woocommerce.
 *
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal;

if ( ! \defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define constants.
\define( 'SUTWC_VERSION', '0.0.2' );
\define( 'SUTWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
\define( 'SUTWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include Composer's autoloader.
if ( file_exists( SUTWC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once SUTWC_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	Logger::log( 'SumUp Terminal for WooCommerce: Composer autoloader not found.' );
}

// Autoload classes using PSR-4.
spl_autoload_register(
	function ( $class ): void {
		$prefix   = __NAMESPACE__ . '\\';
		$base_dir = SUTWC_PLUGIN_DIR . 'includes/';
		$len      = \strlen( $prefix );

		if ( 0 !== strncmp( $prefix, $class, $len ) ) {
			return; // Not in our namespace.
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Initialize the plugin.
 */
function init(): void {
	// Register the gateway.
	add_filter( 'woocommerce_payment_gateways', array( Gateway::class, 'register_gateway' ) );

	// Initialize AJAX handlers early.
	new AjaxHandler();

	// Initialize API.
	add_action(
		'rest_api_init',
		function (): void {
			new API();
		}
	);
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init', 11 );
