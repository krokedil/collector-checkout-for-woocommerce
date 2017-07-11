<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_SOAP_Requests_Activate_Invoice {

	static $log = '';

	public $endpoint = 'https://ecommercetest.collector.se/v3.0/InvoiceServiceV33.svc?wsdl';

	public $username = '';
	public $password = '';
	public $store_id = '';

	public function __construct() {
		$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
		$this->username = $collector_settings['collector_username'];
		$this->password = $collector_settings['collector_password'];
		$this->store_id = $collector_settings['collector_merchant_id'];
	}

	public function request( $order_id ) {
		$soap = new SoapClient( $this->endpoint );
		error_log( var_export( $soap->__getLastRequest(), true ) );
		$args = $this->get_request_args( $order_id );

		$headers = array();
		//$headers[] = new SoapHeader( 'http://schemas.xmlsoap.org/soap/envelope/', 'Username', $this->username );
		//$headers[] = new SoapHeader( 'http://schemas.xmlsoap.org/soap/envelope/', 'Password', $this->password );
		//$soap->__setSoapHeaders( $headers );

		$request = $soap->ActivateInvoice( $args );

		if ( $request->IsSuccess ) {
			$this->log( 'Collector order nr ' . $order_id . ' activated' );
		} else {
			$this->log( 'Collector order nr ' . $order_id . ' failed to activate' );
		}
	}

	public function get_request_args( $order_id ) {
		return array(
			'Username'    => $this->username,
			'Password'    => $this->password,
			'StoreId'     => $this->store_id,
			'CountryCode' => 'SE',
			'InvoiceNo'   => get_post_meta( $order_id, '_collector_payment_id' )[0],
		);
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
