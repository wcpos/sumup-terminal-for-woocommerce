<?php
/**
 * A scripted SumUp behind WordPress's HTTP layer (`pre_http_request`): the merchant profile, two
 * readers, reader checkouts, terminate, reader status, the transactions lookup and refunds, as
 * SumUp's docs describe them. No idempotency on checkout; a reader busy with a live checkout
 * refuses a second one; a transaction is listed only once the customer has tapped.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal\Tests\Conformance
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Tests\Conformance;

/** Only the fixture knows provider internals; the suite observes the money path. */
final class Fake_SumUp_Transport {
	public const MERCHANT = 'MCONF';
	/** Every request as WordPress sent it (method, path, body), for debugging a transcript. */
	public $raw = array();
	/** The client transaction id of the latest checkout created or refused. */
	public $current;
	/** Whether the latest checkout request was refused because the reader was busy. */
	public $last_refused = false;
	private $checkouts = array();
	private $by_row = array();
	private $live = array();
	private $activity = array();
	private $readers = array();
	private $states = array( 'created' );
	private $scenario = 'create_ok';
	private $refund_status = 'ok';
	private $lost = false;
	private $seq = 0;

	public function __construct() {
		$this->readers = array(
			'rdr_online'  => array( 'id' => 'rdr_online', 'name' => 'Front counter', 'status' => 'paired', 'device' => array( 'identifier' => 'SOLO-1', 'model' => 'solo' ) ),
			'rdr_offline' => array( 'id' => 'rdr_offline', 'name' => 'Back office', 'status' => 'processing', 'device' => array( 'identifier' => 'SOLO-2', 'model' => 'solo' ) ),
		);
	}

	/** Arm a scenario: the states successive adapter fetches observe, and how a refund answers. */
	public function script( string $scenario, array $states, string $refund_status = 'ok' ): void {
		$this->scenario      = $scenario;
		$this->states        = $states;
		$this->refund_status = $refund_status;
		$this->lost          = false;
	}

	/**
	 * The reader dropped its live checkout without money (its own timeout): the reader is idle, no
	 * transaction will ever be listed, and the adapter's markers for the row (the unanswered
	 * checkout's start, and a terminate the store sent) are older than its grace.
	 */
	public function release(): void {
		foreach ( $this->live as $client ) {
			$this->checkouts[ $client ]['dropped'] = true;
			$row = array_search( $client, $this->by_row, true );
			if ( false !== $row ) {
				foreach ( array( 'checkout', 'terminated' ) as $marker ) {
					// The adapter keeps an unanswered checkout's markers as options.
					if ( false !== get_option( 'sutwc_' . $marker . '_' . md5( 'row-' . $row ), false ) ) {
						update_option( 'sutwc_' . $marker . '_' . md5( 'row-' . $row ), (string) ( microtime( true ) - 121 ), false );
					}
				}
			}
		}
		$this->live = array();
	}

	/** Called by the recording adapter on entry to fetch(): the checkout moves to its next scripted state. */
	public function advance( string $client ): void {
		if ( ! isset( $this->checkouts[ $client ] ) ) {
			return;
		}
		$entry = &$this->checkouts[ $client ];
		$state = count( $entry['states'] ) > 1 ? array_shift( $entry['states'] ) : $entry['states'][0];
		$this->apply_state( $entry, $state );
	}

	/** A provider-side outcome (what a delivery reports); returns the transaction as SumUp lists it, or null. */
	public function observe( string $client, string $state ): ?array {
		$this->apply_state( $this->checkouts[ $client ], $state );
		return $this->checkouts[ $client ]['txn'];
	}

	/** The checkout a create keyed on this row id put on the reader, even when its response was lost. */
	public function client_for_row( string $row_id ): ?string {
		return $this->by_row[ $row_id ] ?? null;
	}

	/** The return URL the adapter gave this checkout: SumUp delivers its result there. */
	public function return_url( string $client ): string {
		return (string) ( $this->checkouts[ $client ]['return_url'] ?? '' );
	}

	/**
	 * A transaction id resolves to its client id; the adapter's row-keyed id for an unanswered
	 * checkout resolves to that checkout; a client id is itself.
	 */
	public function client_id_for( string $ref ): string {
		if ( 0 === strpos( $ref, 'row-' ) ) {
			return $this->by_row[ substr( $ref, 4 ) ] ?? $ref;
		}
		foreach ( $this->checkouts as $client => $entry ) {
			if ( ( $entry['txn']['id'] ?? null ) === $ref ) {
				return $client;
			}
		}
		return $ref;
	}

