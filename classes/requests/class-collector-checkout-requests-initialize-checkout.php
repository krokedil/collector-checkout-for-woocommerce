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
				$country_code          = 'SE';
				$this->store_id        = $collector_settings[ 'collector_merchant_id_se_' . $customer_type ];
				$this->delivery_module = isset( $collector_settings['collector_delivery_module_se'] ) ? $collector_settings['collector_delivery_module_se'] : 'no';
				break;
			case 'NOK':
				$country_code          = 'NO';
				$this->store_id        = $collector_settings[ 'collector_merchant_id_no_' . $customer_type ];
				$this->delivery_module = isset( $collector_settings['collector_delivery_module_no'] ) ? $collector_settings['collector_delivery_module_no'] : 'no';
				break;
			case 'DKK':
				$country_code          = 'DK';
				$this->store_id        = $collector_settings[ 'collector_merchant_id_dk_' . $customer_type ];
				$this->delivery_module = isset( $collector_settings['collector_delivery_module_dk'] ) ? $collector_settings['collector_delivery_module_dk'] : 'no';
				break;
			case 'EUR':
				$country_code          = 'FI';
				$this->store_id        = $collector_settings[ 'collector_merchant_id_fi_' . $customer_type ];
				$this->delivery_module = isset( $collector_settings['collector_delivery_module_fi'] ) ? $collector_settings['collector_delivery_module_fi'] : 'no';
				break;
			default:
				$country_code          = 'SE';
				$this->store_id        = $collector_settings[ 'collector_merchant_id_se_' . $customer_type ];
				$this->delivery_module = isset( $collector_settings['collector_delivery_module_se'] ) ? $collector_settings['collector_delivery_module_se'] : 'no';
				break;
		}
		$this->customer_type                = $customer_type;
		$this->country_code                 = $country_code;
		$this->terms_page                   = esc_url( get_permalink( wc_get_page_id( 'terms' ) ) );
		$this->activate_validation_callback = isset( $collector_settings['activate_validation_callback'] ) ? $collector_settings['activate_validation_callback'] : 'no';
	}

	private function get_request_args() {
		$request_args = array(
			'headers' => $this->request_header( $this->request_body(), $this->path ),
			'timeout' => 10,
			'body'    => $this->request_body(),
			'method'  => 'POST',
		);
		return $request_args;
	}

	public function request() {
		$request_url  = $this->base_url . '/checkout';
		$request_args = $this->get_request_args();

		$request = wp_remote_request( $request_url, $request_args );

		$response = wp_remote_request( $request_url, $request_args );
		$code     = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = CCO_WC()->logger->format_log( '', 'POST', 'CCO initialize payment', $request_args, $request_url, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		CCO_WC()->logger->log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	protected function request_body() {
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
			'cart'             => $this->cart(),
			'fees'             => $this->fees(),
		);
		if ( 'yes' === $this->activate_validation_callback ) {
			$formatted_request_body['validationUri'] = $validation_uri;
		}
		if ( 'yes' === $this->delivery_module ) {
			$formatted_request_body['profileName'] = 'Shipping';
		}
		return wp_json_encode( $formatted_request_body );
	}
}
