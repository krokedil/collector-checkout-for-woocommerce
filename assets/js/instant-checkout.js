(function ($) {
    // Check if product is variable product, and wait for variable selection before showing checkout.
    $(document).ready( function() {
        if ($('.product-type-variable')[0] && $('.variation_id').val() === '0'){
            // Product is a variable product and variable is not set
            $('#collector-instant-iframe-').hide();
        }
    });
    // Check for changes to .variation-id to show the instant checkout
    $(document).on('change', "input[name='variation_id']", function(){
        if( $('.variation_id').val() !== '' ) {
            $('#collector-instant-iframe-').show();
        } else {
            console.log('in else');
            $('#collector-instant-iframe-').hide();
        }
    });
    //Listen for even from button press
	document.addEventListener("collectorInstantTokenRequested", function(){
		
		 var variation_id		= '';
		 var quantity			= $("[name=quantity]").val()
		
        if( $(".single_add_to_cart_button").val() === '' ){
	        var product_id 		= $("[name=product_id]").val();
            var variation_id 	= $(".variation_id").val();
            console.log( product_id );
        } else {
            var product_id 		= $(".single_add_to_cart_button").val();
        }
        $.ajax(
            wc_collector_bank_instant_checkout.ajaxurl,
            {
                type: 'POST',
                dataType: 'json',
                data: {
                    action  : 'instant_purchase',
                    product_id : product_id,
                    variation_id : variation_id,
                    quantity : quantity,
                    customer_token : window.collector.instant.api.getCustomerToken,
    			},
                success: function(data) {
                    console.log(data);
                    var publicToken = data.data.publicToken;
                    console.log(publicToken);
                    window.collector.instant.api.setPublicToken(publicToken, 'INSTANT_CHECKOUT_MODAL' );
                }
            });
    } )
    
    $(document).on('click', '#button-instant-checkout',function() {
		
		$('body').append('<div class="modal-backdrop fade in"></div>');
		$( ".instant-checkout" ).addClass( "in" );
		$( "body" ).addClass( "modal-open" );
		showInstantCheckout();
    });
    
    $(document).on('click', '.close, .custom-button-slim',function() {
	    
	    $( ".instant-checkout" ).removeClass( "in" );
	    $( "body" ).removeClass( "modal-open" );
		$( ".modal-backdrop" ).remove();
		hideInstantCheckout();
    });
    
    function showInstantCheckout(){
		window.collector.instant.api.expand('INSTANT_CHECKOUT_MODAL');
	}
	
	function hideInstantCheckout(){
		window.collector.instant.api.collapse('INSTANT_CHECKOUT_MODAL');
	}
    
    document.addEventListener("collectorInstantCustomerDataExists", function(){
		$( "#button-instant-checkout" ).addClass( "in" );
		console.log('collectorInstantCustomerDataExists');
	});
}(jQuery));

