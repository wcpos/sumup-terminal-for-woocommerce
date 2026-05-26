<?php
/**
 * Official SumUp SDK implementation of supported Reader API operations.
 */

namespace WCPOS\WooCommercePOS\SumUpTerminal\Services;

use WCPOS\WooCommercePOS\SumUpTerminal\Logger;

class SdkReaderApiClient implements ReaderApiClientInterface {
	private $api_key;
	private $fallback;
	private $profile_service;
	private $merchant_id;
	private $sdk;

	public function __construct( $api_key, WordPressHttpReaderApiClient $fallback ) {
		$this->api_key  = $api_key;
		$this->fallback = $fallback;
	}

	public function set_profile_service( ProfileService $profile_service ): void {
		$this->profile_service = $profile_service;
		$this->fallback->set_profile_service( $profile_service );
	}

	public function set_merchant_id( $merchant_id ): void {
		$this->merchant_id = $merchant_id;
		$this->fallback->set_merchant_id( $merchant_id );
	}

	public function get_merchant_id() {
		return $this->merchant_id ? $this->merchant_id : $this->fallback->get_merchant_id();
	}

	public function has_api_key() {
		return ! empty( $this->api_key );
	}

	public function get_all() {
		$merchant_id = $this->ensure_merchant_id();
		if ( ! $merchant_id ) {
			return false;
		}

		return $this->sdk_call(
			'list readers',
			function () use ( $merchant_id ) {
				$response = $this->get_readers_service()->list( $merchant_id );
				$items    = $response->items ?? array();

				return array_map( array( $this, 'normalize_reader' ), $items );
			},
			function () {
				return $this->fallback->get_all();
			}
		);
	}

	public function get_reader( $reader_id ) {
		$merchant_id = $this->ensure_merchant_id();
		if ( ! $merchant_id ) {
			return false;
		}

		return $this->sdk_call(
			'get reader',
			function () use ( $merchant_id, $reader_id ) {
				return $this->normalize_reader( $this->get_readers_service()->get( $merchant_id, $reader_id ) );
			},
			function () use ( $reader_id ) {
				return $this->fallback->get_reader( $reader_id );
			}
		);
	}

	public function create( array $data ) {
		$merchant_id = $this->ensure_merchant_id();
		if ( ! $merchant_id ) {
			return false;
		}

		return $this->sdk_call(
			'create reader',
			function () use ( $merchant_id, $data ) {
				return $this->normalize_reader( $this->get_readers_service()->create( $merchant_id, $data ) );
			},
			function () use ( $data ) {
				return $this->fallback->create( $data );
			}
		);
	}

	public function destroy( $reader_id ) {
		$merchant_id = $this->ensure_merchant_id();
		if ( ! $merchant_id ) {
			return false;
		}

		return $this->sdk_call(
			'delete reader',
			function () use ( $merchant_id, $reader_id ) {
				$this->get_readers_service()->delete( $merchant_id, $reader_id );

				return true;
			},
			function () use ( $reader_id ) {
				return $this->fallback->destroy( $reader_id );
			}
		);
	}

	public function checkout( $reader_id, $checkout_data ) {
		$merchant_id = $this->ensure_merchant_id();
		if ( ! $merchant_id ) {
			return false;
		}

		return $this->sdk_call(
			'create reader checkout',
			function () use ( $merchant_id, $reader_id, $checkout_data ) {
				return $this->normalize_checkout_response( $this->get_readers_service()->createCheckout( $merchant_id, $reader_id, $checkout_data ) );
			},
			function () use ( $reader_id, $checkout_data ) {
				return $this->fallback->checkout( $reader_id, $checkout_data );
			}
		);
	}

	public function cancel_checkout( $reader_id ) {
		$merchant_id = $this->ensure_merchant_id();
		if ( ! $merchant_id ) {
			return false;
		}

		return $this->sdk_call(
			'terminate reader checkout',
			function () use ( $merchant_id, $reader_id ) {
				$this->get_readers_service()->terminateCheckout( $merchant_id, $reader_id );

				return true;
			},
			function () use ( $reader_id ) {
				return $this->fallback->cancel_checkout( $reader_id );
			}
		);
	}

	public function get_status( $reader_id ) {
		$merchant_id = $this->ensure_merchant_id();
		if ( ! $merchant_id ) {
			return false;
		}

		return $this->sdk_call(
			'get reader status',
			function () use ( $merchant_id, $reader_id ) {
				return $this->normalize_status( $this->get_readers_service()->getStatus( $merchant_id, $reader_id ) );
			},
			function () use ( $reader_id ) {
				return $this->fallback->get_status( $reader_id );
			}
		);
	}

	public function connect( $reader_id ) {
		return $this->fallback->connect( $reader_id );
	}

	public function disconnect( $reader_id ) {
		return $this->fallback->disconnect( $reader_id );
	}

	private function ensure_merchant_id() {
		if ( $this->merchant_id ) {
			return $this->merchant_id;
		}

		if ( $this->profile_service ) {
			$merchant_code = $this->profile_service->get_merchant_code();
			if ( $merchant_code ) {
				$this->set_merchant_id( $merchant_code );

				return $merchant_code;
			}
		}

		return null;
	}

	private function get_sdk() {
		if ( $this->sdk ) {
			return $this->sdk;
		}

		$class     = SdkAvailability::PREFIXED_SUMUP_CLASS;
		$this->sdk = new $class( $this->api_key );

		return $this->sdk;
	}

	private function get_readers_service() {
		$sdk = $this->get_sdk();

		return $sdk->readers();
	}

	private function normalize_reader( $reader ) {
		if ( is_array( $reader ) ) {
			return $reader;
		}

		return array(
			'id'         => $reader->id ?? null,
			'name'       => $reader->name ?? null,
			'status'     => $this->enum_value( $reader->status ?? null ),
			'created_at' => $reader->createdAt ?? $reader->created_at ?? null,
			'device'     => array(
				'model'      => $this->enum_value( $reader->device->model ?? null ),
				'identifier' => $reader->device->identifier ?? null,
			),
		);
	}

	private function normalize_status( $status ) {
		return json_decode( wp_json_encode( $status ), true );
	}

	private function normalize_checkout_response( $response ) {
		return json_decode( wp_json_encode( $response ), true );
	}

	private function enum_value( $value ) {
		if ( is_object( $value ) && $value instanceof \BackedEnum ) {
			return $value->value;
		}

		if ( is_object( $value ) && property_exists( $value, 'value' ) ) {
			return $value->value;
		}

		return $value;
	}

	private function sdk_call( $operation, callable $callback, callable $fallback ) {
		try {
			return $callback();
		} catch ( \Throwable $e ) {
			Logger::log( 'SumUp SDK ' . $operation . ' failed; falling back to WordPress HTTP client: ' . $e->getMessage() );

			return $fallback();
		}
	}
}
