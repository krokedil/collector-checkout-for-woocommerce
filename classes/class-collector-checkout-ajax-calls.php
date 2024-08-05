<?php
/**
 * Ajax class file.
 *
 * @package Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Ajax class.
 */
class Collector_Checkout_Ajax_Calls extends WC_AJAX {

	/**
	 * Use new or old API.
	 *
	 * @var bool $walley_use_new_api
	 */
	public static $walley_use_new_api;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return bool.
	 */
	public static function use_new_api() {
		if ( null === self::$walley_use_new_api ) {
			self::$walley_use_new_api = walley_use_new_api();
		}
		return self::$walley_use_new_api;
	}

	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'get_public_token'             => true,
			'get_checkout_thank_you'       => true,
			'customer_adress_updated'      => true,
			'walley_reauthorize_order'     => true,
			'walley_change_payment_method' => true,
			'walley_log_js'                => true,
			'walley_get_order'             => true,

		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Refresh checkout fragment.
	 */
	public static function walley_change_payment_method() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'walley_change_payment_method' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$available_gateways           = WC()->payment_gateways()->get_available_payment_gateways();
		$switch_to_collector_checkout = isset( $_POST['collector_checkout'] ) ? sanitize_text_field( wp_unslash( $_POST['collector_checkout'] ) ) : '';
		if ( 'false' === $switch_to_collector_checkout ) {
			// Set chosen payment method to first gateway that is not Qliro One Checkout for WooCommerce.
			$first_gateway = reset( $available_gateways );
			if ( 'collector_checkout' !== $first_gateway->id ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway->id );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'collector_checkout' );
		}

		WC()->payment_gateways()->set_current_gateway( $available_gateways );

		$redirect = wc_get_checkout_url();
		$data     = array(
			'redirect' => $redirect,
		);

		wp_send_json_success( $data );
		wp_die();
	}

	/**
	 * Gets the Walley order.
	 */
	public static function walley_get_order() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'walley_get_order' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		// Get customer data from Collector.
		$private_id    = WC()->session->get( 'collector_private_id' );
		$customer_type = WC()->session->get( 'collector_customer_type' );

		// Use new or old API.
		if ( self::use_new_api() ) {
			$collector_order = CCO_WC()->api->get_walley_checkout(
				array(
					'private_id'    => $private_id,
					'customer_type' => $customer_type,
				)
			);
		} else {
			$collector_order = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
			$collector_order = $collector_order->request();
		}

		if ( is_wp_error( $collector_order ) ) {
			$return = sprintf( __( 'Could not connect to Walley. Please reload the page and try again.', 'collector-checkout-for-woocommerce' ), $collector_order->get_error_message() );
			wp_send_json_error( $return );
		}

		$customer_data = Walley_Checkout_Session::get_customer_address( $collector_order );

		wp_send_json_success( $customer_data );
	}

	/**
	 * Gets the Public token from session.
	 *
	 * @return void
	 */
	public static function get_public_token() {
		$customer_type      = filter_input( INPUT_POST, 'customer_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$public_token       = WC()->session->get( 'collector_public_token' );
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$test_mode          = $collector_settings['test_mode'];

		// Use current token if one is stored in session previously and we still have the same customer type.
		if ( ! empty( $public_token ) && WC()->session->get( 'collector_customer_type' ) === $customer_type && get_woocommerce_currency() === WC()->session->get( 'collector_currency' ) ) {
			$return = array(
				'publicToken'   => WC()->session->get( 'collector_public_token' ),
				'test_mode'     => $test_mode,
				'customer_type' => $customer_type,
			);
			wp_send_json_success( $return );
		} else {

			// Get a new public token from Collector.
			if ( self::use_new_api() ) {
				$collector_order = CCO_WC()->api->initialize_walley_checkout( array( 'customer_type' => $customer_type ) );
			} else {
				$init_checkout   = new Collector_Checkout_Requests_Initialize_Checkout( $customer_type );
				$collector_order = $init_checkout->request();
			}

			if ( is_wp_error( $collector_order ) || empty( $collector_order ) ) {
				$return = sprintf( '%s <a href="%s" class="button wc-forward">%s</a>', __( 'Could not connect to Walley. Error message: ', 'collector-checkout-for-woocommerce' ) . $collector_order->get_error_message(), wc_get_checkout_url(), __( 'Try again', 'collector-checkout-for-woocommerce' ) );
				wp_send_json_error( $return );
			} else {

				$return = array(
					'publicToken'   => $collector_order['data']['publicToken'],
					'test_mode'     => $test_mode,
					'customer_type' => $customer_type,
				);

				// Set post metas so they can be used again later.
				WC()->session->set( 'collector_public_token', $collector_order['data']['publicToken'] );
				WC()->session->set( 'collector_private_id', $collector_order['data']['privateId'] );
				WC()->session->set( 'collector_customer_type', $customer_type );
				WC()->session->set( 'collector_currency', get_woocommerce_currency() );

				wp_send_json_success( $return );
			}
		}
	}

	/**
	 * Customer address updated - triggered when collectorCheckoutCustomerUpdated event is fired
	 */
	public static function customer_adress_updated() {
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( wp_unslash( sanitize_key( $_REQUEST['nonce'] ) ), 'collector_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		$update_needed = 'no';

		// Get customer data from Collector.
		$private_id    = WC()->session->get( 'collector_private_id' );
		$customer_type = WC()->session->get( 'collector_customer_type' );

		// Use new or old API.
		if ( self::use_new_api() ) {
			$collector_order = CCO_WC()->api->get_walley_checkout(
				array(
					'private_id'    => $private_id,
					'customer_type' => $customer_type,
				)
			);
		} else {
			$collector_order = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
			$collector_order = $collector_order->request();
		}

		$customer_data                     = array();
		$customer_data['billing_country']  = $collector_order['data']['countryCode'];
		$customer_data['shipping_country'] = $collector_order['data']['countryCode'];
		$customer_data['billing_email']    = $collector_order['data']['customer']['email'];
		$customer_data['billing_phone']    = $collector_order['data']['customer']['mobilePhoneNumber'];

		if ( 'BusinessCustomer' === $collector_order['data']['customerType'] ) {
			$customer_data['billing_city']     = $collector_order['data']['businessCustomer']['invoiceAddress']['city'];
			$customer_data['billing_address']  = $collector_order['data']['businessCustomer']['invoiceAddress']['address'];
			$customer_data['billing_postcode'] = $collector_order['data']['businessCustomer']['invoiceAddress']['postalCode'];

			$customer_data['shipping_city']     = $collector_order['data']['businessCustomer']['deliveryAddress']['city'];
			$customer_data['shipping_address']  = $collector_order['data']['businessCustomer']['deliveryAddress']['address'];
			$customer_data['shipping_postcode'] = $collector_order['data']['businessCustomer']['deliveryAddress']['postalCode'];
		} else {
			$customer_data['billing_city']     = $collector_order['data']['customer']['deliveryAddress']['city'];
			$customer_data['billing_address']  = $collector_order['data']['customer']['billingAddress']['address'];
			$customer_data['billing_postcode'] = $collector_order['data']['customer']['billingAddress']['postalCode'];

			$customer_data['shipping_city']     = $collector_order['data']['customer']['deliveryAddress']['city'];
			$customer_data['shipping_address']  = $collector_order['data']['customer']['deliveryAddress']['address'];
			$customer_data['shipping_postcode'] = $collector_order['data']['customer']['deliveryAddress']['postalCode'];
		}

		if ( $customer_data['billing_country'] ) {

			// If country is changed then we need to trigger an cart update in the Collector Checkout.
			if ( WC()->customer->get_billing_country() !== $customer_data['billing_country'] ) {
				$update_needed = 'yes';
			}

			// If country is changed then we need to trigger an cart update in the Collector Checkout.
			if ( WC()->customer->get_shipping_postcode() !== $customer_data['shipping_postcode'] ) {
				$update_needed = 'yes';
			}
			// Set customer data in Woo.
			WC()->customer->set_billing_address( $customer_data['billing_address'] );
			WC()->customer->set_billing_country( $customer_data['billing_country'] );
			WC()->customer->set_billing_city( $customer_data['billing_city'] );
			WC()->customer->set_billing_postcode( $customer_data['billing_postcode'] );
			WC()->customer->set_shipping_country( $customer_data['shipping_country'] );
			WC()->customer->set_shipping_city( $customer_data['shipping_city'] );
			WC()->customer->set_shipping_address( $customer_data['shipping_address'] );
			WC()->customer->set_shipping_postcode( $customer_data['shipping_postcode'] );
			WC()->customer->save();

			WC()->cart->calculate_totals();
		}

		wp_send_json_success( $customer_data );
	}

	/**
	 * Get thankyou iframe info.
	 *
	 * @return void
	 */
	public static function get_checkout_thank_you() {
		$order_id           = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$purchase_status    = filter_input( INPUT_POST, 'purchase_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$test_mode          = $collector_settings['test_mode'];
		$order              = wc_get_order( $order_id );

		// If something went wrong in get_customer_data() - display a "thank you page light".
		if ( 'not-completed' === $purchase_status ) {
			$public_token = filter_input( INPUT_POST, 'public_token', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( WC()->session->get( 'collector_customer_type' ) ) {
				$customer_type = WC()->session->get( 'collector_customer_type' );
			} else {
				$customer_type = 'b2c';
			}
		} else {
			$public_token  = $order->get_meta( '_collector_public_token' );
			$customer_type = $order->get_meta( '_collector_customer_type' );
		}

		$return = array(
			'publicToken'   => $public_token,
			'test_mode'     => $test_mode,
			'customer_type' => $customer_type,
		);

		wp_send_json_success( $return );
	}

	/**
	 * Reauthorizes / updates an order in Walleys system.
	 *
	 * @return void
	 */
	public static function walley_reauthorize_order() {

		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'walley_reauthorize_order' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$order_id = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT );
		$order    = wc_get_order( $order_id );

		if ( empty( $order->get_date_paid() ) ) {
			wp_send_json_error( 'Can not sync Walley order since it has not been marked as paid yet.' );
			wp_die();
		}

		if ( floatval( $order->get_total() ) > floatval( $order->get_meta( '_collector_original_order_total', true ) ) ) {
				// Translators: Original order amount.
			$message = sprintf( __( 'Updated total amount sent to Walley can not be higher than the original order amount (%1$s).', 'collector-checkout-for-woocommerce' ), $order->get_meta( '_collector_original_order_total', true ) );
			$order->add_order_note( $message );
			wp_send_json_error( $message );
			wp_die();
		}

		// Not going to do this for non-Walley orders.
		if ( 'collector_checkout' !== $order->get_payment_method() ) {
			wp_send_json_error( 'Payment method is not Walley' );
			wp_die();
		}

		$response = CCO_WC()->api->reauthorize_walley_order( $order_id );

		if ( ! is_wp_error( $response ) ) {
			if ( 202 === $response['status'] ) {
				$order->add_order_note( __( 'Walley order successfully updated.', 'collector-checkout-for-woocommerce' ) );
			} elseif ( 201 === $response['status'] ) {
				// This should not happen as long as we do not allow a order total amount that is higher than the original order amount.
				$order->add_order_note( __( 'Walley order sync started. Waiting for reauthorize response.', 'collector-checkout-for-woocommerce' ) );
				$order->update_meta_data( '_walley_reauthorize_data', wp_json_encode( $response['header'] ) );
				$order->save();
			} else {
				// Translators: Request response http status.
				$order->add_order_note( sprintf( __( 'Walley order sync started. Unknown http status response. Status: %1$s.', 'collector-checkout-for-woocommerce' ), $response['status'] ) );
			}

			// Save received data to WP transient.
			walley_save_order_data_to_transient(
				array(
					'order_id'     => $order_id,
					'status'       => $response['status'],
					'total_amount' => $order->get_total(),
					'currency'     => $order->get_currency(),
				)
			);

		} else {
			// Translators: Request error message & request error code.
			$order->add_order_note( sprintf( __( 'Could not update order lines in Walley. Error message: %1$s. Error code: %2$s</i>', 'collector-checkout-for-woocommerce' ), $response->get_error_message(), $response->get_error_code() ) );
			wp_send_json_error( 'Could not update Walley order.' );
			wp_die();
		}
		wp_send_json_success();
		wp_die();
	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function walley_log_js() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'walley_log_js' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$posted_message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$private_id     = WC()->session->get( 'collector_private_id' );
		$message        = "Frontend JS $private_id: $posted_message";
		CCO_WC()->logger::log( $message );
		wp_send_json_success();
		wp_die();
	}
}
Collector_Checkout_Ajax_Calls::init();
