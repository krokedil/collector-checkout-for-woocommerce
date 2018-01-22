<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Settings for Collector Checkout
 */

return apply_filters( 'collector_checkout_settings',
	array(
		'enabled' => array(
			'title'   => __( 'Enable/Disable', 'collector-checkout-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable Collector Checkout', 'collector-checkout-for-woocommerce' ),
			'default' => 'no',
		),
		'title'   => array(
			'title'         => __( 'Title', 'collector-checkout-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'This is the title that the user sees on the checkout page for Collector Checkout.', 'collector-checkout-for-woocommerce' ),
			'default'       => __( 'Collector Checkout', 'collector-checkout-for-woocommerce' ),
		),
		/*
		'description' => array(
			'title'         => __( 'Description', 'collector-checkout-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'This controls the description which the user sees during checkout.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'       => __( 'Pay using Collector Checkout.', 'collector-checkout-for-woocommerce' ),
			'desc_tip'      => true,
		),
		*/
		'collector_username'  => array(
			'title'         => __( 'Username', 'collector-checkout-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Username', 'collector-checkout-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_password'     => array(
			'title'         => __( 'Password', 'collector-checkout-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Password', 'collector-checkout-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_shared_key'     => array(
			'title'         => __( 'Shared Key', 'collector-checkout-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Shared Key', 'collector-checkout-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'se_settings_title' => array(
			'title'			=> __( 'Sweden', 'collector-checkout-for-woocommerce' ),
			'type'  		=> 'title',
		),
		'collector_merchant_id_se_b2c'     => array(
			'title'         => __( 'Merchant ID Sweden B2C', 'collector-checkout-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Merchant ID for B2C purchases in Sweden', 'collector-checkout-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_merchant_id_se_b2b'     => array(
			'title'         => __( 'Merchant ID Sweden B2B', 'collector-checkout-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Merchant ID for B2B purchases in Sweden', 'collector-checkout-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'no_settings_title' => array(
			'title' => __( 'Norway', 'collector-checkout-for-woocommerce' ),
			'type'  => 'title',
		),
		'collector_merchant_id_no_b2c'     => array(
			'title'         => __( 'Merchant ID Norway B2C', 'collector-checkout-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Checkout Merchant ID for B2C purchases in Norway', 'collector-checkout-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'checkout_settings_title' => array(
			'title' => __( 'Checkout settings', 'collector-checkout-for-woocommerce' ),
			'type'  => 'title',
		),
		'collector_invoice_fee' => array(
			'title'         => __( 'Invoice fee ID', 'collector-checkout-for-woocommerce' ),
			'type'          => 'text',
			'description'   => sprintf( __( 'Create a hidden (simple) product that acts as the invoice fee. Enter the product <strong>ID</strong> number in this textfield. Leave blank to disable. <a href="%s" target="_blank">Read more</a>.', 'collector-checkout-for-woocommerce' ), 'http://docs.krokedil.com/documentation/collector-checkout-for-woocommerce/#8' ),
			'default'       => '',
			'desc_tip'      => false,
		),
		'collector_default_customer'   	=> array(
			'title'         => __( 'Default customer', 'collector-checkout-for-woocommerce' ),
			'type'          => 'select',
			'description' 	=> __( 'Sets the default customer/checkout type for Collector Checkout (if offering both B2B & B2C)', 'collector-checkout-for-woocommerce' ),
			'options' => array(
				'b2c'   => __( 'B2C', 'collector-checkout-for-woocommerce' ),
				'b2b'   => __( 'B2B', 'collector-checkout-for-woocommerce' ),
			),
			'default'       => 'b2c',
		),
		'instnt_buy_settings_title' => array(
			'title' => __( 'Instant Buy settings', 'collector-checkout-for-woocommerce' ),
			'type'  => 'title',
		),
		'collector_instant_checkout' => array(
			'title'         => __( 'Instant Buy', 'collector-checkout-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Enable Instant Buy feature on single product pages', 'collector-checkout-for-woocommerce' ),
			'default'       => 'no',
			'desc_tip'    	=> true
		),
		'button_color'             => array(
			'title'       	=> __( 'Button color', 'collector-checkout-for-woocommerce' ),
			'type'        	=> 'color',
			'description' 	=> __( 'Instant Buy button color. Leave blank to use your theme <em>Add to cart</em> button color.', 'collector-checkout-for-woocommerce' ),
			'default'     	=> '',
			'desc_tip'    	=> true
		),
		'button_color_text'        => array(
			'title'       	=> __( 'Button text color', 'collector-checkout-for-woocommerce' ),
			'type'        	=> 'color',
			'description' 	=> __( 'Instant Buy button text color', 'collector-checkout-for-woocommerce' ),
			'default'     	=> '',
			'desc_tip'    	=> true
		),
		'order_management_settings_title' => array(
			'title' 		=> __( 'Order management settings', 'collector-checkout-for-woocommerce' ),
			'type'  		=> 'title',
		),
		'manage_collector_orders' => array(
			'title'         => __( 'Manage orders', 'collector-checkout-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Enable WooCommerce to manage orders in Collectors backend (when order status changes to Cancelled and Completed in WooCommerce).', 'collector-checkout-for-woocommerce' ),
			'default'       => 'yes',
		),
		'display_invoice_no' => array(
			'title'         => __( 'Invoice number on order page', 'collector-checkout-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Display Collector Invoice Number after WooCommerce Order Number on WooCommerce order page (-> WooCommerce -> Orders).', 'collector-checkout-for-woocommerce' ),
			'default'       => 'yes',
		),
		'test_mode_settings_title' => array(
			'title' => __( 'Test Mode Settings', 'collector-checkout-for-woocommerce' ),
			'type'  => 'title',
		),
		'test_mode'         => array(
			'title'         => __( 'Test mode', 'collector-checkout-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Enable Test mode for Collector Checkout.', 'collector-checkout-for-woocommerce' ),
			'default'       => 'no',
		),
		'debug_mode'       	=> array(
			'title'         => __( 'Debug', 'collector-checkout-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'       	=> __( 'Enable logging.', 'collector-checkout-for-woocommerce' ),
			'description' 	=> sprintf( __( 'Log Collector events, in <code>%s</code>', 'collector-checkout-for-woocommerce' ), wc_get_log_file_path( 'collector_checkout' ) ),
			'default'       => 'no',
		),
	)
);
