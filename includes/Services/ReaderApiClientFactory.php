<?php
/**
 * Reader API client factory.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

class ReaderApiClientFactory {
	public static function create( $api_key ): ReaderApiClientInterface {
		$fallback = new WordPressHttpReaderApiClient( $api_key );

		if ( SdkAvailability::is_sdk_available() ) {
			return new SdkReaderApiClient( $api_key, $fallback );
		}

		return $fallback;
	}
}
