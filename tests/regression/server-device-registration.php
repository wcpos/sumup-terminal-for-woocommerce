<?php
// phpcs:ignoreFile
$GLOBALS['without_device_contract'] = in_array( 'without-device', $argv, true );
require_once __DIR__ . '/stubs/pos-reader-settings.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Server\Registration;
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider;
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Device_Provider;
use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
function wcpos_pro_requires( $version ) { return true; }
function wcpos_pro_register_server_provider( $gateway, $class ) { $GLOBALS['registered'][] = array( $gateway, $class ); }
expect( method_exists( Settings::class, 'get_wcpos_connection' ), 'connection setting is missing' );
$registration = new ReflectionProperty( Registration::class, 'registered' );
if ( PHP_VERSION_ID < 80100 ) { $registration->setAccessible( true ); }
foreach ( array( array( array(), 'device' ), array( array( 'default_reader' => 'solo' ), 'server' ), array( array( 'default_reader' => '' ), 'device' ), array( array( 'wcpos_connection' => 'device', 'default_reader' => 'solo' ), 'device' ), array( array( 'wcpos_connection' => 'server' ), 'server' ) ) as $case ) {
	$options['woocommerce_' . Settings::GATEWAY_ID . '_settings'] = $case[0];
	$before = $options;
	expect( $case[1] === Settings::get_wcpos_connection(), 'explicit choice overrides default; default reader preserves server mode' );
	list( $gateway ) = settings_gateway();
	$field = $gateway->form_fields['wcpos_connection'];
	expect( 'select' === $field['type'] && 'POS connection' === $field['title'] && $case[1] === $field['default'], 'connection field uses same default as registration' );
	expect( array( 'device', 'server' ) === array_keys( $field['options'] ) && '' !== $field['description'], 'both connection modes offered' );
	$registered = array(); $device_registered = array();
	$registration->setValue( null, false );
	expect( Registration::register() && Registration::register(), 'registration succeeds repeatedly' );
	expect( array( array( Settings::GATEWAY_ID, SumUp_Server_Provider::class ) ) === $registered, 'server always registered exactly once' );
	$expected = 'device' === $case[1] && ! $GLOBALS['without_device_contract'] ? array( array( Settings::GATEWAY_ID, SumUp_Device_Provider::class ) ) : array();
	expect( $expected === $device_registered, 'device registered only when selected and contract available' );
	expect( $before === $options, 'mode selection performs no migrations or option writes' );
}
echo "server-device-registration ok\n";
