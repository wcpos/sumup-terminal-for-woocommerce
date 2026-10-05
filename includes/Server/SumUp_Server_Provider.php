<?php
/**
 * SumUp cloud transport for Pro; Free owns settlement and replay guards.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Server;

use WCPOS\WooCommercePOS\SumUpTerminal\Logger;
use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ProfileService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\TransactionService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\RefundClient as Refund_Client;
use WCPOS\WooCommercePOSPro\Payments\Server\Money_Units;

/** Solo provider, independent of the legacy order-pay flow. */
class SumUp_Server_Provider extends \WCPOS\WooCommercePOSPro\Payments\Server\Abstract_Provider_Adapter {
	/**
	 * How long after our terminate an empty transaction lookup is trusted as "cancelled".
	 * Long enough for a tap that raced the terminate to become a transaction SumUp lists;
	 * short enough that the cashier sees the cancel confirmed, not the five-minute deadline.
	 * Waived once SumUp's own delivery saying the checkout ended without money (`failed` or
	 * `cancelled`) has arrived after the terminate (`reader_finished()`): the reader has then
	 * demonstrably dropped it, and the cashier sees the cancel confirmed on the next poll,
	 * about three seconds after the tap.
	 */
	public const TERMINATE_GRACE_SECONDS = 10;

	/** Merchant profile.
	 *
	 * @var ProfileService
	 */
	private $profile;
	/** Reader transport.
	 *
	 * @var ReaderService
	 */
	private $readers;
	/** Transaction transport.
	 *
	 * @var TransactionService
	 */
	private $transactions;
	/** Refund transport.
	 *
	 * @var Refund_Client
	 */
	private $refunds;
	/** Construction failure, returned by operations.
	 *
	 * @var \WP_Error|null
	 */
	private $initialization_error;

	/**
	 * Build the same lazily resolved services as legacy AJAX, without order writes.
	 *
	 * @param ProfileService|null     $profile Merchant profile.
	 * @param ReaderService|null      $readers Reader transport.
	 * @param TransactionService|null $transactions Transaction transport.
	 * @param Refund_Client|null      $refunds Refund transport.
	 */
	public function __construct( ?ProfileService $profile = null, ?ReaderService $readers = null, ?TransactionService $transactions = null, ?Refund_Client $refunds = null ) {
		try {
			$key = Settings::api_key();
			$this->profile = $profile ?? new ProfileService( $key );
			$this->readers = $readers ?? new ReaderService( $key );
			$this->transactions = $transactions ?? new TransactionService( $key );
			$this->refunds = $refunds ?? new Refund_Client( $key );
			$this->readers->set_profile_service( $this->profile );
			$this->transactions->set_profile_service( $this->profile );
		} catch ( \Throwable $e ) {
			$this->initialization_error = self::error( $e );
		}
	}

