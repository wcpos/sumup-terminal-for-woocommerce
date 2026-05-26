<?php
/**
 * Plugin Name: SumUp Terminal for WooCommerce
 * Description: Adds SumUp Terminal support to WooCommerce for in-person payments.
 * Version:     0.0.8
 * Author:      kilbot
 * Author URI:  https://kilbot.com/
 * Update URI:  https://github.com/wcpos/sumup-terminal-for-woocommerce
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
\define( 'SUTWC_VERSION', '0.0.8' );
\define( 'SUTWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
\define( 'SUTWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
\define( 'SUTWC_MINIMUM_PHP_VERSION', '7.4' );
\define( 'SUTWC_MINIMUM_PHP_VERSION_ID', 70400 );

// Include Composer's autoloader.
if ( file_exists( SUTWC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once SUTWC_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	Logger::log( 'SumUp Terminal for WooCommerce: Composer autoloader not found.' );
}

// Include the prefixed official SumUp SDK only on PHP versions that can parse it.
// This runtime guard must happen before requiring the SDK autoloader.
$prefixed_sumup_autoload = SUTWC_PLUGIN_DIR . 'vendor_prefixed/sumup-sdk-autoload.php';
if ( PHP_VERSION_ID >= 80200 && file_exists( $prefixed_sumup_autoload ) ) {
	require_once $prefixed_sumup_autoload;
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
 * Validate runtime requirements during plugin activation.
 */
function sutwc_activate(): void {
	if ( PHP_VERSION_ID >= SUTWC_MINIMUM_PHP_VERSION_ID ) {
		return;
	}

	deactivate_plugins( plugin_basename( __FILE__ ) );

	wp_die(
		esc_html(
			sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'SumUp Terminal for WooCommerce requires PHP %1$s or newer. Your server is running PHP %2$s.', 'sumup-terminal-for-woocommerce' ),
				SUTWC_MINIMUM_PHP_VERSION,
				PHP_VERSION
			)
		),
		esc_html__( 'Plugin activation failed', 'sumup-terminal-for-woocommerce' ),
		array( 'back_link' => true )
	);
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\sutwc_activate' );

/**
 * Initialize the plugin.
 */
function init(): void {
	// Register the gateway.
	add_filter( 'woocommerce_payment_gateways', array( Gateway::class, 'register_gateway' ) );

	// Initialize AJAX handlers early.
	new AjaxHandler();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init', 11 );
