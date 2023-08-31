<?php
/**
 * Walley Checkout Session class file.
 *
 * @package Walley/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Walley Checkout Session class.
 */
class Walley_Checkout_Session {

	/**
	 * Returns the full customer address from Walley session.
	 *
	 * @param array $walley_order Walley Checkout session.
	 * @return array
	 */
	public static function get_customer_address( $walley_order ) {
		$customer_address                     = array();
		$customer_address['billing_country']  = $walley_order['data']['countryCode'] ?? '';
		$customer_address['shipping_country'] = $walley_order['data']['countryCode'] ?? '';

		if ( 'BusinessCustomer' === $walley_order['data']['customerType'] ) {
			$customer_address['billing_company']    = $walley_order['data']['businessCustomer']['invoiceAddress']['companyName'] ?? '';
			$customer_address['billing_first_name'] = $walley_order['data']['businessCustomer']['firstName'] ?? '';
			$customer_address['billing_last_name']  = $walley_order['data']['businessCustomer']['lastName'] ?? '';
			$customer_address['billing_city']       = $walley_order['data']['businessCustomer']['invoiceAddress']['city'] ?? '';
			$customer_address['billing_address_1']  = $walley_order['data']['businessCustomer']['invoiceAddress']['address'] ?? '';
			$customer_address['billing_postcode']   = $walley_order['data']['businessCustomer']['invoiceAddress']['postalCode'] ?? '';

			$customer_address['shipping_company']    = $walley_order['data']['businessCustomer']['deliveryAddress']['companyName'] ?? '';
			$customer_address['shipping_first_name'] = $walley_order['data']['businessCustomer']['firstName'] ?? '';
			$customer_address['shipping_last_name']  = $walley_order['data']['businessCustomer']['lastName'] ?? '';
			$customer_address['shipping_city']       = $walley_order['data']['businessCustomer']['deliveryAddress']['city'] ?? '';
			$customer_address['shipping_address_1']  = $walley_order['data']['businessCustomer']['deliveryAddress']['address'] ?? '';
			$customer_address['shipping_postcode']   = $walley_order['data']['businessCustomer']['deliveryAddress']['postalCode'] ?? '';

			$customer_address['billing_email'] = $walley_order['data']['businessCustomer']['email'] ?? '';
			$customer_address['billing_phone'] = $walley_order['data']['businessCustomer']['mobilePhoneNumber'] ?? '';
		} else {
			$customer_address['billing_first_name'] = $walley_order['data']['customer']['billingAddress']['firstName'] ?? '';
			$customer_address['billing_last_name']  = $walley_order['data']['customer']['billingAddress']['lastName'] ?? '';
			$customer_address['billing_city']       = $walley_order['data']['customer']['billingAddress']['city'] ?? '';
			$customer_address['billing_address_1']  = $walley_order['data']['customer']['billingAddress']['address'] ?? '';
			$customer_address['billing_postcode']   = $walley_order['data']['customer']['billingAddress']['postalCode'] ?? '';

			$customer_address['shipping_first_name'] = $walley_order['data']['customer']['deliveryAddress']['firstName'] ?? '';
			$customer_address['shipping_last_name']  = $walley_order['data']['customer']['deliveryAddress']['lastName'] ?? '';
			$customer_address['shipping_city']       = $walley_order['data']['customer']['deliveryAddress']['city'] ?? '';
			$customer_address['shipping_address_1']  = $walley_order['data']['customer']['deliveryAddress']['address'] ?? '';
			$customer_address['shipping_postcode']   = $walley_order['data']['customer']['deliveryAddress']['postalCode'] ?? '';

			$customer_address['billing_email']  = $walley_order['data']['customer']['email'] ?? '';
			$customer_address['billing_phone']  = $walley_order['data']['customer']['mobilePhoneNumber'] ?? '';
			$customer_address['shipping_phone'] = $walley_order['data']['customer']['deliveryContactInformation']['mobilePhoneNumber'] ?? '';
		}

		return $customer_address;
	}
}
