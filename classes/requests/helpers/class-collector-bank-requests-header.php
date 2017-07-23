<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Requests_Header {
	public $auth = '';
	public function __construct( $body, $path ) {
		$calculate_auth = new Collector_Bank_Requests_Calculate_Auth();
		$this->auth = $calculate_auth->calculate_auth( $body, $path );
	}

	public function get() {
		$formatted_request_header = array(
			'Content-Type'  => 'application/json',
			'Authorization' => $this->auth,
		);

		return $formatted_request_header;
	}
}
