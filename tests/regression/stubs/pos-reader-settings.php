<?php
// phpcs:ignoreFile
class WC_Payment_Gateway {
	public $id, $method_title, $method_description, $title, $description, $form_fields, $settings;
	public function init_settings() { $this->settings = get_option( 'woocommerce_' . $this->id . '_settings', array() ); }
	public function get_option( $key ) { return $this->settings[ $key ] ?? ''; }
	public function get_field_key( $key ) { return 'woocommerce_' . $this->id . '_' . $key; }
	public function generate_multiselect_html( $key, $data ) { return '<select multiple name="' . $this->get_field_key( $key ) . '[]"></select>'; }
	public function validate_multiselect_field( $key, $value ) { return is_array( $value ) ? $value : ''; }
	public function process_admin_options() {
		$this->init_settings();
		foreach ( $this->form_fields as $key => $field ) {
			$this->settings[ $key ] = is_callable( array( $this, 'validate_' . $key . '_field' ) ) ? $this->{'validate_' . $key . '_field'}( $key, $GLOBALS['posted'][ $key ] ?? null ) : ( $GLOBALS['posted'][ $key ] ?? ( 'multiselect' === $field['type'] ? array() : ( 'checkbox' === $field['type'] ? 'no' : '' ) ) );
		}
		return update_option( 'woocommerce_' . $this->id . '_settings', $this->settings );
	}
}
require_once __DIR__ . '/sumup-server.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Gateway;
use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService;
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function is_admin() { return $GLOBALS['admin']; }
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return $value; }
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['hooks'][$hook][$priority][] = $callback; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; $GLOBALS['ttls'][ $key ] = $ttl; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function enable_pro() {
	function wcpos_pro_requires( $version ) { return $GLOBALS['pro']; }
	function wcpos_pro_register_server_provider( $id, $class ) {}
	$GLOBALS['pro'] = true;
}
class SettingsReaders extends ServerReaders {
	public $calls = 0;
	public function get_all() { ++$this->calls; return parent::get_all(); }
}
function settings_gateway( $result = array() ) {
	$gateway = ( new ReflectionClass( Gateway::class ) )->newInstanceWithoutConstructor();
	$gateway->id = Settings::GATEWAY_ID;
	$gateway->init_settings();
	$readers = new SettingsReaders();
	$readers->result = $result;
	foreach ( array( 'api_key' => Settings::api_key(), 'profile_service' => new ServerProfile(), 'reader_service' => new ReaderService( 'test', $readers ) ) as $key => $value ) {
		$property = new ReflectionProperty( Gateway::class, $key );
		if ( PHP_VERSION_ID < 80100 ) { $property->setAccessible( true ); }
		$property->setValue( $gateway, $value );
	}
	$gateway->init_form_fields();
	return array( $gateway, $readers );
}
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['admin'] = true;
$GLOBALS['ajax'] = false;
$GLOBALS['options'] = array( 'woocommerce_' . Settings::GATEWAY_ID . '_settings' => array( 'api_key' => 'test' ) );
