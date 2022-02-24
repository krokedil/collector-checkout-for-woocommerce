<?php //phpcs:ignore
/**
 * * API Callbacks class.
 *
 * @package Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Collector_Api_Callbacks class.
 *
 * Class that handles Collector API callbacks.
 */
class Collector_Api_Callbacks {
	/**
	 * Order is valid flag.
	 *
	 * @var boolean
	 */
	public $order_is_valid = true;

	/**
	 * Validation messages.
	 *
	 * @var array
	 */
	public $validation_messages = array();

	/**
	 * The Collector order
	 *
	 * @var array The Collector order object.
	 */
	public $collector_order = array();

	/**
	 * The session id from Database.
	 *
	 * @var string
	 */
	public $db_session_id = null;

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Collector_Api_Callbacks constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'set_current_user' ) );
		add_action( 'woocommerce_api_collector_wc_validation', array( $this, 'validation_cb' ) );
		add_action( 'collector_check_for_order', array( $this, 'collector_check_for_order_callback' ), 10, 3 );
		$this->needs_login = 'no' === get_option( 'woocommerce_enable_guest_checkout' ) ? true : false; // Needs to be logged in order to checkout.
	}

	/**
	 * Handles validation callbacks.
	 */
	public function validation_cb() {
		CCO_WC()->logger::log( 'Validation Callback hit: ' . json_encode( $_GET ) . ' URL: ' . $_SERVER['REQUEST_URI'] );//phpcs:ignore

		$private_id          = isset( $_GET['private-id'] ) ? sanitize_text_field( wp_unslash( $_GET['private-id'] ) ) : null;//phpcs:ignore
		$customer_type       = isset( $_GET['customer-type'] ) ? sanitize_text_field( wp_unslash( $_GET['customer-type'] ) ) : null;//phpcs:ignore
		$collector_db_data   = get_collector_data_from_db( $private_id );
		$this->db_session_id = $collector_db_data->session_id;

		$response              = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$this->collector_order = $response->request();

		// Check if we have a session id.
		$this->check_session_id();

		// Check coupons.
		$this->check_cart_coupons();

		// Check for error notices from WooCommerce.
		$this->check_woo_notices();

		// Check order amount match.
		$this->check_order_amount();

		// Check that all items are still in stock.
		$this->check_all_in_stock();

		// Check if user need to login.
		if ( $this->needs_login ) {
			$this->check_if_user_exists_and_logged_in();
		}

		// Check if order is still valid.
		if ( $this->order_is_valid ) {
			CCO_WC()->logger::log( 'Private id: ' . $private_id . ' Collector Validation Callback. Order is valid.' );
			header( 'HTTP/1.0 200 OK' );
			die();
		} else {
			$log_array = array(
				'message'             => 'Private id: ' . $private_id . ' Collector Validation Callback. Order is NOT valid.',
				'validation_messages' => $this->validation_messages,
			);
			$log       = wp_json_encode( $log_array );
			CCO_WC()->logger::log( $log );
			if ( isset( $this->validation_messages['amount_error_totals'] ) ) {
				unset( $this->validation_messages['amount_error_totals'] );
			}

			// Gets the validation messages.
			$message = '';
			foreach ( $this->validation_messages as $error_type => $error_message ) {
				if ( 1 < count( $this->validation_messages ) ) { // If we have multiple messages, append.
					$message .= $error_message . ' ';
				} else {
					$message = $error_message;
				}
			}

			$data = array(
				'title'   => 'Order Validation Failed',
				'message' => empty( $message ) ? 'Error during checkout process.' : $message,
			);

			header( 'HTTP/1.0 303 See Other' );
			header( 'Content-Type: application/json' );
			echo wp_json_encode( $data );
			die();
		}

	}


	/**
	 * Check for order.
	 *
	 * @param string $private_id The private id.
	 * @param string $public_token The public token.
	 * @param string $customer_type The customer type.
	 *
	 * @return void
	 * @throws Exception When WC_Data_Store validation fails.
	 */
	public function collector_check_for_order_callback( $private_id, $public_token, $customer_type = 'b2c' ) {
		CCO_WC()->logger::log( 'Check for order in API-callback. Private id: ' . $private_id . '. Public token: ' . $public_token );
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'return'         => 'ids',
				'payment_method' => 'collector_checkout',
				'date_created'   => '>' . ( time() - MONTH_IN_SECONDS ),
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

		// Did we get a match?
		if ( $order_id_match ) {
			$order = wc_get_order( $order_id_match );

			if ( $order ) {
				// Check order status & order total.
				CCO_WC()->logger::log( 'API-callback hit. Private id ' . $private_id . '. already exist in order ID ' . $order_id_match . ' Checking order status...' );
				$this->check_order_status( $private_id, $public_token, $customer_type, $order );
			} else {
				// No order, why?
				CCO_WC()->logger::log( 'API-callback hit. Private id ' . $private_id . '. already exist in order ID ' . $order_id_match . '. But we could not instantiate an order object' );
			}
		} else {
			// No order found - create a new.
			CCO_WC()->logger::log( 'API-callback hit. We could NOT find Private id ' . $private_id . '(with public token ' . $public_token . ' & customer type ' . $customer_type . '). Starting backup order creation...' );
			$this->backup_order_creation( $private_id, $public_token, $customer_type );
		}

	}

	/**
	 * Check order status order total and transaction id, in case checkout process failed.
	 *
	 * @param string   $private_id The private id.
	 * @param string   $public_token The public token.
	 * @param string   $customer_type The customer type.
	 * @param WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public function check_order_status( $private_id, $public_token, $customer_type, $order ) {
		$response        = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type, $order->get_currency() );
		$collector_order = $response->request();

		if ( is_object( $order ) ) {

			// Check order status.
			if ( empty( $order->get_date_paid() ) ) {
				// Set order status in Woo.
				$this->set_order_status( $order, $collector_order );
			}

			// Compare order totals between the orders.
			$this->check_order_totals( $order, $collector_order );

			// Check if we need to update reference in collectors system.
			if ( empty( $collector_order['data']['reference'] ) ) {
				$this->update_order_reference_in_collector( $order, $customer_type, $private_id );
			}
		}
	}

	/**
	 * Backup order creation, in case checkout process failed
	 *
	 * @param string $private_id The private id.
	 * @param string $public_token The public token.
	 * @param string $customer_type The customer type.
	 *
	 * @return void
	 * @throws Exception WC_Data_Exception.
	 */
	public function backup_order_creation( $private_id, $public_token, $customer_type ) {
		$response        = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$collector_order = $response->request();

		// Process order.
		$order = $this->process_order( $collector_order, $private_id, $public_token, $customer_type );

		// Check order total.
		$this->check_order_totals( $order, $collector_order );

		// Send order number to Collector.
		if ( is_object( $order ) ) {
			$this->update_order_reference_in_collector( $order, $customer_type, $private_id );
		}
	}


	/**
	 * Processes the WooCommerce order on backup order creation.
	 *
	 * @param array  $collector_order Collector order.
	 * @param string $private_id The private id.
	 * @param string $public_token The public token.
	 * @param string $customer_type The customer type.
	 *
	 * @return WC_Order|WP_Error
	 * @throws Exception WC_Data_Exception.
	 */
	private function process_order( $collector_order, $private_id, $public_token, $customer_type ) {

		$customer_data = wc_collector_verify_customer_data( $collector_order );
		$order         = wc_create_order( array( 'status' => 'pending' ) );

		if ( is_wp_error( $order ) ) {
			CCO_WC()->logger::log( 'Backup order creation. Error - could not create order. ' . var_export( $order->get_error_message(), true ) );//phpcs:ignore
		} else {
			CCO_WC()->logger::log( 'Backup order creation - order ID - ' . $order->get_id() . ' - created.' );
		}

		$order_id = $order->get_id();

		$order->set_billing_first_name( sanitize_text_field( $customer_data['billingFirstName'] ) );
		$order->set_billing_last_name( sanitize_text_field( $customer_data['billingLastName'] ) );
		$order->set_billing_country( sanitize_text_field( $customer_data['countryCode'] ) );
		$order->set_billing_address_1( sanitize_text_field( $customer_data['billingAddress'] ) );
		$order->set_billing_address_2( sanitize_text_field( $customer_data['billingAddress2'] ) );
		$order->set_billing_city( sanitize_text_field( $customer_data['billingCity'] ) );
		$order->set_billing_postcode( sanitize_text_field( $customer_data['billingPostalCode'] ) );
		$order->set_billing_phone( sanitize_text_field( $customer_data['phone'] ) );
		$order->set_billing_email( sanitize_text_field( $customer_data['email'] ) );
		$order->set_shipping_first_name( sanitize_text_field( $customer_data['shippingFirstName'] ) );
		$order->set_shipping_last_name( sanitize_text_field( $customer_data['shippingLastName'] ) );
		$order->set_shipping_country( sanitize_text_field( $customer_data['countryCode'] ) );
		$order->set_shipping_address_1( sanitize_text_field( $customer_data['shippingAddress'] ) );
		$order->set_shipping_address_2( sanitize_text_field( $customer_data['shippingAddress2'] ) );
		$order->set_shipping_city( sanitize_text_field( $customer_data['shippingCity'] ) );
		$order->set_shipping_postcode( sanitize_text_field( $customer_data['shippingPostalCode'] ) );

		// Company specific info.
		if ( 'BusinessCustomer' === $collector_order['data']['customerType'] ) {
			$order->set_billing_company( sanitize_text_field( $customer_data['billingCompanyName'] ) );
			$order->set_shipping_company( sanitize_text_field( $customer_data['shippingCompanyName'] ) );
			update_post_meta( $order_id, '_collector_org_nr', $customer_data['orgNr'] );
		}

		$order->set_created_via( 'collector_checkout_api' );
		$order->set_currency( sanitize_text_field( get_woocommerce_currency() ) );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );

		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['collector_checkout'];
		$order->set_payment_method( $payment_method );
		$order->add_order_note( __( 'Order created via Collector Checkout API callback. Please verify the order in Collectors system.', 'collector-checkout-for-woocommerce' ) );

		foreach ( $collector_order['data']['order']['items'] as $cart_item ) {
			if ( strpos( $cart_item['id'], 'shipping|' ) !== false ) {

				// Shipping.
				$trimmed_cart_item_id = str_replace( 'shipping|', '', $cart_item['id'] );
				if ( $cart_item['vat'] > 0 ) {
					$price_excl_vat = $cart_item['unitPrice'] / ( ( $cart_item['vat'] * 0.01 ) + 1 );
				} else {
					$price_excl_vat = $cart_item['unitPrice'];
				}
				$rate = new WC_Shipping_Rate( $trimmed_cart_item_id, $cart_item['description'], $price_excl_vat, array(), 'flat_rate' );
				$item = new WC_Order_Item_Shipping();
				$item->set_props(
					array(
						'method_title' => $rate->label,
						'method_id'    => $rate->id,
						'total'        => wc_format_decimal( $rate->cost ),
						'taxes'        => $rate->taxes,
						'meta_data'    => $rate->get_meta_data(),
					)
				);
				$order->add_item( $item );
				// Save shipping reference to order.
				update_post_meta( $order->get_id(), '_collector_shipping_reference', $cart_item['id'] );

			} elseif ( 'Frakt' === $cart_item['id'] ) {
				// Collector Delivery Module Shipping.
				$args = array(
					'order_item_name' => $cart_item['description'],
					'order_item_type' => 'shipping',
				);

				$item_id = wc_add_order_item( $order->get_id(), $args );

				if ( $item_id ) {
					if ( $cart_item['unitPrice'] > 0 ) {
						if ( $cart_item['vat'] > 0 ) {
							$line_total_excl_vat = round( $cart_item['unitPrice'] / ( 1 + ( $cart_item['vat'] / 100 ) ), 2 );
							$line_total_vat      = $cart_item['unitPrice'] - $line_total_excl_vat;
						} else {
							$line_total_excl_vat = round( $cart_item['unitPrice'], 2 );
							$line_total_vat      = 0;
						}
					} else {
						$line_total_excl_vat = 0;
						$line_total_vat      = 0;
					}
					wc_add_order_item_meta( $item_id, '_qty', 1 );
					wc_add_order_item_meta( $item_id, 'cost', wc_format_decimal( $line_total_excl_vat ) );
					wc_add_order_item_meta( $item_id, 'total_tax', wc_format_decimal( $line_total_vat ) );

				}
				// Save shipping reference to order.
				update_post_meta( $order->get_id(), '_collector_shipping_reference', $cart_item['id'] );

			} elseif ( strpos( $cart_item['id'], 'invoicefee|' ) !== false ) {

				// Invoice fee.
				$trimmed_cart_item_id = str_replace( 'invoicefee|', '', $cart_item['id'] );
				$id                   = wc_get_product_id_by_sku( $trimmed_cart_item_id );

				if ( 0 === $id ) {
					$id = $trimmed_cart_item_id;
				}

				$product = wc_get_product( $id );

				if ( is_object( $product ) ) {
					$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );
					$price            = wc_get_price_excluding_tax( $product );

					if ( $product->is_taxable() ) {
						$product_tax = true;
					} else {
						$product_tax = false;
					}

					$_tax      = new WC_Tax();
					$tmp_rates = $_tax->get_base_tax_rates( $product->get_tax_class() );
					$_vat      = array_shift( $tmp_rates );// Get the rate.
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
					$order->add_item( $fee );
				}
			} else {

				// Product items.
				$id = wc_get_product_id_by_sku( $cart_item['id'] );

				if ( 0 === $id ) {
					$id = $cart_item['id'];
				}
				$product = wc_get_product( $id );

				// Product price.
				if ( $cart_item['unitPrice'] > 0 ) {
					if ( $cart_item['vat'] > 0 ) {
						$line_total_excl_vat = round( ( $cart_item['unitPrice'] / ( 1 + ( $cart_item['vat'] / 100 ) ) * $cart_item['quantity'] ), 2 );
						$line_total_vat      = $cart_item['unitPrice'] - $line_total_excl_vat;
					} else {
						$line_total_excl_vat = round( ( $cart_item['unitPrice'] * $cart_item['quantity'] ), 2 );
						$line_total_vat      = 0;
					}
				} else {
					$line_total_excl_vat = 0;
					$line_total_vat      = 0;
				}

				$args = array(
					'subtotal' => $line_total_excl_vat,
					'total'    => $line_total_excl_vat,
				);
				$order->add_product( $product, $cart_item['quantity'], $args );
			}
		}

		// Make sure to run Sequential Order numbers if plugin exsists.
		if ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
			$sequential = new WC_Seq_Order_Number_Pro();
			$sequential->set_sequential_order_number( $order_id );
		} elseif ( class_exists( 'WC_Seq_Order_Number' ) ) {
			$sequential = new WC_Seq_Order_Number();
			$sequential->set_sequential_order_number( $order_id, get_post( $order_id ) );
		}

		// Save shipping data.
		if ( isset( $collector_order['data']['shipping'] ) ) {
			update_post_meta( $order_id, '_collector_delivery_module_data', wp_json_encode( $collector_order['data']['shipping'], JSON_UNESCAPED_UNICODE ) );
			update_post_meta( $order_id, '_collector_delivery_module_reference', $collector_order['data']['shipping']['pendingShipment']['id'] );
		}

		update_post_meta( $order_id, '_collector_payment_method', $collector_order['data']['purchase']['paymentName'] );
		update_post_meta( $order_id, '_collector_payment_id', $collector_order['data']['purchase']['purchaseIdentifier'] );
		update_post_meta( $order_id, '_collector_customer_type', $customer_type );
		update_post_meta( $order_id, '_collector_public_token', $public_token );
		update_post_meta( $order_id, '_collector_private_id', $private_id );
		// translators: The Collector order data.
		$order->add_order_note( sprintf( __( 'Purchase via %s', 'collector-checkout-for-woocommerce' ), wc_collector_get_payment_method_name( $collector_order['data']['purchase']['paymentName'] ) ) );
		$order->calculate_totals();
		$order->save();

		// Set order status in Woo.
		$this->set_order_status( $order, $collector_order );

		// Remove database table row data.
		remove_collector_db_row_data( $private_id );

		return $order;
	}


	/**
	 * Set order status function
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $collector_order The Collector order.
	 *
	 * @return void
	 */
	public function set_order_status( $order, $collector_order ) {
		if ( 'Preliminary' === $collector_order['data']['purchase']['result'] || 'Completed' === $collector_order['data']['purchase']['result'] ) {
			$order->payment_complete( $collector_order['data']['purchase']['purchaseIdentifier'] );
			$order->add_order_note( 'Payment via Collector Checkout. Payment ID: ' . sanitize_key( $collector_order['data']['purchase']['purchaseIdentifier'] ) );
			CCO_WC()->logger::log( 'Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to Processing/Completed.' );
		} elseif ( 'Signing' === $collector_order['data']['purchase']['result'] ) {
			$order->add_order_note( __( 'Order is waiting for electronic signing by customer. Payment ID: ', 'woocommerce-gateway-klarna' ) . $collector_order['data']['purchase']['purchaseIdentifier'] );
			update_post_meta( $order->get_id(), '_transaction_id', $collector_order['data']['purchase']['purchaseIdentifier'] );
			$order->update_status( 'on-hold' );
			CCO_WC()->logger::log( 'Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to On hold.' );
		} else {
			$order->add_order_note( __( 'Order is PENDING APPROVAL by Collector. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $collector_order['data']['purchase']['purchaseIdentifier'] );
			$order->update_status( 'on-hold' );
			CCO_WC()->logger::log( 'Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to On hold.' );
		}
	}

	/**
	 * Check order totals
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param array    $collector_order The Collector order.
	 *
	 * @return void
	 */
	public function check_order_totals( $order, $collector_order ) {
		// Check order total and compare it with Woo.
		$woo_order_total       = intval( round( $order->get_total() * 100, 2 ) );
		$collector_order_total = intval( round( $collector_order['data']['order']['totalAmount'] * 100, 2 ) );
		if ( $woo_order_total > $collector_order_total && ( $woo_order_total - $collector_order_total ) > 3 ) {
			// translators: Order total.
			$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review. WooCommerce order total and Collector order total do not match. Collector order total: %s.', 'collector-checkout-for-woocommerce' ), $collector_order_total ) );
			CCO_WC()->logger::log( 'Order total missmatch in order:' . $order->get_order_number() . '. Woo order total: ' . $woo_order_total . '. Collector order total: ' . $collector_order_total );
		} elseif ( $collector_order_total > $woo_order_total && ( $collector_order_total - $woo_order_total ) > 3 ) {
			// translators: Order total notice..
			$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review. WooCommerce order total and Collector order total do not match. Collector order total: %s.', 'collector-checkout-for-woocommerce' ), $collector_order_total ) );
			CCO_WC()->logger::log( 'Order total mismatch in order:' . $order->get_order_number() . '. Woo order total: ' . $woo_order_total . '. Collector order total: ' . $collector_order_total );
		}
	}


	/**
	 *
	 * Update the Collector Order with the WooCommerce Order number
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param string   $customer_type The customer type.
	 * @param string   $private_id The private id.
	 *
	 * @return void
	 */
	public function update_order_reference_in_collector( $order, $customer_type, $private_id ) {
		$update_reference = new Collector_Checkout_Requests_Update_Reference( $order->get_order_number(), $private_id, $customer_type );
		$update_reference->request();
		CCO_WC()->logger::log( 'Update Collector order reference for order - ' . $order->get_order_number() );
	}

	/**
	 * Checks if we have a session id set.
	 *
	 * @return void
	 */
	public function check_session_id() {
		if ( ! isset( $this->db_session_id ) ) {
			$this->order_is_valid                            = false;
			$this->validation_messages['missing_session_id'] = __( 'No session ID detected.', 'collector-checkout-for-woocommerce' );
		}
	}

	/**
	 * Check cart coupons for errors.
	 *
	 * @return void
	 */
	public function check_cart_coupons() {
		foreach ( WC()->cart->get_applied_coupons() as $code ) {
			$coupon = new WC_Coupon( $code );
			if ( ! $coupon->is_valid() ) {
				$this->order_is_valid                      = false;
				$this->validation_messages['coupon_error'] = WC_Coupon::E_WC_COUPON_INVALID_REMOVED;
			}
		}
	}

	/**
	 * Checks for any WooCommerce error notices from the session.
	 *
	 * @return void
	 */
	public function check_woo_notices() {
		$errors = wc_get_notices( 'error' );
		if ( ! empty( $errors ) ) {
			$this->order_is_valid = false;
			foreach ( $errors as $error ) {
				$this->validation_messages['wc_notice'] = $error;
			}
		}
	}


	/**
	 * Checks if all cart items are still in stock.
	 *
	 * @return void
	 */
	public function check_all_in_stock() {
		$stock_check = WC()->cart->check_cart_item_stock();
		if ( true !== $stock_check ) {
			$this->order_is_valid                      = false;
			$this->validation_messages['amount_error'] = __( 'Not all items are in stock.', 'collector-checkout-for-woocommerce' );
		}
	}

	/**
	 * Checks if Collector order total equals the current cart total.
	 *
	 * @return void
	 */
	public function check_order_amount() {
		$collector_total = $this->get_collector_total();
		$woo_total       = floatval( WC()->cart->get_total( 'collector_validation' ) );
		if ( $woo_total > $collector_total && ( $woo_total - $collector_total ) > 3 ) {
			$this->order_is_valid                             = false;
			$this->validation_messages['amount_error']        = __( 'Missmatch between the Collector and WooCommerce order total.', 'collector-checkout-for-woocommerce' );
			$this->validation_messages['amount_error_totals'] = 'Woo Total: ' . $woo_total . ' Collector total: ' . $collector_total;
		} elseif ( $collector_total > $woo_total && ( $collector_total - $woo_total ) > 3 ) {
			$this->order_is_valid                             = false;
			$this->validation_messages['amount_error']        = __( 'Missmatch between the Collector and WooCommerce order total.', 'collector-checkout-for-woocommerce' );
			$this->validation_messages['amount_error_totals'] = 'Woo Total: ' . $woo_total . ' Collector total: ' . $collector_total;
		}
	}

	/**
	 * Checks if the email exists as a user and if they are logged in.
	 *
	 * @return void
	 */
	public function check_if_user_exists_and_logged_in() {
		// Check customer type.
		$collector_email = '';
		if ( 'BusinessCustomer' === $this->collector_order['data']['customerType'] ) {
			$collector_email = $this->collector_order['data']['businessCustomer']['email'];
		} elseif ( 'PrivateCustomer' === $this->collector_order['data']['customerType'] ) {
			$collector_email = $this->collector_order['data']['customer']['email'];
		}

		// Check if the email exists as a user.
		$user = email_exists( $collector_email );
		// If not false, user exists. Check if the session id matches the User id.
		if ( false !== $user ) {
			if ( $user !== $this->db_session_id ) {
				$this->order_is_valid                    = false;
				$this->validation_messages['user_login'] = __( 'An account already exists with this email. Please login to complete the purchase.', 'collector-checkout-for-woocommerce' );
			}
		}
	}

	/**
	 * Get collector total amount.
	 *
	 * @return int
	 */
	public function get_collector_total() {
		$cart_total_amount = $this->collector_order['data']['cart']['totalAmount'];
		$cart_fees         = $this->collector_order['data']['fees'];
		$fee_total_amount  = 0;
		foreach ( $cart_fees as $cart_fee => $fee ) {
			if ( false === strpos( $fee['id'], 'invoicefee|' ) ) { // Invoice fee is not included in WC()->cart->get_total(). Therefore excluding it in this calculation.
				if ( is_numeric( $fee['unitPrice'] ) ) {
					$fee_total_amount += $fee['unitPrice'];
				}
			}
		}

		$collector_total = $fee_total_amount + $cart_total_amount;
		return floatval( round( $collector_total, 2 ) );
	}

	/**
	 * Sets the current user for the callback.
	 *
	 * @return void
	 */
	public function set_current_user() {
		if ( isset( $this->db_session_id ) ) {
			wp_set_current_user( $this->db_session_id );
		}
	}
}
Collector_Api_Callbacks::get_instance();
