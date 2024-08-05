<?php
/**
 * Class for Walley Checkout assets.
 *
 * @package Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Walley_Checkout_Assets
 */
class Walley_Checkout_Assets {
	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * If the gateway is enabled or not.
	 *
	 * @var string
	 */
	protected $enabled;

	/**
	 * The plugin test mode setting.
	 *
	 * @var string
	 */
	protected $test_mode;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->settings  = get_option( 'woocommerce_collector_checkout_settings', array() );
		$this->enabled   = $this->settings['enabled'] ?? 'no';
		$this->test_mode = $this->settings['test_mode'] ?? 'no';

		// Register widget scripts.
		add_action( 'init', array( $this, 'register_widget_assets' ) );

		// Load scripts.
		// add_action( 'wp_enqueue_scripts', array( $this, 'register_checkout_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'localize_and_enqueue_checkout_script' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_css' ) );

		// CSS for settings page.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_css' ) );
		// JS for metabox.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_metabox_scripts' ) );
	}

	/**
	 * Load scripts.
	 */
	public function register_checkout_scripts() {

		if ( 'yes' !== $this->enabled ) {
			return;
		}

		if ( ! is_checkout() ) {
			return;
		}

		wp_register_script( 'walley_checkout', COLLECTOR_BANK_PLUGIN_URL . '/assets/js/walley-checkout-for-woocommerce.js', array( 'jquery' ), COLLECTOR_BANK_VERSION, false );
	}

	/**
	 * Load Walley Checkout JS
	 **/
	public function localize_and_enqueue_checkout_script() {

		if ( 'yes' !== $this->enabled ) {
			return;
		}

		if ( ! is_checkout() ) {
			return;
		}

		wp_register_script( 'walley_checkout', COLLECTOR_BANK_PLUGIN_URL . '/assets/js/walley-checkout-for-woocommerce.js', array( 'jquery' ), COLLECTOR_BANK_VERSION, false );

		if ( 'NOK' === get_woocommerce_currency() ) {
			$locale = 'nb-NO';
		} elseif ( 'DKK' === get_woocommerce_currency() ) {
			$locale = 'en-DK';
		} elseif ( 'EUR' === get_woocommerce_currency() ) {
			$locale = 'fi-FI';
		} else {
			$locale = 'sv-SE';
		}

		$public_token = filter_input( INPUT_GET, 'public-token', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( is_wc_endpoint_url( 'order-received' ) ) {
			$is_thank_you_page = 'yes';
			$key               = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! empty( $key ) ) {
				$order_id = wc_get_order_id_by_order_key( sanitize_text_field( $key ) );
			} else {
				$order_id = '';
			}
			$purchase_status = filter_input( INPUT_GET, 'purchase-status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		} else {
			$is_thank_you_page = 'no';
			$order_id          = '';
			$purchase_status   = '';
		}
		$collector_settings       = get_option( 'woocommerce_collector_checkout_settings' );
		$data_action_color_button = isset( $collector_settings['checkout_button_color'] ) && ! empty( $collector_settings['checkout_button_color'] ) ? "data-action-color='" . $collector_settings['checkout_button_color'] . "'" : '';
		switch ( get_woocommerce_currency() ) {
			case 'SEK':
				$delivery_module = isset( $collector_settings['collector_delivery_module_se'] ) ? $collector_settings['collector_delivery_module_se'] : 'no';
				break;
			case 'NOK':
				$delivery_module = isset( $collector_settings['collector_delivery_module_no'] ) ? $collector_settings['collector_delivery_module_no'] : 'no';
				break;
			case 'DKK':
				$delivery_module = isset( $collector_settings['collector_delivery_module_dk'] ) ? $collector_settings['collector_delivery_module_dk'] : 'no';
				break;
			case 'EUR':
				$delivery_module = isset( $collector_settings['collector_delivery_module_fi'] ) ? $collector_settings['collector_delivery_module_fi'] : 'no';
				break;
			default:
				$delivery_module = isset( $collector_settings['collector_delivery_module_se'] ) ? $collector_settings['collector_delivery_module_se'] : 'no';
				break;
		}

		$standard_woo_checkout_fields = array(
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_address_2',
			'billing_postcode',
			'billing_city',
			'billing_phone',
			'billing_email',
			'billing_state',
			'billing_country',
			'billing_company',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_postcode',
			'shipping_city',
			'shipping_state',
			'shipping_country',
			'shipping_company',
			'terms',
			'terms-field',
			'_wp_http_referer',
		);
		wp_localize_script(
			'walley_checkout',
			'walleyParams',
			array(
				'ajaxurl'                     => admin_url( 'admin-ajax.php' ),
				'locale'                      => $locale,
				'is_thank_you_page'           => $is_thank_you_page,
				'order_id'                    => $order_id,
				'public_token'                => $public_token,
				'purchase_status'             => $purchase_status,
				'data_action_color_button'    => $data_action_color_button,
				'default_customer_type'       => wc_collector_get_default_customer_type(),
				'selected_customer_type'      => wc_collector_get_selected_customer_type(),
				'delivery_module'             => $delivery_module,
				'collector_nonce'             => wp_create_nonce( 'collector_nonce' ),
				'change_payment_method_url'   => WC_AJAX::get_endpoint( 'walley_change_payment_method' ),
				'change_payment_method_nonce' => wp_create_nonce( 'walley_change_payment_method' ),
				'log_to_file_url'             => WC_AJAX::get_endpoint( 'walley_log_js' ),
				'log_to_file_nonce'           => wp_create_nonce( 'walley_log_js' ),
				'get_order_url'               => WC_AJAX::get_endpoint( 'walley_get_order' ),
				'get_order_nonce'             => wp_create_nonce( 'walley_get_order' ),
				'standardWooCheckoutFields'   => $standard_woo_checkout_fields,
				'submitOrder'                 => WC_AJAX::get_endpoint( 'checkout' ),
				'get_public_token_url'        => WC_AJAX::get_endpoint( 'get_public_token' ),
				'get_checkout_thank_you_url'  => WC_AJAX::get_endpoint( 'get_checkout_thank_you' ),
				'get_customer_data_url'       => WC_AJAX::get_endpoint( 'get_customer_data' ),
				'customer_adress_updated_url' => WC_AJAX::get_endpoint( 'customer_adress_updated' ),
				'process_order_text'          => __( 'Please wait while we process your order.', 'collector-checkout-for-woocommerce' ),
				'no_shipping_message'         => apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ),
			)
		);
		wp_enqueue_script( 'walley_checkout' );
	}

	/**
	 * Load Walley Checkout CSS
	 **/
	public function enqueue_checkout_css() {
		// Load stylesheet for the checkout page.
		wp_register_style(
			'collector_checkout',
			COLLECTOR_BANK_PLUGIN_URL . '/assets/css/style.css',
			array(),
			COLLECTOR_BANK_VERSION
		);
		wp_enqueue_style( 'collector_checkout' );

		// Hide the Order overview data on thankyou page if it's a Collector Checkout purchase.
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			$key = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! empty( $key ) ) {
				$order_id = wc_get_order_id_by_order_key( wc_clean( $key ) );
				$order    = wc_get_order( $order_id );
				if ( ! empty( $order ) && 'collector_checkout' === $order->get_payment_method() ) {
					$custom_css = '
		                .woocommerce-order-overview {
				                        display: none;
								}';
					wp_add_inline_style( 'collector_checkout', $custom_css );
				}
			}
		}
	}

	/**
	 * Load Admin CSS
	 *
	 * @param string $hook      The current hook/settings page.
	 **/
	public function enqueue_admin_css( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'collector_checkout' !== $section ) {
			return;
		}

		wp_register_style( 'collector-checkout-admin', COLLECTOR_BANK_PLUGIN_URL . '/assets/css/admin.css', false, COLLECTOR_BANK_VERSION );
		wp_enqueue_style( 'collector-checkout-admin' );
	}

	/**
	 * Enqueues admin page scripts.
	 *
	 * @param string $hook The current hook/settings page.
	 */
	public function enqueue_admin_metabox_scripts( $hook ) {
		if ( ! walley_is_order_page() ) {
			return;
		}

		if ( ! walley_use_new_api() ) {
			return;
		}

		wp_register_script( 'walley-admin', COLLECTOR_BANK_PLUGIN_URL . '/assets/js/walley-order-meta-box.js', true, COLLECTOR_BANK_VERSION, true );
		wp_localize_script(
			'walley-admin',
			'walleyParams',
			array(
				'walley_reauthorize_order'       => WC_AJAX::get_endpoint( 'walley_reauthorize_order' ),
				'walley_reauthorize_order_nonce' => wp_create_nonce( 'walley_reauthorize_order' ),
				'order_id'                       => walley_get_the_ID(),
			)
		);
		wp_enqueue_script( 'walley-admin' );

		// CSS for metabox.
		wp_register_style( 'walley-metabox-css', COLLECTOR_BANK_PLUGIN_URL . '/assets/css/walley-metabox.css', false, COLLECTOR_BANK_VERSION );
		wp_enqueue_style( 'walley-metabox-css' );
	}

	/**
	 * Register the widget script for the part payment widget.
	 */
	public function register_widget_assets() {
		$base_src = 'yes' === $this->test_mode ? 'https://api.uat.walleydev.com' : 'https://api.walleypay.com';
		$src      = "{$base_src}/walley-checkout-loader.js";

		wp_register_script( 'walley-checkout-loader', $src, array(), null, true );
		wp_register_script( 'walley-part-payment-widget', COLLECTOR_BANK_PLUGIN_URL . '/assets/js/walley-part-payment-widget.js', array( 'walley-checkout-loader' ), COLLECTOR_BANK_VERSION, true );
		wp_register_style( 'walley-part-payment-widget', COLLECTOR_BANK_PLUGIN_URL . '/assets/css/walley-part-payment-widget.css', array(), COLLECTOR_BANK_VERSION, false );
	}
}
new Walley_Checkout_Assets();
