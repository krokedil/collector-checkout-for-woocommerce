<?php //phpcs:ignore
/**
 * Shipping method class file.
 *
 * @package CollectorCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Shipping_Method' ) ) {

	/**
	 * Shipping method class.
	 */
	class Collector_Delivery_Module_Shipping_Method extends WC_Shipping_Method {

		/**
		 * Class constructor.
		 *
		 * @param integer $instance_id The instance id.
		 */
		public function __construct( $instance_id = 0 ) {
			$this->id                   = 'collector_delivery_module';
			$this->instance_id          = absint( $instance_id );
			$this->title                = 'Walley Shipping Module';
			$this->method_title         = __( 'Walley Shipping Module', 'collector-checkout-for-woocommerce' );
			$this->method_description   = __( 'Enables Walley Checkout Delivery Module', 'collector-checkout-for-woocommerce' );
			$this->supports             = array(
				'shipping-zones',
				'instance-settings',
				'instance-settings-modal',
			);
			$this->collector_tax_amount = false;
			$this->init_form_fields();
			$this->init_settings();
		}
		/**
		 * Init form fields.
		 */
		public function init_form_fields() {
			$this->instance_form_fields = array(
				'title' => array(
					'title'       => __( 'Walley Shipping Module', 'collector-checkout-for-woocommerce' ),
					'type'        => 'title',
					'description' => __( 'There are currently no settings for Walley Shipping Module since this is controlled by the TMS-provider. If other plugins adds settings, these are shown below.', 'collector-checkout-for-woocommerce' ),
				),
			);
		}

		/**
		 * Check if shipping method should be available.
		 *
		 * @param array $package The shipping package.
		 * @return boolean
		 */
		public function is_available( $package ) {

			if ( null !== WC()->session->get( 'collector_delivery_module_enabled' ) && WC()->session->get( 'collector_delivery_module_enabled' ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Calculate shipping cost.
		 *
		 * @param array $package The shipping package.
		 * @return void
		 */
		public function calculate_shipping( $package = array() ) {
			$cost = 0;

			if ( ! is_checkout() ) {
				return;
			}

			if ( 'collector_checkout' !== WC()->session->get( 'chosen_payment_method' ) ) {
				return;
			}

			// If Delivery module is not used for the currency/country, return.
			if ( 'yes' !== is_collector_delivery_module( get_woocommerce_currency() ) ) {
				return;
			}

			$shipping_data = WC()->session->get( 'collector_delivery_module_data' );

			if ( empty( $shipping_data ) || ! isset( $shipping_data['cost'] ) || ! isset( $shipping_data['label'] ) ) {
				return;
			}

			if ( $shipping_data['shipping_vat'] > 0 ) {
				$cost = $shipping_data['cost'] / ( ( $shipping_data['shipping_vat'] / 100 ) + 1 );
			}
			$args = array(
				'id'      => $this->get_rate_id(),
				'label'   => $shipping_data['label'],
				'cost'    => round( $cost, 2 ),
				'package' => $package,
			);

			$this->add_rate( $args );
		}
	}

	add_filter( 'woocommerce_shipping_methods', 'add_collector_shipping_method' );
	/**
	 * Registers the shipping method.
	 *
	 * @param array $methods WooCommerce shipping methods.
	 * @return array
	 */
	function add_collector_shipping_method( $methods ) {
		$methods['collector_delivery_module'] = 'Collector_Delivery_Module_Shipping_Method';
		return $methods;
	}
}
