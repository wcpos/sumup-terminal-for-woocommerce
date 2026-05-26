<?php
/**
 * Reader API client factory.
 *
 * @package SumUpTerminalForWooCommerce
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

// phpcs:disable Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.VariableComment.Missing, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

class ReaderApiClientFactory {
	public static function create( $api_key ): ReaderApiClientInterface {
		$fallback = new WordPressHttpReaderApiClient( $api_key );

		if ( SdkAvailability::is_sdk_available() ) {
			return new SdkReaderApiClient( $api_key, $fallback );
		}

		return $fallback;
	}
}
