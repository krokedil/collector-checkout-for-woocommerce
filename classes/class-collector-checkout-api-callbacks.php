<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Collector_Api_Callbacks class.
 *
 * Class that handles Collector API callbacks.
 */
class Collector_Api_Callbacks {

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
		add_action( 'collector_check_for_order', array( $this, 'collector_check_for_order_callback' ), 10, 3 );

	}

	public function collector_check_for_order_callback( $private_id, $public_token, $customer_type = 'b2c' ) {
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
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
				// Check order status & order total
				Collector_Checkout::log( 'API-callback hit. Private id ' . $private_id . '. already exist in order ID ' . $order_id_match . ' Checking order status...' );
				$this->check_order_status( $private_id, $public_token, $customer_type, $order );
			} else {
				// No order, why?
				Collector_Checkout::log( 'API-callback hit. Private id ' . $private_id . '. already exist in order ID ' . $order_id_match . '. But we could not instantiate an order object' );
			}
		} else {
			// No order found - create a new
			Collector_Checkout::log( 'API-callback hit. We could NOT find Private id ' . $private_id . '(with public token ' . $public_token . ' & customer type ' . $customer_type . '). Starting backup order creation...' );
			$this->backup_order_creation( $private_id, $public_token, $customer_type );
		}

	}

	/**
	 * Check order status order total and transaction id, in case checkout process failed.
	 *
	 * @param string $private_id, $public_token, $customer_type.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	public function check_order_status( $private_id, $public_token, $customer_type, $order ) {
		$response        = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type, $order->get_currency() );
		$response        = $response->request();
		$collector_order = json_decode( $response );

		if ( is_object( $order ) ) {

			// Check order status
			if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
				// Set order status in Woo
				$this->set_order_status( $order, $collector_order );
			}

			// Compare order totals between the orders
			$this->check_order_totals( $order, $collector_order );

			// Check if we need to update reference in collectors system
			if ( empty( $collector_order->data->reference ) ) {
				$this->update_order_reference_in_collector( $order, $customer_type, $private_id );
			}
		}
	}

	/**
	 * Backup order creation, in case checkout process failed.
	 *
	 * @param string $private_id, $public_token, $customer_type.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	public function backup_order_creation( $private_id, $public_token, $customer_type ) {
		$response        = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$response        = $response->request();
		$collector_order = json_decode( $response );

		// Process order.
		$order = $this->process_order( $collector_order, $private_id, $public_token, $customer_type );

		// Check order total.
		$this->check_order_totals( $order, $collector_order );

		// Send order number to Collector
		if ( is_object( $order ) ) {
			$this->update_order_reference_in_collector( $order, $customer_type, $private_id );
		}
	}


	/**
	 * Processes WooCommerce order on backup order creation.
	 *
	 * @param Klarna_Checkout_Order $collector_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function process_order( $collector_order, $private_id, $public_token, $customer_type ) {
		$fallback_postcode = '11111';
		if ( 'BusinessCustomer' == $collector_order->data->customerType ) {
			$billing_first_name   = isset( $collector_order->data->businessCustomer->firstName ) ? $collector_order->data->businessCustomer->firstName : '.';
			$billing_last_name    = isset( $collector_order->data->businessCustomer->lastName ) ? $collector_order->data->businessCustomer->lastName : '.';
			$billing_company      = isset( $collector_order->data->businessCustomer->companyName ) ? $collector_order->data->businessCustomer->companyName : '.';
			$billing_address      = isset( $collector_order->data->businessCustomer->invoiceAddress->address ) ? $collector_order->data->businessCustomer->invoiceAddress->address : '.';
			$billing_address2     = isset( $collector_order->data->businessCustomer->invoiceAddress->address2 ) ? $collector_order->data->businessCustomer->invoiceAddress->address2 : '';
			$billing_postal_code  = isset( $collector_order->data->businessCustomer->invoiceAddress->postalCode ) ? $collector_order->data->businessCustomer->invoiceAddress->postalCode : $fallback_postcode;
			$billing_city         = isset( $collector_order->data->businessCustomer->invoiceAddress->city ) ? $collector_order->data->businessCustomer->invoiceAddress->city : '.';
			$billing_country      = isset( $collector_order->countryCode ) ? $collector_order->countryCode : WC()->countries->get_base_country();
			$shipping_first_name  = isset( $collector_order->data->businessCustomer->firstName ) ? $collector_order->data->businessCustomer->firstName : '.';
			$shipping_last_name   = isset( $collector_order->data->businessCustomer->lastName ) ? $collector_order->data->businessCustomer->lastName : '.';
			$shipping_company     = isset( $collector_order->data->businessCustomer->deliveryAddress->companyName ) ? $collector_order->data->businessCustomer->deliveryAddress->companyName : isset( $collector_order->data->businessCustomer->companyName ) ? $collector_order->data->businessCustomer->companyName : '.';
			$shipping_address     = isset( $collector_order->data->businessCustomer->deliveryAddress->address ) ? $collector_order->data->businessCustomer->deliveryAddress->address : '.';
			$shipping_address2    = isset( $collector_order->data->businessCustomer->deliveryAddress->address2 ) ? $collector_order->data->businessCustomer->deliveryAddress->address2 : '';
			$shipping_postal_code = isset( $collector_order->data->businessCustomer->deliveryAddress->postalCode ) ? $collector_order->data->businessCustomer->deliveryAddress->postalCode : isset( $collector_order->data->businessCustomer->invoiceAddress->postalCode ) ? $collector_order->data->businessCustomer->invoiceAddress->postalCode : $fallback_postcode;
			$shipping_city        = isset( $collector_order->data->businessCustomer->deliveryAddress->city ) ? $collector_order->data->businessCustomer->deliveryAddress->city : isset( $collector_order->data->businessCustomer->invoiceAddress->city ) ? $collector_order->data->businessCustomer->invoiceAddress->city : '.';
			$shipping_country     = isset( $collector_order->countryCode ) ? $collector_order->countryCode : WC()->countries->get_base_country();

			$phone = isset( $collector_order->data->businessCustomer->mobilePhoneNumber ) ? $collector_order->data->businessCustomer->mobilePhoneNumber : '.';
			$email = isset( $collector_order->data->businessCustomer->email ) ? $collector_order->data->businessCustomer->email : '.';

			$org_nr = isset( $collector_order->data->businessCustomer->organizationNumber ) ? $collector_order->data->businessCustomer->organizationNumber : '.';
		} else {
			$shipping_first_name  = isset( $collector_order->data->customer->deliveryAddress->firstName ) ? $collector_order->data->customer->deliveryAddress->firstName : '.';
			$shipping_last_name   = isset( $collector_order->data->customer->deliveryAddress->lastName ) ? $collector_order->data->customer->deliveryAddress->lastName : '.';
			$shipping_address     = isset( $collector_order->data->customer->deliveryAddress->address ) ? $collector_order->data->customer->deliveryAddress->address : '.';
			$shipping_address2    = isset( $collector_order->data->customer->deliveryAddress->address2 ) ? $collector_order->data->customer->deliveryAddress->address2 : '';
			$shipping_postal_code = isset( $collector_order->data->customer->deliveryAddress->postalCode ) ? $collector_order->data->customer->deliveryAddress->postalCode : $fallback_postcode;
			$shipping_city        = isset( $collector_order->data->customer->deliveryAddress->city ) ? $collector_order->data->customer->deliveryAddress->city : '.';
			$shipping_country     = isset( $collector_order->countryCode ) ? $collector_order->countryCode : WC()->countries->get_base_country();
			$billing_first_name   = isset( $collector_order->data->customer->billingAddress->firstName ) ? $collector_order->data->customer->billingAddress->firstName : isset( $collector_order->data->customer->deliveryAddress->firstName ) ? $collector_order->data->customer->deliveryAddress->firstName : '.';
			$billing_last_name    = isset( $collector_order->data->customer->billingAddress->lastName ) ? $collector_order->data->customer->billingAddress->lastName : isset( $collector_order->data->customer->deliveryAddress->lastName ) ? $collector_order->data->customer->deliveryAddress->lastName : '.';
			$billing_address      = isset( $collector_order->data->customer->billingAddress->address ) ? $collector_order->data->customer->billingAddress->address : isset( $collector_order->data->customer->deliveryAddress->address ) ? $collector_order->data->customer->deliveryAddress->address : '.';
			$billing_address2     = isset( $collector_order->data->customer->billingAddress->address2 ) ? $collector_order->data->customer->billingAddress->address2 : isset( $collector_order->data->customer->deliveryAddress->address2 ) ? $collector_order->data->customer->deliveryAddress->address2 : '';
			$billing_postal_code  = isset( $collector_order->data->customer->billingAddress->postalCode ) ? $collector_order->data->customer->billingAddress->postalCode : isset( $collector_order->data->customer->deliveryAddress->postalCode ) ? $collector_order->data->customer->deliveryAddress->postalCode : $fallback_postcode;
			$billing_city         = isset( $collector_order->data->customer->billingAddress->city ) ? $collector_order->data->customer->billingAddress->city : isset( $collector_order->data->customer->deliveryAddress->city ) ? $collector_order->data->customer->deliveryAddress->city : '.';
			$billing_country      = isset( $collector_order->countryCode ) ? $collector_order->countryCode : WC()->countries->get_base_country();

			$phone = isset( $collector_order->data->customer->mobilePhoneNumber ) ? $collector_order->data->customer->mobilePhoneNumber : '.';
			$email = isset( $collector_order->data->customer->email ) ? $collector_order->data->customer->email : '.';
		}

		$order = wc_create_order( array( 'status' => 'pending' ) );

		if ( is_wp_error( $order ) ) {
			Collector_Checkout::log( 'Backup order creation. Error - could not create order. ' . var_export( $order->get_error_message(), true ) );
		} else {
			Collector_Checkout::log( 'Backup order creation - order ID - ' . $order->get_id() . ' - created.' );
		}

		$order_id = $order->get_id();

		$order->set_billing_first_name( sanitize_text_field( $billing_first_name ) );
		$order->set_billing_last_name( sanitize_text_field( $billing_last_name ) );
		$order->set_billing_country( sanitize_text_field( $billing_country ) );
		$order->set_billing_address_1( sanitize_text_field( $billing_address ) );
		$order->set_billing_address_2( sanitize_text_field( $billing_address2 ) );
		$order->set_billing_city( sanitize_text_field( $billing_city ) );
		$order->set_billing_postcode( sanitize_text_field( $billing_postal_code ) );
		$order->set_billing_phone( sanitize_text_field( $phone ) );
		$order->set_billing_email( sanitize_text_field( $email ) );
		$order->set_shipping_first_name( sanitize_text_field( $shipping_first_name ) );
		$order->set_shipping_last_name( sanitize_text_field( $shipping_last_name ) );
		$order->set_shipping_country( sanitize_text_field( $shipping_country ) );
		$order->set_shipping_address_1( sanitize_text_field( $shipping_address ) );
		$order->set_shipping_address_2( sanitize_text_field( $shipping_address2 ) );
		$order->set_shipping_city( sanitize_text_field( $shipping_city ) );
		$order->set_shipping_postcode( sanitize_text_field( $shipping_postal_code ) );

		// Company specific info
		if ( 'BusinessCustomer' == $collector_order->data->customerType ) {
			$order->set_billing_company( sanitize_text_field( $billing_company ) );
			$order->set_shipping_company( sanitize_text_field( $shipping_company ) );
			update_post_meta( $order_id, '_collector_org_nr', $org_nr );
		}

		$order->set_created_via( 'collector_checkout_api' );
		$order->set_currency( sanitize_text_field( get_woocommerce_currency() ) );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );

		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['collector_checkout'];
		$order->set_payment_method( $payment_method );
		$order->add_order_note( __( 'Order created via Collector Checkout API callback. Please verify the order in Collectors system.', 'collector-checkout-for-woocommerce' ) );

		foreach ( $collector_order->data->order->items as $cart_item ) {
			if ( strpos( $cart_item->id, 'shipping|' ) !== false ) {

				// Shipping
				$trimmed_cart_item_id = str_replace( 'shipping|', '', $cart_item->id );
				if ( $cart_item->vat > 0 ) {
					$price_excl_vat = $cart_item->unitPrice / ( ( $cart_item->vat * 0.01 ) + 1 );
				} else {
					$price_excl_vat = $cart_item->unitPrice;
				}
				$rate = new WC_Shipping_Rate( $trimmed_cart_item_id, $cart_item->description, $price_excl_vat, array(), 'flat_rate' );
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
				update_post_meta( $order->get_id(), '_collector_shipping_reference', $cart_item->id );

			} elseif ( strpos( $cart_item->id, 'invoicefee|' ) !== false ) {

				// Invoice fee
				$trimmed_cart_item_id = str_replace( 'invoicefee|', '', $cart_item->id );
				$id                   = wc_get_product_id_by_sku( $trimmed_cart_item_id );

				if ( 0 == $id ) {
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
				}
			} else {

				// Product items
				$id = wc_get_product_id_by_sku( $cart_item->id );

				if ( 0 == $id ) {
					$id = $cart_item->id;
				}
				$product = wc_get_product( $id );
				$order->add_product( $product, $cart_item->quantity );
			}
		}

		// Make sure to run Sequential Order numbers if plugin exsists
		if ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
			$sequential = new WC_Seq_Order_Number_Pro();
			$sequential->set_sequential_order_number( $order_id );
		} elseif ( class_exists( 'WC_Seq_Order_Number' ) ) {
			$sequential = new WC_Seq_Order_Number();
			$sequential->set_sequential_order_number( $order_id, get_post( $order_id ) );
		}

		update_post_meta( $order_id, '_collector_payment_method', $collector_order->data->purchase->paymentMethod );
		update_post_meta( $order_id, '_collector_payment_id', $collector_order->data->purchase->purchaseIdentifier );
		update_post_meta( $order_id, '_collector_customer_type', $customer_type );
		update_post_meta( $order_id, '_collector_public_token', $public_token );
		update_post_meta( $order_id, '_collector_private_id', $private_id );
		$order->add_order_note( sprintf( __( 'Purchase via %s', 'collector-checkout-for-woocommerce' ), wc_collector_get_payment_method_name( $collector_order->data->purchase->paymentMethod ) ) );
		$order->calculate_totals();
		$order->save();

		// Set order status in Woo
		$this->set_order_status( $order, $collector_order );

		// Check order total and compare it with Woo
		$this->set_order_status( $order, $collector_order );

		return $order;
	}


	/**
	 * Set order status function
	 */
	public function set_order_status( $order, $collector_order ) {
		if ( 'Preliminary' === $collector_order->data->purchase->result ) {
			$order->payment_complete( $collector_order->data->purchase->purchaseIdentifier );
			$order->add_order_note( 'Payment via Collector Checkout. Payment ID: ' . sanitize_key( $collector_order->data->purchase->purchaseIdentifier ) );
			Collector_Checkout::log( 'Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to Processing/Completed.' );
		} else {
			$order->add_order_note( __( 'Order is PENDING APPROVAL by Collector. Payment ID: ', 'collector-checkout-for-woocommerce' ) . $collector_order->data->purchase->purchaseIdentifier );
			$order->update_status( 'on-hold' );
			Collector_Checkout::log( 'Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to On hold.' );
		}
	}

	/**
	 * Check order totals
	 */
	public function check_order_totals( $order, $collector_order ) {
		// Check order total and compare it with Woo
		$woo_order_total       = intval( round( $order->get_total() ) );
		$collector_order_total = $collector_order->data->order->totalAmount;
		if ( $woo_order_total > $collector_order_total && ( $woo_order_total - $collector_order_total ) > 3 ) {
			$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review. WooCommerce order total and Collector order total do not match. Collector order total: %s.', 'collector-checkout-for-woocommerce' ), $collector_order_total ) );
			Collector_Checkout::log( 'Order total missmatch in order:' . $order->get_order_number() . '. Woo order total: ' . $woo_order_total . '. Collector order total: ' . $collector_order_total );
		} elseif ( $collector_order_total > $woo_order_total && ( $collector_order_total - $woo_order_total ) > 3 ) {
			$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review. WooCommerce order total and Collector order total do not match. Collector order total: %s.', 'collector-checkout-for-woocommerce' ), $collector_order_total ) );
			Collector_Checkout::log( 'Order total missmatch in order:' . $order->get_order_number() . '. Woo order total: ' . $woo_order_total . '. Collector order total: ' . $collector_order_total );
		}
	}


	/**
	 * Update the Collector Order with the WooCommerce Order number
	 */
	public function update_order_reference_in_collector( $order, $customer_type, $private_id ) {
		$update_reference = new Collector_Checkout_Requests_Update_Reference( $order->get_order_number(), $private_id, $customer_type );
		$update_reference->request();
		Collector_Checkout::log( 'Update Collector order reference for order - ' . $order->get_order_number() );
	}
}
Collector_Api_Callbacks::get_instance();