	/**
	 * The `pre_http_request` filter.
	 *
	 * @param mixed  $pre  Short-circuit value.
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return mixed
	 * @throws \LogicException On a request no scenario expects.
	 */
	public function handle( $pre, array $args, string $url ) {
		if ( 'api.sumup.com' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
			return $pre;
		}
		$method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
		$path   = (string) wp_parse_url( $url, PHP_URL_PATH );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$body        = isset( $args['body'] ) && '' !== $args['body'] ? json_decode( (string) $args['body'], true ) : array();
		$this->raw[] = array( 'method' => $method, 'path' => $path, 'query' => $query, 'body' => $body );
		$m           = self::MERCHANT;

		if ( '/v0.1/me' === $path ) {
			return self::ok( array( 'merchant_profile' => array( 'merchant_code' => $m, 'currency' => 'EUR' ) ) );
		}
		if ( "/v0.1/merchants/$m/readers" === $path && 'GET' === $method ) {
			return self::ok( array( 'items' => array_values( $this->readers ) ) );
		}
		if ( preg_match( "#^/v0.1/merchants/$m/readers/([^/]+)/(checkout|terminate|status)$#", $path, $r ) ) {
			$reader = $r[1];
			if ( ! isset( $this->readers[ $reader ] ) ) {
				return self::error( 404, 'Reader not found.' );
			}
			if ( 'checkout' === $r[2] ) {
				$this->last_refused = false;
				$live               = $this->live[ $reader ] ?? null;
				if ( null !== $live && empty( $this->checkouts[ $live ]['dropped'] ) && ! $this->final( $live ) ) {
					// SumUp: after acceptance, any other checkout for the same device is rejected.
					$this->last_refused = true;
					$this->current      = $live;
					return self::error( 409, 'The reader is busy with another checkout.' );
				}
				$client = 'ctx_' . ( ++$this->seq );
				parse_str( (string) wp_parse_url( (string) ( $body['return_url'] ?? '' ), PHP_URL_QUERY ), $return_query );
				$this->checkouts[ $client ] = array(
					'reader'     => $reader,
					'states'     => $this->states,
					'return_url' => (string) ( $body['return_url'] ?? '' ),
					'amount'     => (int) ( $body['total_amount']['value'] ?? 0 ),
					'currency'   => (string) ( $body['total_amount']['currency'] ?? 'EUR' ),
					'state'      => 'created',
					'txn'        => null,
					'dropped'    => false,
					'affiliate'  => isset( $body['affiliate']['foreign_transaction_id'] ),
				);
				if ( ! empty( $return_query['payment'] ) ) {
					$this->by_row[ (string) $return_query['payment'] ] = $client;
				}
				$this->live[ $reader ]     = $client;
				$this->activity[ $reader ] = microtime( true );
				$this->current             = $client;
				if ( 0 === strpos( $this->scenario, 'create_indeterminate' ) && ! $this->lost ) {
					$this->lost = true; // Accepted, on the reader, and the response never arrives.
					return new \WP_Error( 'http_request_failed', 'Response lost after acceptance' );
				}
				return self::ok( array( 'data' => array( 'client_transaction_id' => $client, 'checkout_id' => 'chk_' . $this->seq ) ), 201 );
			}
			$live = $this->live[ $reader ] ?? null;
			$idle = null === $live || $this->final( $live );
			if ( 'terminate' === $r[2] ) {
				if ( $idle ) {
					return self::error( 422, 'The reader is not waiting for a card.' ); // Terminate works only during a checkout.
				}
				$this->activity[ $reader ] = microtime( true );
				return self::ok( '', 204 ); // Asynchronous: SumUp acknowledges, the reader reports the end later.
			}
			return self::ok( array( 'data' => array( 'state' => $idle ? 'IDLE' : 'WAITING_FOR_CARD', 'last_activity' => gmdate( 'c', (int) ( $this->activity[ $reader ] ?? time() ) ), 'status' => 'ONLINE' ) ) );
		}
		if ( "/v2.1/merchants/$m/transactions" === $path && isset( $query['foreign_transaction_id'] ) ) {
			// Only a checkout created with affiliate keys carries the foreign id, and only once paid
			// and listed does SumUp return it.
			$client = $this->by_row[ (string) $query['foreign_transaction_id'] ] ?? null;
			$entry  = null !== $client ? $this->checkouts[ $client ] : null;
			if ( null === $entry || empty( $entry['affiliate'] ) || null === $entry['txn'] ) {
				return self::error( 404, 'No transaction for this foreign transaction id.' );
			}
			return self::ok( array( 'items' => array( $entry['txn'] + array( 'foreign_transaction_id' => (string) $query['foreign_transaction_id'] ) ) ) );
		}
		if ( "/v2.1/merchants/$m/transactions" === $path ) {
			$client = (string) ( $query['client_transaction_id'] ?? '' );
			$entry  = $this->checkouts[ $client ] ?? null;
			if ( null === $entry || null === $entry['txn'] ) {
				if ( null !== $entry && 'expired' === $entry['state'] ) {
					// The checkout began longer ago than the adapter's terminate grace: the reader has
					// shown nothing for a while. The marker is the adapter's own transient.
					set_transient( 'sutwc_checkout_' . md5( $client ), microtime( true ) - 11, 15 * MINUTE_IN_SECONDS );
				}
				return self::error( 404, 'No transaction for this client transaction id.' );
			}
			return self::ok( array( 'items' => array( $entry['txn'] ) ) );
		}
		if ( preg_match( '#^/v0.1/me/refund/([^/]+)$#', $path, $r ) && 'POST' === $method ) {
			$client = $this->client_id_for( $r[1] );
			if ( ! isset( $this->checkouts[ $client ] ) || 'SUCCESSFUL' !== ( $this->checkouts[ $client ]['txn']['status'] ?? '' ) ) {
				return self::error( 404, 'Transaction not found.' );
			}
			if ( 'failed' === $this->refund_status ) {
				return self::error( 400, 'Rejected' );
			}
			return self::ok( '', 204 );
		}
		throw new \LogicException( 'Unexpected SumUp request: ' . $method . ' ' . $path );
	}

