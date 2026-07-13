<?php
// phpcs:ignoreFile

function wp_json_encode($data) {
	return json_encode($data);
}

function wp_remote_request($url, $args) {
	return array(
		'response' => array('code' => 204),
		'body' => '',
	);
}

function is_wp_error($response) {
	return false;
}

function wp_remote_retrieve_body($response) {
	return $response['body'];
}

function wp_remote_retrieve_response_code($response) {
	return $response['response']['code'];
}

require_once __DIR__ . '/../../includes/Services/HttpClient.php';

class EmptySuccessHttpClient extends WCPOS\WooCommercePOS\SumUpTerminal\Services\HttpClient {
	public function post_empty_success() {
		return $this->post('/reader/terminate');
	}
}

$client = new EmptySuccessHttpClient('api-key');
$result = $client->post_empty_success();

if ($result !== array('success' => true)) {
	fwrite(STDERR, "Empty successful responses must remain distinguishable from request failures.\n");
	fwrite(STDERR, var_export($result, true) . "\n");
	exit(1);
}

echo "PASS: empty successful API responses are treated as success.\n";
