<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Collector_Checkout_Templates class.
 */
class Collector_Checkout_Templates {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Plugin actions.
	 */
	public function __construct() {

		// Template hooks.
		add_action( 'collector_wc_before_checkout_form', 'collector_wc_calculate_totals', 1 );
	}

}

Collector_Checkout_Templates::get_instance();