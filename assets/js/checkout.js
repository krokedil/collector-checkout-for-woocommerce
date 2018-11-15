(function ($) {
    'use strict';
    var checkout_initiated = wc_collector_checkout.checkout_initiated;
    
    function get_new_checkout_iframe( customer ) {
	    console.log( customer );
        var url = window.location.href;
        
        var data = {
            'action': 'get_public_token',
            'customer_type': customer
        };
        jQuery.post(wc_collector_checkout.get_public_token_url, data, function (data) {
            if (true === data.success) {
                // Add class to body
                $('body').addClass('collector-checkout-selected');
                // Empty any checkout content to prevent duplicate
                $('#collector-container').empty();
                
                var publicToken = data.data.publicToken;
                var testmode = data.data.test_mode;
                console.log('checkout initiated ' + JSON.stringify(data.data));
                
                if(testmode === 'yes') {
                	$('#collector-container').append('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-lang="' + wc_collector_checkout.locale + '" data-token="' + publicToken + '" data-variant="' + customer + '" >');
                } else {
                	$('#collector-container').append('<script src="https://checkout.collector.se/collector-checkout-loader.js" data-lang="' + wc_collector_checkout.locale + '" data-token="' + publicToken + '" data-variant="' + customer + '" >');
                }
                checkout_initiated = 'yes';
            } else {
                $('#collector-container').empty();
                $('#collector-container').append('<ul class="woocommerce-error"><li>' + data.data + '</li></ul>');
                console.log('error');
                console.log(data.data);
            }
        });
    }
    
    // Post WooCommerce checkout form when Collector confirmation page has rendered
    function maybe_post_form() {
        if ( wc_collector_checkout.payment_successful == 1 ) {

            $('input#terms').prop('checked', true);
            $('input#ship-to-different-address-checkbox').prop('checked', true);

            $('.validate-required').removeClass('validate-required');
            $('form.woocommerce-checkout').submit();
            console.log('yes submitted');
            $('form.woocommerce-checkout').addClass( 'processing' );
            console.log('processing class added to form');
        }
    }
    
    // Customer updated - event triggered when customer changes address in Collector iframe
    document.addEventListener("collectorCheckoutCustomerUpdated", function(){
	    
        var url = window.location.href;
        // Check that this only happens in checkout page. Don't do it on thank you page
        if ( wc_collector_checkout.is_thank_you_page === 'no' ) {
            window.collector.checkout.api.suspend();
            $.ajax(
                wc_collector_checkout.customer_adress_updated_url,
                {
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action  : 'customer_adress_updated',
                        nonce: wc_collector_checkout.collector_nonce
                    },
                    success: function(response) {
                        console.log('customer_adress_updated ' + response);
                        if( 'yes' == response.data ) {
                        jQuery(document.body).trigger('update_checkout'); 
                        }
                    }
                }
            );
            window.collector.checkout.api.resume();
        }
	});
	
	// Customer change B2B / B2C
	$(document).on('click', '.collector-checkout-tabs li',function() {
       var tab_id = $(this).attr('data-tab');
       console.log(tab_id);
       get_new_checkout_iframe( tab_id );
       $('.collector-checkout-tabs li').removeClass('current');
       $(this).addClass('current');
    });

    // Set the correct checked radio button
	$( document ).ready(function() {
        $('.collector-checkout-tabs li').removeClass('current');
        $('li[data-tab="' + wc_collector_checkout.selected_customer_type + '"]').addClass('current');
    });

	// Suspend Collector Checkout during WooCommerce checkout update
    $(document).on('update_checkout', function () {
        if ("collector_checkout" === $("input[name='payment_method']:checked").val() && checkout_initiated == 'yes') {
            //window.collector.checkout.api.suspend();
        }
    });
    
    $(document).on('updated_checkout', function () {
        if ("collector_checkout" === $("input[name='payment_method']:checked").val() && wc_collector_checkout.payment_successful == 0 ) {
            update_checkout();
            console.log('Updated checkout event');
            //$('#place_order').remove();
        }
    });
    // Change from Collector Checkout payment method
    $(document).on( 'click', '#collector_change_payment_method', function () {
	    $( '.woocommerce-info, .checkout_coupon' ).remove();
        $('form.checkout').block({
            message: "",
            baseZ: 99999,
            overlayCSS:
                {
                    background: "#fff",
                    opacity: 0.6
                },
            css: {
                padding:        "20px",
                zindex:         "9999999",
                textAlign:      "center",
                color:          "#555",
                backgroundColor:"#fff",
                cursor:         "wait",
                lineHeight:		"24px",
            }
        });
        $.ajax(
            wc_collector_checkout.refresh_checkout_fragment_url,
            {
                type: 'POST',
                dataType: 'json',
                data: {
                    action  : 'update_fragment',
                    collector: false
                },
                success: function(data) {
                    console.log('success');
                    console.log(data);
                 
                    $('body').removeClass('collector-checkout-selected');
                    window.location.href = data.data.redirect;
                }
            });
    });
    // Change to Collector Checkout payment method
    $(document).on("change", "input[name='payment_method']", function (event) {
        if ("collector_checkout" === $("input[name='payment_method']:checked").val()) {
            $('form.checkout').block({
                message: "",
                baseZ: 99999,
                overlayCSS:
                    {
                        background: "#fff",
                        opacity: 0.6
                    },
                css: {
                    padding:        "20px",
                    zindex:         "9999999",
                    textAlign:      "center",
                    color:          "#555",
                    backgroundColor:"#fff",
                    cursor:         "wait",
                    lineHeight:		"24px",
                }
            });
            $( '.woocommerce-info, .checkout_coupon' ).remove();
            $.ajax(
                wc_collector_checkout.refresh_checkout_fragment_url,
                {
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'update_fragment',
                        collector: true,
                    },
                    success: function (data) {
                        console.log(data);
                        window.location.href = data.data.redirect;
                    }
            	}
            );
        }
    });

    function update_checkout() {
        if( checkout_initiated == 'yes' && wc_collector_checkout.payment_successful == 0 ) {
            console.log( 'payment_successful' );
            console.log( wc_collector_checkout.payment_successful );
            //window.collector.checkout.api.suspend();
            var data = {
                'action': 'update_checkout'
            };
            jQuery.post(wc_collector_checkout.update_checkout_url, data, function (data) {
                if (true === data.success) {
                    window.collector.checkout.api.resume();
                } else {
                    console.log('error in update checkout');
                    window.location.href = data.data.redirect_url;
                }

            });
        } else {
            checkout_initiated = 'yes';
        }
    }

    // If customer gets to thank you page
    $( document ).ready( function() {
        if (wc_collector_checkout.is_thank_you_page === 'yes') {
	        console.log('Thankyou page');
            thankyou_page();
        }
    });

    function thankyou_page() {
        $.ajax(
            wc_collector_checkout.get_checkout_thank_you_url,
            {
                type: 'POST',
                dataType: 'json',
                data: {
                    action  : 'get_checkout_thank_you',
                    order_id : wc_collector_checkout.order_id,
                    purchase_status : wc_collector_checkout.purchase_status,
                    public_token: wc_collector_checkout.public_token
                },
                success: function(data) {
                    var publicToken = data.data.publicToken;
                    var testmode = data.data.test_mode;
                    var customer_type = data.data.customer_type;
                    if(testmode === 'yes') {
                        $('div.collector-checkout-thankyou').append('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-lang="' + wc_collector_checkout.locale + '" data-token="' + publicToken + '" data-variant="' + customer_type + '" >');
                    } else {
                        $('div.collector-checkout-thankyou').prepend('<script src="https://checkout.collector.se/collector-checkout-loader.js" data-lang="' + wc_collector_checkout.locale + '" data-token="' + publicToken + '" data-variant="' + customer_type + '" >');
                    }
                }
            });
    }

    // Check if we need to post the WC checkout form and save any customer order notes.
    $( document ).ready( function() {

        // Maybe submit WooCommerce checkout form        
        //maybe_post_form();

        // Saving order note to session
        $('#order_comments').focusout(function(){
            var text = $('#order_comments').val();
            if( text.length > 0 ) {
                $.ajax(
                    wc_collector_checkout.add_customer_order_note_url,
                    {
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action  : 'customer_order_note',
                            order_note : text
                        },
                        success: function(response) {
                        }
                    }
                );
            }
        });
    });

     // When WooCommerce checkout submission fails
    $( document ).on( 'checkout_error', function () {
        console.log('checkout_error');
        if ("collector_checkout" === $("input[name='payment_method']:checked").val() ) {
            checkout_error();
        }
    });

    function checkout_error() {
        var error_message = $( ".woocommerce-NoticeGroup-checkout" ).text();
        console.log('checkout error ' + error_message );
        
        if ("collector_checkout" === $("input[name='payment_method']:checked").val()) {
            var data = {
                'action': 'checkout_error',
                'error_message': error_message,
            };
            
            jQuery.post(wc_collector_checkout.checkout_error, data, function (data) {
                if (true === data.success) {
                    console.log('Collector checkout error');
                    console.log(data.data.redirect_url);
                    window.location.href = data.data.redirect_url;
                }
            });
        }
    }
    
}(jQuery));
