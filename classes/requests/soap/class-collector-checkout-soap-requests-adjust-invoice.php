<?php
/**
 * A class for adjusting the total invoice amount. The adjusted amount can be both negative and positive.
 *
 * @package  Collector/Classes/Requests/Soap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_SOAP_Requests_Adjust_Invoice.
 */
class Collector_Checkout_SOAP_Requests_Adjust_Invoice {


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
	 * @param int    $order_id The WooCommerce order id.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 * @param array  $refunded_items Refund data.
	 *
	 * @return bool
	 * @throws SoapFault SOAP Fault.
	 */
	public function request( $order_id, $amount, $reason, $refunded_items ) {
		$soap      = new SoapClient( $this->endpoint );
		$args      = $this->get_request_args( $order_id, $amount, $reason, $refunded_items );
		$headers   = array();
		$headers[] = new SoapHeader( 'http://schemas.ecommerce.collector.se/v30/InvoiceService', 'Username', $this->username );
		$headers[] = new SoapHeader( 'http://schemas.ecommerce.collector.se/v30/InvoiceService', 'Password', $this->password );
		$soap->__setSoapHeaders( $headers );

		try {
			$request = $soap->AdjustInvoice( $args );
		} catch ( SoapFault $e ) {
			$request = $e->getMessage();

			$log = CCO_WC()->logger::format_log( $order_id, 'SOAP', 'CCO FAILED credit (AdjustInvoice)', $args, '', wp_json_encode( $e ) . wp_json_encode( $headers ), '' );
			CCO_WC()->logger::log( $log );
			return false;
		}
		$order = wc_get_order( $order_id );
		if ( isset( $request->CorrelationId ) || null === $request->CorrelationId ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$order->add_order_note(
				sprintf(
				// translators: 1. Collector correlation ID.
					__(
						'Order credited with Collector Bank. CorrelationId %s',
						'collector-checkout-for-woocommerce'
					),
					$request->CorrelationId // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				)
			);

			$log = CCO_WC()->logger::format_log( $order_id, 'SOAP', 'CCO credit (AdjustInvoice)', $args, '', wp_json_encode( $request ), '' );
			CCO_WC()->logger::log( $log );
			return true;
		} else {

			$export_request = var_export( $request, true ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			$order->add_order_note(
				sprintf(
				// translators: 1. The response from Collector.
					__(
						'Order failed to be credited with Collector Bank - %s',
						'collector-checkout-for-woocommerce'
					),
					$export_request
				)
			);
			// TODO $e is not defined.
			// TODO Add errors from Collector response.
			$log = CCO_WC()->logger::format_log( $order_id, 'SOAP', 'CCO FAILED credit (AdjustInvoice)', $args, '', wp_json_encode( $request ) . wp_json_encode( $headers ), '' );
			CCO_WC()->logger::log( $log );
			return false;
		}
	}

	/**
	 * Get the request args.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 * @param array  $refunded_items Refund data.
	 *
	 * @return array
	 */
	public function get_request_args( $order_id, $amount, $reason, $refunded_items ) {

		$order          = wc_get_order( $order_id );
		$transaction_id = $order->get_transaction_id();
		return array(
			'StoreId'       => $this->store_id,
			'CountryCode'   => $this->country_code,
			'InvoiceNo'     => $transaction_id,
			'InvoiceRows'   => array(
				'InvoiceRow' => $refunded_items,
			),
			'CorrelationId' => Collector_Checkout_Create_Refund_Data::get_refunded_order( $order_id ),
		);
	}
}
