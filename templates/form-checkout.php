<?php
if ( ! defined( 'ABSPATH' ) ) {
exit;
}

wc_print_notices();

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
echo apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) );
return;
}

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

</form>
<div id="collector-bank-iframe"></div>
<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>


