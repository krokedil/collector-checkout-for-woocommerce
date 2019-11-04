<?php
/**
 * Class file for cart processing.
 *
 * @package Collector_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for cart processing.
 */
class Collector_Checkout_Requests_Cart {

	/**
	 * Processes the cart for requests.
	 *
	 * @return array
	 */
	public static function cart() {
		// Get cart contents.
		$wc_cart         = WC()->cart->get_cart_contents();
		$wc_cart_coupons = WC()->cart->get_coupons();
		// Set the return array.
		$items = array();
		// Loop through cart items and make an item line for each.
		foreach ( $wc_cart as $item ) {
			// Don't send items with a price of 0.
			if ( 0 == $item['line_total'] ) {
				continue;
			}
			if ( $item['variation_id'] ) {
				$product    = wc_get_product( $item['variation_id'] );
				$product_id = $item['variation_id'];
			} else {
				$product    = wc_get_product( $item['product_id'] );
				$product_id = $item['product_id'];
			}
			$item_name = wc_get_product( $product_id );
			$item_name = $item_name->get_name();
			$item_line = self::create_item( self::get_sku( $product, $product_id ), $item_name, $item['line_total'], $item['quantity'], $item['line_tax'] );
			array_push( $items, $item_line );
		}

		if ( ! empty( WC()->cart->get_fees() ) ) {
			$items = self::get_fees( $items );
		}

		// Smart coupons.
		foreach ( $wc_cart_coupons as $coupon ) {
			if ( 'smart_coupon' === $coupon->get_discount_type() ) {
				$item_line = self::create_item( $coupon->get_id(), __( 'Gift Card', 'collector-checkout-for-woocommerce' ), $coupon->get_amount() * -1, 1, 0 );
				array_push( $items, $item_line );
			}
		}

		// Check if we need to make any id/sku's unique (required by Collector).
		$items = self::maybe_make_ids_unique( $items );

		$return['items'] = $items;
		return $return;
	}

	/**
	 * Creates line item for collector.
	 *
	 * @param string $sku Product SKU.
	 * @param string $product_name Product name.
	 * @param int    $line_total Line total.
	 * @param int    $quantity Line quantity.
	 * @param float  $line_tax Line tax.
	 * @return array
	 */
	public static function create_item( $sku, $product_name, $line_total, $quantity, $line_tax ) {
		return array(
			'id'          => $sku,
			'description' => $product_name,
			'unitPrice'   => round( ( $line_total + $line_tax ) / $quantity, 2 ), // Total price per unit including VAT.
			'quantity'    => $quantity,
			'vat'         => round( $line_tax / $line_total, 2 ) * 100,
		);
	}

	/**
	 * Gets the product SKU.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @param int        $product_id WooCommerce product ID.
	 * @return string
	 */
	public static function get_sku( $product, $product_id ) {
		if ( get_post_meta( $product_id, '_sku', true ) !== '' ) {
			$part_number = $product->get_sku();
		} else {
			$part_number = $product->get_id();
		}
		return substr( $part_number, 0, 32 );
	}

	/**
	 * Get fees for the cart.
	 *
	 * @param array $items Fee items.
	 * @return array
	 */
	public static function get_fees( $items ) {

		foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {

			$fee_tax_amount = round( $fee->tax * 100 );
			$fee_amount     = round( ( $fee->amount + $fee->tax ), 2 );
			$_tax           = new WC_Tax();
			$tmp_rates      = $_tax->get_rates( $fee->tax_class );
			$vat            = array_shift( $tmp_rates );
			if ( isset( $vat['rate'] ) ) {
				$fee_tax_rate = round( $vat['rate'] );
			} else {
				$fee_tax_rate = 0;
			}

			$fee_item = array(
				'id'          => 'fee|' . $fee->id,
				'description' => $fee->name,
				'unitPrice'   => $fee_amount,
				'quantity'    => 1,
				'vat'         => $fee_tax_rate,
			);

			array_push( $items, $fee_item );
		} // End foreach().
		return $items;
	}

	/**
	 * Checks to make sure that all ids are unique.
	 *
	 * @param array $items List of order line items.
	 * @return array
	 */
	public static function maybe_make_ids_unique( $items ) {
		$ids = array();
		foreach ( $items as $item ) {
			$ids[] = $item['id'];
		}
		// List all ids as 'id_name' => number_of_apperances_in_array.
		$ids = array_count_values( $ids );

		foreach ( $ids as $id_name => $appearances ) {
			if ( $appearances > 1 ) {
				$i = 0;
				// Loop trough all ids that appeare more than 1 time.
				foreach ( $items as $key => $item ) {
					if ( $id_name == $item['id'] ) {
						$items[ $key ]['id'] = $item['id'] . '_' . $i;
						$i++;
					}
				}
			}
		}
		return $items;
	}
}
