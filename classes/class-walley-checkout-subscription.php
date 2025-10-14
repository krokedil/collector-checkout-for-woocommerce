<?php //phpcs:ignore
/**
 * Class for handling Woo subscriptions.
 *
 * @package Collector_Checkout/Classes/Request
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling subscriptions.
 */
class Walley_Subscription {
	public const GATEWAY_ID             = 'collector_checkout';
	public const UNSCHEDULED_TOKEN      = '_' . self::GATEWAY_ID . '_unscheduled_token';
	public const SKIP_OM                = '_' . self::GATEWAY_ID . '_skip_om';
	public const CANCELED_TOKEN_HISTORY = '_' . self::GATEWAY_ID . '_canceled_token_history';
	public const AUTHORIZATION_ID       = '_' . self::GATEWAY_ID . '_authorization_id';
	public const ZERO_AMOUNT_ORDER      = '_' . self::GATEWAY_ID . '_zero_amount_order';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_scheduled_subscription_payment_' . self::GATEWAY_ID, array( $this, 'process_scheduled_payment' ), 10, 2 );

		// TODO: Add support for changing payment method: Set the return_url for change payment method.
		add_filter( 'walley_urls', array( $this, 'set_subscription_order_redirect_urls' ), 10, 2 );

		// Whether the gateway should be available when handling subscriptions.
		add_filter( 'walley_is_available', array( $this, 'is_available' ) );

		// On successful payment method change, the customer is redirected back to the subscription view page. We need to handle the redirect and create a recurring token.
		add_action( 'woocommerce_account_view-subscription_endpoint', array( $this, 'handle_redirect_from_change_payment_method' ) );

		// Show the recurring token on the subscription page in the billing fields.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_payment_token' ) );

		// TODO: Add support for change payment method: Ensure wp_safe_redirect do not redirect back to default dashboard or home page on change_payment_method.
		add_filter( 'allowed_redirect_hosts', array( $this, 'extend_allowed_domains_list' ) );

		// Save payment token to the subscription when the merchant updates the order from the subscription page.
		add_action( 'woocommerce_saved_order_items', array( $this, 'subscription_updated_from_order_page' ), 10, 2 );

