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
	 * Class constructor.
	 */
	public function __construct() {

		// Load scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );

		// CSS for settings page.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_css' ) );

	}

	/**
	 * Load scripts.
	 */
	public function load_scripts() {
		wp_enqueue_script( 'jquery' );
		if ( is_checkout() ) {
			wp_register_script( 'checkout', plugins_url( '/assets/js/checkout.js', __FILE__ ), array( 'jquery' ), COLLECTOR_BANK_VERSION, false );

			if ( 'NOK' === get_woocommerce_currency() ) {
				$locale = 'nb-NO';
			} elseif ( 'DKK' === get_woocommerce_currency() ) {
				$locale = 'en-DK';
			} elseif ( 'EUR' === get_woocommerce_currency() ) {
				$locale = 'fi-FI';
			} else {
				$locale = 'sv-SE';
			}
			$public_token = filter_input( INPUT_GET, 'public-token', FILTER_SANITIZE_STRING );

			if ( WC()->session->get( 'collector_private_id' ) ) {
				$checkout_initiated = 'yes';
			} else {
				$checkout_initiated = 'no';
			}
			$payment_successful = filter_input( INPUT_GET, 'payment_successful', FILTER_SANITIZE_STRING );
			if ( empty( $payment_successful ) ) {
				$payment_successful = '0';
			} else {
				$payment_successful = '1';
			}
			if ( is_wc_endpoint_url( 'order-received' ) ) {
				$is_thank_you_page = 'yes';
				$key               = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
				if ( ! empty( $key ) ) {
					$order_id = wc_get_order_id_by_order_key( sanitize_text_field( $key ) );
				} else {
					$order_id = '';
				}
				$purchase_status = filter_input( INPUT_GET, 'purchase-status', FILTER_SANITIZE_STRING );
			} else {
				$is_thank_you_page = 'no';
				$order_id          = '';
				$purchase_status   = '';
			}
			$collector_settings       = get_option( 'woocommerce_collector_checkout_settings' );
			$data_action_color_button = isset( $collector_settings['checkout_button_color'] ) && ! empty( $collector_settings['checkout_button_color'] ) ? "data-action-color='" . $collector_settings['checkout_button_color'] . "'" : '';
			$checkout_version         = isset( $collector_settings['checkout_version'] ) ? $collector_settings['checkout_version'] : 'v1';
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
			wp_localize_script(
				'checkout',
				'wc_collector_checkout',
				array(
					'ajaxurl'                             => admin_url( 'admin-ajax.php' ),
					'locale'                              => $locale,
					'is_thank_you_page'                   => $is_thank_you_page,
					'is_collector_confirmation'           => ( is_collector_confirmation() ) ? 'yes' : 'no',
					'order_id'                            => $order_id,
					'public_token'                        => $public_token,
					'checkout_initiated'                  => $checkout_initiated,
					'payment_successful'                  => $payment_successful,
					'purchase_status'                     => $purchase_status,
					'data_action_color_button'            => $data_action_color_button,
					'checkout_version'                    => $checkout_version,
					'default_customer_type'               => wc_collector_get_default_customer_type(),
					'selected_customer_type'              => wc_collector_get_selected_customer_type(),
					'delivery_module'                     => $delivery_module,
					'collector_nonce'                     => wp_create_nonce( 'collector_nonce' ),
					'refresh_checkout_fragment_url'       => WC_AJAX::get_endpoint( 'update_fragment' ),
					'get_public_token_url'                => WC_AJAX::get_endpoint( 'get_public_token' ),
					'add_customer_order_note_url'         => WC_AJAX::get_endpoint( 'add_customer_order_note' ),
					'get_checkout_thank_you_url'          => WC_AJAX::get_endpoint( 'get_checkout_thank_you' ),
					'get_customer_data_url'               => WC_AJAX::get_endpoint( 'get_customer_data' ),
					'customer_adress_updated_url'         => WC_AJAX::get_endpoint( 'customer_adress_updated' ),
					'update_checkout_url'                 => WC_AJAX::get_endpoint( 'update_checkout' ),
					'checkout_error'                      => WC_AJAX::get_endpoint( 'checkout_error' ),
					'update_delivery_module_shipping_url' => WC_AJAX::get_endpoint( 'update_delivery_module_shipping' ),
					'process_order_text'                  => __( 'Please wait while we process your order.', 'collector-checkout-for-woocommerce' ),
					'no_shipping_message'                 => apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ),
				)
			);
			wp_enqueue_script( 'checkout' );
		}

		// Load stylesheet for the checkout page.
		wp_register_style(
			'collector_checkout',
			plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
			array(),
			COLLECTOR_BANK_VERSION
		);
		wp_enqueue_style( 'collector_checkout' );

		// Hide the Order overview data on thankyou page if it's a Collector Checkout purchase.
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			$key = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
			if ( ! empty( $key ) ) {
				$order_id = wc_get_order_id_by_order_key( wc_clean( $key ) );
				$order    = wc_get_order( $order_id );
				if ( 'collector_checkout' === $order->get_payment_method() ) {
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

		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_STRING );
		if ( 'collector_checkout' !== $section ) {
			return;
		}

		wp_register_style( 'collector-checkout-admin', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', false, COLLECTOR_BANK_VERSION );
		wp_enqueue_style( 'collector-checkout-admin' );
	}





} new Walley_Checkout_Assets();
