(function ($) {
	var checkout_initiated = false;
	
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
    
    //Listen for event from button press
	document.addEventListener("collectorInstantTokenRequested", function(){
		checkout_initiated		= true;
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
            wc_collector_checkout_instant_checkout.instant_purchase_url,
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
    
    // Display Instant Buy button if customer data exist
    document.addEventListener("collectorInstantCustomerDataExists", function(){
		$( "#button-instant-checkout" ).addClass( "in" );
		console.log('collectorInstantCustomerDataExists');
	});
	
    // Instant Buy button clicked
    $(document).on('click', '#button-instant-checkout',function() {
		// Update instant checkout if the modal already has been displayed 
		// (customer has closed the modal and then opened it again without reloading the page)
		if( checkout_initiated === true ) {
		    update_checkout();
		}
		
		$('body').append('<div class="modal-backdrop fade in"></div>');
		$( ".instant-checkout" ).addClass( "in" );
		$( "body" ).addClass( "modal-open" );
		showInstantCheckout();
		
    });
    
    // Instant Buy modal window closed
    $(document).on('click', '.close, .custom-button-slim',function() {
	    
	    $( ".instant-checkout" ).removeClass( "in" );
	    $( "body" ).removeClass( "modal-open" );
		$( ".modal-backdrop" ).remove();
		hideInstantCheckout();
    });
    
    // Display Instant Buy Modal function
    function showInstantCheckout(){
		window.collector.instant.api.expand('INSTANT_CHECKOUT_MODAL');
	}
	
	// Hide Instant Buy Modal function
	function hideInstantCheckout(){
		window.collector.instant.api.collapse('INSTANT_CHECKOUT_MODAL');
	}
	
	// Update checkout function
	function update_checkout() {
    	window.collector.instant.api.suspend();
        
        var variation_id		= '';
		var quantity			= $("[name=quantity]").val()
		console.log('quantity:' + quantity);
        if( $(".single_add_to_cart_button").val() === '' ){
	        var product_id 		= $("[name=product_id]").val();
            var variation_id 	= $(".variation_id").val();
        } else {
            var product_id 		= $(".single_add_to_cart_button").val();
        }
		
		 var data = {
            action  : 'update_instant_checkout',
            product_id : product_id,
            variation_id : variation_id,
            quantity : quantity,
            customer_token : window.collector.instant.api.getCustomerToken,
        };
        
		jQuery.post(wc_collector_checkout_instant_checkout.update_instant_checkout_url, data, function (data) {
            if (true === data.success) {
                console.log('Instant checkout update ok');
                 window.collector.instant.api.resume();
            } else {
                console.log('Instant checkout update error');
            }

        });
    }
}(jQuery));

