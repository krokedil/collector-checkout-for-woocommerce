<?php
/**
 * Class for the request to initialize a checkout.
 *
 * @package Collector_Checkout/Classes/Requests/POST
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walley_Checkout_Request_Initialize_Checkout class.
 */
class Walley_Checkout_Request_Initialize_Checkout extends Walley_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->log_title = 'Initialize checkout';
		$this->order_id  = $arguments['order_id'] ?? '';
		$this->set_environment_variables( $arguments );
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return $this->get_api_url_base() . '/checkouts';
	}

	/**
	 * Get the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {

		$body = array(
			'storeId'          => $this->store_id,
			'countryCode'      => $this->country_code,
			'reference'        => '',
			'merchantTermsUri' => $this->terms_page,
			'notificationUri'  => add_query_arg(
				array(
					'notification-callback' => '1',
					'private-id'            => '{checkout.id}',
					'public-token'          => '{checkout.publictoken}',
					'customer-type'         => $this->customer_type,
				),
				get_home_url() . '/wc-api/Collector_Checkout_Gateway/'
			),
			'cart'             => empty( $this->order_id ) ? $this->cart() : CCO_WC()->order_items->get_order_lines( $this->order_id ),
			'fees'             => empty( $this->order_id ) ? Walley_Checkout_Requests_Fees_Helper::fees() : CCO_WC()->order_fees->get_order_fees( $this->order_id ),
		);

		// Only send profileName if this is a purchase from the checkout.
		if ( empty( $this->order_id ) ) {
			if ( 'yes' === $this->delivery_module ) {
				$body['profileName'] = trim( $this->settings[ 'collector_custom_profile_' . strtolower( $this->country_code ) ] );
				if ( empty( $body['profileName'] ) ) {
					$body['profileName'] = 'Shipping';
				}
			}

			$body['redirectPageUri'] = add_query_arg(
				array(
					'walley_confirm' => '1',
					'public-token'   => '{checkout.publictoken}',
				),
				wc_get_checkout_url()
			);

			// Customer.
			if ( WC()->customer->get_billing_email() && WC()->customer->get_billing_phone() ) {
				$body['customer']['email']             = WC()->customer->get_billing_email();
				$body['customer']['mobilePhoneNumber'] = WC()->customer->get_billing_phone();
			}
		} else {
			$order                   = wc_get_order( $this->order_id );
			$body['redirectPageUri'] = add_query_arg(
				array(
					'collector_confirm_order_pay' => '1',
					'public-token'                => '{checkout.publictoken}',
				),
				$order->get_checkout_order_received_url()
			);

			// Customer.
			if ( $order->get_billing_email() && $order->get_billing_phone() ) {
				$body['customer']['email']             = $order->get_billing_email();
				$body['customer']['mobilePhoneNumber'] = $order->get_billing_phone();
			}
		}

		// Custom fields.
		$custom_fields = apply_filters( 'walley_custom_fields', array(), $this->order_id );
		if ( ! empty( $custom_fields ) ) {
			$body['customFields'] = $custom_fields;
		}

		return apply_filters( 'walley_initialize_checkout_args', $body, $this->order_id );
	}
}
