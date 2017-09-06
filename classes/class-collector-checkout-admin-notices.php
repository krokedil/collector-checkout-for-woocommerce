<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Returns error messages depending on
 *
 * @class    Collector_Checkout_Admin_Notices
 * @version  1.0
 * @package  Collector_Checkout/Classes
 * @category Class
 * @author   Krokedil
 */
class Collector_Checkout_Admin_Notices {

	/**
	 * Collector_Checkout_Admin_Notices constructor.
	 */
	public function __construct() {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$this->enabled           = $collector_settings['enabled'];
		add_action( 'admin_init', array( $this, 'check_settings' ) );
	}

	public function check_settings() {
		if ( ! empty( $_POST ) ) {
			add_action( 'woocommerce_settings_saved', array( $this, 'check_terms' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'check_terms' ) );
		}
	}

	/**
	 * Check if terms page is set
	 */
	public function check_terms() {
		if ( 'yes' != $this->enabled ) {
			return;
		}
		// Terms page
		if ( ! wc_get_page_id( 'terms' ) || wc_get_page_id( 'terms' ) < 0 ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . __( 'You need to specify a terms page in WooCommerce Settings to be able to use Collector.', 'collector-checkout-for-woocommerce' ) . '</p>';
			echo '</div>';
		}
	}
}

$collector_checkout_admin_notices = new Collector_Checkout_Admin_Notices;
