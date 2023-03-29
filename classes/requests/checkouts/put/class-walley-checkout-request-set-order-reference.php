<?php
/**
 * Class for the request to set order reference.
 *
 * @package Collector_Checkout/Classes/Requests/PUT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Set_Order_Reference class.
 */
class Walley_Checkout_Request_Set_Order_Reference extends Walley_Checkout_Request_Put {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title  = 'Set order reference';
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
		return $this->get_api_url_base() . "/checkouts/{$this->private_id}/reference";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order             = wc_get_order( $this->order_id );
		$body['Reference'] = $order->get_order_number();
		return apply_filters( 'walley_set_order_reference_args', $body, $this->order_id );
	}
}
