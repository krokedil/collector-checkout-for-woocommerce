<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
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
	public static function create_refund_data( $order_id, $amount, $reason ) {
		$data      = array();
		$refund_id = self::get_refunded_order( $order_id );
		if ( '' === $reason ) {
			$reason = '';
		} else {
			$reason = ' (' . $reason . ')';
		}
		if ( null !== $refund_id ) {
			$refund_order   = wc_get_order( $refund_id );
			$refunded_items = $refund_order->get_items();
			if ( $refunded_items ) {
				$data = array();
				foreach ( $refunded_items as $item ) {
					$product            = $refund_order->get_product_from_item( $item );
					$title              = $item->get_name();
					$sku                = ( '' === $product->get_sku() ) ? $title : $product->get_sku();
					$tax_rates          = WC_Tax::get_rates( $item->get_tax_class() );
					$tax_rate           = reset( $tax_rates );
					$formatted_tax_rate = round( $tax_rate['rate'] );
					$total              = $item->get_total();
					$quantity           = ( 0 === $item->get_quantity() ) ? 1 : $item->get_quantity();
					$data[]             = array(
						'ArticleId'   => $sku,
						'Description' => $title . '(Refund #' . $refund_id . ')',
						'Quantity'    => $quantity,
						'UnitPrice'   => $total,
						'VAT'         => $formatted_tax_rate,
					);

				}
			} else {
				$data = array(
					0 => array(
						'ArticleId'   => 'ref1',
						'Description' => '1 Refund #' . $refund_id . $reason,
						'Quantity'    => 1,
						'UnitPrice'   => -$amount,
						'VAT'         => 0,
					),
				);/*
				$data[] = array(
					'InvoiceRow' => array(
						'ArticleId'         => 'ref2',
						'Description'       => '2 Refund #' . $refund_id . $reason,
						'Quantity'          => 1,
						'UnitPrice'         => -$amount,
						'VAT'               => 0,
					),
				);
				*/
			}
			/*
			$data = array(
				'ArticleId'         =>  'Woo-beanie-logo',
				'Description'       => 'Refund #' . $refund_id . $reason,
				'Quantity'          => 1,
				'UnitPrice'         =>  18,
				'VAT'               =>  self::calculate_tax( $refund_id ),
			);

			$data = array(
				'ArticleId'         =>  $refund_id,
				'Description'       => 'Refund #' . $refund_id . $reason,
				'Quantity'          => 1,
				'UnitPrice'         =>  -$amount,
				'VAT'               =>  self::calculate_tax( $refund_id ),
			);
			*/
		}
		update_post_meta( $refund_id, '_krokedil_refunded', 'true' );
		return $data;
	}
	/**
	 * Gets refunded order
	 *
	 * @param int $order_id
	 * @return string
	 */
	public static function get_refunded_order( $order_id ) {
		$query_args = array(
			'fields'         => 'id=>parent',
			'post_type'      => 'shop_order_refund',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		);
		$refunds    = get_posts( $query_args );
		$refund_id  = array_search( $order_id, $refunds );
		if ( is_array( $refund_id ) ) {
			foreach ( $refund_id as $key => $value ) {
				if ( ! get_post_meta( $value, '_krokedil_refunded' ) ) {
					$refund_id = $value;
					break;
				}
			}
		}
		return $refund_id;
	}
	/**
	 * Calculates tax.
	 *
	 * @param string $refund_id
	 * @return void
	 */
	private static function calculate_tax( $refund_id ) {
		$refund_order = wc_get_order( $refund_id );
		// error_log('$refund_order ' . var_export($refund_order, true) );
		$refund_tax_total = $refund_order->get_total_tax() * -1;
		$refund_total     = ( $refund_order->get_total() * -1 ) - $refund_tax_total;
		return intval( ( $refund_tax_total / $refund_total ) * 100 );
	}
}
