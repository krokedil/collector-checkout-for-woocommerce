<?php
/**
 * Collector Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package collector-checkout-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
wc_print_notices();
do_action( 'woocommerce_before_checkout_form', $checkout );
do_action( 'collector_wc_before_checkout_form' );
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<div id="collector-wrapper">
		<!-- <h3 id="order_review_heading" style="float:none"><?php //phpcs:ignore _e( 'Your order', 'woocommerce' ); ?></h3> !-->
		<div id="collector-order-review">
			<?php do_action( 'collector_wc_before_order_review' ); ?>
			<?php woocommerce_order_review(); ?>
			<?php do_action( 'collector_wc_after_order_review' ); ?>
		</div>
		<div id="collector-iframe">
			<?php do_action( 'collector_wc_before_iframe' ); ?>
			<?php collector_wc_show_snippet(); ?>
			<?php do_action( 'collector_wc_after_iframe' ); ?>
		</div>
	</div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
