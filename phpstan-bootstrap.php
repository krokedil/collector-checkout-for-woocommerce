<?php
/**
 * Constant declarations for PHPStan static analysis only.
 *
 * Not loaded at runtime — the real values are defined in collector-checkout-for-woocommerce.php.
 * These declarations exist so PHPStan knows the constants exist when analyzing files
 * that are pulled in via dynamic include_once and therefore can't be traced statically.
 *
 * @package Collector_Checkout
 *
 * @phpcs:disable
 */

define( 'COLLECTOR_BANK_PLUGIN_DIR', __DIR__ );
define( 'COLLECTOR_BANK_PLUGIN_URL', 'https://example.com' );
define( 'COLLECTOR_BANK_VERSION', '0.0.0' );
define( 'COLLECTOR_BANK_REST_LIVE', 'https://example.com' );
define( 'COLLECTOR_BANK_REST_TEST', 'https://example.com' );
define( 'COLLECTOR_BANK_SOAP_LIVE', 'https://example.com' );
define( 'COLLECTOR_BANK_SOAP_TEST', 'https://example.com' );

// Missing constants that are set in WordPress but not in their stubs.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'OBJECT', 'OBJECT' );
