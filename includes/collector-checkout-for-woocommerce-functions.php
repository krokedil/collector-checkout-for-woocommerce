<?php
/**
 * Functions file for the plugin.
 *
 * @package  Collector_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Echoes Collector Checkout iframe snippet.
 */
function collector_wc_show_snippet() {
	$locale = 'en-SE';
	if ( 'NOK' === get_woocommerce_currency() ) {
		$locale = 'nb-NO';
	} elseif ( 'DKK' === get_woocommerce_currency() ) {
		$locale = 'da-DK';
	} elseif ( 'EUR' === get_woocommerce_currency() ) {
		$locale = 'fi-FI';
	} elseif ( 'sv_SE' === get_locale() ) {
		$locale = 'sv-SE';
	}

	$collector_settings       = get_option( 'woocommerce_collector_checkout_settings' );
	$test_mode                = $collector_settings['test_mode'];
	$data_action_color_button = isset( $collector_settings['checkout_button_color'] ) && ! empty( $collector_settings['checkout_button_color'] ) ? ' data-action-color="' . $collector_settings['checkout_button_color'] . '"' : '';

	$url = 'https://' . ( 'yes' === $test_mode ? 'checkout-uat.collector.se' : 'checkout.collector.se' ) . '/collector-checkout-loader.js';

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

		$status = wc_get_var( $collector_order['data']['status'] );
		if ( 'PurchaseCompleted' === $status ) {
			$order = wc_collector_get_order_by_private_id( $private_id );

			if ( ! empty( $order ) ) {
				CCO_WC()->logger::log( "Trying to display checkout but status is PurchaseCompleted. Private id $private_id, exist in order id {$order->get_id()}. Redirecting customer to thankyou page." );

				// Trigger the confirm_order function by redirecting with these specific parameters.
				wp_safe_redirect(
					add_query_arg(
						array(
							'walley_confirm' => '1',
							'public-token'   => $public_token,
						),
						wc_get_checkout_url() // We can redirect to any safe URL.
					)
				);
				// Important! Do not use wp_die(), use exit. A wp_die() will overwrite the HTTP code (302 for redirect) since it needs to display an error message in HTML to the user, setting the HTTP code to 500 (or 200), preventing a redirect. Refer to wp_die() docs.
				exit;
			}

			CCO_WC()->logger::log( "Trying to display checkout but status is PurchaseCompleted. Private id $private_id. No correlating order id can be found." );
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
 * @param WC_Order|int $order The WooCommerce order or order id.
 * @param string       $product_id WooCommerce product id.
 */
function wc_collector_add_invoice_fee_to_order( $order, $product_id ) {
	// Get the order object if the order is passed as an id.
	if ( ! is_object( $order ) ) {
		$order = wc_get_order( $order );
	}

	$result  = false;
	$product = wc_get_product( $product_id );

	if ( is_object( $product ) && is_object( $order ) ) {
		$price = wc_get_price_excluding_tax( $product );

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
 * Finds an Order based on a private ID (the Collector session id during purchase).
 *
 * @param string $private_id Collector session id saved as _collector_private_id ID in WC order.
 *
 * @return WC_Order|bool The WooCommerce order if found or false if not found.
 */
function wc_collector_get_order_by_private_id( $private_id = null ) {
	if ( empty( $private_id ) ) {
		return false;
	}

	$args = array(
		'meta_key'     => '_collector_private_id',
		'meta_value'   => $private_id,
		'meta_compare' => '=',
		'order'        => 'DESC',
		'orderby'      => 'date',
		'limit'        => 1,
		'date_after'   => '120 day ago',
	);

	$orders = wc_get_orders( $args );

	// If the orders array is empty, return false.
	if ( empty( $orders ) ) {
		return false;
	}

	// Get the first order in the array.
	$order = reset( $orders );

	// Validate that the order actually has the metadata we're looking for, and that it is the same.
	$meta_value = $order->get_meta( '_collector_private_id', true );

	// If the meta value is not the same as the Private id, return false.
	if ( $meta_value !== $private_id ) {
		return false;
	}

	return $order;
}

/**
 * Confirm order
 *
 * @param WC_Order|int $order The WooCommerce order or order id.
 * @param string       $private_id Collector session id saved as _collector_private_id ID in WC order.
 */
function wc_collector_confirm_order( $order, $private_id = null ) {
	// Get the order object if the order is passed as an id.
	if ( ! is_object( $order ) ) {
		$order = wc_get_order( $order );
	}

	if ( empty( $private_id ) ) {
		$private_id = $order->get_meta( '_collector_private_id', true );
	}

	$customer_type = $order->get_meta( '_collector_customer_type', true );
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

	// Maybe add invoice fee to order.
	if ( 'DirectInvoice' === $payment_method ) {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$product_id         = $collector_settings['collector_invoice_fee'];
		if ( $product_id ) {
			wc_collector_add_invoice_fee_to_order( $order->get_id(), $product_id );
		}
	}

	$order->update_meta_data( '_collector_payment_method', $payment_method );
	$order->update_meta_data( '_collector_payment_id', $payment_id );
	$order->update_meta_data( '_collector_order_id', sanitize_key( $walley_order_id ) );
	wc_collector_save_shipping_reference_to_order( $order->get_id(), $collector_order );

	if ( 'Preliminary' === $payment_status || 'Completed' === $payment_status ) {
		$order->payment_complete( $payment_id );
	} elseif ( 'Signing' === $payment_status ) {
		$order->add_order_note( __( 'Order is waiting for electronic signing by customer. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $payment_id );
		$order->update_meta_data( '_transaction_id', $payment_id );
		$order->update_status( 'on-hold' );
	} else {
		$order->add_order_note( __( 'Order is PENDING APPROVAL by Collector. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $payment_id );
		$order->add_meta_data( '_transaction_id', $payment_id );
		$order->update_status( 'on-hold' );
	}

	// Translators: Collector Payment method.
	$order->add_order_note( sprintf( __( 'Purchase via %s', 'collector-checkout-for-woocommerce' ), wc_collector_get_payment_method_name( $payment_method ) ) );
	$order->save();
}

/**
 * Saving shipping reference to order
 *
 * @param WC_Order|int $order The WooCommerce order or order id.
 * @param array        $collector_order Collector payment data.
 * @return void
 */
function wc_collector_save_shipping_reference_to_order( $order, $collector_order ) {
	// Get the order object if the order is passed as an id.
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}

	$order_items = $collector_order['data']['order']['items'] ?? array();
	foreach ( $order_items as $item ) {
		if ( strpos( $item['id'], 'shipping|' ) !== false ) {
			$order->update_meta_data( '_collector_shipping_reference', $item['id'] );
		}
	}

	$order->save();
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
	} elseif ( function_exists( 'wc_print_notice' ) ) {
			$print = 'wc_print_notice';
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
	$order = walley_get_order_by_key( '_collector_public_token', $public_token );
	return empty( $order ) ? 0 : $order->get_id();
}

/**
 * Get order by metadata key and value.
 *
 * @param string $key The meta key.
 * @param mixed  $value The expected metadata value.
 * @return bool|WC_Order The Woo order or false if not found.
 */
function walley_get_order_by_key( $key, $value ) {
	$orders = wc_get_orders(
		array(
			'meta_key'   => $key,
			'meta_value' => $value,
			'limit'      => 1,
			'orderby'    => 'date',
			'order'      => 'DESC',
		)
	);

	$order = reset( $orders );
	if ( empty( $order ) || $value !== $order->get_meta( $key ) ) {
		return false;
	}

	return $order;
}

/**
 * Confirm order
 *
 * @param WC_Order|int $order The WooCommerce order or order id.
 * @param string       $private_id Collector session id saved as _collector_private_id ID in WC order.
 */
function walley_confirm_order( $order, $private_id = null ) {
	// Get the Woo order if the order is passed as an int.
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order );
	}

	// Check if the order has been confirmed already.
	if ( ! empty( $order->get_date_paid() ) ) {
		return false;
	}

	if ( empty( $private_id ) ) {
		$private_id = $order->get_meta( '_collector_private_id', true );
	}

	$customer_type = $order->get_meta( '_collector_customer_type', true );
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
		$order->save();

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

	// Maybe add invoice fee to order.
	if ( 'DirectInvoice' === $payment_method ) {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$product_id         = $collector_settings['collector_invoice_fee'];
		if ( $product_id ) {
			wc_collector_add_invoice_fee_to_order( $order, $product_id );
		}
	}

	$order->update_meta_data( '_collector_payment_method', $payment_method );
	$order->update_meta_data( '_collector_payment_id', $payment_id );
	$order->update_meta_data( '_collector_order_id', sanitize_key( $walley_order_id ) );

	wc_collector_save_shipping_reference_to_order( $order, $collector_order );

	// Save custom fields data.
	walley_save_custom_fields( $order, $collector_order );

	// Save shipping data.
	if ( isset( $collector_order['data']['shipping'] ) ) {
		$order->update_meta_data( '_collector_delivery_module_data', wp_json_encode( $collector_order['data']['shipping'], JSON_UNESCAPED_UNICODE ) );
		$order->update_meta_data( '_collector_delivery_module_reference', $collector_order['data']['shipping']['pendingShipment']['id'] );
	}

	if ( 'Preliminary' === $payment_status || 'Completed' === $payment_status ) {
		$order->payment_complete( $payment_id );
	} elseif ( 'Signing' === $payment_status ) {
		$order->add_order_note( __( 'Order is waiting for electronic signing by customer. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $payment_id );
		$order->set_transaction_id( $payment_id );
		$order->update_status( 'on-hold' );
	} else {
		$order->add_order_note( __( 'Order is PENDING APPROVAL by Collector. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $payment_id );
		$order->set_transaction_id( $payment_id );
		$order->update_status( 'on-hold' );
	}

	// Translators: Collector Payment method.
	$order->add_order_note( sprintf( __( 'Purchase via %s', 'collector-checkout-for-woocommerce' ), wc_collector_get_payment_method_name( $payment_method ) ) );

	$order->save();
	return true;
}

/**
 * Get current Walley purchase status.
 *
 * @param array $walley_order the returned Walley order data.
 * @return string
 */
function get_walley_purchase_status( $walley_order ) {
	$purchase_status = $walley_order['data']['status'] ?? '';
	return $purchase_status;
}

/**
 * Returns approved Walley payment statuses where it is ok to send update requests.
 *
 * @return array Approved payment steps.
 */
function walley_payment_status_approved_for_update_request() {
	return array(
		'Initialized',
		'CustomerIdentified',
	);
}

/**
 * Should we add a rounding order line to Walley or not (if needed).
 *
 * @return string
 */
function walley_add_rounding_order_line() {
	$collector_settings      = get_option( 'woocommerce_collector_checkout_settings' );
	$add_rounding_order_line = $collector_settings['add_rounding_order_line'] ?? '';
	return $add_rounding_order_line;
}

/**
 * Return delivery module shipping reference if it exist in order.
 *
 * @param int $order_id WooCommerce order ID.
 *
 * @return string
 */
function walley_get_shipping_reference_from_delivery_module_data( $order_id ) {
	$order                   = wc_get_order( $order_id );
	$collector_delivery_data = json_decode( $order->get_meta( '_collector_delivery_module_data', true ), true ) ?? array();
	$shipping_reference      = $collector_delivery_data['shippingFeeId'] ?? '';
	return $shipping_reference;
}

/**
 * Maybe adds the free shipping tag.
 *
 * @return bool
 */
function walley_cart_contain_free_shipping_coupon() {
	foreach ( WC()->cart->get_applied_coupons() as $coupon_code ) {
		$coupon = new WC_Coupon( $coupon_code );
		if ( $coupon->get_free_shipping() ) {
			return true;
		}
	}
	return false;
}

/**
 * Get cart shipping classes.
 *
 * @return array
 */
function walley_get_cart_shipping_classes() {
	$shipping_classes = array();
	// Check all cart items.
	foreach ( WC()->cart->get_cart() as $cart_item ) {

		if ( ! empty( $cart_item['data']->get_shipping_class() ) ) {
			$shipping_classes[ $cart_item['data']->get_shipping_class() ] = true;
		}
	}
	return $shipping_classes;
}

/**
 * Save custom fields to WooCommerce order if they exist in Walley order.
 *
 * @param WC_Order|int $order The WooCommerce order or order id.
 * @param array        $walley_order Walley order data.
 *
 * @return void
 */
function walley_save_custom_fields( $order, $walley_order ) {
	// Get the order object if the order is passed as an id.
	if ( ! is_object( $order ) ) {
		$order = wc_get_order( $order );
	}

	// Save customFields data.
	if ( ! empty( $order ) && isset( $walley_order['data']['customFields'] ) ) {

		// Save the entire customFields object as json in order.
		if ( apply_filters( 'walley_save_custom_fields_raw_data', true ) ) {
			$order->update_meta_data( '_collector_custom_fields', wp_json_encode( $walley_order['data']['customFields'] ) );
		}

		// Save each individual custom field as id:value.
		if ( apply_filters( 'walley_save_individual_custom_field', true ) ) {
			foreach ( $walley_order['data']['customFields'] as $custom_field_group ) {

				foreach ( $custom_field_group['fields'] as $custom_field ) {

					$value = $custom_field['value'];
					// If the returned value is true/false convert it to yes/no since it is easier to store as post meta value.
					if ( is_bool( $value ) ) {
						$value = $value ? 'yes' : 'no';
					}
					$order->update_meta_data( $custom_field['id'], sanitize_text_field( $value ) );
				}
			}
		}

		$order->save();
	}
}

/**
 * Returns a list of supported currencies with the country code they are supported for.
 *
 * @return array
 */
function walley_get_supported_currencies() {
	return array(
		'SEK' => 'se',
		'NOK' => 'no',
		'DKK' => 'dk',
		'EUR' => 'fi',
	);
}

/**
 * Checks if a currency is supported by Walley.
 *
 * @param string $currency The currency to check.
 *
 * @return bool
 */
function walley_is_currency_supported( $currency ) {
	$supported_currencies = walley_get_supported_currencies();
	return isset( $supported_currencies[ $currency ] );
}

/**
 * Gets the country code for a currency.
 *
 * @param string $currency The currency to get the country code for.
 *
 * @return string|bool
 */
function walley_get_currency_country( $currency ) {
	$supported_currencies = walley_get_supported_currencies();

	if ( ! isset( $supported_currencies[ $currency ] ) ) {
		return false;
	}

	return $supported_currencies[ $currency ];
}

/**
 * Helper function - get available customer types.
 *
 * @return string|bool
 */
function wc_collector_get_available_customer_types() {
	$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );

	$currency = get_woocommerce_currency();
	$country  = walley_get_currency_country( $currency );

	// If the currency is not supported by Walley, return false.
	if ( ! $country ) {
		return false;
	}

	// Get the merchant id for the selected country, for both B2C and B2B.
	$b2c_set = ! empty( $collector_settings[ "collector_merchant_id_{$country}_b2c" ] ?? false );
	$b2b_set = ! empty( $collector_settings[ "collector_merchant_id_{$country}_b2b" ] ?? false );

	// Build the return value dynamically based on the availability of b2c and b2b.
	$result = 'collector-';
	if ( $b2c_set ) {
		$result .= 'b2c';
	}
	if ( $b2b_set ) {
		$result .= ( $b2c_set ? '-b2b' : 'b2b' );
	}

	// Return the result or false if neither b2c nor b2b is available.
	return ( $b2c_set || $b2b_set ) ? $result : false;
}

/**
 * Helper function - get default customer type.
 *
 * @return string
 */
function wc_collector_get_default_customer_type() {
	$collector_settings    = get_option( 'woocommerce_collector_checkout_settings' );
	$default_customer_type = $collector_settings['collector_default_customer'] ?? '';

	$currency = get_woocommerce_currency();
	$country  = walley_get_currency_country( $currency );

	// If no country, return the default customer type.
	if ( ! $country ) {
		return $default_customer_type;
	}

	$b2c_set = ! empty( $collector_settings[ "collector_merchant_id_{$country}_b2c" ] ?? false );
	$b2b_set = ! empty( $collector_settings[ "collector_merchant_id_{$country}_b2b" ] ?? false );

	// If the default type is not available but the other is, switch to the other type.
	if ( 'b2c' === $default_customer_type && ! $b2c_set && $b2b_set ) {
		return 'b2b';
	} elseif ( 'b2b' === $default_customer_type && ! $b2b_set && $b2c_set ) {
		return 'b2c';
	}

	// In all other cases, return the default type.
	return $default_customer_type;
}

/**
 * Helper function - get selected customer type.
 *
 * @return string
 */
function wc_collector_get_selected_customer_type() {
	$selected_customer_type = false;
	if ( isset( WC()->session ) && method_exists( WC()->session, 'get' ) ) {
		$selected_customer_type = WC()->session->get( 'collector_customer_type' );
	}

	if ( empty( $selected_customer_type ) ) {
		$selected_customer_type = wc_collector_get_default_customer_type();
	}

	return $selected_customer_type;
}

/**
 * Get localized and formatted payment method name.
 *
 * @param string $payment_method Collectors naming of the payment type.
 *
 * @return string
 */
function wc_collector_get_payment_method_name( $payment_method ) {
	switch ( $payment_method ) {

		case 'Direct Invoice':
		case 'DirectInvoice':
			$payment_method = __( 'Walley Invoice', 'collector-checkout-for-woocommerce' );
			break;
		case 'Account':
			$payment_method = __( 'Walley Account', 'collector-checkout-for-woocommerce' );
			break;
		case 'Part Payment':
		case 'PartPayment':
			$payment_method = __( 'Walley Part Payment', 'collector-checkout-for-woocommerce' );
			break;
		case 'Campaign':
			$payment_method = __( 'Walley Campaign', 'collector-checkout-for-woocommerce' );
			break;
		case 'Card':
			$payment_method = __( 'Walley Card', 'collector-checkout-for-woocommerce' );
			break;
		case 'Bank Transfer':
		case 'BankTransfer':
			$payment_method = __( 'Walley Bank Transfer', 'collector-checkout-for-woocommerce' );
			break;
		default:
			break;
	}

	return $payment_method;
}

/**
 * Similar to WP's get_the_ID() with HPOS support. Used for retrieving the current order/post ID.
 *
 * Unlike get_the_ID() function, if `id` is missing, we'll default to the `post` query parameter when HPOS is disabled.
 *
 * @return int|false the order ID or false.
 */
//phpcs:ignore
function walley_get_the_ID() {
	$hpos_enabled = walley_is_hpos_enabled();
	$order_id     = $hpos_enabled ? filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) : get_the_ID();
	if ( empty( $order_id ) ) {
		if ( ! $hpos_enabled ) {
			$order_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );
			return empty( $order_id ) ? false : $order_id;
		}
		return false;
	}

	return absint( $order_id );
}

/**
 * Whether HPOS is enabled.
 *
 * @return bool true if HPOS is enabled, otherwise false.
 */
function walley_is_hpos_enabled() {
	// CustomOrdersTableController was introduced in WC 6.4.
	if ( class_exists( CustomOrdersTableController::class ) ) {
		return wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
	}

	return false;
}
/**
 * Retrieves the post type of the current post or of a given post.
 *
 * Compatible with HPOS.
 *
 * @param int|WP_Post|WC_Order|null $post Order ID, post object or order object.
 * @return string|null|false Return type of passed id, post or order object on success, false or null on failure.
 */
function walley_get_post_type( $post = null ) {
	if ( ! walley_is_hpos_enabled() ) {
		return get_post_type( $post );
	}

	return ! class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ? false : Automattic\WooCommerce\Utilities\OrderUtil::get_order_type( $post );
}

/**
 * Retrieves the post type of the current post or of a given post.
 *
 * @param int|WP_Post|WC_Order|null $post Order ID, post object or order object.
 * @return true if order type, otherwise false.
 */
function walley_is_order_type( $post = null ) {
	return in_array( walley_get_post_type( $post ), array( 'woocommerce_page_wc-orders', 'shop_order' ), true );
}

/**
 * Whether the current page is an order page.
 *
 * @return bool
 */
function walley_is_order_page() {
	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( ! empty( $screen ) ) {
			return 'shop_order' === $screen->post_type;
		}
	}

	return walley_is_order_type( walley_get_the_ID() );
}