<?php
/**
 * Collector Bank for WooCommerce
 *
 * @package WC_Dibs_Easy
 *
 * @wordpress-plugin
 * Plugin Name:     Collector Checkout for WooCommerce
 * Plugin URI:      https://krokedil.se/collector/
 * Description:     Extends WooCommerce. Provides a <a href="https://www.collector.se/" target="_blank">Collector Checkout</a> checkout for WooCommerce.
 * Version:         1.5.1
 * Author:          Krokedil
 * Author URI:      https://krokedil.se/
 * Text Domain:     collector-checkout-for-woocommerce
 * Domain Path:     /languages
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 3.9.2
 *
 * Copyright:       © 2017-2020 Krokedil.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'COLLECTOR_BANK_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'COLLECTOR_BANK_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'COLLECTOR_BANK_VERSION', '1.5.1' );

if ( ! class_exists( 'Collector_Checkout' ) ) {
	class Collector_Checkout {

		public static $log = '';

		public function __construct() {

			// Initiate the gateway
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			// Load scripts
			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );

			// CSS for settings page
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_css' ) );

		}

		public function init() {

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			// Include the Classes
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-ajax-calls.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-post-checkout.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-admin-notices.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-order-emails.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-create-order-fallback.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-api-callbacks.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-status.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-templates.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-gdpr.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-confirmation.php';

			include_once COLLECTOR_BANK_PLUGIN_DIR . '/includes/collector-checkout-for-woocommerce-functions.php';

			// Include and add the Gateway
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-gateway.php';
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_collector_checkout_gateway' ) );

			// Include the Request Classes
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-initialize-checkout.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-update-fees.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-update-cart.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-get-checkout-information.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-update-reference.php';

			// Include the Request Helpers
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-cart.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-fees.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-header.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-calculate-auth.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-create-refund-data.php';

			// Include the Soap Request Classes
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-activate-invoice.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-cancel-invoice.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-credit-payment.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-adjust-invoice.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-part-credit-invoice.php';

			// Definitions
			define( 'COLLECTOR_BANK_REST_LIVE', 'https://checkout-api.collector.se' );
			define( 'COLLECTOR_BANK_REST_TEST', 'https://checkout-api-uat.collector.se' );
			define( 'COLLECTOR_BANK_SOAP_LIVE', 'https://ecommerce.collector.se/v3.0/InvoiceServiceV33.svc?wsdl' );
			define( 'COLLECTOR_BANK_SOAP_TEST', 'https://ecommercetest.collector.se/v3.0/InvoiceServiceV33.svc?wsdl' );

			// Translations
			load_plugin_textdomain( 'collector-checkout-for-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		public function load_scripts() {
			// Enqueue scripts
			wp_enqueue_script( 'jquery' );
			if ( is_checkout() ) {
				wp_register_script( 'checkout', plugins_url( '/assets/js/checkout.js', __FILE__ ), array( 'jquery' ), COLLECTOR_BANK_VERSION );

				if ( 'NOK' == get_woocommerce_currency() ) {
					$locale = 'nb-NO';
				} elseif ( 'DKK' == get_woocommerce_currency() ) {
					$locale = 'en-DK';
				} elseif ( 'EUR' == get_woocommerce_currency() ) {
					$locale = 'fi-FI';
				} else {
					$locale = 'sv-SE';
				}
				if ( isset( $_GET['public-token'] ) ) {
					$public_token = sanitize_text_field( $_GET['public-token'] );
				} else {
					$public_token = '';
				}
				if ( WC()->session->get( 'collector_private_id' ) ) {
					$checkout_initiated = 'yes';
				} else {
					$checkout_initiated = 'no';
				}
				if ( isset( $_GET['payment_successful'] ) && '1' == $_GET['payment_successful'] ) {
					$payment_successful = '1';
				} else {
					$payment_successful = '0';
				}
				if ( is_wc_endpoint_url( 'order-received' ) ) {
					$is_thank_you_page = 'yes';
					if ( isset( $_GET['key'] ) ) {
						$order_id = wc_get_order_id_by_order_key( sanitize_text_field( $_GET['key'] ) );
					} else {
						$order_id = '';
					}
					if ( isset( $_GET['purchase-status'] ) ) {
						$purchase_status = sanitize_text_field( $_GET['purchase-status'] );
					} else {
						$purchase_status = '';
					}
				} else {
					$is_thank_you_page = 'no';
					$order_id          = '';
					$purchase_status   = '';
				}

				$must_login = 'no';
				if ( ! is_user_logged_in() && ( 'no' === get_option( 'woocommerce_enable_guest_checkout' ) ) ) {
					if ( method_exists( WC()->customer, 'get_billing_email' ) && ! empty( WC()->customer->get_billing_email() ) ) {
						if ( email_exists( WC()->customer->get_billing_email() ) ) {
							// Email exist in a user account.
							$must_login = 'yes';
						}
					}
				}

				$collector_settings       = get_option( 'woocommerce_collector_checkout_settings' );
				$data_action_color_button = isset( $collector_settings['checkout_button_color'] ) && ! empty( $collector_settings['checkout_button_color'] ) ? "data-action-color='" . $collector_settings['checkout_button_color'] . "'" : '';
				wp_localize_script(
					'checkout',
					'wc_collector_checkout',
					array(
						'ajaxurl'                       => admin_url( 'admin-ajax.php' ),
						'locale'                        => $locale,
						'is_thank_you_page'             => $is_thank_you_page,
						'is_collector_confirmation'     => ( is_collector_confirmation() ) ? 'yes' : 'no',
						'order_id'                      => $order_id,
						'public_token'                  => $public_token,
						'checkout_initiated'            => $checkout_initiated,
						'payment_successful'            => $payment_successful,
						'purchase_status'               => $purchase_status,
						'data_action_color_button'      => $data_action_color_button,
						'must_login'                    => $must_login,
						'must_login_message'            => apply_filters( 'woocommerce_registration_error_email_exists', __( 'An account is already registered with your email address. Please log in.', 'woocommerce' ) ),
						'default_customer_type'         => wc_collector_get_default_customer_type(),
						'selected_customer_type'        => wc_collector_get_selected_customer_type(),
						'collector_nonce'               => wp_create_nonce( 'collector_nonce' ),
						'refresh_checkout_fragment_url' => WC_AJAX::get_endpoint( 'update_fragment' ),
						'get_public_token_url'          => WC_AJAX::get_endpoint( 'get_public_token' ),
						'add_customer_order_note_url'   => WC_AJAX::get_endpoint( 'add_customer_order_note' ),
						'get_checkout_thank_you_url'    => WC_AJAX::get_endpoint( 'get_checkout_thank_you' ),
						'get_customer_data_url'         => WC_AJAX::get_endpoint( 'get_customer_data' ),
						'customer_adress_updated_url'   => WC_AJAX::get_endpoint( 'customer_adress_updated' ),
						'update_checkout_url'           => WC_AJAX::get_endpoint( 'update_checkout' ),
						'checkout_error'                => WC_AJAX::get_endpoint( 'checkout_error' ),
					)
				);
				wp_enqueue_script( 'checkout' );
			}

			// Load stylesheet for the checkout page
			wp_register_style(
				'collector_checkout',
				plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
				array(),
				COLLECTOR_BANK_VERSION
			);
			wp_enqueue_style( 'collector_checkout' );

			// Hide the Order overview data on thankyou page if it's a Collector Checkout purchase
			if ( is_wc_endpoint_url( 'order-received' ) ) {
				if ( isset( $_GET['key'] ) ) {
					$order_id = wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) );
					$order    = wc_get_order( $order_id );
					if ( 'collector_checkout' == $order->get_payment_method() ) {
						$custom_css = '
		                .woocommerce-order-overview {
				                        display: none;
								}';
						wp_add_inline_style( 'collector_checkout', $custom_css );
					}
				}
			}

		}

		public static function log( $message ) {
			$dibs_settings = get_option( 'woocommerce_collector_checkout_settings' );
			if ( 'yes' === $dibs_settings['debug_mode'] ) {
				if ( empty( self::$log ) ) {
					self::$log = new WC_Logger();
				}
				self::$log->add( 'collector_checkout', $message );
			}
		}

		/**
		 * Load Admin CSS
		 **/
		public function enqueue_admin_css( $hook ) {
			if ( 'woocommerce_page_wc-settings' == $hook && isset( $_GET['section'] ) && 'collector_checkout' == $_GET['section'] ) {
				wp_register_style( 'collector-checkout-admin', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', false );
				wp_enqueue_style( 'collector-checkout-admin' );
			}
		}


		public function add_collector_checkout_gateway( $methods ) {
			$methods[] = 'Collector_Checkout_Gateway';

			return $methods;
		}
	}
}
$collector_checkout = new Collector_Checkout();

