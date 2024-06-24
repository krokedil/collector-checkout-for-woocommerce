<?php
/**
 * Adds extra information to the email.
 *
 * @package  Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sends extra information in order emails if order is payed via Collector
 *
 * @class    Collector_Checkout_Order_Emails
 * @version  1.0
 * @package  Collector_Checkout/Classes
 * @category Class
 * @author   Krokedil
 */
class Collector_Checkout_Order_Emails {

	/**
	 * Collector_Checkout_Order_Emails constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_extra_information' ), 10, 3 );
	}

	/**
	 * Adds extra information to the email.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param bool     $sent_to_admin Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 *
	 * @return void
	 */
	public function email_extra_information( $order, $sent_to_admin, $plain_text = false ) {
		$gateway_used = $order->get_payment_method();
		if ( 'collector_checkout' === $gateway_used ) {
			$payment_details = '';
			$payment_id      = $order->get_meta( '_collector_payment_id', true );
			$payment_type    = wc_collector_get_payment_method_name( $order->get_meta( '_collector_payment_method', true ) );

			echo '<h2>' . esc_html__( 'Walley details', 'collector-checkout-for-woocommerce' ) . '</h2>';
			if ( $payment_id ) {
				$payment_details = __( 'Walley Payment ID: ', 'collector-checkout-for-woocommerce' ) . $payment_id . '<br/>';
			}
			if ( $payment_type ) {
				$payment_details .= __( 'Payment type: ', 'collector-checkout-for-woocommerce' ) . $payment_type;
			}

			echo wp_kses_post( wptexturize( $payment_details ) );
		}
	}
}

$collector_checkout_order_emails = new Collector_Checkout_Order_Emails();
