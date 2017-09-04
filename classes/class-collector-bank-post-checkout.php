<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Collector_Bank_Post_Checkout {

	public function __construct() {
		$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
		$this->manage_orders = $collector_settings['manage_collector_orders'];
		$this->display_invoice_no = $collector_settings['display_invoice_no'];
		
		if ( 'yes' == $this->manage_orders ) {
			add_action( 'woocommerce_order_status_completed', array( $this, 'collector_order_completed' ) );
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'collector_order_cancel' ) );
		}
		
		if ( 'yes' == $this->manage_orders ) {
			add_filter ('woocommerce_order_number', array( $this, 'collector_order_number' ), 1000, 2 );
		}
		
		add_action( 'init', array( $this, 'check_callback' ), 20 );
	}

	public function collector_order_completed( $order_id ) {
		$activate_order = new Collector_Bank_SOAP_Requests_Activate_Invoice( $order_id );
		$activate_order->request( $order_id );
	}
	public function collector_order_cancel( $order_id ) {
		$cancel_order = new Collector_Bank_SOAP_Requests_Cancel_Invoice( $order_id );
		$cancel_order->request( $order_id );
	}
	
	/**
	 * Check for Collector Invoice Status Change (anti fraud system)
	 **/
	function check_callback() {
		if ( strpos( $_SERVER["REQUEST_URI"], 'module/collectorcheckout/invoicestatus' ) !== false ) {

			header( "HTTP/1.1 200 Ok" );

			if( isset( $_GET['InvoiceNo'] ) && isset( $_GET['OrderNo'] ) && isset( $_GET['InvoiceStatus'] ) ) {
				
				$collector_payment_id = wc_clean( $_GET['InvoiceNo'] );
				$query_args = array(
					'post_type' => wc_get_order_types(),
					'post_status' => array_keys( wc_get_order_statuses() ),
					'meta_key' => '_collector_payment_id',
					'meta_value' => $collector_payment_id,
				);
				$orders = get_posts( $query_args );
				$order_id = $orders[0]->ID;
				$order = wc_get_order( $order_id );
				
				$order->add_order_note( sprintf( __( 'Invoice status callback from Collector. New Invoice status: %s', 'collector-checkout-for-woocommerce' ), wc_clean( $_GET['InvoiceStatus'] ) ) );
				
				if( '1' == $_GET['InvoiceStatus'] ) {
					$order->payment_complete( $collector_payment_id );
				} elseif ( '5' == $_GET['InvoiceStatus'] ) {
					$order->update_status( 'failed' );
				}
			}
			die();	
		}
	}
	
	/**
	 * Display Collector payment id after WC order number on order overwiev page
	 **/
	public function collector_order_number( $order_number, $order ) {
		if( is_admin() ) {
			$current_screen = get_current_screen();
			if( 'edit-shop_order' == $current_screen->id ) {
				$collector_payment_id = get_post_meta( $order->id, '_collector_payment_id', true );
				if( $collector_payment_id ) {
					$order_number .= ' (' . $collector_payment_id . ')';
				}
			}
		}
		return $order_number;
	}
}
$collector_bank_post_checkout = new Collector_Bank_Post_Checkout();
