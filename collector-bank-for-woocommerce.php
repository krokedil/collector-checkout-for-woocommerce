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
 * Developer URI:   http://krokedil.com/
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
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-bank-handle-payment-method.php' );

			// Include and add the Gateway
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-bank-gateway.php' );
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_collector_bank_gateway' ) );
			}

			// Include the Request Classes
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests-initialize-checkout.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests-update-fees.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests-get-checkout-information.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-bank-requests-update-reference.php' );

			// Include the Request Helpers
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-bank-requests-cart.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-bank-requests-fees.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-bank-requests-header.php' );
			include_once( COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-bank-requests-calculate-auth.php' );
		}

		public function load_scripts() {
			// Enqueue scripts
			wp_enqueue_script( 'jquery' );
			if ( is_checkout() ) {
				wp_register_script( 'checkout', plugins_url( '/assets/js/checkout.js', __FILE__ ), array( 'jquery' ) );
				wp_localize_script( 'checkout', 'wc_collector_bank', array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
				) );
				wp_enqueue_script( 'checkout' );
			}
			// Load stylesheet for the checkout page
			wp_register_style(
				'collector_bank',
				plugin_dir_url( __FILE__ ) . '/assets/css/style.css'
			);
			wp_enqueue_style( 'collector_bank' );
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
