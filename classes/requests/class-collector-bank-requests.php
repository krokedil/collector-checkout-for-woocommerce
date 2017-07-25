<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Requests {

	static $log = '';
	public $base_url = '';

	public function __construct() {
		$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
		$test_mode = $collector_settings['test_mode'];
		if ( 'yes' === $test_mode ) {
			$this->base_url = COLLECTOR_BANK_REST_TEST;
		} else {
			$this->base_url = COLLECTOR_BANK_REST_LIVE;
		}
	}

	public function request() {
		die( 'function Collector_Bank_Requests::request() must be over-ridden in a sub-class.' );
	}

	protected function request_header( $body, $path ) {
		$get_header = new Collector_Bank_Requests_Header( $body, $path );
		return $get_header->get();
	}

	protected function request_body() {
		die( 'function Collector_Bank_Requests::request_body() must be over-ridden in a sub-class.' );
	}

	protected function cart() {
		$collector_bank_requests_cart = new Collector_Bank_Requests_Cart();
		return $collector_bank_requests_cart->cart();
	}

	protected function fees() {
		$collector_bank_requests_fees = new Collector_Bank_Requests_Fees();
		return $collector_bank_requests_fees->fees();
	}

	public static function log( $message ) {
		$dibs_settings = get_option( 'woocommerce_collector_bank_settings' );
		if ( 'yes' === $dibs_settings['debug_mode'] ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'collector_bank', $message );
		}
	}
}
