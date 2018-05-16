<?php
/**
 * Compliance with European Union's General Data Protection Regulation.
 *
 * @class    Collector_Checkout_GDPR
 * @version  1.0.0
 * @package  Collector_Checkout/Classes
 * @category Class
 * @author   Krokedil
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Collector_Checkout_GDPR {
	public function __construct() {
		add_action( 'admin_init', array( $this, 'privacy_declarations' ) );
		//add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		//add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
	}
	public function privacy_declarations() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			$content =
				__( 'When you place an order in the webstore with Collector Checkout as the choosen payment method, ' .
					'information about the products in the order (namne, price, quantity, SKU) is sent to Collector Bank. ' .
					'When the purchase is finalized Collector Bank sends your billing and shipping address back to the webstore. ' .
					'This data plus an unique identifier for the purchase is then stored as billing and shipping data in the order in WooCommerce.',
					'collector-checkout-for-woocommerce' );
			wp_add_privacy_policy_content(
				'Collector Checkout for WooCommerce',
				wp_kses_post( wpautop( $content ) )
			);
		}
	}
}
$wc_collector_checkout_gdpr = new Collector_Checkout_GDPR();