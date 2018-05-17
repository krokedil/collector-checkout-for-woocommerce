<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Collector_Checkout_Instant_Checkout {
	public function __construct() {
		// Add Instant checkout button for Collector
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'collector_instant_checkout' ), 20 );
	}

	public function collector_instant_checkout() {
		$collector_settings = get_option( 'woocommerce_collector_checkout_settings' );
		$instant_checkout = $collector_settings['collector_instant_checkout'];
		if ( is_product() && 'no' !== $instant_checkout ) {
			if( 'NOK' == get_woocommerce_currency() ) {
				$locale = 'nb-NO';
			} else {
				$locale = 'sv-SE';
			}
			?>
			
			<button id="button-instant-checkout" type="button" class="single_add_to_cart_button button alt button-expand-collapse is-collapsed">Snabbköp<span class="button-expand-collapse-additional"> denna vara</span></button>
			<div id="instant-checkout-lightbox-container" class="instant-checkout modal fade modal-window" role="dialog">
				<div class="modal-dialog">
				    <div class="modal-header">
				        <button type="button" class="close" data-dismiss="modal">×</button>
				        <h4 class="modal-title"><?php echo get_bloginfo( 'name' );?> - Köp direkt</h4>
				    </div>
				    <div class="modal-body">
				        <!-- Modal content-->
				        <div class="modal-content">
							<script
							    type="text/javascript"
							    src="https://checkout-uat.collector.se/collector-instant-loader.js"
							    data-lang="<?php echo $locale;?>"
							    data-theme="core"
							    data-instance-id="INSTANT_CHECKOUT_MODAL">
							</script>
				        </div>
				    </div>
				    <div class="modal-footer">
				        <button type="button" class="custom-button custom-button-slim" data-dismiss="modal">Stäng</button>
				    </div>
				</div>
				
			</div>
			
			<?php
		}
	}
}
new Collector_Checkout_Instant_Checkout();
