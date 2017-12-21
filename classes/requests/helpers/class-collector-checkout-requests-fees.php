<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Checkout_Requests_Fees {

	public $invoice_fee_id = '';
	public $price = 0;

	public function __construct() {
		$collector_settings 	= get_option( 'woocommerce_collector_checkout_settings' );
		$invoice_fee_id 		= $collector_settings['collector_invoice_fee'];
		$this->invoice_fee_id 	= $invoice_fee_id;
		
	}

	public function fees() {
		$fees = array();
		$shipping = $this->get_shipping();
		$fees['shipping'] = $shipping;

		if( $this->invoice_fee_id ) {
			$_product   = wc_get_product( $this->invoice_fee_id );
			if ( is_object( $_product ) ) {
				$directinvoicenotification 			= $this->get_invoice_fee( $_product );
				$fees['directinvoicenotification'] 	= $directinvoicenotification;
			}
			
		}

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
								'id' => 'shipping|' . $method->id,
								'description' => $method->label,
								'unitPrice' => round( $method->cost + array_sum( $method->taxes ), 2 ),
								'vat' => round( array_sum( $method->taxes ) / $method->cost, 2 ) * 100,
							);
							return $shipping_item;
						} else {
							$shipping_item = array(
								'id' => 'shipping|' . $method->id,
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

	public function get_invoice_fee( $_product ) {

		$price = wc_get_price_including_tax( $_product );

		$_tax = new WC_Tax();
		$tmp_rates = $_tax->get_base_tax_rates( $_product->get_tax_class() );
		$_vat = array_shift( $tmp_rates );// Get the rate.
		//Check what kind of tax rate we have.
		if ( $_product->is_taxable() && isset( $_vat['rate'] ) ) {
			$vat_rate = round( $_vat['rate'] );
		} else {
			//if empty, set 0% as rate
			$vat_rate = 0;
		}

		return array(
			'id'            => 'invoicefee|' . Collector_Checkout_Requests_Cart::get_sku( $_product, $_product->get_id() ),
			'description'   => $_product->get_title(),
			'unitPrice'     => round( $price, 2 ),
			'vat'           => $vat_rate,
		);
	}
}
