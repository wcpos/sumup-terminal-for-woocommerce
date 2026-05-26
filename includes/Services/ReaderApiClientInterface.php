<?php
/**
 * Reader API client contract.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

interface ReaderApiClientInterface {
	public function get_all();
	public function get_reader( $reader_id );
	public function create( array $data );
	public function destroy( $reader_id );
	public function checkout( $reader_id, $checkout_data );
	public function cancel_checkout( $reader_id );
	public function get_status( $reader_id );
	public function connect( $reader_id );
	public function disconnect( $reader_id );
	public function set_profile_service( ProfileService $profile_service ): void;
	public function set_merchant_id( $merchant_id ): void;
	public function get_merchant_id();
	public function has_api_key();
}
