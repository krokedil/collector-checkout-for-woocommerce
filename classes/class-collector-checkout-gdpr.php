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
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_GDPR
 */
class Collector_Checkout_GDPR {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'privacy_declarations' ) );
		add_action( 'init', array( $this, 'maybe_add_privacy_policy_text' ) );
		// phpcs:ignore add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		// phpcs:ignore add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
	}

	/**
	 * Privacy declarations
	 *
	 * @return void
	 */
	public function privacy_declarations() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			$content =
				__(
					'When you place an order in the webstore with Collector Checkout as the choosen payment method, ' .//phpcs:ignore
					'information about the products in the order (namne, price, quantity, SKU) is sent to Collector Bank. ' .
					'When the purchase is finalized Collector Bank sends your billing and shipping address back to the webstore. ' .
					'This data plus an unique identifier for the purchase is then stored as billing and shipping data in the order in WooCommerce.',
					'collector-checkout-for-woocommerce'
				);
			wp_add_privacy_policy_content(
				'Collector Checkout for WooCommerce',
				wp_kses_post( wpautop( $content ) )
			);
		}
	}

	/**
	 * Maybe adds the terms checkbox to the checkout.
	 *
	 * @return void
	 */
	public function maybe_add_privacy_policy_text() {
		$settings                    = get_option( 'woocommerce_collector_checkout_settings' );
		$display_privacy_policy_text = ( isset( $settings['display_privacy_policy_text'] ) ) ? $settings['display_privacy_policy_text'] : '';
		if ( 'above' === $display_privacy_policy_text ) {
			add_action( 'collector_wc_before_iframe', array( $this, 'wc_display_privacy_policy_text' ), 40 );
		} elseif ( 'below' === $display_privacy_policy_text ) {
			add_action( 'collector_wc_after_iframe', array( $this, 'wc_display_privacy_policy_text' ) );
		}
	}

	/**
	 * Gets the terms template.
	 *
	 * @return void
	 */
	public function wc_display_privacy_policy_text() {
		if ( function_exists( 'wc_checkout_privacy_policy_text' ) ) {
			echo wc_checkout_privacy_policy_text();//phpcs:ignore
		}
	}
}
$wc_collector_checkout_gdpr = new Collector_Checkout_GDPR();
