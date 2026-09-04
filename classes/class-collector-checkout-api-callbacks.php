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

	/**
	 * How long a backup order creation lock may be held before it is treated as abandoned.
	 */
	private const BACKUP_ORDER_LOCK_TTL = 300; // In seconds.

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
			// No order found. The purchase went through at Walley without the checkout ever placing an
			// order here, so build it from the Walley order instead of losing the purchase.
			CCO_WC()->logger::log( 'API-callback executed. We could NOT find Private id ' . $private_id . '(with public token ' . $public_token . ' & customer type ' . $customer_type . '). Attempting backup order creation.' );
			$this->backup_order_creation( $private_id, $public_token, $customer_type );
		}
	}

	/**
	 * Creates the WooCommerce order from the Walley order, when the checkout failed to create one.
	 *
	 * The order is normally placed by the browser from inside Walley's onBeforePayment callback. When
	 * that does not happen — the callback was never registered, the customer lost the connection, the
	 * request was aborted — Walley still completes the purchase, and without this the payment exists
	 * with no order behind it.
	 *
	 * @param string $private_id The private id.
	 * @param string $public_token The public token.
	 * @param string $customer_type The customer type.
	 *
	 * @return void
	 */
	public function backup_order_creation( $private_id, $public_token, $customer_type ) {
		if ( ! apply_filters( 'walley_enable_backup_order_creation', true, $private_id ) ) {
			CCO_WC()->logger::log( "Backup order creation is disabled by filter. Private id $private_id." );
			return;
		}

		// Only one process may create an order for a given private id.
		if ( ! $this->acquire_lock( $private_id ) ) {
			CCO_WC()->logger::log( "Backup order creation already in progress for private id $private_id. Aborting." );
			return;
		}

		try {
			// The browser may have placed the order between the lookup above and the lock being taken.
			$existing_order = wc_collector_get_order_by_private_id( $private_id );
			if ( ! empty( $existing_order ) ) {
				CCO_WC()->logger::log( "Backup order creation aborted. Private id $private_id was placed as order {$existing_order->get_order_number()} in the meantime." );
				return;
			}

			$collector_order = $this->get_walley_order( $private_id, $customer_type );
			if ( is_wp_error( $collector_order ) ) {
				CCO_WC()->logger::log( "Backup order creation failed. Could not retrieve Walley order for private id $private_id. " . $collector_order->get_error_message() );
				return;
			}

			// Never create an order for a session that was not actually paid for.
			$status = wc_get_var( $collector_order['data']['status'] );
			if ( 'PurchaseCompleted' !== $status ) {
				CCO_WC()->logger::log( "Backup order creation skipped. Private id $private_id is in status $status, not PurchaseCompleted." );
				return;
			}

			$order = $this->process_order( $collector_order, $private_id, $public_token, $customer_type );
			if ( ! $order instanceof WC_Order ) {
				return;
			}

			// Sets the payment status and meta, and sends the order number back to Walley.
			walley_confirm_order( $order, $private_id, $collector_order );

			// Puts the order on hold if the totals we rebuilt do not match what the customer was charged.
			cco_check_order_totals( $order, $collector_order );

			CCO_WC()->logger::log( "Backup order creation succeeded. Private id $private_id created as order {$order->get_order_number()} (order ID {$order->get_id()})." );
		} finally {
			$this->release_lock( $private_id );
		}
	}

	/**
	 * Takes the backup order creation lock for a private id.
	 *
	 * Two paid orders for one purchase is the worst outcome this code can produce, and a transient
	 * lock cannot prevent it: get_transient() followed by set_transient() is not atomic, and with a
	 * persistent object cache the read can be stale. The unique index on option_name is the primitive
	 * that is atomic here, so INSERT IGNORE is used to claim the lock in a single statement.
	 *
	 * @param string $private_id The private id.
	 * @param bool   $retry Whether this is the retry that follows breaking a stale lock.
	 *
	 * @return bool Whether the lock was taken.
	 */
	private function acquire_lock( $private_id, $retry = false ) {
		global $wpdb;

		$key = $this->get_lock_key( $private_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic insert is the point.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				$key,
				(string) time()
			)
		);

		if ( 1 === $inserted ) {
			$this->flush_lock_cache( $key );
			return true;
		}

		if ( $retry ) {
			return false;
		}

		// A process that died holding the lock must not block recovery forever.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- must not read a cached value.
		$held_since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );

		if ( ! empty( $held_since ) && ( time() - $held_since ) > self::BACKUP_ORDER_LOCK_TTL ) {
			CCO_WC()->logger::log( "Breaking a stale backup order creation lock for private id $private_id, held for " . ( time() - $held_since ) . ' seconds.' );
			$this->release_lock( $private_id );
			return $this->acquire_lock( $private_id, true );
		}

		return false;
	}

	/**
	 * Releases the backup order creation lock for a private id.
	 *
	 * @param string $private_id The private id.
	 *
	 * @return void
	 */
	private function release_lock( $private_id ) {
		global $wpdb;

		$key = $this->get_lock_key( $private_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the lock is deliberately kept out of the cache.
		$wpdb->delete( $wpdb->options, array( 'option_name' => $key ), array( '%s' ) );

		$this->flush_lock_cache( $key );
	}

	/**
	 * The option name used as the lock for a private id.
	 *
	 * @param string $private_id The private id.
	 *
	 * @return string
	 */
	private function get_lock_key( $private_id ) {
		return 'walley_backup_order_lock_' . md5( $private_id );
	}

	/**
	 * Keeps the option cache from serving a lock that was written or deleted behind its back.
	 *
	 * @param string $key The option name.
	 *
	 * @return void
	 */
	private function flush_lock_cache( $key ) {
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * Gets the Walley order via whichever API version is in use.
	 *
	 * @param string $private_id The private id.
	 * @param string $customer_type The customer type.
	 *
	 * @return array|WP_Error
	 */
	private function get_walley_order( $private_id, $customer_type ) {
		if ( walley_use_new_api() ) {
			return CCO_WC()->api->get_walley_checkout(
				array(
					'private_id'    => $private_id,
					'customer_type' => $customer_type,
				)
			);
		}

		$response = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		return $response->request();
	}

	/**
	 * Builds the WooCommerce order from the Walley order.
	 *
	 * Everything is taken from the Walley order rather than the cart, since there is no customer
	 * session to read from when this runs from a scheduled action.
	 *
	 * @param array  $collector_order Walley order.
	 * @param string $private_id The private id.
	 * @param string $public_token The public token.
	 * @param string $customer_type The customer type.
	 *
	 * @return WC_Order|false
	 */
	private function process_order( $collector_order, $private_id, $public_token, $customer_type ) {
		$order = wc_create_order( array( 'status' => 'pending' ) );

		if ( is_wp_error( $order ) ) {
			CCO_WC()->logger::log( 'Backup order creation. Error - could not create order. ' . $order->get_error_message() );
			return false;
		}

		$customer_address = Walley_Checkout_Session::get_customer_address( $collector_order );

		// WooCommerce has no c/o field, so it goes in front of address 2 — the same as the checkout does.
		foreach ( array( 'billing', 'shipping' ) as $type ) {
			$co = $customer_address[ "{$type}_address_co" ] ?? '';
			if ( ! empty( $co ) ) {
				$address_2                               = $customer_address[ "{$type}_address_2" ] ?? '';
				$customer_address[ "{$type}_address_2" ] = empty( $address_2 ) ? $co : $co . ' ' . $address_2;
			}
			unset( $customer_address[ "{$type}_address_co" ] );
		}

		foreach ( $customer_address as $key => $value ) {
			$setter = "set_$key";
			if ( method_exists( $order, $setter ) ) {
				$order->{$setter}( sanitize_text_field( $value ) );
			}
		}

		if ( 'BusinessCustomer' === $collector_order['data']['customerType'] ) {
			$org_nr = $collector_order['data']['businessCustomer']['organizationNumber'] ?? '';
			if ( ! empty( $org_nr ) ) {
				$order->update_meta_data( '_collector_org_nr', sanitize_text_field( $org_nr ) );
			}
		}

		$order->set_created_via( 'collector_checkout_api' );
		$order->set_currency( sanitize_text_field( $collector_order['data']['currency'] ?? get_woocommerce_currency() ) );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );

		// Passing the gateway sets both the id and the title; fall back to the id if it is unavailable.
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		if ( isset( $gateways['collector_checkout'] ) ) {
			$order->set_payment_method( $gateways['collector_checkout'] );
		} else {
			$order->set_payment_method( 'collector_checkout' );
			$order->set_payment_method_title( 'Walley Checkout' );
		}

		$order->update_meta_data( '_collector_private_id', $private_id );
		$order->update_meta_data( '_collector_public_token', $public_token );
		$order->update_meta_data( '_collector_customer_type', $customer_type );

		$this->add_order_items( $order, $collector_order );

		$order->add_order_note( __( 'Order created via Walley Checkout API callback, because the checkout did not create it. The order lines were rebuilt from the Walley order — please verify it against Walley before shipping.', 'collector-checkout-for-woocommerce' ) );

		$order->calculate_totals();
		$order->save();

		CCO_WC()->logger::log( 'Backup order creation - order ID - ' . $order->get_id() . ' - created.' );

		return $order;
	}

	/**
	 * Adds the Walley order lines to the WooCommerce order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $collector_order Walley order.
	 *
	 * @return void
	 */
	private function add_order_items( $order, $collector_order ) {
		$items = $collector_order['data']['order']['items'] ?? array();

		foreach ( $items as $item ) {
			$id       = $item['id'] ?? '';
			$quantity = ! empty( $item['quantity'] ) ? intval( $item['quantity'] ) : 1;

			// Walley is sent unit prices including VAT, so convert back to what WooCommerce stores.
			$total_incl_vat = floatval( $item['unitPrice'] ?? 0 ) * $quantity;
			$vat_rate       = floatval( $item['vat'] ?? 0 );
			$total_excl_vat = $vat_rate > 0 ? round( $total_incl_vat / ( 1 + ( $vat_rate / 100 ) ), 2 ) : round( $total_incl_vat, 2 );

			if ( false !== strpos( $id, 'shipping|' ) || 'Frakt' === $id ) {
				$shipping = new WC_Order_Item_Shipping();
				$shipping->set_props(
					array(
						'method_title' => $item['description'] ?? '',
						'method_id'    => str_replace( 'shipping|', '', $id ),
						'total'        => wc_format_decimal( $total_excl_vat ),
					)
				);
				$order->add_item( $shipping );
				continue;
			}

			if ( false !== strpos( $id, 'invoicefee|' ) || false !== strpos( $id, 'fee|' ) || 'rounding-fee' === $id ) {
				$fee = new WC_Order_Item_Fee();
				$fee->set_props(
					array(
						'name'  => $item['description'] ?? '',
						'total' => wc_format_decimal( $total_excl_vat ),
					)
				);
				$order->add_item( $fee );
				continue;
			}

			$product = $this->get_product_from_line_item( $id );
			if ( $product instanceof WC_Product ) {
				$order->add_product(
					$product,
					$quantity,
					array(
						'subtotal' => $total_excl_vat,
						'total'    => $total_excl_vat,
					)
				);
				continue;
			}

			// The article number does not resolve to a product any more. Keep the line anyway, so the
			// order total is right and the merchant can see what was actually bought.
			CCO_WC()->logger::log( "Backup order creation: could not match Walley order line '$id' to a WooCommerce product. Adding it as a plain line item." );
			$line = new WC_Order_Item_Product();
			$line->set_props(
				array(
					'name'     => $item['description'] ?? $id,
					'quantity' => $quantity,
					'subtotal' => $total_excl_vat,
					'total'    => $total_excl_vat,
				)
			);
			$order->add_item( $line );
		}
	}

	/**
	 * Resolves a Walley order line id back to a WooCommerce product.
	 *
	 * The id is the product SKU, or the product id when the product has no SKU. Cart lines that would
	 * otherwise share an id get a numeric suffix before being sent (see
	 * Collector_Checkout_Requests_Cart::maybe_make_ids_unique), so that has to be undone here.
	 *
	 * @param string $id The Walley order line id.
	 *
	 * @return WC_Product|false
	 */
	private function get_product_from_line_item( $id ) {
		$candidates = array( $id );

		if ( preg_match( '/^(.+)_\d+$/', $id, $matches ) ) {
			$candidates[] = $matches[1];
		}

		foreach ( $candidates as $candidate ) {
			$product_id = wc_get_product_id_by_sku( $candidate );

			if ( empty( $product_id ) && is_numeric( $candidate ) ) {
				$product_id = absint( $candidate );
			}

			$product = ! empty( $product_id ) ? wc_get_product( $product_id ) : false;
			if ( $product instanceof WC_Product ) {
				return $product;
			}
		}

		return false;
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
			$order->add_order_note( __( 'Could not retrieve Walley order during order status check (on API callback).', 'collector-checkout-for-woocommerce' ) );
		}

		if ( is_object( $order ) ) {

			// Check order status.
			if ( empty( $order->get_date_paid() ) ) {
				$payment_status = $collector_order['data']['purchase']['result'];
				$payment_id     = $collector_order['data']['purchase']['purchaseIdentifier'];

				// Set order status in Woo.
				walley_set_order_status( $order, $payment_status, $payment_id, false, true );

				$order->save();
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
