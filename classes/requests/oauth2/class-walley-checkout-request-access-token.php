<?php
/**
 * Class for the request to request an access token.
 *
 * @package Collector_Checkout/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Access_Token class.
 */
class Walley_Checkout_Request_Access_Token extends Walley_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Access token';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . '/oauth2/v2.0/token';
	}

	/**
	 * Gets the request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'Content-Type' => 'application/x-www-form-urlencoded',
		);
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$client_id     = rawurlencode( $this->get_client_id() );
		$client_secret = rawurlencode( $this->get_api_secret() );
		$scope         = rawurlencode( $this->get_scope() );
		$fields        = "client_id=$client_id&client_secret=$client_secret&grant_type=client_credentials&scope=$scope";

		return $fields;
	}
}
