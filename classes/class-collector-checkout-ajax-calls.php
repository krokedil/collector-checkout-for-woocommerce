<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

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


	public static function get_public_token() {
		$customer_type      = wc_clean( $_REQUEST['customer_type'] );
		$public_token       = WC()->session->get( 'collector_public_token' );
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$test_mode          = $collector_settings['test_mode'];

		// Use current token if one is stored in session previously and we still have the same customer type
		if ( ! empty( $public_token ) && $customer_type == WC()->session->get( 'collector_customer_type' ) && get_woocommerce_currency() == WC()->session->get( 'collector_currency' ) ) {
			$return = array(
				'publicToken'   => WC()->session->get( 'collector_public_token' ),
				'test_mode'     => $test_mode,
				'customer_type' => $customer_type,
			);
			wp_send_json_success( $return );
			wp_die();

		} else {

			// Get a new public token from Collector.
			$init_checkout = new Collector_Checkout_Requests_Initialize_Checkout( $customer_type );
			$request       = $init_checkout->request();
			$decode        = json_decode( $request );

			if ( is_wp_error( $request ) || empty( $request ) ) {
					$return = sprintf( '%s <a href="%s" class="button wc-forward">%s</a>', __( 'Could not connect to Collector. Error message: ', 'collector-checkout-for-woocommerce' ) . $request->get_error_message(), wc_get_checkout_url(), __( 'Try again', 'collector-checkout-for-woocommerce' ) );
				wp_send_json_error( $return );
				wp_die();

			} else {

				$return = array(
					'publicToken'   => $decode->data->publicToken,
					'test_mode'     => $test_mode,
					'customer_type' => $customer_type,
				);

				// Set post metas so they can be used again later.
				WC()->session->set( 'collector_public_token', $decode->data->publicToken );
				WC()->session->set( 'collector_private_id', $decode->data->privateId );
				WC()->session->set( 'collector_customer_type', $customer_type );
				WC()->session->set( 'collector_currency', get_woocommerce_currency() );

				// Save session ID and Private ID to DB.
				$collector_checkout_sessions = new Collector_Checkout_Sessions();
				$collector_data              = array(
					'session_id' => $collector_checkout_sessions->get_session_id(),
				);
				$args                        = array(
					'private_id' => $decode->data->privateId,
					'data'       => $collector_data,
				);
				$result                      = Collector_Checkout_DB::create_data_entry( $args );

				wp_send_json_success( $return );
				wp_die();

			}
		}
	}

	public static function update_checkout() {

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		$private_id          = WC()->session->get( 'collector_private_id' );
		$customer_type       = WC()->session->get( 'collector_customer_type' );
		$update_fees         = new Collector_Checkout_Requests_Update_Fees( $private_id, $customer_type );
		$update_fees_request = $update_fees->request();
		$response_body       = json_decode( $update_fees_request['body'] );

		// Check that update fees request was ok.
		if ( is_wp_error( $update_fees_request ) || ! empty( $response_body->error ) || 200 !== $update_fees_request['response']['code'] ) {
			// Check if purchase was completed, if it was dont redirect customer.
			if ( 900 === $response_body->error->code ) {
				foreach ( $response_body->error->errors as $error ) {
					if ( 'Purchase_Completed' === $error->reason ) {
						$return                 = array();
						$return['redirect_url'] = '#';
						wp_send_json_error( $return );
						wp_die();
					}
				}
			}
			wc_collector_unset_sessions();
			$return                 = array();
			$return['redirect_url'] = wc_get_checkout_url();
			wp_send_json_error( $return );
			wp_die();
		}

		$update_cart         = new Collector_Checkout_Requests_Update_Cart( $private_id, $customer_type );
		$update_cart_request = $update_cart->request();
		$response_body       = json_decode( $update_cart_request['body'] );

		// Check that update cart request was ok
		if ( is_wp_error( $update_cart_request ) || ! empty( $response_body->error || 200 !== $update_cart_request['response']['code'] ) ) {
			wc_collector_unset_sessions();
			$return                 = array();
			$return['redirect_url'] = wc_get_checkout_url();
			wp_send_json_error( $return );
			wp_die();
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
		$result                      = Collector_Checkout_DB::update_data( $args );

		wp_send_json_success();
		wp_die();
	}

	/**
	 * Customer address updated - triggered when collectorCheckoutCustomerUpdated event is fired
	 */
	public static function customer_adress_updated() {

		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'collector_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		$update_needed = 'no';

		// Get customer data from Collector
		$private_id      = WC()->session->get( 'collector_private_id' );
		$customer_type   = WC()->session->get( 'collector_customer_type' );
		$collector_order = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$collector_order = $collector_order->request();
		$collector_order = json_decode( $collector_order );

		$customer_data                     = array();
		$customer_data['billing_country']  = $collector_order->data->countryCode;
		$customer_data['shipping_country'] = $collector_order->data->countryCode;
		$customer_data['billing_email']    = $collector_order->data->customer->email;

		if ( 'BusinessCustomer' == $collector_order->data->customerType ) {
			$customer_data['billing_postcode']  = $collector_order->data->businessCustomer->invoiceAddress->postalCode;
			$customer_data['shipping_postcode'] = $collector_order->data->businessCustomer->deliveryAddress->postalCode;
		} else {
			$customer_data['billing_postcode']  = $collector_order->data->customer->billingAddress->postalCode;
			$customer_data['shipping_postcode'] = $collector_order->data->customer->deliveryAddress->postalCode;
		}

		if ( $customer_data['billing_country'] ) {

			// If country is changed then we need to trigger an cart update in the Collector Checkout
			if ( WC()->customer->get_billing_country() !== $customer_data['billing_country'] ) {
				$update_needed = 'yes';
			}

			// If country is changed then we need to trigger an cart update in the Collector Checkout
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
		wp_die();
	}

	/**
	 * Collector Delivery Module shipping method update - triggered when collectorCheckoutShippingUpdated event is fired
	 */
	public static function update_delivery_module_shipping() {

		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'collector_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		$private_id      = WC()->session->get( 'collector_private_id' );
		$customer_type   = WC()->session->get( 'collector_customer_type' );
		$collector_order = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$collector_order = $collector_order->request();
		$collector_order = json_decode( $collector_order );
		$shipping_title  = $collector_order->data->fees->shipping->description;
		$shipping_id     = $collector_order->data->fees->shipping->id;
		$shipping_price  = $collector_order->data->fees->shipping->unitPrice;
		$shipping_vat    = $collector_order->data->fees->shipping->vat;

		$shipping_data = array(
			'label'        => $shipping_title,
			'shipping_id'  => $shipping_id,
			'cost'         => $shipping_price,
			'shipping_vat' => $shipping_vat,
		);
		WC()->session->set( 'collector_delivery_module_data', $shipping_data );

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		$data = array(
			'shipping_title' => $shipping_title,
			'shipping_price' => $shipping_price,
		);
		wp_send_json_success( $data );
		wp_die();
	}

	public static function add_customer_order_note() {
		WC()->session->set( 'collector_customer_order_note', $_POST['order_note'] );

		wp_send_json_success();
		wp_die();
	}

	public static function get_checkout_thank_you() {
		$order_id           = '';
		$order_id           = sanitize_text_field( $_POST['order_id'] );
		$purchase_status    = sanitize_text_field( $_POST['purchase_status'] );
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$test_mode          = $collector_settings['test_mode'];

		// If something went wrong in get_customer_data() - display a "thank you page light"
		if ( 'not-completed' == $purchase_status ) {
			$public_token = sanitize_text_field( $_POST['public_token'] );
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
		wp_die();
	}

	/**
	 * Get customer data from Collector when payment success url is triggered.
	 */
	public static function get_customer_data() {
		$private_id    = WC()->session->get( 'collector_private_id' );
		$customer_type = WC()->session->get( 'collector_customer_type' );

		// Prevent duplicate orders if confirmation page is reloaded manually by customer
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
			Collector_Checkout::log( 'Payment complete triggered for private id ' . $private_id . ' but _collector_private_id already exist in this order. Redirecting customer to thankyou page.' );
			$return                 = array();
			$return['redirect_url'] = $order->get_checkout_order_received_url();
			wp_send_json_error( $return );
			wp_die();
		}

		Collector_Checkout::log( 'Payment complete triggered for private id ' . $private_id . '. Starting WooCommerce checkout form processing...' );

		$customer_data = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$customer_data = $customer_data->request();
		$decoded_json  = json_decode( $customer_data );

		if ( 'PurchaseCompleted' == $decoded_json->data->status ) {
			// Save the payment method and payment id
			$payment_method = $decoded_json->data->purchase->paymentName;
			$payment_id     = $decoded_json->data->purchase->purchaseIdentifier;
			WC()->session->set( 'collector_payment_method', $payment_method );
			WC()->session->set( 'collector_payment_id', $payment_id );

			// Return the data, customer note and create a nonce.
			$return                  = array();
			$return['customer_data'] = json_decode( $customer_data );
			// Run return through helper function.
			$return['customer_data'] = self::verify_customer_data( $return );
			$return['nonce']         = wp_create_nonce( 'woocommerce-process_checkout' );
			if ( null != WC()->session->get( 'collector_customer_order_note' ) ) {
				$return['order_note'] = WC()->session->get( 'collector_customer_order_note' );
			} else {
				$return['order_note'] = '';
			}
			$return['shipping'] = WC()->session->get( 'collector_chosen_shipping' );
			wp_send_json_success( $return );
			wp_die();
		} else {
			// We didn't get a status PurchaseCompleted from Collector (but the Collector redirectPageUri has been triggered) so we redirect the customer to thank you page
			$return                 = array();
			$url                    = add_query_arg(
				array(
					'purchase-status' => 'not-completed',
					'public-token'    => sanitize_text_field( $_POST['public_token'] ),
				),
				wc_get_endpoint_url( 'order-received', '', get_permalink( wc_get_page_id( 'checkout' ) ) )
			);
			$return['redirect_url'] = $url;
			Collector_Checkout::log( 'Payment complete triggered for private id ' . $private_id . ' but status is not PurchaseCompleted in Collectors system. Current status: ' . var_export( $decoded_json->data->status, true ) . '. Redirecting customer to simplified thankyou page.' );
			wp_send_json_error( $return );
			wp_die();
		}
	}

	public static function update_fragment() {

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( 'false' === $_POST['collector'] ) {
			// Set chosen payment method to first gateway that is not Klarna Checkout for WooCommerce.
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
		/*
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
		/*
		$data = array(
			'fragments' => array(
				'checkout' => $checkout_output,
			),
		);
		*/
		wp_send_json_success( $data );
		wp_die();
	}


	public static function verify_customer_data( $customer_data ) {
		$base_country = WC()->countries->get_base_country();
		if ( 'SE' === $base_country || 'FI' === $base_country ) {
			$fallback_postcode = 11111;
		} elseif ( 'NO' === $base_country || 'DK' === $base_country ) {
			$fallback_postcode = 1111;
		}
		if ( 'PrivateCustomer' === $customer_data['customer_data']->data->customerType ) {
			$shipping_first_name  = isset( $customer_data['customer_data']->data->customer->deliveryAddress->firstName ) ? $customer_data['customer_data']->data->customer->deliveryAddress->firstName : '.';
			$shipping_last_name   = isset( $customer_data['customer_data']->data->customer->deliveryAddress->lastName ) ? $customer_data['customer_data']->data->customer->deliveryAddress->lastName : '.';
			$shipping_address     = isset( $customer_data['customer_data']->data->customer->deliveryAddress->address ) ? $customer_data['customer_data']->data->customer->deliveryAddress->address : '.';
			$shipping_address2    = isset( $customer_data['customer_data']->data->customer->deliveryAddress->address2 ) ? $customer_data['customer_data']->data->customer->deliveryAddress->address2 : '';
			$shipping_postal_code = isset( $customer_data['customer_data']->data->customer->deliveryAddress->postalCode ) ? $customer_data['customer_data']->data->customer->deliveryAddress->postalCode : $fallback_postcode;
			$shipping_city        = isset( $customer_data['customer_data']->data->customer->deliveryAddress->city ) ? $customer_data['customer_data']->data->customer->deliveryAddress->city : '.';

			$billing_first_name  = isset( $customer_data['customer_data']->data->customer->billingAddress->firstName ) ? $customer_data['customer_data']->data->customer->billingAddress->firstName : $shipping_first_name;
			$billing_last_name   = isset( $customer_data['customer_data']->data->customer->billingAddress->lastName ) ? $customer_data['customer_data']->data->customer->billingAddress->lastName : $shipping_last_name;
			$billing_address     = isset( $customer_data['customer_data']->data->customer->billingAddress->address ) ? $customer_data['customer_data']->data->customer->billingAddress->address : $shipping_address;
			$billing_address2    = isset( $customer_data['customer_data']->data->customer->billingAddress->address2 ) ? $customer_data['customer_data']->data->customer->billingAddress->address2 : '';
			$billing_postal_code = isset( $customer_data['customer_data']->data->customer->billingAddress->postalCode ) ? $customer_data['customer_data']->data->customer->billingAddress->postalCode : $shipping_postal_code;
			$billing_city        = isset( $customer_data['customer_data']->data->customer->billingAddress->city ) ? $customer_data['customer_data']->data->customer->billingAddress->city : $shipping_city;

			$billing_company_name  = '';
			$shipping_company_name = '';
			$org_nr                = '';

			$phone = isset( $customer_data['customer_data']->data->customer->mobilePhoneNumber ) ? $customer_data['customer_data']->data->customer->mobilePhoneNumber : '.';
			$email = isset( $customer_data['customer_data']->data->customer->email ) ? $customer_data['customer_data']->data->customer->email : '.';
		} elseif ( 'BusinessCustomer' === $customer_data['customer_data']->data->customerType ) {
			$billing_address      = isset( $customer_data['customer_data']->data->businessCustomer->invoiceAddress->address ) ? $customer_data['customer_data']->data->businessCustomer->invoiceAddress->address : ',';
			$billing_address2     = isset( $customer_data['customer_data']->data->businessCustomer->invoiceAddress->address2 ) ? $customer_data['customer_data']->data->businessCustomer->invoiceAddress->address2 : '';
			$billing_postal_code  = isset( $customer_data['customer_data']->data->businessCustomer->invoiceAddress->postalCode ) ? $customer_data['customer_data']->data->businessCustomer->invoiceAddress->postalCode : $fallback_postcode;
			$billing_city         = isset( $customer_data['customer_data']->data->businessCustomer->invoiceAddress->city ) ? $customer_data['customer_data']->data->businessCustomer->invoiceAddress->city : '.';
			$shipping_address     = isset( $customer_data['customer_data']->data->businessCustomer->deliveryAddress->address ) ? $customer_data['customer_data']->data->businessCustomer->deliveryAddress->address : ',';
			$shipping_address2    = isset( $customer_data['customer_data']->data->businessCustomer->deliveryAddress->address2 ) ? $customer_data['customer_data']->data->businessCustomer->deliveryAddress->address2 : '';
			$shipping_postal_code = isset( $customer_data['customer_data']->data->businessCustomer->deliveryAddress->postalCode ) ? $customer_data['customer_data']->data->businessCustomer->deliveryAddress->postalCode : $fallback_postcode;
			$shipping_city        = isset( $customer_data['customer_data']->data->businessCustomer->deliveryAddress->city ) ? $customer_data['customer_data']->data->businessCustomer->deliveryAddress->city : '.';

			$billing_first_name    = isset( $customer_data['customer_data']->data->businessCustomer->firstName ) ? $customer_data['customer_data']->data->businessCustomer->firstName : '.';
			$billing_last_name     = isset( $customer_data['customer_data']->data->businessCustomer->lastName ) ? $customer_data['customer_data']->data->businessCustomer->lastName : '.';
			$billing_company_name  = isset( $customer_data['customer_data']->data->businessCustomer->companyName ) ? $customer_data['customer_data']->data->businessCustomer->companyName : '.';
			$shipping_first_name   = isset( $customer_data['customer_data']->data->businessCustomer->firstName ) ? $customer_data['customer_data']->data->businessCustomer->firstName : '.';
			$shipping_last_name    = isset( $customer_data['customer_data']->data->businessCustomer->lastName ) ? $customer_data['customer_data']->data->businessCustomer->lastName : '.';
			$shipping_company_name = isset( $customer_data['customer_data']->data->businessCustomer->deliveryAddress->companyName ) ? $customer_data['customer_data']->data->businessCustomer->deliveryAddress->companyName : $customer_data['customer_data']->data->businessCustomer->companyName;
			$phone                 = isset( $customer_data['customer_data']->data->businessCustomer->mobilePhoneNumber ) ? $customer_data['customer_data']->data->businessCustomer->mobilePhoneNumber : '.';
			$email                 = isset( $customer_data['customer_data']->data->businessCustomer->email ) ? $customer_data['customer_data']->data->businessCustomer->email : '.';

			$org_nr            = isset( $customer_data['customer_data']->data->businessCustomer->organizationNumber ) ? $customer_data['customer_data']->data->businessCustomer->organizationNumber : '.';
			$invoice_reference = isset( $customer_data['customer_data']->data->businessCustomer->invoiceReference ) ? $customer_data['customer_data']->data->businessCustomer->invoiceReference : '.';

			WC()->session->set( 'collector_org_nr', $org_nr );
			WC()->session->set( 'collector_invoice_reference', $invoice_reference );
		}
		$countryCode = isset( $customer_data['customer_data']->data->countryCode ) ? $customer_data['customer_data']->data->countryCode : $base_country;

		$customer_information = array(
			'billingFirstName'    => $billing_first_name,
			'billingLastName'     => $billing_last_name,
			'billingCompanyName'  => $billing_company_name,
			'billingAddress'      => $billing_address,
			'billingAddress2'     => $billing_address2,
			'billingPostalCode'   => $billing_postal_code,
			'billingCity'         => $billing_city,
			'shippingFirstName'   => $shipping_first_name,
			'shippingLastName'    => $shipping_last_name,
			'shippingCompanyName' => $shipping_company_name,
			'shippingAddress'     => $shipping_address,
			'shippingAddress2'    => $shipping_address2,
			'shippingPostalCode'  => $shipping_postal_code,
			'shippingCity'        => $shipping_city,
			'phone'               => $phone,
			'email'               => $email,
			'countryCode'         => $countryCode,
			'orgNr'               => $org_nr,
		);
		$empty_fields         = array();
		$errors               = 0;
		foreach ( $customer_information as $key => $value ) {
			if ( '.' === $value ) {
				array_push( $empty_fields, $key );
				$errors = 1;
			}
		}
		if ( 1 === $errors ) {
			WC()->session->set( 'collector_empty_fields', $empty_fields );
		}
		return $customer_information;
	}

	public static function checkout_error() {
		Collector_Checkout::log( 'Starting Create Order Fallback creation...' );
		$customer_type = WC()->session->get( 'collector_customer_type' );
		$private_id    = WC()->session->get( 'collector_private_id' );

		// Prevent duplicate orders if confirmation page is reloaded manually by customer.
		$collector_public_token = sanitize_key( $_POST['public_token'] );
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
			Collector_Checkout::log( 'Checkout error triggered but _collector_public_token already exist in this order: ' . $order_id_match );
			$order        = wc_get_order( $order_id_match );
			$redirect_url = $order->get_checkout_order_received_url();
			$return       = array( 'redirect_url' => $redirect_url );
			wp_send_json_success( $return );
			wp_die();
			exit;
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

		// Maybe add invoice fee to order
		if ( 'DirectInvoice' == WC()->session->get( 'collector_payment_method' ) ) {
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

		// Add payment method
		$create_order->add_order_payment_method( $order );

		// Make sure to run Sequential Order numbers if plugin exsists
		// @Todo - Se i we can run action woocommerce_checkout_update_order_meta in this process
		// so Sequential order numbers and other plugins can do their stuff themselves
		if ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
			$sequential = new WC_Seq_Order_Number_Pro();
			$sequential->set_sequential_order_number( $order_id );
		} elseif ( class_exists( 'WC_Seq_Order_Number' ) ) {
			$sequential = new WC_Seq_Order_Number();
			$sequential->set_sequential_order_number( $order_id, get_post( $order_id ) );
		}

		// Calculate order totals
		$create_order->calculate_order_totals( $order );

		// Update the Collector Order with the Order ID
		$create_order->update_order_reference_in_collector( $order, $customer_type, $private_id );

		// Add order note
		if ( ! empty( $_POST['error_message'] ) ) { // Input var okay.
			$error_message = 'Error message: ' . sanitize_text_field( trim( $_POST['error_message'] ) );
		} else {
			$error_message = 'Error message could not be retreived';
		}
		$note = sprintf( __( 'This order was made as a fallback due to an error in the checkout (%s). Please verify the order with Collector.', 'collector-checkout-for-woocommerce' ), $error_message );
		$order->add_order_note( $note );
		$order->set_status( 'on-hold' );
		$order->save();

		$redirect_url = $order->get_checkout_order_received_url();
		$return       = array( 'redirect_url' => $redirect_url );
		wp_send_json_success( $return );
		wp_die();
	}
}
Collector_Checkout_Ajax_Calls::init();
