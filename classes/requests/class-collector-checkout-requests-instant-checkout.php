<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Checkout_Requests_Instant_Checkout extends Collector_Checkout_Requests {

	public $path = '/instantpurchase';
	public $store_id = '';
	public $terms_page = '';
	public $customer_type = '';
	public $customer_token = '';

	public function __construct( $customer_token, $customer_type = 'b2c' ) {
		parent::__construct();
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );

		switch ( get_woocommerce_currency() ) {
			case 'SEK' :
				$country_code = 'SE';
				$this->store_id = $collector_settings['collector_merchant_id_se_' . $customer_type];
				break;
			case 'NOK' :
				$country_code = 'NO';
				$this->store_id = $collector_settings['collector_merchant_id_no_' . $customer_type];
				break;
			case 'DKK' :
				$country_code = 'DK';
				$this->store_id = $collector_settings['collector_merchant_id_dk_' . $customer_type];
				break;
			case 'EUR' :
				$country_code = 'FI';
				$this->store_id = $collector_settings['collector_merchant_id_fi_' . $customer_type];
				break;
			default :
				$country_code = 'SE';
				$this->store_id = $collector_settings['collector_merchant_id_se_' . $customer_type];
				break;
		}

		$this->customer_type = $customer_type;
		$this->country_code = $country_code;
		$this->terms_page = esc_url( get_permalink( wc_get_page_id( 'terms' ) ) );
	}

	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header( $this->request_body(), $this->path ),
			'body'    => $this->request_body(),
			'method'  => 'POST',
		);
		$this->log( 'Collector instant checkout request args: ' . var_export( $request_args, true ) );
		return $request_args;
	}

	public function request() {
		$request_url = $this->base_url . '/instantpurchase';
		$request = wp_remote_request( $request_url, $this->get_request_args() );
		$request = wp_remote_retrieve_body( $request );
		$this->log( 'Collector instant checkout response: ' . var_export( $request, true ) . ' (Request endpoint: ' . $request_url . ')' );
		return $request;
	}

	protected function request_body() {
		$formatted_request_body = array(
			'storeId'           => $this->store_id,
			'countryCode'       => $this->country_code,
			'reference'         => '',
			'redirectPageUri'   => WC()->cart->get_checkout_url() . '?payment_successful=1',
			'merchantTermsUri'  => $this->terms_page,
			'notificationUri'   => get_site_url(),
			'customerToken'     => $this->customer_token,
			'cart'              => $this->cart(),
			'fees'              => $this->fees(),
		);
		$this->log( 'Collector instant checkout request body: ' . var_export( $formatted_request_body, true ) );
		return wp_json_encode( $formatted_request_body );
	}
}