function wc_collector_get_available_customer_types() {
	$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );

	$collector_b2c_se = ( isset( $collector_settings['collector_merchant_id_se_b2c'] ) ) ? $collector_settings['collector_merchant_id_se_b2c'] : '';
	$collector_b2b_se = ( isset( $collector_settings['collector_merchant_id_se_b2b'] ) ) ? $collector_settings['collector_merchant_id_se_b2b'] : '';
	$collector_b2c_no = ( isset( $collector_settings['collector_merchant_id_no_b2c'] ) ) ? $collector_settings['collector_merchant_id_no_b2c'] : '';
	$collector_b2b_no = ( isset( $collector_settings['collector_merchant_id_no_b2b'] ) ) ? $collector_settings['collector_merchant_id_no_b2b'] : '';
	$collector_b2c_dk = ( isset( $collector_settings['collector_merchant_id_dk_b2c'] ) ) ? $collector_settings['collector_merchant_id_dk_b2c'] : '';
	$collector_b2c_fi = ( isset( $collector_settings['collector_merchant_id_fi_b2c'] ) ) ? $collector_settings['collector_merchant_id_fi_b2c'] : '';
	$collector_b2b_fi = ( isset( $collector_settings['collector_merchant_id_fi_b2b'] ) ) ? $collector_settings['collector_merchant_id_fi_b2b'] : '';

	if ( ( 'SEK' == get_woocommerce_currency() && $collector_b2c_se && $collector_b2b_se ) || ( 'NOK' == get_woocommerce_currency() && $collector_b2c_no && $collector_b2b_no ) || ( 'EUR' == get_woocommerce_currency() && $collector_b2c_fi && $collector_b2b_fi ) ) {
		return 'collector-b2c-b2b';
	} elseif ( ( 'SEK' == get_woocommerce_currency() && $collector_b2c_se ) || ( 'NOK' == get_woocommerce_currency() && $collector_b2c_no ) || ( 'DKK' == get_woocommerce_currency() && $collector_b2c_dk ) || ( 'EUR' == get_woocommerce_currency() && $collector_b2c_fi ) ) {
		return 'collector-b2c';
	} elseif ( $collector_b2b_se || $collector_b2b_no || $collector_b2b_fi ) {
		return 'collector-b2b';
	}
}

