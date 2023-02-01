<?php
/**
 * Class for managing actions during the checkout process.
 *
 * @package Collector_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for managing actions during the checkout process.
 */
class Walley_Checkout {
	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_shipping_method' ) );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_walley_order' ), 9999 );
	}


	/**
	 * Update the shipping method in WooCommerce based on what Walley has sent us.
	 *
	 * @return void
	 */
	public function update_shipping_method() {

		if ( ! is_checkout() ) {
			return;
		}

		if ( 'collector_checkout' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		// If Delivery module is not used for the currency/country, return.
		if ( 'yes' !== is_collector_delivery_module( get_woocommerce_currency() ) ) {
			return;
		}

		// We can only do this during AJAX, so if it is not an ajax call, we should just bail.
		if ( ! wp_doing_ajax() ) {
			return;
		}

		// Trigger get if the ajax event is among the approved ones.
		$ajax = filter_input( INPUT_GET, 'wc-ajax', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! in_array( $ajax, array( 'update_order_review' ), true ) ) {
			return;
		}

		$private_id = WC()->session->get( 'collector_private_id' );
		if ( empty( $private_id ) ) {
			return;
		}
		$customer_type = WC()->session->get( 'collector_customer_type' );
		if ( empty( $customer_type ) ) {
			return;
		}

		// Use new or old API.
		if ( walley_use_new_api() ) {
			$collector_order = CCO_WC()->api->get_walley_checkout(
				array(
					'private_id'    => $private_id,
					'customer_type' => $customer_type,
				)
			);
		} else {
			$collector_order = new Collector_Checkout_Requests_Get_Checkout_Information( $private_id, $customer_type );
			$collector_order = $collector_order->request();
		}

		if ( is_wp_error( $collector_order ) ) {
			return;
		}

		if ( isset( $collector_order['data']['shipping'] ) ) {

			/*
			@todo:
			Dont forget thios part of the ld code:
			if ( ! isset( $shipping_data['label'] ) ) {
				$shipping_data = $shipping_data[0];
			}
			*/
			$shipping_data           = coc_get_shipping_data( $collector_order );
			$chosen_shipping_methods = array( 'collector_delivery_module' );
			WC()->session->set( 'collector_delivery_module_data', $shipping_data );
			WC()->session->set( 'chosen_shipping_methods', apply_filters( 'coc_shipping_method', $chosen_shipping_methods, $shipping_data ) ); // Set chosen shipping method, with filter to allow overrides.
		} else {
			WC()->session->__unset( 'collector_delivery_module_enabled' );
			WC()->session->__unset( 'collector_delivery_module_data' );
		}
		$this->invalidate_session_shipping_cache();
	}

	/**
	 * Invalidate and recalc shipping on Woo session.
	 * This will make WC call calculate_shipping() in the shipping_method
	 */
	public function invalidate_session_shipping_cache() {
		$packages = WC()->cart->get_shipping_packages();
		foreach ( $packages as $package_key => $package ) {
			WC()->session->set( 'shipping_for_package_' . $package_key, false );
		}
	}

	/**
	 * Update the Klarna order after calculations from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_walley_order() {

		if ( ! is_checkout() ) {
			return;
		}

		if ( 'collector_checkout' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		// We can only do this during AJAX, so if it is not an ajax call, we should just bail.
		if ( ! wp_doing_ajax() ) {
			return;
		}

		// Trigger get if the ajax event is among the approved ones.
		$ajax = filter_input( INPUT_GET, 'wc-ajax', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! in_array( $ajax, array( 'update_order_review' ), true ) ) {
			return;
		}

		$private_id = WC()->session->get( 'collector_private_id' );
		if ( empty( $private_id ) ) {
			return walley_print_error_message( new WP_Error( 'error', __( 'Missing Walley private id. Possible API error', 'collector-checkout-for-woocommerce' ) ) );
		}

		$customer_type = WC()->session->get( 'collector_customer_type' );
		if ( empty( $customer_type ) ) {
			return;
		}

		self::maybe_update_metadata( $private_id, $customer_type );

		self::maybe_update_fees( $private_id, $customer_type );

		self::maybe_update_cart( $private_id, $customer_type );

		// Update database session id.
		$collector_checkout_sessions = new Collector_Checkout_Sessions();
		$collector_data              = array(
			'session_id' => $collector_checkout_sessions->get_session_id(),
		);
		$args                        = array(
			'private_id' => WC()->session->get( 'collector_private_id' ),
			'data'       => $collector_data,
		);

		Collector_Checkout_DB::update_data( $args );

		// If cart doesn't need payment anymore - reload the checkout page.
		if ( ! WC()->cart->needs_payment() ) {
			WC()->session->reload_checkout = true;
		}
	}

	/**
	 * Maybe update metadata.
	 *
	 * @param string $private_id The Walley checkout session id.
	 * @param string $customer_type The customer type (B2B|B2C).
	 * @return void
	 */
	public static function maybe_update_metadata( $private_id, $customer_type ) {
		// start by updating our metadata if any.
		$metadata = apply_filters( 'coc_update_cart_metadata', array() );
		if ( ! empty( $metadata ) ) {

			// Use new or old API.
			if ( walley_use_new_api() ) {
				$collecor_order_metadata = CCO_WC()->api->update_walley_metadata(
					array(
						'private_id'    => $private_id,
						'customer_type' => $customer_type,
						'metadata'      => $metadata,
					)
				);
			} else {
				$update_metadata         = new Collector_Checkout_Requests_Update_Metadata( $private_id, $customer_type, $metadata );
				$collecor_order_metadata = $update_metadata->request();
			}

			// Check that everything went alright.
			if ( is_wp_error( $collecor_order_metadata ) ) {
				// Check if purchase was completed, if it was don't redirect customer.
				if ( 900 === $collecor_order_metadata->get_error_code() ) {
					if ( ! empty( $collecor_order_metadata->get_error_message( 'Purchase_Completed' ) ) || ! empty( $collecor_order_metadata->get_error_message( 'Purchase_Commitment_Found' ) ) ) {
						WC()->session->reload_checkout = true;
					}
				}
				// Check if we had validation error.
				if ( 400 === $collecor_order_metadata->get_error_code() ) {
					if ( ! empty( $collecor_order_metadata->get_error_message( 'Validation_Error' ) ) ) {
						WC()->session->reload_checkout = true;
					}
				}
				// Check if the resource is temporarily locked, if it was don't redirect customer.
				if ( 423 === $collecor_order_metadata->get_error_code() ) {
					WC()->session->reload_checkout = true;
				}
			}
		}
	}

	/**
	 * Maybe update fees.
	 *
	 * @param string $private_id The Walley checkout session id.
	 * @param string $customer_type The customer type (B2B|B2C).
	 * @return void
	 */
	public static function maybe_update_fees( $private_id, $customer_type ) {
		// Use new or old API.
		if ( walley_use_new_api() ) {

			// If Walley delivery module is used and there is no invoice fee - bail.
			if ( empty( Walley_Checkout_Requests_Fees_Helper::fees() ) ) {
				return;
			}

			$collector_order_fee = CCO_WC()->api->update_walley_fees(
				array(
					'private_id'    => $private_id,
					'customer_type' => $customer_type,
				)
			);
		} else {
			$update_fees         = new Collector_Checkout_Requests_Update_Fees( $private_id, $customer_type );
			$collector_order_fee = $update_fees->request();
		}

		if ( is_checkout() ) {
			$cart_item_total = Collector_Checkout_Requests_Cart::cart();

			// Update checkout and annul payment method if the total cart item amount is 0.
			if ( empty( $cart_item_total['items'] ) ) {
				WC()->session->reload_checkout = true;
			}
		}

		// Check that update fees request was ok.
		if ( is_wp_error( $collector_order_fee ) ) {
			// Check if purchase was completed, if it was don't redirect customer.
			if ( 900 === $collector_order_fee->get_error_code() ) {
				WC()->session->reload_checkout = true;
				return;
			}

			// Check if somethings wrong with the content of the cart sent, if it was don't redirect customer.
			if ( 400 === $collector_order_fee->get_error_code() ) {
				if ( ! empty( $collector_order_fee->get_error_message( 'Duplicate_Articles' ) ) || ! empty( $collector_order_fee->get_error_message( 'Validation_Error' ) ) ) {
					WC()->session->reload_checkout = true;
					return;
				}
			}

			// Check if the resource is temporarily locked, if it was don't redirect customer.
			if ( 423 === $collector_order_fee->get_error_code() ) {
				WC()->session->reload_checkout = true;
				return;
			}

			wc_collector_unset_sessions();
			WC()->session->reload_checkout = true;
		}
	}

	/**
	 * Maybe update cart.
	 *
	 * @param string $private_id The Walley checkout session id.
	 * @param string $customer_type The customer type (B2B|B2C).
	 * @return void
	 */
	public static function maybe_update_cart( $private_id, $customer_type ) {

		// Use new or old API.
		if ( walley_use_new_api() ) {
			$collector_order_cart = CCO_WC()->api->update_walley_cart(
				array(
					'private_id'    => $private_id,
					'customer_type' => $customer_type,
				)
			);
		} else {
			$update_cart          = new Collector_Checkout_Requests_Update_Cart( $private_id, $customer_type );
			$collector_order_cart = $update_cart->request();
		}

		// Check that update cart request was ok.
		if ( is_wp_error( $collector_order_cart ) ) {

			// Check if purchase was completed, if it was don't redirect customer.
			if ( 900 === $collector_order_cart->get_error_code() ) {
				if ( ! empty( $collector_order_cart->get_error_message( 'Purchase_Completed' ) ) || ! empty( $collector_order_cart->get_error_message( 'Purchase_Commitment_Found' ) ) ) {
					WC()->session->reload_checkout = true;
					return;
				}
			}

			// Check if somethings wrong with the content of the cart sent, if it was don't redirect customer.
			if ( 400 === $collector_order_cart->get_error_code() ) {

				if ( ! empty( $collector_order_cart->get_error_message( 'Duplicate_Articles' ) ) || ! empty( $collector_order_cart->get_error_message( 'Validation_Error' ) ) ) {
					WC()->session->reload_checkout = true;
					return;
				}
			}

			// Check if the resource is temporarily locked, if it was don't redirect customer.
			if ( 423 === $collector_order_cart->get_error_code() ) {
				WC()->session->reload_checkout = true;
				return;
			}

			wc_collector_unset_sessions();
			WC()->session->reload_checkout = true;
		}

	}
} new Walley_Checkout();
