<?php
/**
 * Main request class
 *
 * @package Collector_Checkout/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all request classes.
 */
abstract class Walley_Checkout_Request {

	/**
	 * The request method.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * The request title.
	 *
	 * @var string
	 */
	protected $log_title;

	/**
	 * The Walley order id.
	 *
	 * @var string
	 */
	protected $walley_order_id;

	/**
	 * The request arguments.
	 *
	 * @var array
	 */
	protected $arguments;

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	protected $settings;


	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request args.
	 */
	public function __construct( $arguments = array() ) {
		$this->arguments = $arguments;
		$this->load_settings();
	}

	/**
	 * Loads the Walley Checkout settings and sets them to be used here.
	 *
	 * @return void
	 */
	protected function load_settings() {
		$this->settings = get_option( 'woocommerce_collector_checkout_settings', array() );
	}

	/**
	 * Get the API base URL.
	 *
	 * @return string
	 */
	protected function get_api_url_base() {
		if ( 'yes' === $this->settings['test_mode'] ) {
			return 'https://api.uat.walleydev.com';
		}

		return 'https://api.walleypay.com';
	}

	/**
	 * Get the request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'Content-type'  => 'application/json',
			'Authorization' => $this->get_access_token(),
		);
	}

	/**
	 * Get the access token from Walley.
	 *
	 * @return string
	 */
	private function get_access_token() {
		$access_token = get_transient( 'walley_checkout_access_token' );
		if ( $access_token ) {
			return $access_token;
		}

		$response = CCO_WC()->api->get_access_token();

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$access_token = $response['token_type'] . ' ' . $response['access_token'];
		set_transient( 'walley_checkout_access_token', $access_token, absint( $response['expires_in'] ) );
		return $access_token;
	}

	/**
	 * Get the user agent.
	 *
	 * @return string
	 */
	protected function get_user_agent() {
		return apply_filters(
			'http_headers_useragent',
			'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
		) . ' - WooCommerce: ' . WC()->version . ' - Walley Checkout: ' . COLLECTOR_BANK_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil';
	}

	/**
	 * Get the request args.
	 *
	 * @return array
	 */
	abstract protected function get_request_args();

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	abstract protected function get_request_url();

	/**
	 * Make the request.
	 *
	 * @return array|WP_Error
	 */
	public function request() {
		$url      = $this->get_request_url();
		$args     = $this->get_request_args();
		$response = wp_remote_request( $url, $args );
		return $this->process_response( $response, $args, $url );
	}

	/**
	 * Processes the response checking for errors.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request url.
	 * @return array|WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			// Get the error messages.
			if ( null !== json_decode( $response['body'], true ) ) {
				$errors = json_decode( $response['body'], true );

				foreach ( $errors as $error ) {
					$error_message .= ' ' . wp_json_encode( $error );
				}
			}

			$error_message = empty( $error_message ) ? "API Error ${response_code}" : "API Error ${error_message}";
			$return        = new WP_Error( $response_code, $error_message, $data );
		} else {
			$return = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		$this->log_response( $response, $request_args, $request_url );
		return $return;
	}

	/**
	 * Logs the response from the request.
	 *
	 * @param array|WP_Error $response The response from the request.
	 * @param array          $request_args The request args.
	 * @param string         $request_url The request URL.
	 *
	 * @return void
	 */
	protected function log_response( $response, $request_args, $request_url ) {
		$method = $this->method;
		$title  = $this->log_title;
		$code   = wp_remote_retrieve_response_code( $response );

		$body     = json_decode( $response['body'], true );
		$order_id = $this->private_id ?? $this->order_id ?? null;
		$log      = Collector_Checkout_Logger::format_log( $order_id, $method, $title, $request_args, $request_url, $response, $code );
		Collector_Checkout_Logger::log( $log );
	}

	/**
	 * Get the api secret.
	 *
	 * @return string
	 */
	protected function get_client_id() {
		return $this->settings['walley_api_client_id'];
	}

	/**
	 * Get the api key.
	 *
	 * @return string
	 */
	protected function get_api_secret() {
		return $this->settings['walley_api_secret'];
	}

	/**
	 * Get the scope.
	 *
	 * @return string
	 */
	protected function get_scope() {
		if ( 'yes' === $this->settings['test_mode'] ) {
			return '705798e0-8cef-427c-ae00-6023deba29af/.default';
		}
		return 'a3f3019f-2be9-41cc-a254-7bb347238e89/.default';
	}

	/**
	 * Set environment variables from settings depending on customer type and currency.
	 *
	 * @param array $arguments The current customer args.
	 *
	 * @return void
	 */
	public function set_environment_variables( $arguments = array() ) {
		$this->customer_type = $arguments['customer_type'] ?? 'b2c';
		$this->currency      = $arguments['currency'] ?? get_woocommerce_currency();
		switch ( $this->currency ) {
			case 'SEK':
				$country_code          = 'SE';
				$this->store_id        = $this->settings[ 'collector_merchant_id_se_' . $this->customer_type ];
				$this->delivery_module = isset( $this->settings['collector_delivery_module_se'] ) ? $this->settings['collector_delivery_module_se'] : 'no';
				break;
			case 'NOK':
				$country_code          = 'NO';
				$this->store_id        = $this->settings[ 'collector_merchant_id_no_' . $this->customer_type ];
				$this->delivery_module = isset( $this->settings['collector_delivery_module_no'] ) ? $this->settings['collector_delivery_module_no'] : 'no';
				break;
			case 'DKK':
				$country_code          = 'DK';
				$this->store_id        = $this->settings[ 'collector_merchant_id_dk_' . $this->customer_type ];
				$this->delivery_module = isset( $this->settings['collector_delivery_module_dk'] ) ? $this->settings['collector_delivery_module_dk'] : 'no';
				break;
			case 'EUR':
				$country_code          = 'FI';
				$this->store_id        = $this->settings[ 'collector_merchant_id_fi_' . $this->customer_type ];
				$this->delivery_module = isset( $this->settings['collector_delivery_module_fi'] ) ? $this->settings['collector_delivery_module_fi'] : 'no';
				break;
			default:
				$country_code          = 'SE';
				$this->store_id        = $this->settings[ 'collector_merchant_id_se_' . $this->customer_type ];
				$this->delivery_module = isset( $this->settings['collector_delivery_module_se'] ) ? $this->settings['collector_delivery_module_se'] : 'no';
				break;
		}
		$this->country_code = $country_code;
		$this->terms_page   = esc_url( get_permalink( wc_get_page_id( 'terms' ) ) );
	}

	/**
	 * Gets the WC cart.
	 *
	 * @return array
	 */
	protected function cart() {
		$collector_checkout_requests_cart = new Collector_Checkout_Requests_Cart();
		return $collector_checkout_requests_cart->cart();
	}
}
