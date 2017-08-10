<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Collector_Bank_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'collector_bank';
		$this->method_title       = __( 'Collector Bank', 'collector-bank-for-woocommerce' );
		$this->method_description = __( 'Collector Bank payment solution for WooCommerce', 'collector-bank-for-woocommerce' );
		$this->description        = $this->get_option( 'description' );
		$this->title              = $this->get_option( 'title' );
		$this->enabled            = $this->get_option( 'enabled' );
		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();
		$this->supports = array(
			'products',
			'refunds',
		);

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		// Function to handle the thankyou page.
		add_action( 'woocommerce_thankyou_collector_bank', array( $this, 'collector_thankyou' ) );
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'collector_thankyou_order_received_text' ), 10, 2 );

		// Override the checkout template
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );
	}

	public function init_form_fields() {
		$this->form_fields = include( COLLECTOR_BANK_PLUGIN_DIR . '/includes/collector-bank-settings.php' );
	}
	
	/**
	 * Check if this gateway is enabled and available in the user's country
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! is_admin() ) {
				// Currency check.
				if ( ! in_array( get_woocommerce_currency(), array( 'NOK', 'SEK' ) ) ) {
					return false;
				}
			}
			return true;
		}
		return false;
	}
	
	/**
	 * Get a link to the transaction on the 3rd party gateway size (if applicable).
	 *
	 * @param  WC_Order $order the order object.
	 *
	 * @return string transaction URL, or empty string.
	 */
	public function get_transaction_url( $order ) {
		// Check if order is completed
		$invoice_url = get_post_meta( $order->id, '_collector_invoice_url', true );
		if ( $invoice_url ) {
			$this->view_transaction_url = $invoice_url;
		}
		return parent::get_transaction_url( $order );
	}
	

	public function override_template( $template, $template_name, $template_path ) {
		if ( is_checkout() ) {
			$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
			reset( $available_payment_gateways );
			$first_gateway = key( $available_payment_gateways );
			if ( ( WC()->session->get( 'chosen_payment_method' ) === $this->id || $this->id === $first_gateway ) && true === $this->is_available()) {
				if ( 'checkout/form-checkout.php' === $template_name ) {
					$template = COLLECTOR_BANK_PLUGIN_DIR . '/templates/form-checkout.php';
				}
			}
		}

		return $template;
	}

	public function get_customer_data() {
		// Get information about order from Collector
		$private_id    = WC()->session->get( 'collector_private_id' );
		$customer_data = new Collector_Bank_Requests_Get_Checkout_Information( $private_id );
		$customer_data = $customer_data->request();

		return json_decode( $customer_data );
	}

	public function process_payment( $order_id, $retry = false ) {
		$order = wc_get_order( $order_id );

		if ( 'Direct Invoice' == WC()->session->get( 'collector_payment_method' ) ) {
			$product_id = $this->get_option( 'collector_invoice_fee' );
			$_product   = wc_get_product( $product_id );
			$price      = $_product->get_regular_price();

			$collector_fee            = new stdClass();
			$collector_fee->id        = sanitize_title( __( 'Collector Bank Invoice Fee', 'collector-bank-for-woocommerce' ) );
			$collector_fee->name      = __( 'Collector Bank Invoice Fee', 'collector-bank-for-woocommerce' );
			$collector_fee->amount    = $price;
			$collector_fee->taxable   = false;
			$collector_fee->tax       = 0;
			$collector_fee->tax_data  = array();
			$collector_fee->tax_class = '';
			$fee_id                   = $order->add_fee( $collector_fee );

			if ( ! $fee_id ) {
				$order->add_order_note( __( 'Unable to add Collector Bank Invoice Fee to the order.', 'collector-bank-for-woocommerce' ) );
			}
			$order->calculate_totals( true );
		}

		WC()->session->__unset( 'collector_customer_order_note' );
		// Update the Collector Order with the Order ID
		$update_reference = new Collector_Bank_Requests_Update_Reference( $order->get_order_number(), WC()->session->get( 'collector_private_id' ) );
		$update_reference->request();
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	public function collector_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		
		$private_id = WC()->session->get( 'collector_private_id' );
		$payment_data = new Collector_Bank_Requests_Get_Checkout_Information( $private_id );
		$payment_data = $payment_data->request();
		$payment_data = json_decode( $payment_data );
		$payment_status = $payment_data->data->purchase->result;
		
		if('Preliminary' == $payment_status ) {
			$order->payment_complete( WC()->session->get( 'collector_payment_id' ) );
		} else {
			$order->add_order_note( __( 'Order is PENDING APPROVAL by Collector. Payment ID: ', 'woocommerce-gateway-klarna' ) . WC()->session->get( 'collector_payment_id' ) );
			$order->update_status( 'on-hold' );
		}
		
		
		update_post_meta( $order_id, '_collector_payment_method', WC()->session->get( 'collector_payment_method' ) );
		update_post_meta( $order_id, '_collector_payment_id', WC()->session->get( 'collector_payment_id' ) );
		$order->add_order_note( sprintf( __( 'Order made with Collector. Payment Method: %s', 'collector-bank-for-woocommerce' ), WC()->session->get( 'collector_payment_method' ) ) );
	}
	
	/**
	 * Remove thank you page order received text if Collector is the selected payment method.
	 *
	 * @param $text
	 * @param $order
	 *
	 * @return string
	 */
	public function collector_thankyou_order_received_text( $text, $order ) {
		if( 'collector_bank' == $order->get_payment_method() ) {
			return '';
		}

		return $text;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		//Check if amount equals total order
		$order = wc_get_order( $order_id );
		if ( $amount == $order->get_total() ) {
			$credit_order = new Collector_Bank_SOAP_Requests_Credit_Payment();
			if ( $credit_order->request( $order_id ) === true ) {
				return true;
			} else {
				return false;
			}
		} else {
			$order->add_order_note( sprintf( __( 'Collector Bank currently only supports full refunds, for a partial refund use the Collector Bank Merchant Portal', 'collector-bank-for-woocommerce' ) ) );
			return false;
		}
	}
}
