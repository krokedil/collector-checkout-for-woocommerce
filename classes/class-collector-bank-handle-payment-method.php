<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Handle_Payment_Method {

	public $collector_settings = '';

	public function __construct() {
		$this->collector_settings = get_option( 'woocommerce_collector_bank_settings' );
	}

	public function handle_payment_method( $payment_method ) {
		switch ( $payment_method ) {
			case 'Direct Invoice':
				$this->direct_invoice();
				break;
			case 'Account':
				break;
			case 'Card':
				break;
			case 'Bank Transfer':
				break;
			case 'PartPayment':
				break;
			case 'Campaign':
				break;
		}
	}

	public function direct_invoice() {
		$product_id = $this->collector_settings['collector_invoice_fee'];
		WC()->cart->add_to_cart( $product_id );
	}
}
