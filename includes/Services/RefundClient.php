<?php
/**
 * SumUp refund transport, including empty successful responses.
 *
 * @package WCPOS\WooCommercePOS\SumUpTerminal
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

/** Refunds use v0.1 rather than the transaction lookup's v2.1. */
class RefundClient extends HttpClient {
	/** Match the existing HttpClient request timeout for SumUp cloud operations. */
	private const REQUEST_TIMEOUT = 30;

	/**
	 * Submit a full (empty body) or partial refund without retrying.
	 *
	 * @param string     $transaction_id SumUp transaction ID.
	 * @param float|null $amount Partial refund in major units, null for full.
	 * @return bool|\WP_Error Transport result.
	 */
	public function refund( string $transaction_id, ?float $amount ) {
		$response = wp_remote_request(
			$this->base_url . '/v0.1/me/refund/' . rawurlencode( $transaction_id ),
			array(
				'method' => 'POST',
				'headers' => $this->get_headers(),
				'body' => null === $amount ? '' : wp_json_encode( array( 'amount' => $amount ) ),
				'timeout' => self::REQUEST_TIMEOUT,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response; }
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $code, array( 200, 204 ), true ) ) {
			return true;
		}
		// Surface SumUp's reason (e.g. {"message":"Rejected"}) instead of a bare failure.
		$body    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$message = is_array( $body ) ? (string) ( $body['message'] ?? $body['error_description'] ?? $body['error_message'] ?? '' ) : '';
		return new \WP_Error( 'sumup_refund_rejected', '' !== $message ? $message : sprintf( 'SumUp refund failed (HTTP %d).', $code ), array( 'status' => $code ) );
	}
}
