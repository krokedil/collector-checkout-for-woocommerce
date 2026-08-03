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
		 * The shipping method id.
		 *
		 * @var string
		 */
		public $id;

		/**
		 * The shipping method instance id.
		 *
		 * @var integer
		 */
		public $instance_id;

		/**
		 * The shipping method title.
		 *
		 * @var string
		 */
		public $title;

		/**
		 * The shipping method method title.
		 *
		 * @var string
		 */
		public $method_title;

		/**
		 * The shipping method method description.
		 *
		 * @var string
		 */
		public $method_description;

		/**
		 * The shipping method supports.
		 *
		 * @var array
		 */
		public $supports;

		/**
		 * The shipping method collector tax amount.
		 *
		 * @var boolean
		 */
		public $collector_tax_amount;

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
				'title'      => array(
					'title'       => __( 'Walley Shipping Module', 'collector-checkout-for-woocommerce' ),
					'type'        => 'title',
					'description' => __( 'There are currently no settings for Walley Shipping Module since this is controlled by the TMS-provider. If other plugins adds settings, these are shown below.', 'collector-checkout-for-woocommerce' ),
				),
				'tax_status' => array(
					'title'   => __( 'Tax status', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Reuses WooCommerce's translation of its own setting.
					'type'    => 'select',
					'class'   => 'wc-enhanced-select',
					'default' => 'taxable',
					'options' => array(
						'taxable' => __( 'Taxable', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Reuses WooCommerce's translation of its own setting.
						// @todo Offer a 'none' tax status once the logic for it is implemented.
					),
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
			if ( ! is_checkout() ) {
				return;
			}

			if ( 'collector_checkout' !== WC()->session->get( 'chosen_payment_method' ) ) {
				return;
			}

			// If Delivery module is not used for the currency/country, return.
			if ( ! is_collector_delivery_module( get_woocommerce_currency() ) ) {
				return;
			}

			$shipping_data = WC()->session->get( 'collector_delivery_module_data' );

			if ( empty( $shipping_data ) || ! isset( $shipping_data['cost'] ) || ! isset( $shipping_data['label'] ) ) {
				return;
			}

			// Walley reports the fee including VAT. Without a tax rate there is nothing to deduct.
			$shipping_vat = $shipping_data['shipping_vat'] ?? 0;
			$cost         = $shipping_vat > 0 ? $shipping_data['cost'] / ( ( $shipping_vat / 100 ) + 1 ) : $shipping_data['cost'];

			$args = array(
				'id'      => $this->get_rate_id(),
				'label'   => $shipping_data['label'],
				'cost'    => round( $cost, 2 ),
				'package' => $package,
			);

			$this->add_rate( $args );
		}
	}

	// add_collector_shipping_method() is declared in includes/collector-checkout-for-woocommerce-functions.php.
	add_filter( 'woocommerce_shipping_methods', 'add_collector_shipping_method' );
}
