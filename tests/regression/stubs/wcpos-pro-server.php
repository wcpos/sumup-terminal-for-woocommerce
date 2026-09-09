<?php
namespace {
	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private $code;
			private $message;
			private $data;
			public function __construct( $code, $message, $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
			public function get_error_code() { return $this->code; }
			public function get_error_message() { return $this->message; }
			public function get_error_data() { return $this->data; }
		}
	}
	if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $value ) { return $value instanceof WP_Error; } }
	if ( ! class_exists( 'WP_REST_Request' ) ) {
		class WP_REST_Request {
			private $params = array();
			private $query = array();
			private $json = array();
			public function set_query_params( $query ) { $this->query = $query; }
			public function get_query_params() { return $this->query; }
			public function set_body( $body ) { $this->json = json_decode( $body, true ); }
			public function get_json_params() { return $this->json; }
			public function set_param( $key, $value ) { $this->params[ $key ] = $value; }
			public function get_param( $key ) { return $this->params[ $key ] ?? null; }
		}
	}
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { class WC_Payment_Gateway {} }
	if ( ! function_exists( 'rest_url' ) ) { function rest_url( $path ) { return 'https://shop.example/wp-json/' . $path; } }
	if ( ! function_exists( 'add_query_arg' ) ) {
		function add_query_arg( $key, $value, $url = '' ) {
			$args = is_array( $key ) ? $key : array( $key => $value );
			$url = is_array( $key ) ? $value : $url;
			return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
		}
	}
	if ( ! function_exists( 'get_option' ) ) { function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; } }
	if ( ! function_exists( 'update_option' ) ) { function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; } }
	if ( ! function_exists( 'delete_option' ) ) { function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; } }
	if ( ! function_exists( 'wc_get_order' ) ) { function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; } }
	if ( ! function_exists( '__' ) ) { function __( $text, $domain = null ) { return $text; } }
}
namespace WCPOS\WooCommercePOSPro\Payments\Server {
	if ( ! interface_exists( __NAMESPACE__ . '\\Provider_Adapter_Interface' ) ) {
		interface Provider_Adapter_Interface {
			public function provider(): string;
			public function describe( \WC_Payment_Gateway $gateway ): array;
			public function list_readers();
			public function create_reader_action( array $row, string $reader_id );
			public function fetch( string $ref );
			public function cancel( string $ref );
			public function capture( string $ref );
			public function refund( array $row, int $refund_id, string $amount );
			public function verify_webhook( \WP_REST_Request $request );
		}
	}
	if ( ! class_exists( __NAMESPACE__ . '\\Abstract_Provider_Adapter' ) ) {
		abstract class Abstract_Provider_Adapter implements Provider_Adapter_Interface {
			public function describe( \WC_Payment_Gateway $gateway ): array { return array(); }
			public function capture( string $ref ) { return $this->unsupported(); }
			public function refund( array $row, int $refund_id, string $amount ) { return $this->unsupported(); }
			public function verify_webhook( \WP_REST_Request $request ) { return $this->unsupported(); }
			protected function event( array $row, string $level, string $message ): array { return Event_Log::append( $row, $level, $message ); }
			private function unsupported(): \WP_Error { return new \WP_Error( 'wcpos_capture_mode_unsupported', __( 'This payment provider does not support that operation.', 'woocommerce-pos-pro' ), array( 'status' => 501 ) ); }
		}
	}
	if ( ! class_exists( __NAMESPACE__ . '\\Event_Log' ) ) {
		final class Event_Log {
			public static function append( array $row, string $level, string $message, array $context = array() ): array {
				$row['events'][] = array( 't' => gmdate( 'c' ), 'level' => $level, 'message' => $message );
				return $row;
			}
		}
	}
}

namespace WCPOS\WooCommercePOSPro\Payments\Server {
	class Money_Units {
		private static function exponent( string $currency ): int { return array( 'JPY' => 0, 'HUF' => 0, 'KWD' => 3 )[ strtoupper( $currency ) ] ?? 2; }
		public static function minor( string $amount, string $currency ): int { return (int) round( (float) $amount * ( 10 ** self::exponent( $currency ) ) ); }
		public static function major( int $minor, string $currency ): string { return number_format( $minor / ( 10 ** self::exponent( $currency ) ), self::exponent( $currency ), '.', '' ); }
	}
}
