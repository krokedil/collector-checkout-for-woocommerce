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
		if ( WC()->session->get( 'chosen_payment_method' ) === $this->id ) {
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$template = COLLECTOR_BANK_PLUGIN_DIR . '/templates/form-checkout.php';
			}
		}
		return $template;
	}

	public function maybe_process_payment() {
		if ( isset( $_GET['payment_successful'] ) && WC()->session->get( 'order_awaiting_payment' ) == $_GET['payment_successful'] && is_checkout() ) {
			add_filter( 'woocommerce_checkout_get_value', array( $this, 'populate_fields' ), 10, 2 );
			add_filter( 'woocommerce_checkout_fields' ,  array( $this, 'set_not_required' ), 20 );
		}
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

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	public function collector_thankyou( $order_id ) {
		/*
		// Update the reference with Collector
		$customer_data = $this->get_customer_data();
		$private_id = WC()->session->get( 'collector_private_id' );
		$update_reference = new Collector_Bank_Requests_Update_Reference( $order_id, $private_id );
		$update_reference->request();
		$payment_method = $customer_data->data->purchase->paymentMethod;
		update_post_meta( $order_id, '_collector_payment_method', $payment_method );
		$order = wc_get_order( $order_id );
		switch ( $payment_method ) {
			case 'Direct Invoice':
				$product_id = $this->get_option( 'collector_invoice_fee' );
				$woocommerce->cart->add_to_cart($product_id);
				//$product = wc_get_product( $product_id );
				//$order->add_product( $product, 1 );
				break;
			case 'Account':
				break;
			case 'Card':
				break;
			case 'Bank Transfer':
				break;
			case 'PartPayment':
				break;
			case 'Campaign':
				break;
		}
		$order->add_order_note( sprintf( __( 'Order made with Collector. Payment Method: %s', 'collector-bank-for-woocommerce' ), $payment_method ) );
		*/
	}
}