function wc_collector_get_default_customer_type() {
	$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );

	$default_customer_type = $collector_settings['collector_default_customer'];
	$collector_b2c_se      = ( isset( $collector_settings['collector_merchant_id_se_b2c'] ) ) ? $collector_settings['collector_merchant_id_se_b2c'] : '';
	$collector_b2b_se      = ( isset( $collector_settings['collector_merchant_id_se_b2b'] ) ) ? $collector_settings['collector_merchant_id_se_b2b'] : '';
	$collector_b2c_no      = ( isset( $collector_settings['collector_merchant_id_no_b2c'] ) ) ? $collector_settings['collector_merchant_id_no_b2c'] : '';
	$collector_b2b_no      = ( isset( $collector_settings['collector_merchant_id_no_b2b'] ) ) ? $collector_settings['collector_merchant_id_no_b2b'] : '';
	$collector_b2c_dk      = ( isset( $collector_settings['collector_merchant_id_dk_b2c'] ) ) ? $collector_settings['collector_merchant_id_dk_b2c'] : '';
	$collector_b2c_fi      = ( isset( $collector_settings['collector_merchant_id_fi_b2c'] ) ) ? $collector_settings['collector_merchant_id_fi_b2c'] : '';
	$collector_b2b_fi      = ( isset( $collector_settings['collector_merchant_id_fi_b2b'] ) ) ? $collector_settings['collector_merchant_id_fi_b2b'] : '';

	if ( 'NOK' == get_woocommerce_currency() ) {
		if ( $collector_b2c_no && empty( $default_customer_type ) ) {
			return 'b2c';
		} elseif ( $collector_b2b_no && empty( $default_customer_type ) ) {
			return 'b2b';
		} elseif ( $collector_b2c_no && 'b2c' == $default_customer_type ) {
			return 'b2c';
		} elseif ( $collector_b2b_no && 'b2b' == $default_customer_type ) {
			return 'b2b';
		} elseif ( empty( $collector_b2c_no ) && ! empty( $collector_b2b_no ) && 'b2c' == $default_customer_type ) {
			return 'b2b';
		} else {
			return 'b2c';
		}
	}

	if ( 'SEK' == get_woocommerce_currency() ) {
		if ( $collector_b2c_se && empty( $default_customer_type ) ) {
			return 'b2c';
		} elseif ( $collector_b2b_se && empty( $default_customer_type ) ) {
			return 'b2b';
		} elseif ( $collector_b2c_se && 'b2c' == $default_customer_type ) {
			return 'b2c';
		} elseif ( $collector_b2b_se && 'b2b' == $default_customer_type ) {
			return 'b2b';
		} elseif ( empty( $collector_b2c_se ) && ! empty( $collector_b2b_se ) && 'b2c' == $default_customer_type ) {
			return 'b2b';
		} else {
			return 'b2c';
		}
	}

	if ( 'EUR' == get_woocommerce_currency() ) {
		if ( $collector_b2c_fi && empty( $default_customer_type ) ) {
			return 'b2c';
		} elseif ( $collector_b2b_fi && empty( $default_customer_type ) ) {
			return 'b2b';
		} elseif ( $collector_b2c_fi && 'b2c' == $default_customer_type ) {
			return 'b2c';
		} elseif ( $collector_b2b_fi && 'b2b' == $default_customer_type ) {
			return 'b2b';
		} elseif ( empty( $collector_b2c_fi ) && ! empty( $collector_b2b_fi ) && 'b2c' == $default_customer_type ) {
			return 'b2b';
		} else {
			return 'b2c';
		}
	}

	if ( 'DKK' == get_woocommerce_currency() ) {
		return 'b2c';
	}

}

