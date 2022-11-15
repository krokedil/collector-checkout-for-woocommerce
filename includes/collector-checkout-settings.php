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
	'collector_password'              => array(
		'title'       => __( 'Password', 'collector-checkout-for-woocommerce' ),
		'type'        => 'text',
		'description' => __( 'Enter your Walley Checkout Password', 'collector-checkout-for-woocommerce' ),
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
		'title'   => __( 'Shipping Module Sweden', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Activate Shipping Module for Sweden', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
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
		'title'   => __( 'Shipping Module Norway', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Activate Shipping Module for Norway', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
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
		'title'   => __( 'Shipping Module Finland', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Activate Shipping Module for Finland', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
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
	'collector_delivery_module_dk'    => array(
		'title'   => __( 'Shipping Module Denmark', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Activate Shipping Module for Denmark', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'checkout_settings_title'         => array(
		'title' => __( 'Checkout settings', 'collector-checkout-for-woocommerce' ),
		'type'  => 'title',
	),
	'checkout_version'                => array(
		'title'       => __( 'Checkout version', 'collector-checkout-for-woocommerce' ),
		'type'        => 'select',
		'description' => __( 'A brand new Checkout is available, project name Checkout 2.0. The current solution (referred as Checkout 1.0) will be deprecated during 2022.', 'collector-checkout-for-woocommerce' ),
		'options'     => array(
			'v1' => __( 'Checkout 1.0', 'collector-checkout-for-woocommerce' ),
			'v2' => __( 'Checkout 2.0', 'collector-checkout-for-woocommerce' ),
		),
		'default'     => 'v1',
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
	'activate_validation_callback'    => array(
		'title'       => __( 'Validation Callback', 'collector-checkout-for-woocommerce' ),
		'type'        => 'checkbox',
		'label'       => __( 'Tick the checkbox to activate Walley Validation Callback.', 'collector-checkout-for-woocommerce' ),
		/* Translators: link to docs */
		'description' => sprintf( __( 'Triggered by Collector when customer clicks the Complete purchase button in Walley Checkout. <a href="%s" target="_blank">Read more about validation callback.</a>', 'collector-checkout-for-woocommerce' ), 'https://docs.krokedil.com/walley-checkout-for-woocommerce/get-started/introduction/' ),
		'default'     => 'yes',
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
	'activate_individual_order_lines' => array(
		'title'   => __( 'Activate individual order lines', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'If checked, each order line will be activated instead of the entire order.', 'collector-checkout-for-woocommerce' ),
		'default' => 'no',
	),
	'display_invoice_no'              => array(
		'title'   => __( 'Invoice number on order page', 'collector-checkout-for-woocommerce' ),
		'type'    => 'checkbox',
		'label'   => __( 'Display Walley Invoice Number after WooCommerce Order Number on WooCommerce order page (-> WooCommerce -> Orders).', 'collector-checkout-for-woocommerce' ),
		'default' => 'yes',
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
		'title'       => __( 'Debug', 'collector-checkout-for-woocommerce' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable logging.', 'collector-checkout-for-woocommerce' ),
		/* Translators: link to logs */
		'description' => sprintf( __( 'Log Walley events, in <code>%s</code>', 'collector-checkout-for-woocommerce' ), wc_get_log_file_path( 'collector_checkout' ) ),
		'default'     => 'no',
	),
);

$wc_version = defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;

if ( version_compare( $wc_version, '3.4', '>=' ) ) {
	$new_settings = array();
	foreach ( $settings as $key => $value ) {
		$new_settings[ $key ] = $value;
		if ( 'collector_shared_key' === $key ) {
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
