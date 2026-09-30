<?php
/**
 * Logger class file.
 *
 * @package Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use KrokedilWalleyDeps\Krokedil\WpApi\KeyMasker;

/**
 * Logger class.
 */
class Collector_Checkout_Logger {
	/**
	 * Log message string
	 *
	 * @var $log
	 */
	public static $log;

	/**
	 * Logs an event.
	 *
	 * @param array|string $data The data to log.
	 */
	public static function log( $data ) {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		if ( 'yes' !== $collector_settings['debug_mode'] ) {
			return;
		}

		try {
			$message = KeyMasker::mask( self::format_data( $data ) );
		} catch ( \Throwable $e ) {
			$message = array( 'error' => KeyMasker::FAILED );
		}

		if ( empty( self::$log ) ) {
			self::$log = new WC_Logger();
		}
		self::$log->add( 'walley_checkout', wp_json_encode( $message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Formats the log data to prevent json error.
	 *
	 * @param array|string $data The data to log.
	 * @return array|string
	 */
	public static function format_data( $data ) {
		if ( isset( $data['request']['body'] ) && is_string( $data['request']['body'] ) ) {
			$request_body            = json_decode( $data['request']['body'], true );
			$data['request']['body'] = ( ! empty( $request_body ) ) ? $request_body : $data['request']['body'];
		}

		return $data;
	}

	/**
	 * Formats the log data to be logged.
	 *
	 * @param string $checkout_id The gateway Checkout ID.
	 * @param string $method The method.
	 * @param string $title The title for the log.
	 * @param array  $request_args The request args.
	 * @param string $request_url The request url.
	 * @param array  $response The response.
	 * @param string $code The status code.
	 * @return array
	 */
	public static function format_log( $checkout_id, $method, $title, $request_args, $request_url, $response, $code ) {
		// If the response contains a body, try to decode it from JSON.
		if ( is_array( $response ) && isset( $response['body'] ) ) {
			$decoded_response = json_decode( $response['body'], true );
			if ( null !== $decoded_response ) {
				$response['body'] = $decoded_response;
			}
		}

		return array(
			'id'             => $checkout_id,
			'type'           => $method,
			'title'          => $title,
			'request_url'    => Walley_Log_Masking::mask_url( $request_url ),
			'request'        => Walley_Log_Masking::mask_request( $request_args ),
			'response'       => array(
				'body' => Walley_Log_Masking::mask_response( $response ),
				'code' => $code,
			),
			'timestamp'      => date( 'Y-m-d H:i:s' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
			'stack'          => self::get_stack(),
			'plugin_version' => COLLECTOR_BANK_VERSION,
		);
	}

	/**
	 * Gets the stack for the request.
	 *
	 * @return array
	 */
	public static function get_stack() {
		$debug_data = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- Data is not used for display.
		$stack      = array();
		foreach ( $debug_data as $data ) {
			$extra_data = '';
			if ( ! in_array( $data['function'], array( 'get_stack', 'format_log' ), true ) ) {
				if ( in_array( $data['function'], array( 'do_action', 'apply_filters' ), true ) ) {
					if ( isset( $data['object'] ) && $data['object'] instanceof WP_Hook ) {
						$priority   = $data['object']->current_priority();
						$name       = is_array( $data['object']->current() ) ? key( $data['object']->current() ) : '';
						$extra_data = $name . ' : ' . $priority;
					}
				}
			}
			$stack[] = $data['function'] . $extra_data;
		}
		return $stack;
	}
}
