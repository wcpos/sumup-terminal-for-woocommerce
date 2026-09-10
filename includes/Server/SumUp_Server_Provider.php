<?php
/**
 * SumUp cloud transport for Pro; Free owns settlement and replay guards.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Server;

use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ProfileService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ReaderService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\TransactionService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\RefundClient as Refund_Client;
use WCPOS\WooCommercePOSPro\Payments\Server\Money_Units;

/** Solo provider, independent of the legacy order-pay flow. */
class SumUp_Server_Provider extends \WCPOS\WooCommercePOSPro\Payments\Server\Abstract_Provider_Adapter {
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
				if ( false === $result || is_wp_error( $result ) ) {
					return self::error( $result );
				}
				if ( empty( $result['data']['client_transaction_id'] ) ) {
					return self::error( 'SumUp checkout did not return a client transaction ID.' );
				}
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
				$transaction = $this->lookup( explode( ':', $ref, 2 )[1] ?? '' );
				return is_wp_error( $transaction ) ? $transaction : self::normalize( $transaction );
			}
		);
	}

	/**
	 * Pure projection of an observation; missing transactions remain pending.
	 *
	 * @param array|false $transaction Transaction or empty observation.
	 * @return array Normalized observation.
	 */
	public static function normalize( $transaction ): array {
		$transaction = is_array( $transaction ) ? $transaction : array();
		$states = array(
			'PENDING' => 'in_progress',
			'SUCCESSFUL' => 'completed',
			'FAILED' => 'failed',
			'CANCELLED' => 'cancelled',
		);
		$currency = ! empty( $transaction['currency'] ) ? strtoupper( $transaction['currency'] ) : null;
		$result = array(
			'status' => $states[ $transaction['status'] ?? '' ] ?? 'pending',
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
		if ( 'failed' === $result['status'] ) {
			$result['failure_reason'] = 'provider_error'; }
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
				return false === $result || is_wp_error( $result ) ? self::error( $result ) : 'requested';
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
	 * @return \WP_Error Provider error.
	 */
	private static function error( $error, string $code = 'sumup_api_error' ): \WP_Error {
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
				'status' => 502,
				'detail' => array(
					'code' => $code,
					'message' => $message,
				),
			)
		);
	}
}
