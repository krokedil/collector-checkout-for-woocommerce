(function ($) {
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
                    var publicToken = data.data.data.publicToken;
                    console.log(publicToken);
                    window.collector.instant.api.setPublicToken(publicToken);
                }
            });
    } )
}(jQuery));