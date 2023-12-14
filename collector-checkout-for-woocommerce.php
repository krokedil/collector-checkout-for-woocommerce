<?php // phpcs:ignore WordPress.NamingConventions.ValidFileName
/**
 * Collector Bank for WooCommerce
 *
 * @package Collector_Checkout
 *
 * @wordpress-plugin
 * Plugin Name:     Walley Checkout for WooCommerce
 * Plugin URI:      https://krokedil.se/produkt/walley-checkout/
 * Description:     Extends WooCommerce. Provides a <a href="https://www.walley.se/foretag/checkout/" target="_blank">Walley Checkout</a> checkout for WooCommerce.
 * Version:         4.0.10
 * Author:          Krokedil
 * Author URI:      https://krokedil.se/
 * Text Domain:     collector-checkout-for-woocommerce
 * Domain Path:     /languages
 *
 * WC requires at least: 6.0.0
 * WC tested up to: 8.3.0
 *
 * Copyright:       © 2017-2023 Krokedil.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'COLLECTOR_BANK_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'COLLECTOR_BANK_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'COLLECTOR_BANK_VERSION', '4.0.10' );
define( 'COLLECTOR_DB_VERSION', '1' );

if ( ! class_exists( 'Collector_Checkout' ) ) {
	/**
	 * Main class for the plugin.
	 */
	class Collector_Checkout {

		/**
		 * Reference to logging class.
		 *
		 * @var Collector_Checkout_Logger
		 */
		public $logger;

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
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		public function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Class constructor.
		 */
		public function __construct() {

			$collector_settings         = get_option( 'woocommerce_collector_checkout_settings', array() );
			$this->walley_api_client_id = $collector_settings['walley_api_client_id'] ?? '';
			$this->walley_api_secret    = $collector_settings['walley_api_secret'] ?? '';

			// Initiate the gateway.
			add_action( 'plugins_loaded', array( $this, 'init' ) );

			// Maybe create Collector db table.
			// @todo - will be removed in next version.
			// add_action( 'init', array( $this, 'collector_maybe_create_db_table' ) );

			// Maybe schedule action.
			// @todo - will be removed in next version.
			// add_action( 'init', array( $this, 'collector_maybe_schedule_action' ) );
			// Clean Collector db.
			add_action( 'collector_clean_db', array( $this, 'collector_clean_db_callback' ) );

		}

		/**
		 * Initiates the plugin.
		 *
		 * @return void
		 */
		public function init() {

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			// Include the Classes.
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-logger.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-ajax-calls.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-admin-notices.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-order-emails.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-api-callbacks.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-status.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-templates.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-gdpr.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-pay-for-order-confirmation.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-db.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-shipping-method.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-delivery-module.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-product-fields.php';

			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-walley-checkout-assets.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-walley-checkout.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-walley-checkout-confirmation.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-walley-checkout-session.php';

			// Order management. SOAP will be deprecated.
			if ( ! empty( $this->walley_api_client_id ) && ! empty( $this->walley_api_secret ) ) {

				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-walley-checkout-api.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-walley-checkout-order-management.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-walley-checkout-order-actions.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-walley-checkout-meta-box.php';

				// New request class files.
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-walley-checkout-request.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-walley-checkout-request-get.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-walley-checkout-request-put.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-walley-checkout-request-post.php';

				// New Checkout request class files.
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/checkouts/get/class-walley-checkout-request-get-checkout.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/checkouts/post/class-walley-checkout-request-initialize-checkout.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/checkouts/put/class-walley-checkout-request-update-checkout.php';
				// include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/checkouts/put/class-walley-checkout-request-update-cart.php';
				// include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/checkouts/put/class-walley-checkout-request-update-fees.php';
				// include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/checkouts/put/class-walley-checkout-request-update-metadata.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/checkouts/put/class-walley-checkout-request-set-order-reference.php';

				// New OM request class files.
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/manage-orders/get/class-walley-checkout-request-get-order.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/manage-orders/post/class-walley-checkout-request-reauthorize-order.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/manage-orders/post/class-walley-checkout-request-capture-order.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/manage-orders/post/class-walley-checkout-request-part-capture-order.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/manage-orders/post/class-walley-checkout-request-cancel-order.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/manage-orders/post/class-walley-checkout-request-refund-order.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/manage-orders/post/class-walley-checkout-request-refund-order-by-amount.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/oauth2/class-walley-checkout-request-access-token.php';

				// Set class variables related to new Management API.
				$this->api              = new Walley_Checkout_API();
				$this->order_management = new Walley_Checkout_Order_Management();

			} else {

				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-post-checkout.php';

				// Include the Checkout Request Classes.
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-initialize-checkout.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-update-fees.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-update-cart.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-update-metadata.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-get-checkout-information.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-update-reference.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/class-collector-checkout-requests-paylink.php';

				// Include the Soap Request Classes.
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-activate-invoice.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-part-activate-invoice.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-cancel-invoice.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-credit-payment.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-adjust-invoice.php';
				include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/soap/class-collector-checkout-soap-requests-part-credit-invoice.php';

				// Set class variables related to old Payments/SOAP API.
				$this->order_management = new Collector_Checkout_Post_Checkout();
			}

			// Functions.
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/includes/collector-checkout-for-woocommerce-functions.php';

			// Include and add the Gateway.
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/class-collector-checkout-gateway.php';
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_collector_checkout_gateway' ) );

			// Include the Request Helpers.
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-cart.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-fees.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-header.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-calculate-auth.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-create-refund-data.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-helper-order.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-helper-order-om.php';
			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-collector-checkout-requests-helper-order-fees.php';

			include_once COLLECTOR_BANK_PLUGIN_DIR . '/classes/requests/helpers/class-walley-checkout-requests-fees-helper.php';

			// Set variables for shorthand access to classes.
			$this->order_items = new Collector_Checkout_Requests_Helper_Order();
			$this->order_fees  = new Collector_Checkout_Requests_Helper_Order_Fees();

			// Definitions.
			define( 'COLLECTOR_BANK_REST_LIVE', 'https://checkout-api.collector.se' );
			define( 'COLLECTOR_BANK_REST_TEST', 'https://checkout-api-uat.collector.se' );
			define( 'COLLECTOR_BANK_SOAP_LIVE', 'https://ecommerce.collector.se/v3.0/InvoiceServiceV33.svc?wsdl' );
			define( 'COLLECTOR_BANK_SOAP_TEST', 'https://ecommercetest.collector.se/v3.0/InvoiceServiceV33.svc?wsdl' );

			// Translations.
			load_plugin_textdomain( 'collector-checkout-for-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_action_links' ) );

			// Set class variables.
			$this->logger = new Collector_Checkout_Logger();
		}

		/**
		 * Add payment gateway.
		 *
		 * @param array $methods The array of registered gateways.
		 **/
		public function add_collector_checkout_gateway( $methods ) {
			$methods[] = 'Collector_Checkout_Gateway';

			return $methods;
		}

		/**
		 * Add plugin page links.
		 *
		 * @param array $links The links displayed on plugin page.
		 **/
		public function add_action_links( $links ) {
			$settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=collector_checkout">Settings</a>';
			array_unshift( $links, $settings_link );
			return $links;
		}

		/**
		 * Maybe create collector database table.
		 *
		 * @return void
		 */
		public function collector_maybe_create_db_table() {
			$current_db_version = get_option( 'collector_db_version' );
			if ( $current_db_version < COLLECTOR_DB_VERSION ) {
				Collector_Checkout_DB::setup_table();
			}
		}

		/**
		 * Maybe schedule action.
		 *
		 * @return void
		 */
		public function collector_maybe_schedule_action() {
			if ( false === as_next_scheduled_action( 'collector_clean_db' ) ) {
				as_schedule_recurring_action( strtotime( 'midnight tonight' ), DAY_IN_SECONDS, 'collector_clean_db' );
			}
		}

		/**
		 * Clean database of one week old data entries.
		 *
		 * @return void
		 */
		public function collector_clean_db_callback() {
			$current_date = date( 'Y-m-d H:i:s', time() ); // phpcs:ignore
			Collector_Checkout_DB::delete_old_data_entry( $current_date );
		}
	}

	Collector_Checkout::get_instance();
}

