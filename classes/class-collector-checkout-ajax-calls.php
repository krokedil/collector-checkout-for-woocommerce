<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Checkout_Ajax_Calls extends WC_AJAX {
	
	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}
	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'get_public_token' => true,
			'update_checkout' => true,
			'add_customer_order_note' => true,
			'get_checkout_thank_you' => true,
			'get_customer_data' => true,
			'customer_adress_updated' => true,
			'update_fragment' => true,
			'instant_purchase' => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}


	public static function get_public_token() {
		$customer_type = wc_clean( $_REQUEST['customer_type'] );
		$init_checkout = new Collector_Checkout_Requests_Initialize_Checkout( $customer_type );
		$request = $init_checkout->request();

		$decode = json_decode( $request );
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
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

	public static function update_checkout() {
		$private_id 	= WC()->session->get( 'collector_private_id' );
		$customer_type 	= WC()->session->get( 'collector_customer_type' );
		$update_fees 	= new Collector_Checkout_Requests_Update_Fees( $private_id, $customer_type );
		$update_fees->request();

		$update_cart 	= new Collector_Checkout_Requests_Update_Cart( $private_id, $customer_type );
		$update_cart->request();

		wp_send_json_success();
		wp_die();
	}
	
	/**
	 * Customer address updated - triggered when collectorCheckoutCustomerUpdated event is fired
	 */
	public static function customer_adress_updated() {
		
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'collector_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}
		$update_needed = 'no';
		
		// Get customer data from Collector
		$private_id 		= WC()->session->get( 'collector_private_id' );
		$customer_type 		= WC()->session->get( 'collector_customer_type' );
		$customer_data 		= new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$customer_data 		= $customer_data->request();
		$customer_data 		= json_decode( $customer_data );
		$country 			= $customer_data->data->countryCode;
		
		if( 'BusinessCustomer' == $customer_data->data->customerType ) {
			$billing_postcode 	= $customer_data->data->businessCustomer->invoiceAddress->postalCode;
			$shipping_postcode 	= $customer_data->data->businessCustomer->deliveryAddress->postalCode;
		} else {
			$billing_postcode 	= $customer_data->data->customer->billingAddress->postalCode;
			$shipping_postcode 	= $customer_data->data->customer->deliveryAddress->postalCode;
		}
		
		
		if( $country ) {
			
			// If country is changed then we need to trigger an cart update in the Collector Checkout
			if( WC()->customer->get_billing_country() !== $country ) {
				$update_needed = 'yes';
			}
			
			// If country is changed then we need to trigger an cart update in the Collector Checkout
			if( WC()->customer->get_shipping_postcode() !== $shipping_postcode ) {
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

	public static function add_customer_order_note() {
		WC()->session->set( 'collector_customer_order_note', $_POST['order_note'] );

		wp_send_json_success();
		wp_die();
	}

	public static function get_checkout_thank_you() {
		$public_token = WC()->session->get( 'collector_public_token' );

		wp_send_json_success( $public_token );

		WC()->session->__unset( 'collector_public_token' );
		WC()->session->__unset( 'collector_private_id' );

		wp_die();
	}

	public static function get_customer_data() {
		$private_id 	= WC()->session->get( 'collector_private_id' );
		$customer_type 	= WC()->session->get( 'collector_customer_type' );
		$customer_data 	= new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
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

	public static function update_fragment() {
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( 'false' === $_POST['collector'] ) {
			// Set chosen payment method to first gateway that is not Klarna Checkout for WooCommerce.
			$first_gateway = reset( $available_gateways );
			if ( 'collector_checkout' !== $first_gateway->id ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway->id );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'collector_checkout' );
		}
		WC()->payment_gateways()->set_current_gateway( $available_gateways );
		ob_start();
		if ( 'collector_checkout' !== WC()->session->get( 'chosen_payment_method' ) ) {
			
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

	public static function instant_purchase() {
		$product_id 	= $_POST['product_id'];
		$variation_id 	= $_POST['variation_id'];
		$quantity 		= $_POST['quantity'];
		$customer_token = $_POST['customer_token'];
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
		$customer_type 	= WC()->session->get( 'collector_customer_type' );
		$instant_checkout = new Collector_Checkout_Requests_Instant_Checkout( $customer_token, $customer_type );
		$request = $instant_checkout->request();
		
		$decode = json_decode( $request );
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$test_mode = $collector_settings['test_mode'];
		$return = array(
			'publicToken' 	=> $decode->data->publicToken,
			'test_mode'   	=> $test_mode,
			'customer_type'	=> $customer_type,
		);
		
		WC()->session->set( 'collector_public_token', $return );
		WC()->session->set( 'collector_private_id', $decode->data->privateId );
		
		wp_send_json_success( $return );
		wp_die();
	}
}
Collector_Checkout_Ajax_Calls::init();
