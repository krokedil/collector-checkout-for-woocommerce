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
			if ( empty( floatval( $item['line_total'] ) ) ) {
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
			$item_line = self::create_item( self::get_sku( $product, $product_id ), $item_name, $item['line_total'], $item['quantity'], $item['line_tax'], $product );
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

		// Maybe add a rounding fee to Walley if needed.
		if ( 'yes' === walley_add_rounding_order_line() ) {
			// Compare and fix differences in total amounts due to rounding.
			self::rounding_fee( $items );
		}

		// Check if we need to make any id/sku's unique (required by Collector).
		$items = self::maybe_make_ids_unique( $items );

		$return['items'] = $items;
		return $return;
	}

	/**
	 * Creates line item for collector.
	 *
	 * @param string     $sku Product SKU.
	 * @param string     $product_name Product name.
	 * @param float      $line_total Line total.
	 * @param int        $quantity Line quantity.
	 * @param float      $line_tax Line tax.
	 * @param WC_Product $product The WooCommerce product.
	 * @return array
	 */
	public static function create_item( $sku, $product_name, $line_total, $quantity, $line_tax, $product = null ) {
		$configured_item = array(
			'id'          => $sku,
			'description' => substr( $product_name, 0, 50 ),
			'unitPrice'   => wc_format_decimal( ( $line_total + $line_tax ) / $quantity, 2 ), // Total price per unit including VAT.
			'quantity'    => $quantity,
			'vat'         => ( empty( floatval( $line_total ) ) ) ? 0 : round( $line_tax / $line_total, 2 ) * 100,
		);

		// Only check this on product line items.
		if ( $product && 'yes' === self::get_add_product_electronic_id_fields() ) {
			$product_id                              = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$product                                 = wc_get_product( $product_id );
			$collector_requires_electronic_id        = 'yes' === $product->get_meta( '_collector_requires_electronic_id' ) ? true : false;
			$configured_item['requiresElectronicId'] = $collector_requires_electronic_id;
		}

		if ( $product && ! empty( $product->get_weight() ) ) {
			$configured_item['unitWeight'] = round( wc_get_weight( $product->get_weight(), 'kg' ), 2 );
		}

		return apply_filters( 'coc_cart_item', $configured_item );
	}

	/**
	 * Compare and fix rounded total amounts in WooCommerce and Collector.
	 *
	 * @param array $collector_items The cart order line items array.
	 * @return void
	 */
	public static function rounding_fee( &$collector_items ) {
		$rounding_item = array(
			'id'          => 'rounding-fee',
			'description' => __( 'Rounding fee', 'collector-checkout-for-woocommerce' ),
			'unitPrice'   => 0.00,
			'quantity'    => 1,
			'vat'         => 0.0,
		);

		// Get WooCommerce cart totals including tax.
		$wc_total        = WC()->cart->get_total( 'total' );
		$collector_total = 0;

		/**
		 * If shipping is handled by WooCommerce, it will be passed separately in the fees.shipping object.
		 * Since the $wc_total include the shipping cost, it must be subtracted so that both $wc_total and $collector_total
		 * only include item cost.
		 */
		if ( WC()->cart->show_shipping() ) {
			$wc_total -= WC()->cart->get_shipping_total() + WC()->cart->get_shipping_tax();
		}

		// Add all collector item amounts together.
		foreach ( $collector_items as $collector_item ) {
			$collector_total += wc_format_decimal( $collector_item['unitPrice'] * $collector_item['quantity'], 2 );
		}

		// Set the unitprice for the rounding fee to the difference between WooCommerce and Collector.
		$rounding_item['unitPrice'] = wc_format_decimal( $wc_total - $collector_total, 2 );

		// Add the rounding item to the collector items only if the price is not zero.
		if ( ! empty( floatval( $rounding_item['unitPrice'] ) && abs( floatval( $rounding_item['unitPrice'] ) ) < 3 ) ) {
			$collector_items[] = $rounding_item;
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
				'description' => substr( $fee->name, 0, 50 ),
				'unitPrice'   => wc_format_decimal( $fee_amount, 2 ),
				'quantity'    => 1,
				'vat'         => $fee_tax_rate,
			);

			array_push( $items, $fee_item );
		} // End foreach(). phpcs:ignore Squiz.PHP.CommentedOutCode.Found
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
					if ( $id_name === $item['id'] ) {// TODO Use strict comparisons === !
						$items[ $key ]['id'] = $item['id'] . '_' . $i;
						++$i;
					}
				}
			}
		}
		return $items;
	}

	/**
	 * See if product Require electronic id fields should be sent to Collector.
	 *
	 * @return string
	 */
	public static function get_add_product_electronic_id_fields() {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		// TODO inline variable.
		$add_product_fields = ! empty( $collector_settings['requires_electronic_id_fields'] ) ? $collector_settings['requires_electronic_id_fields'] : 'no';
		return $add_product_fields;
	}
}
