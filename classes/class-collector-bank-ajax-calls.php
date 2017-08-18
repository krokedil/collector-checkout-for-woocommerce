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
		
		// Customer address updated
		add_action( 'wp_ajax_customer_adress_updated', array( $this, 'customer_adress_updated' ) );
		add_action( 'wp_ajax_nopriv_customer_adress_updated', array( $this, 'customer_adress_updated' ) );

		// Update Template Fragment
		add_action( 'wp_ajax_update_fragment', array( $this, 'update_fragment' ) );
		add_action( 'wp_ajax_nopriv_update_fragment', array( $this, 'update_fragment' ) );
	}

	public function get_public_token() {
		$customer_type = wc_clean( $_REQUEST['customer_type'] );
		$init_checkout = new Collector_Bank_Requests_Initialize_Checkout( $customer_type );
		$request = $init_checkout->request();

		$decode = json_decode( $request );
		$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
		$test_mode = $collector_settings['test_mode'];
		$return = array(
			'publicToken' 	=> $decode->data->publicToken,
			'test_mode'   	=> $test_mode,
			'customer_type'	=> $customer_type,
		);

		// Set post metas so they can be used again later
		WC()->session->set( 'collector_public_token', $return );
		WC()->session->set( 'collector_private_id', $decode->data->privateId );
		WC()->session->set( 'collector_customer_type', $customer_type );

		wp_send_json_success( $return );
		wp_die();
	}

	public function update_checkout() {
		$private_id 	= WC()->session->get( 'collector_private_id' );
		$customer_type 	= WC()->session->get( 'collector_customer_type' );
		$update_fees 	= new Collector_Bank_Requests_Update_Fees( $private_id, $customer_type );
		$update_fees->request();

		$update_cart 	= new Collector_Bank_Requests_Update_Cart( $private_id, $customer_type );
		$update_cart->request();

		wp_send_json_success();
		wp_die();
	}
	
	/**
	 * Customer address updated - triggered when collectorCheckoutCustomerUpdated event is fired
	 */
	public function customer_adress_updated() {
		
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'collector_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}
		$update_needed = 'no';
		
		// Get customer data from Collector
		$private_id 	= WC()->session->get( 'collector_private_id' );
		$customer_type 	= WC()->session->get( 'collector_customer_type' );
		$customer_data 	= new Collector_Bank_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$customer_data 	= $customer_data->request();
		$customer_data 	= json_decode( $customer_data );
		$country 		= $customer_data->data->countryCode;
		
		if( $country ) {
			
			$billing_postcode = $customer_data->data->customer->billingAddress->postalCode;
			$shipping_postcode = $customer_data->data->customer->deliveryAddress->postalCode;
			
			// If country is changed then we need to trigger an cart update in the Collector Checkout
			if( WC()->customer->get_billing_country() !== $country ) {
				$update_needed = 'yes';
			}
			// Set customer data in Woo			
			WC()->customer->set_billing_country( $country );
			WC()->customer->set_shipping_country( $country );
			WC()->customer->set_billing_postcode( $billing_postcode );
			WC()->customer->set_shipping_postcode( $shipping_postcode );
			WC()->customer->save();
			WC()->cart->calculate_totals();
			
		}
		
		wp_send_json_success( $update_needed );
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
		$private_id 	= WC()->session->get( 'collector_private_id' );
		$customer_type 	= WC()->session->get( 'collector_customer_type' );
		$customer_data 	= new Collector_Bank_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$customer_data 	= $customer_data->request();

		// Save the payment method and payment id
		$decoded_json = json_decode( $customer_data );
		$payment_method = $decoded_json->data->purchase->paymentMethod;
		$payment_id = $decoded_json->data->purchase->purchaseIdentifier;
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

	public function update_fragment() {

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( 'false' === $_POST['collector'] ) {
			// Set chosen payment method to first gateway that is not Klarna Checkout for WooCommerce.
			$first_gateway = reset( $available_gateways );
			if ( 'collector_bank' !== $first_gateway->id ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway->id );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'collector_bank' );
		}
		WC()->payment_gateways()->set_current_gateway( $available_gateways );
		ob_start();
		if ( 'collector_bank' !== WC()->session->get( 'chosen_payment_method' ) ) {
			wc_get_template( 'checkout/form-checkout.php', array(
				'checkout' => WC()->checkout(),
			) );
		} else {
			include( COLLECTOR_BANK_PLUGIN_DIR . '/templates/form-checkout.php' );
		}
		$checkout_output = ob_get_clean();

		$data = array(
			'fragments' => array(
				'checkout' => $checkout_output,
			),
		);
		wp_send_json_success( $data );
		wp_die();
	}
}
$collector_ajax_calls = new Collector_Bank_Ajax_Calls();
