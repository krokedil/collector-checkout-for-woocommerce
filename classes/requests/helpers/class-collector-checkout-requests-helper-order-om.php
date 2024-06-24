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
 * Used for order management requests.
 */
class Collector_Checkout_Requests_Helper_Order_Om {

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
			$formatted_item = self::get_order_line_items( $item, $order );
			if ( is_array( $formatted_item ) ) {
				array_push( $order_lines, $formatted_item );
			}
		}
		foreach ( $order->get_fees() as $fee ) {
			$formatted_item = self::get_order_line_fees( $fee, $order );
			if ( is_array( $formatted_item ) ) {
				array_push( $order_lines, $formatted_item );
			}
		}

		foreach ( $order->get_items( 'shipping' ) as $order_item ) {
			$formatted_item = self::get_order_line_shipping( $order_item, $order );
			if ( is_array( $formatted_item ) ) {
				array_push( $order_lines, $formatted_item );
			}
		}

		// Maybe add a rounding fee to Walley if needed.
		if ( 'yes' === walley_add_rounding_order_line() ) {
			self::rounding_fee( $order_lines, $order );
		}
		return $order_lines;
	}

	/**
	 * Formats the order lines for a refund request.
	 *
	 * @param int $order_id The WooCommerce Order ID.
	 * @return array
	 */
	public static function get_refund_items( $order_id ) {
		$order_lines  = self::get_order_lines( $order_id );
		$return_lines = array();

		foreach ( $order_lines as $order_line ) {
			$unit_price     = 'rounding-fee' === $order_line['id'] ? $order_line['UnitPrice'] : abs( $order_line['UnitPrice'] );
			$return_lines[] = array(
				'id'          => $order_line['id'],
				'Description' => substr( $order_line['Description'], 0, 50 ),
				'Quantity'    => abs( $order_line['Quantity'] ),
				'UnitPrice'   => self::format_number( $unit_price ),
			);
		}

		return $return_lines;
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
			'Description' => __( 'Rounding fee', 'collector-checkout-for-woocommerce' ),
			'Quantity'    => 1,
		);

		// Get WooCommerce order total.
		$wc_total = abs( $order->get_total() );

		$collector_total = 0;

		// Add all collector item amounts together.
		foreach ( $order_lines as $order_line ) {
			$collector_total += round( $order_line['UnitPrice'] * $order_line['Quantity'], 2 );
		}

		// Make $collector_total a positive number. Can be negative due to refunds.
		$collector_total = abs( $collector_total );

		// Set the unitprice for the rounding fee to the difference between WooCommerce and Collector.
		$rounding_item['UnitPrice'] = self::format_number( $wc_total - $collector_total );

		// Add the rounding item to the collector items only if the price is not zero.
		if ( ! empty( floatval( $rounding_item['UnitPrice'] ) ) ) {
			$order_lines[] = $rounding_item;
		}
	}

	/**
	 * Gets the formatted order line.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order line item.
	 * @param object                $order The WooCommerce order.
	 * @return array
	 */
	public static function get_order_line_items( $order_item, $order ) {

		$unit_price = self::format_number( ( $order_item->get_total() + $order_item->get_total_tax() ) / $order_item->get_quantity() );

		// If price is 0 - return.
		if ( empty( floatval( $unit_price ) ) ) {
			return false;
		}

		return array(
			'id'          => self::get_article_number( $order_item ),
			'Description' => substr( $order_item->get_name(), 0, 50 ),
			'Quantity'    => $order_item->get_quantity(),
			'UnitPrice'   => $unit_price,
			'vat'         => self::get_tax_rate( $order_item, $order ),
		);
	}

	/**
	 * Gets the formatted order line fees.
	 *
	 * @param WC_Order_Item_Fee $order_fee The order item fee.
	 * @param object            $order The WooCommerce order.
	 * @return array
	 */
	public static function get_order_line_fees( $order_fee, $order ) {
		$order_id           = $order_fee->get_order_id();
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$invoice_fee_id     = isset( $collector_settings['collector_invoice_fee'] ) ? $collector_settings['collector_invoice_fee'] : '';

		if ( $invoice_fee_id ) {
			$_product         = wc_get_product( $invoice_fee_id );
			$invoice_fee_name = $_product->get_name();
		}

		// Check if the refunded fee is the invoice fee.
		if ( $invoice_fee_name === $order_fee->get_name() ) {
			$sku = 'invoicefee|' . Collector_Checkout_Requests_Cart::get_sku( $_product, $_product->get_id() );
		} else {
			// Format the fee name so it match the same fee in Collector.
			$fee_name = str_replace( ' ', '-', strtolower( $order_fee->get_name() ) );
			$sku      = 'fee|' . $fee_name;
		}

		$unit_price = self::format_number( ( $order_fee->get_total() + $order_fee->get_total_tax() ) / $order_fee->get_quantity() );

		// If price is 0 - return.
		if ( empty( floatval( $unit_price ) ) ) {
			return false;
		}

		return array(
			'id'          => $sku,
			'Description' => substr( $order_fee->get_name(), 0, 50 ),
			'Quantity'    => $order_fee->get_quantity(),
			'UnitPrice'   => $unit_price,
			'vat'         => self::get_tax_rate( $order_fee, $order ),
		);
	}

	/**
	 * Process order item shipping and return it formatted for the request.
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce order line item.
	 * @param object                $order The WooCommerce order.
	 * @return array
	 */
	public static function get_order_line_shipping( $order_item, $order ) {

		// Try to get shipping reference from delivery module data.
		$shipping_reference_from_delivery_module_data = walley_get_shipping_reference_from_delivery_module_data( $order->get_id() );

		// Try to get shipping reference from regular purchase (shipping in woo).
		$collector_shipping_reference = $order->get_meta( '_collector_shipping_reference', true );

		if ( isset( $collector_shipping_reference ) && ! empty( $collector_shipping_reference ) ) {
			$shipping_reference = $collector_shipping_reference;
		} elseif ( ! empty( $shipping_reference_from_delivery_module_data ) ) {
			$shipping_reference = $shipping_reference_from_delivery_module_data;
		} else {
			if ( null !== $order_item->get_instance_id() ) {
				$shipping_reference = 'shipping|' . $order_item->get_method_id() . ':' . $order_item->get_instance_id();
			} else {
				$shipping_reference = 'shipping|' . $order_item->get_method_id();
			}
		}

		// Shipping should be added even if it is 0 since free shipping is added to the original purchase.
		$unit_price = self::format_number( ( $order_item->get_total() + $order_item->get_total_tax() ) / $order_item->get_quantity() );

		return array(
			'id'          => $shipping_reference,
			'Description' => self::get_name( $order_item ),
			'Quantity'    => 1,
			'UnitPrice'   => $unit_price,
			'vat'         => self::get_tax_rate( $order_item, $order ),
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
	 * Get the name of the order item.
	 *
	 * @param WC_Order_Item $order_item The WooCommerce order item.
	 * @return string
	 */
	public static function get_name( $order_item ) {
		return substr( $order_item->get_name(), 0, 50 );
	}

	/**
	 * Format the value as needed for Walley order management.
	 *
	 * @param int|float $value The unformated value.
	 * @return string
	 */
	public static function format_number( $value ) {
		return wc_format_decimal( $value, 2 );

	}

	/**
	 * Get the tax rate.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Shipping|WC_Order_Item_Fee $order_item The WooCommerce order item.
	 * @param WC_Order                                                       $order The WooCommerce order.
	 * @return int
	 */
	public static function get_tax_rate( $order_item, $order ) {
		// If we don't have any tax, return 0.
		if ( '0' === $order_item->get_total_tax() ) {
			return 0;
		}

		$tax_items = $order->get_items( 'tax' );
		/**
		 * Process the tax items.
		 *
		 * @var WC_Order_Item_Tax $tax_item The WooCommerce order tax item.
		 */
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			if ( key( $order_item->get_taxes()['total'] ) === $rate_id ) {
				return (string) round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] );
			}
		}
		return 0;
	}

	/**
	 * Gets the order lines for the order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	public static function get_order_lines_total_amount( $order_id ) {
		$total_amount = 0;
		$order_lines  = self::get_order_lines( $order_id );

		foreach ( $order_lines as $order_line ) {
			$total_amount += floatval( $order_line['UnitPrice'] ) * $order_line['Quantity'];

		}
		return self::format_number( $total_amount );
	}
}
