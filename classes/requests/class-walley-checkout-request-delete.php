<?php
/**
 * Base class for all DELETE requests.
 *
 * @package Collector_Checkout/Classes/Request
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 *  The main class for DELETE requests.
 */
abstract class Walley_Checkout_Request_Delete extends Walley_Checkout_Request {

	/**
	 * Walley_Checkout_Request_Delete constructor.
	 *
	 * @param  array $arguments  The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'DELETE';
	}

	/**
	 * Build and return proper request arguments for this request type.
	 *
	 * @return array Request arguments
	 */
	protected function get_request_args() {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			'timeout'    => apply_filters( 'walley_checkout_set_timeout', 10 ),
		);
	}

	/**
	 * Builds the request URL for a DELETE request.
	 *
	 * @return array
	 */
	abstract protected function get_request_url();
}
