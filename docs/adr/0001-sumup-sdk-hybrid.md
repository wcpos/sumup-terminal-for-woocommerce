# ADR 0001: Use prefixed SumUp SDK on PHP 8.2+ with WordPress HTTP compatibility fallback

## Status

Accepted

## Context

This plugin integrates SumUp Terminal reader operations for WooCommerce. The previous Composer dependency, `sumup/sumup-ecom-php-sdk`, was unused and did not provide first-class Terminal reader operations.

SumUp now publishes `sumup/sumup-php`, which includes reader support for listing, pairing, retrieving, deleting, checkout creation, checkout termination, and reader status. That SDK requires PHP 8.2+ and ext-curl, while this plugin continues to support WordPress sites running PHP 7.4+.

WordPress also provides its own HTTP API, `wp_remote_request()`, which handles WordPress-specific hosting concerns such as proxies, filters, and transport selection. The official SDK uses its own cURL client; this is an intentional trade-off on PHP 8.2+ unless a future WordPress-backed SDK HTTP client is added.

## Decision

The plugin uses a prefixed copy of the official `sumup/sumup-php` SDK for supported Terminal reader operations when PHP 8.2+ is available and the prefixed SDK is bundled.

On PHP 7.4-8.1, or when the prefixed SDK is unavailable, the plugin uses its WordPress HTTP compatibility client. Payments can still work normally in compatibility mode.

The SDK is loaded through a PHP-version-guarded autoloader so older PHP runtimes do not parse PHP 8.2-only SDK files.

## Consequences

- Users on PHP 8.2+ get the official SDK path for supported operations.
- Users on older PHP versions keep the existing direct HTTP behavior.
- The plugin maintains two reader API transports, so behavior must remain covered by shared service-level tests or regression checks.
- Operations not exposed by the SDK, such as connect and disconnect, remain on the WordPress HTTP client.
- The settings UI explains when the compatibility client is active because the server PHP version is below 8.2.
