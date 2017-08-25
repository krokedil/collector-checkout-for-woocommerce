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
    $(document).on( 'collectorInstantTokenRequested', function() {
        if( $(".single_add_to_cart_button").val() === '' ){
            var product_id = $(".variation_id").val();
        } else {
            var product_id = $(".single_add_to_cart_button").val();
        }
        $.ajax(
            wc_collector_bank_instant_checkout.ajaxurl,
            {
                type: 'POST',
                dataType: 'json',
                data: {
                    action  : 'instant_purchase',
                    product_id : product_id,
                    customer_token : window.collector.instant.api.getCustomerToken,
    			},
                success: function(data) {
                    console.log(data);
                    var publicToken = data.data.publicToken;
                    console.log(publicToken);
                    window.collector.instant.api.setPublicToken(publicToken);
                }
            });
    } )
}(jQuery));