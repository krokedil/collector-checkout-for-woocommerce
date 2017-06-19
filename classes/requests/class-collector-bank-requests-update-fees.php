<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Requests_Update_Fees extends Collector_Bank_Requests {
	public $path = '';

	public function __construct( $store_id, $private_id ) {
		$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
		$store_id = $collector_settings['collector_merchant_id'];
		$this->path = '/merchants/' . $store_id . '/checkouts/' . $private_id . '/fees';
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
		$fees = $this->fees();
		$formatted_request_body = array(
			'shipping'                  => $fees['shipping'],
			'directinvoicenotification' => $fees['directinvoicenotification'],
		);
		return wp_json_encode( $formatted_request_body );
	}
}
