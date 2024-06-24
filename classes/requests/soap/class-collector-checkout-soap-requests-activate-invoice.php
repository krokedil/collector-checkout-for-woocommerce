<?php
/**
 * Creates Collector refund data.
 *
 * @class    Collector_Checkout_SOAP_Requests_Activate_Invoice
 * @package  Collector/Classes/Requests/Soap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Class Collector_Checkout_SOAP_Requests_Activate_Invoice
 */
class Collector_Checkout_SOAP_Requests_Activate_Invoice {


	/**
	 * The Collector endpoint.
	 *
	 * @var string
	 */
	public $endpoint = '';

	/**
	 * Walley checkout username
	 *
	 * @var string
	 */
	public $username = '';

	/**
	 * Walley checkout password
	 *
	 * @var string
	 */
	public $password = '';

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
	 * Customer type
	 *
	 * @var string
	 */
	public $customer_type = '';


	/**
	 * Class constructor.
	 *
	 * @param int $order_id The WooCommerce order id.
	 */
	public function __construct( $order_id ) {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$this->username     = $collector_settings['collector_username'];
		$this->password     = $collector_settings['collector_password'];
		$order              = wc_get_order( $order_id );
		$currency           = $order->get_currency();
		$customer_type      = $order->get_meta( '_collector_customer_type', true );

		switch ( $currency ) {
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
		$this->country_code = $country_code;
		$test_mode          = $collector_settings['test_mode'];
		if ( 'yes' === $test_mode ) {
			$this->endpoint = COLLECTOR_BANK_SOAP_TEST;
		} else {
			$this->endpoint = COLLECTOR_BANK_SOAP_LIVE;
		}
	}

	/**
	 * Make the request.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return void
	 * @throws SoapFault SOAP Fault.
	 */
	public function request( $order_id ) {

		$soap = new SoapClient( $this->endpoint );
		$args = $this->get_request_args( $order_id );

		$headers   = array();
		$headers[] = new SoapHeader( 'http://schemas.ecommerce.collector.se/v30/InvoiceService', 'Username', $this->username );
		$headers[] = new SoapHeader( 'http://schemas.ecommerce.collector.se/v30/InvoiceService', 'Password', $this->password );
		$soap->__setSoapHeaders( $headers );

		$order    = wc_get_order( $order_id );
		$due_date = '';

		try {
			$request = $soap->ActivateInvoice( $args );
		} catch ( SoapFault $e ) {
			$request = $e->getMessage();
			$order->set_status( 'on-hold' );
			$order->save();
		}

		// todo maybe : solution for snake case errors is to cast response to key-value array??!
		if ( isset( $request->TotalAmount ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			if ( isset( $request->InvoiceUrl ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$order->update_meta_data( '_collector_invoice_url', wc_clean( $request->InvoiceUrl ) );// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$due_date = gmdate( get_option( 'date_format' ) . ' - ' . get_option( 'time_format' ), strtotime( $request->DueDate ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$due_date = sprintf( __( 'Invoice due date: %s', 'collector-checkout-for-woocommerce' ), $due_date );
			}

			// translators: 1. Due date.
			$order->add_order_note( sprintf( __( 'Order activated with Walley Checkout. %s', 'collector-checkout-for-woocommerce' ), $due_date ) );
			$order->update_meta_data( '_collector_order_activated', time() );
			$log = CCO_WC()->logger::format_log( $order_id, 'SOAP', 'CCO Activate order ', $args, '', wp_json_encode( $request ), '' );
			CCO_WC()->logger::log( $log );

			$order->save();
		} else {
			$order->update_status( $order->get_status() );
			$failed_request = wp_json_encode( $request );
			// translators: 1. Failed request.
			$order->add_order_note( sprintf( __( 'Order failed to activate with Walley Checkout - %s', 'collector-checkout-for-woocommerce' ), $failed_request ) );

			// TODO $e is not defined.
			$log = CCO_WC()->logger::format_log( $order_id, 'SOAP', 'CCO FAILED Activate order', $args, '', wp_json_encode( $request ) . wp_json_encode( $headers ), '' );
			CCO_WC()->logger::log( $log );
		}
	}

	/**
	 * Get the request args.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function get_request_args( $order_id ) {
		$order = wc_get_order( $order_id );
		return array(
			'StoreId'     => $this->store_id,
			'CountryCode' => $this->country_code,
			'InvoiceNo'   => $order->get_meta( '_collector_payment_id' )[0],
		);
	}
}
