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
		add_action( 'wp_ajax_update_fees', array( $this, 'update_fees' ) );
		add_action( 'wp_ajax_nopriv_update_fees', array( $this, 'update_fees' ) );

		// Ajax to add order notes as a session for the customer
		add_action( 'wp_ajax_dibs_customer_order_note', array( $this, 'add_customer_order_note' ) );
		add_action( 'wp_ajax_nopriv_dibs_customer_order_note', array( $this, 'add_customer_order_note' ) );
	}

	public function get_public_token() {
		// Maybe create local order
		$order_id = $this->helper_maybe_create_local_order();

		$init_checkout = new Collector_Bank_Requests_Initialize_Checkout( $order_id );
		$request = $init_checkout->request();

		$decode = json_decode( $request['body'] );
		$return = $decode->data->publicToken;
		// Set post metas so they can be used again later
		update_post_meta( $order_id, '_collector_public_token', $return );
		update_post_meta( $order_id, '_collector_private_id', $decode->data->privateId );

		wp_send_json_success( $return );
		wp_die();
	}

	public function update_fees() {
		$order_id = $this->helper_maybe_create_local_order();
		$private_id = get_post_meta( $order_id, '_collector_private_id' );
		$update_fees = new Collector_Bank_Requests_Update_Fees( $order_id, $private_id[0] );
		$update_fees->request();

		wp_send_json_success();
		wp_die();
	}

	public function helper_maybe_create_local_order() {
		if ( WC()->session->get( 'order_awaiting_payment' ) > 0 ) { // Create local order if there already isn't an order awaiting payment.
			$local_order_id = WC()->session->get( 'order_awaiting_payment' );
			$local_order    = wc_get_order( $local_order_id );
			$local_order->update_status( 'pending' ); // If the order was failed in the past, unfail it because customer was successfully authenticated again.
		} else {
			$local_order    = wc_create_order();
			$local_order_id = $local_order->id;
			WC()->session->set( 'order_awaiting_payment', $local_order_id );
			do_action( 'woocommerce_checkout_update_order_meta', $local_order_id, array() ); // Let plugins add their own meta data.
		}
		return $local_order_id;
	}

	public function dibs_add_customer_order_note() {
		WC()->session->set( 'collector_customer_order_note', $_POST['order_note'] );
		wp_send_json_success();
		wp_die();
	}
}
$collector_ajax_calls = new Collector_Bank_Ajax_Calls();