function wc_collector_get_selected_customer_type() {
	$selected_customer_type = false;
	if ( method_exists( WC()->session, 'get' ) ) {
		$selected_customer_type = WC()->session->get( 'collector_customer_type' );
	}

	if ( empty( $selected_customer_type ) ) {
		$selected_customer_type = wc_collector_get_default_customer_type();
	}

	return $selected_customer_type;
}

/**
 * Get localized and formatted payment method name.
 *
 * @param $payment_method
 *
 * @return string
 */
function wc_collector_get_payment_method_name( $payment_method ) {
	switch ( $payment_method ) {

		case 'Direct Invoice':
		case 'DirectInvoice':
			$payment_method = __( 'Collector Invoice', 'collector-checkout-for-woocommerce' );
			break;
		case 'Account':
			$payment_method = __( 'Collector Account', 'collector-checkout-for-woocommerce' );
			break;
		case 'Part Payment':
		case 'PartPayment':
			$payment_method = __( 'Collector Part Payment', 'collector-checkout-for-woocommerce' );
			break;
		case 'Campaign':
			$payment_method = __( 'Collector Campaign', 'collector-checkout-for-woocommerce' );
			break;
		case 'Card':
			$payment_method = __( 'Collector Card', 'collector-checkout-for-woocommerce' );
			break;
		case 'Bank Transfer':
		case 'BankTransfer':
			$payment_method = __( 'Collector Bank Transfer', 'collector-checkout-for-woocommerce' );
			break;
		default:
			break;
	}

	return $payment_method;
}
