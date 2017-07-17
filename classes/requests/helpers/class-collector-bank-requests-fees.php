<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Requests_Fees {

	public $invoice_fee_id = '';
	public $price = 0;

	public function __construct() {
		$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
		$invoice_fee_id = $collector_settings['collector_invoice_fee'];
		$_product   = wc_get_product( $invoice_fee_id );
		$this->invoice_fee_id = $invoice_fee_id;
		$this->price = $_product->get_regular_price();
	}

	public function fees() {
		$fees = array();
		$shipping = $this->get_shipping();
		$directinvoicenotification = $this->get_invoice_fee();

		$fees['shipping'] = $shipping;
		$fees['directinvoicenotification'] = $directinvoicenotification;

		return $fees;

	}

	public function get_shipping() {
		if ( WC()->cart->needs_shipping() ) {
			WC()->cart->calculate_shipping();
			$packages = WC()->shipping->get_packages();
			$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
			$chosen_shipping = $chosen_methods[0];
			foreach ( $packages as $i => $package ) {
				foreach ( $package['rates'] as $method ) {
					if ( $chosen_shipping === $method->id ) {
						WC()->session->set( 'collector_chosen_shipping', $method->id );
						if ( $method->cost > 0 ) {
							$shipping_item = array(
								'id' => $method->label,
								'description' => $method->label,
								'unitPrice' => $method->cost + array_sum( $method->taxes ),
								'vat' => round( array_sum( $method->taxes ) / $method->cost, 2 ) * 100,
							);
							return $shipping_item;
						} else {
							$shipping_item = array(
								'id' => $method->label,
								'description' => $method->label,
								'unitPrice' => 0,
								'vat' => 0,
							);
							return $shipping_item;
						}
					}
				}
			}
		}
	}

	public function get_invoice_fee() {
		return array(
			'id'            => get_the_title( $this->invoice_fee_id ),
			'description'   => get_the_title( $this->invoice_fee_id ),
			'unitPrice'     => $this->price,
			'vat'           => 0,
		);
	}
}