	/** Whether a checkout has ended on the reader (paid, listed or not yet; failed; cancelled; dropped). */
	private function final( string $client ): bool {
		$entry = $this->checkouts[ $client ];
		return $entry['dropped'] || in_array( $entry['state'], array( 'SUCCESSFUL', 'short', 'usd', 'FAILED', 'CANCELLED', 'expired', 'paid_unlisted' ), true );
	}

	/**
	 * Move a checkout to a named state.
	 *
	 * @param array  $entry Checkout entry, by reference.
	 * @param string $state created, PENDING, SUCCESSFUL, short, usd, FAILED, CANCELLED, expired,
	 *                      paid_unlisted (the customer paid; SumUp lists nothing yet; the reader is idle).
	 */
	private function apply_state( array &$entry, string $state ): void {
		$entry['state'] = $state;
		if ( 'created' !== $state ) {
			$this->activity[ $entry['reader'] ] = microtime( true );
		}
		switch ( $state ) {
			case 'created':
			case 'expired':
			case 'paid_unlisted':
				$entry['txn'] = null;
				break;
			case 'PENDING':
			case 'SUCCESSFUL':
			case 'short':
			case 'usd':
			case 'FAILED':
			case 'CANCELLED':
				$status = in_array( $state, array( 'short', 'usd' ), true ) ? 'SUCCESSFUL' : $state;
				$amount = 'short' === $state ? 1.0 : $entry['amount'] / 100;
				$id     = $entry['txn']['id'] ?? 'txn_' . ( ++$this->seq );
				$entry['txn'] = array( 'id' => $id, 'transaction_code' => 'TC' . strtoupper( substr( $id, 4 ) ), 'client_transaction_id' => array_search( $entry, $this->checkouts, true ) ?: $this->current_client( $entry ), 'status' => $status, 'amount' => $amount, 'currency' => 'usd' === $state ? 'USD' : $entry['currency'], 'card' => array( 'last_4_digits' => '4242', 'type' => 'VISA' ), 'entry_mode' => 'contactless', 'payment_type' => 'POS' );
				break;
			default:
				throw new \OutOfBoundsException( 'Unknown checkout state: ' . $state );
		}
	}

	/** The client id of an entry (entries are looked up by reference during a state change). */
	private function current_client( array $entry ): string {
		foreach ( $this->checkouts as $client => $candidate ) {
			if ( $candidate['return_url'] === $entry['return_url'] && $candidate['reader'] === $entry['reader'] ) {
				return $client;
			}
		}
		return (string) $this->current;
	}

	/** A WordPress HTTP response. */
	private static function ok( $body, int $status = 200 ): array {
		return array( 'headers' => array(), 'body' => is_string( $body ) ? $body : wp_json_encode( $body ), 'response' => array( 'code' => $status, 'message' => '' ), 'cookies' => array() );
	}

	/** A SumUp error envelope. */
	private static function error( int $status, string $message ): array {
		return self::ok( array( 'message' => $message ), $status );
	}
}
