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
			return;
		}

		$result = walley_confirm_order( $order_id );
		$order = wc_get_order($order_id);

		if ( $result ) {
			$walley_payment_id = $order->get_meta( '_collector_payment_id', true );
			CCO_WC()->logger::log( "Order ID $order_id confirmed on the confirmation page. Walley payment ID: $walley_payment_id." );
		}

		$order = wc_get_order( $order_id );
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}
}

Walley_Checkout_Confirmation::get_instance();
