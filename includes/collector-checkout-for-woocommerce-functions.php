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
		$locale = 'en-DK';
	} elseif ( 'EUR' == get_woocommerce_currency() ) {
		$locale = 'fi-FI';
	} else {
		$locale = 'sv-SE';
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
	WC()->session->__unset( 'collector_public_token' );
	WC()->session->__unset( 'collector_private_id' );
	WC()->session->__unset( 'collector_currency' );
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
 * Adds a hidden payment method field in Collector Checkout page.
 */
function collector_wc_show_payment_method_field() {
	?>
	<input style="display:none" type="radio" name="payment_method" value="collector_checkout"/>
	<?php
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

		$collector_fee            = new stdClass();
		$collector_fee->id        = sanitize_title( $product->get_title() );
		$collector_fee->name      = $product->get_title();
		$collector_fee->amount    = $price;
		$collector_fee->taxable   = $product_tax;
		$collector_fee->tax       = $vat_rate;
		$collector_fee->tax_data  = array();
		$collector_fee->tax_class = $product->get_tax_class();
		$fee_id                   = $order->add_fee( $collector_fee );

		if ( ! $fee_id ) {
			$order->add_order_note( __( 'Unable to add Collector Bank Invoice Fee to the order.', 'collector-checkout-for-woocommerce' ) );
		}
		$result = $order->calculate_totals( true );
	}
	return $result;
}