/**
 * Main instance Collector_Checkout.
 *
 * Returns the main instance of Collector_Checkout.
 *
 * @return Collector_Checkout
 */
function CCO_WC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return Collector_Checkout::get_instance();
}

/**
 * Helper function - get available customer types.
 *
 * @return string
 */
function wc_collector_get_available_customer_types() {
	$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );

	$collector_b2c_se = ( isset( $collector_settings['collector_merchant_id_se_b2c'] ) ) ? $collector_settings['collector_merchant_id_se_b2c'] : '';
	$collector_b2b_se = ( isset( $collector_settings['collector_merchant_id_se_b2b'] ) ) ? $collector_settings['collector_merchant_id_se_b2b'] : '';
	$collector_b2c_no = ( isset( $collector_settings['collector_merchant_id_no_b2c'] ) ) ? $collector_settings['collector_merchant_id_no_b2c'] : '';
	$collector_b2b_no = ( isset( $collector_settings['collector_merchant_id_no_b2b'] ) ) ? $collector_settings['collector_merchant_id_no_b2b'] : '';
	$collector_b2c_dk = ( isset( $collector_settings['collector_merchant_id_dk_b2c'] ) ) ? $collector_settings['collector_merchant_id_dk_b2c'] : '';
	$collector_b2c_fi = ( isset( $collector_settings['collector_merchant_id_fi_b2c'] ) ) ? $collector_settings['collector_merchant_id_fi_b2c'] : '';
	$collector_b2b_fi = ( isset( $collector_settings['collector_merchant_id_fi_b2b'] ) ) ? $collector_settings['collector_merchant_id_fi_b2b'] : '';

	if ( ( 'SEK' === get_woocommerce_currency() && $collector_b2c_se && $collector_b2b_se ) || ( 'NOK' === get_woocommerce_currency() && $collector_b2c_no && $collector_b2b_no ) || ( 'EUR' === get_woocommerce_currency() && $collector_b2c_fi && $collector_b2b_fi ) ) {
		return 'collector-b2c-b2b';
	} elseif ( ( 'SEK' === get_woocommerce_currency() && $collector_b2c_se ) || ( 'NOK' === get_woocommerce_currency() && $collector_b2c_no ) || ( 'DKK' === get_woocommerce_currency() && $collector_b2c_dk ) || ( 'EUR' === get_woocommerce_currency() && $collector_b2c_fi ) ) {
		return 'collector-b2c';
	} elseif ( $collector_b2b_se || $collector_b2b_no || $collector_b2b_fi ) {
		return 'collector-b2b';
	}
}

