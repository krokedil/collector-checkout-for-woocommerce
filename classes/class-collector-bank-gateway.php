<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Collector_Bank_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'collector_bank';
		$this->method_title       = __( 'Collector Bank', 'collector-bank-for-woocommerce' );
		$this->method_description = __( 'Collector Bank payment solution for WooCommerce', 'collector-bank-for-woocommerce' );
		$this->title = $this->get_option( 'title' );
		$this->enabled = $this->get_option( 'enabled' );
		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Function to handle the thankyou page.
		add_action( 'woocommerce_thankyou_collector_bank', array( $this, 'collector_thankyou' ) );

		// Override the checkout template
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );
	}
	public function init_form_fields() {
		$this->form_fields = include( COLLECTOR_BANK_PLUGIN_DIR . '/includes/collector-bank-settings.php' );
	}

	public function override_template( $template, $template_name, $template_path ) {
		$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
		reset( $available_payment_gateways );
		$first_gateway = key( $available_payment_gateways );
		if ( WC()->session->get( 'chosen_payment_method' ) === $this->id || $this->id === $first_gateway ) {
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$template = COLLECTOR_BANK_PLUGIN_DIR . '/templates/form-checkout.php';
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
			$_product = wc_get_product( $product_id );
			$price = $_product->get_regular_price();

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

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	public function collector_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		$order->payment_complete();
		update_post_meta( $order_id, '_collector_payment_method', WC()->session->get( 'collector_payment_method' ) );
		$order->add_order_note( sprintf( __( 'Order made with Collector. Payment Method: %s', 'collector-bank-for-woocommerce' ), WC()->session->get( 'collector_payment_method' ) ) );
	}
}
