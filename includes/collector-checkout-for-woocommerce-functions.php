<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Echoes Collector Checkout iframe snippet.
 */
function collector_wc_show_snippet() {
	if( 'NOK' == get_woocommerce_currency() ) {
		$locale = 'nb-NO';
	} elseif( 'DKK' == get_woocommerce_currency() ) {
		$locale = 'en-DK';
	} elseif( 'EUR' == get_woocommerce_currency() ) {
		$locale = 'fi-FI';
	} else {
		$locale = 'sv-SE';
	}
	
	$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
	$test_mode          = $collector_settings['test_mode'];
	if( 'yes' == $test_mode ) {
		$url = 'https://checkout-uat.collector.se/collector-checkout-loader.js';
	} else {
		$url = 'https://checkout.collector.se/collector-checkout-loader.js';
	}
	
    $customer_type 		= WC()->session->get( 'collector_customer_type' );
    
    if( empty( $customer_type ) ) {
        $customer_type = wc_collector_get_default_customer_type();
        WC()->session->set( 'collector_customer_type', $customer_type );
    }
    
	$public_token 		= WC()->session->get( 'collector_public_token' );
	$collector_currency = WC()->session->get( 'collector_currency' );
	
    if( empty( $public_token ) || $collector_currency !== get_woocommerce_currency() ) {
        // Get a new public token from Collector
		$init_checkout 	= new Collector_Checkout_Requests_Initialize_Checkout( $customer_type );
		$request 		= $init_checkout->request();
		
		if ( is_wp_error( $request ) || empty( $request ) ) {
			$return =  '<ul class="woocommerce-error"><li>' . sprintf( '%s <a href="%s" class="button wc-forward">%s</a>', __( 'Could not connect to Collector.', 'collector-checkout-for-woocommerce' ), wc_get_checkout_url(), __( 'Try again', 'collector-checkout-for-woocommerce' ) ) . '</li></ul>';
			
		} else {
			$decode	= json_decode( $request );
			WC()->session->set( 'collector_public_token', $decode->data->publicToken );
			WC()->session->set( 'collector_private_id', $decode->data->privateId );
			WC()->session->set( 'collector_currency', get_woocommerce_currency() );
			
			$public_token = $decode->data->publicToken;
			$output             = array(
				'publicToken'   => $public_token,
				'test_mode'     => $test_mode,
				'customer_type' => $customer_type,
			);
			
			echo("<script>console.log('Collector: ".json_encode($output)."');</script>");
			$return = '<script src="' . $url . '" data-lang="' . $locale . '" data-token="' . $public_token . '" data-variant="' . $customer_type . '" ></script>';
    	}
    	
    	
    } else {
        
        $output             = array(
			'publicToken'   => $public_token,
			'test_mode'     => $test_mode,
			'customer_type' => $customer_type,
		);
		echo("<script>console.log('Collector: ".json_encode($output)."');</script>");
        $return = '<script src="' . $url . '" data-lang="' . $locale . '" data-token="' . $public_token . '" data-variant="' . $customer_type . '" ></script>';
    }
     
    echo $return;
}

/**
 * Unset Collector public token and private id
 */
function wc_collector_unset_sessions() {
	WC()->session->__unset( 'collector_public_token' );
	WC()->session->__unset( 'collector_private_id' );
	WC()->session->__unset( 'collector_currency' );
}

/**
 * Calculates cart totals.
 */
function collector_wc_calculate_totals() {
	WC()->cart->calculate_fees();
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();
}

/**
 * Unset Collector public token and private id
 */
function wc_collector_add_invoice_fee_to_order( $order_id, $product_id ) {
	$result = false;
	$order = wc_get_order( $order_id );
	$product = wc_get_product( $product_id );
				
	if ( is_object( $product ) && is_object( $order ) ) {
		$tax_display_mode 	= get_option('woocommerce_tax_display_shop');
		$price 				= wc_get_price_excluding_tax( $product );
		
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
		$result = $order->calculate_totals( true );
	}
	return $result;
}