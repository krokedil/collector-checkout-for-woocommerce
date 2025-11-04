<?php
/**
 * Order helper class.
 *
 * @package Collector_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_Requests_Helper_Order_Fees
 */
class Collector_Checkout_Requests_Helper_Order_Fees {

	/**
	 * Price with tax included, based on store settings or an empty string if price calculation failed.
	 *
	 * @var float|string
	 */
	public $price = 0;

	/**
	 * Delivery module
	 *
	 * @var string
	 */
	public $delivery_module;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );

		switch ( get_woocommerce_currency() ) {
			case 'SEK':
				$this->delivery_module = walley_is_delivery_enabled( 'se', $collector_settings );
				break;
			case 'NOK':
				$this->delivery_module = walley_is_delivery_enabled( 'no', $collector_settings );
				break;
			case 'DKK':
				$this->delivery_module = walley_is_delivery_enabled( 'dk', $collector_settings );
				break;
			case 'EUR':
				$this->delivery_module = walley_is_delivery_enabled( 'fi', $collector_settings );
				break;
			default:
				$this->delivery_module = walley_is_delivery_enabled( 'se', $collector_settings );
				break;
		}
	}

	/**
	 * Gets order fees for the order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function get_order_fees( $order_id ) {
		$order            = wc_get_order( $order_id );
		$fees             = array();
		$shipping         = $this->get_shipping( $order );
		$fees['shipping'] = $shipping;

		return $fees;
	}

	/**
	 * Gets the shipping for the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return array|void
	 */
	public function get_shipping( $order ) {

		if ( empty( $order->get_shipping_method() ) ) {
			return;
		}

		if ( $order->get_shipping_total() > 0 ) {
			$shipping_items     = $order->get_items( 'shipping' );
			$shipping_method_id = reset( $shipping_items )->get_method_id();
			return array(
				'id'          => 'shipping|' . substr( $shipping_method_id, 0, 50 ),
				'description' => substr( $order->get_shipping_method(), 0, 50 ),
				'unitPrice'   => $order->get_shipping_total() + $order->get_shipping_tax(), // Float.
				'vat'         => ( ! empty( floatval( $order->get_shipping_tax() ) ) ) ? $this->get_product_tax_rate( $order, current( $order->get_items( 'shipping' ) ) ) : 0, // Float.
			);
		} else {
			// todo $shipping_method_id is here probably undefined.
			return array(
				'id'          => 'shipping|' . substr( $shipping_method_id, 0, 50 ),
				'description' => substr( $order->get_shipping_method(), 0, 50 ),
				'unitPrice'   => 0, // Float.
				'vat'         => 0, // Float.
			);
		}
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
