<?php
/**
 * Class for the Part Payment widget support.
 *
 * @package Collector_Checkout/Classes
 */

use Krokedil\ShopWidgets\CartWidget;
use Krokedil\ShopWidgets\ProductWidget;

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Part_Payment_Widget class.
 */
class Walley_Part_Payment_Widget {
	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Instance of the cart widget class.
	 *
	 * @var CartWidget
	 */
	protected $cart_widget;

	/**
	 * Instance of the product widget class.
	 *
	 * @var ProductWidget
	 */
	protected $product_widget;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_collector_checkout_settings', array() );

		$this->init();

		// Add a hidden input field with the cart total value, so we can use it in the frontend to update the widget.
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'add_cart_total_input' ) );

		add_filter( 'collector_checkout_settings', array( $this, 'append_settings' ) );
		add_filter( 'script_loader_tag', array( $this, 'set_script_data_tags' ), 10, 3 );
	}

	/**
	 * Initialize the class.
	 *
	 * @return void
	 */
	public function init() {
		$this->cart_widget = new CartWidget( 'collector_checkout', $this->settings );

		$this->cart_widget
			->set_output( $this->get_output() )
			->set_script_handles( array( 'walley-part-payment-widget', 'walley-checkout-loader' ) )
			->set_style_handles( array( 'walley-part-payment-widget' ) )
			->init();

		$this->product_widget = new ProductWidget( 'collector_checkout', $this->settings );

		$this->product_widget
			->set_output( $this->get_output() )
			->set_script_handles( array( 'walley-part-payment-widget', 'walley-checkout-loader' ) )
			->set_style_handles( array( 'walley-part-payment-widget' ) )
			->init();
	}

	/**
	 * Get the output for the widget.
	 *
	 * @return string
	 */
	public function get_output() {
		ob_start();
		?>
		<div id="walley-part-payment-widget" class="walley-part-payment-widget__wrapper"></div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Append settings to the plugin settings.
	 *
	 * @param array $settings The plugin settings.
	 */
	public function append_settings( $settings ) {
		$cart_widget_settings    = $this->cart_widget->get_setting_fields( __( 'Cart Part Payment Widget', 'collector-checkout-for-woocommerce' ) );
		$product_widget_settings = $this->product_widget->get_setting_fields( __( 'Product Part Payment Widget', 'collector-checkout-for-woocommerce' ) );

		$settings = array_merge( $settings, $product_widget_settings, $cart_widget_settings );

		return $settings;
	}

	/**
	 * Set the script data tags for the widget.
	 *
	 * @param string $tag The script tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 * @param string $src The script's source URL.
	 *
	 * @return string
	 */
	public function set_script_data_tags( $tag, $handle, $src ) {
		if ( 'walley-checkout-loader' !== $handle ) {
			return $tag;
		}

		// Add the data tags to the script tag.
		$tag = '<script id="' . $handle . '" src="' . esc_url( $src ) . '"' . $this->get_data_tags_string() . '></script>';
		return $tag;
	}

	/**
	 * Get the data tags string to add to the script tag.
	 *
	 * @return string
	 */
	public function get_data_tags_string() {
		$token  = $this->get_cart_token();
		$amount = $this->get_amount();

		$data_tags = array(
			'token'        => $token,
			'widget'       => 'part-payment',
			'amount'       => $amount,
			'lang'         => get_locale(),
			'container-id' => 'walley-part-payment-widget',
		);

		$data_tags_string = '';

		foreach ( $data_tags as $key => $value ) {
			$data_tags_string .= " data-$key='$value'";
		}

		return $data_tags_string;
	}

	/**
	 * Get the cart token.
	 *
	 * @return string
	 */
	public function get_cart_token() {
		// Check if we have a transient for the token already.
		$token = get_transient( 'walley_part_payment_token' );

		if ( false === $token ) {
			// If not, get a new token.
			if ( ! property_exists( CCO_WC(), 'api' ) ) {
				return '';
			}

			$response = CCO_WC()->api->create_widget_token();

			if ( is_wp_error( $response ) ) {
				return '';
			}

			$token   = $response['data']['widgetToken'];
			$expires = strtotime( $response['data']['expiresAt'] );

			// Set the transient for the token.
			set_transient( 'walley_part_payment_token', $token, $expires - time() );
		}

		return $token;
	}

	/**
	 * Get the amount to display in the widget.
	 *
	 * @return int
	 */
	public function get_amount() {
		// If we are on the cart page, get the cart total.
		if ( is_cart() ) {
			return $this->get_cart_amount();
		}

		// If we are on a product page, get the product price.
		if ( is_product() ) {
			return $this->get_product_amount();
		}

		// Else return 0.
		return 0;
	}

	/**
	 * Get the amount for the cart page.
	 *
	 * @return int
	 */
	public function get_cart_amount() {
		return WC()->cart->total * 100;
	}

	/**
	 * Get the amount for the product page.
	 *
	 * @return int
	 */
	public function get_product_amount() {
		// Get the product that we are currently on.
		$product = wc_get_product();

		// If the product is a variable product, get the min price.
		if ( $product->is_type( 'variable' ) ) {
			return floatval( $product->get_variation_price( 'min' ) ) * 100;
		}

		// Else return the price.
		return floatval( $product->get_price() ) * 100;
	}

	/**
	 * Add a hidden input field with the cart totals.
	 *
	 * @return void
	 */
	public function add_cart_total_input() {
		?>
		<input type="hidden" id="walley-cart-total" name="walley-cart-total" value="<?php echo esc_html( WC()->cart->get_total( 'walley' ) ); ?>">
		<?php
	}
}
