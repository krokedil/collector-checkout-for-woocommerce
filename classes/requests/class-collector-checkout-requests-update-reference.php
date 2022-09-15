<?php
/**
 * Set Order Reference
 *
 * @package  Collector/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_Requests_Update_Reference
 */
class Collector_Checkout_Requests_Update_Reference extends Collector_Checkout_Requests {
	/**
	 * The endpoint path
	 *
	 * @var string
	 */
	public $path = '';

	/**
	 * The WooCommerce order id.
	 *
	 * @var int
	 */
	public $order_id;

	/**
	 * Class constructor.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $private_id The private id.
	 * @param string $customer_type The customer type.
	 */
	public function __construct( $order_id, $private_id, $customer_type ) {
		parent::__construct();
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		switch ( get_woocommerce_currency() ) {
			case 'SEK':
				$store_id = $collector_settings[ 'collector_merchant_id_se_' . $customer_type ];
				break;
			case 'NOK':
				$store_id = $collector_settings[ 'collector_merchant_id_no_' . $customer_type ];
				break;
			case 'DKK':
				$store_id = $collector_settings[ 'collector_merchant_id_dk_' . $customer_type ];
				break;
			case 'EUR':
				$store_id = $collector_settings[ 'collector_merchant_id_fi_' . $customer_type ];
				break;
			default:
				$store_id = $collector_settings[ 'collector_merchant_id_se_' . $customer_type ];
				break;
		}
		$this->private_id = $private_id;
		$this->order_id   = $order_id;
		$this->path       = '/merchants/' . $store_id . '/checkouts/' . $private_id . '/reference';
	}

	/**
	 * Get the request args.
	 *
	 * @return array
	 */
	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header( $this->request_body(), $this->path ),
			'timeout' => 10,
			'body'    => $this->request_body(),
			'method'  => 'PUT',
		);

		return $request_args;
	}

	/**
	 * Make the request.
	 *
	 * @return array|object|void|WP_Error
	 */
	public function request() {
		$request_url  = $this->base_url . $this->path;
		$request_args = $this->get_request_args();
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = CCO_WC()->logger::format_log( $this->private_id, 'PUT', 'CCO update reference', $request_args, $request_url, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		CCO_WC()->logger::log( $log );

		$formatted_response = $this->process_response( $response, $request_args, $request_url );
		return $formatted_response;
	}

	/**
	 * Get the request body.
	 *
	 * @return false|string
	 */
	protected function request_body() {
		$formatted_request_body = array(
			'Reference' => $this->order_id,
		);
		return wp_json_encode( apply_filters( 'coc_request_body', $formatted_request_body ) );
	}
}
