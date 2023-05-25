<?php
/**
 * Functions file for the plugin.
 *
 * @package  Collector_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Echoes Collector Checkout iframe snippet.
 */
function collector_wc_show_snippet() {

	// Don't display the checkout on confirmation page.
	if ( is_collector_confirmation() ) {
		return;
	}

	if ( 'NOK' === get_woocommerce_currency() ) {
		$locale = 'nb-NO';
	} elseif ( 'DKK' === get_woocommerce_currency() ) {
		$locale = 'da-DK';
	} elseif ( 'EUR' === get_woocommerce_currency() ) {
		$locale = 'fi-FI';
	} else {
		if ( 'sv_SE' === get_locale() ) {
			$locale = 'sv-SE';
		} else {
			$locale = 'en-SE';
		}
	}

	$collector_settings       = get_option( 'woocommerce_collector_checkout_settings' );
	$test_mode                = $collector_settings['test_mode'];
	$data_action_color_button = isset( $collector_settings['checkout_button_color'] ) && ! empty( $collector_settings['checkout_button_color'] ) ? ' data-action-color="' . $collector_settings['checkout_button_color'] . '"' : '';

	if ( 'yes' === $test_mode ) {
		$url = 'https://checkout-uat.collector.se/collector-checkout-loader.js';
	} else {
		$url = 'https://checkout.collector.se/collector-checkout-loader.js';
	}

	$customer_type = WC()->session->get( 'collector_customer_type' );

	if ( empty( $customer_type ) ) {
		$customer_type = wc_collector_get_default_customer_type();
		WC()->session->set( 'collector_customer_type', $customer_type );
	}

	$public_token       = WC()->session->get( 'collector_public_token' );
	$collector_currency = WC()->session->get( 'collector_currency' );
	$private_id         = WC()->session->get( 'collector_private_id' );

	if ( empty( $public_token ) || empty( $private_id ) || get_woocommerce_currency() !== $collector_currency ) {
		// Get a new public token from Collector.
		if ( walley_use_new_api() ) {
			$collector_order = CCO_WC()->api->initialize_walley_checkout( array( 'customer_type' => $customer_type ) );
		} else {
			$init_checkout   = new Collector_Checkout_Requests_Initialize_Checkout( $customer_type );
			$collector_order = $init_checkout->request();
		}

		if ( is_wp_error( $collector_order ) ) {
			$return = '<ul class="woocommerce-error"><li>' . sprintf( '%s <a href="%s" class="button wc-forward">%s</a>', __( 'Could not connect to Walley. Error message: ', 'collector-checkout-for-woocommerce' ) . $collector_order->get_error_message(), wc_get_checkout_url(), __( 'Try again', 'collector-checkout-for-woocommerce' ) ) . '</li></ul>';
		} else {
			WC()->session->set( 'collector_public_token', $collector_order['data']['publicToken'] );
			WC()->session->set( 'collector_private_id', $collector_order['data']['privateId'] );
			WC()->session->set( 'collector_currency', get_woocommerce_currency() );

			$public_token = $collector_order['data']['publicToken'];
			$output       = array(
				'publicToken'   => $public_token,
				'test_mode'     => $test_mode,
				'customer_type' => $customer_type,
			);

			echo( "<script>console.log('Collector: " . wp_json_encode( $output ) . "');</script>" );
			$return = '<div id="collector-container"><script src="' . $url . '" data-lang="' . $locale . '" data-token="' . $public_token . '" data-variant="' . $customer_type . '"' . $data_action_color_button . ' ></script></div>'; // phpcs:ignore
		}
	} else {

		// Check if purchase was completed, if it was redirect customer to thankyou page.
		// Use new or old API.
		if ( walley_use_new_api() ) {
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

		// If the update results in a Purchase_Completed response, let's try to redirect the customer to thank you page.
		if ( is_wp_error( $collector_order ) ) {
			return;
		}

		if ( isset( $collector_order['data']['status'] ) && 'PurchaseCompleted' === $collector_order['data']['status'] ) {
			$order_id = wc_collector_get_order_id_by_private_id( $private_id );

			if ( ! empty( $order_id ) ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					CCO_WC()->logger::log( 'Trying to display checkout but status is PurchaseCompleted. Private id ' . $private_id . ', exist in order id ' . $order_id . '. Redirecting customer to thankyou page.' );
					wp_safe_redirect( $order->get_checkout_order_received_url() );
					exit;
				}
			} else {
				CCO_WC()->logger::log( 'Trying to display checkout but status is PurchaseCompleted. Private id ' . $private_id . '. No correlating order id can be found.' );
			}
		}

		$output = array(
			'publicToken'   => $public_token,
			'test_mode'     => $test_mode,
			'customer_type' => $customer_type,
		);
		echo( "<script>console.log('Collector: " . wp_json_encode( $output ) . "');</script>" );
		$return = '<div id="collector-container"><script src="' . $url . '" data-lang="' . $locale . '" data-token="' . $public_token . '" data-variant="' . $customer_type . '"' . $data_action_color_button . ' ></script></div>'; // phpcs:ignore
	}
	echo wp_kses( $return, wc_collector_allowed_tags() );
}

