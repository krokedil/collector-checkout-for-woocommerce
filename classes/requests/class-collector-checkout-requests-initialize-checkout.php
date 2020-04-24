<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Checkout_Requests_Initialize_Checkout extends Collector_Checkout_Requests {

	public $path          = '/checkout';
	public $store_id      = '';
	public $country_code  = '';
	public $terms_page    = '';
	public $customer_type = '';

	public function __construct( $customer_type = 'b2c' ) {
		parent::__construct();
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		switch ( get_woocommerce_currency() ) {
			case 'SEK':
				$country_code   = 'SE';
				$this->store_id = $collector_settings[ 'collector_merchant_id_se_' . $customer_type ];
				break;
			case 'NOK':
				$country_code   = 'NO';
				$this->store_id = $collector_settings[ 'collector_merchant_id_no_' . $customer_type ];
				break;
			case 'DKK':
				$country_code   = 'DK';
				$this->store_id = $collector_settings[ 'collector_merchant_id_dk_' . $customer_type ];
				break;
			case 'EUR':
				$country_code   = 'FI';
				$this->store_id = $collector_settings[ 'collector_merchant_id_fi_' . $customer_type ];
				break;
			default:
				$country_code   = 'SE';
				$this->store_id = $collector_settings[ 'collector_merchant_id_se_' . $customer_type ];
				break;
		}
		$this->customer_type = $customer_type;
		$this->country_code  = $country_code;
		$this->terms_page    = esc_url( get_permalink( wc_get_page_id( 'terms' ) ) );
	}

	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header( $this->request_body(), $this->path ),
			'timeout' => 10,
			'body'    => $this->request_body(),
			'method'  => 'POST',
		);
		$this->log( 'Collector Init checkout request args: ' . stripslashes_deep( json_encode( $request_args ) ) );

		return $request_args;
	}

	public function request() {
		$request_url = $this->base_url . '/checkout';
		$request     = wp_remote_request( $request_url, $this->get_request_args() );
		if ( is_wp_error( $request ) ) {
			$this->log( 'Collector init checkout request response ERROR: ' . stripslashes_deep( json_encode( $request->get_error_message() ) ) . ' (Request endpoint: ' . $request_url . ')' );
		} elseif ( 200 !== $request['response']['code'] ) {
			$this->log( 'Collector init checkout request response ERROR: ' . stripslashes_deep( json_encode( $request ) ) . ' (Request endpoint: ' . $request_url . ')' );
			$request = new WP_Error( $request['response']['code'], $request['response']['message'] );
		} else {
			$this->log( 'Collector init checkout request response: ' . stripslashes_deep( json_encode( $request ) ) . ' (Request endpoint: ' . $request_url . ')' );
			$request = wp_remote_retrieve_body( $request );
		}

		return $request;
	}

	protected function request_body() {
		$collector_checkout_sessions = new Collector_Checkout_Sessions();

		// Set validation URI query args.
		$validation_uri = add_query_arg(
			array(
				'private-id'    => '{checkout.id}',
				'public-token'  => '{checkout.publictoken}',
				'customer-type' => $this->customer_type,
			),
			get_home_url() . '/wc-api/Collector_WC_Validation/'
		);

		$formatted_request_body = array(
			'storeId'          => $this->store_id,
			'countryCode'      => $this->country_code,
			'reference'        => '',
			'redirectPageUri'  => add_query_arg(
				array(
					'payment_successful' => '1',
					'public-token'       => '{checkout.publictoken}',
				),
				wc_get_checkout_url()
			),
			'merchantTermsUri' => $this->terms_page,
			'notificationUri'  => add_query_arg(
				array(
					'notification-callback' => '1',
					'private-id'            => '{checkout.id}',
					'public-token'          => '{checkout.publictoken}',
					'customer-type'         => $this->customer_type,
				),
				get_home_url() . '/wc-api/Collector_Checkout_Gateway/'
			),
			'validationUri'    => $validation_uri,
			'cart'             => $this->cart(),
			'fees'             => $this->fees(),
		);

		return wp_json_encode( $formatted_request_body );
	}
}
