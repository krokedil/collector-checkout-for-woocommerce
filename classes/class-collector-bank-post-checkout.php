<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Collector_Bank_Post_Checkout {

	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'collector_order_completed' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'collector_order_cancel' ) );
	}

	public function collector_order_completed( $order_id ) {
		$activate_order = new Collector_Bank_SOAP_Requests_Activate_Invoice();
		$activate_order->request( $order_id );
	}
	public function collector_order_cancel( $order_id ) {
		$cancel_order = new Collector_Bank_SOAP_Requests_Cancel_Invoice();
		$cancel_order->request( $order_id );
	}
}
$collector_bank_post_checkout = new Collector_Bank_Post_Checkout();
