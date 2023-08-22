<?php
/**
 * Class for the request to update checkout.
 *
 * @package Collector_Checkout/Classes/Requests/PUT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Update_Checkout class.
 */
class Walley_Checkout_Request_Update_Checkout extends Walley_Checkout_Request_Put {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title  = 'Update checkout';
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
		return $this->get_api_url_base() . "/checkouts/{$this->private_id}/";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {

		$body = array(
			'cart' => empty( $this->order_id ) ? $this->cart()['items'] : CCO_WC()->order_items->get_order_lines( $this->order_id )['items'],
			'fees' => empty( $this->order_id ) ? Walley_Checkout_Requests_Fees_Helper::fees() : CCO_WC()->order_fees->get_order_fees( $this->order_id ),
		);

		// Maybe add shipping classes.
		if ( ! empty( walley_get_cart_shipping_classes() ) ) {
			$body['shippingProperties'] = walley_get_cart_shipping_classes();
		}

		// Maybe add free shipping coupon.
		if ( ! empty( walley_cart_contain_free_shipping_coupon() ) ) {
			$body['shippingProperties']['free_shipping'] = true;
		}

		return apply_filters( 'walley_update_checkout_args', $body, $this->order_id );
	}
}
