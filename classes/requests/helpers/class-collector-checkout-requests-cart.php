<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Checkout_Requests_Cart {
	public static function cart() {
		// Get cart contents
		$wc_cart = WC()->cart->cart_contents;
		// Set the return array
		$items = array();
		// Loop through cart items and make an item line for each.
		foreach ( $wc_cart as $item ) {
			// Don't send items with a price of 0
			if( 0 == $item['line_total'] ) {
				continue;
			}
			if ( $item['variation_id'] ) {
				$product = wc_get_product( $item['variation_id'] );
			} else {
				$product = wc_get_product( $item['product_id'] );
			}
			$item_name = wc_get_product( $item['product_id'] );
			$item_name = $item_name->get_title();
			$item_line = self::create_item( self::get_sku( $product ), $item_name, $item['line_total'], $item['quantity'], $item['line_tax'] );
			array_push( $items, $item_line );
		}
		$return['items'] = $items;
		return $return;
	}

	public static function create_item( $sku, $product_name, $line_total, $quantity, $line_tax ) {
		return array(
			'id'            => $sku,
			'description'   => $product_name,
			'unitPrice'     => round( ( $line_total + $line_tax ) / $quantity, 2 ), // Total price per unit including VAT
			'quantity'      => $quantity,
			'vat'           => round( $line_tax / $line_total, 2 ) * 100,
		);
	}

	public static function get_sku( $product ) {
		if ( $product->get_sku() ) {
			$part_number = $product->get_sku();
		} else {
			$part_number = $product->get_id();
		}
		return substr( $part_number, 0, 32 );
	}
}