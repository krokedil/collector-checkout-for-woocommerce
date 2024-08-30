<?php
/**
 * The HTML for the admin order metabox content.
 *
 * @package Collector_Checkout/Templates
 */

foreach ( $keys_for_meta_box as $item ) {
	?>
	<p><b><?php echo esc_html( $item['title'] ); ?></b>: <?php echo wp_kses_post( $item['value'] ); ?></p>
	<?php
}

$manage_orders = get_option( 'woocommerce_collector_checkout_settings', array() )['manage_collector_orders'] ?? 'no';
if ( ! ( isset( $manage_orders ) || wc_string_to_bool( $manage_orders ) ) ) {
	return;
}

if ( in_array( $walley_order_status, array( 'NotActivated', 'PartActivated' ), true ) && 0 === count( $order->get_refunds() ) ) {
	?>
	<div class="walley_sync_wrapper">
		<button class="button-secondary sync-btn-walley"><?php esc_html_e( 'Update order to Walley', 'collector-checkout-for-woocommerce' ); ?></button>
	</div>
	<?php
}


