<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Collector_Bank_Gateway extends WC_Payment_Gateway {

	public $customer_data = '';

	public function __construct() {
		error_log( 'test' );
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
		if ( isset( $_GET['payment_successful'] ) && WC()->session->get( 'order_awaiting_payment' ) == $_GET['payment_successful'] && is_checkout() ) {
			error_log( 'in if' );
			$this->get_customer_data();
			add_filter( 'woocommerce_checkout_get_value', array( $this, 'populate_fields' ), 10, 2 );
			add_filter( 'woocommerce_checkout_fields', array( $this, 'set_not_required' ), 20 );
		}
		//add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_process_payment' ) );

	}
	public function init_form_fields() {
		$this->form_fields = include( COLLECTOR_BANK_PLUGIN_DIR . '/includes/collector-bank-settings.php' );
	}

	public function maybe_process_payment() {
		if ( isset( $_GET['payment_successful'] ) && WC()->session->get( 'order_awaiting_payment' ) == $_GET['payment_successful'] && is_checkout() ) {
			error_log( 'in maybe process payment' );
			add_filter( 'woocommerce_checkout_get_value', array( $this, 'populate_fields' ), 10, 2 );
			add_filter( 'woocommerce_checkout_fields' ,  array( $this, 'set_not_required' ), 20 );

		}
	}
	public function populate_fields( $value, $key ) {
		error_log( 'in populate fields' );
		$customer_data = $this->customer_data;
		// Get billing information from the customer
		$billing_first_name = $customer_data->data->customer->billingAddress->firstName;
		$billing_last_name  = $customer_data->data->customer->billingAddress->lastName;
		$billing_country    = $customer_data->data->customer->billingAddress->country;
		$billing_address    = $customer_data->data->customer->billingAddress->address;
		$billing_city       = $customer_data->data->customer->billingAddress->city;
		$billing_postcode   = $customer_data->data->customer->billingAddress->postalCode;

		// Get shipping information from the customer
		$shipping_first_name = $customer_data->data->customer->deliveryAddress->firstName;
		$shipping_last_name  = $customer_data->data->customer->deliveryAddress->lastName;
		$shipping_country    = $customer_data->data->customer->deliveryAddress->country;
		$shipping_address    = $customer_data->data->customer->deliveryAddress->address;
		$shipping_city       = $customer_data->data->customer->deliveryAddress->city;
		$shipping_postcode   = $customer_data->data->customer->deliveryAddress->postalCode;

		// Standard information
		$phone = $customer_data->data->customer->mobilePhoneNumber;
		$email = $customer_data->data->customer->email;

		//Populate the fields
		switch ( $key ) {
			case 'billing_first_name':
				return $billing_first_name;
				break;
			case 'billing_last_name':
				return $billing_last_name;
				break;
			case 'billing_email':
				return $email;
				break;
			case 'billing_country':
				return $billing_country;
				break;
			case 'billing_address_1':
				return $billing_address;
				break;
			case 'billing_city':
				return $billing_city;
				break;
			case 'billing_postcode':
				return $billing_postcode;
				break;
			case 'billing_phone':
				return $phone;
				break;
			case 'shipping_first_name':
				return $shipping_first_name;
				break;
			case 'shipping_last_name':
				return $shipping_last_name;
				break;
			case 'shipping_email':
				return $email;
				break;
			case 'shipping_country':
				return $shipping_country;
				break;
			case 'shipping_address_1':
				return $shipping_address;
				break;
			case 'shipping_city':
				return $shipping_city;
				break;
			case 'shipping_postcode':
				return $shipping_postcode;
				break;
			case 'shipping_phone':
				return $phone;
				break;
			case 'order_comments':
				if ( WC()->session->get( 'collector_customer_order_note' ) ) {
					return WC()->session->get( 'collector_customer_order_note' );
				} else {
					return '';
				}
				break;
		} // End switch().
	}
	public function get_customer_data() {
		// Get information about order from Collector
		$order_id      = WC()->session->get( 'order_awaiting_payment' );
		$private_id    = get_post_meta( $order_id, '_collector_private_id' );
		$customer_data = new Collector_Bank_Requests_Get_Checkout_Information( $order_id, $private_id[0] );
		$customer_data = $customer_data->request();
		$customer_data = $customer_data['body'];
		$this->customer_data = json_decode( $customer_data );
		error_log( var_export( $this->customer_data, true ) );
	}
	public function set_not_required( $checkout_fields ) {
		//Set fields to not required, to prevent orders from failing
		if ( 'dibs_easy' === WC()->session->get( 'chosen_payment_method' ) ) {
			foreach ( $checkout_fields as $fieldset_key => $fieldset ) {
				foreach ( $fieldset as $field_key => $field ) {
					$checkout_fields[ $fieldset_key ][ $field_key ]['required'] = false;
				}
			}
		}
		return $checkout_fields;
	}

	public function process_payment( $order_id, $retry = false ) {
		$order = wc_get_order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
