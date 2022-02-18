<?php
/**
 * Calculates Auth.
 *
 * @class    Collector_Checkout_Requests_Calculate_Auth
 * @package  Collector/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper auth class for Walley Checkout.
 */
class Collector_Checkout_Requests_Calculate_Auth {

	/**
	 * Walley Checkout username.
	 *
	 * @var string
	 */
	public $username = '';

	/**
	 * Walley Checkout shared key.
	 *
	 * @var string
	 */
	public $shared_key = '';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$this->username     = $collector_settings['collector_username'];
		$this->shared_key   = $collector_settings['collector_shared_key'];
	}

	/**
	 * Calculates the basic auth.
	 *
	 * @param array  $body The request body.
	 * @param string $path The endpoint.
	 *
	 * @return string
	 */
	public function calculate_auth( $body, $path ) {
		return 'SharedKey ' . base64_encode( $this->username . ':' . hash( 'sha256', $body . $path . $this->shared_key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Base64 used to calculate auth header.
	}
}
