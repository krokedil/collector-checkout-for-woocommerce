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
			'get_public_token'                => true,
			'update_checkout'                 => true,
			'add_customer_order_note'         => true,
			'get_checkout_thank_you'          => true,
			'get_customer_data'               => true,
			'customer_adress_updated'         => true,
			'update_fragment'                 => true,
			'checkout_error'                  => true,
			'update_delivery_module_shipping' => true,
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
	 * Gets the Public token from session.
	 *
	 * @return void
	 */
	public static function get_public_token() {
		$customer_type      = filter_input( INPUT_POST, 'customer_type', FILTER_SANITIZE_STRING );
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
			$init_checkout   = new Collector_Checkout_Requests_Initialize_Checkout( $customer_type );
			$collector_order = $init_checkout->request();

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

				// Save session ID and Private ID to DB.
				$collector_checkout_sessions = new Collector_Checkout_Sessions();
				$collector_data              = array(
					'session_id' => $collector_checkout_sessions->get_session_id(),
				);
				$args                        = array(
					'private_id' => $collector_order['data']['privateId'],
					'data'       => $collector_data,
				);
				$result                      = Collector_Checkout_DB::create_data_entry( $args );

				wp_send_json_success( $return );
			}
		}
	}

	/**
	 * Update checkout.
	 *
	 * @return void
	 */
	public static function update_checkout() {
		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		$private_id          = WC()->session->get( 'collector_private_id' );
		$customer_type       = WC()->session->get( 'collector_customer_type' );
		$update_fees         = new Collector_Checkout_Requests_Update_Fees( $private_id, $customer_type );
		$collector_order_fee = $update_fees->request();

		if ( is_checkout() ) {
			$cart_item_total = Collector_Checkout_Requests_Cart::cart();

			// Update checkout and annul payment method if the total cart item amount is 0.
			if ( empty( $cart_item_total['items'] ) ) {
				$return                 = array();
				$return['redirect_url'] = wc_get_checkout_url();
				wp_send_json_error( $return );
			}
		}

		// Check that update fees request was ok.
		if ( is_wp_error( $collector_order_fee ) ) {
			// Check if purchase was completed, if it was don't redirect customer.
			if ( 900 === $collector_order_fee->get_error_code() ) {
				if ( ! empty( $collector_order_fee->get_error_message( 'Purchase_Completed' ) ) || ! empty( $collector_order_fee->get_error_message( 'Purchase_Commitment_Found' ) ) ) {
					$return = array();
					// Check if an order exist with this private id. If we find a match, redirect to thank you page.
					$order_id = wc_collector_get_order_id_by_private_id( $private_id );
					if ( ! empty( $order_id ) ) {
						$order = wc_get_order( $order_id );
						if ( is_object( $order ) ) {
							$return['redirect_url'] = $order->get_checkout_order_received_url();
						} else {
							$return['redirect_url'] = '#900';
						}
					} else {
						$return['redirect_url'] = '#900';
					}
					wp_send_json_error( $return );
				}
			}

			// Check if somethings wrong with the content of the cart sent, if it was don't redirect customer.
			if ( 400 === $collector_order_fee->get_error_code() ) {
				if ( ! empty( $collector_order_fee->get_error_message( 'Duplicate_Articles' ) ) || ! empty( $collector_order_fee->get_error_message( 'Validation_Error' ) ) ) {
					$return                 = array();
					$return['redirect_url'] = '#400';
					wp_send_json_error( $return );
				}
			}

			// Check if the resource is temporarily locked, if it was don't redirect customer.
			if ( 423 === $collector_order_fee->get_error_code() ) {
				$return                 = array();
				$return['redirect_url'] = '#423';
				wp_send_json_error( $return );
			}

			wc_collector_unset_sessions();
			$return                 = array();
			$return['redirect_url'] = wc_get_checkout_url();
			wp_send_json_error( $return );
		}

		$update_cart          = new Collector_Checkout_Requests_Update_Cart( $private_id, $customer_type );
		$collector_order_cart = $update_cart->request();

		// Check that update cart request was ok.
		if ( is_wp_error( $collector_order_cart ) ) {

			// Check if purchase was completed, if it was don't redirect customer.
			if ( 900 === $collector_order_cart->get_error_code() ) {
				if ( ! empty( $collector_order_cart->get_error_message( 'Purchase_Completed' ) ) || ! empty( $collector_order_cart->get_error_message( 'Purchase_Commitment_Found' ) ) ) {
					$return                 = array();
					$return['redirect_url'] = '#';
					wp_send_json_error( $return );
				}
			}

			// Check if somethings wrong with the content of the cart sent, if it was don't redirect customer.
			if ( 400 === $collector_order_cart->get_error_code() ) {

				if ( ! empty( $collector_order_cart->get_error_message( 'Duplicate_Articles' ) ) || ! empty( $collector_order_cart->get_error_message( 'Validation_Error' ) ) ) {

					$return                 = array();
					$return['redirect_url'] = '#';
					wp_send_json_error( $return );
				}
			}

			// Check if the resource is temporarily locked, if it was don't redirect customer.
			if ( 423 === $collector_order_cart->get_error_code() ) {
				$return                 = array();
				$return['redirect_url'] = '#';
				wp_send_json_error( $return );
			}

			wc_collector_unset_sessions();
			$return                 = array();
			$return['redirect_url'] = wc_get_checkout_url();
			wp_send_json_error( $return );
		}

		// Update database session id.
		$collector_checkout_sessions = new Collector_Checkout_Sessions();
		$collector_data              = array(
			'session_id' => $collector_checkout_sessions->get_session_id(),
		);
		$args                        = array(
			'private_id' => WC()->session->get( 'collector_private_id' ),
			'data'       => $collector_data,
		);

		Collector_Checkout_DB::update_data( $args );

		wp_send_json_success();
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
		$private_id      = WC()->session->get( 'collector_private_id' );
		$customer_type   = WC()->session->get( 'collector_customer_type' );
		$collector_order = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$collector_order = $collector_order->request();

		$customer_data                     = array();
		$customer_data['billing_country']  = $collector_order['data']['countryCode'];
		$customer_data['shipping_country'] = $collector_order['data']['countryCode'];
		$customer_data['billing_email']    = $collector_order['data']['customer']['email'];

		if ( 'BusinessCustomer' === $collector_order['data']['customerType'] ) {
			$customer_data['billing_postcode']  = $collector_order['data']['businessCustomer']['invoiceAddress']['postalCode'];
			$customer_data['shipping_postcode'] = $collector_order['data']['businessCustomer']['deliveryAddress']['postalCode'];
		} else {
			$customer_data['billing_postcode']  = $collector_order['data']['customer']['billingAddress']['postalCode'];
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
			WC()->customer->set_billing_country( $customer_data['billing_country'] );
			WC()->customer->set_shipping_country( $customer_data['shipping_country'] );
			WC()->customer->set_billing_postcode( $customer_data['billing_postcode'] );
			WC()->customer->set_shipping_postcode( $customer_data['shipping_postcode'] );
			WC()->customer->save();
			WC()->cart->calculate_totals();
		}

		wp_send_json_success( $customer_data );
	}

	/**
	 * Collector Delivery Module shipping method update - triggered when collectorCheckoutShippingUpdated event is fired
	 */
	public static function update_delivery_module_shipping() {
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( wp_unslash( sanitize_key( $_REQUEST['nonce'] ) ), 'collector_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		$private_id      = WC()->session->get( 'collector_private_id' );
		$customer_type   = WC()->session->get( 'collector_customer_type' );
		$collector_order = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$collector_order = $collector_order->request();
		$shipping_title  = $collector_order['data']['shipping']['label'];
		$shipping_id     = $collector_order['data']['shipping']['shipping_id'];
		$shipping_price  = $collector_order['data']['shipping']['cost'];
		$shipping_vat    = $collector_order['data']['shipping']['shipping_vat'];

		$shipping_data = array(
			'label'        => $shipping_title,
			'shipping_id'  => $shipping_id,
			'cost'         => $shipping_price,
			'shipping_vat' => $shipping_vat,
		);
		WC()->session->set( 'collector_delivery_module_data', $shipping_data );

		$chosen_shipping_methods = array( 'collector_delivery_module' );
		WC()->session->set( 'chosen_shipping_methods', apply_filters( 'coc_shipping_method', $chosen_shipping_methods, $shipping_data ) ); // Set chosen shipping method, with filter to allow overrides.

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		$data = array(
			'shipping_title' => $shipping_title,
			'shipping_price' => $shipping_price,
		);
		wp_send_json_success( $data );
	}

	/**
	 * Save customer order note to session.
	 *
	 * @return void
	 */
	public static function add_customer_order_note() {
		$order_note = filter_input( INPUT_POST, 'order_note', FILTER_SANITIZE_STRING );
		WC()->session->set( 'collector_customer_order_note', $order_note );

		wp_send_json_success();
	}

	/**
	 * Get thankyou iframe info.
	 *
	 * @return void
	 */
	public static function get_checkout_thank_you() {
		$order_id           = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_STRING );
		$purchase_status    = filter_input( INPUT_POST, 'purchase_status', FILTER_SANITIZE_STRING );
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$test_mode          = $collector_settings['test_mode'];

		// If something went wrong in get_customer_data() - display a "thank you page light".
		if ( 'not-completed' === $purchase_status ) {
			$public_token = filter_input( INPUT_POST, 'public_token', FILTER_SANITIZE_STRING );
			if ( WC()->session->get( 'collector_customer_type' ) ) {
				$customer_type = WC()->session->get( 'collector_customer_type' );
			} else {
				$customer_type = 'b2c';
			}
		} else {
			$public_token  = get_post_meta( $order_id, '_collector_public_token', true );
			$customer_type = get_post_meta( $order_id, '_collector_customer_type', true );
		}

		$return = array(
			'publicToken'   => $public_token,
			'test_mode'     => $test_mode,
			'customer_type' => $customer_type,
		);

		wp_send_json_success( $return );
	}

	/**
	 * Get customer data from Collector when payment success url is triggered.
	 */
	public static function get_customer_data() {
		$private_id    = WC()->session->get( 'collector_private_id' );
		$customer_type = WC()->session->get( 'collector_customer_type' );

		// Prevent duplicate orders if confirmation page is reloaded manually by customer.
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'collector_checkout',
				'date_created'   => '>' . ( time() - DAY_IN_SECONDS ),
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
		if ( $order_id_match ) {
			$order = wc_get_order( $order_id_match );
			CCO_WC()->logger::log( 'Payment complete triggered for private id ' . $private_id . ' but _collector_private_id already exist in this order. Redirecting customer to thankyou page.' );
			$return                 = array();
			$return['redirect_url'] = $order->get_checkout_order_received_url();
			wp_send_json_error( $return );
		}

		CCO_WC()->logger::log( 'Payment complete triggered for private id ' . $private_id . '. Starting WooCommerce checkout form processing...' );

		$customer_data   = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$collector_order = $customer_data->request();

		if ( 'PurchaseCompleted' === $collector_order['data']['status'] ) {
			// Save the payment method and payment id.
			$payment_method = $collector_order['data']['purchase']['paymentName'];
			$payment_id     = $collector_order['data']['purchase']['purchaseIdentifier'];
			WC()->session->set( 'collector_payment_method', $payment_method );
			WC()->session->set( 'collector_payment_id', $payment_id );

			// Return the data, customer note and create a nonce.
			$return = array();

			// Run customer data through helper function.
			$return['customer_data'] = wc_collector_verify_customer_data( $collector_order );
			$return['nonce']         = wp_create_nonce( 'woocommerce-process_checkout' );
			if ( null !== WC()->session->get( 'collector_customer_order_note' ) ) {
				$return['order_note'] = WC()->session->get( 'collector_customer_order_note' );
			} else {
				$return['order_note'] = '';
			}
			$return['shipping'] = WC()->session->get( 'collector_chosen_shipping' );
			wp_send_json_success( $return );
		} else {
			// We didn't get a status PurchaseCompleted from Collector (but the Collector redirectPageUri has been triggered) so we redirect the customer to thank you page.
			$return                 = array();
			$url                    = add_query_arg(
				array(
					'purchase-status' => 'not-completed',
					'public-token'    => sanitize_text_field( $_POST['public_token'] ), //phpcs:ignore
				),
				wc_get_endpoint_url( 'order-received', '', get_permalink( wc_get_page_id( 'checkout' ) ) )
			);
			$return['redirect_url'] = $url;
			CCO_WC()->logger::log( 'Payment complete triggered for private id ' . $private_id . ' but status is not PurchaseCompleted in Collectors system. Current status: ' . var_export( $collector_order['data']['status'], true ) . '. Redirecting customer to simplified thankyou page.' ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			wp_send_json_error( $return );
		}
	}

	/**
	 * WC Ajax updates fragment.
	 *
	 * @return void
	 */
	public static function update_fragment() {
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( 'false' === $_POST['collector'] ) { //phpcs:ignore
			// Set chosen payment method to first gateway that is not Collector Checkout for WooCommerce.
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
		/* phpcs:ignore
		ob_start();
		if ( 'collector_checkout' !== WC()->session->get( 'chosen_payment_method' ) ) {

			wc_get_template( 'checkout/form-checkout.php', array(
				'checkout' => WC()->checkout(),
			) );

		} else {
			include( COLLECTOR_BANK_PLUGIN_DIR . '/templates/form-checkout.php' );
		}
		$checkout_output = ob_get_clean();
		*/
		$redirect = wc_get_checkout_url();
		$data     = array(
			'redirect' => $redirect,
		);
		/* phpcs:ignore
		$data = array(
			'fragments' => array(
				'checkout' => $checkout_output,
			),
		);
		*/
		wp_send_json_success( $data );
	}

	/**
	 * Checkout error.
	 *
	 * @return void
	 * @throws Exception If something goes wrong.
	 */
	public static function checkout_error() {
		CCO_WC()->logger::log( 'Starting Create Order Fallback creation...' );
		$customer_type = WC()->session->get( 'collector_customer_type' );
		$private_id    = WC()->session->get( 'collector_private_id' );

		// Prevent duplicate orders if confirmation page is reloaded manually by customer.
		$collector_public_token = sanitize_key( $_POST['public_token'] ); //phpcs:ignore
		$query                  = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'collector_checkout',
				'date_created'   => '>' . ( time() - DAY_IN_SECONDS ),
			)
		);
		$orders                 = $query->get_orders();
		$order_id_match         = null;
		foreach ( $orders as $order_id ) {
			$order_collector_public_token = get_post_meta( $order_id, '_collector_public_token', true );
			if ( strtolower( $order_collector_public_token ) === strtolower( $collector_public_token ) ) {
				$order_id_match = $order_id;
				break;
			}
		}
		// _collector_public_token already exist in an order. Let's redirect the customer to the thankyou page for that order.
		if ( $order_id_match ) {
			CCO_WC()->logger::log( 'Checkout error triggered but _collector_public_token already exist in this order: ' . $order_id_match );
			$order        = wc_get_order( $order_id_match );
			$redirect_url = $order->get_checkout_order_received_url();
			$return       = array( 'redirect_url' => $redirect_url );
			wp_send_json_success( $return );
		}

		// If we get here its safe to create an order.
		$create_order = new Collector_Create_Local_Order_Fallback();

		// Create the order.
		$order    = $create_order->create_order();
		$order_id = $order->get_id();

		// Add items to order.
		$create_order->add_items_to_local_order( $order );

		// Add fees to order.
		$create_order->add_order_fees( $order );

		// Maybe add invoice fee to order.
		if ( 'DirectInvoice' === WC()->session->get( 'collector_payment_method' ) ) {
			$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
			$product_id         = $collector_settings['collector_invoice_fee'];
			if ( $product_id ) {
				wc_collector_add_invoice_fee_to_order( $order_id, $product_id );
			}
		}

		// Add shipping to order.
		$create_order->add_order_shipping( $order );

		// Add tax rows to order.
		$create_order->add_order_tax_rows( $order );

		// Add coupons to order.
		$create_order->add_order_coupons( $order );

		// Add customer to order.
		$create_order->add_customer_data_to_local_order( $order, $customer_type, $private_id );

		// Add payment method.
		$create_order->add_order_payment_method( $order );

		// Make sure to run Sequential Order numbers if plugin exsists.
		// @Todo - Se i we can run action woocommerce_checkout_update_order_meta in this process.
		// so Sequential order numbers and other plugins can do their stuff themselves.
		if ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
			$sequential = new WC_Seq_Order_Number_Pro();
			$sequential->set_sequential_order_number( $order_id );
		} elseif ( class_exists( 'WC_Seq_Order_Number' ) ) {
			$sequential = new WC_Seq_Order_Number();
			$sequential->set_sequential_order_number( $order_id, get_post( $order_id ) );
		}

		// Calculate order totals.
		$create_order->calculate_order_totals( $order );

		// Update the Collector Order with the Order ID.
		$create_order->update_order_reference_in_collector( $order, $customer_type, $private_id );

		// Add order note.
		if ( isset( $_POST['error_message'] ) && ! empty( $_POST['error_message'] ) ) { //phpcs:ignore
			$error_message = 'Error message: ' . wp_unslash( sanitize_text_field( sanitize_text_field( trim( $_POST['error_message'] ) ) ) ); //phpcs:ignore
		} else {
			$error_message = 'Error message could not be retreived';
		}
		// translators: The error message.
		$note = sprintf( __( 'This order was made as a fallback due to an error in the checkout (%s). Please verify the order with Walley.', 'collector-checkout-for-woocommerce' ), $error_message );
		$order->add_order_note( $note );
		$order->set_status( 'on-hold' );
		$order->save();

		$redirect_url = $order->get_checkout_order_received_url();
		$return       = array( 'redirect_url' => $redirect_url );
		wp_send_json_success( $return );
	}
}
Collector_Checkout_Ajax_Calls::init();