	/** {@inheritDoc} */
	public function provider(): string {
		return 'sumup';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WC_Payment_Gateway $gateway Gateway instance.
	 */
	public function describe( \WC_Payment_Gateway $gateway ): array {
		$merchant = $this->call(
			function () {
				return $this->profile->get_merchant_code();
			}
		);
		// Pro requires an array here; unavailable optional metadata is null, not an error.
		return array(
			'capabilities' => array(
				'tips' => 'none',
				'refunds' => array(
					'via' => 'provider',
					'partial' => true,
				),
				'void' => true,
			),
			'provider_data' => array( 'merchant_code' => is_wp_error( $merchant ) ? null : $merchant ),
		);
	}

	/** {@inheritDoc} */
	public function list_readers() {
		return $this->call(
			function () {
				$response = $this->readers->get_all();
				if ( false === $response || is_wp_error( $response ) ) {
					return self::error( $response );
				}
				$readers = array();
				foreach ( $response as $reader ) {
					if ( 'expired' === ( $reader['status'] ?? '' ) || empty( $reader['id'] ) ) {
						continue;
					}
					$readers[] = array(
						'id' => $reader['id'],
						'label' => ! empty( $reader['name'] ) ? $reader['name'] : ( ! empty( $reader['device']['identifier'] ) ? $reader['device']['identifier'] : $reader['id'] ),
						'status' => 'paired' === ( $reader['status'] ?? '' ) ? 'online' : 'offline',
					);
				}
				return $readers;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array  $row Ledger row.
	 * @param string $reader_id Selected reader.
	 */
	public function create_reader_action( array $row, string $reader_id ) {
		return $this->call(
			function () use ( $row, $reader_id ) {
				$order = wc_get_order( (int) $row['order_id'] );
				if ( ! $order ) {
					return new \WP_Error( 'wcpos_order_not_found', __( 'Order not found.', 'sumup-terminal-for-woocommerce' ), array( 'status' => 404 ) );
				}
				$currency = strtoupper( $row['currency'] );
				$fraction = explode( '.', Money_Units::major( 1, $currency ) );
				$payload = array(
					'total_amount' => array(
						'value' => Money_Units::minor( $row['amount'], $currency ),
						'currency' => $currency,
						'minor_unit' => strlen( $fraction[1] ?? '' ),
					),
					'description' => sprintf( 'Order #%s', $order->get_order_number() ),
					'return_url' => self::webhook_url( $row['id'] ),
				);
				$affiliate = Settings::affiliate();
				if ( '' !== $affiliate['app_id'] && '' !== $affiliate['key'] ) {
					$payload['affiliate'] = $affiliate + array( 'foreign_transaction_id' => $row['id'] );
				}
				$result = $this->readers->checkout( $reader_id, $payload );
				if ( false === $result ) {
					// A refused checkout (422: a currency this merchant cannot take) must reach the
					// till with SumUp's words and its 4xx, so the app stops instead of retrying;
					// an outage (5xx, no response) stays a 502 the app retries as transport.
					$last = $this->readers->last_error();
					$data = $last ? $last->get_error_data() : null;
					$http = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
					return self::error( $last ?? false, 'sumup_api_error', $http >= 400 && $http < 500 ? $http : 502 );
				}
				if ( is_wp_error( $result ) ) {
					return self::error( $result );
				}
				if ( empty( $result['data']['client_transaction_id'] ) ) {
					return self::error( 'SumUp checkout did not return a client transaction ID.' );
				}
				// When the reader later reports itself idle, fetch() needs to know the checkout
				// predates that idleness (see fetch()).
				self::remember( 'checkout', $result['data']['client_transaction_id'] );
				return array(
					'ref' => $reader_id . ':' . $result['data']['client_transaction_id'],
					'expires_at' => null,
				);
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $ref Reader and client transaction ID.
	 */
	public function fetch( string $ref ) {
		return $this->call(
			function () use ( $ref ) {
				list( $reader_id, $client_id ) = array_pad( explode( ':', $ref, 2 ), 2, '' );
				$transaction = $this->lookup( $client_id );
				if ( is_wp_error( $transaction ) ) {
					return $transaction;
				}
				$observation = self::normalize( $transaction );
				// SumUp records no transaction for a checkout nobody paid: a physical Solo that
				// was terminated from the till, or that gave up on its own after about a minute,
				// leaves the lookup empty for good, and the till would spin until the five-minute
				// deadline (first physical run, 2026-10-05). The evidence that it is over is the
				// authenticated reader status: the reader reports itself IDLE with a last_activity
				// after the checkout began (or after our terminate). Only an EMPTY lookup qualifies
				// — any transaction, in any state, is the customer's and wins — and only after a
				// grace period, so a tap that raced the end has time to become a transaction.
				if ( array() === $transaction && $this->reader_finished( $reader_id, $client_id ) ) {
					$observation['status'] = 'cancelled';
					// Nobody paid and nobody pressed cancel on the device (that leaves a FAILED
					// transaction): the reader timed out. The till ignores the reason when it asked
					// for the cancel itself (that row is voided), so this only ever names a timeout.
					$observation['failure_reason'] = 'expired';
				}
				return $observation;
			}
		);
	}

	/**
	 * Remember that the store asked SumUp to terminate this checkout.
	 *
	 * Only our own terminate counts: an unsigned `failed` delivery must never end a payment,
	 * because a late SUCCESSFUL after a failed row is ignored and the money would be lost.
	 *
	 * @param string $client_id SumUp client transaction ID.
	 */
	private static function remember_terminated( string $client_id ): void {
		self::remember( 'terminated', $client_id );
	}

	/**
	 * Remember when this checkout began, when the store terminated it, or when SumUp delivered
	 * that it ended without money.
	 *
	 * @param string $what      `checkout`, `terminated` or `ended`.
	 * @param string $client_id SumUp client transaction ID.
	 */
	private static function remember( string $what, string $client_id ): void {
		if ( '' === $client_id ) {
			return;
		}
		// Sub-second, so a delivery and a terminate in the same second keep their order.
		if ( ! set_transient( self::marker_key( $what, $client_id ), microtime( true ), 15 * MINUTE_IN_SECONDS ) ) {
			// Without the marker the poll keeps waiting and the deadline voids the leg, as before
			// these markers existed: slower for the cashier, never wrong about money.
			Logger::log( "SumUp $what marker for $client_id could not be written; the cancel confirms at the deadline." );
		}
	}

	/**
	 * Whether the reader has finished with this checkout without a transaction: the checkout (or
	 * our terminate) is older than the grace period, and the authenticated reader status says the
	 * device is IDLE with activity since then. A reader still showing the amount, a status call
	 * that fails, or activity older than the checkout all mean "still waiting".
	 *
	 * The grace covers a tap that raced the terminate: the reader goes IDLE having taken the money,
	 * and SumUp lists the transaction a moment later. A SumUp delivery for this checkout saying it
	 * ended without money (`failed`/`cancelled`), arriving after our terminate, closes that race —
	 * a successful or unknown delivery never writes the marker — so the grace is skipped and only
	 * the reader status decides. The delivery is unsigned, which is why it never decides the
	 * outcome itself — it only brings the authenticated check forward.
	 *
	 * @param string $reader_id SumUp reader ID.
	 * @param string $client_id SumUp client transaction ID.
	 */
	private function reader_finished( string $reader_id, string $client_id ): bool {
		if ( '' === $reader_id || '' === $client_id ) {
			return false;
		}
		$started = get_transient( self::marker_key( 'checkout', $client_id ) );
		$terminated = get_transient( self::marker_key( 'terminated', $client_id ) );
		if ( false === $started && false === $terminated ) {
			return false;
		}
		// The grace runs from the latest thing that happened; the reader's activity is measured
		// from the checkout's start, because terminating a checkout the reader already dropped
		// (its own timeout) produces no new activity on the device.
		$latest = max( (int) $started, (int) $terminated );
		$since  = false !== $started ? (int) $started : (int) $terminated;
		$ended  = get_transient( self::marker_key( 'ended', $client_id ) );
		$reported = false !== $terminated && false !== $ended && (float) $ended > (float) $terminated;
		if ( ! $reported && time() - $latest < self::TERMINATE_GRACE_SECONDS ) {
			return false;
		}
		$status = $this->readers->get_status( $reader_id );
		if ( ! is_array( $status ) ) {
			return false;
		}
		$state    = $status['data']['state'] ?? $status['state'] ?? '';
		$activity = strtotime( (string) ( $status['data']['last_activity'] ?? $status['last_activity'] ?? '' ) );
		return 'IDLE' === strtoupper( (string) $state ) && false !== $activity && $activity >= $since;
	}

	/**
	 * Transient key for a checkout marker.
	 *
	 * @param string $what      `checkout`, `terminated` or `ended`.
	 * @param string $client_id SumUp client transaction ID.
	 */
	private static function marker_key( string $what, string $client_id ): string {
		return 'sutwc_' . $what . '_' . md5( $client_id );
	}

	/**
	 * Pure projection of an observation; missing transactions remain pending.
	 *
	 * @param array|false $transaction Transaction or empty observation.
	 * @return array Normalized observation.
	 */
	public static function normalize( $transaction ): array {
		$transaction = is_array( $transaction ) ? $transaction : array();
		// SumUp reports a terminated checkout and a declined card identically (FAILED, no reason),
		// so both become `cancelled`: Pro's Status_Map turns that into `voided` when the till asked
		// for the cancel and into a cancelled failure otherwise, which is what the device showed.
		$states = array(
			'PENDING' => 'in_progress',
			'SUCCESSFUL' => 'completed',
			'FAILED' => 'cancelled',
			'CANCELLED' => 'cancelled',
		);
		$currency = ! empty( $transaction['currency'] ) ? strtoupper( $transaction['currency'] ) : null;
		$result = array(
			'status' => $states[ $transaction['status'] ?? '' ] ?? 'pending',
			// FAILED is a declined card OR a cancel on the reader; the till keeps `voided` when it
			// asked for the cancel, and otherwise reads this reason instead of "cancelled".
			'failure_reason' => 'FAILED' === ( $transaction['status'] ?? '' ) ? 'declined_or_cancelled' : null,
			'amount' => isset( $transaction['amount'], $currency ) ? Money_Units::major( Money_Units::minor( (string) $transaction['amount'], $currency ), $currency ) : null,
			'currency' => $currency,
			'provider_refs' => array(
				'sumup_transaction' => $transaction['id'] ?? null,
				'sumup_transaction_code' => $transaction['transaction_code'] ?? null,
				'sumup_client_transaction' => $transaction['client_transaction_id'] ?? null,
			),
			'receipt' => array_filter(
				array(
					'card_last4' => $transaction['card']['last_4_digits'] ?? null,
					'card_type' => $transaction['card']['type'] ?? null,
					'entry_mode' => $transaction['entry_mode'] ?? null,
					'payment_type' => $transaction['payment_type'] ?? null,
					'transaction_code' => $transaction['transaction_code'] ?? null,
				),
				static function ( $value ) {
					return is_string( $value ) && '' !== $value;
				}
			),
		);
		return $result;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $ref Reader and client transaction ID.
	 */
	public function cancel( string $ref ) {
		return $this->call(
			function () use ( $ref ) {
				$parts = explode( ':', $ref, 2 );
				$transaction = $this->lookup( $parts[1] ?? '' );
				if ( is_wp_error( $transaction ) ) {
					return $transaction;
				}
				$status = $transaction['status'] ?? '';
				if ( 'SUCCESSFUL' === $status ) {
					return 'requested';
				}
				if ( in_array( $status, array( 'FAILED', 'CANCELLED' ), true ) ) {
					return 'final';
				}
				$result = $this->readers->cancel_checkout( $parts[0] );
				// Terminate is asynchronous; acceptance never proves that money was not taken.
				if ( false === $result || is_wp_error( $result ) ) {
					return self::error( $result );
				}
				// The next poll confirms the cancel when SumUp still has no transaction (see fetch()).
				self::remember_terminated( $parts[1] ?? '' );
				return 'requested';
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array  $row Ledger row.
	 * @param int    $refund_id WooCommerce refund ID.
	 * @param string $amount Decimal major units.
	 */
	public function refund( array $row, int $refund_id, string $amount ) {
		return $this->call(
			function () use ( $row, $amount ) {
				$ref = $row['provider_refs']['action'] ?? '';
				$id = '' !== $ref ? ( explode( ':', $ref, 2 )[1] ?? '' ) : ( $row['provider_refs']['sumup_client_transaction'] ?? '' );
				$transaction = $this->lookup( $id );
				if ( is_wp_error( $transaction ) ) {
					return $transaction;
				}
				if ( 'SUCCESSFUL' !== ( $transaction['status'] ?? '' ) || empty( $transaction['id'] ) ) {
					return self::error( 'No successful SumUp transaction found for refund.', 'transaction_not_found' );
				}
				$currency = strtoupper( $transaction['currency'] ?? $row['currency'] );
				$full = isset( $transaction['amount'] ) && Money_Units::minor( $amount, $currency ) === Money_Units::minor( (string) $transaction['amount'], $currency );
				// SumUp has no refund idempotency: Free's row + refund-id replay guard is the only one.
				$result = $this->refunds->refund( $transaction['id'], $full ? null : (float) $amount );
				return false === $result || is_wp_error( $result ) ? self::error( $result ) : array(
					'status' => 'succeeded',
					'provider_ref' => $transaction['id'],
				);
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_REST_Request $request Unsigned SumUp delivery.
	 */
	public function verify_webhook( \WP_REST_Request $request ) {
		return $this->call(
			function () use ( $request ) {
				$id = $request->get_query_params()['payment'] ?? '';
				if ( ! is_string( $id ) || ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id ) ) {
					return new \WP_Error( 'sumup_webhook_unknown_payment', 'Unknown POS payment.', array( 'status' => 200 ) );
				}
				$event = $request->get_json_params();
				if ( 'solo.transaction.updated' !== ( $event['event_type'] ?? '' ) ) {
					return new \WP_Error( 'sumup_webhook_ignored', 'Event type not handled.', array( 'status' => 200 ) );
				}
				$merchant = $this->call(
					function () {
						return $this->profile->get_merchant_code();
					}
				);
				if ( is_wp_error( $merchant ) ) {
					return self::error( $merchant );
				}
				if ( ( $event['payload']['merchant_code'] ?? null ) !== $merchant ) {
					return new \WP_Error( 'sumup_webhook_merchant_mismatch', 'SumUp merchant mismatch.', array( 'status' => 403 ) );
				}
				$client_id = $event['payload']['client_transaction_id'] ?? '';
				if ( ! is_string( $client_id ) || '' === $client_id ) {
					return new \WP_Error( 'sumup_webhook_invalid', 'Missing client transaction ID.', array( 'status' => 400 ) );
				}
				// SumUp says the checkout ended without money: the poll may confirm our terminate at
				// once. A successful (or unknown) delivery must not — SumUp may not list the
				// transaction yet, and an IDLE reader would read as cancelled while the money moved.
				if ( in_array( strtolower( (string) ( $event['payload']['status'] ?? '' ) ), array( 'failed', 'cancelled' ), true ) ) {
					self::remember( 'ended', $client_id );
				}
				// The authenticated lookup, never the unsigned body, is the money evidence.
				$transaction = $this->lookup( $client_id );
				return is_wp_error( $transaction ) ? $transaction : array(
					'payment_id' => strtolower( $id ),
					'patch' => self::webhook_patch( $event, $transaction ),
				);
			}
		);
	}

	/**
	 * Polling owns non-money outcomes; settlement must preserve Pro's references.
	 *
	 * @param array       $event SumUp event.
	 * @param array|false $transaction Authoritative transaction.
	 * @return array Settlement patch, never provider_refs.
	 */
	public static function webhook_patch( array $event, $transaction ): array {
		$payload = $event['payload'];
		// A delivery may omit fields; treat a missing status as a non-money event.
		$status = strtolower( (string) ( $payload['status'] ?? '' ) );
		$patch = array( 'event_id' => ! empty( $event['id'] ) ? $event['id'] : $payload['client_transaction_id'] . ':' . ( '' !== $status ? $status : 'unknown' ) );
		if ( 'successful' === $status && 'SUCCESSFUL' === ( $transaction['status'] ?? '' ) && ( $transaction['client_transaction_id'] ?? null ) === $payload['client_transaction_id'] ) {
			$normalized = self::normalize( $transaction );
			$patch += array(
				'status' => 'captured',
				'amount' => $normalized['amount'],
				'currency' => $normalized['currency'],
				'receipt' => $normalized['receipt'],
			);
		}
		return $patch;
	}

	/**
	 * Build the shared result URL, separate from legacy admin-ajax.
	 *
	 * @param string $payment_id Ledger UUID.
	 * @return string Result webhook URL.
	 */
	public static function webhook_url( string $payment_id ): string {
		return add_query_arg(
			array(
				'provider' => 'sumup',
				'payment' => $payment_id,
			),
			rest_url( 'wcpos/v2/payments/webhook' )
		);
	}

	/**
	 * Select only the requested transaction from either API response shape.
	 *
	 * @param string $client_id Client transaction ID.
	 * @return array|\WP_Error Matching transaction, empty observation or transport error.
	 */
	private function lookup( string $client_id ) {
		$response = $this->call(
			function () use ( $client_id ) {
				return $this->transactions->find_by_client_transaction_id( $client_id );
			}
		);
		if ( is_wp_error( $response ) ) {
			return self::error( $response );
		}
		if ( null === $response ) {
			// SumUp has no transaction for this checkout yet: still waiting on the device.
			return array();
		}
		foreach ( $response['items'] ?? array( $response ) as $transaction ) {
			if ( '' !== $client_id && ( $transaction['client_transaction_id'] ?? null ) === $client_id ) {
				return $transaction;
			}
		}
		return array();
	}

	/**
	 * Contain service failures while preserving intentional adapter errors.
	 *
	 * @param callable $operation Provider operation.
	 * @return mixed Result or provider error.
	 */
	private function call( callable $operation ) {
		if ( $this->initialization_error ) {
			return $this->initialization_error;
		}
		try {
			$result = $operation();
			if ( false === $result ) {
				return self::error( 'SumUp API request failed.' );
			}
			return $result;
		} catch ( \Throwable $e ) {
			return self::error( $e );
		}
	}

	/**
	 * Preserve provider detail inside Pro's shared error envelope.
	 *
	 * @param \WP_Error|\Throwable|string $error Service failure.
	 * @param string                      $code Local error code.
	 * @param int                         $status HTTP status for the envelope: 502 is retried by the till as
	 *                                            transport; a 4xx SumUp answered with is final.
	 * @return \WP_Error Provider error.
	 */
	private static function error( $error, string $code = 'sumup_api_error', int $status = 502 ): \WP_Error {
		if ( is_wp_error( $error ) ) {
			if ( 'wcpos_provider_error' === $error->get_error_code() ) {
				return $error;
			}
			$code = $error->get_error_code() ? (string) $error->get_error_code() : $code;
			$message = $error->get_error_message();
		} elseif ( $error instanceof \Throwable ) {
			$code = $error->getCode() ? (string) $error->getCode() : $code;
			$message = $error->getMessage();
		} else {
			$message = false === $error ? 'SumUp API request failed.' : $error;
		}
		return new \WP_Error(
			'wcpos_provider_error',
			$message,
			array(
				'status' => $status,
				'detail' => array(
					'code' => $code,
					'message' => $message,
				),
			)
		);
	}
}
