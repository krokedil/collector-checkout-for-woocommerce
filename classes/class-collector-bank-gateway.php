<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
class Collector_Bank_Gateway extends WC_Payment_Gateway {
	private $public_token = '';
	public function __construct() {
		$this->id                 = 'collector_bank';
		$this->method_title       = __( 'Collector Bank', 'collector-bank-for-woocommerce' );
		$this->method_description = __( 'Collector Bank payment solution for WooCommerce', 'collector-bank-for-woocommerce' );
		$this->title = $this->get_option( 'title' );
		$this->enabled = $this->get_option( 'enabled' );
		// Load the form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();
		if ( is_checkout() ) {
			//wp_enqueue_script( 'collector_script', 'https://checkout-uat.collector.se/collector-checkout-loader.js', array( 'jquery' ) );
			//add_filter( 'script_loader_src', array( $this, 'add_data_tags' ), 10, 2 );
			//add_filter( 'clean_url', array( $this, 'unclean_url' ), 10, 3 );
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}
	public function init_form_fields() {
		$this->form_fields = include( COLLECTOR_BANK_PLUGIN_DIR . '/includes/collector-bank-settings.php' );
	}

	public function add_data_tags( $src, $handle ) {
		if ( 'collector_script' != $handle ) {
			return $src;
		}
		$init_checkout = new Collector_Bank_Requests_Initialize_Checkout();
		$request = $init_checkout->request();
		$decode = json_decode( $request['body'] );
		$this->public_token = $decode->data->publicToken;
		return $src . ' data-token="' . $this->public_token . '" data-lang="sv"';
	}

	public function unclean_url( $good_protocol_url, $original_url, $_context ) {
		if ( false !== strpos( $original_url, 'data-token' ) ) {
			remove_filter( 'clean_url','unclean_url' , 10, 3 );
			$url_parts = parse_url( $good_protocol_url );
			error_log( $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'] . ' data-token="' . $this->public_token . '" data-lang="sv"' );
			return $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'] . "' data-token='" . $this->public_token . "' data-lang='sv";
		}
		return $good_protocol_url;
	}
}
