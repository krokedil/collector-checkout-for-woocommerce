<?php //phpcs:ignore
/**
 * Class for issuing API request.
 *
 * @package Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walley_Checkout_API class.
 *
 * Class for handling Walley API requests.
 */
class Walley_Checkout_API {

	/**
	 * Returns a access token.
	 *
	 * @return array|WP_Error
	 */
	public function get_access_token() {
		$request  = new Walley_Checkout_Request_Access_Token( array() );
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Initialize Walley checkout.
	 *
	 * @param array $args Data passed to init request.
	 * @return array|WP_Error
	 */
	public function initialize_walley_checkout( $args ) {
		$request  = new Walley_Checkout_Request_Initialize_Checkout( $args );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Update Walley checkout.
	 *
	 * @param array $args Data passed to init request.
	 * @return array|WP_Error
	 */
	public function update_walley_checkout( $args ) {
		$request  = new Walley_Checkout_Request_Update_Checkout( $args );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Update Walley checkout cart.
	 *
	 * @param array $args Data passed to init request.
	 * @return array|WP_Error
	 */
	public function update_walley_cart( $args ) {
		$request  = new Walley_Checkout_Request_Update_Cart( $args );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Update Walley checkout fees.
	 *
	 * @param array $args Data passed to init request.
	 * @return array|WP_Error
	 */
	public function update_walley_fees( $args ) {
		$request  = new Walley_Checkout_Request_Update_Fees( $args );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Update Walley checkout metadata.
	 *
	 * @param array $args Data passed to init request.
	 * @return array|WP_Error
	 */
	public function update_walley_metadata( $args ) {
		$request  = new Walley_Checkout_Request_Update_Metadata( $args );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Get Walley checkout session.
	 *
	 * @param array $args Data passed to init request.
	 * @return array|WP_Error
	 */
	public function get_walley_checkout( $args ) {
		$request  = new Walley_Checkout_Request_Get_Checkout( $args );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Set order reference in Walley order.
	 *
	 * @param array $args Data passed to init request.
	 * @return array|WP_Error
	 */
	public function set_order_reference_in_walley( $args ) {
		$request  = new Walley_Checkout_Request_Set_Order_Reference( $args );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Get Walley order.
	 *
	 * @param string $walley_id The Walley transaction id.
	 * @return array|WP_Error
	 */
	public function get_walley_order( $walley_id ) {
		// If the walley ID is missing, the request will still be successful as Walley will return the 10 most recent transactions which is not the desired behavior.
		if ( empty( $walley_id ) ) {
			return new WP_Error( 'walley_get_order_error', __( 'Walley order id is missing.', 'collector-checkout-for-woocommerce' ) );
		}

		$args     = array( 'walley_id' => $walley_id );
		$request  = new Walley_Checkout_Request_Get_Order( $args );
		$response = $request->request();
		return $response;
	}

	/**
	 * Reauthorize Walley order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array|WP_Error
	 */
	public function reauthorize_walley_order( $order_id ) {
		$args     = array( 'order_id' => $order_id );
		$request  = new Walley_Checkout_Request_Reauthorize_Order( $args );
		$response = $request->request();
		return $response;
	}

	/**
	 * Capture Walley order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array|WP_Error
	 */
	public function capture_walley_order( $order_id ) {
		$args     = array( 'order_id' => $order_id );
		$request  = new Walley_Checkout_Request_Capture_Order( $args );
		$response = $request->request();
		return $response;
	}

	/**
	 * Part capture Walley order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array|WP_Error
	 */
	public function part_capture_walley_order( $order_id ) {
		$args     = array( 'order_id' => $order_id );
		$request  = new Walley_Checkout_Request_Part_Capture_Order( $args );
		$response = $request->request();
		return $response;
	}

	/**
	 * Cancel Walley order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array|WP_Error
	 */
	public function cancel_walley_order( $order_id ) {
		$args     = array( 'order_id' => $order_id );
		$request  = new Walley_Checkout_Request_Cancel_Order( $args );
		$response = $request->request();
		return $response;
	}

	/**
	 * Refund Walley order.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $amount The refund amount.
	 * @param string $reason The refund reason.
	 * @return array|WP_Error
	 */
	public function refund_walley_order( $order_id, $amount = null, $reason = '' ) {
		$order        = wc_get_order( $order_id );
		$refund_order = $order->get_refunds()[0];

		$args     = array(
			'order_id'        => $order_id,
			'refund_order_id' => $refund_order->get_id(),
			'amount'          => $amount,
			'reason'          => $reason,
		);
		$request  = new Walley_Checkout_Request_Refund_Order( $args );
		$response = $request->request();
		return $response;
	}

	/**
	 * Refund Walley order by amount.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $amount The refund amount.
	 * @param string $reason The refund reason.
	 * @return array|WP_Error
	 */
	public function refund_walley_order_by_amount( $order_id, $amount = null, $reason = '' ) {

		$refund_order_id = Collector_Checkout_Create_Refund_Data::get_refunded_order_id( $order_id );

		$args     = array(
			'order_id'        => $order_id,
			'refund_order_id' => $refund_order_id,
			'amount'          => $amount,
			'reason'          => $reason,
		);
		$request  = new Walley_Checkout_Request_Refund_Order_By_Amount( $args );
		$response = $request->request();
		return $response;
	}

	/**
	 * Get a reauthorize result.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $location The location of the reauthorize request result.
	 *
	 * @return array|WP_Error
	 */
	public function get_reauthorize_result( $order_id, $location ) {
		$args    = array(
			'order_id' => $order_id,
			'location' => $location,
		);
		$request = new Walley_Checkout_Request_Get_Reauthorize( $args );

		$response = $request->request();
		return $response;
	}

	/**
	 * Create a widget token.
	 *
	 * @return array|WP_Error
	 */
	public function create_widget_token() {
		$request  = new Walley_Create_Widget_Token( array() );
		$response = $request->request();
		return $response;
	}

	/**
	 * Checks for WP Errors and returns either the response as array.
	 *
	 * @param array $response The response from the request.
	 * @return array|WP_Error
	 */
	private function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			if ( ! is_admin() ) {
				walley_print_error_message( $response );
			}
		}
		return $response;
	}
}
