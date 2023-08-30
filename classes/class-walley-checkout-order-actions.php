<?php
/**
 * Class for order actions in order page.
 *
 * @package Collector_Checkout/Classes/
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Walley_Checkout_Order_Actions
 */
class  Walley_Checkout_Order_Actions {

	/**
	 * Class constructor
	 */
	public function __construct() {
		$collector_settings                    = get_option( 'woocommerce_collector_checkout_settings' );
		$this->manage_orders                   = $collector_settings['manage_collector_orders'];
		$this->display_invoice_no              = $collector_settings['display_invoice_no'];
		$this->activate_individual_order_lines = $collector_settings['activate_individual_order_lines'] ?? 'no';

		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_actions' ), 10, 2 );
		add_action( 'woocommerce_order_action_walley_cancel_order', array( $this, 'trigger_walley_cancel_order' ) );
		add_action( 'woocommerce_order_action_walley_activate_order', array( $this, 'trigger_walley_activate_order' ) );
	}

	/**
	 * Add custom actions to order actions select box on edit order page
	 * Only added for paid orders that haven't fired this action yet.
	 *
	 * @param array  $actions order actions array to display.
	 * @param object $order WooCommerce order.
	 * @return array - updated actions
	 */
	public function add_order_meta_box_actions( $actions, $order ) {

		// If this order wasn't created using collector_checkout or collector_invoice payment method, bail.
		if ( ! in_array( $order->get_payment_method(), array( 'collector_checkout', 'collector_invoice' ), true ) ) {
			return $actions;
		}

		// If the order has not been paid for, bail.
		if ( empty( $order->get_date_paid() ) ) {
			return $actions;
		}

		// If order hasn't already been cancelled - add cancel action.
		if ( empty( $order->get_meta( '_collector_order_cancelled', true ) ) && empty( $order->get_meta( '_collector_order_activated', true ) ) ) {
			$actions['walley_cancel_order']   = __( 'Cancel Walley order', 'collector-checkout-for-woocommerce' );
			$actions['walley_activate_order'] = __( 'Activate Walley order', 'collector-checkout-for-woocommerce' );
		}

		return $actions;
	}

	/**
	 *  Trigger Activate Walley order.
	 *
	 * @param object $order The WooCommerce order.
	 *
	 * @return void
	 */
	public function trigger_walley_activate_order( $order ) {
		if ( ! is_object( $order ) ) {
			return;
		}
		CCO_WC()->order_management->activate_walley_order( $order->get_id() );
	}

	/**
	 *  Trigger Cancel Walley order.
	 *
	 * @param object $order The WooCommerce order.
	 *
	 * @return void
	 */
	public function trigger_walley_cancel_order( $order ) {
		if ( ! is_object( $order ) ) {
			return;
		}
		CCO_WC()->order_management->cancel_walley_order( $order->get_id() );
	}
}
new Walley_Checkout_Order_Actions();
