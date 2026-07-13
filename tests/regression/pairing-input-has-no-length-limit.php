<?php
// phpcs:ignoreFile

$gateway = file_get_contents(__DIR__ . '/../../includes/Gateway.php');

if ($gateway === false) {
	fwrite(STDERR, "Failed to read Gateway.php\n");
	exit(1);
}

preg_match_all('/<input type="text" id="sumup-pairing-code"[^>]*>/', $gateway, $matches);

if (count($matches[0]) !== 3) {
	fwrite(STDERR, "Expected all three pairing forms to be present.\n");
	exit(1);
}

foreach ($matches[0] as $input) {
	if (stripos($input, 'maxlength=') !== false) {
		fwrite(STDERR, "Pairing inputs must not hard-code SumUp's pairing-code length.\n");
		exit(1);
	}
}

echo "PASS: pairing inputs do not impose a client-side length limit.\n";
