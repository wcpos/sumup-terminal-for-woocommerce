<?php
/**
 * Fold attempts the old order-pay panel left mid-flight into Pro's ledger on upgrade.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal;

/** One pass per plugin version, 25 orders at a time, resumable by offset. */
final class Legacy_Adoption {
	/** Plugin version this adoption belongs to. */
	public const VERSION = '1.0.0';
	/** Candidates per `init` request; keeps the admin request that triggers it short. */
	public const PAGE_SIZE = 25;
	/** Provider family, as SumUp_Server_Provider::provider() reports it. */
	public const PROVIDER = 'sumup';
	/** The old panel's checkout status; `PENDING` once the reader has the checkout. */
	public const META_STATUS = '_sumup_checkout_status';
	/** The reader the old panel sent the checkout to. */
	public const META_READER = '_sumup_reader_id';

	/**
	 * The action reference Pro's provider uses for an attempt the old panel started:
	 * the reader id and SumUp's client transaction id, which the old panel stored as the
	 * order's transaction id.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function action_ref( \WC_Order $order ): string {
		$reader = (string) $order->get_meta( self::META_READER );
		$client = (string) $order->get_transaction_id();
		return '' === $reader || '' === $client ? '' : $reader . ':' . $client;
	}

	/**
	 * Whether Pro adopted this attempt from the old panel; its outcome is then Pro's.
	 *
	 * @param string $ref Action reference.
	 */
	public static function is_adopted( string $ref ): bool {
		return '' !== $ref && \function_exists( 'wcpos_pro_payment_id_for_action' ) && null !== wcpos_pro_payment_id_for_action( self::PROVIDER, $ref );
	}

	/** Run the next page of adoption, until every candidate snapshotted at the start has been seen. */
	public static function upgrade(): void {
		if ( version_compare( (string) get_option( 'sutwc_adoption_version', '0' ), self::VERSION, '>=' ) ) {
			return;
		}
		// The candidates are snapshotted once, as id => action reference, when the pass begins:
		// every PENDING attempt with a reader and a client transaction id on an order still
		// waiting for payment. Paging a live filter by offset would skip rows as webhooks move
		// orders out of it, and an attempt the old panel starts later is never a candidate.
		$queue = get_option( 'sutwc_adoption_queue', null );
		if ( ! is_array( $queue ) ) {
			$queue = array();
			$candidates = wc_get_orders(
				array(
					'type'       => 'shop_order',
					'limit'      => -1,
					'orderby'    => 'ID',
					'order'      => 'ASC',
					'meta_key'   => self::META_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off upgrade pass.
					'meta_value' => 'PENDING', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-off upgrade pass.
				)
			);
			foreach ( $candidates as $order ) {
				$ref = self::action_ref( $order );
				if ( '' !== $ref && ! $order->is_paid() && $order->needs_payment() ) {
					$queue[ $order->get_id() ] = $ref;
				}
			}
			update_option( 'sutwc_adoption_queue', $queue, false );
		}
		$page = array_slice( $queue, 0, self::PAGE_SIZE, true );
		foreach ( $page as $order_id => $ref ) {
			$result = self::with_order_lock(
				(int) $order_id,
				static function () use ( $order_id, $ref ) {
					// Read the order under the lock and repeat the checks on that copy, so a leg a
					// till recorded meanwhile, or a new attempt, is kept out of the way.
					$fresh = wc_get_order( (int) $order_id );
					if ( ! $fresh || self::is_adopted( $ref ) || self::action_ref( $fresh ) !== $ref || 'PENDING' !== strtoupper( (string) $fresh->get_meta( self::META_STATUS ) ) || $fresh->is_paid() || ! $fresh->needs_payment() ) {
						return null;
					}
					return wcpos_pro_adopt_legacy_attempt( $fresh, Settings::GATEWAY_ID, $ref, (string) $fresh->get_total(), $fresh->get_currency() );
				}
			);
			if ( is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'wcpos_payment_locked', 'sutwc_adoption_no_lock' ), true ) ) {
				// A held lock is a till at work on that order: it stays in the queue for the next
				// request. Any other refusal is final for this order and is logged.
				Logger::log( 'Legacy SumUp adoption deferred for order ' . $order_id . ': ' . $result->get_error_code() );
				continue;
			}
			if ( is_wp_error( $result ) ) {
				Logger::log( 'Legacy SumUp adoption failed for order ' . $order_id . ': ' . $result->get_error_code() );
			}
			unset( $queue[ $order_id ] );
		}
		if ( array() === $queue ) {
			delete_option( 'sutwc_adoption_queue' );
			update_option( 'sutwc_adoption_version', self::VERSION, false );
			return;
		}
		update_option( 'sutwc_adoption_queue', $queue, false );
	}

	/**
	 * Run under Free's per-order lock, the one every ledger write takes.
	 *
	 * @param int      $order_id Order id.
	 * @param callable $callback Work to run while the lock is held.
	 * @return mixed The callback's result, or a WP_Error when the lock could not be taken.
	 */
	private static function with_order_lock( int $order_id, callable $callback ) {
		if ( ! class_exists( '\WCPOS\WooCommercePOS\Payments\Contract\Order_Lock' ) ) {
			return new \WP_Error( 'sutwc_adoption_no_lock' );
		}
		return \WCPOS\WooCommercePOS\Payments\Contract\Order_Lock::instance()->with_lock( $order_id, $callback );
	}
}