/**
 * Unset Collector public token and private id
 */
function wc_collector_unset_sessions() {
	if ( method_exists( WC()->session, '__unset' ) ) {
		if ( WC()->session->get( 'collector_public_token' ) ) {
			WC()->session->__unset( 'collector_public_token' );
		}
		if ( WC()->session->get( 'collector_private_id' ) ) {
			WC()->session->__unset( 'collector_private_id' );
		}
		if ( WC()->session->get( 'collector_currency' ) ) {
			WC()->session->__unset( 'collector_currency' );
		}
	}
}

/**
 * Shows select another payment method button in Collector Checkout page.
 */
function collector_wc_show_another_gateway_button() {
	$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
	if ( count( $available_payment_gateways ) > 1 ) {
		?>
		<a class="button" id="collector_change_payment_method" href="#"><?php esc_html_e( 'Select another payment method', 'collector-checkout-for-woocommerce' ); ?></a>
		<?php
	}
}

/**
 * Shows B2C/B2B switcher in Collector Checkout page.
 * Only available for Checkout version 1.0.
 */
function collector_wc_show_customer_type_switcher() {
	if ( 'collector-b2c-b2b' === wc_collector_get_available_customer_types() ) {
		?>
	<ul class="collector-checkout-tabs">
		<li class="tab-link current" data-tab="b2c"><?php esc_html_e( 'Person', 'collector-checkout-for-woocommerce' ); ?></li>
		<li class="tab-link" data-tab="b2b"><?php esc_html_e( 'Company', 'collector-checkout-for-woocommerce' ); ?></li>
	</ul>
		<?php
	}
}

/**
 * Unset Collector public token and private id
 *
 * @param string $order_id WooCommerce order id.
 * @param string $product_id WooCommerce product id.
 */
function wc_collector_add_invoice_fee_to_order( $order_id, $product_id ) {
	$result  = false;
	$order   = wc_get_order( $order_id );
	$product = wc_get_product( $product_id );

	if ( is_object( $product ) && is_object( $order ) ) {
		$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );
		$price            = wc_get_price_excluding_tax( $product );

		if ( $product->is_taxable() ) {
			$product_tax = true;
		} else {
			$product_tax = false;
		}

		$_tax      = new WC_Tax();
		$tmp_rates = $_tax->get_base_tax_rates( $product->get_tax_class() );
		$_vat      = array_shift( $tmp_rates );// Get the rate
		// Check what kind of tax rate we have.
		if ( $product->is_taxable() && isset( $_vat['rate'] ) ) {
			$vat_rate = round( $_vat['rate'] );
		} else {
			// if empty, set 0% as rate.
			$vat_rate = 0;
		}

		$args = array(
			'name'      => $product->get_title(),
			'tax_class' => $product_tax ? $product->get_tax_class() : 0,
			'total'     => $price,
			'total_tax' => $vat_rate,
			'taxes'     => array(
				'total' => array(),
			),
		);
		$fee  = new WC_Order_Item_Fee();
		$fee->set_props( $args );
		$fee_result = $order->add_item( $fee );

		if ( false === $fee_result ) {
			$order->add_order_note( __( 'Unable to add Walley Checkout Invoice Fee to the order.', 'collector-checkout-for-woocommerce' ) );
		}
		$result = $order->calculate_totals( true );
	}
	return $result;
}

/**
 * Checking if it is Collector confirmation page.
 *
 * @return boolean
 */
