<?php
/**
 * Settings file for the plugin.
 *
 * @package  Collector_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings for Collector Checkout
 */
$settings = array(
	'enabled'                         => array(
		'title'   => __( 'Enable/Disable', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Walley Checkout (Collector)', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'title'                           => array(
		'title'       => __( 'Title', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'This is the title that the user sees on the checkout page for Walley Checkout (Collector).', 'collector-checkout-for-woocommerce' ),
		'default'     => __( 'Walley Checkout', 'collector-checkout-for-woocommerce' ),
	),
	'collector_username'              => array(
		'title'       => __( 'Username', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Username', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'collector_shared_key'            => array(
		'title'       => __( 'Shared Key', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Shared Key', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'walley_api_client_id'            => array(
		'title'       => __( 'API Client ID', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout API Client ID. Used for Walley\'s new Checkout and Management API. If entered this API will be used instead of the old API.', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => false,
	),
	'walley_api_secret'               => array(
		'title'       => __( 'API Secret', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout API Secret. Used for Walley\'s new Checkout and Management API. If entered this API will be used instead of the old API.', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => false,
	),
	'collector_password'              => array(
		'title'       => __( 'Password', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Password. Used for Walley\'s old SOAP based Payments API (for order management).', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => false,
	),
	'se_settings_title'               => array(
		'title' => __( 'Sweden', 'collector-checkout-for-woocommerce' ),
		'type'  => 'title',
	),
	'collector_merchant_id_se_b2c'    => array(
		'title'       => __( 'Merchant ID Sweden B2C', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Merchant ID for B2C purchases in Sweden', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'collector_merchant_id_se_b2b'    => array(
		'title'       => __( 'Merchant ID Sweden B2B', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Merchant ID for B2B purchases in Sweden', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'collector_delivery_module_se'    => array(
		'title'   => __( 'Walley nShift Delivery', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Activate Walley nShift Delivery Sweden', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'collector_custom_profile_se'     => array(
		'title'       => __( 'Custom Profile Sweden', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( '', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
	),
	'no_settings_title'               => array(
		'title' => __( 'Norway', 'collector-checkout-for-woocommerce' ),
		'type'  => 'title',
	),
	'collector_merchant_id_no_b2c'    => array(
		'title'       => __( 'Merchant ID Norway B2C', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Merchant ID for B2C purchases in Norway', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'collector_merchant_id_no_b2b'    => array(
		'title'       => __( 'Merchant ID Norway B2B', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Merchant ID for B2B purchases in Norway', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'collector_delivery_module_no'    => array(
		'title'   => __( 'Walley nShift Delivery', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Activate Walley nShift Delivery Norway', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'collector_custom_profile_no'     => array(
		'title'       => __( 'Custom Profile Norway', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( '', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
	),
	'fi_settings_title'               => array(
		'title' => __( 'Finland', 'collector-checkout-for-woocommerce' ),
		'type'  => 'title',
	),
	'collector_merchant_id_fi_b2c'    => array(
		'title'       => __( 'Merchant ID Finland B2C', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Merchant ID for B2C purchases in Finland', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'collector_merchant_id_fi_b2b'    => array(
		'title'       => __( 'Merchant ID Finland B2B', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Merchant ID for B2B purchases in Finland', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'collector_delivery_module_fi'    => array(
		'title'   => __( 'Walley nShift Delivery', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Activate Walley nShift Delivery Finland', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'collector_custom_profile_fi'     => array(
		'title'       => __( 'Custom Profile Finland', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( '', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
	),
	'dk_settings_title'               => array(
		'title' => __( 'Denmark', 'collector-checkout-for-woocommerce' ),
		'type'  => 'title',
	),
	'collector_merchant_id_dk_b2c'    => array(
		'title'       => __( 'Merchant ID Denmark B2C', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Merchant ID for B2C purchases in Denmark', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'collector_merchant_id_dk_b2b'    => array(
		'title'       => __( 'Merchant ID Denmark B2B', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Merchant ID for B2B purchases in Denmark', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'collector_delivery_module_dk'    => array(
		'title'   => __( 'Walley nShift Delivery', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Activate Walley nShift Delivery Denmark', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'collector_custom_profile_dk'     => array(
		'title'       => __( 'Custom Profile Denmark', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( '', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
	),
	'checkout_settings_title'         => array(
		'title' => __( 'Checkout settings', 'collector-checkout-for-woocommerce' ),
		'type'  => 'title',
	),
	'checkout_layout'                 => array(
		'title'       => __( 'Checkout layout', 'collector-checkout-for-woocommerce' ),
		'type'        => 'select',
		'options'     => array(
			'one_column_checkout' => __( 'One column checkout', 'collector-checkout-for-woocommerce' ),
			'two_column_right'    => __( 'Two column checkout (Walley Checkout in right column)', 'collector-checkout-for-woocommerce' ),
			'two_column_left'     => __( 'Two column checkout (Walley Checkout in left column)', 'collector-checkout-for-woocommerce' ),
			'two_column_left_sf'  => __( 'Two column checkout (Walley Checkout in left column) - Storefront light', 'collector-checkout-for-woocommerce' ),
		),
		'description' => __( 'Select the Checkout layout.', 'collector-checkout-for-woocommerce' ),
		'default'     => 'one_column_checkout',
		'desc_tip'    => false,
	),
	'collector_invoice_fee'           => array(
		'title'       => __( 'Invoice fee ID', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		/* Translators: link to docs */
		'description' => sprintf( __( 'Create a hidden (simple) product that acts as the invoice fee. Enter the product <strong>ID</strong> number in this textfield. Leave blank to disable. <a href="%s" target="_blank">Read more</a>.', 'collector-checkout-for-woocommerce' ), 'https://docs.krokedil.com/walley-checkout-for-woocommerce/' ),
		'default'     => '',
		'desc_tip'    => false,
	),
	'collector_default_customer'      => array(
		'title'       => __( 'Default customer', 'collector-checkout-for-woocommerce' ),
		'type'        => 'select',
		'description' => __( 'Sets the default customer/checkout type for Walley Checkout (if offering both B2B & B2C)', 'collector-checkout-for-woocommerce' ),
		'options'     => array(
			'b2c' => __( 'B2C', 'collector-checkout-for-woocommerce' ),
			'b2b' => __( 'B2B', 'collector-checkout-for-woocommerce' ),
		),
		'default'     => 'b2c',
	),
	'checkout_button_color'           => array(
		'title'       => __( 'Checkout button color', 'collector-checkout-for-woocommerce' ),
		'type'        => 'color',
		'description' => __( 'Background color of call to action buttons in Walley Checkout.', 'collector-checkout-for-woocommerce' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'requires_electronic_id_fields'   => array(
		'title'       => __( 'Electronic ID fields', 'collector-checkout-for-woocommerce' ),
		'type'        => 'checkbox',
		'label'       => __( 'Tick the checkbox to activate Requires Electronic ID Fields settings in product pages.', 'collector-checkout-for-woocommerce' ),
		/* Translators: link to docs */
		'description' => sprintf( __( '<a href="%s" target="_blank">Read more about this.</a>', 'collector-checkout-for-woocommerce' ), 'https://docs.krokedil.com/walley-checkout-for-woocommerce/get-started/introduction/' ),
		'default'     => 'no',
	),
	'order_management_settings_title' => array(
		'title' => __( 'Order management settings', 'collector-checkout-for-woocommerce' ),
		'type'  => 'title',
	),
	'manage_collector_orders'         => array(
		'title'   => __( 'Manage orders', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable WooCommerce to manage orders in Walley backend (when order status changes to Cancelled and Completed in WooCommerce).', 'collector-checkout-for-woocommerce' ),
		'default' => 'yes',
	),
	'display_invoice_no'              => array(
		'title'   => __( 'Invoice number on order page', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Display Walley Invoice Number after WooCommerce Order Number on WooCommerce order page (-> WooCommerce -> Orders).', 'collector-checkout-for-woocommerce' ),
		'default' => 'yes',
	),
	'activate_individual_order_lines' => array(
		'title'   => __( 'Activate individual order lines', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'If checked, each order line will be activated instead of the entire order.', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'add_rounding_order_line'         => array(
		'title'   => __( 'Activate rounding order line', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'If checked, an extra rounding order line will be sent to Walley if the sum of all order lines does not match WooCommerce cart/order total.', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'test_mode_settings_title'        => array(
		'title' => __( 'Test Mode Settings', 'collector-checkout-for-woocommerce' ),
		'type'  => 'title',
	),
	'test_mode'                       => array(
		'title'   => __( 'Test mode', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Test mode for Walley Checkout.', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'debug_mode'                      => array(
		'title'   => __( 'Debug', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable logging.', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
);

$wc_version = defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;

if ( version_compare( $wc_version, '3.4', '>=' ) ) {
	$new_settings = array();
	foreach ( $settings as $key => $value ) {
		$new_settings[ $key ] = $value;
		if ( 'collector_password' === $key ) {
			$new_settings['display_privacy_policy_text'] = array(
				'title'   => __( 'Display checkout privacy policy text', 'collector-checkout-for-woocommerce' ),
				'label'   => __( 'Select if you want to show the <em>Checkout privacy policy</em> text on the checkout page, and where you want to display it.', 'collector-checkout-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'no',
				'options' => array(
					'no'    => __( 'Do not display', 'collector-checkout-for-woocommerce' ),
					'above' => __( 'Display above checkout', 'collector-checkout-for-woocommerce' ),
					'below' => __( 'Display below checkout', 'collector-checkout-for-woocommerce' ),
				),
			);
		}
	}
	$settings = $new_settings;
}

return apply_filters( 'collector_checkout_settings', $settings );
