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
	} else {
		$locale = 'sv';
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