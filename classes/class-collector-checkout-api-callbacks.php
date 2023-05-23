<?php //phpcs:ignore
/**
 * * API Callbacks class.
 *
 * @package Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Collector_Api_Callbacks class.
 *
 * Class that handles Collector API callbacks.
 */
class Collector_Api_Callbacks {

	/**
	 * The Collector order
	 *
	 * @var array The Collector order object.
	 */
	public $collector_order = array();

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Collector_Api_Callbacks constructor.
	 */
	public function __construct() {
		add_action( 'collector_check_for_order', array( $this, 'collector_check_for_order_callback' ), 10, 3 );
	}

	/**
	 * Check for order.
	 *
	 * @param string $private_id The private id.
	 * @param string $public_token The public token.
	 * @param string $customer_type The customer type.
	 *
	 * @return void
	 * @throws Exception When WC_Data_Store validation fails.
	 */
	public function collector_check_for_order_callback( $private_id, $public_token, $customer_type = 'b2c' ) {
		CCO_WC()->logger::log( 'Check for order in API-callback. Private id: ' . $private_id . '. Public token: ' . $public_token );
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'return'         => 'ids',
				'payment_method' => 'collector_checkout',
				'date_created'   => '>' . ( time() - MONTH_IN_SECONDS ),
			)
		);
		$orders         = $query->get_orders();
		$order_id_match = '';
		foreach ( $orders as $order_id ) {

			$order_private_id = get_post_meta( $order_id, '_collector_private_id', true );

			if ( $order_private_id === $private_id ) {
				$order_id_match = $order_id;
				break;
			}
		}

		// Did we get a match?
		if ( $order_id_match ) {
			$order = wc_get_order( $order_id_match );

			if ( $order ) {
				// Check order status & order total.
				CCO_WC()->logger::log( 'API-callback hit. Private id ' . $private_id . '. already exist in order ID ' . $order_id_match . ' Checking order status...' );
				$this->check_order_status( $private_id, $public_token, $customer_type, $order );
			} else {
				// No order, why?
				CCO_WC()->logger::log( 'API-callback hit. Private id ' . $private_id . '. already exist in order ID ' . $order_id_match . '. But we could not instantiate an order object' );
			}
		} else {
			// No order found - create a new.
			CCO_WC()->logger::log( 'API-callback hit. We could NOT find Private id ' . $private_id . '(with public token ' . $public_token . ' & customer type ' . $customer_type . '). Aborting process.' );
		}

	}

	/**
	 * Check order status order total and transaction id, in case checkout process failed.
	 *
	 * @param string   $private_id The private id.
	 * @param string   $public_token The public token.
	 * @param string   $customer_type The customer type.
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public function check_order_status( $private_id, $public_token, $customer_type, $order ) {

		// Use new or old API.
		if ( walley_use_new_api() ) {
			$collector_order = CCO_WC()->api->get_walley_checkout(
				array(
					'private_id'    => $private_id,
					'customer_type' => $customer_type,
				)
			);
		} else {
			$response        = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type, $order->get_currency() );
			$collector_order = $response->request();
		}

		if ( is_wp_error( $collector_order ) ) {
			$order->add_order_note( __( 'Could not retreive Walley order during order status check (on API callback).', 'collector-checkout-for-woocommerce' ) );
		}

		if ( is_object( $order ) ) {

			// Check order status.
			if ( empty( $order->get_date_paid() ) ) {
				// Set order status in Woo.
				$this->set_order_status( $order, $collector_order );
			}

			// Compare order totals between the orders.
			cco_check_order_totals( $order, $collector_order );

			// Check if we need to update reference in collectors system.
			if ( empty( $collector_order['data']['reference'] ) ) {
				$this->update_order_reference_in_collector( $order, $customer_type, $private_id );
			}
		}
	}

	/**
	 * Set order status function
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $collector_order The Collector order.
	 *
	 * @return void
	 */
	public function set_order_status( $order, $collector_order ) {
		if ( 'Preliminary' === $collector_order['data']['purchase']['result'] || 'Completed' === $collector_order['data']['purchase']['result'] ) {
			$order->payment_complete( $collector_order['data']['purchase']['purchaseIdentifier'] );
			$order->add_order_note( 'Payment via Collector Checkout. Payment ID: ' . sanitize_key( $collector_order['data']['purchase']['purchaseIdentifier'] ) );
			CCO_WC()->logger::log( 'Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to Processing/Completed.' );
		} elseif ( 'Signing' === $collector_order['data']['purchase']['result'] ) {
			$order->add_order_note( __( 'Order is waiting for electronic signing by customer. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $collector_order['data']['purchase']['purchaseIdentifier'] );
			update_post_meta( $order->get_id(), '_transaction_id', $collector_order['data']['purchase']['purchaseIdentifier'] );
			$order->update_status( 'on-hold' );
			CCO_WC()->logger::log( 'Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to On hold.' );
		} else {
			$order->add_order_note( __( 'Order is PENDING APPROVAL by Walley. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $collector_order['data']['purchase']['purchaseIdentifier'] );
			$order->update_status( 'on-hold' );
			CCO_WC()->logger::log( 'Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to On hold.' );
		}
	}

	/**
	 *
	 * Update the Collector Order with the WooCommerce Order number
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param string   $customer_type The customer type.
	 * @param string   $private_id The private id.
	 *
	 * @return void
	 */
	public function update_order_reference_in_collector( $order, $customer_type, $private_id ) {

		// Use new or old API.
		if ( walley_use_new_api() ) {
			$collector_order = CCO_WC()->api->set_order_reference_in_walley(
				array(
					'order_id'      => $order->get_id(),
					'private_id'    => $private_id,
					'customer_type' => $customer_type,
				)
			);
		} else {
			$update_reference = new Collector_Checkout_Requests_Update_Reference( $order->get_order_number(), $private_id, $customer_type );
			$update_reference->request();
			CCO_WC()->logger::log( 'Update Collector order reference for order - ' . $order->get_order_number() );
		}
	}
}
Collector_Api_Callbacks::get_instance();
