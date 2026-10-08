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

	private const SCHEDULE_INTERVAL_SEC = 30; // In seconds.

	public const HOOK_PREFIX = 'walley_scheduled_callback_';

	/**
	 * REST API namespace.
	 */
	public const REST_API_NAMESPACE = 'krokedil/walley/v1';

	/**
	 * REST API endpoint.
	 */
	public const REST_API_ENDPOINT = '/callback';

	/**
	 * Full REST API route.
	 * wp-json/krokedil/walley/v1/callback
	 */
	public const REST_API_ROUTE = 'wp-json/' . self::REST_API_NAMESPACE . self::REST_API_ENDPOINT;

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( self::HOOK_PREFIX . 'process_authorization', array( 'Walley_Subscription', 'process_authorization' ) );
	}

	/**
	 * Register the REST API route(s).
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_API_NAMESPACE,
			self::REST_API_ENDPOINT,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'callback_handler' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handles a callback from Walley.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function callback_handler( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			return new WP_Error( 'missing_params', 'Missing parameters.', array( 'status' => 400 ) );
		}

		$params     = filter_var_array( $params, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$event_type = $params['Type'] ?? null;
		$payload    = $params['Payload'] ?? null;

		$did_schedule = false;
		switch ( $event_type ) {
			case 'walley:order:created':
			case 'walley:authorization:failed':
			case 'walley:authorization:retrying':
				// A subscription is recognized by its customer token.
				// Since we only want to handle subscriptions here, we can return early if no customer token is present.
				$customer_token = $payload['CustomerToken'];
				if ( empty( $customer_token ) ) {
					return new WP_REST_Response( null, 200 );
				}

				// If the authorization ID is missing, this is not a renewal.
				$authorization_id = $payload['AuthorizationId'];
				if ( empty( $authorization_id ) ) {
					return new WP_REST_Response( null, 200 );
				}

				$walley_order_id = $payload['OrderId'];
				$reason          = $payload['Reason'] ?? null; // Only available when failed or retrying.

				// Schedule the processing of the authorization.
				$signature    = "{$event_type}:{$authorization_id}:{$walley_order_id}";
				$args         = array(
					'hook' => self::HOOK_PREFIX . 'process_authorization',
					'args' => array(
						array(
							'authorization_id' => $authorization_id,
							'walley_order_id'  => $walley_order_id,
							'event_type'       => $event_type,
							'reason'           => $reason,
						),
					),
				);
				$did_schedule = $this->schedule_callback( $args, $signature );
				break;
			default:
				CCO_WC()->logger::log( "[CALLBACK HANDLER] Unhandled event type: {$event_type}" );
				break;
		}

		return new WP_REST_Response( null, $did_schedule ? 200 : 422 );
	}

	/**
	 * Schedule a callback for processing.
	 *
	 * @param array  $args The arguments to schedule. A 'signature' key is required to avoid duplicates.
	 * @param string $signature The unique signature for the callback.
	 *
	 * @return bool True if the callback was scheduled, false otherwise.
	 */
	public function schedule_callback( $args, $signature ) {
		$as_args           = array(
			'hook'   => $args['hook'],
			'args'   => $args['args'],
			'status' => \ActionScheduler_Store::STATUS_PENDING,
		);
		$scheduled_actions = as_get_scheduled_actions( $as_args, OBJECT );

		if ( ! empty( $scheduled_actions ) ) {
			CCO_WC()->logger::log( "[SCHEDULE CALLBACK]: The order is already scheduled for processing. Signature: {$signature}." );
			return true;
		}

		// If we get here, we should be good to create a new scheduled action, since none are currently scheduled for this order.
		$schedule_id = as_schedule_single_action(
			time() + self::SCHEDULE_INTERVAL_SEC,
			$args['hook'],
			$args['args'],
			'',
			true, // unique.
		);

		$did_schedule = 0 !== $schedule_id;
		if ( ! $did_schedule ) {
			CCO_WC()->logger::log( "[SCHEDULE CALLBACK]: Could not schedule the callback for processing. Signature: {$signature}." );
		} else {
			CCO_WC()->logger::log( "[SCHEDULE CALLBACK]: Scheduled the callback for processing. Signature: {$signature}." );
		}

		return $did_schedule;
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

		$order = wc_collector_get_order_by_private_id( $private_id );

		if ( $order ) {
			// Get the metadata for if the order is pending a callback from walley.
			$pending_callback = $order->get_meta( '_walley_pending_callback', true );

			// Maybe abort the callback (if the order already has been processed in Woo).
			if ( ! empty( $order->get_date_paid() ) ) {
				CCO_WC()->logger::log( 'Aborting API callback. Order ' . $order->get_order_number() . '(order ID ' . $order->get_id() . ', Private ID ' . $private_id . ') already processed.' );
			} else {
				if ( 'yes' !== $pending_callback ) {
					CCO_WC()->logger::log( 'Order status not set correctly for order ' . $order->get_order_number() . '(order ID ' . $order->get_id() . ', Private ID ' . $private_id . ') during checkout process. Setting order status to Processing/Completed in API callback.' );
					// translators: Walley private ID.
					$note = sprintf( __( 'Order status not set correctly during checkout process. Confirming purchase via callback from Walley.', 'collector-checkout-for-woocommerce' ), $private_id );
					$order->add_order_note( $note );
				} else {
					CCO_WC()->logger::log( 'Pending order received a callback from Walley ' . $order->get_order_number() . '(order ID ' . $order->get_id() . ', Private ID ' . $private_id . '). Confirming order.' );
					// translators: Walley private ID.
					$note = sprintf( __( 'Callback from Walley received.', 'collector-checkout-for-woocommerce' ), $private_id );
					$order->add_order_note( $note );
					$order->update_meta_data( '_walley_pending_callback', 'no' );
					$order->save();
				}
				walley_confirm_order( $order, $private_id );
			}
		} else {
			// No order found.
			CCO_WC()->logger::log( 'API-callback executed. We could NOT find Private id ' . $private_id . '(with public token ' . $public_token . ' & customer type ' . $customer_type . '). Aborting process.' );
		}
	}
}
Collector_Api_Callbacks::get_instance();
