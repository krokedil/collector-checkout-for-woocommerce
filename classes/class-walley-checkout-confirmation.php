<?php  // phpcs:ignore
/**
 * Walley_Checkout_Confirmation class.
 *
 * @package CollectorCheckout/Classes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walley_Checkout_Confirmation class.
 *
 * Handles Walley Checkout confirmation page.
 */
class Walley_Checkout_Confirmation {

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
	 * Collector_Checkout_Confirmation constructor.
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'confirm_order' ), 999 );
	}

	/**
	 * Confirms the order with Walley and redirects the customer to the thankyou page.
	 *
	 * @return void
	 */
	public function confirm_order() {
		$walley_confirm = filter_input( INPUT_GET, 'walley_confirm', FILTER_SANITIZE_SPECIAL_CHARS );
		$public_token   = filter_input( INPUT_GET, 'public-token', FILTER_SANITIZE_SPECIAL_CHARS );
		// Return if we dont have our parameters set.
		if ( empty( $walley_confirm ) || empty( $public_token ) ) {
			return;
		}

		$order_id = walley_get_order_id_by_public_token( $public_token );
		if ( empty( $order_id ) ) {
			// The customer has paid, but the checkout never placed the order. Build it here rather than
			// leaving them on a checkout page for a purchase they have already been charged for.
			$order = $this->recover_missing_order( $public_token );
			if ( empty( $order ) ) {
				return;
			}

			wc_collector_unset_sessions();
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$order = wc_get_order( $order_id );

		// If the order does not need processing, set the status to on-hold, and redirect.
		// This is to prevent an error from attempting to complete an order before it has been moved to the order management api in Walley.
		if ( ! $order->needs_processing() ) {
			$order->update_meta_data( '_walley_pending_callback', 'yes' );
			$order->update_status( 'on-hold', __( 'The payment has been completed, but awaiting callback from Walley to confirm the order.', 'collector-checkout-for-woocommerce' ) );
			$order->save();
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$result = walley_confirm_order( $order_id );
		if ( $result ) {
			$walley_payment_id = $order->get_meta( '_collector_payment_id' );
			CCO_WC()->logger::log( "Order ID $order_id confirmed on the confirmation page. Walley payment ID: " . ( empty( $walley_payment_id ) ? 'not saved to order' : $walley_payment_id ) );
		}

		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	/**
	 * Creates the order for a purchase that was paid for but never placed.
	 *
	 * Only the public token is in the URL, and an order that does not exist cannot be looked up to get
	 * the private id, so it has to come from the session. When the customer comes back in a different
	 * browser there is no session to read, and the notification callback has to handle it instead.
	 *
	 * @param string $public_token The public token.
	 *
	 * @return WC_Order|false
	 */
	private function recover_missing_order( $public_token ) {
		$private_id    = WC()->session ? WC()->session->get( 'collector_private_id' ) : '';
		$customer_type = WC()->session ? WC()->session->get( 'collector_customer_type' ) : '';

		if ( empty( $private_id ) ) {
			CCO_WC()->logger::log( "Confirmation page reached for public token $public_token, but no WooCommerce order exists and there is no private id in the session. Leaving it to the notification callback." );
			return false;
		}

		CCO_WC()->logger::log( "Confirmation page reached for public token $public_token with no WooCommerce order. Attempting backup order creation for private id $private_id." );

		Collector_Api_Callbacks::get_instance()->backup_order_creation( $private_id, $public_token, empty( $customer_type ) ? 'b2c' : $customer_type );

		$order = wc_collector_get_order_by_private_id( $private_id );

		return empty( $order ) ? false : $order;
	}
}

Walley_Checkout_Confirmation::get_instance();
