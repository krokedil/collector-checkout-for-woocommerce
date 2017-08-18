<?php
/**
 * Collector Bank for WooCommerce
 *
 * @package WC_Dibs_Easy
 *
 * @wordpress-plugin
 * Plugin Name:     Collector Bank for WooCommerce
 * Plugin URI:      https://krokedil.se/
 * Description:     Extends WooCommerce. Provides a <a href="https://www.collector.se/" target="_blank">Collector Bank</a> checkout for WooCommerce.
 * Version:         0.1.0
 * Author:          Krokedil
 * Author URI:      https://woocommerce.com/
 * Developer:       Krokedil
 * Developer URI:   https://krokedil.se/
 * Text Domain:     collector-bank-for-woocommerce
 * Domain Path:     /languages
 * Copyright:       © 2009-2017 WooCommerce.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'COLLECTOR_BANK_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'COLLECTOR_BANK_VERSION', '0.1.8' );

if ( ! class_exists( 'Collector_Bank' ) ) {
	class Collector_Bank {
		public function __construct() {
			/**
			 * UserName: combuyit
			 * PartnerID: 873
			 * Shared Key: 4bxpaFU;u?So7eI@QTQR*2btWKL1wS
			 *
			 * Username: krokedil_test
			 * Password: combuyit_test
			 * StoreID: 873
			 */
			// Initiate the gateway
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			// Load scripts
			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );

			// Remove the storefront sticky checkout.
			add_action( 'wp_enqueue_scripts', array( $this, 'jk_remove_sticky_checkout' ), 99 );
		}

		public function init() {
			// Include the Classes
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-bank-ajax-calls.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-bank-post-checkout.php' );
			//include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-bank-instant-checkout.php' );

			// Include and add the Gateway
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-bank-gateway.php' );
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_collector_bank_gateway' ) );
			}

			// Include the Request Classes
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests-initialize-checkout.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests-update-fees.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests-update-cart.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests-get-checkout-information.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests-update-reference.php' );

			// Include the Request Helpers
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-bank-requests-cart.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-bank-requests-fees.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-bank-requests-header.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-bank-requests-calculate-auth.php' );

			// Include the Soap Request Classes
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-bank-soap-requests-activate-invoice.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-bank-soap-requests-cancel-invoice.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-bank-soap-requests-credit-payment.php' );

			// Definitions
			define( 'COLLECTOR_BANK_REST_LIVE', 'https://checkout-api.collector.se' );
			define( 'COLLECTOR_BANK_REST_TEST', 'https://checkout-api-uat.collector.se' );
			define( 'COLLECTOR_BANK_SOAP_LIVE', 'https://ecommerce.collector.se/v3.0/InvoiceServiceV33.svc?wsdl' );
			define( 'COLLECTOR_BANK_SOAP_TEST', 'https://ecommercetest.collector.se/v3.0/InvoiceServiceV33.svc?wsdl' );
		}

		public function load_scripts() {
			// Enqueue scripts
			wp_enqueue_script( 'jquery' );
			if ( is_checkout() ) {
				wp_register_script( 'checkout', plugins_url( '/assets/js/checkout.js', __FILE__ ), array( 'jquery' ), COLLECTOR_BANK_VERSION );
				
				if( 'NOK' == get_woocommerce_currency() ) {
					$locale = 'nb-NO';
				} else {
					$locale = 'sv';
				}
				wp_localize_script( 'checkout', 'wc_collector_bank', array(
					'ajaxurl' 				=> admin_url( 'admin-ajax.php' ),
					'locale' 				=> $locale,
					'default_customer_type' => wc_collector_get_default_customer_type(),
					'collector_nonce' 		=> wp_create_nonce( 'collector_nonce' ),
				) );
				wp_enqueue_script( 'checkout' );
			}
			// Load stylesheet for the checkout page
			wp_register_style(
				'collector_bank',
				plugin_dir_url( __FILE__ ) . '/assets/css/style.css',
				array(),
				COLLECTOR_BANK_VERSION
			);
			wp_enqueue_style( 'collector_bank' );
			
			// Hide the Order overview data on thankyou page if it's a Collector Checkout purchase
			if( is_wc_endpoint_url( 'order-received' ) ) {
				$order_id = wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) );
				$order = wc_get_order( $order_id );
				if( 'collector_bank' == $order->get_payment_method() ) {
					$custom_css = "
	                .woocommerce-order-overview {
			                        display: none;
							}";
					wp_add_inline_style( 'collector_bank', $custom_css );
				}
			}
			
		}
		public function add_collector_bank_gateway( $methods ) {
			$methods[] = 'Collector_Bank_Gateway';

			return $methods;
		}

		public function jk_remove_sticky_checkout() {
			wp_dequeue_script( 'storefront-sticky-payment' );
		}
	}
}
$collector_bank = new Collector_Bank();

function wc_collector_get_available_customer_types() {
	$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
	$collector_b2c_se 	= $collector_settings['collector_merchant_id_se_b2c'];
	$collector_b2b_se 	= $collector_settings['collector_merchant_id_se_b2b'];
	$collector_b2c_no 	= $collector_settings['collector_merchant_id_no_b2c'];

	if( $collector_b2c_se && $collector_b2b_se ) {
		return 'collector-b2c-b2b';
	} elseif( ( 'SEK' == get_woocommerce_currency() && $collector_b2c_se ) || ( 'NOK' == get_woocommerce_currency() && $collector_b2c_no ) ) {
		return 'collector-b2c';
	} elseif( $collector_b2b_se ) {
		return 'collector-b2b';
	}
}

function wc_collector_get_default_customer_type() {
	$collector_settings = get_option( 'woocommerce_collector_bank_settings' );
	$collector_b2c_se 	= $collector_settings['collector_merchant_id_se_b2c'];
	$collector_b2b_se 	= $collector_settings['collector_merchant_id_se_b2b'];
	$collector_b2c_no 	= $collector_settings['collector_merchant_id_no_b2c'];

	if( $collector_b2c_no && 'NOK' == get_woocommerce_currency() ) {
		return 'b2c';
	} elseif( $collector_b2c_se && $collector_b2b_se ) {
		return 'b2c';
	} elseif( $collector_b2b_se && !$collector_b2c_se ) {
		return 'b2b';
	} else {
		return 'b2c';
	}
}