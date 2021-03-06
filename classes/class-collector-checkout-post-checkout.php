<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Collector_Checkout_Post_Checkout {

	public function __construct() {
		$collector_settings       = get_option( 'woocommerce_collector_checkout_settings' );
		$this->manage_orders      = $collector_settings['manage_collector_orders'];
		$this->display_invoice_no = $collector_settings['display_invoice_no'];

		if ( 'yes' == $this->manage_orders ) {
			add_action( 'woocommerce_order_status_completed', array( $this, 'collector_order_completed' ) );
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'collector_order_cancel' ) );
		}

		if ( 'yes' == $this->manage_orders ) {
			add_filter( 'woocommerce_order_number', array( $this, 'collector_order_number' ), 1000, 2 );
		}

		add_action( 'init', array( $this, 'check_callback' ), 20 );
	}

	public function collector_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );

		// If this order wasn't created using collector_checkout or collector_invoice payment method, bail.
		if ( ! in_array( $order->get_payment_method(), array( 'collector_checkout', 'collector_invoice' ), true ) ) {
			return;
		}

		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		if ( get_post_meta( $order_id, '_collector_order_activated', true ) ) {
			$order->add_order_note( __( 'Could not activate Collector reservation, Collector reservation is already activated.', 'collector-checkout-for-woocommerce' ) );
			return;
		}

		$activate_order = new Collector_Checkout_SOAP_Requests_Activate_Invoice( $order_id );
		$activate_order->request( $order_id );
	}

	public function collector_order_cancel( $order_id ) {
		$order = wc_get_order( $order_id );

		// If this order wasn't created using collector_checkout or collector_invoice payment method, bail.
		if ( ! in_array( $order->get_payment_method(), array( 'collector_checkout', 'collector_invoice' ), true ) ) {
			return;
		}

		// If the order has not been paid for, bail.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		// If this reservation was already cancelled, do nothing.
		if ( get_post_meta( $order_id, '_collector_order_cancelled', true ) ) {
			$order->add_order_note( __( 'Could not cancel Collector reservation, Collector reservation is already cancelled.', 'collector-checkout-for-woocommerce' ) );
			return;
		}

		$cancel_order = new Collector_Checkout_SOAP_Requests_Cancel_Invoice( $order_id );
		$cancel_order->request( $order_id );
	}

	/**
	 * Check for Collector Invoice Status Change (anti fraud system)
	 **/
	function check_callback() {
		if ( strpos( $_SERVER['REQUEST_URI'], 'module/collectorcheckout/invoicestatus' ) !== false ) {

			if ( ( isset( $_GET['InvoiceNo'] ) && ! empty( $_GET['InvoiceNo'] ) ) && ( isset( $_GET['InvoiceStatus'] ) && ! empty( $_GET['InvoiceStatus'] ) ) ) {
				Collector_Checkout::log( 'Collector Invoice Status Change callback hit' );
				$collector_payment_id = wc_clean( $_GET['InvoiceNo'] );
				$query_args           = array(
					'post_type'   => wc_get_order_types(),
					'post_status' => array_keys( wc_get_order_statuses() ),
					'meta_key'    => '_collector_payment_id',
					'meta_value'  => $collector_payment_id,
				);
				$orders               = get_posts( $query_args );
				$order_id             = $orders[0]->ID;
				$order                = wc_get_order( $order_id );

				if ( is_object( $order ) ) {
					// Add order note about the callback
					$order->add_order_note( sprintf( __( 'Invoice status callback from Collector. New Invoice status: %s', 'collector-checkout-for-woocommerce' ), wc_clean( $_GET['InvoiceStatus'] ) ) );
					// Set orderstatus
					if ( '1' == $_GET['InvoiceStatus'] ) {
						$order->payment_complete( $collector_payment_id );
					} elseif ( '5' == $_GET['InvoiceStatus'] ) {
						$order->update_status( 'failed' );
					}
					header( 'HTTP/1.1 200 Ok' );
				} else {
					Collector_Checkout::log( 'Invoice status callback from Collector but we could not find the corresponding order in WC. Collector InvoiceNo: ' . wc_clean( $_GET['InvoiceNo'] ) . '. InvoiceStatus: ' . wc_clean( $_GET['InvoiceStatus'] ) );
					header( 'HTTP/1.0 404 Not Found' );
				}
			} else {
				Collector_Checkout::log( 'HTTP Request from Collector is missing parameters' );
				header( 'HTTP/1.0 400 Bad Request' );
			}
			die();
		}
	}

	/**
	 * Display Collector payment id after WC order number on order overwiev page
	 **/
	public function collector_order_number( $order_number, $order ) {
		if ( is_admin() ) {
			// Check if function get_current_screen() exist
			if ( ! function_exists( 'get_current_screen' ) ) {
				return $order_number;
			}

			$current_screen = get_current_screen();
			if ( is_object( $current_screen ) && 'edit-shop_order' == $current_screen->id ) {
				$collector_payment_id = null !== get_post_meta( $order->get_id(), '_collector_payment_id', true ) ? get_post_meta( $order->get_id(), '_collector_payment_id', true ) : '';
				// $collector_payment_id = get_post_meta( $order->get_id(), '_collector_payment_id', true );
				if ( $collector_payment_id ) {
					$order_number .= ' (' . $collector_payment_id . ')';
				}
			}
		}
		return $order_number;
	}
}
$collector_checkout_post_checkout = new Collector_Checkout_Post_Checkout();
