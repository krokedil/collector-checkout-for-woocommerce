<?php
/**
 * Class for the request to Create a widget token.
 *
 * @package Collector_Checkout/Classes/Requests/Widgets/Post
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Create_Widget_Token class.
 */
class Walley_Create_Widget_Token extends Walley_Checkout_Request_Post {

	/**
	 * Walley Create widget token request.
	 *
	 * @param  array $arguments  The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->set_environment_variables();
		$this->log_title = 'Create Widget Token';
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	protected function get_body() {
		return array( 'storeId' => $this->store_id );
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . '/widgets';
	}
}
