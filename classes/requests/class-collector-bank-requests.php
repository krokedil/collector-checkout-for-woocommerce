<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Requests {

	static $log = '';

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

	public static function log( $message ) {
		$dibs_settings = get_option( 'woocommerce_dibs_easy_settings' );
		if ( 'yes' === $dibs_settings['debug_mode'] ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'collector_bank', $message );
		}
	}
}
