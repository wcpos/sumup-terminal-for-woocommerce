<?php
/**
 * Extension-owned fixture for Pro's provider conformance suite: the real gateway, adapter and
 * services, with only SumUp's HTTP transport faked.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal\Tests\Conformance
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Tests\Conformance;

use WCPOS\WooCommercePOS\SumUpTerminal\Gateway;
use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
use WCPOS\WooCommercePOSPro\API\V2\Payments_Webhook_Controller;
use WCPOS\WooCommercePOSPro\Payments\Device\Device_Providers;
use WCPOS\WooCommercePOSPro\Payments\Server\Reader_Curation;
use WCPOS\WooCommercePOSPro\Payments\Server\Server_Providers;
use WCPOS\WooCommercePOSPro\Tests\Conformance\Conformance_Fixture;

require_once __DIR__ . '/Fake_SumUp_Transport.php';
require_once __DIR__ . '/Recording_SumUp_Provider.php';

/**
 * Capabilities claimed beyond the floor, and why:
 * - `non_idempotent_create`: SumUp's reader checkout has no idempotency key, the client transaction
 *   id is SumUp's and arrives only in the response, and the reader status reports no checkout; a
 *   lost create can neither be resumed nor be known paid, so a replay dispatches nothing and the
 *   lessons certify settlement through the return URL's webhook, an ended first checkout ending the
 *   leg, and a cancel terminating it.
 * - `refund_synchronous`: a SumUp refund answers 204 or a rejection; there is no pending state.
 * - `webhook_money_only`: SumUp's delivery is unsigned, so the adapter settles only money it has
 *   confirmed through the authenticated transaction lookup; a failure is left to polling, which
 *   alone can tell a terminate from a decline (FAILED covers both).
 * - `expiry`: through the adapter's own reader-finished rule (an empty lookup after the terminate
 *   grace, with the reader idle since the checkout began); the fake ages the checkout marker.
 * Not claimed: `cancel_final` (terminate is asynchronous and never final), `cancel_unsupported`,
 * `manual_capture`, `prompt`, `test_live_isolation` (one credential, no test mode).
 */
final class SumUp_Conformance_Fixture implements Conformance_Fixture {
	/**
	 * Scripted SumUp.
	 *
	 * @var Fake_SumUp_Transport
	 */
	public $transport;
	private $registry_property;
	private $old_registry;
	private $device_registry_property;
	private $old_device_registry;
	private $old_gateways;
	private $old_options;
	private $old_currency;
	private $calls = array();
	private $aliases = array();

	public function gateway_id(): string {
		return Settings::GATEWAY_ID;
	}

	public function install(): void {
		$this->old_options  = get_option( 'woocommerce_' . Settings::GATEWAY_ID . '_settings', array() );
		$this->old_currency = get_option( 'woocommerce_currency' );
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_' . Settings::GATEWAY_ID . '_settings', array( 'enabled' => 'yes', 'api_key' => 'sup_sk_conformance', 'wcpos_connection' => 'server' ) );
		delete_transient( 'sumup_profile_' . md5( 'sup_sk_conformance' ) );
		$this->transport                   = new Fake_SumUp_Transport();
		Recording_SumUp_Provider::$fixture = $this;
		add_filter( 'pre_http_request', array( $this->transport, 'handle' ), 10, 3 );
		$this->registry_property = new \ReflectionProperty( Server_Providers::class, 'instance' );
		$this->registry_property->setAccessible( true );
		$this->old_registry = $this->registry_property->getValue();
		$this->registry_property->setValue( null, null );
		wcpos_pro_register_server_provider( Settings::GATEWAY_ID, Recording_SumUp_Provider::class );
		// The plugin may have registered its device provider at boot; Pro routes a gateway with one
		// to device mode, and the server configuration under test has none.
		$this->device_registry_property = new \ReflectionProperty( Device_Providers::class, 'instance' );
		$this->device_registry_property->setAccessible( true );
		$this->old_device_registry = $this->device_registry_property->getValue();
		$this->device_registry_property->setValue( null, null );
		$this->old_gateways = WC()->payment_gateways;
		add_filter( 'woocommerce_payment_gateways', array( Gateway::class, 'register_gateway' ) );
		WC()->payment_gateways = new \WC_Payment_Gateways();
		Reader_Curation::forget( Settings::GATEWAY_ID );
		delete_option( 'wcpos_pro_readers_lkg_' . Settings::GATEWAY_ID );
	}

