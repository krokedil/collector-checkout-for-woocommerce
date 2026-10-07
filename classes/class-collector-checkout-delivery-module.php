<?php //phpcs:ignore
/**
 * Delivery module class.
 *
 * @package Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Collector_Delivery_Module class.
 */
class Collector_Delivery_Module {

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
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'admin_order_meta' ), 10, 1 );
		add_filter( 'wc_get_template', array( $this, 'override_shipping_template' ), 999, 2 );
	}

	/**
	 * Display Shipping info in order if order contain a Collector Delivery Module shipping method.
	 *
	 * @param object $order WooCommerce order.
	 * @return mixed Prints the html displayed in order admin.
	 */
	public function admin_order_meta( $order ) {
		$collector_delivery_data = json_decode( $order->get_meta( '_collector_delivery_module_data', true ), true );

		if ( empty( $collector_delivery_data ) ) {
			return;
		}

		$shipment_text = '';
		foreach ( walley_get_shipments( $collector_delivery_data ) as $shipment ) {
			$shipment_text .= ! empty( $shipment['label'] ) ? sprintf( '<strong>%1$s</strong> %2$s<br>', __( 'Service:', 'collector-checkout-for-woocommerce' ), wc_clean( $shipment['label'] ) ) : '';
			$shipment_text .= ! empty( $shipment['pickup_point'] ) ? sprintf( '<strong>%1$s</strong> %2$s<br>', __( 'Pickup Point:', 'collector-checkout-for-woocommerce' ), wc_clean( $shipment['pickup_point'] ) ) : '';
			$shipment_text .= ! empty( $shipment['shipment_id'] ) ? sprintf( '<strong>%1$s</strong> %2$s<br>', __( 'Shipment ID:', 'collector-checkout-for-woocommerce' ), wc_clean( $shipment['shipment_id'] ) ) : '';
		}

		if ( empty( $shipment_text ) ) {
			return;
		}

		printf(
			'<h3>%1$s</h3><div class="unifaun"><p>%2$s</p></div>',
			esc_html__( 'Shipment information', 'collector-checkout-for-woocommerce' ),
			wp_kses_post( $shipment_text )
		);
	}

	/**
	 * Overrides the default cart shipping template.
	 *
	 * @param string $template The absolute template path.
	 * @param string $template_name The name of the template.
	 * @return string
	 */
	public function override_shipping_template( $template, $template_name ) {
		// If its not the cart, return.
		if ( ! is_cart() ) {
			return $template;
		}

		// If its not the cart/cart-shipping.php file, return.
		if ( 'cart/cart-shipping.php' !== $template_name ) {
			return $template;
		}

		// If Delivery module is not used for the currency/country, return.
		if ( ! is_collector_delivery_module() ) {
			return $template;
		}

		if ( locate_template( 'woocommerce/collector-cart-shipping.php' ) ) {
			$template = locate_template( 'woocommerce/collector-cart-shipping.php' );
		} else {
			$template = COLLECTOR_BANK_PLUGIN_DIR . '/templates/collector-cart-shipping.php';
		}

		return $template;
	}
}

Collector_Delivery_Module::get_instance();
