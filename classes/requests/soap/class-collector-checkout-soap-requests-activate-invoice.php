<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Checkout_SOAP_Requests_Activate_Invoice {

	static $log = '';

	public $endpoint = '';

	public $username      = '';
	public $password      = '';
	public $store_id      = '';
	public $country_code  = '';
	public $customer_type = '';

	public function __construct( $order_id ) {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$this->username     = $collector_settings['collector_username'];
		$this->password     = $collector_settings['collector_password'];
		$order              = wc_get_order( $order_id );
		$currency           = $order->get_currency();
		$customer_type      = get_post_meta( $order_id, '_collector_customer_type', true );

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

		if ( isset( $request->TotalAmount ) ) {

			if ( isset( $request->InvoiceUrl ) ) {
				update_post_meta( $order_id, '_collector_invoice_url', wc_clean( $request->InvoiceUrl ) );
				$due_date = date( get_option( 'date_format' ) . ' - ' . get_option( 'time_format' ), strtotime( $request->DueDate ) );
				$due_date = ' ' . __( 'Invoice due date: ' . $due_date, 'collector-checkout-for-woocommerce' );
			}

			$order->add_order_note( sprintf( __( 'Order activated with Collector Bank.' . $due_date, 'collector-checkout-for-woocommerce' ) ) );
			update_post_meta( $order_id, '_collector_order_activated', time() );

			$log = CCO_WC()->logger->format_log( '', 'SOAP', 'CCO Activate order for order ID ' . $order_id, $args, wp_json_encode( $request, true ), '' );
			CCO_WC()->logger->log( $log );

		} else {
			$order->update_status( $order->get_status() );
			$order->add_order_note( sprintf( __( 'Order failed to activate with Collector Bank - ' . var_export( $request, true ), 'collector-checkout-for-woocommerce' ) ) );

			$log = CCO_WC()->logger->format_log( '', 'SOAP', 'CCO FAILED Activate order for order ID ' . $order_id, $args, json_decode( $e, true ), '' );
			CCO_WC()->logger->log( $log );
			$this->log( 'Activate order headers: ' . var_export( $headers, true ) );
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
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		if ( 'yes' === $collector_settings['debug_mode'] ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'collector_checkout', $message );
		}
	}
}