/**
 * Helper function - get default customer type.
 *
 * @return string
 */
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

	if ( 'NOK' === get_woocommerce_currency() ) {
		if ( $collector_b2c_no && empty( $default_customer_type ) ) {
			return 'b2c';
		} elseif ( $collector_b2b_no && empty( $default_customer_type ) ) {
			return 'b2b';
		} elseif ( $collector_b2c_no && 'b2c' === $default_customer_type ) {
			return 'b2c';
		} elseif ( $collector_b2b_no && 'b2b' === $default_customer_type ) {
			return 'b2b';
		} elseif ( empty( $collector_b2c_no ) && ! empty( $collector_b2b_no ) && 'b2c' === $default_customer_type ) {
			return 'b2b';
		} else {
			return 'b2c';
		}
	}

	if ( 'SEK' === get_woocommerce_currency() ) {
		if ( $collector_b2c_se && empty( $default_customer_type ) ) {
			return 'b2c';
		} elseif ( $collector_b2b_se && empty( $default_customer_type ) ) {
			return 'b2b';
		} elseif ( $collector_b2c_se && 'b2c' === $default_customer_type ) {
			return 'b2c';
		} elseif ( $collector_b2b_se && 'b2b' === $default_customer_type ) {
			return 'b2b';
		} elseif ( empty( $collector_b2c_se ) && ! empty( $collector_b2b_se ) && 'b2c' === $default_customer_type ) {
			return 'b2b';
		} else {
			return 'b2c';
		}
	}

	if ( 'EUR' === get_woocommerce_currency() ) {
		if ( $collector_b2c_fi && empty( $default_customer_type ) ) {
			return 'b2c';
		} elseif ( $collector_b2b_fi && empty( $default_customer_type ) ) {
			return 'b2b';
		} elseif ( $collector_b2c_fi && 'b2c' === $default_customer_type ) {
			return 'b2c';
		} elseif ( $collector_b2b_fi && 'b2b' === $default_customer_type ) {
			return 'b2b';
		} elseif ( empty( $collector_b2c_fi ) && ! empty( $collector_b2b_fi ) && 'b2c' === $default_customer_type ) {
			return 'b2b';
		} else {
			return 'b2c';
		}
	}

	if ( 'DKK' === get_woocommerce_currency() ) {
		return 'b2c';
	}

}

/**
 * Helper function - get selected customer type.
 *
 * @return string
 */
function wc_collector_get_selected_customer_type() {
	$selected_customer_type = false;
	if ( isset( WC()->session ) && method_exists( WC()->session, 'get' ) ) {
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
 * @param string $payment_method Collectors naming of the payment type.
 *
 * @return string
 */
function wc_collector_get_payment_method_name( $payment_method ) {
	switch ( $payment_method ) {

		case 'Direct Invoice':
		case 'DirectInvoice':
			$payment_method = __( 'Walley Invoice', 'collector-checkout-for-woocommerce' );
			break;
		case 'Account':
			$payment_method = __( 'Walley Account', 'collector-checkout-for-woocommerce' );
			break;
		case 'Part Payment':
		case 'PartPayment':
			$payment_method = __( 'Walley Part Payment', 'collector-checkout-for-woocommerce' );
			break;
		case 'Campaign':
			$payment_method = __( 'Walley Campaign', 'collector-checkout-for-woocommerce' );
			break;
		case 'Card':
			$payment_method = __( 'Walley Card', 'collector-checkout-for-woocommerce' );
			break;
		case 'Bank Transfer':
		case 'BankTransfer':
			$payment_method = __( 'Walley Bank Transfer', 'collector-checkout-for-woocommerce' );
			break;
		default:
			break;
	}

	return $payment_method;
}
