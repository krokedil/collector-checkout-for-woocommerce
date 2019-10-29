<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Creates Collector refund data.
 *
 * @class    Collector_Checkout_Create_Refund_Data
 * @package  Collector/Classes/Requests/Helpers
 * @category Class
 * @author   Krokedil <info@krokedil.se>
 */
class Collector_Checkout_Create_Refund_Data {
	/**
	 * Creates refund data
	 *
	 * @param int    $order_id
	 * @param int    $amount
	 * @param string $reason
	 * @return array
	 */
	public static function create_refund_data( $order_id, $refund_order_id, $amount, $reason ) {
		$data = array();
		if ( '' === $reason ) {
			$reason = '';
		} else {
			$reason = ' (' . $reason . ')';
		}
		if ( null !== $refund_order_id ) {
			// Get refund order data.
			$refund_order   = wc_get_order( $refund_order_id );
			$refunded_items = $refund_order->get_items();

			// Set needed variables.
			$modified_item_prices = 0;
			$full_item_refund     = array();
			if ( $refunded_items ) {
				foreach ( $refunded_items as $item ) {
					$original_order = wc_get_order( $order_id );
					foreach ( $original_order->get_items() as $original_order_item ) {
						if ( $item->get_id() == $original_order_item->get_id() ) {
							// Found product match, continue.
							break;
						}
					}
					if ( abs( $item->get_total() ) / abs( $item->get_quantity() ) === $original_order_item->get_total() / $original_order_item->get_quantity() ) {
						// The entire item price is refunded.
						array_push( $full_item_refund, self::get_full_refund_item_data( $item ) );
					} else {
						// The item is partial refunded.
						$modified_item_prices += abs( $item->get_total() + $item->get_total_tax() );
					}
				}
				if ( $modified_item_prices > 0 ) {
					// Maybe add partial item refund on remaining products.
					$data['partial_refund'] = self::get_partial_refund_data( $modified_item_prices, $refund_order_id, $reason );
				}
				if ( ! empty( $full_item_refund ) ) {
					// Maybe add full refunds.
					$data['full_refunds'] = $full_item_refund;
				}
			} else {
				// Partial item Refund here.
				if ( $modified_item_prices > 0 ) {
					$data['partial_refund'] = self::get_partial_refund_data( $amount, $refund_order_id, $reason );
				}
			}
		}

		// update_post_meta( $refund_order_id, '_krokedil_refunded', 'true' );
		return $data;
	}
	/**
	 * Gets refunded order
	 *
	 * @param int $order_id
	 * @return string
	 */
	public static function get_refunded_order( $order_id ) {
		$query_args      = array(
			'fields'         => 'id=>parent',
			'post_type'      => 'shop_order_refund',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		);
		$refunds         = get_posts( $query_args );
		$refund_order_id = array_search( $order_id, $refunds );
		if ( is_array( $refund_order_id ) ) {
			foreach ( $refund_order_id as $key => $value ) {
				if ( ! get_post_meta( $value, '_krokedil_refunded' ) ) {
					$refund_order_id = $value;
					break;
				}
			}
		}
		return $refund_order_id;
	}
	/**
	 * Calculates tax.
	 *
	 * @param string $refund_order_id
	 * @return void
	 */
	private static function calculate_tax( $refund_order_id ) {
		$refund_order     = wc_get_order( $refund_order_id );
		$refund_tax_total = $refund_order->get_total_tax() * -1;
		$refund_total     = ( $refund_order->get_total() * -1 ) - $refund_tax_total;
		return intval( ( $refund_tax_total / $refund_total ) * 100 );
	}

	/**
	 * Gets a partial refund item object
	 *
	 * @param int    $modified_item_prices Total remaining amount to be refunded.
	 * @param int    $refund_order_id WooCommerce refund order id.
	 * @param string $reason The reason given for the refund.
	 * @return array
	 */
	private static function get_partial_refund_data( $modified_item_prices, $refund_order_id, $reason ) {
		return array(
			'ArticleId'   => 'ref1',
			'Description' => 'Refund #' . $refund_order_id . $reason,
			'Quantity'    => 1,
			'UnitPrice'   => -$modified_item_prices,
			'VAT'         => 0,
		);
	}

	/**
	 * Gets a full refund item object.
	 *
	 * @param WC_Order_Item $item WooCommerce Order Item.
	 * @return array
	 */
	private static function get_full_refund_item_data( $item ) {
		$product = $item->get_product();
		// The entire item price is refunded.
		$title              = $item->get_name();
		$sku                = empty( $product->get_sku() ) ? $product->get_id() : $product->get_sku();
		$tax_rates          = WC_Tax::get_rates( $item->get_tax_class() );
		$tax_rate           = reset( $tax_rates );
		$formatted_tax_rate = round( $tax_rate['rate'] );
		$total              = $item->get_total();
		$quantity           = ( 0 === $item->get_quantity() ) ? 1 : $item->get_quantity();

		return array(
			'ArticleId'   => $sku,
			'Description' => $title,
			'Quantity'    => abs( $quantity ),
			'UnitPrice'   => $total,
			'VAT'         => $formatted_tax_rate,
		);
	}
}
