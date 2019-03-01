<?php
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
		add_action( 'wp_head', array( $this, 'maybe_hide_checkout_form' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_populate_wc_checkout' ) );
		add_action( 'wp_footer', array( $this, 'maybe_submit_wc_checkout' ), 999 );

		// Set fields to not required.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'collector_set_not_required' ), 20 );

		// Remove the storefront sticky checkout.
		add_action( 'wp_enqueue_scripts', array( $this, 'jk_remove_sticky_checkout' ), 99 );

		// add_filter( 'woocommerce_checkout_posted_data', array( $this, 'unrequire_posted_data' ), 99 );
		// Save Collector data (private id) in WC order
		add_action( 'woocommerce_new_order', array( $this, 'save_collector_order_data' ) );

	}

	/**
	 * Hides WooCommerce checkout form in confirmation page.
	 */
	public function maybe_hide_checkout_form() {
		if ( ! $this->is_collector_confirmation() ) {
			return;
		}

		echo '<style>form.woocommerce-checkout,div.woocommerce-info{display:none!important}</style>';
	}

	/**
	 * Populates WooCommerce checkout form in Collector confirmation page.
	 */
	public function maybe_populate_wc_checkout( $checkout ) {
		if ( ! $this->is_collector_confirmation() ) {
			return;
		}

		echo '<div id="collector-confirm-loading"></div>';

		$private_id    = WC()->session->get( 'collector_private_id' );
		$customer_type = WC()->session->get( 'collector_customer_type' );

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

			$this->save_customer_data( $return );

		} else {
			// We didn't get a status PurchaseCompleted from Collector (but the Collector redirectPageUri has been triggered) so we redirect the customer to thank you page
			$url = add_query_arg(
				array(
					'purchase-status' => 'not-completed',
					'public-token'    => sanitize_text_field( $_POST['public_token'] ),
				), wc_get_endpoint_url( 'order-received', '', get_permalink( wc_get_page_id( 'checkout' ) ) )
			);

			Collector_Checkout::log( 'Payment complete triggered for private id ' . $private_id . ' but status is not PurchaseCompleted in Collectors system. Current status: ' . var_export( $decoded_json->data->status, true ) . '. Redirecting customer to simplified thankyou page.' );

			wp_safe_redirect( $url );
			exit;
		}

	}

	/**
	 * Submits WooCommerce checkout form in Collector confirmation page.
	 */
	public function maybe_submit_wc_checkout() {
		if ( ! $this->is_collector_confirmation() ) {
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
				'date_created'   => '>' . ( time() - DAY_IN_SECONDS ),
			)
		);
		$orders          = $query->get_orders();
		$order_id_match  = null;
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
			wp_safe_redirect( $location );
			exit;
		}
		?>

		<script>
			var collector_text = '<?php echo __( 'Please wait while we process your order.', 'collector-checkout-for-woocommerce' ); ?>';
			jQuery(function ($) {
				$( 'body' ).append( $( '<div class="collector-modal"><div class="collector-modal-content">' + collector_text + '</div></div>' ) );
				$('input#terms').prop('checked', true);
				$('input#ship-to-different-address-checkbox').prop('checked', true);

				$('.validate-required').removeClass('validate-required');
				$('form.woocommerce-checkout').submit();
				console.log('yes submitted');
				$('form.woocommerce-checkout').addClass( 'processing' );
				console.log('processing class added to form');
			});
		</script>
		<?php
	}

	/**
	 * Checks if in Collector confirmation page.
	 *
	 * @return bool
	 */
	private function is_collector_confirmation() {
		if ( isset( $_GET['payment_successful'] ) && 1 == $_GET['payment_successful'] && isset( $_GET['public-token'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Saves customer data from Collector order into WC()->customer.
	 *
	 * @param $klarna_order
	 */
	private function save_customer_data( $formated_customer_data ) {
		// First name.
		WC()->customer->set_billing_first_name( sanitize_text_field( $formated_customer_data['customer_data']['billingFirstName'] ) );
		WC()->customer->set_shipping_first_name( sanitize_text_field( $formated_customer_data['customer_data']['shippingFirstName'] ) );

		// Last name.
		WC()->customer->set_billing_last_name( sanitize_text_field( $formated_customer_data['customer_data']['billingLastName'] ) );
		WC()->customer->set_shipping_last_name( sanitize_text_field( $formated_customer_data['customer_data']['shippingLastName'] ) );

		// Country.
		WC()->customer->set_billing_country( strtoupper( sanitize_text_field( $formated_customer_data['customer_data']['countryCode'] ) ) );
		WC()->customer->set_shipping_country( strtoupper( sanitize_text_field( $formated_customer_data['customer_data']['countryCode'] ) ) );

		// Street address 1.
		WC()->customer->set_billing_address_1( sanitize_text_field( $formated_customer_data['customer_data']['billingAddress'] ) );
		WC()->customer->set_shipping_address_1( sanitize_text_field( $formated_customer_data['customer_data']['shippingAddress'] ) );

		// Street address 2.
		if ( isset( $formated_customer_data['customer_data']['billingAddress2'] ) ) {
			WC()->customer->set_billing_address_2( sanitize_text_field( $formated_customer_data['customer_data']['billingAddress2'] ) );
			WC()->customer->set_shipping_address_2( sanitize_text_field( $formated_customer_data['customer_data']['shippingAddress2'] ) );
		}

		// Company Name.
		if ( isset( $formated_customer_data['customer_data']['billingCompanyName'] ) ) {
			WC()->customer->set_billing_company( sanitize_text_field( $formated_customer_data['customer_data']['billingCompanyName'] ) );
			WC()->customer->set_shipping_company( sanitize_text_field( $formated_customer_data['customer_data']['shippingCompanyName'] ) );
		}

		// City.
		WC()->customer->set_billing_city( sanitize_text_field( $formated_customer_data['customer_data']['billingCity'] ) );
		WC()->customer->set_shipping_city( sanitize_text_field( $formated_customer_data['customer_data']['shippingCity'] ) );

		// Postcode.
		WC()->customer->set_billing_postcode( sanitize_text_field( $formated_customer_data['customer_data']['billingPostalCode'] ) );
		WC()->customer->set_shipping_postcode( sanitize_text_field( $formated_customer_data['customer_data']['shippingPostalCode'] ) );

		// Phone.
		WC()->customer->set_billing_phone( sanitize_text_field( $formated_customer_data['customer_data']['phone'] ) );

		// Email.
		WC()->customer->set_billing_email( sanitize_text_field( $formated_customer_data['customer_data']['email'] ) );

		WC()->customer->save();
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

			$billing_first_name  = isset( $customer_data['customer_data']->data->customer->billingAddress->firstName ) ? $customer_data['customer_data']->data->customer->billingAddress->firstName : isset( $customer_data['customer_data']->data->customer->deliveryAddress->firstName ) ? $customer_data['customer_data']->data->customer->deliveryAddress->firstName : '.';
			$billing_last_name   = isset( $customer_data['customer_data']->data->customer->billingAddress->lastName ) ? $customer_data['customer_data']->data->customer->billingAddress->lastName : isset( $customer_data['customer_data']->data->customer->deliveryAddress->lastName ) ? $customer_data['customer_data']->data->customer->deliveryAddress->lastName : '.';
			$billing_address     = isset( $customer_data['customer_data']->data->customer->billingAddress->address ) ? $customer_data['customer_data']->data->customer->billingAddress->address : isset( $customer_data['customer_data']->data->customer->deliveryAddress->address ) ? $customer_data['customer_data']->data->customer->deliveryAddress->address : '.';
			$billing_address2    = isset( $customer_data['customer_data']->data->customer->billingAddress->address2 ) ? $customer_data['customer_data']->data->customer->billingAddress->address2 : isset( $customer_data['customer_data']->data->customer->deliveryAddress->address2 ) ? $customer_data['customer_data']->data->customer->deliveryAddress->address2 : '';
			$billing_postal_code = isset( $customer_data['customer_data']->data->customer->billingAddress->postalCode ) ? $customer_data['customer_data']->data->customer->billingAddress->postalCode : isset( $customer_data['customer_data']->data->customer->deliveryAddress->postalCode ) ? $customer_data['customer_data']->data->customer->deliveryAddress->postalCode : $fallback_postcode;
			$billing_city        = isset( $customer_data['customer_data']->data->customer->billingAddress->city ) ? $customer_data['customer_data']->data->customer->billingAddress->city : isset( $customer_data['customer_data']->data->customer->deliveryAddress->city ) ? $customer_data['customer_data']->data->customer->deliveryAddress->city : '.';

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

	/**
	 * When checking out using Collector Checkout, we need to make sure none of the WooCommerce are required, in case Collector
	 * does not return info for some of them.
	 *
	 * @param array $fields WooCommerce checkout fields.
	 *
	 * @return mixed
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

	public function jk_remove_sticky_checkout() {
		wp_dequeue_script( 'storefront-sticky-payment' );
	}


	/**
	 * Makes sure there's no empty data sent for validation.
	 *
	 * @param array $data Posted data.
	 *
	 * @return mixed
	 */
	public function unrequire_posted_data( $data ) {
		if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) ) {
			foreach ( $data as $key => $value ) {
				if ( '' === $value ) {
					unset( $data[ $key ] );
				}
			}
		}

		return $data;
	}


	/**
	 * Saves Collector data to WooCommerce order as meta field.
	 *
	 * @param string $order_id WooCommerce order id.
	 * @param array  $data  Posted data.
	 */
	public function save_collector_order_data( $order_id ) {
		if ( method_exists( WC()->session, 'get' ) ) {
			$order = wc_get_order( $order_id );

			if ( WC()->session->get( 'collector_private_id' ) ) {

				Collector_Checkout::log( 'Saving Collector meta data for private id ' . WC()->session->get( 'collector_private_id' ) . ' in order id ' . $order_id );

				update_post_meta( $order_id, '_collector_customer_type', WC()->session->get( 'collector_customer_type' ) );
				update_post_meta( $order_id, '_collector_public_token', WC()->session->get( 'collector_public_token' ) );
				update_post_meta( $order_id, '_collector_private_id', WC()->session->get( 'collector_private_id' ) );
			}

			if ( null != WC()->session->get( 'collector_customer_order_note' ) ) {
				$order->set_customer_note( sanitize_text_field( WC()->session->get( 'collector_customer_order_note' ) ) );
				$order->save();
			}
		}
	}

}

Collector_Checkout_Confirmation::get_instance();
