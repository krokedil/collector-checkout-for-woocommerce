<?php
/**
 * Class for order management.
 *
 * @package Collector_Checkout/Classes/
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Walley_Checkout_Order_Management
 */
class Walley_Checkout_Order_Management {

	/**
	 * Class constructor
	 */
	public function __construct() {
		$collector_settings                    = get_option( 'woocommerce_collector_checkout_settings' );
		$this->manage_orders                   = $collector_settings['manage_collector_orders'];
		$this->display_invoice_no              = $collector_settings['display_invoice_no'];
		$this->activate_individual_order_lines = $collector_settings['activate_individual_order_lines'] ?? 'no';

		if ( 'yes' === $this->manage_orders ) {
			add_action( 'woocommerce_order_status_completed', array( $this, 'activate_walley_order' ) );
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_walley_order' ) );
		}

		if ( 'yes' === $this->manage_orders ) {
			add_filter( 'woocommerce_order_number', array( $this, 'collector_order_number' ), 1000, 2 );
		}

		add_action( 'init', array( $this, 'check_callback' ), 20 );
	}

	/**
	 *  Activate Walley order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return void
	 */
	public function activate_walley_order( $order_id ) {
		$order = wc_get_order( $order_id );

		// If this order wasn't created using collector_checkout or collector_invoice payment method, bail.
		if ( ! in_array( $order->get_payment_method(), array( 'collector_checkout', 'collector_invoice' ), true ) ) {
			return;
		}

		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		if ( $order->get_meta( '_collector_order_activated', true ) ) {
			$order->add_order_note( __( 'Could not activate Walley reservation, Walley reservation is already activated.', 'collector-checkout-for-woocommerce' ) );
			return;
		}

		if ( empty( $order->get_meta( '_collector_order_id', true ) ) ) {
			$order->add_order_note( __( 'Could not activate Walley reservation, Walley order ID is missing.', 'collector-checkout-for-woocommerce' ) );
			return;
		}

		// Part activate or activate the entire order.
		if ( 'yes' === $this->activate_individual_order_lines || apply_filters( 'wpd_delivery_order_type', 'shop_order_delivery', $order ) === $order->get_type() ) {
			$response = CCO_WC()->api->part_capture_walley_order( $order_id );

			if ( is_wp_error( $response ) ) {
				// If error save error message.
				$code          = $response->get_error_code();
				$message       = $response->get_error_message();
				$text          = __( 'Part activate Walley Checkout order error: ', 'collector-checkout-for-woocommerce' ) . '%s %s';
				$formated_text = sprintf( $text, $code, $message );
				$order->add_order_note( $formated_text );
				$order->update_status( 'on-hold' );
				return;
			}

			// Translators: Activated amount.
			$order->add_order_note( sprintf( __( 'Order part activated with Walley Checkout. Activated amount %s', 'collector-checkout-for-woocommerce' ), wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) );
			$order->update_meta_data( '_collector_order_activated', time() );
			$order->save();

			// Save received data to WP transient.
			walley_save_order_data_to_transient(
				array(
					'order_id'     => $order_id,
					'total_amount' => $order->get_total(),
					'currency'     => $order->get_currency(),
				)
			);
			return;
		} else {
			$response = CCO_WC()->api->capture_walley_order( $order_id );

			if ( is_wp_error( $response ) ) {
				// If error save error message.
				$code          = $response->get_error_code();
				$message       = $response->get_error_message();
				$text          = __( 'Activate Walley Checkout order error: ', 'collector-checkout-for-woocommerce' ) . '%s %s';
				$formated_text = sprintf( $text, $code, $message );
				$order->add_order_note( $formated_text );
				$order->update_status( 'on-hold' );
				return;
			}

			$note = __( 'Walley Checkout order activated.', 'collector-checkout-for-woocommerce' );
			$order->add_order_note( $note );
			$order->update_meta_data( '_collector_order_activated', time() );
			$order->save();

			// Save received data to WP transient.
			walley_save_order_data_to_transient(
				array(
					'order_id'     => $order_id,
					'total_amount' => $order->get_total(),
					'currency'     => $order->get_currency(),
				)
			);
			return;
		}
	}

