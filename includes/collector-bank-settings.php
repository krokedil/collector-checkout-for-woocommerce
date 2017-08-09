<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Settings for Collector Bank
 */

return apply_filters( 'collector_bank_settings',
	array(
		'enabled' => array(
			'title'   => __( 'Enable/Disable', 'collector-bank-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable Collector Bank', 'collector-bank-for-woocommerce' ),
			'default' => 'no',
		),
		'title'   => array(
			'title'         => __( 'Title', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'This is the title that the user sees on the checkout page for Collector Bank.', 'collector-bank-for-woocommerce' ),
			'default'       => __( 'Collector Bank', 'collector-bank-for-woocommerce' ),
		),
		'description' => array(
			'title'         => __( 'Description', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'This controls the description which the user sees during checkout.', 'krokedil-ecster-pay-for-woocommerce' ),
			'default'       => __( 'Pay using Collector Bank.', 'collector-bank-for-woocommerce' ),
			'desc_tip'      => true,
		),
		'terms_page' => array(
			'title'         => __( 'Terms page URL', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'The link to the terms page for the shop.', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_shared_key'     => array(
			'title'         => __( 'Shared Key', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Bank Shared Key', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_merchant_id_se'     => array(
			'title'         => __( 'Sweden Merchant ID', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Bank Merchant ID for Sweden', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_merchant_id_no'     => array(
			'title'         => __( 'Norway Merchant ID', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Bank Merchant ID for Norway', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_username'  => array(
			'title'         => __( 'Username', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Bank Username', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_password'     => array(
			'title'         => __( 'Password', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Bank Password', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_invoice_fee' => array(
			'title'         => __( 'Invoice fee ID', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter the ID of the invoice fee', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'invoice_status_token' => array(
			'title'         => __( 'Anti-fraud token', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter the token for Invoice status callbacks from Collector. The token is a string you choose/set yourself (select any combination of letters and numbers). This token should then also be sent to Collector.', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'test_mode'         => array(
			'title'         => __( 'Test mode', 'collector-bank-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Enable Test mode for Collector Bank.', 'collector-bank-for-woocommerce' ),
			'default'       => 'no',
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
		'debug_mode'         => array(
			'title'         => __( 'Debug', 'collector-bank-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Enable Debug mode for Collector Bank', 'collector-bank-for-woocommerce' ),
			'default'       => 'no',
		),
	)
);
