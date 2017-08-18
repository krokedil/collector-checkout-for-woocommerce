<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

wc_print_notices();

do_action( 'woocommerce_before_checkout_form', $checkout );
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
	<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
	<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
	<h3 id="order_review_heading" style="float:none"><?php _e( 'Your order', 'woocommerce' ); ?></h3>

	<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

	<div id="order_review" style="width:100%">
		<?php do_action( 'woocommerce_checkout_order_review' ); ?>
	</div>
	<?php $form_field = WC()->checkout()->get_checkout_fields( 'order' ); ?>
	<?php woocommerce_form_field( 'order_comments', $form_field['order_comments'] ); ?>
	<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
    <?php
    $available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
    if ( count( $available_payment_gateways ) > 1 ){
    ?>
        <a id="collector_change_payment_method" href="#"><?php  echo __( 'Other payment method', 'collector-bank-for-woocommerce' ) ?></a>
    <?php } ?>
    <ul class="collector-checkout-tabs">
        <li class="tab-link current" data-tab="b2c"><?php _e( 'Privatperson', 'woocommerce' ); ?></li>
        <li class="tab-link" data-tab="b2b"><?php _e( 'Företag', 'woocommerce' ); ?></li>
    </ul>
    <div id="collector-bank-iframe"></div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>


