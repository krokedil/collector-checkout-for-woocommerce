<?php
/**
 * Collector checkout template.
 *
 * @package Collector_Checkout/Classes
 */

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
		$cco_settings          = get_option( 'woocommerce_collector_checkout_settings' );
		$this->checkout_layout = ( isset( $cco_settings['checkout_layout'] ) ) ? $cco_settings['checkout_layout'] : 'one_column_checkout';

		// Override the checkout template.
		add_filter( 'wc_get_template', array( $this, 'override_template' ), 999, 2 );

		// Template hooks.
		add_action( 'collector_wc_after_order_review', 'collector_wc_show_another_gateway_button', 20 );
		add_action( 'collector_wc_after_order_review', array( $this, 'add_extra_checkout_fields' ), 10 );

		add_action( 'collector_wc_before_iframe', 'collector_wc_show_customer_type_switcher', 20 );
		add_action( 'collector_wc_after_iframe', array( $this, 'add_wc_form' ), 10 );

		// Body class. For checkout layout setting.
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Override checkout form template if Collector Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name ) {
		if ( is_checkout() ) {

			// Don't display Collector Checkout template if we have a cart that doesn't needs payment.
			if ( ! WC()->cart->needs_payment() ) {
				return $template;
			}

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
					// If chosen payment method does not exist and Collector is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
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

	/**
	 * Adds the extra checkout field div to the checkout page.
	 */
	public function add_extra_checkout_fields() {
		do_action( 'collector_wc_before_extra_fields' );
		?>
		<div id="walley-extra-checkout-fields">
		</div>
		<?php
		do_action( 'collector_wc_after_extra_fields' );
	}

	/**
	 * Adds the WC form and other fields to the checkout page.
	 *
	 * @return void
	 */
	public function add_wc_form() {
		?>
		<div aria-hidden="true" id="collector-wc-form" style="position:absolute; top:0; left:-99999px;">
			<?php do_action( 'woocommerce_checkout_billing' ); ?>
			<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			<div id="collector-nonce-wrapper">
				<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
				<?php wc_get_template( 'checkout/terms.php' ); ?>
			</div>
			<input id="payment_method_collector_checkout" type="radio" class="input-radio" name="payment_method" value="collector_checkout" checked="checked" />
		</div>
		<?php
	}

	/**
	 * Add cco-two-column-checkout body class.
	 *
	 * @param array $class CSS classes used in body tag.
	 *
	 * @return array
	 */
	public function add_body_class( $class ) {
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			// Don't display Collector body classes if we have a cart that doesn't needs payment.
			if ( method_exists( WC()->cart, 'needs_payment' ) && ! WC()->cart->needs_payment() ) {
				return $class;
			}

			$first_gateway = '';
			if ( WC()->session->get( 'chosen_payment_method' ) ) {
				$first_gateway = WC()->session->get( 'chosen_payment_method' );
			} else {
				$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
				reset( $available_payment_gateways );
				$first_gateway = key( $available_payment_gateways );
			}

			if ( 'collector_checkout' === $first_gateway && 'two_column_left' === $this->checkout_layout ) {
				$class[] = 'cco-two-column-left';
			}
			if ( 'collector_checkout' === $first_gateway && 'two_column_left_sf' === $this->checkout_layout ) {
				$class[] = 'cco-two-column-left-sf';
			}

			if ( 'collector_checkout' === $first_gateway && 'two_column_right' === $this->checkout_layout ) {
				$class[] = 'cco-two-column-right';
			}
		}
		return $class;
	}
}

Collector_Checkout_Templates::get_instance();
