<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Collector_Checkout_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'collector_checkout';
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
		add_action( 'woocommerce_thankyou_collector_checkout', array( $this, 'collector_thankyou' ) );
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'collector_thankyou_order_received_text' ), 10, 2 );
		
		// Body class
		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		// Override the checkout template
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );

		// Set fields to not required.
		add_filter( 'woocommerce_checkout_fields' ,  array( $this, 'collector_set_not_required' ), 20 );

		// Add org nr after address on company order.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'add_org_nr_to_order' ) );
		
		// Change the title when processing the WooCommerce order in checkout
		add_filter( 'the_title', array( $this, 'confirm_page_title' ) );

		// Notification listener.
		add_action( 'woocommerce_api_collector_checkout_gateway', array( $this, 'notification_listener' ) );
	}

	/**
	 * Schedule order status check on notificationUri callback from Collector
	 */
	public function notification_listener() {
		
		if( isset( $_GET['private-id'] ) && isset( $_GET['public-token'] ) ) {
			$private_id 	= $_GET['private-id'];
			$public_token 	= $_GET['public-token'];
			$customer_type 	= $_GET['customer-type'];
			wp_schedule_single_event( time() + 120, 'collector_check_for_order', array( $private_id, $public_token, $customer_type ) );
			
			header( 'HTTP/1.1 200 OK' );
		}
		
	}

	public function init_form_fields() {
		$this->form_fields = include( COLLECTOR_BANK_PLUGIN_DIR . '/includes/collector-checkout-settings.php' );
	}
	
	/**
	 * Admin Panel Options
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		$image_url = COLLECTOR_BANK_PLUGIN_URL . '/assets/images/collector_bank_logo_blackgrey.png';
		?>
		<p><img src="<?php echo $image_url;?>" width="280px"/></p>
		<h3><?php _e( 'Collector Checkout', 'collector-checkout-for-woocommerce' ); ?></h3>
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
					<li><a href="http://docs.krokedil.com/documentation/collector-checkout-for-woocommerce/" target="_blank">Dokumentation</a></li>
					<li><a href="https://wordpress.org/plugins/collector-checkout-for-woocommerce" target="_blank">Pluginsida</a></li>
				</ul></p>
				<h4>Support</h4><p>Har du frågor kring ditt konto eller kring specifika köp är du välkommen att <a href="https://www.collector.se/kundservice/" target="_blank">kontakta Collector</a>. Har du tekniska frågor eller funderingar kring konfigurationen av modulen så kan du <a href="https://krokedil.se/support/" target="_blank">kontakta Krokedil</a>.</p>
				<h4>Logotypes</h4><p>Du hittar våra logotypes genom att klicka på länken <a href="https://checkout-documentation.collector.se/#branding-resources" target="_blank">här</a>.</p>
				<h4>Köpvillkorstexter</h4><p>Text som avser Collector Banks köpvillkor hittas nedan.
					<ul>
						<li><a href="https://merchant.collectorbank.se/integration/b2c/agreement-texts/collector-checkout/" target="_blank">B2C</a></li>
						<li><a href="https://merchant.collectorbank.se/integration/b2b/agreement-texts/collector-checkout/" target="_blank">B2B</a></li>
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
			if ( ! is_admin() ) {
				$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
				$collector_b2c_se 	= ( isset( $collector_settings['collector_merchant_id_se_b2c'] ) ) ? $collector_settings['collector_merchant_id_se_b2c'] : '';
				$collector_b2b_se 	= ( isset( $collector_settings['collector_merchant_id_se_b2b'] ) ) ? $collector_settings['collector_merchant_id_se_b2b'] : '';
				$collector_b2c_no 	= ( isset( $collector_settings['collector_merchant_id_no_b2c'] ) ) ? $collector_settings['collector_merchant_id_no_b2c'] : '';
				$collector_b2b_no 	= ( isset( $collector_settings['collector_merchant_id_no_b2b'] ) ) ? $collector_settings['collector_merchant_id_no_b2b'] : '';
				$collector_b2c_dk 	= ( isset( $collector_settings['collector_merchant_id_dk_b2c'] ) ) ? $collector_settings['collector_merchant_id_dk_b2c'] : '';
				$collector_b2c_fi 	= ( isset( $collector_settings['collector_merchant_id_fi_b2c'] ) ) ? $collector_settings['collector_merchant_id_fi_b2c'] : '';
				$collector_b2b_fi 	= ( isset( $collector_settings['collector_merchant_id_fi_b2b'] ) ) ? $collector_settings['collector_merchant_id_fi_b2b'] : '';
	
				// Currency check.
				if ( ! in_array( get_woocommerce_currency(), array( 'NOK', 'SEK', 'DKK', 'EUR' ) ) ) {
					return false;
				}
				// Store ID check
				if( 'NOK' == get_woocommerce_currency() && ( ! $collector_b2c_no && ! $collector_b2b_no  ) ) {
					return false;
				}
				if( 'SEK' == get_woocommerce_currency() && ( ! $collector_b2c_se && ! $collector_b2b_se  ) ) {
					return false;
				}
				if( 'DKK' == get_woocommerce_currency() && ( ! $collector_b2c_dk  ) ) {
					return false;
				}
				if( 'EUR' == get_woocommerce_currency() && ( ! $collector_b2c_fi && ! $collector_b2b_fi  ) ) {
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
		$invoice_url = get_post_meta( $order->get_id(), '_collector_invoice_url', true );
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
				if ( array_key_exists( 'collector_checkout', $available_payment_gateways ) ) {
					// If chosen payment method exists.
					if ( 'collector_checkout' === WC()->session->get( 'chosen_payment_method' ) ) {
						$template = COLLECTOR_BANK_PLUGIN_DIR . '/templates/form-checkout.php';
					}
					// If chosen payment method does not exist and KCO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_payment_gateways );
						if ( 'collector_checkout' === key( $available_payment_gateways ) ) {
							$template = COLLECTOR_BANK_PLUGIN_DIR . '/templates/form-checkout.php';
						}
					}
				}
			}
		}
		return $template;
	}

	/*public function get_customer_data() {
		// Get information about order from Collector
		$private_id    	= WC()->session->get( 'collector_private_id' );
		$customer_type 	= WC()->session->get( 'collector_customer_type' );
		$customer_data 	= new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
		$customer_data 	= $customer_data->request();

		return json_decode( $customer_data );
	}*/

	public function process_payment( $order_id, $retry = false ) {
		$order = wc_get_order( $order_id );

		// Maybe add invoice fee to order
		if ( 'DirectInvoice' == WC()->session->get( 'collector_payment_method' ) ) {
			$product_id = $this->get_option( 'collector_invoice_fee' );
			if( $product_id ) {
				wc_collector_add_invoice_fee_to_order( $order_id, $product_id );
			}
		}

		WC()->session->__unset( 'collector_customer_order_note' );
		
		// Update the Collector Order with the Order number
		$private_id = get_post_meta( $order_id, '_collector_private_id', true );
		$customer_type = get_post_meta( $order_id, '_collector_customer_type', true );
		if( !empty( $private_id ) && !empty( $customer_type ) ) {
			$update_reference = new Collector_Checkout_Requests_Update_Reference( $order->get_order_number(), $private_id, $customer_type );
			$update_reference->request();
			Collector_Checkout::log('Update Collector order reference for order - ' . $order->get_order_number());
		}
		
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}


	public function collector_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if( WC()->session->get( 'collector_private_id' ) ) {
			
			$private_id 	= get_post_meta( $order_id, '_collector_private_id', true );
			Collector_Checkout::log('collector_thankyou page hit for private_id ' . $private_id );
			
			$customer_type 	= get_post_meta( $order_id, '_collector_customer_type', true );
			$payment_data 	= new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
			$payment_data 	= $payment_data->request();
			$payment_data 	= json_decode( $payment_data );
			$payment_status = $payment_data->data->purchase->result;
			$payment_method = $payment_data->data->purchase->paymentName;
			$payment_id 	= $payment_data->data->purchase->purchaseIdentifier;
			
			update_post_meta( $order_id, '_collector_payment_method', $payment_method );
			update_post_meta( $order_id, '_collector_payment_id', $payment_id );
			
			// Tie this order to a user if we have one.
			if ( email_exists( $payment_data->data->customer->email ) ) {
				$user    = get_user_by( 'email', $payment_data->data->customer->email );
				$user_id = $user->ID;
				update_post_meta( $order_id, '_customer_user', $user_id );
			}
			
			if( 'Preliminary' == $payment_status ) {
				$order->payment_complete( $payment_id );
			} elseif( 'Signing' == $payment_status ) {
				$order->add_order_note( __( 'Order is waiting for electronic signing by customer. Payment ID: ', 'woocommerce-gateway-klarna' ) . $payment_id );
				update_post_meta( $order_id, '_transaction_id', $payment_id );
				$order->update_status( 'on-hold' );
			} else {
				$order->add_order_note( __( 'Order is PENDING APPROVAL by Collector. Payment ID: ', 'woocommerce-gateway-klarna' ) . $payment_id );
				update_post_meta( $order_id, '_transaction_id', $payment_id );
				$order->update_status( 'on-hold' );
			}
			
			$order->add_order_note( sprintf( __( 'Purchase via %s', 'collector-checkout-for-woocommerce' ), wc_collector_get_payment_method_name( $payment_method ) ) );
			
			// Check if there where any empty fields, if so send mail.
	        if ( WC()->session->get( 'collector_empty_fields' ) ) {
	            $email = get_option( 'admin_email' );
	            $subject = __( 'Order data was missing from Collector', 'collector-checkout-for-woocommerce' );
	            $message = '<p>' . __( 'The following fields had missing data from Collector, please verify the order with Collector.', 'collector-checkout-for-woocommerce' );
	            foreach ( WC()->session->get( 'collector_empty_fields' ) as $field ) {
	                $message = $message . '<br>' . $field;
	            }
	            $message = $message . '<br><a href="' . get_edit_post_link( $order_id ) . '">' . __( 'Link to the order', 'collector-checkout-for-woocommerce' ) . '</a></p>';
	            wp_mail( $email, $subject, $message );
	            WC()->session->__unset( 'collector_empty_fields' );
	        }
	        // Check if there is a org nr set, if so add post meta
            if ( WC()->session->get( 'collector_org_nr' ) ) {
	            $org_nr = WC()->session->get( 'collector_org_nr' );
	            update_post_meta( $order_id, '_collector_org_nr', $org_nr );
	            WC()->session->__unset( 'collector_org_nr' );
            }
            
            // Check if there is a invoice refernce set, if so add post meta
            if ( WC()->session->get( 'collector_invoice_reference' ) ) {
	            $invoice_reference = WC()->session->get( 'collector_invoice_reference' );
	            update_post_meta( $order_id, '_collector_invoice_reference', $invoice_reference );
	            WC()->session->__unset( 'collector_invoice_reference' );
            }
            // Unset Collector token and id
			wc_collector_unset_sessions();

		} else {
			// @todo - add logging here.
			Collector_Checkout::log('collector_thankyou page hit but collector_private_id session not existing.');
		}
		
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
		if( is_object( $order ) && 'collector_checkout' == $order->get_payment_method() ) {
			return '<div class="collector-checkout-thankyou"></div>';
			
		}
		if( isset( $_GET['purchase-status'] ) && 'not-completed' == $_GET['purchase-status'] ) {
			// Unset Collector token and id
			wc_collector_unset_sessions();
			WC()->cart->empty_cart();
			Collector_Checkout::log('Rendering simplified thankyou page (only display Collector thank you iframe).' );
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
			
			$first_gateway = '';
			if( WC()->session->get( 'chosen_payment_method' ) ) {
				$first_gateway = WC()->session->get( 'chosen_payment_method' );
			} else {
				$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
				reset( $available_payment_gateways );
				$first_gateway = key( $available_payment_gateways );
			}
			
			if ( 'collector_checkout' == $first_gateway ) {
				$class[] = 'collector-checkout-selected';
			}
		}
		return $class;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		//Check if amount equals total order
		$order = wc_get_order( $order_id );
		if ( $amount == $order->get_total() ) {
			$credit_order = new Collector_Checkout_SOAP_Requests_Credit_Payment( $order_id );
			if ( $credit_order->request( $order_id ) === true ) {
				return true;
			} else {
				return false;
			}
		} else {
			//$credit_order = new Collector_Checkout_SOAP_Requests_Adjust_Invoice( $order_id );
			$credit_order = new Collector_Checkout_SOAP_Requests_Part_Credit_Invoice( $order_id );
			if ( $credit_order->request( $order_id, $amount, $reason ) === true ) {
				return true;
			} else {
				return false;
			}

			//$order->add_order_note( sprintf( __( 'Collector Bank currently only supports full refunds, for a partial refund use the Collector Bank Merchant Portal', 'collector-checkout-for-woocommerce' ) ) );
			//return false;
		}
	}

	public function collector_set_not_required( $checkout_fields ) {
		//Set fields to not required, to prevent orders from failing
		if ( 'collector_checkout' === WC()->session->get( 'chosen_payment_method' ) ) {
			foreach ( $checkout_fields as $fieldset_key => $fieldset ) {
				foreach ( $fieldset as $field_key => $field ) {
					$checkout_fields[ $fieldset_key ][ $field_key ]['required'] = false;
				}
			}
		}
		return $checkout_fields;
    }

    public function add_org_nr_to_order( $order ) {
	    if ( 'collector_checkout' === $order->get_payment_method() ) {
		    $order_id = $order->get_id();
		    if ( get_post_meta( $order_id, '_collector_org_nr' ) ) {
			    echo '<p class="form-field form-field-wide"><strong>' . __( 'Org Nr', 'collector-checkout-for-woocommerce' ) . ':</strong> ' . get_post_meta( $order_id, '_collector_org_nr', true ) . '</p>';
			}
			if ( get_post_meta( $order_id, '_collector_invoice_reference' ) ) {
			    echo '<p class="form-field form-field-wide"><strong>' . __( 'Invoice reference', 'collector-checkout-for-woocommerce' ) . ':</strong> ' . get_post_meta( $order_id, '_collector_invoice_reference', true ) . '</p>';
		    }
	    }
    }
    
    /**
	 * Filter Checkout page title in confirmation page.
	 *
	 * @param $title
	 *
	 * @return string
	 */
	public function confirm_page_title( $title ) {
		if ( ! is_admin() && is_main_query() && in_the_loop() && is_page() && is_checkout() && isset( $_GET['payment_successful'] ) && 1 == $_GET['payment_successful'] ) {
			$title = __( 'Please wait while we process your order.', 'collector-checkout-for-woocommerce' );
		}
		return $title;
	}
}
