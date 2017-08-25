<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Settings for Collector Checkout
 */

return apply_filters( 'collector_bank_settings',
	array(
		'enabled' => array(
			'title'   => __( 'Enable/Disable', 'collector-bank-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable Collector Checkout', 'collector-bank-for-woocommerce' ),
			'default' => 'no',
		),
		'title'   => array(
			'title'         => __( 'Title', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'This is the title that the user sees on the checkout page for Collector Checkout.', 'collector-bank-for-woocommerce' ),
			'default'       => __( 'Collector Checkout', 'collector-bank-for-woocommerce' ),
		),
		'description' => array(
			'title'         => __( 'Description', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'This controls the description which the user sees during checkout.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'       => __( 'Pay using Collector Checkout.', 'collector-bank-for-woocommerce' ),
			'desc_tip'      => true,
		),
		'collector_username'  => array(
			'title'         => __( 'Username', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Username', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_password'     => array(
			'title'         => __( 'Password', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Password', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_shared_key'     => array(
			'title'         => __( 'Shared Key', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Shared Key', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'se_settings_title' => array(
			'title'			=> __( 'Sweden', 'collector-bank-for-woocommerce' ),
			'type'  		=> 'title',
		),
		'collector_merchant_id_se_b2c'     => array(
			'title'         => __( 'Merchant ID Sweden B2C', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Merchant ID for B2C purchases in Sweden', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_merchant_id_se_b2b'     => array(
			'title'         => __( 'Merchant ID Sweden B2B', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Merchant ID for B2B purchases in Sweden', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'no_settings_title' => array(
			'title' => __( 'Norway', 'collector-bank-for-woocommerce' ),
			'type'  => 'title',
		),
		'collector_merchant_id_no_b2c'     => array(
			'title'         => __( 'Merchant ID Norway B2C', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Merchant ID for B2C purchases in Norway', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'checkout_settings_title' => array(
			'title' => __( 'Checkout settings', 'collector-bank-for-woocommerce' ),
			'type'  => 'title',
		),
		'collector_invoice_fee' => array(
			'title'         => __( 'Invoice fee ID', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter the ID of the invoice fee', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_instant_checkout' => array(
			'title'         => __( 'Instant buy', 'collector-bank-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Enable Instant buy feature for Collector Checkout', 'collector-bank-for-woocommerce' ),
			'default'       => 'no',
		),
		'order_management_settings_title' => array(
			'title' => __( 'Order management settings', 'collector-bank-for-woocommerce' ),
			'type'  => 'title',
		),
		'manage_collector_orders' => array(
			'title'         => __( 'Manage orders', 'collector-bank-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Enable WooCommerce to manage orders in Collectors backend (when order status changes to Cancelled and Completed in WooCommerce).', 'collector-bank-for-woocommerce' ),
			'default'       => 'yes',
		),
		'display_invoice_no' => array(
			'title'         => __( 'Invoice number on order page', 'collector-bank-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Display Collector Invoice Number after WooCommerce Order Number on WooCommerce order page (-> WooCommerce -> Orders).', 'collector-bank-for-woocommerce' ),
			'default'       => 'yes',
		),
		'test_mode_settings_title' => array(
			'title' => __( 'Test Mode Settings', 'collector-bank-for-woocommerce' ),
			'type'  => 'title',
		),
		'test_mode'         => array(
			'title'         => __( 'Test mode', 'collector-bank-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Enable Test mode for Collector Checkout.', 'collector-bank-for-woocommerce' ),
			'default'       => 'no',
		),
		'debug_mode'       	=> array(
			'title'         => __( 'Debug', 'collector-bank-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'       	=> __( 'Enable logging.', 'collector-bank-for-woocommerce' ),
			'description' 	=> sprintf( __( 'Log Collector events, in <code>%s</code>', 'collector-bank-for-woocommerce' ), wc_get_log_file_path( 'collector_bank' ) ),
			'default'       => 'no',
		),
	)
);
