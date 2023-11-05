<?php
/**
 * Class for the request to refund an order by amount.
 *
 * @package Collector_Checkout/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Refund_Order_By_Amount class.
 */
class Walley_Checkout_Request_Refund_Order_By_Amount extends Walley_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Refund order by amount';
		$this->order_id  = $this->arguments['order_id'];
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$order = wc_get_order( $this->order_id );
		$walley_id = $order->get_meta( '_collector_order_id', true );
		return $this->get_api_url_base() . "/manage/orders/{$walley_id}/refund";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$refund_order_id = $this->arguments['refund_order_id'];
		$refund_order    = wc_get_order( $refund_order_id );
		$body            = array(
			'amount'          => $this->arguments['amount'],
			'description'     => $this->arguments['reason'],
			'actionReference' => strval( $this->arguments['refund_order_id'] ),
		);

		return apply_filters( 'coc_order_refund_by_amount_args', $body, $this->order_id, $this->arguments['refund_order_id'] );
	}
}
