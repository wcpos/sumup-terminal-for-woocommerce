<?php
// phpcs:ignoreFile
//
// A Solo reports the undocumented reader state `PROCESSING` between card tap and result.
// The SDK's enum rejects it, so routing status through the SDK cost a wasted round trip
// on every two-second poll while the cashier waited. Reader status must go straight to
// the HTTP client, and the vendored SDK must still be the one that cannot decode it
// (if a future SDK bump accepts the state, this premise check tells us).

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp {
    class SumUp {
        public static $status_calls = 0;

        public function __construct($api_key) {}

        public function readers() {
            return new class {
                public function getStatus($merchant_code, $reader_id) {
                    SumUp::$status_calls++;
                    throw new \ValueError('"PROCESSING" is not a valid backing value for enum StatusResponseDataState');
                }
            };
        }
    }
}

namespace {
    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data) {
            return json_encode($data);
        }
    }

    require_once __DIR__ . '/../../includes/Logger.php';
    require_once __DIR__ . '/../../includes/Services/ReaderApiClientInterface.php';
    require_once __DIR__ . '/../../includes/Services/HttpClient.php';
    require_once __DIR__ . '/../../includes/Services/WordPressHttpReaderApiClient.php';
    require_once __DIR__ . '/../../includes/Services/SdkAvailability.php';
    require_once __DIR__ . '/../../includes/Services/SdkReaderApiClient.php';

    $processing = array('data' => array('state' => 'PROCESSING', 'status' => 'ONLINE'));

    if (PHP_VERSION_ID >= 80100) {
        $sdk = __DIR__ . '/../../vendor_prefixed/sumup-sdk';
        require_once $sdk . '/Hydrator.php';
        require_once $sdk . '/Types/StatusResponse.php';
        require_once $sdk . '/Types/StatusResponseData.php';
        require_once $sdk . '/Types/StatusResponseDataState.php';
        require_once $sdk . '/Types/StatusResponseDataStatus.php';
        require_once $sdk . '/Types/StatusResponseDataConnectionType.php';

        $threw = false;
        try {
            \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Hydrator::hydrate(
                $processing,
                \WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\Types\StatusResponse::class
            );
        } catch (\ValueError $e) {
            $threw = true;
        }

        if (!$threw) {
            fwrite(STDERR, "The vendored SDK now decodes the PROCESSING reader state; revisit whether status may use the SDK again.\n");
            exit(1);
        }

        echo "PASS: the vendored SDK still rejects the PROCESSING reader state.\n";
    }

    class CountingHttpReaderApiClient extends \WCPOS\WooCommercePOS\SumUpTerminal\Services\WordPressHttpReaderApiClient {
        public $status_calls = 0;
        public $response;

        public function get_status($reader_id) {
            $this->status_calls++;

            return $this->response;
        }
    }

    $fallback = new CountingHttpReaderApiClient('key');
    $fallback->response = $processing;

    $client = new \WCPOS\WooCommercePOS\SumUpTerminal\Services\SdkReaderApiClient('key', $fallback);
    $client->set_merchant_id('MERCHANT');

    $result = $client->get_status('rdr_1');

    if ($result !== $processing) {
        fwrite(STDERR, "Reader status did not return the HTTP client's body unchanged.\n");
        fwrite(STDERR, var_export($result, true) . "\n");
        exit(1);
    }

    if ($fallback->status_calls !== 1) {
        fwrite(STDERR, "Reader status must make exactly one HTTP request, made {$fallback->status_calls}.\n");
        exit(1);
    }

    if (\WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\SumUp::$status_calls !== 0) {
        fwrite(STDERR, "Reader status must not go through the SDK.\n");
        exit(1);
    }

    echo "PASS: reader status skips the SDK and costs one request.\n";
}
