<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Collector_Checkout_Templates class.
 */
class Collector_Checkout_Templates {

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
	 * Plugin actions.
	 */
	public function __construct() {

		// Override the checkout template
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 10, 3 );

		// Template hooks.
		add_action( 'collector_wc_before_checkout_form', 'collector_wc_calculate_totals', 1 );

		add_action( 'collector_wc_before_iframe', 'collector_wc_show_customer_order_notes', 10 );
		add_action( 'collector_wc_before_iframe', 'collector_wc_show_another_gateway_button', 20 );
		add_action( 'collector_wc_before_iframe', 'collector_wc_show_customer_type_switcher', 30 );
	}

	/**
	 * Override checkout form template if Collector Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name, $template_path ) {
		if ( is_checkout() && ! isset( $_GET['payment_successful'] ) ) {

			if ( 'checkout/form-checkout.php' === $template_name ) {
				$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();

				if ( locate_template( 'woocommerce/collector-checkout.php' ) ) {
					$collector_checkout_template = locate_template( 'woocommerce/collector-checkout.php' );
				} else {
					$collector_checkout_template = COLLECTOR_BANK_PLUGIN_DIR . '/templates/collector-checkout.php';
				}

				// Collector checkout page.
				if ( array_key_exists( 'collector_checkout', $available_payment_gateways ) ) {
					// If chosen payment method exists.
					if ( 'collector_checkout' === WC()->session->get( 'chosen_payment_method' ) ) {
						$template = $collector_checkout_template;
					}
					// If chosen payment method does not exist and KCO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_payment_gateways );
						if ( 'collector_checkout' === key( $available_payment_gateways ) ) {
							$template = $collector_checkout_template;
						}
					}
				}
			}
		}
		return $template;
	}

}

Collector_Checkout_Templates::get_instance();
