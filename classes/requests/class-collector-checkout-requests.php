<?php
/**
 * Main request class
 *
 * @package  Collector/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_Requests
 */
class Collector_Checkout_Requests {

	/**
	 * Static log variable.
	 *
	 * @var string
	 */
	protected static $log = '';

	/**
	 * The Collector base url.
	 *
	 * @var string
	 */
	public $base_url = '';

	/**
	 * Class constructor
	 */
	public function __construct() {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$test_mode          = $collector_settings['test_mode'];
		if ( 'yes' === $test_mode ) {
			$this->base_url = COLLECTOR_BANK_REST_TEST;
		} else {
			$this->base_url = COLLECTOR_BANK_REST_LIVE;
		}
	}


	/**
	 * All subclasses must implement this function.
	 *
	 * @return void
	 */
	public function request() {
		die( 'function Collector_Checkout_Requests::request() must be over-ridden in a sub-class.' );
	}

	/**
	 * Get the request headers.
	 *
	 * @param array  $body The request body.
	 * @param string $path The endpoint.
	 *
	 * @return array
	 */
	protected function request_header( $body, $path ) {
		$get_header = new Collector_Checkout_Requests_Header( $body, $path );
		return $get_header->get();
	}
	/*  phpcs:ignore
	protected function request_body( $order_id ) {
		die( 'function Collector_Checkout_Requests::request_body() must be over-ridden in a sub-class.' );
	}
	*/

	/**
	 * Gets the WC cart.
	 *
	 * @return array
	 */
	protected function cart() {
		$collector_checkout_requests_cart = new Collector_Checkout_Requests_Cart();
		return $collector_checkout_requests_cart->cart();
	}

	/**
	 * Gets fees.
	 *
	 * @return array|string
	 */
	protected function fees() {
		$collector_checkout_requests_fees = new Collector_Checkout_Requests_Fees();
		return $collector_checkout_requests_fees->fees();
	}

	/**
	 * A Helper function for logging request.
	 *
	 * @param string $message The message.
	 *
	 * @return void
	 */
	public static function log( $message ) {
		$dibs_settings = get_option( 'woocommerce_collector_checkout_settings' );
		if ( 'yes' === $dibs_settings['debug_mode'] ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'collector_checkout', $message );
		}
	}

	/**
	 * Checks response for any error.
	 *
	 * @param object $response The response.
	 * @param array  $request_args The request args.
	 * @param string $request_url The request URL.
	 * @return object|array
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		// Check if response is a WP_Error, and return it back if it is.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check the status code, if its not between 200 and 299 then its an error.
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			// Get the error messages.
			// @todo - remove this if?
			if ( null !== $response['response'] ) {
				$aco_error_code    = isset( $response['response']['code'] ) ? $response['response']['code'] . ' ' : '';
				$aco_error_message = isset( $response['response']['message'] ) ? $response['response']['message'] . ' ' : '';
				$error_message     = $aco_error_code . $aco_error_message;
			}

			if ( null !== json_decode( $response['body'], true ) ) {
				$response_body = json_decode( $response['body'], true );
				$error         = new WP_Error();
				$error->add( wp_remote_retrieve_response_code( $response ), $response_body['error']['message'] );
				foreach ( $response_body['error']['errors'] as $key => $collector_error ) {
					$error->add( $collector_error['reason'], $collector_error['message'] );
				}
			}
			return $error;
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
