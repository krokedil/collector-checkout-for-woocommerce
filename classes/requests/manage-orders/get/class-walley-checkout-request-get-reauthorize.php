<?php
/**
 * Class for the request to get the result of a reauthorize request.
 *
 * @package Collector_Checkout/Classes/Requests/GET
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Get_Reauthorize class.
 */
class Walley_Checkout_Request_Get_Reauthorize extends Walley_Checkout_Request_Get {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Get Reauthorize result';
		$this->order_id  = $this->arguments['order_id'] ?? '';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . $this->arguments['location'];
	}
}
