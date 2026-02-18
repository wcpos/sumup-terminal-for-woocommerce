<?php
// phpcs:ignoreFile

$ajax_handler = file_get_contents(__DIR__ . '/../../includes/AjaxHandler.php');

if ($ajax_handler === false) {
	fwrite(STDERR, "Failed to read AjaxHandler.php\n");
	exit(1);
}

$pair_method_start = strpos($ajax_handler, 'public function ajax_pair_reader');
$pair_method_end   = strpos($ajax_handler, 'public function ajax_unpair_reader');

if ($pair_method_start === false || $pair_method_end === false || $pair_method_end <= $pair_method_start) {
	fwrite(STDERR, "Could not isolate ajax_pair_reader() method body.\n");
	exit(1);
}

$pair_method = substr($ajax_handler, $pair_method_start, $pair_method_end - $pair_method_start);
$pattern     = '/create\s*\(\s*array\s*\((.*?)\)\s*\)/s';

if (!preg_match($pattern, $pair_method, $matches)) {
	fwrite(STDERR, "Could not find reader create payload in ajax_pair_reader().\n");
	exit(1);
}

$payload = $matches[1];

if (!preg_match("/'pairing_code'\s*=>/", $payload)) {
	fwrite(STDERR, "Payload is missing pairing_code.\n");
	exit(1);
}

if (!preg_match("/'name'\s*=>/", $payload)) {
	fwrite(STDERR, "Payload is missing required name field.\n");
	exit(1);
}

$mock_server = file_get_contents(__DIR__ . '/../../packages/test-server/server.js');

if ($mock_server === false) {
	fwrite(STDERR, "Failed to read packages/test-server/server.js\n");
	exit(1);
}

if (!preg_match('/\{\s*pairing_code,\s*name\s*\}\s*=\s*req\.body/', $mock_server)) {
	fwrite(STDERR, "Mock server does not read both pairing_code and name from request body.\n");
	exit(1);
}

if (!preg_match('/if \(!name\)/', $mock_server)) {
	fwrite(STDERR, "Mock server does not validate missing name in pairing endpoint.\n");
	exit(1);
}

if (!preg_match('/missing property \'name\'/', $mock_server)) {
	fwrite(STDERR, "Mock server does not return missing property 'name' validation message.\n");
	exit(1);
}

echo "PASS: pairing flow requires and sends the name field.\n";
