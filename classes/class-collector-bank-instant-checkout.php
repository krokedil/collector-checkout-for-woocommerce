<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Bank_Instant_Checkout {
	public function __construct() {
		// Add Instant checkout button for Collector
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'collector_instant_checkout' ), 20 );
	}

	public function collector_instant_checkout() {

		echo '<br><br><script src="https://checkout-uat.collector.se/collector-instant-loader.js" data-lang="sv-SE"></script>';
	}
}
new Collector_Bank_Instant_Checkout();
