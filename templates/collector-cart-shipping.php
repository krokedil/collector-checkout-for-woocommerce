<?php
/**
 * Template file for the shipping section of the cart.
 *
 * @package Collector/Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shipping_id = WC()->session->get( 'chosen_shipping_methods' )[0];
$packages    = WC()->shipping->get_packages();
$rate        = null;
foreach ( $packages as $package ) {
	if ( isset( $package['rates'] ) && isset( $package['rates'][ $shipping_id ] ) ) {
		$rate = $package['rates'][ $shipping_id ];
		break;
	}
}
if ( ! empty( $rate ) ) {
	?>
	<tr class="woocommerce-shipping-totals shipping">
		<th><?php esc_html_e( 'Shipping', 'woocommerce' ); ?></th>
		<td data-title="Shipping" class="cco-shipping"><?php echo wp_kses_post( wc_cart_totals_shipping_method_label( $rate ) ); ?></td>
	</tr>
	<?php
}
