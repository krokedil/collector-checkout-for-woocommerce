<?php
/**
 * Class for the request to cancel customer token.
 *
 * @package Collector_Checkout/Classes/Requests/DELETE
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Cancel_Customer_token class.
 */
class Walley_Checkout_Request_Cancel_Customer_Token extends Walley_Checkout_Request_Delete {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Cancel customer token';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . "/purchase/customer-tokens/{$this->arguments['token']}";
	}
}
