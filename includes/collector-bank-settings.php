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
		'collector_shared_key'     => array(
			'title'         => __( 'Shared Key', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Bank Shared Key', 'collector-bank-for-woocommerce' ),
			'default'       => '',
			'desc_tip'      => true,
		),
		'collector_merchant_id'     => array(
			'title'         => __( 'Merchant ID', 'collector-bank-for-woocommerce' ),
			'type'          => 'text',
			'description'   => __( 'Enter your Collector Bank Merchant ID', 'collector-bank-for-woocommerce' ),
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
		'test_mode'         => array(
			'title'         => __( 'Test mode', 'collector-bank-for-woocommerce' ),
			'type'          => 'checkbox',
			'label'         => __( 'Enable Test mode for Collector Bank', 'collector-bank-for-woocommerce' ),
			'default'       => 'no',
		),
	)
);
