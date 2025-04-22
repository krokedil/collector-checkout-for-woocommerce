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
class Walley_Checkout_Requests_Fees_Helper {

	/**
	 * Price with tax included, based on store settings or an empty string if price calculation failed.
	 *
	 * @var float|string
	 */
	public $price = 0;

	/**
	 * Gets the delivery module settings for the currency.
	 *
	 * @return string
	 */
	public static function get_delivery_module() {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		switch ( get_woocommerce_currency() ) {
			case 'SEK':
				$delivery_module = isset( $collector_settings['collector_delivery_module_se'] ) ? $collector_settings['collector_delivery_module_se'] : 'no';
				break;
			case 'NOK':
				$delivery_module = isset( $collector_settings['collector_delivery_module_no'] ) ? $collector_settings['collector_delivery_module_no'] : 'no';
				break;
			case 'DKK':
				$delivery_module = isset( $collector_settings['collector_delivery_module_dk'] ) ? $collector_settings['collector_delivery_module_dk'] : 'no';
				break;
			case 'EUR':
				$delivery_module = isset( $collector_settings['collector_delivery_module_fi'] ) ? $collector_settings['collector_delivery_module_fi'] : 'no';
				break;
			default:
				$delivery_module = isset( $collector_settings['collector_delivery_module_se'] ) ? $collector_settings['collector_delivery_module_se'] : 'no';
				break;
		}

		return $delivery_module;
	}

	/**
	 * Gets the fees.
	 *
	 * @return array|string
	 */
	public static function fees() {
		$fees = array();

		/**
		 * If a delivery module is not used, the shipping is handled in WooCommerce, and must be sent in the fee.shipping object. Retrieves first available.
		 * Note: if no shipping is available, fees.shipping is simply unset, NOT null.
		 */
		$update_shipping = apply_filters( 'coc_update_shipping', ( 'no' === self::get_delivery_module() ) ); // Filter on if we should update shipping with fees or not.
		$shipping        = self::get_shipping();
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
	public static function get_shipping() {
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
	 * Gets the product SKU.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @param int        $product_id WooCommerce product ID.
	 * @return string
	 */
	public static function get_sku( $product, $product_id = 0 ) {
		$part_number = ! empty( $product->get_sku() ) ? $product->get_sku() : $product->get_id();
		return substr( $part_number, 0, 32 );
	}
}
