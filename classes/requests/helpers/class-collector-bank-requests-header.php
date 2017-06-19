<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Requests_Header {
	public static function get( $body, $path ) {
		$formatted_request_header = array(
			'Content-Type'  => 'application/json',
			'Authorization' => Collector_Bank_Requests_Calculate_Auth::calculate_auth( $body, $path ),
		);

		return $formatted_request_header;
	}
}
