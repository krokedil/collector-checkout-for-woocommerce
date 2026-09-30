<?php
/**
 * Class for masking sensitive data out of the plugin log.
 *
 * @package Collector_Checkout/Classes
 */

defined( 'ABSPATH' ) || exit;

use KrokedilWalleyDeps\Krokedil\WpApi\FieldMasker;
use KrokedilWalleyDeps\Krokedil\WpApi\KeyMasker;

/**
 * What the plugin masks out of its logs, shared by the Walley API and the legacy request layers.
 *
 * The configured rules describe the Walley payloads we know about. The key names are the
 * safety net for everything else that reaches a log entry.
 */
class Walley_Log_Masking {
	/**
	 * The address fields kept readable.
	 */
	const ADDRESS_KEPT = array( 'postalCode', 'city', 'countryCode' );

	/**
	 * Key names masked wherever they appear, on top of the package defaults.
	 *
	 * @var string[]
	 */
	private static $key_names = array(
		'firstName',
		'lastName',
		'coAddress',
		'address2',
		'email',
		'phone',
		'nationalIdentificationNumber',
		'organizationNumber',
		'companyName',
		'invoiceReference',
		// The order pay redirect carries the order key, which grants access to the order.
		'redirectPageUri',
	);

	/**
	 * Widen the package key name masking with the names Walley uses.
	 *
	 * @return void
	 */
	public static function register() {
		KeyMasker::add_keys( self::$key_names );
	}

	/**
	 * The rules for a Walley request body.
	 *
	 * @return array
	 */
	public static function body_fields() {
		return array(
			'deliveryAddress'  => array( 'keep' => self::ADDRESS_KEPT ),
			'billingAddress'   => array( 'keep' => self::ADDRESS_KEPT ),
			'invoiceAddress'   => array( 'keep' => self::ADDRESS_KEPT ),
			'customer'         => array( 'keep' => array( 'deliveryAddress', 'billingAddress' ) ),
			'businessCustomer' => array( 'keep' => array( 'deliveryAddress', 'invoiceAddress' ) ),
			'redirectPageUri'  => 'mask',
		);
	}

	/**
	 * The rules for a Walley response body.
	 *
	 * @return array
	 */
	public static function response_fields() {
		return self::body_fields() + array( 'publicToken' => 'mask' );
	}

	/**
	 * The rules for a whole set of request args.
	 *
	 * @return array
	 */
	public static function request_fields() {
		return array(
			'headers' => array( 'Authorization' ),
			'body'    => self::body_fields(),
		);
	}

	/**
	 * Mask a set of request args.
	 *
	 * @param array|mixed $request_args The request args.
	 * @return array|string The masked args, or the failure marker.
	 */
	public static function mask_request( $request_args ) {
		try {
			// Decode the body that was really sent, so the rules can reach into it.
			if ( isset( $request_args['body'] ) && is_string( $request_args['body'] ) ) {
				$request_args['body'] = self::decode_body( $request_args['body'] );
			}

			return KeyMasker::mask( is_array( $request_args ) ? FieldMasker::mask( $request_args, self::request_fields() ) : $request_args );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Mask a decoded response body.
	 *
	 * @param array|string $body The decoded response body, or a message in its place.
	 * @return array|string The masked body, or the failure marker.
	 */
	public static function mask_response( $body ) {
		if ( empty( $body ) ) {
			return $body;
		}

		try {
			return KeyMasker::mask( is_array( $body ) ? FieldMasker::mask( $body, self::response_fields() ) : $body );
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Mask the customer token Walley addresses a subscription by in the path.
	 *
	 * @param string $request_url The request URL.
	 * @return string
	 */
	public static function mask_url( $request_url ) {
		try {
			$masked = preg_replace( '#/customer-tokens/[^/?]+#', '/customer-tokens/' . KeyMasker::REDACTED, (string) $request_url );
			return null === $masked ? KeyMasker::FAILED : $masked;
		} catch ( \Throwable $e ) {
			return KeyMasker::FAILED;
		}
	}

	/**
	 * Decode a json or form encoded body string into an array, or return it as it was.
	 *
	 * @param string $body The body string.
	 * @return array|string
	 */
	private static function decode_body( $body ) {
		$decoded = json_decode( $body, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// What http_build_query() produces: key=value pairs joined by '&', nothing else.
		if ( preg_match( '/^[^=&\s]+=[^&\s]*(?:&[^=&\s]+=[^&\s]*)*$/', $body ) ) {
			parse_str( $body, $decoded );
			return $decoded;
		}

		return $body;
	}
}
