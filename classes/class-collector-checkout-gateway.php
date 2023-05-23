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
			as_schedule_single_action( time() + 120, 'collector_check_for_order', array( $private_id, $public_token, $customer_type ) );
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

		if ( 'yes' === $this->enabled ) {

			if ( is_checkout() ) {
				$cart_item_total = Collector_Checkout_Requests_Cart::cart();

				// Update checkout and annul payment method if the total cart item amount is 0.
				if ( empty( $cart_item_total['items'] ) ) {
					return false;
				}
			}

			if ( ! is_admin() ) {
				$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
				$collector_b2c_se   = ( isset( $collector_settings['collector_merchant_id_se_b2c'] ) ) ? $collector_settings['collector_merchant_id_se_b2c'] : '';
				$collector_b2b_se   = ( isset( $collector_settings['collector_merchant_id_se_b2b'] ) ) ? $collector_settings['collector_merchant_id_se_b2b'] : '';
				$collector_b2c_no   = ( isset( $collector_settings['collector_merchant_id_no_b2c'] ) ) ? $collector_settings['collector_merchant_id_no_b2c'] : '';
				$collector_b2b_no   = ( isset( $collector_settings['collector_merchant_id_no_b2b'] ) ) ? $collector_settings['collector_merchant_id_no_b2b'] : '';
				$collector_b2c_dk   = ( isset( $collector_settings['collector_merchant_id_dk_b2c'] ) ) ? $collector_settings['collector_merchant_id_dk_b2c'] : '';
				$collector_b2c_fi   = ( isset( $collector_settings['collector_merchant_id_fi_b2c'] ) ) ? $collector_settings['collector_merchant_id_fi_b2c'] : '';
				$collector_b2b_fi   = ( isset( $collector_settings['collector_merchant_id_fi_b2b'] ) ) ? $collector_settings['collector_merchant_id_fi_b2b'] : '';

				// Currency check.
				if ( ! in_array( get_woocommerce_currency(), array( 'NOK', 'SEK', 'DKK', 'EUR' ), true ) ) {
					return false;
				}
				// Store ID check.
				if ( 'NOK' === get_woocommerce_currency() && ( ! $collector_b2c_no && ! $collector_b2b_no ) ) {
					return false;
				}
				if ( 'SEK' === get_woocommerce_currency() && ( ! $collector_b2c_se && ! $collector_b2b_se ) ) {
					return false;
				}
				if ( 'DKK' === get_woocommerce_currency() && ( ! $collector_b2c_dk ) ) {
					return false;
				}
				if ( 'EUR' === get_woocommerce_currency() && ( ! $collector_b2c_fi && ! $collector_b2b_fi ) ) {
					return false;
				}
			}
			return true;
		}
		return false;
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
		$invoice_url = get_post_meta( $order->get_id(), '_collector_invoice_url', true );
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

		$customer_type = WC()->session->get( 'collector_customer_type' );
		$private_id    = WC()->session->get( 'collector_private_id' );
		update_post_meta( $order_id, '_collector_customer_type', $customer_type );
		update_post_meta( $order_id, '_collector_public_token', WC()->session->get( 'collector_public_token' ) );
		update_post_meta( $order_id, '_collector_private_id', $private_id );

		$walley_reference = $this->update_walley_reference( $order_id, $customer_type, $private_id );
		if ( false === $walley_reference ) {
			return array(
				'result' => 'error',
			);
		}

		$walley_order = $this->get_walley_order( $order_id, $customer_type, $private_id );

		if ( is_wp_error( $walley_order ) ) {
			return array(
				'result' => 'error',
			);
		}

		$walley_extra_fields = $this->save_walley_extra_fields( $order_id, $walley_order );

		$walley_shipping = $this->save_walley_purchase_and_shipping_data( $order_id, $walley_order );

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

	public function save_walley_extra_fields( $order_id, $walley_order ) {

		// Save customFields data.
		if ( isset( $walley_order['data']['customFields'] ) ) {

			// Save the entire customFields object as json in order.
			if ( true === apply_filters( 'walley_save_custom_fields_raw_data', true ) ) {
				update_post_meta( $order_id, '_collector_custom_fields', wp_json_encode( $walley_order['data']['customFields'] ) );
			}

			// Save each individual custom field as id:value.
			if ( true === apply_filters( 'walley_save_individual_custom_field', true ) ) {
				foreach ( $walley_order['data']['customFields'] as $custom_field_group ) {

					foreach ( $custom_field_group['fields'] as $custom_field ) {

						$value = $custom_field['value'];
						// If the returned value is true/false convert it to yes/no since it is easier to store as post meta value.
						if ( is_bool( $value ) ) {
							$value = $value ? 'yes' : 'no';
						}
						update_post_meta( $order_id, $custom_field['id'], sanitize_text_field( $value ) );
					}
				}
			}
		}
	}

	public function save_walley_purchase_and_shipping_data( $order_id, $walley_order ) {
		$order               = wc_get_order( $order_id );
		$payment_status      = $walley_order['data']['purchase']['result'] ?? '';
		$payment_method      = $walley_order['data']['purchase']['paymentName'] ?? '';
		$payment_id          = $walley_order['data']['purchase']['purchaseIdentifier'] ?? '';
		$walley_order_id     = $walley_order['data']['order']['orderId'] ?? '';
		$organization_number = $walley_order['data']['businessCustomer']['organizationNumber'] ?? '';
		$invoice_reference   = $walley_order['data']['businessCustomer']['invoiceReference'] ?? '';

		update_post_meta( $order_id, '_collector_payment_method', $payment_method );
		update_post_meta( $order_id, '_collector_payment_id', $payment_id );
		update_post_meta( $order_id, '_collector_order_id', sanitize_key( $walley_order_id ) );
		update_post_meta( $order_id, '_collector_original_order_total', $order->get_total() );

		if ( ! empty( $organization_number ) ) {
			update_post_meta( $order_id, '_collector_org_nr', sanitize_key( $organization_number ) );
		}

		if ( ! empty( $invoice_reference ) ) {
			update_post_meta( $order_id, '_collector_invoice_reference', sanitize_key( $invoice_reference ) );
		}

		wc_collector_save_shipping_reference_to_order( $order_id, $walley_order );

		// Save shipping data.
		if ( isset( $walley_order['data']['shipping'] ) ) {
			update_post_meta( $order_id, '_collector_delivery_module_data', wp_json_encode( $walley_order['data']['shipping'], JSON_UNESCAPED_UNICODE ) );
			update_post_meta( $order_id, '_collector_delivery_module_reference', $walley_order['data']['shipping']['pendingShipment']['id'] );
		}
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
		if ( is_object( $order ) && 'collector_checkout' === $order->get_payment_method() ) {
			CCO_WC()->logger::log( 'Thankyou page rendered for order ID - ' . $order->get_id() );
			return '<div class="collector-checkout-thankyou"></div>';
		}
		$purchase_status = filter_input( INPUT_GET, 'purchase-status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( 'not-completed' === $purchase_status ) {
			// Unset Collector token and id.
			wc_collector_unset_sessions();
			WC()->cart->empty_cart();
			CCO_WC()->logger::log( 'Rendering simplified thankyou page (only display Collector thank you iframe).' );

			return '<div class="collector-checkout-thankyou"></div>';
		}

		return $text;
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
		if ( empty( get_post_meta( $order->get_id(), '_collector_order_activated', true ) ) ) {
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
			$order_id = $order->get_id();
			if ( get_post_meta( $order_id, '_collector_org_nr' ) ) {
				echo '<p class="form-field form-field-wide"><strong>' . __( 'Org Nr', 'collector-checkout-for-woocommerce' ) . ':</strong> ' . get_post_meta( $order_id, '_collector_org_nr', true ) . '</p>';// phpcs:ignore
			}
			if ( get_post_meta( $order_id, '_collector_invoice_reference' ) ) {
				echo '<p class="form-field form-field-wide"><strong>' . __( 'Invoice reference', 'collector-checkout-for-woocommerce' ) . ':</strong> ' . get_post_meta( $order_id, '_collector_invoice_reference', true ) . '</p>';//phpcs:ignore
			}
		}
	}

	/**
	 * Whether shipping should be displayed in the order review.
	 *
	 * If the "Walley Delivery Module" is enabled, the shipping option will not be available, only the default ones (if available).
	 * This will result in a discrepancy between WooCommerce that has a default shipping cost, and Walley that does not include a shipping cost.
	 * For this purpose, we want to WooCommerce to wait with calculating shipping until a shipping option from Walley is available.
	 *
	 * @param  bool $show_shipping
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

			/* Once the "Walley Delivery Module" is available, display the shipping options. */
			$chosen_shipping = WC()->session->get( 'chosen_shipping_methods' )[0];
			if ( ! empty( $chosen_shipping ) && false !== strpos( $chosen_shipping, 'collector_delivery_module' ) ) {
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

}
