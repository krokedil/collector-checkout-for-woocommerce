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
		add_action( 'collector_check_for_order', array( $this, 'collector_check_for_order_callback' ), 10, 2 );

	}

	public function collector_check_for_order_callback( $private_id, $public_token, $customer_type = 'b2c' ) {
		error_log('collector_check_for_order_callback hit. $private_id ' . $private_id . '. $public_token ' . $public_token . '. $customer_type ' . $customer_type );
	    $query = new WC_Order_Query( array(
	        'limit' => -1,
	        'orderby' => 'date',
	        'order' => 'DESC',
	        'return' => 'ids',
	        'payment_method' => 'collector_checkout',
	        'date_created' => '>' . ( time() - 3600 )
	    ) );
	    $orders = $query->get_orders();
		error_log( 'collector_check_for_order_callback. Queried $orders: ' . var_export($orders, true));
		$order_id_match = '';
	    foreach( $orders as $order_id ) {
			
			$order_private_id = get_post_meta( $order_id, '_collector_private_id', true );
			error_log('$order_private_id ' . $order_private_id);
			error_log('$private_id' . $private_id );
	        if( $order_private_id === $private_id ) {
	            $order_id_match = $order_id;
	            break;
	        }
		}
		error_log( 'collector_check_for_order_callback. order_id_match: ' . $order_id_match);
	    // Did we get a match?
	    if( $order_id_match ) {
		    $order = wc_get_order( $order_id_match );
	        
	        if( $order ) {
		        $order->add_order_note( 'API-callback hit.' );
	        } else {
				// No order, why?
	        }
	    } else {
			// No order found - create a new
			$order = $this->backup_order_creation( $private_id, $customer_type );
			$order->add_order_note( 'Created via API-callback.' );
		}
	    
	}

	/**
	 * Backup order creation, in case checkout process failed.
	 *
	 * @param string $collector_order_id Klarna order ID.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	public function backup_order_creation( $private_id, $customer_type ) {
		$response 			= new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$response 			= $response->request();
		$collector_order 	= json_decode( $response );
		
		// Process customer data.
		$this->process_customer_data( $collector_order );
		// Process customer data.
		$this->process_cart( $collector_order );
		// Process order.
		$this->process_order( $collector_order );
	}
	/**
	 * Processes customer data on backup order creation.
	 *
	 * @param Klarna_Checkout_Order $collector_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function process_customer_data( $collector_order ) {
		
		error_log('$collector_order ' . var_export($collector_order, true ));
		
		
		$shipping_first_name    = isset( $collector_order->data->customer->deliveryAddress->firstName ) ? $collector_order->data->customer->deliveryAddress->firstName : '.';
		$shipping_last_name     = isset( $collector_order->data->customer->deliveryAddress->lastName ) ? $collector_order->data->customer->deliveryAddress->lastName : '.';
		$shipping_address       = isset( $collector_order->data->customer->deliveryAddress->address ) ? $collector_order->data->customer->deliveryAddress->address : '.';
		$shipping_address2      = isset( $collector_order->data->customer->deliveryAddress->address2 ) ? $collector_order->data->customer->deliveryAddress->address2 : '';
		$shipping_postal_code   = isset( $collector_order->data->customer->deliveryAddress->postalCode ) ? $collector_order->data->customer->deliveryAddress->postalCode : '';
		$shipping_city          = isset( $collector_order->data->customer->deliveryAddress->city ) ? $collector_order->data->customer->deliveryAddress->city : '.';
		$shipping_country       = isset( $collector_order->countryCode ) ? $collector_order->countryCode : WC()->countries->get_base_country();

		$billing_first_name     = isset( $collector_order->data->customer->billingAddress->firstName ) ? $collector_order->data->customer->billingAddress->firstName : isset( $collector_order->data->customer->deliveryAddress->firstName ) ? $collector_order->data->customer->deliveryAddress->firstName : '.';
		$billing_last_name      = isset( $collector_order->data->customer->billingAddress->lastName ) ? $collector_order->data->customer->billingAddress->lastName : isset( $collector_order->data->customer->deliveryAddress->lastName ) ? $collector_order->data->customer->deliveryAddress->lastName : '.';
		$billing_address        = isset( $collector_order->data->customer->billingAddress->address ) ? $collector_order->data->customer->billingAddress->address : isset( $collector_order->data->customer->deliveryAddress->address ) ? $collector_order->data->customer->deliveryAddress->address : '.';
		$billing_address2       = isset( $collector_order->data->customer->billingAddress->address2 ) ? $collector_order->data->customer->billingAddress->address2 : isset( $collector_order->data->customer->deliveryAddress->address2 ) ? $collector_order->data->customer->deliveryAddress->address2 : '';
		$billing_postal_code    = isset( $collector_order->data->customer->billingAddress->postalCode ) ? $collector_order->data->customer->billingAddress->postalCode : isset( $collector_order->data->customer->deliveryAddress->postalCode ) ? $collector_order->data->customer->deliveryAddress->postalCode : '';
		$billing_city           = isset( $collector_order->data->customer->billingAddress->city ) ? $collector_order->data->customer->billingAddress->city : isset( $collector_order->data->customer->deliveryAddress->city ) ? $collector_order->data->customer->deliveryAddress->city : '.';
		$billing_country       	= isset( $collector_order->countryCode ) ? $collector_order->countryCode : WC()->countries->get_base_country();

		$phone                  = isset( $collector_order->data->customer->mobilePhoneNumber ) ? $collector_order->data->customer->mobilePhoneNumber : '.';
		$email                  = isset( $collector_order->data->customer->email ) ? $collector_order->data->customer->email : 'test@example.com';
		
		
		/*
		$customer = new WC_Customer();

		// First name.
		$customer->set_billing_first_name( sanitize_text_field( $billing_first_name ) );
		$customer->set_shipping_first_name( sanitize_text_field( $shipping_first_name ) );
		// Last name.
		$customer->set_billing_last_name( sanitize_text_field( $billing_last_name ) );
		$customer->set_shipping_last_name( sanitize_text_field( $shipping_last_name ) );
		// Country.
		$customer->set_billing_country( sanitize_text_field( $billing_country ) );
		$customer->set_shipping_country( sanitize_text_field( $shipping_country ) );
		// Street address 1.
		$customer->set_billing_address_1( sanitize_text_field( $billing_address ) );
		$customer->set_shipping_address_1( sanitize_text_field(  $shipping_address) );
		// Street address 2.
		$customer->set_billing_address_2( sanitize_text_field( $billing_address2 ) );
		$customer->set_shipping_address_2( sanitize_text_field( $shipping_address2 ) );
		// City.
		$customer->set_billing_city( sanitize_text_field( $billing_city ) );
		$customer->set_shipping_city( sanitize_text_field( $shipping_city ) );
		// Postcode.
		$customer->set_billing_postcode( sanitize_text_field( $billing_postal_code ) );
		$customer->set_shipping_postcode( sanitize_text_field( $shipping_postal_code ) );
		// Phone.
		$customer->set_billing_phone( sanitize_text_field( $phone ) );
		// Email.
		$customer->set_billing_email( sanitize_text_field( $email ) );
		//$customer->set_billing_email( 'test@example2.com' );
		$customer->set_email( sanitize_text_field( $email ) );
		$customer->save();
		*/
		//$user = get_user_by( 'email', $email );

		if( empty( $user ) ) {
			WC()->customer = new WC_Customer();
			// First name.
			WC()->customer->set_billing_first_name( sanitize_text_field( $billing_first_name ) );
			WC()->customer->set_shipping_first_name( sanitize_text_field( $shipping_first_name ) );
			// Last name.
			WC()->customer->set_billing_last_name( sanitize_text_field( $billing_last_name ) );
			WC()->customer->set_shipping_last_name( sanitize_text_field( $shipping_last_name ) );
			// Country.
			WC()->customer->set_billing_country( sanitize_text_field( $billing_country ) );
			WC()->customer->set_shipping_country( sanitize_text_field( $shipping_country ) );
			// Street address 1.
			WC()->customer->set_billing_address_1( sanitize_text_field( $billing_address ) );
			WC()->customer->set_shipping_address_1( sanitize_text_field(  $shipping_address) );
			// Street address 2.
			WC()->customer->set_billing_address_2( sanitize_text_field( $billing_address2 ) );
			WC()->customer->set_shipping_address_2( sanitize_text_field( $shipping_address2 ) );
			// City.
			WC()->customer->set_billing_city( sanitize_text_field( $billing_city ) );
			WC()->customer->set_shipping_city( sanitize_text_field( $shipping_city ) );
			// Postcode.
			WC()->customer->set_billing_postcode( sanitize_text_field( $billing_postal_code ) );
			WC()->customer->set_shipping_postcode( sanitize_text_field( $shipping_postal_code ) );
			// Phone.
			WC()->customer->set_billing_phone( sanitize_text_field( $phone ) );
			// Email.
			WC()->customer->set_billing_email( sanitize_text_field( $email ) );
			WC()->customer->set_email( sanitize_text_field( $email ) );
			//WC()->customer->save();
		} else {
		//error_log('user ' . var_export($user, true ));
		//error_log('$user->ID ' . var_export($user->ID, true ));
		WC()->customer = new WC_Customer( $user->ID );
		WC()->customer->save();
		}

		
		error_log('WC()' . var_export(WC(), true ));
	}
	/**
	 * Processes cart contents on backup order creation.
	 *
	 * @param Klarna_Checkout_Order $collector_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function process_cart( $collector_order ) {
		WC()->cart = new WC_Cart();
		//WC()->cart->empty_cart();
		foreach ( $collector_order->data->order->items as $cart_item ) {
			//if ( 'physical' === $cart_item->type ) {
				WC()->cart->add_to_cart( $cart_item->id, $cart_item->quantity );
			//}
		}
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();
		// Check cart items (quantity, coupon validity etc).
		if ( ! WC()->cart->check_cart_items() ) {
			return;
		}
		WC()->cart->check_cart_coupons();
		error_log('WC()' . var_export(WC(), true ));
		/*
		$cart = new WC_Cart();
		foreach ( $collector_order->data->order->items as $cart_item ) {
			//if ( 'physical' === $cart_item->type ) {
				$cart->add_to_cart( $cart_item->id, $cart_item->quantity );
			//}
		}
		$cart->cart->calculate_shipping();
		$cart->cart->calculate_fees();
		$cart->cart->calculate_totals();
		// Check cart items (quantity, coupon validity etc).
		if ( ! $cart->cart->check_cart_items() ) {
			return;
		}
		$cart->cart->check_cart_coupons();
		*/
	}
	/**
	 * Processes WooCommerce order on backup order creation.
	 *
	 * @param Klarna_Checkout_Order $collector_order Klarna order.
	 *
	 * @throws Exception WC_Data_Exception.
	 */
	private function process_order( $collector_order ) {

		$shipping_first_name    = isset( $collector_order->data->customer->deliveryAddress->firstName ) ? $collector_order->data->customer->deliveryAddress->firstName : '.';
		$shipping_last_name     = isset( $collector_order->data->customer->deliveryAddress->lastName ) ? $collector_order->data->customer->deliveryAddress->lastName : '.';
		$shipping_address       = isset( $collector_order->data->customer->deliveryAddress->address ) ? $collector_order->data->customer->deliveryAddress->address : '.';
		$shipping_address2      = isset( $collector_order->data->customer->deliveryAddress->address2 ) ? $collector_order->data->customer->deliveryAddress->address2 : '';
		$shipping_postal_code   = isset( $collector_order->data->customer->deliveryAddress->postalCode ) ? $collector_order->data->customer->deliveryAddress->postalCode : $fallback_postcode;
		$shipping_city          = isset( $collector_order->data->customer->deliveryAddress->city ) ? $collector_order->data->customer->deliveryAddress->city : '.';
		$shipping_country       = isset( $collector_order->countryCode ) ? $collector_order->countryCode : WC()->countries->get_base_country();

		$billing_first_name     = isset( $collector_order->data->customer->billingAddress->firstName ) ? $collector_order->data->customer->billingAddress->firstName : isset( $collector_order->data->customer->deliveryAddress->firstName ) ? $collector_order->data->customer->deliveryAddress->firstName : '.';
		$billing_last_name      = isset( $collector_order->data->customer->billingAddress->lastName ) ? $collector_order->data->customer->billingAddress->lastName : isset( $collector_order->data->customer->deliveryAddress->lastName ) ? $collector_order->data->customer->deliveryAddress->lastName : '.';
		$billing_address        = isset( $collector_order->data->customer->billingAddress->address ) ? $collector_order->data->customer->billingAddress->address : isset( $collector_order->data->customer->deliveryAddress->address ) ? $collector_order->data->customer->deliveryAddress->address : '.';
		$billing_address2       = isset( $collector_order->data->customer->billingAddress->address2 ) ? $collector_order->data->customer->billingAddress->address2 : isset( $collector_order->data->customer->deliveryAddress->address2 ) ? $collector_order->data->customer->deliveryAddress->address2 : '';
		$billing_postal_code    = isset( $collector_order->data->customer->billingAddress->postalCode ) ? $collector_order->data->customer->billingAddress->postalCode : isset( $collector_order->data->customer->deliveryAddress->postalCode ) ? $collector_order->data->customer->deliveryAddress->postalCode : $fallback_postcode;
		$billing_city           = isset( $collector_order->data->customer->billingAddress->city ) ? $collector_order->data->customer->billingAddress->city : isset( $collector_order->data->customer->deliveryAddress->city ) ? $collector_order->data->customer->deliveryAddress->city : '.';
		$billing_country       	= isset( $collector_order->countryCode ) ? $collector_order->countryCode : WC()->countries->get_base_country();

		$phone                  = isset( $collector_order->data->customer->mobilePhoneNumber ) ? $collector_order->data->customer->mobilePhoneNumber : '.';
		$email                  = isset( $collector_order->data->customer->email ) ? $collector_order->data->customer->email : '.';
		$order = wc_create_order();
		//$order = new WC_Order();
		$order->set_billing_first_name( sanitize_text_field( $billing_first_name ) );
		$order->set_billing_last_name( sanitize_text_field( $billing_last_name ) );
		$order->set_billing_country( sanitize_text_field( $billing_country ) );
		$order->set_billing_address_1( sanitize_text_field( $billing_address ) );
		$order->set_billing_address_2( sanitize_text_field( $billing_address2 ) );
		$order->set_billing_city( sanitize_text_field( $billing_city ) );
		//$order->set_billing_state( sanitize_text_field( $collector_order->billing_address->region ) );
		$order->set_billing_postcode( sanitize_text_field( $billing_postal_code ) );
		$order->set_billing_phone( sanitize_text_field( $phone ) );
		$order->set_billing_email( sanitize_text_field( $email ) );
		$order->set_shipping_first_name( sanitize_text_field( $shipping_first_name ) );
		$order->set_shipping_last_name( sanitize_text_field( $shipping_last_name ) );
		$order->set_shipping_country( sanitize_text_field( $shipping_country ) );
		$order->set_shipping_address_1( sanitize_text_field( $shipping_address ) );
		$order->set_shipping_address_2( sanitize_text_field( $shipping_address2 ) );
		$order->set_shipping_city( sanitize_text_field( $shipping_city ) );
		//$order->set_shipping_state( sanitize_text_field( $collector_order->shipping_address->region ) );
		$order->set_shipping_postcode( sanitize_text_field( $shipping_postal_code ) );
		$order->set_created_via( 'collector_checkout_api_order_creation' );
		$order->set_currency( sanitize_text_field( get_woocommerce_currency() ) );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
		$order->set_payment_method( 'collector_checkout' );
		//$order->set_shipping_total( WC()->cart->get_shipping_total() );
		$order->set_discount_total( WC()->cart->get_discount_total() );
		$order->set_discount_tax( WC()->cart->get_discount_tax() );
		$order->set_cart_tax( WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax() );
		//$order->set_shipping_tax( WC()->cart->get_shipping_tax() );
		$order->set_total( WC()->cart->get_total( 'edit' ) );
		WC()->checkout()->create_order_line_items( $order, WC()->cart );
		WC()->checkout()->create_order_fee_lines( $order, WC()->cart );
		//WC()->checkout()->create_order_shipping_lines( $order, 'flat_rate:5', WC()->shipping->get_packages() );
		//WC()->checkout()->create_order_tax_lines( $order, WC()->cart );
		WC()->checkout()->create_order_coupon_lines( $order, WC()->cart );
		$order->save();
		if ( 'Preliminary' === $collector_order->data->purchase->result ) {
			$order->payment_complete( $collector_order->data->purchase->purchaseIdentifier );
			$order->add_order_note( 'Payment via Collector Checkout, order ID: ' . sanitize_key( $collector_order->data->purchase->purchaseIdentifier ) );
		} else {
			$order->add_order_note( __( 'Order is PENDING APPROVAL by Collector. Payment ID: ', 'woocommerce-gateway-klarna' ) . $collector_order->data->purchase->purchaseIdentifier );
			$order->update_status( 'on-hold' );
		}
		return $order;
	}

    public function create_order() {
        $order = wc_create_order();

        return $order;
    }

    
}
Collector_Api_Callbacks::get_instance();