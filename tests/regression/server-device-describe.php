<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/sumup-server.php';
use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Device_Provider;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\TransactionService;
use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
expect( class_exists( SumUp_Device_Provider::class ), 'device adapter is missing' );
function wp_remote_request( $url, $args ) { $GLOBALS['requests'][] = array( $url, $args ); return array( 'response' => array( 'code' => 404 ), 'body' => '' ); }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
$options['woocommerce_' . Settings::GATEWAY_ID . '_settings'] = array( 'api_key' => 'test', 'affiliate_app_id' => 'app', 'affiliate_key' => 'affiliate-test' );
$profile = new ServerProfile();
$provider = new SumUp_Device_Provider( new TransactionService( 'test' ), null, $profile );
$gateway = new WC_Payment_Gateway();
$described = $provider->describe( $gateway );
expect( 'sumup' === $provider->provider(), 'SumUp provider identity' );
expect( array( 'discovery' => 'sdk', 'transports' => array( array( 'transport' => 'bluetooth', 'offline' => 'none', 'tips' => 'on_reader' ) ) ) === $described['hardware'], 'SDK discovers Bluetooth readers' );
expect( array( 'tips' => 'on_reader', 'offline' => 'none', 'refunds' => array( 'via' => 'provider', 'partial' => true ) ) === $described['capabilities'], 'device capabilities' );
expect( array( 'merchant_code' => 'M123', 'affiliate_app_id' => 'app' ) === $described['provider_data'], 'public metadata excludes affiliate key' );
expect( array( 'handoff' => array( 'affiliate_key' => 'affiliate-test', 'merchant_code' => 'M123' ), 'expires_at' => null ) === $provider->bootstrap( $gateway, array() ), 'SDK bootstrap credentials' );
foreach ( array( false, new WP_Error( 'unavailable', 'Unavailable' ), new RuntimeException( 'Unavailable' ) ) as $profile->result ) {
	expect( null === $provider->describe( $gateway )['provider_data']['merchant_code'], 'unknown merchant is null' );
	expect( null === $provider->bootstrap( $gateway, array() )['handoff']['merchant_code'], 'bootstrap tolerates unknown merchant' );
}
$profile->result = 'M123';
$options['woocommerce_' . Settings::GATEWAY_ID . '_settings']['affiliate_key'] = '';
$options['woocommerce_' . Settings::GATEWAY_ID . '_settings']['affiliate_app_id'] = '';
expect( null === $provider->describe( $gateway )['provider_data']['affiliate_app_id'], 'empty app ID is null' );
$error = $provider->bootstrap( $gateway, array() );
expect( is_wp_error( $error ) && 'sumup_affiliate_missing' === $error->get_error_code() && 409 === $error->get_error_data()['status'], 'missing affiliate refuses bootstrap' );
$requests = array();
expect( array( 'ref' => 'payment:123', 'handoff' => array( 'foreign_transaction_id' => 'payment:123' ) ) === $provider->create_intent( array( 'id' => 'payment:123' ), array() ), 'intent correlates the SDK checkout' );
expect( 'final' === $provider->cancel( 'payment:123' ) && array() === $requests, 'intent and cancel do not call SumUp' );
$error = $provider->capture( 'payment:123' );
expect( is_wp_error( $error ) && 'wcpos_capture_mode_unsupported' === $error->get_error_code() && 501 === $error->get_error_data()['status'], 'capture remains unsupported' );
$pending = $provider->fetch( 'payment:123' );
expect( 'pending' === $pending['status'] && 'payment:123' === $pending['payment_id'] && null === $pending['amount'] && null === $pending['currency'] && array( 'foreign_transaction_id' => 'payment:123' ) === $pending['provider_refs'] && array() === $pending['receipt'] && null === $pending['failure_reason'], '404 is the complete pending observation' );
expect( 'https://api.sumup.com/v2.1/merchants/M123/transactions?foreign_transaction_id=payment%3A123' === $requests[0][0] && 'GET' === $requests[0][1]['method'], 'merchant-scoped foreign ID lookup' );
echo "server-device-describe ok\n";
