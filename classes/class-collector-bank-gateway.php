<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Collector_Bank_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'collector_bank';
		$this->method_title       = __( 'Collector Checkout', 'collector-checkout-for-woocommerce' );
		$this->method_description = __( 'Collector Checkout payment solution for WooCommerce.', 'collector-checkout-for-woocommerce' );
		$this->description        = $this->get_option( 'description' );
		$this->title              = $this->get_option( 'title' );
		$this->enabled            = $this->get_option( 'enabled' );
		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();
		$this->supports = array(
			'products',
			'refunds',
		);

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		// Function to handle the thankyou page.
		add_action( 'woocommerce_thankyou_collector_bank', array( $this, 'collector_thankyou' ) );
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'collector_thankyou_order_received_text' ), 10, 2 );
		
		// Body class
		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		// Override the checkout template
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );
	}

	public function init_form_fields() {
		$this->form_fields = include( COLLECTOR_BANK_PLUGIN_DIR . '/includes/collector-bank-settings.php' );
	}
	
	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		$image_url = COLLECTOR_BANK_PLUGIN_URL . '/assets/images/collector_bank_logo_blackgrey.png';
		?>
		<p><img src="<?php echo $image_url;?>" width="280px"/></p>
		<h3><?php _e( 'Collector Checkout', 'woocommerce-gateway-dibs-account' ); ?></h3>
		<div class="collector-settings">
			<div class="collector-settings-content">
				<table class="form-table">
					<?php
					$this->generate_settings_html();
					?>
				</table>
			</div>	
			<div class="collector-settings-sidebar">
				<h4>Hej!</h4><p>Här skulle vi kunna placera ett par block med info och länkar till dokumentation och support.</p>
			</div>
		</div>
		<?php
	}
	/**
	 * Check if this gateway is enabled and available in the user's country
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! is_admin() ) {
				$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
				$collector_b2c_se 	= $collector_settings['collector_merchant_id_se_b2c'];
				$collector_b2b_se 	= $collector_settings['collector_merchant_id_se_b2b'];
				$collector_b2c_no 	= $collector_settings['collector_merchant_id_no_b2c'];
	
				// Currency check.
				if ( ! in_array( get_woocommerce_currency(), array( 'NOK', 'SEK' ) ) ) {
					return false;
				}
				// Store ID check
				if( 'NOK' == get_woocommerce_currency() && ! $collector_b2c_no ) {
					return false;
				}
				if( 'SEK' == get_woocommerce_currency() && ( ! $collector_b2c_se && ! $collector_b2b_se  ) ) {
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
		// Check if order is completed
		$invoice_url = get_post_meta( $order->id, '_collector_invoice_url', true );
		if ( $invoice_url ) {
			$this->view_transaction_url = $invoice_url;
		}
		return parent::get_transaction_url( $order );
	}
	

	public function override_template( $template, $template_name, $template_path ) {
		if ( is_checkout() ) {
			$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
			if ( 'checkout/form-checkout.php' === $template_name ) {
				// Collector checkout page.
				if ( array_key_exists( 'collector_bank', $available_payment_gateways ) ) {
					// If chosen payment method exists.
					if ( 'collector_bank' === WC()->session->get( 'chosen_payment_method' ) ) {
						$template = COLLECTOR_BANK_PLUGIN_DIR . '/templates/form-checkout.php';
					}
					// If chosen payment method does not exist and KCO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );
						if ( 'collector_bank' === key( $available_payment_gateways ) ) {
							$template = COLLECTOR_BANK_PLUGIN_DIR . '/templates/form-checkout.php';
						}
					}
				}
			}
			return $template;
		}
	}

	public function get_customer_data() {
		// Get information about order from Collector
		$private_id    	= WC()->session->get( 'collector_private_id' );
		$customer_type 	= WC()->session->get( 'collector_customer_type' );
		$customer_data 	= new Collector_Bank_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$customer_data 	= $customer_data->request();

		return json_decode( $customer_data );
	}

	public function process_payment( $order_id, $retry = false ) {
		$order = wc_get_order( $order_id );

		if ( 'Direct Invoice' == WC()->session->get( 'collector_payment_method' ) ) {
			$product_id = $this->get_option( 'collector_invoice_fee' );
			
			if( $product_id ) {
				
				$product   				= wc_get_product( $product_id );
				$tax_display_mode 			= get_option('woocommerce_tax_display_shop');
				
				$price = wc_get_price_excluding_tax( $product );
				
				if ( $product->is_taxable() ) {
					$product_tax = true;
				} else {
					$product_tax = false;
				}
				
				$_tax = new WC_Tax();
				$tmp_rates = $_tax->get_base_tax_rates( $product->get_tax_class() );
				$_vat = array_shift( $tmp_rates );// Get the rate 
				//Check what kind of tax rate we have 
				if( $product->is_taxable() && isset($_vat['rate']) ) {
					$vat_rate=round($_vat['rate']);
				} else {
					//if empty, set 0% as rate
					$vat_rate = 0;
				}
				
				$collector_fee            	= new stdClass();
				$collector_fee->id        	= sanitize_title( $product->get_title() );
				$collector_fee->name      	= $product->get_title();
				$collector_fee->amount    	= $price;
				$collector_fee->taxable   	= $product_tax;
				$collector_fee->tax       	= $vat_rate;
				$collector_fee->tax_data  	= array();
				$collector_fee->tax_class 	= $product->get_tax_class();
				$fee_id                   	= $order->add_fee( $collector_fee );
	
				if ( ! $fee_id ) {
					$order->add_order_note( __( 'Unable to add Collector Bank Invoice Fee to the order.', 'collector-checkout-for-woocommerce' ) );
				}
				$order->calculate_totals( true );
				
			}
			
		}

		WC()->session->__unset( 'collector_customer_order_note' );
		// Update the Collector Order with the Order ID
		$update_reference = new Collector_Bank_Requests_Update_Reference( $order->get_order_number(), WC()->session->get( 'collector_private_id' ), WC()->session->get( 'collector_customer_type' ) );
		$update_reference->request();
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	public function collector_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		
		$private_id 	= WC()->session->get( 'collector_private_id' );
		$customer_type 	= WC()->session->get( 'collector_customer_type' );
		$payment_data 	= new Collector_Bank_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$payment_data 	= $payment_data->request();
		$payment_data 	= json_decode( $payment_data );
		$payment_status = $payment_data->data->purchase->result;
		
		update_post_meta( $order_id, '_collector_payment_method', WC()->session->get( 'collector_payment_method' ) );
		update_post_meta( $order_id, '_collector_payment_id', WC()->session->get( 'collector_payment_id' ) );
		update_post_meta( $order_id, '_collector_customer_type', WC()->session->get( 'collector_customer_type' ) );
		
		if('Preliminary' == $payment_status ) {
			$order->payment_complete( WC()->session->get( 'collector_payment_id' ) );
		} else {
			$order->add_order_note( __( 'Order is PENDING APPROVAL by Collector. Payment ID: ', 'woocommerce-gateway-klarna' ) . WC()->session->get( 'collector_payment_id' ) );
			$order->update_status( 'on-hold' );
		}
		
		$order->add_order_note( sprintf( __( 'Purchase via %s', 'collector-checkout-for-woocommerce' ), wc_collector_get_payment_method_name( WC()->session->get( 'collector_payment_method' ) ) ) );
	}
	
	/**
	 * Remove thank you page order received text if Collector is the selected payment method.
	 *
	 * @param $text
	 * @param $order
	 *
	 * @return string
	 */
	public function collector_thankyou_order_received_text( $text, $order ) {
		if( 'collector_bank' == $order->get_payment_method() ) {
			return '<div class="collector-checkout-thankyou"></div>';
		}

		return $text;
	}
	
	/**
	 * Add collector-b2c/b2b body class.
	 *
	 * @param $class
	 *
	 * @return array
	 */
	public function add_body_class( $class ) {
		if ( is_checkout() ) {	
			$class[] = wc_collector_get_available_customer_types();
		}
		return $class;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		//Check if amount equals total order
		$order = wc_get_order( $order_id );
		if ( $amount == $order->get_total() ) {
			$credit_order = new Collector_Bank_SOAP_Requests_Credit_Payment( $order_id );
			if ( $credit_order->request( $order_id ) === true ) {
				return true;
			} else {
				return false;
			}
		} else {
			$order->add_order_note( sprintf( __( 'Collector Bank currently only supports full refunds, for a partial refund use the Collector Bank Merchant Portal', 'collector-checkout-for-woocommerce' ) ) );
			return false;
		}
	}
}
