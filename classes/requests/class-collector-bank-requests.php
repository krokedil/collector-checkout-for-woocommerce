<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Requests {
	public function __construct() {

	}

	public function request() {
		die( 'function Collector_Bank_Requests::request() must be over-ridden in a sub-class.' );
	}

	protected function request_header( $body, $path ) {
		return Collector_Bank_Requests_Header::get( $body, $path );
	}

	protected function request_body() {
		die( 'function Collector_Bank_Requests::request_body() must be over-ridden in a sub-class.' );
	}

	protected function cart() {
		return Collector_Bank_Requests_Cart::cart();
	}

	protected function fees() {
		return Collector_Bank_Requests_Fees::fees();
	}
}
