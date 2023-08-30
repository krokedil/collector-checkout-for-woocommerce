<?php
/**
 * To initialize a Checkout session
 *
 * @package  Collector/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_Requests_Initialize_Checkout
 */
class Collector_Checkout_Requests_Initialize_Checkout extends Collector_Checkout_Requests {

	/**
	 * The endpoint path
	 *
	 * @var string
	 */
	public $path = '/checkout';
	/**
	 * The store id.
	 *
	 * @var mixed|string
	 */
	public $store_id = '';

	/**
	 * The country code.
	 *
	 * @var string
	 */
	public $country_code = '';
	/**
	 * Term page
	 *
	 * @var string
	 */
	public $terms_page = '';
	/**
	 * Customer type
	 *
	 * @var string
	 */
	public $customer_type = '';

	/**
	 * Class constructor.
	 *
	 * @param string $customer_type The customer type.
	 */
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
		$this->customer_type = $customer_type;
		$this->country_code  = $country_code;
		$this->currency      = get_woocommerce_currency();
		$this->terms_page    = esc_url( get_permalink( wc_get_page_id( 'terms' ) ) );
	}

	/**
	 * Get the request args.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	private function get_request_args( $order_id ) {
		$request_args = array(
			'headers' => $this->request_header( $this->request_body( $order_id ), $this->path ),
			'timeout' => 10,
			'body'    => $this->request_body( $order_id ),
			'method'  => 'POST',
		);
		return $request_args;
	}

	/**
	 * Make the request.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array|object|void|WP_Error
	 */
	public function request( $order_id = null ) {
		$request_url  = $this->base_url . '/checkout';
		$request_args = $this->get_request_args( $order_id );

		$response = wp_remote_request( $request_url, $request_args );
		$code     = wp_remote_retrieve_response_code( $response );

		// Log the request.
		$log = CCO_WC()->logger::format_log( '', 'POST', 'CCO initialize payment', $request_args, $request_url, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		CCO_WC()->logger::log( $log );

		$formatted_response = $this->process_response( $response, $request_args, $request_url );
		return $formatted_response;
	}

	/**
	 * Get the request body.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return false|string
	 */
	protected function request_body( $order_id ) {

		$formatted_request_body = array(
			'storeId'          => $this->store_id,
			'countryCode'      => $this->country_code,
			'reference'        => '',
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
			'cart'             => ( null === $order_id ) ? $this->cart() : CCO_WC()->order_items->get_order_lines( $order_id ),
			'fees'             => ( null === $order_id ) ? $this->fees() : CCO_WC()->order_fees->get_order_fees( $order_id ),
		);

		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );

		// Only send profileName if this is a purchase from the checkout.
		if ( null === $order_id ) {
			if ( 'yes' === $this->delivery_module ) {
				$formatted_request_body['profileName'] = trim( $collector_settings[ 'collector_custom_profile_' . strtolower( $this->country_code ) ] );
				if ( empty( $formatted_request_body['profileName'] ) ) {
					$formatted_request_body['profileName'] = 'Shipping';
				}
			}

			$formatted_request_body['redirectPageUri'] = add_query_arg(
				array(
					'walley_confirm' => '1',
					'public-token'   => '{checkout.publictoken}',
				),
				wc_get_checkout_url()
			);

			// Customer.
			if ( WC()->customer->get_billing_email() && WC()->customer->get_billing_phone() ) {
				$formatted_request_body['customer']['email']             = WC()->customer->get_billing_email();
				$formatted_request_body['customer']['mobilePhoneNumber'] = WC()->customer->get_billing_phone();
			}
		} else {
			$order                                     = wc_get_order( $order_id );
			$formatted_request_body['redirectPageUri'] = add_query_arg(
				array(
					'collector_confirm_order_pay' => '1',
					'public-token'                => '{checkout.publictoken}',
				),
				$order->get_checkout_order_received_url()
			);

			// Customer.
			if ( $order->get_billing_email() && $order->get_billing_phone() ) {
				$formatted_request_body['customer']['email']             = $order->get_billing_email();
				$formatted_request_body['customer']['mobilePhoneNumber'] = $order->get_billing_phone();
			}
		}

		if ( ! wp_json_encode( $formatted_request_body ) ) {
			$log = CCO_WC()->logger::format_log( 'null', 'POST', 'CCO initialize payment JSON FAILED', 'FAILED', 'null', print_r( $formatted_request_body, true ), 200 );
			CCO_WC()->logger::log( $log );
		}

		return wp_json_encode( apply_filters( 'coc_request_body', $formatted_request_body ), JSON_PARTIAL_OUTPUT_ON_ERROR );
	}
}
