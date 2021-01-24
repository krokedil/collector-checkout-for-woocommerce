<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Checkout_Requests_Helper_Order_Fees {

	public $invoice_fee_id = '';
	public $price          = 0;

	public function __construct() {
		$collector_settings   = get_option( 'woocommerce_collector_checkout_settings' );
		$invoice_fee_id       = $collector_settings['collector_invoice_fee'];
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

	public function get_order_fees() {
		$fees = array();
		if ( 'no' === $this->delivery_module ) {
			$shipping         = $this->get_shipping();
			$fees['shipping'] = $shipping;
		}

		if ( $this->invoice_fee_id ) {
			$_product = wc_get_product( $this->invoice_fee_id );
			if ( is_object( $_product ) ) {
				$directinvoicenotification         = $this->get_invoice_fee( $_product );
				$fees['directinvoicenotification'] = $directinvoicenotification;
			}
		}

		return $fees;

	}

	public function get_shipping( $order ) {
		if ( $order->get_shipping_total() > 0 ) {
			$shipping_items     = $order->get_items( 'shipping' );
			$shipping_method_id = reset( $shipping_items )->get_method_id();
			return array(
				'id'          => 'shipping|' . substr( $shipping_method_id, 0, 50 ),
				'description' => $order->get_shipping_method(),
				'unitPrice'   => $order->get_shipping_total() + $order->get_shipping_tax(), // Float.
				'vat'         => ( '0' !== $order->get_shipping_tax() ) ? $this->get_product_tax_rate( $order, current( $order->get_items( 'shipping' ) ) ) : 0, // Float.
			);
		} else {
			return array(
				'id'          => 'shipping|' . substr( $shipping_method_id, 0, 50 ),
				'description' => $order->get_shipping_method(),
				'unitPrice'   => 0, // Float.
				'vat'         => 0, // Float.
			);
		}
	}

	public function get_invoice_fee( $_product ) {

		$price = wc_get_price_including_tax( $_product );

		$_tax      = new WC_Tax();
		$tmp_rates = $_tax->get_base_tax_rates( $_product->get_tax_class() );
		$_vat      = array_shift( $tmp_rates );// Get the rate.
		// Check what kind of tax rate we have.
		if ( $_product->is_taxable() && isset( $_vat['rate'] ) ) {
			$vat_rate = round( $_vat['rate'] );
		} else {
			// if empty, set 0% as rate
			$vat_rate = 0;
		}

		return array(
			'id'          => 'invoicefee|' . Collector_Checkout_Requests_Cart::get_sku( $_product, $_product->get_id() ),
			'description' => $_product->get_title(),
			'unitPrice'   => round( $price, 2 ),
			'vat'         => $vat_rate,
		);
	}

	/**
	 * Gets the tax rate for the product.
	 *
	 * @param object $order The order item.
	 * @param object $order_item The WooCommerce order item.
	 * @return float
	 */
	public function get_product_tax_rate( $order, $order_item ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			if ( key( $order_item->get_taxes()['total'] ) === $rate_id ) {
				return round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] / 100, 2 );
			}
		}
	}
}
