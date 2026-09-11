<?php
/**
 * Native SDK handoff and authoritative REST observations for Pro device checkout.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Server;

use WCPOS\WooCommercePOS\SumUpTerminal\Settings;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\ProfileService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\TransactionService;
use WCPOS\WooCommercePOS\SumUpTerminal\Services\RefundClient as Refund_Client;

/** The app drives the reader; only SumUp REST observations report money outcomes. */
class SumUp_Device_Provider extends \WCPOS\WooCommercePOSPro\Payments\Device\Abstract_Device_Provider_Adapter {
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
	/** Merchant profile.
	 *
	 * @var ProfileService
	 */
	private $profile;
	/** Construction failure.
	 *
	 * @var \WP_Error|null
	 */
	private $initialization_error;

	/**
	 * Resolve the existing REST services without starting a reader action.
	 *
	 * @param TransactionService|null $transactions Transaction transport.
	 * @param Refund_Client|null      $refunds Refund transport.
	 * @param ProfileService|null     $profile Merchant profile.
	 */
	public function __construct( ?TransactionService $transactions = null, ?Refund_Client $refunds = null, ?ProfileService $profile = null ) {
		$this->initialization_error = $this->call(
			function () use ( $transactions, $refunds, $profile ) {
				$key = Settings::api_key();
				$this->profile = $profile ?? new ProfileService( $key );
				$this->transactions = $transactions ?? new TransactionService( $key );
				$this->refunds = $refunds ?? new Refund_Client( $key );
				$this->transactions->set_profile_service( $this->profile );
			}
		);
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
		$affiliate = Settings::affiliate();
		return array(
			'hardware' => array(
				'discovery' => 'sdk',
				'transports' => array(
					array(
						'transport' => 'bluetooth',
						'offline' => 'none',
						'tips' => 'on_reader',
					),
				),
			),
			'capabilities' => array(
				'tips' => 'on_reader',
				'offline' => 'none',
				'refunds' => array(
					'via' => 'provider',
					'partial' => true,
				),
			),
			'provider_data' => array(
				'merchant_code' => $this->merchant_code(),
				'affiliate_app_id' => '' !== $affiliate['app_id'] ? $affiliate['app_id'] : null,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WC_Payment_Gateway $gateway Gateway instance.
	 * @param array               $context Bootstrap context.
	 */
	public function bootstrap( \WC_Payment_Gateway $gateway, array $context ) {
		$key = Settings::affiliate()['key'];
		if ( '' === $key ) {
			return new \WP_Error( 'sumup_affiliate_missing', __( 'A store administrator must enter a SumUp Affiliate Key in the gateway settings to use a Bluetooth reader.', 'sumup-terminal-for-woocommerce' ), array( 'status' => 409 ) );
		}
		return array(
			'handoff' => array(
				'affiliate_key' => $key,
				'merchant_code' => $this->merchant_code(),
			),
			'expires_at' => null,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $row Ledger row.
	 * @param array $context Checkout context.
	 */
	public function create_intent( array $row, array $context ) {
		return array(
			'ref' => $row['id'],
			'handoff' => array( 'foreign_transaction_id' => $row['id'] ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $ref POS payment ID supplied to the SDK.
	 */
	public function fetch( string $ref ) {
		return $this->call(
			function () use ( $ref ) {
				$response = $this->transactions->find_by_foreign_transaction_id( $ref );
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$transaction = array();
				foreach ( $response['items'] ?? array( $response ) as $item ) {
					// Only a singleton may omit the echoed ID; never select an uncorrelated list item.
					if ( is_array( $item ) && ( $item['foreign_transaction_id'] ?? ( isset( $response['items'] ) ? null : $ref ) ) === $ref ) {
						$transaction = $item;
						break;
					}
				}
				$result = SumUp_Server_Provider::normalize( $transaction );
				$result['payment_id'] = $transaction['foreign_transaction_id'] ?? $ref;
				$result['provider_refs'] = array( 'foreign_transaction_id' => $result['payment_id'] );
				if ( $transaction ) {
					$result['provider_refs']['transaction_id'] = $transaction['id'] ?? null;
					$result['provider_refs']['transaction_code'] = $transaction['transaction_code'] ?? null;
				}
				$result['failure_reason'] = null;
				return $result;
			}
		);
	}

	/**
	 * Pro re-reads fetch after cancellation; this is not evidence that money was not taken.
	 *
	 * @param string $ref POS payment ID.
	 * @return string No server-side action remains to cancel.
	 */
	public function cancel( string $ref ) {
		return 'final';
	}

	/**
	 * Refund the REST UUID persisted by fetch, never the SDK transaction code.
	 *
	 * @param array  $row Ledger row.
	 * @param int    $refund_id WooCommerce refund ID (Pro owns replay protection).
	 * @param string $amount Decimal major units.
	 * @return array|\WP_Error Refund outcome.
	 */
	public function refund( array $row, int $refund_id, string $amount ) {
		return $this->call(
			function () use ( $row, $amount ) {
				$id = $row['provider_refs']['transaction_id'] ?? '';
				if ( '' === $id ) {
					return new \WP_Error( 'transaction_not_found', 'No SumUp transaction UUID found for refund.', array( 'status' => 409 ) );
				}
				// Always send the requested amount: SDK tips can make the transaction total larger.
				$result = $this->refunds->refund( $id, (float) $amount );
				return false === $result || is_wp_error( $result ) ? $result : array(
					'status' => 'succeeded',
					'provider_ref' => $id,
				);
			}
		);
	}

	/** Optional profile metadata must be null when unavailable. */
	private function merchant_code(): ?string {
		$result = $this->call(
			function () {
				return $this->profile->get_merchant_code();
			}
		);
		return is_wp_error( $result ) || ! $result ? null : $result;
	}

	/**
	 * Keep service failures as errors, never as observations or successful refunds.
	 *
	 * @param callable $operation Provider operation.
	 * @return mixed Operation result or error.
	 */
	private function call( callable $operation ) {
		if ( $this->initialization_error ) {
			return $this->initialization_error;
		}
		try {
			$result = $operation();
			return false === $result ? new \WP_Error( 'sumup_api_error', 'SumUp API request failed.', array( 'status' => 502 ) ) : $result;
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'sumup_api_error', $e->getMessage(), array( 'status' => 502 ) );
		}
	}
}
