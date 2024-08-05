<?php  // phpcs:ignore
/**
 * Collector_Checkout_Confirmation class.
 *
 * @package CollectorCheckout/Classes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collector_Checkout_Pay_For_Order_Confirmation class.
 *
 * Handles Collector Checkout confirmation page for pay for order purchases.
 */
class Collector_Checkout_Pay_For_Order_Confirmation {

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

		add_action( 'template_redirect', array( $this, 'confirm_order_pay_order' ) );
	}


	/**
	 * Confirm order
	 */
	public function confirm_order_pay_order() {
		$collector_confirm = filter_input( INPUT_GET, 'collector_confirm_order_pay', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$public_token      = filter_input( INPUT_GET, 'public-token', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_key         = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// Return if we don't have our parameters set.
		if ( empty( $collector_confirm ) || empty( $public_token ) || empty( $order_key ) ) {
			return;
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );

		// Return if we cant find an order id.
		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// If the order is already completed, return.
		if ( ! empty( $order->get_date_paid() ) ) {
			return;
		}

		wc_collector_confirm_order( $order );
	}
}

Collector_Checkout_Pay_For_Order_Confirmation::get_instance();