	/**
	 * Cancel the Collector order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return void
	 */
	public function cancel_walley_order( $order_id ) {
		$order = wc_get_order( $order_id );

		// If this order wasn't created using collector_checkout or collector_invoice payment method, bail.
		if ( ! in_array( $order->get_payment_method(), array( 'collector_checkout', 'collector_invoice' ), true ) ) {
			return;
		}

		// If the order has not been paid for, bail.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		// If this reservation was already cancelled, do nothing.
		if ( $order->get_meta( '_collector_order_cancelled' ) ) {
			$order->add_order_note( __( 'Could not cancel Walley reservation, Walley reservation is already cancelled.', 'collector-checkout-for-woocommerce' ) );
			return;
		}

		// Since we cannot cancel a Walley order unless we have the Walley order ID, we need to check if it exists.
		if ( empty( $order->get_meta( '_collector_order_id' ) ) ) {
			$order->add_order_note( __( 'Could not cancel Walley reservation, Walley order ID is missing.', 'collector-checkout-for-woocommerce' ) );
			return;
		}

		// Check order status in Walley backoffice.
		if ( ! $this->is_ok_to_cancel( $order_id ) ) {
			return;
		}

		$response = CCO_WC()->api->cancel_walley_order( $order_id );

		if ( is_wp_error( $response ) ) {
			// If error save error message.
			$code          = $response->get_error_code();
			$message       = $response->get_error_message();
			$text          = __( 'Cancel Walley Checkout order error: ', 'collector-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			$order->update_status( 'on-hold' );
			return;
		}

		$note = __( 'Walley Checkout order cancelled.', 'collector-checkout-for-woocommerce' );
		$order->add_order_note( $note );
		$order->update_meta_data( '_collector_order_cancelled', time() );
		$order->save();

		// Save received data to WP transient.
		walley_save_order_data_to_transient(
			array(
				'order_id'     => $order_id,
				'total_amount' => $order->get_total(),
				'currency'     => $order->get_currency(),
			)
		);
	}

	/**
	 * Refunds the Dintero order that the WooCommerce order corresponds to.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $amount The refund amount.
	 * @param string $reason The reason for the refund.
	 * @return boolean|null TRUE on success, FALSE on unrecoverable failure, and null if not relevant or valid.
	 */
	public function refund_walley_order( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		// If this order wasn't created using collector_checkout or collector_invoice payment method, bail.
		if ( ! in_array( $order->get_payment_method(), array( 'collector_checkout', 'collector_invoice' ), true ) ) {
			return;
		}

		// If the order has not been paid for, bail.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		// If this reservation was already cancelled, do nothing.
		if ( $order->get_meta( '_collector_order_cancelled', true ) ) {
			$order->add_order_note( __( 'Could not refund Walley order, Walley reservation is cancelled.', 'collector-checkout-for-woocommerce' ) );
			return new WP_Error( 'error', __( 'Could not refund Walley order, Walley reservation is cancelled.', 'collector-checkout-for-woocommerce' ) );
		}

		// If this reservation was not activated, do nothing.
		if ( empty( $order->get_meta( '_collector_order_activated', true ) ) ) {
			$order->add_order_note( __( 'There is nothing to refund. The order has not yet been captured in WooCommerce.', 'collector-checkout-for-woocommerce' ) );
			return new WP_Error( 'error', __( 'There is nothing to refund. The order has not yet been captured in WooCommerce.', 'collector-checkout-for-woocommerce' ) );
		}

		if ( empty( $order->get_meta( '_collector_order_id', true ) ) ) {
			$order->add_order_note( __( 'Could not refund Walley reservation, Walley order ID is missing.', 'collector-checkout-for-woocommerce' ) );
			return new WP_Error( 'error', __( 'Could not refund Walley reservation, Walley order ID is missing.', 'collector-checkout-for-woocommerce' ) );
		}

		// Currently we always need to do a refund by amount since rounding fee in refund can differ from original order and Walley does not accept that.
		/*
		if ( $this->order_contain_goodwill_refund( $order_id ) ) {
			$response = CCO_WC()->api->refund_walley_order_by_amount( $order_id, $amount, $reason );
		} else {
			$response = CCO_WC()->api->refund_walley_order( $order_id, $amount, $reason );
		}
		*/
		$response = CCO_WC()->api->refund_walley_order_by_amount( $order_id, $amount, $reason );

		if ( is_wp_error( $response ) ) {
			// If error save error message.
			$code          = $response->get_error_code();
			$message       = $response->get_error_message();
			$text          = __( 'Refund Walley Checkout order error: ', 'collector-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );

			return $response;
		}

		// Save received data to WP transient.
		walley_save_order_data_to_transient(
			array(
				'order_id' => $order_id,
				'currency' => $order->get_currency(),
			)
		);

		// Translators: Refunded amount.
		$order->add_order_note( sprintf( __( 'Walley Checkout order refunded with %s.', 'collector-checkout-for-woocommerce' ), wc_price( $amount ) ) );
		return true;
	}

	/**
	 * Check if order is ok to cancel.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return bool
	 */
	public function is_ok_to_cancel( $order_id ) {
		$order     = wc_get_order( $order_id );
		$walley_id = $order->get_meta( '_collector_order_id' );
		$response  = CCO_WC()->api->get_walley_order( $walley_id );
		if ( ! is_wp_error( $response ) ) {

			if ( in_array( $response['data']['status'], array( 'NotActivated', 'PartActivated', 'Expired' ), true ) ) {
				return true;
			} else {
				// Translators: Walley payment status.
				$order->add_order_note( sprintf( __( 'Cancel Walley order request will not be triggered. Order have status <i>%s</i> in Walley Merchant Hub.', 'dibs-easy-for-woocommerce' ), $response['data']['status'] ) );
				return false;
			}
		}
		// Translators: Request error message.
		$order->add_order_note( sprintf( __( 'Unable to get the Walley order. Error message: <i>%s</i>.', 'dibs-easy-for-woocommerce' ), $response->get_error_message() ) );
		return false;
	}

	/**
	 * Check if refund order contains a goodwill refund (a partially refunded orderline).
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return bool
	 */
	public function order_contain_goodwill_refund( $order_id ) {
		$original_order  = wc_get_order( $order_id );
		$refund_order_id = Collector_Checkout_Create_Refund_Data::get_refunded_order_id( $order_id );

		// Get refund order data.
		$refund_order   = wc_get_order( $refund_order_id );
		$refunded_items = $refund_order->get_items( array( 'line_item', 'shipping', 'fee' ) );

		if ( $refunded_items ) {
			foreach ( $refunded_items as $refunded_item ) {

				$parent_order_item_id = $refunded_item->get_meta( '_refunded_item_id' );

				switch ( $refunded_item->get_type() ) {
					case 'line_item':
						$parent_order_item = new WC_Order_Item_Product( $parent_order_item_id );
						break;
					case 'shipping':
						$parent_order_item = new WC_Order_Item_Shipping( $parent_order_item_id );
						break;
					case 'fee':
						$parent_order_item = new WC_Order_Item_Fee( $parent_order_item_id );
						break;
					default:
						$parent_order_item = new WC_Order_Item_Product( $parent_order_item_id );

				}

				if ( abs( $refunded_item->get_total() ) / abs( $refunded_item->get_quantity() ) !== $parent_order_item->get_total() / $parent_order_item->get_quantity() ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check for Collector Invoice Status Change (anti fraud system)
	 **/
	public function check_callback() {
		if ( ! empty( $_SERVER['REQUEST_URI'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'module/collectorcheckout/invoicestatus' ) ) {
			$invoice_no     = filter_input( INPUT_GET, 'InvoiceNo', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$invoice_status = filter_input( INPUT_GET, 'InvoiceStatus', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! empty( $invoice_no ) && ! empty( $invoice_status ) ) {
				CCO_WC()->logger::log( 'Collector Invoice Status Change callback hit' );
				$collector_payment_id = $invoice_no;

				$order = walley_get_order_by_key( '_collector_payment_id', $collector_payment_id );

				if ( ! empty( $order ) ) {
					// Add order note about the callback
					// translators: Invoice status.
					$order->add_order_note( sprintf( __( 'Invoice status callback from Walley. New Invoice status: %s', 'collector-checkout-for-woocommerce' ), $invoice_no ) );
					// Set order status.
					if ( '1' === $invoice_status ) {
						$order->payment_complete( $collector_payment_id );
					} elseif ( '5' === $invoice_status ) {
						$order->update_status( 'failed' );
					}
					header( 'HTTP/1.1 200 Ok' );
				} else {
					$collector_info = sprintf( 'Invoice status callback from Collector but we could not find the corresponding order in WC. Collector InvoiceNo: %s InvoiceStatus: %s', $invoice_no, $invoice_status );
					// TODO log function not found in Collector_Checkout!
					CCO_WC()->logger::log( $collector_info );
					header( 'HTTP/1.0 404 Not Found' );
				}
			} else {
				CCO_WC()->logger::log( 'HTTP Request from Collector is missing parameters' );
				header( 'HTTP/1.0 400 Bad Request' );
			}
			die();
		}
	}

	/**
	 * Display Collector payment id after WC order number on orders overview page
	 *
	 * @param string   $order_number The WooCommerce order number.
	 * @param WC_Order $order The WooCommerce order.
	 **/
	public function collector_order_number( $order_number, $order ) {
		if ( is_admin() ) {
			// Check if function get_current_screen() exist.
			if ( ! function_exists( 'get_current_screen' ) ) {
				return $order_number;
			}

			$current_screen = get_current_screen();
			if ( isset( $current_screen ) && in_array( $current_screen->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ) ) ) {
				$collector_payment_id = $order->get_meta( '_collector_payment_id' );
				if ( ! empty( $collector_payment_id ) ) {
					$order_number .= ' (' . $collector_payment_id . ')';
				}
			}
		}
		return $order_number;
	}
}
