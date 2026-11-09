<?php
// phpcs:ignoreFile
// Test double for Free's per-order lock: runs the callback and records the ids locked;
// refuses the ids listed in $refuse the way Free does.
namespace WCPOS\WooCommercePOS\Payments\Contract;

if ( ! class_exists( Order_Lock::class ) ) {
	class Order_Lock {
		public static $locked = array();
		public static $refuse = array();
		public static function instance() { return new self(); }
		public function with_lock( $order_id, $callback ) {
			if ( in_array( $order_id, self::$refuse, true ) ) { return new \WP_Error( 'wcpos_payment_locked', 'busy' ); }
			self::$locked[] = $order_id;
			return $callback();
		}
	}
}
