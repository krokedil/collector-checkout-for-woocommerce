<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Ajax_Calls {
	public function __construct() {
		// Get public token and set some meta data
		add_action( 'wp_ajax_get_public_token', array( $this, 'get_public_token' ) );
		add_action( 'wp_ajax_nopriv_get_public_token', array( $this, 'get_public_token' ) );

		// Update fees
		add_action( 'wp_ajax_update_checkout', array( $this, 'update_checkout' ) );
		add_action( 'wp_ajax_nopriv_update_checkout', array( $this, 'update_checkout' ) );

		// Ajax to add order notes as a session for the customer
		add_action( 'wp_ajax_customer_order_note', array( $this, 'add_customer_order_note' ) );
		add_action( 'wp_ajax_nopriv_customer_order_note', array( $this, 'add_customer_order_note' ) );

		// Get old checkout for thank you page
		add_action( 'wp_ajax_get_checkout_thank_you', array( $this, 'get_checkout_thank_you' ) );
		add_action( 'wp_ajax_nopriv_get_checkout_thank_you', array( $this, 'get_checkout_thank_you' ) );

		// Get customer data
		add_action( 'wp_ajax_get_customer_data', array( $this, 'get_customer_data' ) );
		add_action( 'wp_ajax_nopriv_get_customer_data', array( $this, 'get_customer_data' ) );
	}

	public function get_public_token() {
		$init_checkout = new Collector_Bank_Requests_Initialize_Checkout();
		$request = $init_checkout->request();

		$decode = json_decode( $request );
		$return = $decode->data->publicToken;
		// Set post metas so they can be used again later
		WC()->session->set( 'collector_public_token', $return );
		WC()->session->set( 'collector_private_id', $decode->data->privateId );

		wp_send_json_success( $return );
		wp_die();
	}

	public function update_checkout() {
		$private_id = WC()->session->get( 'collector_private_id' );
		$update_fees = new Collector_Bank_Requests_Update_Fees( $private_id );
		$update_fees->request();

		$update_cart = new Collector_Bank_Requests_Update_Cart( $private_id );
		$update_cart->request();

		wp_send_json_success();
		wp_die();
	}

	public function add_customer_order_note() {
		WC()->session->set( 'collector_customer_order_note', $_POST['order_note'] );

		wp_send_json_success();
		wp_die();
	}

	public function get_checkout_thank_you() {
		$public_token = WC()->session->get( 'collector_public_token' );

		wp_send_json_success( $public_token );

		WC()->session->__unset( 'collector_public_token' );
		WC()->session->__unset( 'collector_private_id' );

		wp_die();
	}

	public function get_customer_data() {
		$private_id = WC()->session->get( 'collector_private_id' );
		$customer_data = new Collector_Bank_Requests_Get_Checkout_Information( $private_id );
		$customer_data = $customer_data->request();

		// Save the payment method and payment id
		$decoded_json = json_decode( $customer_data );
		$payment_method = $decoded_json->data->purchase->paymentMethod;
		$payment_id = $decoded_json->id;
		WC()->session->set( 'collector_payment_method', $payment_method );
		WC()->session->set( 'collector_payment_id', $payment_id );

		// Return the data, customer note and create a nonce.
		$return = array();
		$return['customer_data'] = json_decode( $customer_data );
		$return['nonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		if ( null != WC()->session->get( 'collector_customer_order_note' ) ) {
			$return['order_note'] = WC()->session->get( 'collector_customer_order_note' );
		} else {
			$return['order_note'] = '';
		}
		$return['shipping'] = WC()->session->get( 'collector_chosen_shipping' );
		wp_send_json_success( $return );
		wp_die();
	}
}
$collector_ajax_calls = new Collector_Bank_Ajax_Calls();
