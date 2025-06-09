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
		add_action( 'init', array( $this, 'handle_walley_order_redirect' ), 999 );
	}

	/**
	 * Handle the redirect from Walley Checkout to the order confirmation page, and schedule the order confirmation if needed.
	 *
	 * @return void
	 */
	public function handle_walley_order_redirect() {
		$walley_confirm = filter_input( INPUT_GET, 'walley_confirm', FILTER_SANITIZE_SPECIAL_CHARS );
		$public_token   = filter_input( INPUT_GET, 'public-token', FILTER_SANITIZE_SPECIAL_CHARS );
		// Return if we don't have our parameters set.
		if ( empty( $walley_confirm ) || empty( $public_token ) ) {
			return;
		}

		$order_id = walley_get_order_id_by_public_token( $public_token );
		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Get the private id, and customer type from the order meta.
		$private_id    = $order->get_meta( '_collector_private_id', true );
		$customer_type = $order->get_meta( '_collector_customer_type', true );

		$order->update_meta_data( '_walley_pending_callback', 'yes' );

		// Maybe schedule the order confirmation.
		$this->maybe_schedule_order_confirmation( $private_id, $public_token, $customer_type );

		$walley_order = walley_get_order_from_walley( $private_id, $customer_type, $order->get_currency() );
		// If the order was not found, we can't update the meta data for it.
		if ( is_wp_error( $walley_order ) ) {
			CCO_WC()->logger::log( 'Error getting Walley order: ' . $walley_order->get_error_message() );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$order = walley_update_order_meta( $order, $walley_order );

		// Unset the session data for the customer.
		wc_collector_unset_sessions();

		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	/**
	 * Maybe schedule the order confirmation.
	 *
	 * @param string $private_id The private ID of the Walley order.
	 * @param string $public_token The public token of the Walley order.
	 * @param string $customer_type The type of customer (private or business).
	 *
	 * @return bool
	 */
	public function maybe_schedule_order_confirmation( $private_id, $public_token, $customer_type ) {
		// See if the order was already scheduled for processing.
		$scheduled_actions = as_get_scheduled_actions(
			array(
				'hook'   => 'collector_check_for_order',
				'status' => ActionScheduler_Store::STATUS_PENDING,
				'args'   => array( $private_id, $public_token, $customer_type ),
			),
			'ids'
		);

		if ( ! empty( $scheduled_actions ) ) {
			CCO_WC()->logger::log( "collector_check_for_order callback already scheduled for private id: $private_id." );
			return true; // The action is already scheduled, no need to schedule it again.
		}

		$settings = get_option( 'woocommerce_collector_checkout_settings', array() );
		$schedule_delay = intval( $settings['order_payment_complete_delay'] ?? 1 ); // Default to 1 minute if not set.
		$result = as_schedule_single_action( time() + (MINUTE_IN_SECONDS * $schedule_delay), 'collector_check_for_order', array( $private_id, $public_token, $customer_type ) );

		return $result !== 0; // Return true if the action was scheduled successfully, false otherwise.
	}
}
