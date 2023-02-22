<?php
/**
 * Class for the request to update cart.
 *
 * @package Collector_Checkout/Classes/Requests/PUT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Update_Cart class.
 */
class Walley_Checkout_Request_Update_Cart extends Walley_Checkout_Request_Put {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title  = 'Update cart';
		$this->order_id   = $arguments['order_id'] ?? '';
		$this->private_id = $arguments['private_id'] ?? '';
		$this->set_environment_variables( $arguments );
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . "/checkouts/{$this->private_id}/cart";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {

		$body = $this->cart();
		return apply_filters( 'walley_update_cart_args', $body, $this->order_id );
	}
}
