<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Checkout_Requests_Update_Fees extends Collector_Checkout_Requests {
	public $path = '';

	public function __construct( $private_id, $customer_type ) {
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
		$this->path = '/merchants/' . $store_id . '/checkouts/' . $private_id . '/fees';
	}

	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header( $this->request_body(), $this->path ),
			'timeout' => 10,
			'body'    => $this->request_body(),
			'method'  => 'PUT',
		);
		$this->log( 'Collector update fees request args (to ' . $this->path . '): ' . stripslashes_deep( json_encode( $request_args ) ) );
		return $request_args;
	}

	public function request() {
		$request_url = $this->base_url . $this->path;
		$request     = wp_remote_request( $request_url, $this->get_request_args() );
		if ( is_wp_error( $request ) ) {
			$this->log( 'Collector update fees request response ERROR: ' . stripslashes_deep( json_encode( $request ) ) . ' (Request endpoint: ' . $request_url . ')' );
		} else {
			$this->log( 'Collector update fees request response: ' . stripslashes_deep( json_encode( $request ) ) . ' (Request endpoint: ' . $request_url . ')' );
		}
		return $request;
	}

	protected function request_body() {
		$fees                   = $this->fees();
		$formatted_request_body = array();

		if ( isset( $fees['directinvoicenotification'] ) ) {
			$formatted_request_body ['directinvoicenotification'] = $fees['directinvoicenotification'];
		}

		if ( isset( $fees['shipping'] ) && ! empty( $fees['shipping'] ) ) {
			$formatted_request_body['shipping'] = $fees['shipping'];
		}
		return wp_json_encode( $formatted_request_body );
	}
}