function is_collector_confirmation() {
	$payment_successful = filter_input( INPUT_GET, 'payment_successful', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	$public_token       = filter_input( INPUT_GET, 'payment_successful', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	if ( '1' === $payment_successful && ! empty( $public_token ) ) {
		return true;
	}
	return false;
}

/**
 * Removes the database table row data.
 *
 * @param string $private_id Collector private id.
 * @return void
 */
function remove_collector_db_row_data( $private_id ) {
	Collector_Checkout_DB::delete_data_entry( $private_id );
}

/**
 * Checking if Collector Delivery Module is active.
 *
 * @param string $currency selected currency.
 *
 * @return boolean
 */
function is_collector_delivery_module( $currency = false ) {
	$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
	$currency           = ! false === $currency ? $currency : get_woocommerce_currency();
	switch ( $currency ) {
		case 'SEK':
			$delivery_module = isset( $collector_settings['collector_delivery_module_se'] ) ? $collector_settings['collector_delivery_module_se'] : 'no';
			break;
		case 'NOK':
			$delivery_module = isset( $collector_settings['collector_delivery_module_no'] ) ? $collector_settings['collector_delivery_module_no'] : 'no';
			break;
		case 'DKK':
			$delivery_module = isset( $collector_settings['collector_delivery_module_dk'] ) ? $collector_settings['collector_delivery_module_dk'] : 'no';
			break;
		case 'EUR':
			$delivery_module = isset( $collector_settings['collector_delivery_module_fi'] ) ? $collector_settings['collector_delivery_module_fi'] : 'no';
			break;
		default:
			$delivery_module = 'no';
			break;
	}
	return $delivery_module;
}

/**
 * Format customer address.
 *
 * @param array $collector_order Collector order.
 * @return array
 */
function wc_collector_verify_customer_data( $collector_order ) {
	$base_country = WC()->countries->get_base_country();
	if ( 'SE' === $base_country || 'FI' === $base_country ) {
		$fallback_postcode = 11111;
	} elseif ( 'NO' === $base_country || 'DK' === $base_country ) {
		$fallback_postcode = 1111;
	}
	if ( 'PrivateCustomer' === $collector_order['data']['customerType'] ) {
		$shipping_first_name  = isset( $collector_order['data']['customer']['deliveryAddress']['firstName'] ) ? $collector_order['data']['customer']['deliveryAddress']['firstName'] : '.';
		$shipping_last_name   = isset( $collector_order['data']['customer']['deliveryAddress']['lastName'] ) ? $collector_order['data']['customer']['deliveryAddress']['lastName'] : '.';
		$shipping_address     = isset( $collector_order['data']['customer']['deliveryAddress']['address'] ) ? $collector_order['data']['customer']['deliveryAddress']['address'] : '.';
		$shipping_address2    = isset( $collector_order['data']['customer']['deliveryAddress']['address2'] ) ? $collector_order['data']['customer']['deliveryAddress']['address2'] : '';
		$shipping_postal_code = isset( $collector_order['data']['customer']['deliveryAddress']['postalCode'] ) ? $collector_order['data']['customer']['deliveryAddress']['postalCode'] : $fallback_postcode;
		$shipping_city        = isset( $collector_order['data']['customer']['deliveryAddress']['city'] ) ? $collector_order['data']['customer']['deliveryAddress']['city'] : '.';

		$billing_first_name  = isset( $collector_order['data']['customer']['billingAddress']['firstName'] ) ? $collector_order['data']['customer']['billingAddress']['firstName'] : $shipping_first_name;
		$billing_last_name   = isset( $collector_order['data']['customer']['billingAddress']['lastName'] ) ? $collector_order['data']['customer']['billingAddress']['lastName'] : $shipping_last_name;
		$billing_address     = isset( $collector_order['data']['customer']['billingAddress']['address'] ) ? $collector_order['data']['customer']['billingAddress']['address'] : $shipping_address;
		$billing_address2    = isset( $collector_order['data']['customer']['billingAddress']['address2'] ) ? $collector_order['data']['customer']['billingAddress']['address2'] : '';
		$billing_postal_code = isset( $collector_order['data']['customer']['billingAddress']['postalCode'] ) ? $collector_order['data']['customer']['billingAddress']['postalCode'] : $shipping_postal_code;
		$billing_city        = isset( $collector_order['data']['customer']['billingAddress']['city'] ) ? $collector_order['data']['customer']['billingAddress']['city'] : $shipping_city;

		$billing_company_name  = '';
		$shipping_company_name = '';
		$org_nr                = '';

		$phone = isset( $collector_order['data']['customer']['mobilePhoneNumber'] ) ? $collector_order['data']['customer']['mobilePhoneNumber'] : '.';
		$email = isset( $collector_order['data']['customer']['email'] ) ? $collector_order['data']['customer']['email'] : '.';
	} elseif ( 'BusinessCustomer' === $collector_order['data']['customerType'] ) {
		$billing_address      = isset( $collector_order['data']['businessCustomer']['invoiceAddress']['address'] ) ? $collector_order['data']['businessCustomer']['invoiceAddress']['address'] : ',';
		$billing_address2     = isset( $collector_order['data']['businessCustomer']['invoiceAddress']['address2'] ) ? $collector_order['data']['businessCustomer']['invoiceAddress']['address2'] : '';
		$billing_postal_code  = isset( $collector_order['data']['businessCustomer']['invoiceAddress']['postalCode'] ) ? $collector_order['data']['businessCustomer']['invoiceAddress']['postalCode'] : $fallback_postcode;
		$billing_city         = isset( $collector_order['data']['businessCustomer']['invoiceAddress']['city'] ) ? $collector_order['data']['businessCustomer']['invoiceAddress']['city'] : '.';
		$shipping_address     = isset( $collector_order['data']['businessCustomer']['deliveryAddress']['address'] ) ? $collector_order['data']['businessCustomer']['deliveryAddress']['address'] : ',';
		$shipping_address2    = isset( $collector_order['data']['businessCustomer']['deliveryAddress']['address2'] ) ? $collector_order['data']['businessCustomer']['deliveryAddress']['address2'] : '';
		$shipping_postal_code = isset( $collector_order['data']['businessCustomer']['deliveryAddress']['postalCode'] ) ? $collector_order['data']['businessCustomer']['deliveryAddress']['postalCode'] : $fallback_postcode;
		$shipping_city        = isset( $collector_order['data']['businessCustomer']['deliveryAddress']['city'] ) ? $collector_order['data']['businessCustomer']['deliveryAddress']['city'] : '.';

		$billing_first_name    = isset( $collector_order['data']['businessCustomer']['firstName'] ) ? $collector_order['data']['businessCustomer']['firstName'] : '.';
		$billing_last_name     = isset( $collector_order['data']['businessCustomer']['lastName'] ) ? $collector_order['data']['businessCustomer']['lastName'] : '.';
		$billing_company_name  = isset( $collector_order['data']['businessCustomer']['companyName'] ) ? $collector_order['data']['businessCustomer']['companyName'] : '.';
		$shipping_first_name   = isset( $collector_order['data']['businessCustomer']['firstName'] ) ? $collector_order['data']['businessCustomer']['firstName'] : '.';
		$shipping_last_name    = isset( $collector_order['data']['businessCustomer']['lastName'] ) ? $collector_order['data']['businessCustomer']['lastName'] : '.';
		$shipping_company_name = isset( $collector_order['data']['businessCustomer']['deliveryAddress']['companyName'] ) ? $collector_order['data']['businessCustomer']['deliveryAddress']['companyName'] : $collector_order['data']['businessCustomer']['companyName'];
		$phone                 = isset( $collector_order['data']['businessCustomer']['mobilePhoneNumber'] ) ? $collector_order['data']['businessCustomer']['mobilePhoneNumber'] : '.';
		$email                 = isset( $collector_order['data']['businessCustomer']['email'] ) ? $collector_order['data']['businessCustomer']['email'] : '.';

		$org_nr            = isset( $collector_order['data']['businessCustomer']['organizationNumber'] ) ? $collector_order['data']['businessCustomer']['organizationNumber'] : '.';
		$invoice_reference = isset( $collector_order['data']['businessCustomer']['invoiceReference'] ) ? $collector_order['data']['businessCustomer']['invoiceReference'] : '.';

		if ( isset( WC()->session ) && method_exists( WC()->session, 'set' ) ) {
			WC()->session->set( 'collector_org_nr', $org_nr );
			WC()->session->set( 'collector_invoice_reference', $invoice_reference );
		}
	}
	$country_code = isset( $collector_order['data']['countryCode'] ) ? $collector_order['data']['countryCode'] : $base_country;

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
		'countryCode'         => $country_code,
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

/**
 * Finds an Order ID based on a private ID (the Collector session id during purchase).
 *
 * @param string $private_id Collector session id saved as _collector_private_id ID in WC order.
 * @return int The ID of an order, or 0 if the order could not be found.
 */
function wc_collector_get_order_id_by_private_id( $private_id = null ) {

	if ( empty( $private_id ) ) {
		return false;
	}

	$query_args = array(
		'fields'      => 'ids',
		'post_type'   => wc_get_order_types(),
		'post_status' => array_keys( wc_get_order_statuses() ),
		'meta_key'    => '_collector_private_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'meta_value'  => sanitize_text_field( wp_unslash( $private_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'date_query'  => array(
			array(
				'after' => '120 day ago',
			),
		),
	);

	$orders = get_posts( $query_args );

	if ( $orders ) {
		$order_id = $orders[0];
	} else {
		$order_id = 0;
	}

	return $order_id;
}

/**
 * Finds all Orders based on a private ID (the Collector session id during purchase).
 *
 * @param string $private_id Collector session id saved as _collector_private_id ID in WC order.
 * @return array WC orders that have the specific private id saved as meta.
 */
function wc_collector_get_orders_by_private_id( $private_id = null ) {

	if ( empty( $private_id ) ) {
		return array();
	}

	$query_args = array(
		'fields'      => 'ids',
		'post_type'   => wc_get_order_types(),
		'post_status' => array_keys( wc_get_order_statuses() ),
		'meta_key'    => '_collector_private_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'meta_value'  => sanitize_text_field( wp_unslash( $private_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'date_query'  => array(
			array(
				'after' => '120 day ago',
			),
		),
	);

	$order_ids = get_posts( $query_args );

	return $order_ids;
}

/**
 * Confirm order
 *
 * @param string $order_id WC order id.
 * @param string $private_id Collector session id saved as _collector_private_id ID in WC order.
 */
function wc_collector_confirm_order( $order_id, $private_id = null ) {
	$order = wc_get_order( $order_id );

	if ( empty( $private_id ) ) {
		$private_id = get_post_meta( $order_id, '_collector_private_id', true );
	}

	$customer_type = get_post_meta( $order_id, '_collector_customer_type', true );
	if ( empty( $customer_type ) ) {
		$customer_type = 'b2c';
	}

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
		$order->add_order_note( __( 'Could not retreive Walley order during wc_collector_confirm_order function.', 'collector-checkout-for-woocommerce' ) );
		return;
	}

	$payment_status  = $collector_order['data']['purchase']['result'];
	$payment_method  = $collector_order['data']['purchase']['paymentName'];
	$payment_id      = $collector_order['data']['purchase']['purchaseIdentifier'];
	$walley_order_id = $collector_order['data']['order']['orderId'];

	// Check if we need to update reference in collectors system.
	if ( empty( $collector_order['data']['reference'] ) ) {

		// Use new or old API.
		if ( walley_use_new_api() ) {
			$update_reference = CCO_WC()->api->set_order_reference_in_walley(
				array(
					'order_id'      => $order_id,
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

	// Maybe add invoice fee to order.
	if ( 'DirectInvoice' === $payment_method ) {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$product_id         = $collector_settings['collector_invoice_fee'];
		if ( $product_id ) {
			wc_collector_add_invoice_fee_to_order( $order_id, $product_id );
		}
	}

	update_post_meta( $order_id, '_collector_payment_method', $payment_method );
	update_post_meta( $order_id, '_collector_payment_id', $payment_id );
	update_post_meta( $order_id, '_collector_order_id', sanitize_key( $walley_order_id ) );
	wc_collector_save_shipping_reference_to_order( $order_id, $collector_order );

	// Tie this order to a user if we have one.
	if ( email_exists( $collector_order['data']['customer']['email'] ) ) {
		$user    = get_user_by( 'email', $collector_order['data']['customer']['email'] );
		$user_id = $user->ID;
		update_post_meta( $order_id, '_customer_user', $user_id );
	}

	if ( 'Preliminary' === $payment_status || 'Completed' === $payment_status ) {
		$order->payment_complete( $payment_id );
	} elseif ( 'Signing' === $payment_status ) {
		$order->add_order_note( __( 'Order is waiting for electronic signing by customer. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $payment_id );
		update_post_meta( $order_id, '_transaction_id', $payment_id );
		$order->update_status( 'on-hold' );
	} else {
		$order->add_order_note( __( 'Order is PENDING APPROVAL by Collector. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $payment_id );
		update_post_meta( $order_id, '_transaction_id', $payment_id );
		$order->update_status( 'on-hold' );
	}

	// Translators: Collector Payment method.
	$order->add_order_note( sprintf( __( 'Purchase via %s', 'collector-checkout-for-woocommerce' ), wc_collector_get_payment_method_name( $payment_method ) ) );
}

/**
 * Saving shipping reference to order
 *
 * @param int   $order_id WooCommerce order id.
 * @param array $collector_order Collector payment data.
 * @return void
 */
function wc_collector_save_shipping_reference_to_order( $order_id, $collector_order ) {
	$order_items = $collector_order['data']['order']['items'] ?? array();
	foreach ( $order_items as $item ) {
		if ( strpos( $item['id'], 'shipping|' ) !== false ) {
			update_post_meta( $order_id, '_collector_shipping_reference', $item['id'] );
		}
	}
}

/**
 * Function wc_collector_allowed_tags.
 * Set which tags are allowed in the content printed by the plugin.
 *
 * @return array
 */
function wc_collector_allowed_tags() {

	$allowed_tags = array(
		'a'          => array(
			'class' => array(),
			'href'  => array(),
			'rel'   => array(),
			'title' => array(),
		),
		'abbr'       => array(
			'title' => array(),
		),
		'b'          => array(),
		'blockquote' => array(
			'cite' => array(),
		),
		'button'     => array(
			'onclick'    => array(),
			'class'      => array(),
			'type'       => array(),
			'aria-label' => array(),
		),
		'cite'       => array(
			'title' => array(),
		),
		'code'       => array(),
		'del'        => array(
			'datetime' => array(),
			'title'    => array(),
		),
		'dd'         => array(),
		'div'        => array(
			'class'             => array(),
			'title'             => array(),
			'style'             => array(),
			'id'                => array(),
			'data-id'           => array(),
			'data-redirect-url' => array(),
			'onclick'           => array(),
		),
		'dl'         => array(),
		'dt'         => array(),
		'em'         => array(),
		'h1'         => array(),
		'h2'         => array(),
		'h3'         => array(),
		'h4'         => array(),
		'h5'         => array(),
		'h6'         => array(),
		'hr'         => array(
			'class' => array(),
		),
		'i'          => array(),
		'img'        => array(
			'alt'     => array(),
			'class'   => array(),
			'height'  => array(),
			'src'     => array(),
			'width'   => array(),
			'onclick' => array(),
		),
		'li'         => array(
			'class' => array(),
		),
		'ol'         => array(
			'class' => array(),
		),
		'p'          => array(
			'class' => array(),
		),
		'q'          => array(
			'cite'  => array(),
			'title' => array(),
		),
		'span'       => array(
			'class'       => array(),
			'title'       => array(),
			'style'       => array(),
			'onclick'     => array(),
			'aria-hidden' => array(),
		),
		'strike'     => array(),
		'strong'     => array(),
		'ul'         => array(
			'class' => array(),
		),
		'style'      => array(
			'types' => array(),
		),
		'table'      => array(
			'class' => array(),
			'id'    => array(),
		),
		'tbody'      => array(
			'class' => array(),
			'id'    => array(),
		),
		'tr'         => array(
			'class' => array(),
			'id'    => array(),
		),
		'td'         => array(
			'class' => array(),
			'id'    => array(),
		),
		'iframe'     => array(
			'src'             => array(),
			'height'          => array(),
			'width'           => array(),
			'frameborder'     => array(),
			'allowfullscreen' => array(),
		),
		'script'     => array(
			'type'              => array(),
			'src'               => array(),
			'async'             => array(),
			'data-lang'         => array(),
			'data-version'      => array(),
			'data-token'        => array(),
			'data-variant'      => array(),
			'data-action-color' => array(),
		),
	);

	return apply_filters( 'coc_allowed_tags', $allowed_tags );
}


	/**
	 * Check order totals
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $collector_order The Collector order.
	 *
	 * @return bool TRUE If the WC and Collector total amounts match, otherwise FALSE.
	 */
function cco_check_order_totals( $order, $collector_order ) {
	// Check order total and compare it with Woo.
	$woo_order_total       = intval( round( $order->get_total() * 100, 2 ) );
	$collector_order_total = intval( round( $collector_order['data']['order']['totalAmount'] * 100, 2 ) );
	if ( $woo_order_total > $collector_order_total && ( $woo_order_total - $collector_order_total ) > 3 ) {
		// translators: Order total.
		$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review. WooCommerce order total and Walley order total do not match. Walley order total: %s.', 'collector-checkout-for-woocommerce' ), $collector_order_total ) );
		CCO_WC()->logger::log( 'Order total mismatch in order:' . $order->get_order_number() . '. Woo order total: ' . $woo_order_total . '. Collector order total: ' . $collector_order_total );
		return false;

	} elseif ( $collector_order_total > $woo_order_total && ( $collector_order_total - $woo_order_total ) > 3 ) {
		// translators: Order total notice.
		$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review. WooCommerce order total and Walley order total do not match. Walley order total: %s.', 'collector-checkout-for-woocommerce' ), $collector_order_total ) );
		CCO_WC()->logger::log( 'Order total mismatch in order:' . $order->get_order_number() . '. Woo order total: ' . $woo_order_total . '. Collector order total: ' . $collector_order_total );
		return false;

	}

	return true;
}


/**
 * Get shipping data from a collector order when using shipping in iframe.
 *
 * @param array $collector_order The collector order array.
 * @return array
 */
function coc_get_shipping_data( $collector_order ) {
	$shipping_data = array();
	$shipping      = $collector_order['data']['shipping'];
	if ( isset( $shipping['shipments'] ) ) {
		// Handle Walley Custom Delivery Adapter.
		foreach ( $shipping['shipments'] as $shipment ) {
			$cost = $shipment['shippingChoice']['fee'];

			$shipment_options = $shipment['shippingChoice']['options'] ?? array();
			foreach ( $shipment_options as $option ) {
				$cost += $option['fee'] ?? 0;
			}

			$shipping_data[] = array(
				'label'        => $shipment['shippingChoice']['name'],
				'shipping_id'  => $shipment['shippingChoice']['id'],
				'cost'         => $cost,
				'shipping_vat' => $shipment['shippingChoice']['metadata']['tax_rate'] ?? null,
			);
		}
	} else {
		// Default handling of the Walley Shipping Module.
		$shipping_data = array(
			'label'        => $shipping['carrierName'],
			'shipping_id'  => $shipping['carrierId'],
			'cost'         => $shipping['shippingFee'],
			'shipping_vat' => $collector_order['data']['fees']['shipping']['vat'],
		);
	}

	return $shipping_data;
}

/**
 * Prints error message as notices.
 *
 * Sometimes an error message cannot be printed (e.g., in a cronjob environment) where there is
 * no front end to display the error message, or otherwise irrelevant for a human. For that reason, we have to check if the print functions are undefined.
 *
 * @param WP_Error $wp_error A WordPress error object.
 * @return void
 */
function walley_print_error_message( $wp_error ) {
	if ( is_ajax() ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			$print = 'wc_add_notice';
		}
	} else {
		if ( function_exists( 'wc_print_notice' ) ) {
			$print = 'wc_print_notice';
		}
	}

	if ( ! isset( $print ) ) {
		return;
	}

	foreach ( $wp_error->get_error_messages() as $error ) {
		$message = is_array( $error ) ? implode( ' ', $error ) : $error;
		$print( $message, 'error' );
	}
}

/**
 * Use new Walley API or not.
 *
 * @return bool
 */
function walley_use_new_api() {
	$collector_settings   = get_option( 'woocommerce_collector_checkout_settings' );
	$walley_api_client_id = $collector_settings['walley_api_client_id'] ?? '';
	$walley_api_secret    = $collector_settings['walley_api_secret'] ?? '';

	if ( ! empty( $walley_api_client_id ) && ! empty( $walley_api_secret ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Save Walley order data to transient in WordPress.
 *
 * @param array $walley_order the returned Walley order data.
 * @return void
 */
function walley_save_order_data_to_transient( $walley_order ) {
	$walley_order_status_data = array(
		'status'       => $walley_order['status'] ?? '',
		'total_amount' => $walley_order['total_amount'] ?? '',
		'currency'     => $walley_order['currency'] ?? '',
	);
	set_transient( "walley_order_status_{$walley_order['order_id']}", $walley_order_status_data, 30 );
}

/**
 * Finds an Order ID based on a Walley public token.
 *
 * @param string $public_token Walley public token.
 * @return int The ID of an order, or 0 if the order could not be found.
 */
function walley_get_order_id_by_public_token( $public_token ) {
	$query_args = array(
		'fields'      => 'ids',
		'post_type'   => wc_get_order_types(),
		'post_status' => array_keys( wc_get_order_statuses() ),
		'meta_key'    => '_collector_public_token', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'meta_value'  => sanitize_text_field( wp_unslash( $public_token ) ), // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		'date_query'  => array(
			array(
				'after' => '120 day ago',
			),
		),
	);

	$orders = get_posts( $query_args );

	if ( $orders ) {
		$order_id = $orders[0];
	} else {
		$order_id = 0;
	}

	return $order_id;
}

/**
 * Confirm order
 *
 * @param string $order_id WC order id.
 * @param string $private_id Collector session id saved as _collector_private_id ID in WC order.
 */
function walley_confirm_order( $order_id, $private_id = null ) {
	$order = wc_get_order( $order_id );

	// Check if the order has been confirmed already.
	if ( ! empty( $order->get_date_paid() ) ) {
		return false;
	}

	if ( empty( $private_id ) ) {
		$private_id = get_post_meta( $order_id, '_collector_private_id', true );
	}

	$customer_type = get_post_meta( $order_id, '_collector_customer_type', true );
	if ( empty( $customer_type ) ) {
		$customer_type = 'b2c';
	}

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
		$order->add_order_note( __( 'Could not retreive Walley order during walley_confirm_order function.', 'collector-checkout-for-woocommerce' ) );
		return false;
	}

	$payment_status  = $collector_order['data']['purchase']['result'];
	$payment_method  = $collector_order['data']['purchase']['paymentName'];
	$payment_id      = $collector_order['data']['purchase']['purchaseIdentifier'];
	$walley_order_id = $collector_order['data']['order']['orderId'];

	// Check if we need to update reference in collectors system.
	if ( empty( $collector_order['data']['reference'] ) ) {

		// Use new or old API.
		if ( walley_use_new_api() ) {
			$update_reference = CCO_WC()->api->set_order_reference_in_walley(
				array(
					'order_id'      => $order_id,
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

	// Maybe add invoice fee to order.
	if ( 'DirectInvoice' === $payment_method ) {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$product_id         = $collector_settings['collector_invoice_fee'];
		if ( $product_id ) {
			wc_collector_add_invoice_fee_to_order( $order_id, $product_id );
		}
	}

	update_post_meta( $order_id, '_collector_payment_method', $payment_method );
	update_post_meta( $order_id, '_collector_payment_id', $payment_id );
	update_post_meta( $order_id, '_collector_order_id', sanitize_key( $walley_order_id ) );

	wc_collector_save_shipping_reference_to_order( $order_id, $collector_order );

	// Save shipping data.
	if ( isset( $collector_order['data']['shipping'] ) ) {
		update_post_meta( $order_id, '_collector_delivery_module_data', wp_json_encode( $collector_order['data']['shipping'], JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $order_id, '_collector_delivery_module_reference', $collector_order['data']['shipping']['pendingShipment']['id'] );
	}

	// Tie this order to a user if we have one.
	if ( email_exists( $collector_order['data']['customer']['email'] ) ) {
		$user    = get_user_by( 'email', $collector_order['data']['customer']['email'] );
		$user_id = $user->ID;
		update_post_meta( $order_id, '_customer_user', $user_id );
	}

	if ( 'Preliminary' === $payment_status || 'Completed' === $payment_status ) {
		$order->payment_complete( $payment_id );
	} elseif ( 'Signing' === $payment_status ) {
		$order->add_order_note( __( 'Order is waiting for electronic signing by customer. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $payment_id );
		update_post_meta( $order_id, '_transaction_id', $payment_id );
		$order->update_status( 'on-hold' );
	} else {
		$order->add_order_note( __( 'Order is PENDING APPROVAL by Collector. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $payment_id );
		update_post_meta( $order_id, '_transaction_id', $payment_id );
		$order->update_status( 'on-hold' );
	}

	// Translators: Collector Payment method.
	$order->add_order_note( sprintf( __( 'Purchase via %s', 'collector-checkout-for-woocommerce' ), wc_collector_get_payment_method_name( $payment_method ) ) );
	return true;
}
