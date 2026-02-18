<?php
// phpcs:ignoreFile

$ajax_handler = file_get_contents(__DIR__ . '/../../includes/AjaxHandler.php');

if ($ajax_handler === false) {
	fwrite(STDERR, "Failed to read AjaxHandler.php\n");
	exit(1);
}

$pattern = '/create\s*\(\s*array\s*\((.*?)\)\s*\)/s';

if (!preg_match($pattern, $ajax_handler, $matches)) {
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
