<?php
/**
 * Gets the order information from an order.
 *
 * @package Collector_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for processing order lines from a WooCommerce order.
 */
class Collector_Checkout_Requests_Helper_Order {

	/**
	 * Gets the order lines for the order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	public static function get_order_lines( $order_id ) {

		$order       = wc_get_order( $order_id );
		$order_lines = array();

		foreach ( $order->get_items() as $item ) {
			array_push( $order_lines, self::get_order_line_items( $item ) );
		}
		foreach ( $order->get_fees() as $fee ) {
			array_push( $order_lines, self::get_order_line_fees( $fee ) );
		}

		// Maybe add a rounding fee to Walley if needed.
		if ( 'yes' === walley_add_rounding_order_line() ) {
			self::rounding_fee( $order_lines, $order );
		}
		return array( 'items' => $order_lines );
	}

	/**
	 * Compare and fix rounded total amounts in WooCommerce and Collector.
	 *
	 * @param array    $order_lines The cart order line items array.
	 * @param WC_Order $order The WooCommerce Order.
	 * @return void
	 */
	public static function rounding_fee( &$order_lines, $order ) {
		$rounding_item = array(
			'id'          => 'rounding-fee',
			'description' => __( 'Rounding fee', 'collector-checkout-for-woocommerce' ),
			'unitPrice'   => 0.00,
			'quantity'    => 1,
			'vat'         => 0.0,
		);

		// Get WooCommerce cart totals including tax.
		$wc_total        = ( $order->get_total() + $order->get_total_tax() );
		$collector_total = 0;

		// Add all collector item amounts together.
		foreach ( $order_lines as $order_line ) {
			$collector_total += round( $order_line['unitPrice'] * $order_line['quantity'], 2 );
		}

		// Set the unitprice for the rounding fee to the difference between WooCommerce and Collector.
		$rounding_item['unitPrice'] = round( $wc_total - $collector_total, 2 );

		// Add the rounding item to the collector items only if the price is not zero.
		if ( ! empty( $rounding_item['unitPrice'] ) ) {
			$order_lines[] = $rounding_item;
		}
	}

	/**
	 * Gets the formatted order line.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order line item.
	 * @return array
	 */
	public static function get_order_line_items( $order_item ) {
		return array(
			'id'          => self::get_article_number( $order_item ),
			'description' => substr( $order_item->get_name(), 0, 50 ),
			'quantity'    => $order_item->get_quantity(),
			'vat'         => intval( round( ( $order_item->get_total_tax() / $order_item->get_total() ), 2 ) * 100 ),
			'unitPrice'   => round( ( ( $order_item->get_total() + $order_item->get_total_tax() ) / $order_item->get_quantity() ), 2 ),
		);
	}

	/**
	 * Gets the formatted order line fees.
	 *
	 * @param WC_Order_Item_Fee $order_fee The order item fee.
	 * @return array
	 */
	public static function get_order_line_fees( $order_fee ) {
		$order_id = $order_fee->get_order_id();
		$order    = wc_get_order( $order_id );

		return array(
			'id'          => 'fee|' . $order_fee->get_id(),
			'description' => substr( $order_fee->get_name(), 0, 50 ),
			'quantity'    => $order_fee->get_quantity(),
			'vat'         => ( ! empty( floatval( $order->get_total_tax() ) ) ) ? self::get_order_line_tax_rate( $order, current( $order->get_items( 'fee' ) ) ) : 0,
			'unitPrice'   => round( ( ( $order_fee->get_total() + $order_fee->get_total_tax() ) / $order_fee->get_quantity() ), 2 ),
		);
	}

	/**
	 * Get order item article number.
	 *
	 * Returns SKU or product ID.
	 *
	 * @param object $order_item Product object.
	 * @return string $article_number Order item article number.
	 */
	public static function get_article_number( $order_item ) {
		if ( 'fee' === $order_item->get_type() ) {
			$article_number = $order_item->get_id();
		} else {
			$product = $order_item->get_product();

			if ( $product ) {
				if ( $product->get_sku() ) {
					$article_number = $product->get_sku();
				} else {
					$article_number = $product->get_id();
				}
			} else {
				$article_number = $order_item->get_id();
			}
		}
		return substr( apply_filters( 'collector_checkout_sku', $article_number, $order_item ), 0, 32 );
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
					if ( $id_name === $item['id'] ) {
						$items[ $key ]['id'] = $item['id'] . '_' . $i;
						$i++;
					}
				}
			}
		}
		return $items;
	}

	/**
	 * Gets the order line tax rate.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param mixed    $order_item If not false the WooCommerce order item WC_Order_Item.
	 * @return int
	 */
	public static function get_order_line_tax_rate( $order, $order_item = false ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			foreach ( $order_item->get_taxes()['total'] as $key => $value ) {
				if ( '' !== $value ) {
					if ( $rate_id === $key ) {
						return round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] );
					}
				}
			}
		}
		// If we get here, there is no tax set for the order item. Return zero.
		return 0;
	}
}
