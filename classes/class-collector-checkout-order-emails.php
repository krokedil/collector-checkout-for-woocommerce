<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
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

	public function email_extra_information( $order, $sent_to_admin, $plain_text = false ) {
		$order_id     = $order->get_id();
		$gateway_used = get_post_meta( $order_id, '_payment_method', true );
		if ( 'collector_checkout' == $gateway_used ) {
			$payment_details 	= '';
			$payment_id    		= get_post_meta( $order_id, '_collector_payment_id', true );
			$payment_type 		= wc_collector_get_payment_method_name( get_post_meta( $order_id, '_collector_payment_method', true ) );
			$order_date 		= wc_format_datetime( $order->get_date_created() );
			
			echo '<h2>' . __( 'Collector details', 'collector-checkout-for-woocommerce' ) . '</h2>';
			if ( $payment_id ) {
				$payment_details = __( 'Collector Payment ID: ', 'collector-checkout-for-woocommerce' ) . $payment_id . '<br/>';
			}
			if ( $payment_type ) {
				$payment_details .= __( 'Payment type: ', 'collector-checkout-for-woocommerce' ) . $payment_type;
			}
			
			echo wpautop( wptexturize( $payment_details ) );
			//echo $payment_details;
			
		}
	}
}

$collector_checkout_order_emails = new Collector_Checkout_Order_Emails;