	public function uninstall(): void {
		remove_filter( 'pre_http_request', array( $this->transport, 'handle' ), 10 );
		Recording_SumUp_Provider::$fixture = null;
		Reader_Curation::forget( Settings::GATEWAY_ID );
		delete_option( 'wcpos_pro_readers_lkg_' . Settings::GATEWAY_ID );
		delete_transient( 'sumup_profile_' . md5( 'sup_sk_conformance' ) );
		$this->registry_property->setValue( null, $this->old_registry );
		$this->device_registry_property->setValue( null, $this->old_device_registry );
		WC()->payment_gateways = $this->old_gateways;
		update_option( 'woocommerce_' . Settings::GATEWAY_ID . '_settings', $this->old_options );
		update_option( 'woocommerce_currency', $this->old_currency );
	}

	public function supports( string $capability ): bool {
		return in_array( $capability, array( 'cancel', 'cancel_requested_then_completed', 'webhook', 'refund', 'partial_refund', 'expiry', 'legacy_adoption', 'historical_webview_refund', 'non_idempotent_create', 'refund_synchronous', 'webhook_money_only' ), true );
	}

	public function script( string $scenario ): void {
		if ( 'create_indeterminate_released' === $scenario ) {
			$this->transport->release();
			return;
		}
		// Scenario => states successive fetches observe, refund answer.
		$scripts = array(
			'create_ok'                       => array( array( 'created' ) ),
			'create_indeterminate'            => array( array( 'created' ) ),
			'create_indeterminate_void'       => array( array( 'created' ) ),
			'create_indeterminate_paid'       => array( array( 'paid_unlisted' ) ),
			'webhook_replay'                  => array( array( 'created' ) ),
			'webhook_out_of_order'            => array( array( 'created' ) ),
			'pending_then_completed'          => array( array( 'PENDING', 'SUCCESSFUL' ) ),
			'declined'                        => array( array( 'FAILED' ) ),
			'cancel_requested_then_cancelled' => array( array( 'FAILED' ) ),
			'cancel_requested_then_completed' => array( array( 'SUCCESSFUL' ) ),
			'amount_mismatch'                 => array( array( 'short' ) ),
			'currency_mismatch'               => array( array( 'usd' ) ),
			'expired'                         => array( array( 'expired' ) ),
			'refund_ok'                       => array( array( 'SUCCESSFUL' ), 'ok' ),
			'refund_failed'                   => array( array( 'SUCCESSFUL' ), 'failed' ),
		);
		if ( ! isset( $scripts[ $scenario ] ) ) {
			throw new \OutOfBoundsException( 'Unknown conformance scenario: ' . $scenario );
		}
		$this->transport->script( $scenario, ...$scripts[ $scenario ] );
	}

	public function webhook_request( string $event ): \WP_REST_Request {
		$tampered = 'tampered' === $event;
		$event    = $tampered ? 'completed' : $event;
		$states   = array( 'completed' => array( 'successful', 'SUCCESSFUL' ), 'failed' => array( 'failed', 'FAILED' ), 'cancelled' => array( 'cancelled', 'CANCELLED' ) );
		if ( ! isset( $states[ $event ] ) ) {
			throw new \OutOfBoundsException( 'Unknown webhook event: ' . $event );
		}
		$client = (string) $this->transport->current;
		$txn    = $this->transport->observe( $client, $states[ $event ][1] );
		// SumUp calls the return URL the checkout was created with; the body is its unsigned event.
		parse_str( (string) wp_parse_url( $this->transport->return_url( $client ), PHP_URL_QUERY ), $query );
		$body    = array( 'id' => 'evt_' . $client . '_' . $event, 'event_type' => 'solo.transaction.updated', 'timestamp' => gmdate( 'c' ), 'payload' => array( 'client_transaction_id' => $client, 'merchant_code' => $tampered ? 'MOTHER' : Fake_SumUp_Transport::MERCHANT, 'status' => $states[ $event ][0], 'transaction_id' => $txn['id'] ?? null ) );
		$request = new \WP_REST_Request( 'POST', Payments_Webhook_Controller::ROUTE );
		$request->set_query_params( array_intersect_key( $query, array_flip( array( 'provider', 'payment', 'reader' ) ) ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return $request;
	}

	/** One stable alias per provider checkout; a transaction id resolves to its checkout. */
	public function alias( string $ref ): string {
		$ref = $this->transport->client_id_for( $ref );
		if ( ! isset( $this->aliases[ $ref ] ) ) {
			$this->aliases[ $ref ] = 'action_' . ( count( $this->aliases ) + 1 );
		}
		return $this->aliases[ $ref ];
	}

	/** Append an adapter operation to the transcript. */
	public function record( string $op, string $ref, string $details ): void {
		$this->calls[] = array( 'op' => $op, 'request' => 'action=' . $this->alias( $ref ) . ' ' . $details );
	}

	public function transcript(): array {
		return $this->calls;
	}

	public function reset_transcript(): void {
		$this->calls   = array();
		$this->aliases = array();
	}

	public function transcript_dir(): ?string {
		return __DIR__ . '/transcripts';
	}
}
