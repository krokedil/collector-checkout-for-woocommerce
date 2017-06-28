<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Requests_Update_Reference extends Collector_Bank_Requests {
	public $path = '';

	public $order_id;

	public function __construct( $order_id, $private_id ) {
		$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
		$store_id = $collector_settings['collector_merchant_id'];
		$this->order_id = $order_id;
		$this->path = '/merchants/' . $store_id . '/checkouts/' . $private_id . '/reference';
	}

	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header( $this->request_body(), $this->path ),
			'body'    => $this->request_body(),
			'method'  => 'PUT',
		);
		return $request_args;
	}

	public function request() {
		$request_url = 'https://checkout-api-uat.collector.se' . $this->path;
		$request = wp_remote_request( $request_url, $this->get_request_args() );
		return $request;
	}

	protected function request_body() {
		$formatted_request_body = array(
			'Reference'         => $this->order_id,
		);
		return wp_json_encode( $formatted_request_body );
	}
}
