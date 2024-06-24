<?php
/**
 * Credit_Payment.
 *
 * @package  Collector/Classes/Requests/Soap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_SOAP_Requests_Credit_Payment
 */
class Collector_Checkout_SOAP_Requests_Credit_Payment {

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
	 * @return bool|void
	 * @throws SoapFault SOAP Fault.
	 */
	public function request( $order_id ) {
		$soap = new SoapClient( $this->endpoint );
		$args = $this->get_request_args( $order_id );

		$headers   = array();
		$headers[] = new SoapHeader( 'http://schemas.ecommerce.collector.se/v30/InvoiceService', 'Username', $this->username );
		$headers[] = new SoapHeader( 'http://schemas.ecommerce.collector.se/v30/InvoiceService', 'Password', $this->password );
		$soap->__setSoapHeaders( $headers );

		try {
			$request = $soap->CreditInvoice( $args );
		} catch ( SoapFault $e ) {
			$request = $e->getMessage();
		}

		$order = wc_get_order( $order_id );
		if ( isset( $request->CorrelationId ) || null === $request->CorrelationId ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$order->add_order_note( sprintf( __( 'Order credited with Collector Bank', 'collector-checkout-for-woocommerce' ) ) );
			$log = CCO_WC()->logger::format_log( $order_id, 'SOAP', 'CCO refund order (CreditInvoice)', $args, '', wp_json_encode( $request ), '' );
			CCO_WC()->logger::log( $log );
			return true;
		} else {
			$order->update_status( 'completed' );
			// TODO check this!
			// translators: The request.
			$order->add_order_note( sprintf( __( 'Order failed to be credited with Collector Bank - %s', 'collector-checkout-for-woocommerce' ), wp_json_encode( $request ) ) );
			$log = CCO_WC()->logger::format_log( $order_id, 'SOAP', 'CCO FAILED refund order (CreditInvoice)', $args, '', wp_json_encode( $e ) . wp_json_encode( $headers ), '' );
			CCO_WC()->logger::log( $log );
		}
	}

	/**
	 * Make the request.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 *
	 * @return bool|void
	 * @throws SoapFault SOAP Fault.
	 */
	public function request_part_credit( $order_id, $amount, $reason ) {
		$order = wc_get_order( $order_id );
		$soap  = new SoapClient( $this->endpoint );
		$args  = $this->get_request_args( $order_id );

		$headers   = array();
		$headers[] = new SoapHeader( 'http://schemas.ecommerce.collector.se/v30/InvoiceService', 'Username', $this->username );
		$headers[] = new SoapHeader( 'http://schemas.ecommerce.collector.se/v30/InvoiceService', 'Password', $this->password );
		$soap->__setSoapHeaders( $headers );
		$payment_id = $order->get_meta( '_collector_payment_id', true );
		$data       = Collector_Checkout_Create_Refund_Data::create_refund_data( $order_id, $amount, $reason, $payment_id );
		try {
			$request = $soap->PartCreditInvoice( $args );
		} catch ( SoapFault $e ) {
			$request = $e->getMessage();
		}

		if ( isset( $request->CorrelationId ) || null === $request->CorrelationId ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$order->add_order_note( sprintf( __( 'Order credited with Collector Bank', 'collector-checkout-for-woocommerce' ) ) );
			$log = CCO_WC()->logger::format_log( $order_id, 'SOAP', 'CCO refund order (PartCreditInvoice)', $args, '', wp_json_encode( $request ), '' );
			CCO_WC()->logger::log( $log );
			return true;
		} else {
			$order->update_status( 'completed' );
			// translators: The request.
			$order->add_order_note( sprintf( __( 'Order failed to be credited with Collector Bank - %s', 'collector-checkout-for-woocommerce' ), wp_json_encode( $request ) ) );
			$log = CCO_WC()->logger::format_log( $order_id, 'SOAP', 'CCO FAILED refund order (PartCreditInvoice)', $args, '', wp_json_encode( $request ) . wp_json_encode( $headers ), '' );
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

		$order                  = wc_get_order( $order_id );
		$collector_invoice_data = json_decode( $order->get_meta( '_collector_activate_invoice_data', true ), true );

		if ( is_array( $collector_invoice_data ) && isset( $collector_invoice_data[0]['NewInvoiceNo'] ) ) {
			// The newest invoice is the latest one. Let's use that.
			$reversed_invoice_data = array_reverse( $collector_invoice_data );
			$invoice_no            = $reversed_invoice_data[0]['NewInvoiceNo'];
		} else {
			$invoice_no = $order->get_meta( '_collector_payment_id', true );
		}
		return array(
			'StoreId'     => $this->store_id,
			'CountryCode' => $this->country_code,
			'InvoiceNo'   => $invoice_no,
			'CreditDate'  => time(),
		);
	}
}
