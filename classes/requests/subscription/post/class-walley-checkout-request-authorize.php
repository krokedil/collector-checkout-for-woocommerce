<?php
/**
 * Class for the request to create an authorization.
 *
 * @package Collector_Checkout/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Create_Authorization class.
 *
 * @see https://dev.walleypay.com/docs/checkout/tokenization/authorization
 */
class Walley_Checkout_Request_Create_Authorization extends Walley_Checkout_Request_Post {

	/**
	 * The customer token.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * The subscription ID.
	 *
	 * @var string|int
	 */
	private $subscription_id;

	/**
	 * The subscription object.
	 *
	 * @var WC_Order
	 */
	private $renewal_order;

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title     = 'Create authorization';
		$this->renewal_order = $this->arguments['order'];
		$this->token         = $this->arguments['token'];
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return "{$this->get_api_url_base()}/purchase/authorizations";
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		$body = array(
			'customerToken' => $this->token,
			'order'         => array(
				'items'       => Collector_Checkout_Requests_Helper_Order_Om::get_order_lines( $this->renewal_order->get_id() ),
				'currency'    => $this->renewal_order->get_currency(),
				'captureMode' => 'Manual', // Set to 'Manual' (instead of 'Auto') since a renewal order will be created for this subscription; the subscription will be managed through that order instead.
			),
		);

		return apply_filters( 'coc_create_authorization_args', $body, $this->subscription_id );
	}


	/**
	 * Processes the response checking for errors.
	 *
	 * Since we need the status code as well as the body, we override the parent method.
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
			'body'   => json_decode( wp_remote_retrieve_body( $response ), true ),
		);
	}
}
