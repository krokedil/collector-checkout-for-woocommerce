<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Checkout_Requests_Get_Checkout_Information extends Collector_Checkout_Requests {

	public $path = '';

	public function __construct( $private_id, $customer_type ) {
		parent::__construct();
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		switch ( get_woocommerce_currency() ) {
			case 'SEK' :
				$store_id = $collector_settings['collector_merchant_id_se_' . $customer_type];
				break;
			case 'NOK' :
				$store_id = $collector_settings['collector_merchant_id_no_' . $customer_type];
				break;
			default :
				$store_id = $collector_settings['collector_merchant_id_se_' . $customer_type];
				break;
		}
		$this->path = '/merchants/' . $store_id . '/checkouts/' . $private_id;
	}

	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header( '', $this->path ),
			'timeout' => 10,
			'method'  => 'GET',
		);
		$this->log( 'Collector get checkout information request args (to ' . $this->path . '): ' . stripslashes_deep( json_encode( $request_args ) ) );
		return $request_args;
	}

	public function request() {
		$request_url = $this->base_url . $this->path;
		$request = wp_remote_request( $request_url, $this->get_request_args() );
		$request = wp_remote_retrieve_body( $request );
		$this->log( 'Collector get checkout information request response: ' . stripslashes_deep( json_encode( $request ) ) );
		return $request;
	}
}
