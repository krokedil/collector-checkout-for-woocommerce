<?php  // phpcs:ignore
/**
 * Collector_Checkout_Confirmation class.
 *
 * @package CollectorCheckout/Classes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collector_Checkout_Confirmation class.
 *
 * Handles Collector Checkout confirmation page.
 */
class Collector_Checkout_Confirmation {

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
	 * Collector_Checkout_Confirmation constructor.
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'check_if_order_already_exist' ) );

		add_action( 'wp_head', array( $this, 'maybe_hide_checkout_form' ) );

		add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_populate_wc_checkout' ) );

		// Set fields to not required.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'collector_set_not_required' ), 20 );

		// Save Collector data (private id) in WC order.
		add_action( 'woocommerce_new_order', array( $this, 'save_collector_order_data' ) );

	}


	/**
	 * Populates WooCommerce checkout form in Collector confirmation page.
	 */
	public function check_if_order_already_exist() {
		if ( ! is_collector_confirmation() ) {
			return;
		}

		// Prevent duplicate orders if confirmation page is reloaded manually by customer.
		$collector_public_token = sanitize_key( $_GET['public-token'] );
		$query                  = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'collector_checkout',
				'date_created'   => '>' . ( time() - WEEK_IN_SECONDS ),
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
			Collector_Checkout::log( 'Confirmation page rendered but _collector_public_token already exist in this order: ' . $order_id_match );
			$order    = wc_get_order( $order_id_match );
			$location = $order->get_checkout_order_received_url();
            wp_redirect( $location ); // phpcs:ignore
			exit;
		} else {
			CCO_WC()->logger->log( 'Confirmation page rendered for public token - ' . $collector_public_token );
		}
	}

	/**
	 * Hides WooCommerce checkout form in confirmation page.
	 */
	public function maybe_hide_checkout_form() {
		if ( ! is_collector_confirmation() ) {
			return;
		}

		echo '<style>form.woocommerce-checkout,div.woocommerce-info{display:none!important}</style>';
	}


	/**
	 * Populates WooCommerce checkout form in Collector confirmation page.
	 */
	public function maybe_populate_wc_checkout( $checkout ) {
		if ( ! is_collector_confirmation() ) {
			return;
		}

		$collector_public_token = sanitize_key( $_GET['public-token'] );

		echo '<div id="collector-confirm-loading"></div>';

		$private_id    = WC()->session->get( 'collector_private_id' );
		$customer_type = WC()->session->get( 'collector_customer_type' );

		$customer_data   = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$collector_order = $customer_data->request();

		if ( 'PurchaseCompleted' === $collector_order['data']['status'] ) {
			// Save the payment method and payment id
			$payment_method = $collector_order['data']['purchase']['paymentName'];
			$payment_id     = $collector_order['data']['purchase']['purchaseIdentifier'];
			WC()->session->set( 'collector_payment_method', $payment_method );
			WC()->session->set( 'collector_payment_id', $payment_id );

			$customer_data = wc_collector_verify_customer_data( $collector_order );

			$this->save_customer_data( $customer_data );

		} else {
			// We didn't get a status PurchaseCompleted from Collector (but the Collector redirectPageUri has been triggered) so we redirect the customer to thank you page
			$url = add_query_arg(
				array(
					'purchase-status' => 'not-completed',
					'public-token'    => sanitize_text_field( $_POST['public_token'] ),
				),
				wc_get_endpoint_url( 'order-received', '', get_permalink( wc_get_page_id( 'checkout' ) ) )
			);

			Collector_Checkout::log( 'Payment complete triggered for private id ' . $private_id . ' but status is not PurchaseCompleted in Collectors system. Current status: ' . var_export( $collector_order['data']['status'], true ) . '. Redirecting customer to simplified thankyou page.' );

			wp_safe_redirect( $url );
			exit;
		}

	}

	/**
	 * Saves customer data from Collector order into WC()->customer.
	 *
	 * @param $klarna_order
	 */
	private function save_customer_data( $customer_data ) {
		if ( is_user_logged_in() ) {
			// Load customer object, if user is logged in.
			WC()->customer = new WC_Customer( get_current_user_id() );
		}
		// First name.
		WC()->customer->set_billing_first_name( sanitize_text_field( $customer_data['billingFirstName'] ) );
		WC()->customer->set_shipping_first_name( sanitize_text_field( $customer_data['shippingFirstName'] ) );

		// Last name.
		WC()->customer->set_billing_last_name( sanitize_text_field( $customer_data['billingLastName'] ) );
		WC()->customer->set_shipping_last_name( sanitize_text_field( $customer_data['shippingLastName'] ) );

		// Country.
		WC()->customer->set_billing_country( strtoupper( sanitize_text_field( $customer_data['countryCode'] ) ) );
		WC()->customer->set_shipping_country( strtoupper( sanitize_text_field( $customer_data['countryCode'] ) ) );

		// Street address 1.
		WC()->customer->set_billing_address_1( sanitize_text_field( $customer_data['billingAddress'] ) );
		WC()->customer->set_shipping_address_1( sanitize_text_field( $customer_data['shippingAddress'] ) );

		// Street address 2.
		if ( isset( $customer_data['billingAddress2'] ) ) {
			WC()->customer->set_billing_address_2( sanitize_text_field( $customer_data['billingAddress2'] ) );
			WC()->customer->set_shipping_address_2( sanitize_text_field( $customer_data['shippingAddress2'] ) );
		}

		// Company Name.
		if ( isset( $customer_data['billingCompanyName'] ) ) {
			WC()->customer->set_billing_company( sanitize_text_field( $customer_data['billingCompanyName'] ) );
			WC()->customer->set_shipping_company( sanitize_text_field( $customer_data['shippingCompanyName'] ) );
		}

		// City.
		WC()->customer->set_billing_city( sanitize_text_field( $customer_data['billingCity'] ) );
		WC()->customer->set_shipping_city( sanitize_text_field( $customer_data['shippingCity'] ) );

		// Postcode.
		WC()->customer->set_billing_postcode( sanitize_text_field( $customer_data['billingPostalCode'] ) );
		WC()->customer->set_shipping_postcode( sanitize_text_field( $customer_data['shippingPostalCode'] ) );

		// Phone.
		WC()->customer->set_billing_phone( sanitize_text_field( $customer_data['phone'] ) );

		// Email.
		WC()->customer->set_billing_email( sanitize_text_field( $customer_data['email'] ) );

		WC()->customer->save();
	}

	/**
	 * When checking out using Collector Checkout, we need to make sure none of the WooCommerce are required, in case Collector
	 * does not return info for some of them.
	 *
	 * @param array $checkout_fields WooCommerce checkout fields.
	 *
	 * @return array
	 */
	public function collector_set_not_required( $checkout_fields ) {
		// Set fields to not required, to prevent orders from failing
		if ( 'collector_checkout' === WC()->session->get( 'chosen_payment_method' ) ) {
			foreach ( $checkout_fields as $fieldset_key => $fieldset ) {
				foreach ( $fieldset as $field_key => $field ) {
					$checkout_fields[ $fieldset_key ][ $field_key ]['required'] = false;
				}
			}
		}
		return $checkout_fields;
	}


	/**
	 * Saves Collector data to WooCommerce order as meta field.
	 *
	 * @param string $order_id WooCommerce order id.
	 */
	public function save_collector_order_data( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'collector_checkout' !== $order->get_payment_method() ) {
			return;
		}

		if ( method_exists( WC()->session, 'get' ) ) {

			if ( WC()->session->get( 'collector_private_id' ) ) {

				Collector_Checkout::log( 'Saving Collector meta data for private id ' . WC()->session->get( 'collector_private_id' ) . ' in order id ' . $order_id );

				update_post_meta( $order_id, '_collector_customer_type', WC()->session->get( 'collector_customer_type' ) );
				update_post_meta( $order_id, '_collector_public_token', WC()->session->get( 'collector_public_token' ) );
				update_post_meta( $order_id, '_collector_private_id', WC()->session->get( 'collector_private_id' ) );
			}

			if ( null !== WC()->session->get( 'collector_customer_order_note' ) ) {
				$order->set_customer_note( sanitize_text_field( WC()->session->get( 'collector_customer_order_note' ) ) );
				$order->save();
			}
		}
	}

}

Collector_Checkout_Confirmation::get_instance();
