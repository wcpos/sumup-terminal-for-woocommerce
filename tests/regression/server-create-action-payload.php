<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/wcpos-pro-server.php';
require_once __DIR__ . '/stubs/sumup-server.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
list( $provider, $profile, $readers ) = server_fixture();
$GLOBALS['orders'][12] = new class {
	public function get_total() { return '999.99'; }
	public function get_order_number() { return 'WEB-12'; }
	public function __call( $method, $args ) { throw new RuntimeException( 'Unexpected order mutation: ' . $method ); }
};
$options['woocommerce_sumup_terminal_for_woocommerce_settings'] = array( 'api_key' => 'real', 'secret_key' => 'wrong' );
expect( 'real' === Settings::api_key(), 'read actual gateway credential' );
foreach ( array( array( 'eur', '12.30', 1230, 2 ), array( 'jpy', '123', 123, 0 ), array( 'huf', '123', 123, 0 ), array( 'kwd', '1.234', 1234, 3 ) ) as $case ) {
	$row = server_row(); $row['currency'] = $case[0]; $row['amount'] = $case[1];
	$result = $provider->create_reader_action( $row, 'reader' );
	expect( array( 'ref' => 'reader:client:123', 'expires_at' => null ) === $result, 'composite action reference' );
	$payload = end( $readers->checkouts )[1];
	expect( array( 'value' => $case[2], 'currency' => strtoupper( $case[0] ), 'minor_unit' => $case[3] ) === $payload['total_amount'], 'row money, not order total' );
	expect( 'Order #WEB-12' === $payload['description'], 'order number description' );
	parse_str( parse_url( $payload['return_url'], PHP_URL_QUERY ), $query );
	expect( array( 'provider' => 'sumup', 'payment' => $row['id'] ) === $query, 'result webhook routing' );
	expect( ! isset( $payload['affiliate'] ), 'no incomplete affiliate' );
}
foreach ( array( array( '', 'key' ), array( 'app', '' ), array( 'app', 'key' ) ) as $settings ) {
	$options['woocommerce_sumup_terminal_for_woocommerce_settings']['affiliate_app_id'] = $settings[0];
	$options['woocommerce_sumup_terminal_for_woocommerce_settings']['affiliate_key'] = $settings[1];
	$provider->create_reader_action( server_row(), 'reader' );
	$payload = end( $readers->checkouts )[1];
	if ( $settings[0] && $settings[1] ) { expect( array( 'app_id' => 'app', 'key' => 'key', 'foreign_transaction_id' => server_row()['id'] ) === $payload['affiliate'], 'affiliate attribution' ); }
	else { expect( ! isset( $payload['affiliate'] ), 'both affiliate settings required' ); }
}
foreach ( array( array(), false, new WP_Error( 'busy', 'Busy' ), new RuntimeException( 'bad' ) ) as $readers->checkout_result ) {
	provider_error_expect( $provider->create_reader_action( server_row(), 'reader' ), is_wp_error( $readers->checkout_result ) ? 'busy' : 'sumup_api_error' );
}
unset( $orders[12] );
$error = $provider->create_reader_action( server_row(), 'reader' );
expect( 'wcpos_order_not_found' === $error->get_error_code() && 404 === $error->get_error_data()['status'], 'missing order' );
echo "server-create-action-payload ok\n";
