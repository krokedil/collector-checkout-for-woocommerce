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

// Declared in class-walley-checkout-meta-box.php.
if ( ! isset( $manage_orders ) || false === $manage_orders ) {
	return;
}

if ( in_array( $walley_order_status, array( 'NotActivated', 'PartActivated' ), true ) && 0 === count( $order->get_refunds() ) ) {
	?>
	<div class="walley_sync_wrapper">
		<button class="button-secondary sync-btn-walley"><?php esc_html_e( 'Update order to Walley', 'collector-checkout-for-woocommerce' ); ?></button>
	</div>
	<?php
}


