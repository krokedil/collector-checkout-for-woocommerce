<?php
/**
 * Base class for all PUT requests.
 *
 * @package Collector_Checkout/Classes/Request
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 *  The main class for PUT requests.
 */
abstract class Walley_Checkout_Request_Put extends Walley_Checkout_Request {

	/**
	 * Walley_Checkout_Request_Put constructor.
	 *
	 * @param  array $arguments  The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'PUT';
	}

	/**
	 * Build and return proper request arguments for this request type.
	 *
	 * @return array Request arguments
	 */
	protected function get_request_args() {

		$body = wp_json_encode( apply_filters( 'walley_checkout_request_args', $this->get_body() ) );

		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			'timeout'    => apply_filters( 'walley_checkout_set_timeout', 10 ),
			'body'       => $body,
		);
	}

	/**
	 * Builds the request args for a PUT request.
	 *
	 * @return array
	 */
	abstract protected function get_body();
}
