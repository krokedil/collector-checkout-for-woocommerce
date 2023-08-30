<?php
/**
 * Class for the request to capture an order.
 *
 * @package Collector_Checkout/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Capture_Order class.
 */
class Walley_Checkout_Request_Capture_Order extends Walley_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Capture order';
		$this->order_id  = $this->arguments['order_id'];
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$walley_id = get_post_meta( $this->order_id, '_collector_order_id', true );
		return $this->get_api_url_base() . "/manage/orders/{$walley_id}/capture";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$order = wc_get_order( $this->order_id );
		$body  = array(
			'amount' => Collector_Checkout_Requests_Helper_Order_Om::get_order_lines_total_amount( $this->order_id ),
		);

		return apply_filters( 'coc_order_capture_args', $body, $this->order_id );
	}
}
