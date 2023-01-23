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
			if ( null !== WC()->session->get( 'collector_private_id' ) ) {
				$private_id    = WC()->session->get( 'collector_private_id' );
				$customer_type = WC()->session->get( 'collector_customer_type' );

				if ( method_exists( WC()->session, 'get' ) && ! empty( WC()->session->get( 'collector_delivery_module_data' )['label'] ) && ! empty( WC()->session->get( 'collector_delivery_module_data' )['cost'] ) ) {
					$shipping_data = WC()->session->get( 'collector_delivery_module_data' );
				} else {

					// Use new or old API.
					if ( walley_use_new_api() ) {
						$collector_order = CCO_WC()->api->get_walley_checkout(
							array(
								'private_id'    => $private_id,
								'customer_type' => $customer_type,
							)
						);
					} else {
						$collector_order = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
						$collector_order = $collector_order->request();
					}

					if ( isset( $collector_order['data']['shipping'] ) ) {
						$shipping_data = coc_get_shipping_data( $collector_order );
						WC()->session->set( 'collector_delivery_module_data', $shipping_data );
					} else {
						$shipping_data = array(
							'label'        => 'Collector',
							'shipping_id'  => '',
							'cost'         => 0,
							'shipping_vat' => 0,
						);
					}
				}

				if ( ! isset( $shipping_data['label'] ) ) {
					$shipping_data = $shipping_data[0];
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
				WC()->session->set( 'collector_delivery_module_enabled', true );
				$this->add_rate( $args );
			}
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
