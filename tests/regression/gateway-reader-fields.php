<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/pos-reader-settings.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
enable_pro();
foreach ( array( array( false, false, Settings::GATEWAY_ID, 'test' ), array( true, true, Settings::GATEWAY_ID, 'test' ), array( true, false, 'other', 'test' ), array( true, false, '', 'test' ), array( true, false, Settings::GATEWAY_ID, '' ) ) as $case ) {
	list( $GLOBALS['admin'], $GLOBALS['ajax'], $_GET['section'], $key ) = $case;
	$GLOBALS['options']['woocommerce_' . Settings::GATEWAY_ID . '_settings']['api_key'] = $key;
	list( $gateway, $readers ) = settings_gateway();
	expect( 0 === $readers->calls, 'no discovery outside configured gateway screen' );
}
$GLOBALS['options']['woocommerce_' . Settings::GATEWAY_ID . '_settings']['api_key'] = 'test';
$before = $GLOBALS['options'];
list( $gateway, $readers ) = settings_gateway( array(
	array( 'id' => 'one', 'name' => 'Till', 'status' => 'paired' ),
	array( 'id' => 'two', 'device' => array( 'model' => 'virtual-solo', 'identifier' => 'Virtual' ) ),
	array( 'id' => 'expired', 'status' => 'expired' ),
) );
$fields = $gateway->form_fields;
expect( isset( $fields['default_reader'], $fields['allowed_readers'], $fields['lock_to_default'] ), 'three reader fields exist' );
expect( 'select' === $fields['default_reader']['type'], 'loaded default is a select' );
expect( 'multiselect' === $fields['allowed_readers']['type'] && 'wc-enhanced-select' === $fields['allowed_readers']['class'], 'enhanced multiselect' );
expect( 'checkbox' === $fields['lock_to_default']['type'], 'lock checkbox' );
expect( array( 'one' => 'Till (one)', 'two' => 'Virtual (two)' ) === $fields['allowed_readers']['options'], 'provider projection reused, including Virtual Solo' );
$keys = array_keys( $fields );
expect( array_search( 'affiliate_key', $keys ) < array_search( 'default_reader', $keys ) && array_search( 'lock_to_default', $keys ) < array_search( 'show_payment_logs', $keys ), 'reader fields follow credentials' );
$gateway->init_form_fields();
expect( 1 === $readers->calls, 'request memoization' );
list( $cached, $cached_readers ) = settings_gateway();
expect( 0 === $cached_readers->calls && $fields === $cached->form_fields, 'transient cache hit' );
expect( array( 300 ) === array_values( $GLOBALS['ttls'] ), 'five-minute cache' );
expect( $before === $GLOBALS['options'], 'field reads do not mirror or migrate' );
$GLOBALS['options']['woocommerce_' . Settings::GATEWAY_ID . '_settings']['api_key'] = 'changed';
list( $changed, $changed_readers ) = settings_gateway();
expect( 1 === $changed_readers->calls, 'changed key does not reuse another account list' );
foreach ( array( false, new WP_Error( 'timeout', 'Timed out' ), new RuntimeException( 'Unavailable' ) ) as $failure ) {
	$GLOBALS['transients'] = array();
	list( $gateway, $readers ) = settings_gateway( $failure );
	expect( 'text' === $gateway->form_fields['default_reader']['type'] && ! isset( $gateway->form_fields['allowed_readers'] ), 'failure falls back without multiselect' );
	$gateway->init_form_fields();
	expect( 1 === $readers->calls, 'failed discovery memoized' );
	$GLOBALS['options']['woocommerce_' . Settings::GATEWAY_ID . '_settings']['allowed_readers'] = array( 'saved' );
	$GLOBALS['posted'] = array( 'api_key' => 'changed', 'default_reader' => 'saved' );
	$gateway->process_admin_options();
	expect( array( 'saved' ) === Settings::get_gateway_settings()['allowed_readers'], 'failed discovery save preserves enabled readers' );
}
echo "gateway-reader-fields ok\n";
