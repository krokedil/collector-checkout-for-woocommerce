<?php
/**
 * Class for the request to update fees.
 *
 * @package Collector_Checkout/Classes/Requests/PUT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Update_Fees class.
 */
class Walley_Checkout_Request_Update_Fees extends Walley_Checkout_Request_Put {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title  = 'Update fees';
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
		return $this->get_api_url_base() . "/checkouts/{$this->private_id}/fees";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {

		$fees = Walley_Checkout_Requests_Fees_Helper::fees();
		$body = array();

		if ( isset( $fees['directinvoicenotification'] ) ) {
			$body ['directinvoicenotification'] = $fees['directinvoicenotification'];
		}

		if ( isset( $fees['shipping'] ) && ! empty( $fees['shipping'] ) ) {
			$body['shipping'] = $fees['shipping'];
		}

		if ( ! empty( $body ) ) {
			return apply_filters( 'walley_update_fees_args', $body, $this->order_id );
		} else {
			return false;
		}
	}
}
