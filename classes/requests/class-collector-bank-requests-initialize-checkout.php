<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Requests_Initialize_Checkout extends Collector_Bank_Requests {

	public $path = '/checkout';

	public function __construct() {
	}

	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header( $this->request_body(), $this->path ),
			'body'    => $this->request_body(),
			'method'  => 'POST',
		);
		return $request_args;
	}

	public function request() {
		$request_url = 'https://checkout-api-uat.collector.se/checkout';
		$request = wp_remote_request( $request_url, $this->get_request_args() );
		return $request;
	}

	protected function request_body() {
		$formatted_request_body = array(
			'storeId'           => '873',
			'countryCode'       => 'SE',
			'reference'         => '',
			'redirectPageUri'   => WC()->cart->get_checkout_url() . '?payment_successful=1',
			'merchantTermsUri'  => 'http://krokedil.dibs.ngrok.io/terms',
			'notificationUri'   => 'http://krokedil.dibs.ngrok.io',
			'cart'              => $this->cart(),
			'fees'              => $this->fees(),
		);
		return wp_json_encode( $formatted_request_body );
	}
}
