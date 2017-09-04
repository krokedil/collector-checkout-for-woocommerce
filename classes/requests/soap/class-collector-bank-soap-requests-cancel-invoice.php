<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_SOAP_Requests_Cancel_Invoice {

	static $log = '';

	public $endpoint = '';

	public $username = '';
	public $password = '';
	public $store_id = '';
	public $country_code = '';

	public function __construct( $order_id ) {
		$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
		$this->username = $collector_settings['collector_username'];
		$this->password = $collector_settings['collector_password'];
		$order = wc_get_order( $order_id );
		$currency = $order->get_currency();
		$customer_type = get_post_meta( $order_id, '_collector_customer_type', true );
		
		switch ( $currency ) {
			case 'SEK' :
				$country_code = 'SE';
				$this->store_id = $collector_settings['collector_merchant_id_se_' . $customer_type];
				break;
			case 'NOK' :
				$country_code = 'NO';
				$this->store_id = $collector_settings['collector_merchant_id_no_' . $customer_type];
				break;
			default :
				$country_code = 'SE';
				$this->store_id = $collector_settings['collector_merchant_id_se_' . $customer_type];
				break;
		}
		$this->country_code = $country_code;
		$test_mode = $collector_settings['test_mode'];
		if ( 'yes' === $test_mode ) {
			$this->endpoint = COLLECTOR_BANK_SOAP_TEST;
		} else {
			$this->endpoint = COLLECTOR_BANK_SOAP_LIVE;
		}
	}

	public function request( $order_id ) {
		$soap = new SoapClient( $this->endpoint );
		$args = $this->get_request_args( $order_id );

		$headers = array();
		$headers[] = new SoapHeader( 'http://schemas.ecommerce.collector.se/v30/InvoiceService', 'Username', $this->username );
		$headers[] = new SoapHeader( 'http://schemas.ecommerce.collector.se/v30/InvoiceService', 'Password', $this->password );
		$soap->__setSoapHeaders( $headers );
		
		try {
			$request = $soap->CancelInvoice( $args );
		}
			catch( SoapFault $e ){
			$request = $e->getMessage();
		}
		
		$order = wc_get_order( $order_id );
		if ( isset( $request->CorrelationId ) || $request->CorrelationId == null ) {
			$order->add_order_note( sprintf( __( 'Order canceled with Collector Bank', 'collector-checkout-for-woocommerce' ) ) );
			update_post_meta( $order_id, '_collector_order_cancelled', time() );
		} else {
			$order->update_status( $order->get_status() );
			$order->add_order_note( sprintf( __( 'Order failed to cancel with Collector Bank - ' . $request, 'collector-checkout-for-woocommerce' ) ) );
			$this->log( 'Order failed to cancel with Collector Bank. Request response: ' . var_export( $e, true ) );
			$this->log( 'Cancel order headers: ' . var_export( $headers, true ) );
			$this->log( 'Cancel order args: ' . var_export( $args, true ) );
		}
	}

	public function get_request_args( $order_id ) {
		return array(
			'StoreId'     => $this->store_id,
			'CountryCode' => $this->country_code,
			'InvoiceNo'   => get_post_meta( $order_id, '_collector_payment_id' )[0],
		);
	}

	public static function log( $message ) {
		$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
		if ( 'yes' === $collector_settings['debug_mode'] ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'collector_bank', $message );
		}
	}
}
