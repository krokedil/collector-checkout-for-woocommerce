<?php
/**
 * Gateway class.
 *
 * @package CollectorCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_Gateway
 */
class Collector_Checkout_Gateway extends WC_Payment_Gateway {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                   = 'collector_checkout';
		$this->method_title         = __( 'Walley Checkout', 'collector-checkout-for-woocommerce' );
		$this->method_description   = __( 'Walley Checkout payment solution for WooCommerce.', 'collector-checkout-for-woocommerce' );
		$this->description          = $this->get_option( 'description' );
		$this->title                = $this->get_option( 'title' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->walley_api_client_id = $this->get_option( 'walley_api_client_id' );
		$this->walley_api_secret    = $this->get_option( 'walley_api_secret' );

		switch ( get_woocommerce_currency() ) {
			case 'SEK':
				$this->delivery_module = isset( $this->settings['collector_delivery_module_se'] ) ? $this->settings['collector_delivery_module_se'] : 'no';
				break;
			case 'NOK':
				$this->delivery_module = isset( $this->settings['collector_delivery_module_no'] ) ? $this->settings['collector_delivery_module_no'] : 'no';
				break;
			case 'DKK':
				$this->delivery_module = isset( $this->settings['collector_delivery_module_dk'] ) ? $this->settings['collector_delivery_module_dk'] : 'no';
				break;
			case 'EUR':
				$this->delivery_module = isset( $this->settings['collector_delivery_module_fi'] ) ? $this->settings['collector_delivery_module_fi'] : 'no';
				break;
			default:
				$this->delivery_module = isset( $this->settings['collector_delivery_module_se'] ) ? $this->settings['collector_delivery_module_se'] : 'no';
				break;
		}
		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();
		$this->supports = array(
			'products',
			'refunds',
			'upsell',
		);

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		// Function to handle the thankyou page.
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'collector_thankyou_order_received_text' ), 10, 2 );
		add_action( 'woocommerce_thankyou', array( $this, 'maybe_delete_collector_sessions' ), 100, 1 );

		// Body class.
		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		// Add org nr after address on company order.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'add_org_nr_to_order' ) );

		// Notification listener.
		add_action( 'woocommerce_api_collector_checkout_gateway', array( $this, 'notification_listener' ) );

		// Wait for the delivery module to load before calculating shipping.
		add_filter( 'woocommerce_cart_ready_to_calc_shipping', array( $this, 'show_shipping' ) );

		// Delete transient when Walley settings is saved.
		add_action( 'woocommerce_update_options_checkout_collector_checkout', array( $this, 'delete_transients' ) );
	}

	/**
	 * Schedule order status check on notificationUri callback from Collector
	 */
	public function notification_listener() {
		$private_id    = filter_input( INPUT_GET, 'private-id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$public_token  = filter_input( INPUT_GET, 'public-token', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$customer_type = filter_input( INPUT_GET, 'customer-type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		CCO_WC()->logger::log( 'Notification Listener hit. Private id: ' . wp_json_encode( $private_id ) . '. Public token: ' . $public_token . '. Customer type: ' . $customer_type );

		if ( empty( $private_id ) || empty( $public_token ) || empty( $customer_type ) ) {
			return;
		}

		$scheduled_actions = as_get_scheduled_actions(
			array(
				'hook'   => 'collector_check_for_order',
				'status' => ActionScheduler_Store::STATUS_PENDING,
				'args'   => array( $private_id, $public_token, $customer_type ),
			),
			'ids'
		);

		if ( empty( $scheduled_actions ) ) {
			as_schedule_single_action( time() + 30, 'collector_check_for_order', array( $private_id, $public_token, $customer_type ) );
			header( 'HTTP/1.1 200 OK' );
		} else {
			CCO_WC()->logger::log( 'collector_check_for_order callback already scheduled. ' . wp_json_encode( $scheduled_actions ) ); // Input var okay.
			header( 'HTTP/1.1 400 Bad Request' );
		}
		die();
	}

	/**
	 * Initialise settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = include COLLECTOR_BANK_PLUGIN_DIR . '/includes/collector-checkout-settings.php';
	}

	/**
	 * Admin Panel Options
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		$image_url = COLLECTOR_BANK_PLUGIN_URL . '/assets/images/walley-black.svg';
		?>
		<p><img src="<?php echo esc_html( $image_url ); ?>" width="200px"/></p>
		<h3><?php _e( 'Walley Checkout (Collector)', 'collector-checkout-for-woocommerce' );//phpcs:ignore ?></h3>
		<div class="collector-settings">
			<div class="collector-settings-content">
				<table class="form-table">
					<?php
					$this->generate_settings_html();
					?>
				</table>
			</div>
			<div class="collector-settings-sidebar">
				<h4>Kom igång</h4><p><ul>
					<li><a href="https://docs.krokedil.com/walley-checkout-for-woocommerce/" target="_blank">Dokumentation</a></li>
					<li><a href="https://wordpress.org/plugins/collector-checkout-for-woocommerce" target="_blank">Pluginsida</a></li>
				</ul></p>
				<h4>Support</h4><p>Har du frågor kring ditt konto eller kring specifika köp är du välkommen att <a href="https://www.collector.se/kundservice/" target="_blank">kontakta Collector</a>. Har du tekniska frågor eller funderingar kring konfigurationen av modulen så kan du <a href="https://krokedil.se/support/" target="_blank">kontakta Krokedil</a>.</p>
				<h4>Logotypes</h4><p>Du hittar våra logotypes genom att klicka på länken <a href="https://checkout-documentation.collector.se/#branding-resources" target="_blank">här</a>.</p>
				<h4>Köpvillkorstexter</h4><p>Text som avser Collector Banks köpvillkor hittas nedan.
					<ul>
						<li><a href="https://dev.walleypay.com/docs/marketingAssets/terms/overview" target="_blank">B2C</a></li>
						<li><a href="https://dev.walleypay.com/docs/marketingAssets/terms/overview" target="_blank">B2B</a></li>
					</ul></p>
			</div>
		</div>
		<?php
	}
	/**
	 * Check if this gateway is enabled and available in the user's country
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( is_checkout() ) {
			$cart_item_total = Collector_Checkout_Requests_Cart::cart();

			// Update checkout and annul payment method if the total cart item amount is 0.
			if ( empty( $cart_item_total['items'] ) ) {
				return false;
			}
		}

		if ( ! is_admin() ) {
			$currency = get_woocommerce_currency();
			// Currency check.
			if ( ! in_array( $currency, array( 'NOK', 'SEK', 'DKK', 'EUR' ), true ) ) {
				return false;
			}

			// If there are no available customer types, return false.
			if ( ! wc_collector_get_available_customer_types() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get a link to the transaction on the 3rd party gateway size (if applicable).
	 *
	 * @param  WC_Order $order the order object.
	 *
	 * @return string transaction URL, or empty string.
	 */
	public function get_transaction_url( $order ) {
		// Check if order is completed.
		$invoice_url = $order->get_meta( '_collector_invoice_url', true );
		if ( $invoice_url ) {
			$this->view_transaction_url = $invoice_url;
		}
		return parent::get_transaction_url( $order );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int  $order_id WooCommerce order ID.
	 * @param bool $retry The retry.
	 *
	 * @return array
	 */
	public function process_payment( $order_id, $retry = false ) {

		$order         = wc_get_order( $order_id );
		$customer_type = WC()->session->get( 'collector_customer_type' );
		$private_id    = WC()->session->get( 'collector_private_id' );
		$order->update_meta_data( '_collector_customer_type', $customer_type );
		$order->update_meta_data( '_collector_public_token', WC()->session->get( 'collector_public_token' ) );
		$order->update_meta_data( '_collector_private_id', $private_id );
		$order->save();

		$walley_reference = $this->update_walley_reference( $order_id, $customer_type, $private_id );

		// Check that update reference request was ok.
		if ( false === $walley_reference ) {
			$message = __( 'There was a problem updating the reference number in Walley.', 'collector-checkout-for-woocommerce' );
			wc_add_notice( $message, 'error' );
			return array(
				'result' => 'error',
			);
		}

		$walley_order = $this->get_walley_order( $order_id, $customer_type, $private_id );

		// Check that get order request was ok.
		if ( is_wp_error( $walley_order ) ) {
			$message = __( 'There was a problem retrieving the Walley order.', 'collector-checkout-for-woocommerce' );
			wc_add_notice( $message, 'error' );
			return array(
				'result' => 'error',
			);
		}

		// $shipping_cost                 = $walley_order['data']['fees']['shipping']['unitPrice'] ?? 0; // Shipping.
		$shipping_cost = $walley_order['data']['fees']['shipping']['unitPrice'] ?? $walley_order['data']['shipping']['shippingFee'] ?? 0;
		$cart_cost     = $walley_order['data']['cart']['totalAmount']; // Cart.
		$total_amount  = $shipping_cost + $cart_cost;

		// Check that order totals match between Woo and Walley.
		if ( abs( $total_amount - $order->get_total() ) > 3 ) {
			CCO_WC()->logger::log( 'Order total mismatch in process_payment. Woo order total: ' . $order->get_total() . '. Walley order total: ' . $total_amount . ' (cart:' . $cart_cost . ', shipping: ' . $shipping_cost . ').' );
			$message = __( 'It seems like the WooCommerce and Walley total amount differs. Please, try again.', 'collector-checkout-for-woocommerce' );
			wc_add_notice( $message, 'error' );
			return array(
				'result' => 'error',
			);
		}

		// Save data to order.
		walley_save_custom_fields( $order_id, $walley_order );
		$this->save_walley_purchase_and_shipping_data( $order_id, $walley_order );

		return array(
			'result' => 'success',
		);
	}

	public function update_walley_reference( $order_id, $customer_type, $private_id ) {
		// Update the Collector Order with the Order number.
		if ( ! empty( $private_id ) && ! empty( $customer_type ) ) {

			// Use new or old API.
			if ( walley_use_new_api() ) {
				$collector_order = CCO_WC()->api->set_order_reference_in_walley(
					array(
						'order_id'      => $order_id,
						'private_id'    => $private_id,
						'customer_type' => $customer_type,
					)
				);
				if ( is_wp_error( $collector_order ) ) {
					return false;
				}
			} else {
				$order            = wc_get_order( $order_id );
				$update_reference = new Collector_Checkout_Requests_Update_Reference( $order->get_order_number(), $private_id, $customer_type );
				$update_reference->request();
				CCO_WC()->logger::log( 'Update Collector order reference for order - ' . $order->get_order_number() );
				if ( is_wp_error( $update_reference ) ) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	public function get_walley_order( $order_id, $customer_type, $private_id ) {
		// Use new or old API.
		if ( walley_use_new_api() ) {
			$walley_order = CCO_WC()->api->get_walley_checkout(
				array(
					'private_id'    => $private_id,
					'customer_type' => $customer_type,
				)
			);
		} else {
			$walley_order = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
			$walley_order = $walley_order->request();
		}
		return $walley_order;
	}

	public function save_walley_purchase_and_shipping_data( $order_id, $walley_order ) {
		$order               = wc_get_order( $order_id );
		$payment_method      = $walley_order['data']['purchase']['paymentName'] ?? '';
		$payment_id          = $walley_order['data']['purchase']['purchaseIdentifier'] ?? '';
		$walley_order_id     = $walley_order['data']['order']['orderId'] ?? '';
		$organization_number = $walley_order['data']['businessCustomer']['organizationNumber'] ?? '';
		$invoice_reference   = $walley_order['data']['businessCustomer']['invoiceReference'] ?? '';

		$order->update_meta_data( '_collector_payment_method', $payment_method );
		$order->update_meta_data( '_collector_payment_id', $payment_id );
		$order->update_meta_data( '_collector_order_id', sanitize_key( $walley_order_id ) );
		$order->update_meta_data( '_collector_original_order_total', $order->get_total() );

		if ( ! empty( $organization_number ) ) {
			$order->update_meta_data( '_collector_org_nr', sanitize_key( $organization_number ) );
		}

		if ( ! empty( $invoice_reference ) ) {
			$order->update_meta_data( '_collector_invoice_reference', sanitize_key( $invoice_reference ) );
		}

		wc_collector_save_shipping_reference_to_order( $order_id, $walley_order );

		// Save shipping data.
		if ( isset( $walley_order['data']['shipping'] ) ) {
			$order->update_meta_data( '_collector_delivery_module_data', wp_json_encode( $walley_order['data']['shipping'], JSON_UNESCAPED_UNICODE ) );
		}
		$order->save();
	}

	/**
	 * Delete the Collector stored sessions.
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return void
	 */
	public function maybe_delete_collector_sessions( $order_id ) {
		// Unset Collector token and id.
		wc_collector_unset_sessions();
	}

	/**
	 * Remove thank you page order received text if Collector is the selected payment method.
	 *
	 * @param string   $text The received bank.
	 * @param WC_Order $order The WooCommerce order id.
	 *
	 * @return string
	 */
	public function collector_thankyou_order_received_text( $text, $order ) {
		// The $order might be FALSE. This can happen if the customer visits the order received page directly and the order does not exist or if the order number and/or the key is invalid.
		if ( empty( $order ) ) {
			return $text;
		}

		// Check if the payment method is Collector since the hook 'woocommerce_thankyou_order_received_text' is triggered for all payment methods.
		if ( 'collector_checkout' !== $order->get_payment_method() ) {
			return $text;
		}

		$html_snippet = '<div class="collector-checkout-thankyou"></div>';

		// Only print the snippet if the order was not upsold. If it has, the iframe wont show the same order amount as the WC order.
		$upsell_uuids    = $order->get_meta( '_ppu_upsell_ids' );
		$has_been_upsold = ! empty( $upsell_uuids );

		if ( $has_been_upsold ) {
			CCO_WC()->logger::log( 'Order has been upsold. Not rendering thankyou page snippet.' );
			return $text;
		}

		// Maybe render simplified thankyou page.
		$purchase_status = filter_input( INPUT_GET, 'purchase-status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'not-completed' === $purchase_status ) {
			// Unset Collector token and id.
			wc_collector_unset_sessions();
			WC()->cart->empty_cart();

			CCO_WC()->logger::log( 'Rendering simplified thankyou page (only display Collector thank you iframe).' );
		} else {
			CCO_WC()->logger::log( 'Thankyou page rendered for order ID - ' . $order->get_id() );
		}

		// Starting WC 8.1 the HTML snippet will be escaped, we now have to echo it directly.
		echo wp_kses_post( $html_snippet );
		// And return an empty string to overwrite the default $text string.
		return '';
	}

	/**
	 * Add collector-b2c/b2b body class.
	 *
	 * @param array $class Css class.
	 *
	 * @return array
	 */
	public function add_body_class( $class ) {
		if ( is_checkout() ) {

			// Don't display Collector body classes if we have a cart that doesn't needs payment.
			if ( method_exists( WC()->cart, 'needs_payment' ) && ! WC()->cart->needs_payment() ) {
				return $class;
			}

			$class[] = wc_collector_get_available_customer_types();

			$first_gateway = '';
			if ( WC()->session->get( 'chosen_payment_method' ) ) {
				$first_gateway = WC()->session->get( 'chosen_payment_method' );
			} else {
				$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
				reset( $available_payment_gateways );
				$first_gateway = key( $available_payment_gateways );
			}

			if ( 'collector_checkout' === $first_gateway ) {
				$class[] = 'collector-checkout-selected';
				// Add class if Collector delivery module is used.
				if ( 'yes' === $this->delivery_module ) {
					$class[] = 'collector-delivery-module';
				}
			}
		}
		return $class;
	}

	/**
	 * Can the order be refunded via Walley?
	 *
	 * @param  WC_Order $order Order object.
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		if ( empty( $order->get_meta( '_collector_order_activated', true ) ) ) {
			return false;
		}
		return parent::can_refund_order( $order );
	}

	/**
	 *
	 *  Process refund request.
	 *
	 * @param int    $order_id Thw WooCommerce order id.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 *
	 * @return bool
	 * @throws SoapFault Soap Fault.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		if ( ! empty( $this->walley_api_client_id ) && ! empty( $this->walley_api_secret ) ) {
			return $this->process_rest_refund( $order_id, $amount, $reason );
		} else {
			return $this->process_soap_refund( $order_id, $amount, $reason );
		}
	}

	/**
	 *
	 *  Process refund request if SOAP is still active.
	 *
	 * @param int    $order_id Thw WooCommerce order id.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 *
	 * @return bool
	 * @throws SoapFault Soap Fault.
	 */
	public function process_soap_refund( $order_id, $amount = null, $reason = '' ) {
		// Check if amount equals total order.
		$order = wc_get_order( $order_id );
		if ( $amount === $order->get_total() ) {
			$credit_order = new Collector_Checkout_SOAP_Requests_Credit_Payment( $order_id );
			if ( $credit_order->request( $order_id ) === true ) {
				return true;
			} else {
				return false;
			}
		} else {
			$result_full     = true;
			$result_part     = true;
			$refund_order_id = Collector_Checkout_Create_Refund_Data::get_refunded_order( $order_id );
			$refunded_items  = Collector_Checkout_Create_Refund_Data::create_refund_data( $order_id, $refund_order_id, $amount, $reason );
			if ( isset( $refunded_items['full_refunds'] ) ) {
				$credit_order = new Collector_Checkout_SOAP_Requests_Part_Credit_Invoice( $order_id );
				if ( ! $credit_order->request( $order_id, $amount, $reason, $refunded_items['full_refunds'] ) ) {
					$result_full = false;
				}
			}
			if ( isset( $refunded_items['partial_refund'] ) ) {
				$credit_order = new Collector_Checkout_SOAP_Requests_Adjust_Invoice( $order_id );
				if ( ! $credit_order->request( $order_id, $amount, $reason, $refunded_items['partial_refund'] ) ) {
					$result = false;
				}
			}
			// Failed full item refunds.
			if ( ! $result_full ) {
				$order->add_order_note( sprintf( __( 'Failed to refund full order lines with Walley.', 'collector-checkout-for-woocommerce' ) ) );
			}
			// Failed partial item refunds.
			if ( ! $result_part ) {
				$order->add_order_note( sprintf( __( 'Failed to refund partial order lines with Walley.', 'collector-checkout-for-woocommerce' ) ) );
			}
			return ( ! $result_full || ! $result_part ) ? false : true;
		}
	}

	/**
	 *
	 *  Process refund request if REST order management is active.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 *
	 * @return bool
	 */
	public function process_rest_refund( $order_id, $amount = null, $reason = '' ) {
		return CCO_WC()->order_management->refund_walley_order( $order_id, $amount, $reason );
	}

	/**
	 * Add org nr and invoice reference to order for
	 *
	 * @param WC_Order $order The WoCommerce order.
	 *
	 * @return void
	 */
	public function add_org_nr_to_order( $order ) {
		if ( 'collector_checkout' === $order->get_payment_method() ) {
			if ( $order->get_meta( '_collector_org_nr' ) ) {
				echo '<p class="form-field form-field-wide"><strong>' . __( 'Org Nr', 'collector-checkout-for-woocommerce' ) . ':</strong> ' . $order->get_meta( '_collector_org_nr', true ) . '</p>';// phpcs:ignore
			}
			if ( $order->get_meta( '_collector_invoice_reference' ) ) {
				echo '<p class="form-field form-field-wide"><strong>' . __( 'Invoice reference', 'collector-checkout-for-woocommerce' ) . ':</strong> ' . $order->get_meta( '_collector_invoice_reference', true ) . '</p>';//phpcs:ignore
			}
		}
	}

	/**
	 * Whether shipping should be displayed in the order review.
	 *
	 * If the "Walley Delivery Module" is enabled, the shipping option will not be available, only the default ones (if available).
	 * This will result in a discrepancy between WooCommerce that has a default shipping cost, and Walley that does not include a shipping cost.
	 * For this purpose, we want WooCommerce to wait with calculating shipping until a shipping option from Walley is available.
	 *
	 * @param  bool $show_shipping Whether to show the shipping options.
	 * @return bool
	 */
	public function show_shipping( $show_shipping ) {
		if ( 'collector_checkout' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return $show_shipping;
		}

		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );

		switch ( get_woocommerce_currency() ) {
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
				$delivery_module = isset( $collector_settings['collector_delivery_module_se'] ) ? $collector_settings['collector_delivery_module_se'] : 'no';
				break;
		}

		if ( 'yes' === $delivery_module ) {
			/* If the delivery module is configured by Walley (displayed in iframe), its shipping data will be available in the session. We need to check if there is a corresponding WC shipping option. */
			$delivery_module_data = WC()->session->get( 'collector_delivery_module_data', array() )[0] ?? '';
			$chosen_shipping      = WC()->session->get( 'chosen_shipping_methods', array() )[0] ?? '';
			if ( ! empty( $chosen_shipping ) && ( false !== strpos( $chosen_shipping, 'collector_delivery_module' ) || ( isset( $delivery_module_data['shipping_id'] ) && $delivery_module_data['shipping_id'] === $chosen_shipping ) ) ) {
				return true;
			}

			return false;
		}

		return $show_shipping;
	}

	/**
	 * Delete transients when Walley settings is saved.
	 *
	 * @return void
	 */
	public function delete_transients() {
		// Need to clear transients if credentials is changed.
		delete_transient( 'walley_checkout_access_token' );
	}

	/**
	 * Check if upsell should be available for the Klarna order or not.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return bool
	 */
	public function upsell_available( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( empty( $order ) ) {
			return false;
		}

		// Get the payment method from the order meta.
		$payment_method = $order->get_meta( '_collector_payment_method', true );

		// Ensure the payment method is valid.
		if ( ! in_array( $payment_method, array( 'Invoice', 'DirectInvoice', 'Account', 'Instalment', 'InterestFreeAccount' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Make an upsell request to Walley.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $upsell_uuid The unique id for the upsell request.
	 *
	 * @return bool|WP_Error
	 */
	public function upsell( $order_id, $upsell_uuid ) {
		$response = CCO_WC()->api->reauthorize_walley_order( $order_id );

		Collector_Checkout_Logger::log( 'Upsell request result: ' . wp_json_encode( $response ), true ); // Input var okay.

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// If the status is 201 we need to query the header url to get the result of the request.
		if ( 201 === $response['status'] ) {
			$location = $response['header'] ?? '';

			if ( empty( $location ) ) {
				return new WP_Error( 'collector_error', __( 'Could not get the reauthorize result from Walley.', 'collector-checkout-for-woocommerce' ) );
			}

			$i      = 0;
			$result = false;
			while ( $i < 5 && false === $result ) {
				sleep( 1 );
				$result = $this->get_reauthorize_result( $order_id, $location );
				++$i;
			}

			if ( false === $result ) {
				// TODO - Might need better error handling here? For example if the request is still pending after 5 seconds, how should we handle that case?
				return new WP_Error( 'collector_error', __( 'Could not get the reauthorize result from Walley.', 'collector-checkout-for-woocommerce' ) );
			}

			if ( 'Completed' !== $result ) {
				return new WP_Error( 'collector_error', __( 'Walley did not approve the Upsell.', 'collector-checkout-for-woocommerce' ) );
			}
		}

		return true;
	}

	/**
	 * Get the reauthorize result from Walley.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $location The location header from the reauthorize request.
	 *
	 * @return bool
	 */
	private function get_reauthorize_result( $order_id, $location ) {
		$response = CCO_WC()->api->get_reauthorize_result( $order_id, $location );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status = $response['data']['status'] ?? '';

		// If the status is not either completed or failed, then we need to try again.
		if ( ! in_array( $status, array( 'Completed', 'Failed' ), true ) ) {
			return false;
		}

		return $status;
	}
}