		// "Neither customers nor store managers can reactivate cancelled subscriptions."
		// See https://woocommerce.com/document/subscriptions/statuses/#cancelled-subscription-status
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'on_subscription_canceled' ) );

		// "You cannot reactivate subscriptions with the Expired status. Customers must manually create a new subscription or repurchase the subscription product to regain access."
		// See https://woocommerce.com/document/subscriptions/statuses/#expired-subscription-status
		add_action( 'woocommerce_subscription_status_expired', array( $this, 'on_subscription_expired' ) );
	}

	/**
	 * Handle actions when a subscription is canceled.
	 *
	 * @param \WC_Subscription $subscription The WooCommerce subscription object.
	 * @return void
	 */
	public function on_subscription_canceled( $subscription ) {
		if ( self::GATEWAY_ID !== $subscription->get_payment_method() ) {
			return;
		}

		$token = self::get_token( $subscription );
		if ( ! $token ) {
			return;
		}

		self::cancel_customer_token( $subscription, $token, __( 'The subscription was cancelled.', 'collector-checkout-for-woocommerce' ) );
	}

	/**
	 * Handle actions when a subscription is expired.
	 *
	 * @param \WC_Subscription $subscription The WooCommerce subscription object.
	 * @return void
	 */
	public function on_subscription_expired( $subscription ) {
		if ( self::GATEWAY_ID !== $subscription->get_payment_method() ) {
			return;
		}

		$token = self::get_token( $subscription );
		if ( ! $token ) {
			return;
		}

		self::cancel_customer_token( $subscription, $token, __( 'The subscription has expired.', 'collector-checkout-for-woocommerce' ) );
	}

	/**
	 * Process subscription renewal.
	 *
	 * @param float     $amount_to_charge Amount to charge.
	 * @param \WC_Order $renewal_order The Woo order that will be created as a result of the renewal.
	 * @return void
	 */
	public function process_scheduled_payment( $amount_to_charge, $renewal_order ) {
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order );
		$token         = self::get_token( $renewal_order );

		$response = CCO_WC()->api->renew_subscription( $renewal_order, $token );
		if ( is_wp_error( $response ) ) {
			// translators: Error message.
			$message = sprintf( __( 'Failed to renew subscription. Reason: %s', 'collector-checkout-for-woocommerce' ), $response->get_error_message() );

			foreach ( $subscriptions as $subscription ) {
				$subscription->add_order_note( $message );
				$subscription->payment_failed();
			}

			$renewal_order->add_order_note( $message );
			return;
		}

		$code = $response['status'];
		$data = $response['body']['data'];

		// The authorization request (renewal) may result in a 200 OK or 202 Accepted.
		// For 200 OK, we'll proceed with the renewal since the authorization is completed.
		// For 202 Accepted, the authorization is pending and we wait for a callback to complete the order.
		// See https://dev.walleypay.com/docs/checkout/tokenization/authorization.
		$order_id = null;
		$auth_id  = $data['authorizationId']; // Used for identifying the subscription renewal through the authorization in callbacks.
		if ( 200 === $code ) {
			$order_id = $data['orderId'];
			$note     = sprintf(
			// translators: 1: subscription token, 2: Walley order id.
				__( 'Subscription renewal was made successfully via Walley. Subscription token: %1$s. Walley order ID: %2$s', 'collector-checkout-for-woocommerce' ),
				$token,
				$order_id
			);
			$renewal_order->add_order_note( $note );
			$renewal_order->update_meta_data( '_collector_order_id', $order_id );
			$renewal_order->payment_complete( $order_id );
		} else {
			$note = sprintf(
			// translators: 1: subscription token, 2: authorization id.
				__( 'Subscription renewal is pending authorization via Walley. Subscription token: %1$s. Authorization ID: %2$s', 'collector-checkout-for-woocommerce' ),
				$token,
				$auth_id
			);

			$renewal_order->update_status( 'on-hold', $note );
		}

		$renewal_order->update_meta_data( self::AUTHORIZATION_ID, $auth_id );
		$renewal_order->save_meta_data();

		foreach ( $subscriptions as $subscription ) {
			$subscription->update_meta_data( self::UNSCHEDULED_TOKEN, $token );
			$subscription->update_meta_data( self::AUTHORIZATION_ID, $auth_id );

			if ( 200 === $code ) {
				$subscription->update_meta_data( '_collector_order_id', $order_id );
				$subscription->add_order_note( $note );
				$subscription->payment_complete( $order_id );
			} else {
				$subscription->add_order_note( $note );
				$subscription->update_status( 'on-hold' );
			}

			$subscription->save_meta_data();
		}
	}

	/**
	 * Retrieve the renewal order associated with an authorization ID.
	 *
	 * @param string $auth_id The authorization ID.
	 * @return WC_Order|null The renewal order if found, null otherwise.
	 */
	public static function get_renewal_order_by_auth_id( $auth_id ) {
		$orders = wc_get_orders(
			array(
				'meta_key'     => self::AUTHORIZATION_ID,
				'meta_value'   => $auth_id,
				'limit'        => 1,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'meta_compare' => '=',
			)
		);

		$renewal_order = reset( $orders );
		if ( empty( $renewal_order ) || $auth_id !== $renewal_order->get_meta( self::AUTHORIZATION_ID ) ) {
			return null;
		}

		return $renewal_order;
	}

	/**
	 * Process an authorization callback from Walley.
	 *
	 * @param array $args The callback arguments.
	 * @return void
	 */
	public static function process_authorization( $args ) {
		$auth_id    = $args['authorization_id'];
		$event_type = $args['event_type'];

		CCO_WC()->logger::log( "[AUTHORIZATION][{$event_type}] Processing authorization ID: {$auth_id}." );

		// Find the renewal order associated with this authorization ID.
		$renewal_order = self::get_renewal_order_by_auth_id( $auth_id );
		if ( null === $renewal_order ) {
			CCO_WC()->logger::log( "[AUTHORIZATION] No matching order found for authorization ID: {$auth_id}" );
			return;
		}

		CCO_WC()->logger::log( "[AUTHORIZATION] Found matching order ID: {$renewal_order->get_id()} for authorization ID: {$auth_id}" );
		// Update the renewal order and any subscriptions associated with the renewal order.
		$filter        = array(
			'subscription_status' => 'on-hold',
			'order_type'          => 'renewal',
		);
		$subscriptions = wcs_get_subscriptions_for_order( $renewal_order, $filter );
		if ( empty( $subscriptions ) ) {
			CCO_WC()->logger::log( "[AUTHORIZATION] No subscriptions found for renewal order ID: {$renewal_order->get_id()}" );
			return;
		}

		$walley_order_id = $args['walley_order_id'] ?? null;
		if ( ! empty( $walley_order_id ) ) {
			self::process_authorization_success( $renewal_order, $subscriptions, $walley_order_id );
		} else {
			self::process_authorization_error( $renewal_order, $subscriptions, $args['reason'] );
		}

		CCO_WC()->logger::log( "[AUTHORIZATION] Processed authorization ID: {$auth_id} for order ID: {$renewal_order->get_id()}" );
	}

	/**
	 * Process a successful authorization.
	 *
	 * @param \WC_Order $renewal_order The renewal order.
	 * @param array     $subscriptions The subscriptions associated with the renewal order.
	 * @param string    $walley_order_id The Walley order ID.
	 * @return void
	 */
	public static function process_authorization_success( $renewal_order, $subscriptions, $walley_order_id ) {
		// translators: Walley order ID.
		$note = sprintf( __( 'The subscription is now authorized by Walley, and renewal is complete. Walley order ID: %s.', 'collector-checkout-for-woocommerce' ), $walley_order_id );

		// Update the renewal order and any subscriptions associated with the renewal order.
		foreach ( $subscriptions as $subscription ) {
			$subscription->update_meta_data( '_collector_order_id', $walley_order_id );
			$subscription->add_order_note( $note );
			$subscription->payment_complete( $walley_order_id );
			$subscription->save_meta_data();
		}

		// translators: Walley order ID.
		$renewal_order->add_order_note( sprintf( __( 'The order is now authorized by Walley. Walley order ID %s.', 'collector-checkout-for-woocommerce' ), $walley_order_id ) );
		$renewal_order->update_meta_data( '_collector_order_id', $walley_order_id );
		$renewal_order->payment_complete( $walley_order_id );
	}

	/**
	 * Process a failed authorization.
	 *
	 * @param \WC_Order $renewal_order The renewal order.
	 * @param array     $subscriptions The subscriptions associated with the renewal order.
	 * @param string    $reason The reason for the failure.
	 * @return void
	 */
	public static function process_authorization_error( $renewal_order, $subscriptions, $reason ) {
		$log    = "[AUTHORIZATION][{$reason}]: ";
		$status = 'on-hold';
		switch ( $reason ) {
			case 'SERVICE_UNAVAILABLE':
				$log .= 'The authorization failed because the payment service is currently unavailable. Walley will attempt to process the payment again at a later time.';
				$note = __( 'The authorization failed because the payment service is currently unavailable. Walley will attempt to process the payment again at a later time.', 'collector-checkout-for-woocommerce' );
				break;
			case 'PAYMENT_METHOD_NO_FUNDS':
				$log .= 'The authorization failed because the payment method has insufficient funds Walley will attempt to process the payment again at a later time.';
				$note = __( 'The authorization failed because the payment method has insufficient funds. Walley will attempt to process the payment again at a later time.', 'collector-checkout-for-woocommerce' );
				break;
			case 'PAYMENT_METHOD_DECLINED':
				$log .= 'The payment method used for the authorization was declined by the payment provider or bank, and Walley will retry the authorization.';
				$note = __( 'The payment method used for the authorization was declined by the payment provider or bank, and Walley will retry the authorization.', 'collector-checkout-for-woocommerce' );
				break;
			case 'PAYMENT_METHOD_EXPIRED':
				$log   .= 'The authorization failed because the payment method has expired.';
				$note   = __( 'The authorization failed because the payment method has expired.', 'collector-checkout-for-woocommerce' );
				$status = 'failed';
				break;
			case 'CANCELLED_BY_CUSTOMER':
				$log   .= 'The customer token has been cancelled using the Cancel endpoint.';
				$note   = __( 'The customer token has been cancelled using the Cancel endpoint.', 'collector-checkout-for-woocommerce' );
				$status = 'cancelled';
				break;
			case 'PAYMENT_METHOD_REFUSED':
				$log   .= 'The payment method used for the authorization was refused by the payment provider or bank.';
				$note   = __( 'The payment method used for the authorization was refused by the payment provider or bank.', 'collector-checkout-for-woocommerce' );
				$status = 'failed';
				break;
			default:
				$log .= 'The authorization failed due to an unknown error.';
				$note = sprintf( __( 'The authorization failed due to an unknown error.', 'collector-checkout-for-woocommerce' ) );
				// Since we don't know the reason, we'll leave the order status to on-hold. This ensures that wcs_get_subscriptions_for_renewal_order() will find the subscription next time as we filter on "on-hold" status.
				break;
		}

		// Update the renewal order and any subscriptions associated with the renewal order.
		foreach ( $subscriptions as $subscription ) {
			if ( $subscription->get_status() === $status ) {
				$subscription->add_order_note( $note );
			} else {
				$subscription->update_status( $status, $note );
			}
		}

		if ( $renewal_order->get_status() === $status ) {
			$renewal_order->add_order_note( $note );
		} else {
			$renewal_order->update_status( $status, $note );
		}

		CCO_WC()->logger::log( $log );
	}

	/**
	 * Cancel an unscheduled token associated with a subscription.
	 *
	 * @note Always calls save().
	 *
	 * @param \WC_Subscription $subscription The WooCommerce subscription order.
	 * @param string           $token The unscheduled token to cancel.
	 * @param string|null      $reason Optional reason for cancelling the token. Empty string not allowed.
	 */
	public static function cancel_customer_token( $subscription, $token, $reason = null ) {
		$response = CCO_WC()->api->cancel_customer_token( $token );

		if ( ! is_wp_error( $response ) ) {
			CCO_WC()->logger::log( "[CANCEL TOKEN]: Cancelled customer token: {$token} in subscription: {$subscription->get_order_number()}" );
			self::update_token_history( $subscription, $token );

			$subscription->add_order_note(
				// translators: 1: subscription token, 2: optional reason for cancelling the token.
				sprintf( __( 'Cancelled subscription token: %1$s%2$s', 'collector-checkout-for-woocommerce' ), $token, $reason ? ". Reason: {$reason}" : '' )
			);

		} else {
			CCO_WC()->logger::log( "[CANCEL TOKEN] Failed to cancel customer token {$token} belonging to subscription #{$subscription->get_order_number()}. Error: {$response->get_error_message()}" );

			$subscription->add_order_note(
				// translators: 1: Subscription token, 2: Error message.
				sprintf( __( 'Failed to cancel subscription token: %1$s. Reason: %2$s', 'collector-checkout-for-woocommerce' ), $token, $response->get_error_message() )
			);
		}

		$subscription->save();
	}

	/**
	 * Update the token history for a subscription or order.
	 *
	 * @param WC_Order|WC_Subscription $subscription The WooCommerce subscription or order.
	 * @param string                   $old_token The old token to add to the history.
	 * @return void
	 */
	private static function update_token_history( $subscription, $old_token ) {
		$timestamp = current_time( 'mysql', true );
		$history   = (array) $subscription->get_meta( self::CANCELED_TOKEN_HISTORY );
		// Use the timestamp as key to avoid overwriting previous entries.
		$history[ $timestamp ] = $old_token;
		$subscription->update_meta_data( self::CANCELED_TOKEN_HISTORY, $history );
		$subscription->save_meta_data();
	}

	/**
	 *
	 * Save the customer token to the order and any subscriptions associated with the order.
	 *
	 * @param  \WC_Order $order The WC order.
	 * @param  string    $token The customer token to save.
	 * @param  bool      $overwrite_existing Whether to cancel the existing token before saving the new token.
	 */
	public static function save_customer_token( $order, $token, $overwrite_existing = false ) {
		$existing_token = $order->get_meta( self::UNSCHEDULED_TOKEN );
		if ( $overwrite_existing ) {
			if ( $existing_token !== $token ) {
				self::update_token_history( $order, $existing_token );
				$order->update_meta_data( self::UNSCHEDULED_TOKEN, $token );
				$order->save_meta_data();
			}

			$subscriptions = self::get_subscriptions_for_order( $order );
			foreach ( $subscriptions as $subscription ) {
				$existing_token = $subscription->get_meta( self::UNSCHEDULED_TOKEN );
				if ( ! empty( $existing_token ) && $existing_token !== $token ) {
					self::update_token_history( $subscription, $existing_token );
				}
				$subscription->update_meta_data( self::UNSCHEDULED_TOKEN, $token );
				$subscription->save_meta_data();
			}
		} else {

			$order->update_meta_data( self::UNSCHEDULED_TOKEN, $token );
			$order->save_meta_data();

			$subscriptions = self::get_subscriptions_for_order( $order );
			foreach ( $subscriptions as $subscription ) {
				$subscription->update_meta_data( self::UNSCHEDULED_TOKEN, $token );
				$subscription->save_meta_data();
			}
		}
	}

	/**
	 * Get the subscriptions associated with an order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return array<WC_Subscription>|array() Array of subscriptions, or empty array if no subscriptions found.
	 */
	public static function get_subscriptions_for_order( $order ) {
		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );
		}

		return array();
	}

	/**
	 * Retrieve the unscheduled token from the subscription (first) or order, and thereafter any subscriptions associated with the renewal order or its parent order.
	 *
	 * @param \WC_Order|\WC_Subscription $order The renewal order or subscription.
	 * @return string|null The customer token if found, null otherwise.
	 */
	public static function get_token( $order ) {
		// Prefer the token stored in the subscription first.
		if ( $order instanceof WC_Subscription ) {
			$token = $order->get_meta( self::UNSCHEDULED_TOKEN );
			if ( ! empty( $token ) ) {
				return $token;
			}
		} else {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
			foreach ( $subscriptions as $subscription ) {
				$token = $subscription->get_meta( self::UNSCHEDULED_TOKEN );
				if ( ! empty( $token ) ) {
					return $token;
				}
			}
		}

		// Next, check the order itself.
		$token = $order->get_meta( self::UNSCHEDULED_TOKEN );
		if ( ! empty( $token ) ) {
			return $token;
		}

		$parent_order = self::get_parent_order( $order );
		if ( ! empty( $parent_order ) ) {
			$token = $parent_order->get_meta( self::UNSCHEDULED_TOKEN );
			if ( ! empty( $token ) ) {
				return $token;
			}
		}

		return null;
	}

	/**
	 * Whether the gateway should be available if it contains a subscriptions.
	 *
	 * @param bool $is_available Whether the gateway is available.
	 * @return bool
	 */
	public function is_available( $is_available ) {
		// If no subscription is found, we don't need to do anything.
		if ( ! self::cart_has_subscription() ) {
			return $is_available;
		}

		// Allow free orders when changing subscription payment method.
		if ( self::is_change_payment_method() ) {
			return true;
		}

		return true;
	}

	/**
	 * TODO: Set the session URLs for change payment method request.
	 *
	 * Used for changing payment method.
	 *
	 * @param array $url_data The URL data.
	 * @param Order $helper The Order helper.
	 */
	public function set_subscription_order_redirect_urls( $url_data, $helper ) {
		if ( ! self::is_change_payment_method() ) {
			return $url_data;
		}

		$subscription = self::get_subscription( $helper->get_order() );
		$url          = add_query_arg( 'walley_redirect', 'subscription', $subscription->get_view_order_url() );
		return $url_data;
	}

	/**
	 * Handle the redirect from the change payment method page.
	 *
	 * @param int $subscription_id The subscription ID.
	 * @return void
	 */
	public function handle_redirect_from_change_payment_method( $subscription_id ) {
		// We use the 'walley_redirect' query var to determine if we are redirected from Walley after changing payment method, otherwise the customer is viewing a subscription.
		$walley_redirect = filter_input( INPUT_GET, 'walley_redirect', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'subscription' !== $walley_redirect ) {
			return;
		}

		$subscription = self::get_subscription( $subscription_id );
		if ( self::GATEWAY_ID !== $subscription->get_payment_method() ) {
			return;
		}

		$response = CCO_WC()->api->get_walley_order( $subscription->get_transaction_id() );
		if ( is_wp_error( $response ) ) {
			if ( function_exists( 'wc_print_notice' ) ) {
				// translators: Error message.
				wc_print_notice( sprintf( __( 'Failed to update payment method. Reason: %s', 'collector-checkout-for-woocommerce' ), $response->get_error_message() ), 'error' );
			}
			return;
		}

		$customer_token = $response['data']['order']['customerToken'] ?? null;
		$this->save_customer_token( $subscription, $customer_token );
		// translators: New subscription token.
		$subscription->add_order_note( sprintf( __( 'The payment method was changed by the customer. New subscription token: %s', 'collector-checkout-for-woocommerce' ), $customer_token ) );
	}

	/**
	 * Get a subscription's parent order.
	 *
	 * @param WC_Order $order The WooCommerce order id.
	 * @param string   $order_type The order type to check for. Default is 'any'. Other options are 'renewal', 'switch', 'resubscribe' and 'parent'.
	 * @return WC_Order|false The parent order or false if none is found.
	 */
	public static function get_parent_order( $order, $order_type = 'any' ) {
		$subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => $order_type ) );
		foreach ( $subscriptions as $subscription ) {
			$parent_order = $subscription->get_parent();
			if ( ! empty( $parent_order ) ) {
				return $parent_order;
			}
		}

		return false;
	}

	/**
	 * Check if the current request is for changing the payment method.
	 *
	 * @return bool
	 */
	public static function is_change_payment_method() {
		return isset( $_GET['change_payment_method'] ); // phpcs:ignore -- only used for checking if the query var is set.
	}

	/**
	 * Check if an order contains a subscription.
	 *
	 * @param \WC_Order $order The WooCommerce order or leave empty to use the cart (default).
	 * @return bool
	 */
	public static function order_has_subscription( $order ) {
		if ( empty( $order ) ) {
			return false;
		}

		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order, array( 'parent', 'resubscribe', 'switch', 'renewal' ) ) ) {
			return true;
		}

		return function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order );
	}

	/**
	 * Check if the cart contains a subscription.
	 *
	 * @return bool
	 */
	public static function cart_has_subscription() {
		if ( ! is_checkout() ) {
			return false;
		}

		return ( class_exists( 'WC_Subscriptions_Cart' ) && \WC_Subscriptions_Cart::cart_contains_subscription() ) || ( function_exists( 'wcs_cart_contains_failed_renewal_order_payment' ) && wcs_cart_contains_failed_renewal_order_payment() );
	}

	/**
	 * Whether the cart contains only free trial subscriptions.
	 *
	 * If invoked from anywhere but the checkout page, this will return FALSE.
	 *
	 * @return boolean
	 */
	public static function cart_has_only_free_trial() {
		if ( ! is_checkout() ) {
			return false;
		}

		return ( class_exists( 'WC_Subscriptions_Cart' ) ) ? \WC_Subscriptions_Cart::all_cart_items_have_free_trial() : false;
	}

	/**
	 * Retrieve a WC_Subscription from order ID.
	 *
	 * @param \WC_Order|int $order  The WC order or id.
	 * @return bool|\WC_Subscription The subscription object, or false if it cannot be found.
	 */
	public static function get_subscription( $order ) {
		return ! function_exists( 'wcs_get_subscription' ) ? false : wcs_get_subscription( $order );
	}

	/**
	 * Add Walley redirect payment page as allowed external url for wp_safe_redirect.
	 * We do this because WooCommerce Subscriptions use wp_safe_redirect when processing a payment method change request (from v5.1.0).
	 *
	 * @param array $hosts Domains that are allowed when wp_safe_redirect is used.
	 * @return array
	 */
	public function extend_allowed_domains_list( $hosts ) {
		// FIXME: Remove or keep if needed.
		return $hosts;
	}

	/**
	 * Save the payment token to the subscription when the merchant updates the order from the subscription page.
	 *
	 * @param int   $order_id The Woo order ID.
	 * @param array $items The posted data (includes even the data that was not updated).
	 * @return bool True if the payment token was updated, false otherwise.
	 */
	public function subscription_updated_from_order_page( $order_id, $items ) {
		$order = wc_get_order( $order_id );

		// The action hook woocommerce_saved_order_items is triggered for all order updates, so we must check if the payment method is Walley.
		if ( self::GATEWAY_ID !== $order->get_payment_method() ) {
			return false;
		}

		// Are we on the subscription page?
		if ( 'shop_subscription' === $order->get_type() ) {
			// Retrieve the stored token, and the one included in the posted data.
			$payment_token  = wc_get_var( $items[ self::UNSCHEDULED_TOKEN ] );
			$existing_token = $order->get_meta( self::UNSCHEDULED_TOKEN );

			// Did the customer update the subscription's payment token?
			if ( ! empty( $payment_token ) && $existing_token !== $payment_token ) {
				self::save_customer_token( $order, $payment_token, true );
				$order->add_order_note(
					sprintf(
					// translators: 1: User's name, 2: Existing token, 3: New token.
						__( '%1$s updated the subscription token from "%2$s" to "%3$s". Note: the previous token must be manually canceled if you do not intend to reuse it.', 'collector-checkout-for-woocommerce' ),
						ucfirst( wp_get_current_user()->display_name ),
						$existing_token,
						$payment_token
					)
				);

				$order->save();
				return true;
			}
		}

		return true;
	}

	/**
	 * Shows the recurring token for the order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function show_payment_token( $order ) {
		$subscription_token = $order->get_meta( self::UNSCHEDULED_TOKEN );
		if ( 'shop_subscription' === $order->get_type() ) {
			$label = __( 'Walley subscription token', 'collector-checkout-for-woocommerce' );
			?>
			<div class="order_data_column" style="clear:both; float:none; width:100%;">
				<div class="address">
					<p>
						<strong><?php echo esc_html( $label ); ?>:</strong><?php echo esc_html( $subscription_token ); ?>
					</p>
				</div>
				<div class="edit_address">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'            => self::UNSCHEDULED_TOKEN,
						'label'         => $label,
						'wrapper_class' => '_billing_company_field',
						'value'         => $subscription_token,
					)
				);
				?>
				</div>
			</div>
				<?php
		}
	}
}

new Walley_Subscription();