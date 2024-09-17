<?php
/**
 * Metabox class file.
 *
 * @package Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Meta box class.
 */
class Walley_Checkout_Meta_Box {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}

	/**
	 * Adds meta box to the side of a Walley Checkout order.
	 *
	 * @param string $post_type The WordPress post type.
	 * @return void
	 */
	public function add_meta_box( $post_type ) {
		if ( walley_is_order_page() ) {
			$order_id = walley_get_the_ID();
			$order    = wc_get_order( $order_id );
			if ( 'collector_checkout' === $order->get_payment_method() && walley_use_new_api() ) {
				add_meta_box( 'walley_checkout_meta_box', __( 'Walley', 'collector-checkout-for-woocommerce' ), array( $this, 'meta_box_content' ), $post_type, 'side', 'core' );
			}
		}
	}


	/**
	 * Adds content for the meta box.
	 *
	 * @param WC_Order|WP_Post $order The post or Woo order ID.
	 *
	 * @return void
	 */
	public function meta_box_content( $order = null ) {
		$order_id = walley_get_the_ID();
		$order    = wc_get_order( $order_id );

		$payment_method             = $order->get_meta( '_collector_payment_method', true );
		$walley_order_id            = $order->get_meta( '_collector_order_id', true );
		$title_payment_method       = __( 'Payment method', 'collector-checkout-for-woocommerce' );
		$title_walley_order_id      = __( 'Walley order id', 'collector-checkout-for-woocommerce' );
		$title_walley_order_status  = __( 'Walley order status', 'collector-checkout-for-woocommerce' );
		$title_walley_order_total   = __( 'Walley order total', 'collector-checkout-for-woocommerce' );
		$title_order_total_mismatch = __( 'Order total mismatch', 'collector-checkout-for-woocommerce' );

		if ( empty( $walley_order_status_from_transient = get_transient( "walley_order_status_{$order_id}" ) ) ) {
			$walley_order = CCO_WC()->api->get_walley_order( $walley_order_id );

			if ( is_wp_error( $walley_order ) ) {
				$walley_order_status   = 'unknown';
				$walley_order_total    = '';
				$walley_order_currency = '';
				$order_total_mismatch  = '';
			} else {
				$walley_order_status   = $walley_order['data']['status'] ?? 'unknown';
				$walley_order_total    = $walley_order['data']['totalAmount'] ?? '';
				$walley_order_currency = $walley_order['data']['currency'] ?? '';
				// Translators: Woo order total & Walley order total.
				$order_total_mismatch = floatval( Collector_Checkout_Requests_Helper_Order_Om::get_order_lines_total_amount( $order_id ) ) !== floatval( $walley_order_total ) ? sprintf( __( '<i>Order total differs between systems (WooCommerce: %1$s, Walley: %2$s)</i>', 'collector-checkout-for-woocommerce' ), Collector_Checkout_Requests_Helper_Order_Om::get_order_lines_total_amount( $order_id ), $walley_order_total ) : '';
				// Save received data to WP transient.
				walley_save_order_data_to_transient(
					array(
						'order_id'     => $order_id,
						'status'       => $walley_order_status,
						'total_amount' => $walley_order_total,
						'currency'     => $walley_order_currency,
					)
				);
			}
		} else {
			$walley_order_status   = $walley_order_status_from_transient['status'] ?? 'unknown';
			$walley_order_total    = $walley_order_status_from_transient['total_amount'] ?? '';
			$walley_order_currency = $walley_order_status_from_transient['currency'] ?? '';
			// Translators: Woo order total & Walley order total.
			$order_total_mismatch = floatval( Collector_Checkout_Requests_Helper_Order_Om::get_order_lines_total_amount( $order_id ) ) !== floatval( $walley_order_total ) ? sprintf( __( '<i>Order total differs between systems (WooCommerce: %1$s, Walley: %2$s)</i>', 'collector-checkout-for-woocommerce' ), Collector_Checkout_Requests_Helper_Order_Om::get_order_lines_total_amount( $order_id ), $walley_order_total ) : '';
		}

		$keys_for_meta_box = array(
			array(
				'title' => esc_html( $title_payment_method ),
				'value' => esc_html( wc_collector_get_payment_method_name( $payment_method ) ),
			),
			array(
				'title' => esc_html( $title_walley_order_id ),
				'value' => esc_html( $walley_order_id ),
			),
			array(
				'title' => esc_html( $title_walley_order_status ),
				'value' => esc_html( $walley_order_status ),
			),
			array(
				'title' => esc_html( $title_walley_order_total ),
				'value' => wp_kses_post( $walley_order_total . ' ' . $walley_order_currency ),
			),
		);

		// Order total mismatch info.
		if ( ! empty( $order_total_mismatch ) && in_array( $walley_order_status, array( 'NotActivated', 'PartActivated' ), true ) && 0 === count( $order->get_refunds() ) ) {
			$keys_for_meta_box[] = array(
				'title' => esc_html( $title_order_total_mismatch ),
				'value' => wp_kses_post( $order_total_mismatch ),
			);
		}
		$keys_for_meta_box = apply_filters( 'walley_checkout_meta_box_keys', $keys_for_meta_box );

		// DO NOT REMOVE! Used in the template file.
		$manage_orders = wc_string_to_bool( get_option( 'woocommerce_collector_checkout_settings', array() )['manage_collector_orders'] ?? 'no' );
		include COLLECTOR_BANK_PLUGIN_DIR . '/templates/walley-checkout-meta-box.php';
	}
} new Walley_Checkout_Meta_Box();
