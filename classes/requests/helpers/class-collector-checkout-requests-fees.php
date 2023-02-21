<?php
/**
 * The class represents helpers functions for requests fees.
 *
 * @package Collector_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_Requests_Fees.
 */
class Collector_Checkout_Requests_Fees {

	/**
	 * Invoice fee ID
	 *
	 * @var string
	 */
	public $invoice_fee_id = '';

	/**
	 * Price with tax included, based on store settings or an empty string if price calculation failed.
	 *
	 * @var float|string
	 */
	public $price = 0;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$collector_settings   = get_option( 'woocommerce_collector_checkout_settings' );
		$invoice_fee_id       = $collector_settings['collector_invoice_fee'] ?? '';
		$this->invoice_fee_id = $invoice_fee_id;

		switch ( get_woocommerce_currency() ) {
			case 'SEK':
				$this->delivery_module = isset( $collector_settings['collector_delivery_module_se'] ) ? $collector_settings['collector_delivery_module_se'] : 'no';
				break;
			case 'NOK':
				$this->delivery_module = isset( $collector_settings['collector_delivery_module_no'] ) ? $collector_settings['collector_delivery_module_no'] : 'no';
				break;
			case 'DKK':
				$this->delivery_module = isset( $collector_settings['collector_delivery_module_dk'] ) ? $collector_settings['collector_delivery_module_dk'] : 'no';
				break;
			case 'EUR':
				$this->delivery_module = isset( $collector_settings['collector_delivery_module_fi'] ) ? $collector_settings['collector_delivery_module_fi'] : 'no';
				break;
			default:
				$this->delivery_module = isset( $collector_settings['collector_delivery_module_se'] ) ? $collector_settings['collector_delivery_module_se'] : 'no';
				break;
		}
	}

	/**
	 * Gets the fees.
	 *
	 * @return array|string
	 */
	public function fees() {
		$fees = array();

		/**
		 * If a delivery module is not used, the shipping is handled in WooCommerce, and must be sent in the fee.shipping object. Retrieves first available.
		 * Note: if no shipping is available, fees.shipping is simply unset, NOT null.
		 */
		$update_shipping = apply_filters( 'coc_update_shipping', ( 'no' === $this->delivery_module ) ); // Filter on if we should update shipping with fees or not.
		$shipping        = $this->get_shipping();
		if ( ( WC()->cart->show_shipping() && $update_shipping ) && ! empty( $shipping ) ) {
			$fees['shipping'] = $shipping;
		}

		/**
		 * If "Hide shipping cost until address is entered" is enabled, we don't want to send the shipping cost yet,
		 * as this will cause a discrepancy between WooCommerce and Walley since the WooCommerce shipping cost is zero.
		 */
		if ( ! WC()->cart->show_shipping() ) {
			unset( $fees['shipping'] );
		}

		if ( $this->invoice_fee_id ) {
			$_product = wc_get_product( $this->invoice_fee_id );
			if ( is_object( $_product ) ) {
				$directinvoicenotification         = $this->get_invoice_fee( $_product );
				$fees['directinvoicenotification'] = $directinvoicenotification;
			}
		}

		// Don't return an array if it's empty.
		if ( empty( $fees ) ) {
			$fees = '';
		}

		return $fees;

	}

	/**
	 * Gets the shipping.
	 *
	 * @return array|void
	 */
	public function get_shipping() {
		if ( WC()->cart->needs_shipping() ) {
			WC()->cart->calculate_shipping();
			$packages        = WC()->shipping->get_packages();
			$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
			$chosen_shipping = $chosen_methods[0];
			foreach ( $packages as $i => $package ) {
				foreach ( $package['rates'] as $method ) {
					if ( $chosen_shipping === $method->id ) {
						WC()->session->set( 'collector_chosen_shipping', $method->id );
						if ( $method->cost > 0 ) {
							$shipping_item = array(
								'id'          => 'shipping|' . substr( $method->id, 0, 50 ),
								'description' => substr( $method->label, 0, 50 ),
								'unitPrice'   => round( $method->cost + array_sum( $method->taxes ), 2 ),
								'vat'         => round( array_sum( $method->taxes ) / $method->cost, 2 ) * 100,
							);
							return $shipping_item;
						} else {
							$shipping_item = array(
								'id'          => 'shipping|' . $method->id,
								'description' => substr( $method->label, 0, 50 ),
								'unitPrice'   => 0,
								'vat'         => 0,
							);
							return $shipping_item;
						}
					}
				}
			}
		}
	}

	/**
	 * Gets the invoice fee for the WooCommerce product.
	 *
	 * @param WC_Product $_product The WooCommerce product.
	 *
	 * @return array
	 */
	public function get_invoice_fee( $_product ) {

		$price = wc_get_price_including_tax( $_product );

		$_tax      = new WC_Tax();
		$tmp_rates = $_tax->get_base_tax_rates( $_product->get_tax_class() );
		$_vat      = array_shift( $tmp_rates );// Get the rate.
		// Check what kind of tax rate we have.
		if ( $_product->is_taxable() && isset( $_vat['rate'] ) ) {
			$vat_rate = round( $_vat['rate'] );
		} else {
			// if empty, set 0% as rate.
			$vat_rate = 0;
		}

		return array(
			'id'          => 'invoicefee|' . Collector_Checkout_Requests_Cart::get_sku( $_product, $_product->get_id() ),
			'description' => substr( $_product->get_title(), 0, 50 ),
			'unitPrice'   => wc_format_decimal( $price, 2 ),
			'vat'         => $vat_rate,
		);
	}
}
