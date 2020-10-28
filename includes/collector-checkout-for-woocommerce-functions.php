<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Echoes Collector Checkout iframe snippet.
 */
function collector_wc_show_snippet() {
	if ( 'NOK' == get_woocommerce_currency() ) {
		$locale = 'nb-NO';
	} elseif ( 'DKK' == get_woocommerce_currency() ) {
		$locale = 'da-DK';
	} elseif ( 'EUR' == get_woocommerce_currency() ) {
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

	if ( 'yes' == $test_mode ) {
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

	if ( empty( $public_token ) || $collector_currency !== get_woocommerce_currency() ) {
		// Get a new public token from Collector
		$init_checkout = new Collector_Checkout_Requests_Initialize_Checkout( $customer_type );
		$request       = $init_checkout->request();

		if ( is_wp_error( $request ) || empty( $request ) ) {
			$return = '<ul class="woocommerce-error"><li>' . sprintf( '%s <a href="%s" class="button wc-forward">%s</a>', __( 'Could not connect to Collector. Error message: ', 'collector-checkout-for-woocommerce' ) . $request->get_error_message(), wc_get_checkout_url(), __( 'Try again', 'collector-checkout-for-woocommerce' ) ) . '</li></ul>';
		} else {
			$decode = json_decode( $request );
			WC()->session->set( 'collector_public_token', $decode->data->publicToken );
			WC()->session->set( 'collector_private_id', $decode->data->privateId );
			WC()->session->set( 'collector_currency', get_woocommerce_currency() );

			$collector_checkout_sessions = new Collector_Checkout_Sessions();
			$collector_data              = array(
				'session_id' => $collector_checkout_sessions->get_session_id(),
			);
			$args                        = array(
				'private_id' => $decode->data->privateId,
				'data'       => $collector_data,
			);
			$result                      = Collector_Checkout_DB::create_data_entry( $args );

			$public_token = $decode->data->publicToken;
			$output       = array(
				'publicToken'   => $public_token,
				'test_mode'     => $test_mode,
				'customer_type' => $customer_type,
			);

			echo( "<script>console.log('Collector: " . json_encode( $output ) . "');</script>" );
			$return = '<div id="collector-container"><script src="' . $url . '" data-lang="' . $locale . '" data-token="' . $public_token . '" data-variant="' . $customer_type . '"' . $data_action_color_button . ' ></script></div>';
		}
	} else {

		$output = array(
			'publicToken'   => $public_token,
			'test_mode'     => $test_mode,
			'customer_type' => $customer_type,
		);
		echo( "<script>console.log('Collector: " . json_encode( $output ) . "');</script>" );
		$return = '<div id="collector-container"><script src="' . $url . '" data-lang="' . $locale . '" data-token="' . $public_token . '" data-variant="' . $customer_type . '"' . $data_action_color_button . ' ></script></div>';
	}

	echo $return;
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
 * Calculates cart totals.
 */
function collector_wc_calculate_totals() {
	WC()->cart->calculate_fees();
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();
}

/**
 * Shows select another payment method button in Collector Checkout page.
 */
function collector_wc_show_another_gateway_button() {
	$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
	if ( count( $available_payment_gateways ) > 1 ) {
		?>
		<a class="button" id="collector_change_payment_method" href="#"><?php echo __( 'Select another payment method', 'collector-checkout-for-woocommerce' ); ?></a>
		<?php
	}
}

/**
 * Shows B2C/B2B switcher in Collector Checkout page.
 */
function collector_wc_show_customer_type_switcher() {
	if ( 'collector-b2c-b2b' === wc_collector_get_available_customer_types() ) {
		?>
		<ul class="collector-checkout-tabs">
			<li class="tab-link current" data-tab="b2c"><?php _e( 'Person', 'collector-checkout-for-woocommerce' ); ?></li>
			<li class="tab-link" data-tab="b2b"><?php _e( 'Company', 'collector-checkout-for-woocommerce' ); ?></li>
		</ul>
		<?php
	}
}

/**
 * Shows Customer order notes in Collector Checkout page.
 */
function collector_wc_show_customer_order_notes() {
	if ( apply_filters( 'woocommerce_enable_order_notes_field', true ) ) {
		$form_field = WC()->checkout()->get_checkout_fields( 'order' );
		woocommerce_form_field( 'order_comments', $form_field['order_comments'] );
	}
}

/**
 * Unset Collector public token and private id
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
		// Check what kind of tax rate we have
		if ( $product->is_taxable() && isset( $_vat['rate'] ) ) {
			$vat_rate = round( $_vat['rate'] );
		} else {
			// if empty, set 0% as rate
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
			$order->add_order_note( __( 'Unable to add Collector Bank Invoice Fee to the order.', 'collector-checkout-for-woocommerce' ) );
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
	if ( isset( $_GET['payment_successful'] ) && '1' === $_GET['payment_successful'] && isset( $_GET['public-token'] ) ) {
		return true;
	}
	return false;
}


/**
 * Get Collector data from Database.
 *
 * @param string $private_id Collector private id.
 * @return string|null
 */
function get_collector_data_from_db( $private_id ) {
	$result = Collector_Checkout_DB::get_data_entry( $private_id );
	return $result;
}
