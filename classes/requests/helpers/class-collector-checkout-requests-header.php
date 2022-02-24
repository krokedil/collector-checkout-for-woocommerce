<?php
/**
 * Creates Collector refund data.
 *
 * @class    Collector_Checkout_Requests_Header
 * @package  Collector/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Collector_Checkout_Requests_Header.
 */
class Collector_Checkout_Requests_Header {
	/**
	 * The auth token.
	 *
	 * @var string
	 */
	public $auth = '';

	/**
	 * Class constructor.
	 *
	 * @param array  $body The request body.
	 * @param string $path The endpoint.
	 */
	public function __construct( $body, $path ) {
		$calculate_auth = new Collector_Checkout_Requests_Calculate_Auth();
		$this->auth     = $calculate_auth->calculate_auth( $body, $path );
	}

	/**
	 * Returns header options.
	 *
	 * @return array
	 */
	public function get() {
		return array(
			'Content-Type'  => 'application/json',
			'Authorization' => $this->auth,
		);
	}
}
