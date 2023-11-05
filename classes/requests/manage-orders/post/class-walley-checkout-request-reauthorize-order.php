<?php
/**
 * Class for the request to reauthorize an order.
 *
 * @package Collector_Checkout/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Reauthorize_Order class.
 */
class Walley_Checkout_Request_Reauthorize_Order extends Walley_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Reauthorize order';
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
		return $this->get_api_url_base() . "/manage/orders/{$walley_id}/reauthorize";
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
			'items'  => Collector_Checkout_Requests_Helper_Order_Om::get_order_lines( $this->order_id ),
		);

		return apply_filters( 'coc_order_reauthorize_args', $body, $this->order_id );
	}

	/**
	 * Processes the response checking for errors.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request url.
	 * @return array|WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parent_response = parent::process_response( $response, $request_args, $request_url );

		// Return WP_Error if Walley returns something else than 2xx response.
		if ( is_wp_error( $parent_response ) ) {
			return $parent_response;
		}

		return array(
			'status' => wp_remote_retrieve_response_code( $response ),
			'header' => wp_remote_retrieve_header( $response, 'location' ),
		);
	}
}
