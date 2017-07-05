<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Requests_Fees {
	public static function fees() {
		$fees = array();
		$shipping = self::get_shipping();
		$directinvoicenotification = self::get_invoice_fee();

		$fees['shipping'] = $shipping;
		$fees['directinvoicenotification'] = $directinvoicenotification;

		return $fees;

	}

	public static function get_shipping() {
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
								'vat' => round( array_sum( $method->taxes ) / $method->cost, 2 ),
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

	public static function get_invoice_fee() {
		return array(
			'id'            => 'Invoice Fee',
			'description'   => 'Invoice Fee incl VAT',
			'unitPrice'     => 19,
			'vat'           => 25,
		);
	}
}
