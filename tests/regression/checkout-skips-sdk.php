<?php
// phpcs:ignoreFile
//
// A checkout is POSTed exactly once. SumUp's checkout has no idempotency key, and the SDK route
// answered any throwable (a dropped connection after the POST left, a 5xx) by sending the same
// checkout again over HTTP: a second checkout behind one that may already be on the reader. So
// a checkout never goes through the SDK, and a failing HTTP answer is never sent a second time.

namespace WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp {
    class SumUp {
        public static $checkout_calls = 0;

        public function __construct($api_key) {}

        public function readers() {
            return new class {
                public function createCheckout($merchant_code, $reader_id, $body) {
                    SumUp::$checkout_calls++;
                    throw new \RuntimeException('Connection reset after the request was sent');
                }
            };
        }
    }
}

namespace {
    if (!class_exists('WP_Error')) {
        class WP_Error {
            private $code;
            private $message;
            private $data;

            public function __construct($code = '', $message = '', $data = null) {
                $this->code = $code;
                $this->message = $message;
                $this->data = $data;
            }

            public function get_error_code() { return $this->code; }
            public function get_error_message() { return $this->message; }
            public function get_error_data() { return $this->data; }
        }
    }

    function is_wp_error($value) { return $value instanceof WP_Error; }
    function wp_json_encode($data) { return json_encode($data); }
    function wp_remote_retrieve_body($response) { return $response['body']; }
    function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
    function wp_remote_request($url, $args) {
        $GLOBALS['requests'][] = array($url, $args);

        return $GLOBALS['response'];
    }

    require_once __DIR__ . '/../../includes/Logger.php';
    require_once __DIR__ . '/../../includes/Services/ReaderApiClientInterface.php';
    require_once __DIR__ . '/../../includes/Services/HttpClient.php';
    require_once __DIR__ . '/../../includes/Services/ProfileService.php';
    require_once __DIR__ . '/../../includes/Services/WordPressHttpReaderApiClient.php';
    require_once __DIR__ . '/../../includes/Services/SdkAvailability.php';
    require_once __DIR__ . '/../../includes/Services/SdkReaderApiClient.php';

    use WCPOS\WooCommercePOS\SumUpTerminal\Services\ProfileService;
    use WCPOS\WooCommercePOS\SumUpTerminal\Services\SdkReaderApiClient;
    use WCPOS\WooCommercePOS\SumUpTerminal\Services\WordPressHttpReaderApiClient;
    use WCPOS\WooCommercePOS\SumUpTerminal\Vendor\SumUpSdk\SumUp\SumUp;

    class CheckoutProfile extends ProfileService {
        public $merchant_code = false;

        public function get_merchant_code($force_refresh = false) { return $this->merchant_code; }
    }

    function check($condition, $message) {
        if (!$condition) {
            fwrite(STDERR, $message . "\n");
            exit(1);
        }
    }

    $requests = array();
    $payload = array('total_amount' => array('value' => 1230, 'currency' => 'EUR', 'minor_unit' => 2));
    $client = new SdkReaderApiClient('key', new WordPressHttpReaderApiClient('key'));
    $client->set_merchant_id('M123');

    // A checkout SumUp accepts: one HTTP request, the body as SumUp sent it, the SDK untouched.
    $response = array('response' => array('code' => 201), 'body' => '{"data":{"client_transaction_id":"client:123"}}');
    $result = $client->checkout('rdr_1', $payload);
    check(array('data' => array('client_transaction_id' => 'client:123')) === $result, 'A checkout must return the HTTP body: ' . var_export($result, true));
    check(1 === count($requests), 'A checkout must make exactly one HTTP request, made ' . count($requests) . '.');
    check('https://api.sumup.com/v0.1/merchants/M123/readers/rdr_1/checkout' === $requests[0][0], 'A checkout must POST to the reader checkout endpoint, got ' . $requests[0][0]);
    check(json_encode($payload) === $requests[0][1]['body'], 'A checkout must send the payload unchanged.');

    // A failing answer (an outage, then a dropped connection) is reported, never sent again.
    foreach (array(
        array('response' => array('code' => 503), 'body' => '{"message":"Service unavailable"}'),
        new WP_Error('http_request_failed', 'cURL error 28: timed out'),
    ) as $response) {
        $requests = array();
        check(false === $client->checkout('rdr_1', $payload), 'A failed checkout must return false.');
        check(1 === count($requests), 'A failed checkout must not be sent a second time, sent ' . count($requests) . '.');
        check(null !== $client->last_error(), 'A failed checkout must leave its reason in last_error().');
    }
    check('cURL error 28: timed out' === $client->last_error()->get_error_message(), 'last_error() must be the latest failure.');
    check(0 === SumUp::$checkout_calls, 'A checkout must not go through the SDK, called ' . SumUp::$checkout_calls . ' times.');

    // No merchant id (the profile cannot supply one): false, nothing sent, and no earlier refusal
    // left in last_error() for the provider to mistake for this checkout's.
    $profile = new CheckoutProfile('key');
    $client->set_profile_service($profile);
    $client->set_merchant_id('');
    $requests = array();
    check(false === $client->checkout('rdr_1', $payload), 'A checkout without a merchant id must return false.');
    check(array() === $requests, 'A checkout without a merchant id must send nothing.');
    check(null === $client->last_error(), 'A checkout without a merchant id must leave last_error() empty.');

    // A merchant id found through the profile reaches the HTTP request.
    $profile->merchant_code = 'MPROFILE';
    $response = array('response' => array('code' => 201), 'body' => '{"data":{"client_transaction_id":"client:456"}}');
    check(array('data' => array('client_transaction_id' => 'client:456')) === $client->checkout('rdr_1', $payload), 'A checkout with the profile\'s merchant id must succeed.');
    check(1 === count($requests) && 'https://api.sumup.com/v0.1/merchants/MPROFILE/readers/rdr_1/checkout' === $requests[0][0], 'The profile\'s merchant id must reach the checkout URL.');
    check(0 === SumUp::$checkout_calls, 'A checkout must not go through the SDK.');

    echo "PASS: a checkout skips the SDK and is sent once.\n";
}
