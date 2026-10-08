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
	/**
	 * Seconds to wait before asking SumUp again about a recorded success it did not answer for,
	 * one entry per silent try; after the last the order is given up with a note. SumUp may not
	 * list a transaction for a short while after its delivery, and an outage must be outlived,
	 * not counted.
	 */
	public const LOOKUP_BACKOFF = array( 60, 300, 900, 3600, 21600 );

	/**
	 * Order statuses that still need payment: WooCommerce's own two plus the two Free adds
	 * through `woocommerce_valid_order_statuses_for_payment` (POS open and partially paid
	 * orders). `needs_payment()` on the rows read stays the truth.
	 */
	public const UNPAID_STATUSES = array( 'pending', 'failed', 'pos-open', 'pos-partial' );
	/** The sweep that works the completion queue: Free's ten-minute payments sweep. */
	public const SWEEP_HOOK = 'wcpos_payments_sweep';
	/** Provider family, as SumUp_Server_Provider::provider() reports it. */
	public const PROVIDER = 'sumup';
	/** The old panel's checkout status; `PENDING` once the reader has the checkout. */
	public const META_STATUS = '_sumup_checkout_status';
	/** The reader the old panel sent the checkout to. */
	public const META_READER = '_sumup_reader_id';
	/** When the old panel sent the checkout (unix time). */
	public const META_STARTED = '_sumup_attempt_started';
	/** The action reference Pro adopted, kept on the order after Free rewrites the transaction id. */
	public const META_ADOPTED = '_sutwc_adopted_ref';

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

	/**
	 * Whether Pro adopted the attempt on this order: by its current reference, or by the one
	 * recorded at adoption, since Free rewrites the order's transaction id once a row captures.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function is_adopted_order( \WC_Order $order ): bool {
		return self::is_adopted( self::action_ref( $order ) ) || self::is_adopted( (string) $order->get_meta( self::META_ADOPTED ) );
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
				// An attempt without a start time predates the marker (v0.0.10); the provider could
				// never confirm it finished, so it is not adopted. It is months old by now; one whose
				// result the old panel recorded is caught by the recorded-success pass below.
				if ( '' !== $ref && (int) $order->get_meta( self::META_STARTED ) > 0 && ! $order->is_paid() && $order->needs_payment() ) {
					$queue[ $order->get_id() ] = $ref;
				}
			}
			update_option( 'sutwc_adoption_queue', $queue, false );
			foreach ( self::recorded_successes() as $order_id ) {
				self::queue_recorded( $order_id );
			}
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
					$row = wcpos_pro_adopt_legacy_attempt( $fresh, Settings::GATEWAY_ID, $ref, (string) $fresh->get_total(), $fresh->get_currency() );
					if ( is_array( $row ) ) {
						// Pro's fetch() measures the reader's activity from this moment, as it would
						// for a keypad leg, so an attempt nobody pays settles without the deadline.
						Server\SumUp_Server_Provider::mark_adopted_checkout( (string) $fresh->get_transaction_id(), (int) $fresh->get_meta( self::META_STARTED ) );
						$fresh->update_meta_data( self::META_ADOPTED, $ref );
						$fresh->save();
					}
					return $row;
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
		update_option( 'sutwc_adoption_queue', $queue, false );
		if ( array() === $queue ) {
			delete_option( 'sutwc_adoption_queue' );
			update_option( 'sutwc_adoption_version', self::VERSION, false );
		}
	}

	/**
	 * Queue an order whose recorded success is to be completed on SumUp's word, off the request
	 * path: the sweep asks SumUp on its next run (or after `$delay`), never a page view.
	 *
	 * @param int $order_id Order id.
	 * @param int $delay    Seconds before the first ask; 0 for the next sweep.
	 */
	public static function queue_recorded( int $order_id, int $delay = 0 ): void {
		self::update_queue(
			static function ( array $queue ) use ( $order_id, $delay ) {
				if ( ! isset( $queue[ $order_id ] ) ) {
					$queue[ $order_id ] = array(
						'tries'   => 0,
						'next_at' => time() + $delay,
					);
				}
				return $queue;
			}
		);
	}

	/**
	 * Rewrite the completion queue from a fresh read, so two requests (a sweep and a webhook, or
	 * two webhooks) cannot overwrite each other's entries.
	 *
	 * @param callable $change Receives the current queue, returns the new one.
	 */
	private static function update_queue( callable $change ): void {
		$queue = get_option( 'sutwc_completion_queue', array() );
		$queue = $change( is_array( $queue ) ? $queue : array() );
		if ( array() === $queue ) {
			delete_option( 'sutwc_completion_queue' );
			return;
		}
		update_option( 'sutwc_completion_queue', $queue, false );
	}

	/**
	 * Orders whose old-panel attempt SumUp recorded as successful (PAID checkout or SUCCESSFUL
	 * transaction) but whose form submit never landed, so they still wait for payment. The old
	 * panel completed them on its next status check; that path is gone.
	 *
	 * @return int[] Order ids.
	 */
	private static function recorded_successes(): array {
		$ids = array();
		foreach ( array( array( self::META_STATUS, 'PAID' ), array( '_sumup_transaction_status', 'SUCCESSFUL' ) ) as $pair ) {
			$orders = wc_get_orders(
				array(
					'type'       => 'shop_order',
					// Only orders still waiting for payment: a completed sale keeps these meta values
					// for good, and a store's whole SumUp history must not be read on every upgrade.
					'status'     => self::UNPAID_STATUSES,
					'limit'      => -1,
					'orderby'    => 'ID',
					'order'      => 'ASC',
					'meta_key'   => $pair[0], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off upgrade pass.
					'meta_value' => $pair[1], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-off upgrade pass.
				)
			);
			foreach ( $orders as $order ) {
				if ( '' !== (string) $order->get_transaction_id() && ! $order->is_paid() && $order->needs_payment() ) {
					$ids[ $order->get_id() ] = true;
				}
			}
		}
		return array_keys( $ids );
	}

	/**
	 * Work the completion queue: run on Free's payments sweep (every ten minutes, off the
	 * request path). Each entry due now is asked about once; an order leaves the queue on a
	 * definite answer or on completion; one SumUp did not answer for waits the next backoff
	 * step, and after the last step it is given up with an order note for staff.
	 */
	public static function complete_recorded_page(): void {
		$queue = get_option( 'sutwc_completion_queue', array() );
		if ( ! is_array( $queue ) || array() === $queue ) {
			return;
		}
		$handler = new AjaxHandler();
		$now     = time();
		$changes = array(); // order id => new entry, or null to remove.
		// The page is taken from the entries due now, so entries waiting out a long backoff
		// at the front of the queue cannot hold back a due one behind them.
		$due = array_filter(
			$queue,
			static function ( $entry ) use ( $now ) {
				return (int) ( $entry['next_at'] ?? 0 ) <= $now;
			}
		);
		foreach ( array_slice( $due, 0, self::PAGE_SIZE, true ) as $order_id => $entry ) {
			$tries = (int) ( $entry['tries'] ?? 0 );
			$result = self::complete_recorded( (int) $order_id, $handler );
			if ( is_wp_error( $result ) ) {
				$code = $result->get_error_code();
				if ( in_array( $code, array( 'wcpos_payment_locked', 'sutwc_adoption_no_lock' ), true ) ) {
					// A held lock is a till at work on that order: it stays as it is.
					continue;
				}
				if ( 'sutwc_lookup_unavailable' === $code && isset( self::LOOKUP_BACKOFF[ $tries ] ) ) {
					$changes[ $order_id ] = array(
						'tries'   => $tries + 1,
						'next_at' => $now + self::LOOKUP_BACKOFF[ $tries ],
					);
					continue;
				}
				self::give_up( (int) $order_id, $code );
			}
			$changes[ $order_id ] = null;
		}
		self::update_queue(
			static function ( array $queue ) use ( $changes ) {
				foreach ( $changes as $order_id => $entry ) {
					if ( null === $entry ) {
						unset( $queue[ $order_id ] );
					} else {
						$queue[ $order_id ] = $entry;
					}
				}
				return $queue;
			}
		);
	}

	/**
	 * Stop asking about an order and tell staff, on the order, that it needs a look.
	 *
	 * @param int    $order_id Order id.
	 * @param string $code     The last error code.
	 */
	private static function give_up( int $order_id, string $code ): void {
		Logger::log( 'Legacy SumUp completion gave up on order ' . $order_id . ': ' . $code );
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->add_order_note( __( 'SumUp recorded a successful card payment for this order on the previous order-pay panel, but SumUp could not confirm it when asked. Check the transaction in the SumUp dashboard before taking payment again.', 'sumup-terminal-for-woocommerce' ) );
		}
	}

	/**
	 * Complete a recorded success under the order lock: the order is re-read inside it and the
	 * adopted and paid checks repeated on that copy, so two deliveries, or a delivery racing the
	 * adoption pass, cannot complete the same order twice or complete one Pro now owns.
	 *
	 * @param int         $order_id Order id.
	 * @param AjaxHandler $handler  The handler that knows SumUp's transaction lookup.
	 * @return bool|null|\WP_Error Whether the order was completed, null when nothing applied,
	 *                             or the lock's error.
	 */
	public static function complete_recorded( int $order_id, AjaxHandler $handler ) {
		return self::with_order_lock(
			$order_id,
			static function () use ( $order_id, $handler ) {
				$fresh = wc_get_order( $order_id );
				if ( ! $fresh || self::is_adopted_order( $fresh ) ) {
					return null;
				}
				return $handler->complete_recorded_attempt( $fresh );
			}
		);
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
			return new \WP_Error( 'sutwc_adoption_no_lock', 'The POS order lock is unavailable.' );
		}
		return \WCPOS\WooCommercePOS\Payments\Contract\Order_Lock::instance()->with_lock( $order_id, $callback );
	}
}
