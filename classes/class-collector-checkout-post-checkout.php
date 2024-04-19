<?php
/**
 * Collector_Checkout_Post_Checkout
 *
 * @package  Collector/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_Post_Checkout
 */
class Collector_Checkout_Post_Checkout {

	/**
	 * Class constructor
	 */
	public function __construct() {
		$collector_settings                    = get_option( 'woocommerce_collector_checkout_settings' );
		$this->manage_orders                   = $collector_settings['manage_collector_orders'];
		$this->display_invoice_no              = $collector_settings['display_invoice_no'];
		$this->activate_individual_order_lines = $collector_settings['activate_individual_order_lines'] ?? 'no';

		if ( 'yes' === $this->manage_orders ) {
			add_action( 'woocommerce_order_status_completed', array( $this, 'collector_order_completed' ) );
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'collector_order_cancel' ) );
		}

		if ( 'yes' === $this->manage_orders ) {
			add_filter( 'woocommerce_order_number', array( $this, 'collector_order_number' ), 1000, 2 );
		}

		add_action( 'init', array( $this, 'check_callback' ), 20 );
	}

	/**
	 *  Activate Collector order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return void
	 * @throws SoapFault Soap Fault.
	 */
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

		if ( $order->get_meta( '_collector_order_activated', true ) ) {
			$order->add_order_note( __( 'Could not activate Walley reservation, Walley reservation is already activated.', 'collector-checkout-for-woocommerce' ) );
			return;
		}

		// Part activate or activate the entire order.
		if ( 'yes' === $this->activate_individual_order_lines ) {
			$activate_order = new Collector_Checkout_SOAP_Requests_Part_Activate_Invoice( $order_id );
			$activate_order->request( $order_id );
		} else {
			$activate_order = new Collector_Checkout_SOAP_Requests_Activate_Invoice( $order_id );
			$activate_order->request( $order_id );
		}
	}

	/**
	 * Cancel the Collector order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return void
	 * @throws SoapFault Soap Fault.
	 */
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
		if ( $order->get_meta( '_collector_order_cancelled', true ) ) {
			$order->add_order_note( __( 'Could not cancel Walley reservation, Walley reservation is already cancelled.', 'collector-checkout-for-woocommerce' ) );
			return;
		}

		$cancel_order = new Collector_Checkout_SOAP_Requests_Cancel_Invoice( $order_id );
		$cancel_order->request( $order_id );
	}

	/**
	 * Check for Collector Invoice Status Change (anti fraud system)
	 **/
	public function check_callback() {
		if ( ! empty( $_SERVER['REQUEST_URI'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'module/collectorcheckout/invoicestatus' ) ) {
			$invoice_no     = filter_input( INPUT_GET, 'InvoiceNo', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$invoice_status = filter_input( INPUT_GET, 'InvoiceStatus', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! empty( $invoice_no ) && ! empty( $invoice_status ) ) {
				CCO_WC()->logger::log( 'Collector Invoice Status Change callback hit' );
				$collector_payment_id = $invoice_no;
				$order                = walley_get_order_by_key( '_collector_payment_id', $collector_payment_id );

				if ( ! empty( $order ) ) {
					// Add order note about the callback
					// translators: Invoice status.
					$order->add_order_note( sprintf( __( 'Invoice status callback from Walley. New Invoice status: %s', 'collector-checkout-for-woocommerce' ), $invoice_no ) );
					// Set order status.
					if ( '1' === $invoice_status ) {
						$order->payment_complete( $collector_payment_id );
					} elseif ( '5' === $invoice_status ) {
						$order->update_status( 'failed' );
					}
					header( 'HTTP/1.1 200 Ok' );
				} else {
					$collector_info = sprintf( 'Invoice status callback from Collector but we could not find the corresponding order in WC. Collector InvoiceNo: %s InvoiceStatus: %s', $invoice_no, $invoice_status );
					// TODO log function not found in Collector_Checkout!
					CCO_WC()->logger::log( $collector_info );
					header( 'HTTP/1.0 404 Not Found' );
				}
			} else {
				CCO_WC()->logger::log( 'HTTP Request from Collector is missing parameters' );
				header( 'HTTP/1.0 400 Bad Request' );
			}
			die();
		}
	}

	/**
	 * Display Collector payment id after WC order number on orders overview page
	 *
	 * @param string   $order_number The WooCommerce order number.
	 * @param WC_Order $order The WooCommerce order.
	 **/
	public function collector_order_number( $order_number, $order ) {
		if ( is_admin() ) {
			// Check if function get_current_screen() exist.
			if ( ! function_exists( 'get_current_screen' ) ) {
				return $order_number;
			}

			$current_screen = get_current_screen();
			if ( isset( $current_screen ) && in_array( $current_screen->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ) ) ) {
				$collector_payment_id = $order->get_meta( '_collector_payment_id' );
				if ( ! empty( $collector_payment_id ) ) {
					$order_number .= ' (' . $collector_payment_id . ')';
				}
			}
		}
		return $order_number;
	}
}

