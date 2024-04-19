<?php
/**
 * Creates Collector refund data.
 *
 * @class    Collector_Checkout_Create_Refund_Data
 * @package  Collector/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Class Collector_Checkout_Create_Refund_Data
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
	 * @param int    $order_id The WooCommerce order id.
	 * @param int    $refund_order_id The Refund order id.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
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
			$refund_order      = wc_get_order( $refund_order_id );
			$refunded_items    = $refund_order->get_items();
			$refunded_shipping = $refund_order->get_items( 'shipping' );
			$refunded_fees     = $refund_order->get_items( 'fee' );

			// Set needed variables for refunds.
			$modified_item_prices = 0;
			$full_item_refund     = array();

			// Item refund.
			if ( $refunded_items ) {
				foreach ( $refunded_items as $item ) {
					$original_order = wc_get_order( $order_id );
					foreach ( $original_order->get_items() as $original_order_item ) {
						if ( $item->get_product_id() === $original_order_item->get_product_id() ) {
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
			}

			// Shipping item refund.
			if ( $refunded_shipping ) {
				foreach ( $refunded_shipping as $shipping ) {
					$original_order = wc_get_order( $order_id );
					foreach ( $original_order->get_items( 'shipping' ) as $original_order_shipping ) {
						if ( $shipping->get_name() === $original_order_shipping->get_name() ) {
							// Found product match, continue.
							break;
						}
					}
					if ( abs( $shipping->get_total() ) / abs( $shipping->get_quantity() ) === $original_order_shipping->get_total() / $original_order_shipping->get_quantity() ) {
						// The entire shipping price is refunded.
						array_push( $full_item_refund, self::get_full_refund_shipping_data( $shipping, $original_order ) );
					} else {
						// The shipping is partial refunded.
						$modified_item_prices += abs( $shipping->get_total() + $shipping->get_total_tax() );
					}
				}
				if ( $modified_item_prices > 0 ) {
					// Maybe add partial shipping refund on remaining products.
					$data['partial_refund'] = self::get_partial_refund_data( $modified_item_prices, $refund_order_id, $reason );
				}
				if ( ! empty( $full_item_refund ) ) {
					// Maybe add full refunds.
					$data['full_refunds'] = $full_item_refund;
				}
			}

			// Fee item refund.
			if ( $refunded_fees ) {
				foreach ( $refunded_fees as $fee ) {
					$original_order = wc_get_order( $order_id );
					foreach ( $original_order->get_items( 'fee' ) as $original_order_fee ) {
						if ( $fee->get_name() === $original_order_fee->get_name() ) {
							// Found product match, continue.
							break;
						}
					}
					if ( abs( $fee->get_total() ) / abs( $fee->get_quantity() ) === $original_order_fee->get_total() / $original_order_fee->get_quantity() ) {
						// The entire fee price is refunded.
						array_push( $full_item_refund, self::get_full_refund_fee_data( $fee ) );
					} else {
						// The fee is partial refunded.
						$modified_item_prices += abs( $fee->get_total() + $fee->get_total_tax() );
					}
				}
				if ( $modified_item_prices > 0 ) {
					// Maybe add partial fee refund on remaining products.
					$data['partial_refund'] = self::get_partial_refund_data( $modified_item_prices, $refund_order_id, $reason );
				}
				if ( ! empty( $full_item_refund ) ) {
					// Maybe add full refunds.
					$data['full_refunds'] = $full_item_refund;
				}
			}
		}

		return $data;
	}
	/**
	 * Gets refunded order
	 *
	 * @param int $order_id The Woo order ID.
	 * @return string
	 */
	public static function get_refunded_order( $order_id ) {
		$order = wc_get_order( $order_id );
		return $order->get_refunds()[0];

	}
	/**
	 * Calculates tax.
	 *
	 * @param string $refund_order_id The refund order id.
	 * @return int
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
		$title              = wp_strip_all_tags( $item->get_name() );
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

	/**
	 * Gets a full refund shipping object.
	 *
	 * @param WC_Order_Item_Shipping $shipping WooCommerce Order shipping.
	 * @param WC_Order               $original_order WooCommerce original order.
	 * @return array
	 */
	private static function get_full_refund_shipping_data( $shipping, $original_order ) {
		// The entire shipping price is refunded.
		$shipping_reference = 'Shipping';

		$collector_shipping_reference = $original_order->get_meta( '_collector_shipping_reference', true );
		if ( isset( $collector_shipping_reference ) && ! empty( $collector_shipping_reference ) ) {
			$shipping_reference = $collector_shipping_reference;
		} elseif ( null !== $shipping->get_instance_id() ) {
				$shipping_reference = 'shipping|' . $shipping->get_method_id() . ':' . $shipping->get_instance_id();
		} else {
			$shipping_reference = 'shipping|' . $shipping->get_method_id();
		}

		$free_shipping = false;
		if ( empty( floatval( $shipping->get_total() ) ) ) {
			$free_shipping = true;
		}

		$title    = $shipping->get_name();
		$tax_rate = ( $free_shipping ) ? 0 : intval( round( $shipping->get_total_tax() / $shipping->get_total() * 100 ) );
		$total    = ( $free_shipping ) ? 0 : $shipping->get_total();
		$quantity = ( 0 === $shipping->get_quantity() ) ? 1 : $shipping->get_quantity();

		return array(
			'ArticleId'   => $shipping_reference,
			'Description' => $title,
			'Quantity'    => abs( $quantity ),
			'UnitPrice'   => $total,
			'VAT'         => $tax_rate,
		);
	}

	/**
	 * Gets a full refund fee object.
	 *
	 * @param WC_Order_Item_Fee $fee WooCommerce Order fee.
	 * @return array
	 */
	private static function get_full_refund_fee_data( $fee ) {
		// The entire fee price is refunded.
		$sku                = 'Fee';
		$invoice_fee_name   = '';
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$invoice_fee_id     = isset( $collector_settings['collector_invoice_fee'] ) ? $collector_settings['collector_invoice_fee'] : '';

		if ( $invoice_fee_id ) {
			$_product         = wc_get_product( $invoice_fee_id );
			$invoice_fee_name = $_product->get_name();
		}

		// Check if the refunded fee is the invoice fee.
		if ( $invoice_fee_name === $fee->get_name() ) {
			$sku = 'invoicefee|' . Collector_Checkout_Requests_Cart::get_sku( $_product, $_product->get_id() );
		} else {
			// Format the fee name so it match the same fee in Collector.
			$fee_name = str_replace( ' ', '-', strtolower( $fee->get_name() ) );
			$sku      = 'fee|' . $fee_name;
		}

		$title              = $fee->get_name();
		$tax_rates          = WC_Tax::get_rates( $fee->get_tax_class() );
		$tax_rate           = reset( $tax_rates );
		$formatted_tax_rate = round( $tax_rate['rate'] );
		$total              = $fee->get_total();
		$quantity           = ( 0 === $fee->get_quantity() ) ? 1 : $fee->get_quantity();

		return array(
			'ArticleId'   => $sku,
			'Description' => $title,
			'Quantity'    => abs( $quantity ),
			'UnitPrice'   => $total,
			'VAT'         => $formatted_tax_rate,
		);
	}

	/**
	 * Returns the id of the refunded order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return string
	 */
	public static function get_refunded_order_id( $order_id ) {
		$order = wc_get_order( $order_id );

		/* Always retrieve the most recent (current) refund (index 0). */
		return $order->get_refunds()[0]->get_id();
	}
}
