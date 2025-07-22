<?php
/**
 * SumUp Checkout Service
 * Handles checkout-related SumUp API operations.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

use WC_Order;

/**
 * Class CheckoutService.
 */
class CheckoutService extends HttpClient {
	/**
	 * Get all checkouts, optionally filtered by checkout reference.
	 *
	 * @param null|string $checkout_reference Optional checkout reference to filter by.
	 *
	 * @return array|false Checkouts data or false on failure.
	 */
	public function get_all( $checkout_reference = null ) {
		$params = array();

		if ( $checkout_reference ) {
			$params['checkout_reference'] = $checkout_reference;
		}

		return parent::get( '/v0.1/checkouts', $params );
	}

	/**
	 * Create a new checkout.
	 *
	 * @param array $data Checkout data.
	 *
	 * @return array|false Checkout data or false on failure.
	 */
	public function create( array $data ) {
		// Add merchant code if we have it.
		if ( ! isset( $data['merchant_code'] ) && $this->get_merchant_id() ) {
			$data['merchant_code'] = $this->get_merchant_id();
		}

		return parent::post( '/v0.1/checkouts', $data );
	}

	/**
	 * Get a specific checkout by ID.
	 *
	 * @param string $checkout_id Checkout ID.
	 *
	 * @return array|false Checkout data or false on failure.
	 */
	public function get_checkout( $checkout_id ) {
		return parent::get( "/v0.1/checkouts/{$checkout_id}" );
	}

	/**
	 * Update a checkout.
	 *
	 * @param string $checkout_id Checkout ID.
	 * @param array  $data        Updated checkout data.
	 *
	 * @return array|false Updated checkout data or false on failure.
	 */
	public function update( $checkout_id, array $data ) {
		return parent::put( "/v0.1/checkouts/{$checkout_id}", $data );
	}

	/**
	 * Delete a checkout.
	 *
	 * @param string $checkout_id Checkout ID.
	 *
	 * @return array|false Response data or false on failure.
	 */
	public function destroy( $checkout_id ) {
		return parent::delete( "/v0.1/checkouts/{$checkout_id}" );
	}

	/**
	 * Create a checkout for a WooCommerce order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return array|false Checkout data or false on failure.
	 */
	public function create_for_order( $order ) {
		if ( ! $this->has_api_key() ) {
			return false;
		}

		// Prepare checkout data.
		$data = array(
			'checkout_reference' => $order->get_order_number(),
			'amount'             => \floatval( $order->get_total() ),
			'currency'           => $order->get_currency(),
			'description'        => \sprintf( __( 'Order #%s', 'sumup-terminal-for-woocommerce' ), $order->get_order_number() ),
		);

		return $this->create( $data );
	}
}
