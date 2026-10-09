<?php
/**
 * The real adapter with each operation recorded on the fixture's transcript: the lessons count
 * what Pro asked the adapter to do, not the wire messages behind it.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal\Tests\Conformance
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Tests\Conformance;

use WCPOS\WooCommercePOS\SumUpTerminal\Server\SumUp_Server_Provider;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\WordPressHttpReaderApiClient;
use WCPOS\WooCommercePOS\SumUpTerminal\Settings;

/**
 * Registered in place of SumUp_Server_Provider for the suite. The WordPress reader client is
 * injected: it is the client every PHP 7.4 site uses, and on PHP 8.2+ the SDK client wraps the
 * same calls with it as fallback; the scripted SumUp sits behind `pre_http_request`.
 * SumUp has one credential (no test mode), so every op records `mode=live`.
 */
final class Recording_SumUp_Provider extends SumUp_Server_Provider {
	/**
	 * The installed fixture; set before Pro constructs the adapter.
	 *
	 * @var SumUp_Conformance_Fixture|null
	 */
	public static $fixture;

	public function __construct() {
		$key = Settings::api_key();
		parent::__construct( null, new ReaderService( $key, new WordPressHttpReaderApiClient( $key ) ), null, null );
	}

	/** {@inheritDoc} */
	public function create_reader_action( array $row, string $reader_id ) {
		$transport = self::$fixture->transport;
		$before    = count( $transport->raw );
		$result    = parent::create_reader_action( $row, $reader_id );
		$client    = (string) ( $transport->client_for_row( $row['id'] ) ?? $transport->current ?? '' );
		// A replay of an unanswered create sends nothing: SumUp was not asked.
		self::$fixture->record( 'create', $client, 'amount=' . $row['amount'] . ' currency=' . $row['currency'] . ' reader=' . $reader_id . ' mode=live' . ( count( $transport->raw ) === $before ? ' replay=held' : '' ) );
		return $result;
	}

	/** {@inheritDoc} */
	public function fetch( string $ref ) {
		$client = self::client( $ref );
		self::$fixture->transport->advance( $client );
		$result = parent::fetch( $ref );
		self::$fixture->record( 'fetch', $client, 'mode=live' );
		return $result;
	}

	/** {@inheritDoc} */
	public function cancel( string $ref ) {
		$result = parent::cancel( $ref );
		self::$fixture->record( 'cancel', self::client( $ref ), 'mode=live' );
		return $result;
	}

	/** {@inheritDoc} */
	public function refund( array $row, int $refund_id, string $amount ) {
		$result = parent::refund( $row, $refund_id, $amount );
		$ref    = (string) ( $row['provider_refs']['action'] ?? '' );
		$client = '' !== $ref ? self::client( $ref ) : (string) ( $row['provider_refs']['sumup_client_transaction'] ?? $row['provider_refs']['transaction_id'] ?? '' );
		self::$fixture->record( 'refund', $client, 'amount=' . $amount . ' currency=' . $row['currency'] . ' mode=live transaction_id=' . self::$fixture->alias( $client ) );
		return $result;
	}

	/** {@inheritDoc} */
	public function verify_webhook( \WP_REST_Request $request ) {
		$event = $request->get_json_params();
		self::$fixture->record( 'webhook', (string) ( $event['payload']['client_transaction_id'] ?? '' ), 'event=' . (string) ( $event['event_type'] ?? '' ) . ' status=' . (string) ( $event['payload']['status'] ?? '' ) );
		return parent::verify_webhook( $request );
	}

	/** The checkout behind a `reader:client` action reference, the adapter's row-keyed id included. */
	private static function client( string $ref ): string {
		return self::$fixture->transport->client_id_for( explode( ':', $ref, 2 )[1] ?? $ref );
	}
}
