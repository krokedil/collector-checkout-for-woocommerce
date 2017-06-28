<?php
echo '<a href="#" id="collector-checkout-select-other">Select another payment method</a>';
echo '<div style="overflow:hidden; padding-top:20px;">';
echo '<div>';
woocommerce_order_review();
$form_field = WC()->checkout()->get_checkout_fields( 'order' );
woocommerce_form_field( 'order_comments', $form_field['order_comments'] );
echo '</div>';
echo '<div id="collector-bank-iframe">';

echo '</div>';
echo '</div>';
