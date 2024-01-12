jQuery( function( $ ) {
    walleyWidget = {
        script: $( '#walley-checkout-loader' ),
        cartTotalSelector: '#walley-cart-total',

        init: function() {
            $( document.body )
                .on( 'updated_cart_totals', walleyWidget.cartUpdated )
                .on( 'found_variation', walleyWidget.variationUpdated );
        },

        cartUpdated: function() {
            // Get the new total from the cart.
            const total = $( walleyWidget.cartTotalSelector ).val();

            // Update the widget with the new total.
            walleyWidget.updateWidgetAmount( total );
        },

        variationUpdated: function( event, variation ) {
            // Get the new total from the variation.
            const total = variation.display_price;

            // Update the widget with the new total.
            walleyWidget.updateWidgetAmount( total );
        },

        updateWidgetAmount: function( amount ) {
            // Update the total in the widget.
            walleyWidget.script.attr("data-amount", Math.round(amount * 100));

            // Update the widget with Walley.
            window.walley.checkout.api.update();
        }
    };

    walleyWidget.init();
});
