<?php //phpcs:ignore
/**
 * Creates local order.
 *
 * @package  Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Create_Local_Order_Fallback
 */
class Collector_Create_Local_Order_Fallback {

	/**
	 * Creates an order.
	 *
	 * @return WC_Order|WP_Error
	 */
	public function create_order() {
		$order = wc_create_order();

		return $order;
	}

	/**
	 * Adds items to the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 * @throws Exception If unable to create order.
	 */
	public function add_items_to_local_order( $order ) {
			// Remove items as to stop the item lines from being duplicated.
			$order->remove_order_items();
		foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) { // Store the line items to the new/resumed order.
			$item_id = $order->add_product(
				$values['data'],
				$values['quantity'],
				array(
					'variation' => $values['variation'],
					'totals'    => array(
						'subtotal'     => $values['line_subtotal'],
						'subtotal_tax' => $values['line_subtotal_tax'],
						'total'        => $values['line_total'],
						'tax'          => $values['line_tax'],
						'tax_data'     => $values['line_tax_data'],
					),
				)
			);
			if ( ! $item_id ) {
				CCO_WC()->logger::log( 'Error: Unable to add cart items in Create Local Order Fallback.' );
				// translators: The error message.
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 525 ) );
			}
			do_action( 'woocommerce_add_order_item_meta', $item_id, $values, $cart_item_key ); // Allow plugins to add order item meta.
		}
	}


	/**
	 * Adds order fees
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 * @throws Exception If unable to create order.
	 */
	public function add_order_fees( $order ) {
			$order_id = $order->get_id();
		foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {
			$item_id = $order->add_fee( $fee );
			if ( ! $item_id ) {
				CCO_WC()->logger::log( 'Error: Unable to add order fees in Create Local Order Fallback.' );
				throw new Exception( __( 'Error: Unable to create order. Please try again.', 'woocommerce' ) );
			}
			// Allow plugins to add order item meta to fees.
			do_action( 'woocommerce_add_order_fee_meta', $order_id, $item_id, $fee, $fee_key );
		}
	}

	/**
	 * Adds shipping.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 * @throws Exception If unable to add shipping item.
	 */
	public function add_order_shipping( $order ) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
			$order_id              = $order->get_id();
			$this_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			WC()->cart->calculate_shipping();
			// Store shipping for all packages.
		foreach ( WC()->shipping->get_packages() as $package_key => $package ) {
			if ( isset( $package['rates'][ $this_shipping_methods[ $package_key ] ] ) ) {
				$item_id = $order->add_shipping( $package['rates'][ $this_shipping_methods[ $package_key ] ] );
				if ( ! $item_id ) {
					CCO_WC()->logger::log( 'Error: Unable to add shipping item in Create Local Order Fallback.' );
					throw new Exception( __( 'Error: Unable to add shipping item. Please try again.', 'woocommerce' ) );
				}
				// Allows plugins to add order item meta to shipping.
				do_action( 'woocommerce_add_shipping_order_item', $order_id, $item_id, $package_key );
			}
		}
	}

	/**
	 * Adds a tax row to the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 * @throws Exception If unable to add order tax rows.
	 */
	public function add_order_tax_rows( $order ) {
		// Store tax rows.
		foreach ( array_keys( WC()->cart->taxes + WC()->cart->shipping_taxes ) as $tax_rate_id ) {
			if ( $tax_rate_id && ! $order->add_tax( $tax_rate_id, WC()->cart->get_tax_amount( $tax_rate_id ), WC()->cart->get_shipping_tax_amount( $tax_rate_id ) ) && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				CCO_WC()->logger::log( 'Error: Unable to add order tax rows in Create Local Order Fallback.' );
				// translators: The Error code.
				throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 405 ) );
			}
		}
	}

	/**
	 * Adds coupon code to the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 * @throws Exception If unable to add coupons.
	 */
	public function add_order_coupons( $order ) {
		foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
			if ( ! $order->add_coupon( $code, WC()->cart->get_coupon_discount_amount( $code ) ) ) {
				CCO_WC()->logger::log( 'Error: Unable to add coupons in Create Local Order Fallback.' );
				throw new Exception( __( 'Error: Unable to add coupons. Please try again.', 'woocommerce' ) );
			}
		}
	}

	/**
	 * Set the payment method.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public function add_order_payment_method( $order ) {
		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['collector_checkout'];
		$order->set_payment_method( $payment_method );
	}

	/**
	 * Add customer data to local order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param string   $customer_type The customer type.
	 * @param string   $private_id The private id.
	 *
	 * @return void
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	public function add_customer_data_to_local_order( $order, $customer_type, $private_id ) {
		$order_id = $order->get_id();

		$response        = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$collector_order = $response->request();

		$formated_customer_data = wc_collector_verify_customer_data( $collector_order );

		update_post_meta( $order_id, '_billing_first_name', $formated_customer_data['billingFirstName'] );
		update_post_meta( $order_id, '_billing_last_name', $formated_customer_data['billingLastName'] );
		update_post_meta( $order_id, '_billing_address_1', $formated_customer_data['billingAddress'] );
		update_post_meta( $order_id, '_billing_address_2', $formated_customer_data['billingAddress2'] );
		update_post_meta( $order_id, '_billing_city', $formated_customer_data['billingCity'] );
		update_post_meta( $order_id, '_billing_postcode', $formated_customer_data['billingPostalCode'] );
		update_post_meta( $order_id, '_billing_country', $formated_customer_data['countryCode'] );
		update_post_meta( $order_id, '_billing_phone', $formated_customer_data['phone'] );
		update_post_meta( $order_id, '_billing_email', $formated_customer_data['email'] );
		update_post_meta( $order_id, '_shipping_first_name', $formated_customer_data['shippingFirstName'] );
		update_post_meta( $order_id, '_shipping_last_name', $formated_customer_data['shippingLastName'] );
		update_post_meta( $order_id, '_shipping_address_1', $formated_customer_data['shippingAddress'] );
		update_post_meta( $order_id, '_shipping_address_2', $formated_customer_data['shippingAddress2'] );
		update_post_meta( $order_id, '_shipping_city', $formated_customer_data['shippingCity'] );
		update_post_meta( $order_id, '_shipping_postcode', $formated_customer_data['shippingPostalCode'] );
		update_post_meta( $order_id, '_shipping_country', $formated_customer_data['countryCode'] );

		// Post meta.
		update_post_meta( $order_id, '_created_via_collector_fallback', 'yes' );

		update_post_meta( $order_id, '_collector_payment_method', $collector_order['data']['purchase']['paymentName'] );
		update_post_meta( $order_id, '_collector_payment_id', $collector_order['data']['purchase']['purchaseIdentifier'] );
		update_post_meta( $order_id, '_collector_customer_type', $customer_type );
		update_post_meta( $order_id, '_collector_private_id', $private_id );
		update_post_meta( $order_id, '_transaction_id', $collector_order['data']['purchase']['purchaseIdentifier'] );

		$order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );

		$public_token = WC()->session->get( 'collector_public_token' );
		update_post_meta( $order_id, '_collector_public_token', $public_token );
	}

	/**
	 * Calculate order_ otals.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public function calculate_order_totals( $order ) {
		$order->calculate_totals();
		$order->save();
	}


	/**
	 * Update the Collector Order with the Order ID
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param string   $customer_type The customer type.
	 * @param string   $private_id The private id.
	 *
	 * @return void
	 */
	public function update_order_reference_in_collector( $order, $customer_type, $private_id ) {
		$update_reference = new Collector_Checkout_Requests_Update_Reference( $order->get_order_number(), $private_id, $customer_type );
		$update_reference->request();
		CCO_WC()->logger::log( 'Update Collector order reference in Create Local Order Fallback - ' . $order->get_order_number() );
	}
}
