(function ($) {
    'use strict';
    var checkout_initiated = wc_collector_checkout.checkout_initiated;

    // Avoid duplicate form submissions.
    var cco_submitted = false;

    // True or false if we need to update the Collector order. Set to true on initial page load.
	var collectorUpdateNeeded = true;
    
    function get_new_checkout_iframe( customer ) {
	    console.log( customer );
        
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
                	$('#collector-container').append('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-version="' + wc_collector_checkout.checkout_version + '" data-lang="' + wc_collector_checkout.locale + '" data-token="' + publicToken + '" data-variant="' + customer + '"' + wc_collector_checkout.data_action_color_button + ' >');
                } else {
                	$('#collector-container').append('<script src="https://checkout.collector.se/collector-checkout-loader.js" data-version="' + wc_collector_checkout.checkout_version + '" data-lang="' + wc_collector_checkout.locale + '" data-token="' + publicToken + '" data-variant="' + customer + '"' + wc_collector_checkout.data_action_color_button + ' >');
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

			jQuery(function ($) {
				$( 'body' ).append( $( '<div class="collector-modal"><div class="collector-modal-content">' + wc_collector_checkout.process_order_text + '</div></div>' ) );
				$('input#terms').prop('checked', true);
				$('input#ship-to-different-address-checkbox').prop('checked', true);
                $('.validate-required').removeClass('validate-required');
                
				if( false === cco_submitted ) {
                    $('form[name="checkout"]').submit();
					console.log('yes submitted');
					cco_submitted = true;
					$('form.woocommerce-checkout').addClass( 'processing' );
					console.log('processing class added to form');
				} else {
					console.log('Already submitted');
				}
			});
        }
    }
    
    // Customer updated - event triggered when customer changes address in Collector iframe
    document.addEventListener("collectorCheckoutCustomerUpdated", function(){
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
                        // All good trigger update_checkout event
						set_customer_data( response.data );
                        // if( 'yes' == response.data ) {
                        jQuery(document.body).trigger('update_checkout'); 
                        // }
                    }
                }
            );
        }
    });

    document.addEventListener("collectorCheckoutLocked", function () {
        console.log("collectorCheckoutLocked");
        blockForm()
    });

    document.addEventListener("collectorCheckoutUnlocked", function () {
        console.log("collectorCheckoutUnlocked")
        unblockForm();
    });
    
    document.addEventListener("collectorCheckoutShippingUpdated", function(listener){
        console.log('collectorCheckoutShippingUpdated');
        console.log(listener);

        if ( wc_collector_checkout.is_thank_you_page === 'no' ) {

            $( '.woocommerce-checkout-review-order-table' ).block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            window.collector.checkout.api.suspend();
            $.ajax(
                wc_collector_checkout.update_delivery_module_shipping_url,
                {
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action  : 'update_delivery_module_shipping',
                        data : listener.detail,
                        nonce: wc_collector_checkout.collector_nonce,
                    },
                    complete: function( response ) {
                        console.log('update_delivery_module_shipping done');
                        $( 'body' ).trigger( 'update_checkout' );
                    }
                }
            );
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
        // Display shipping price if Delivery module is active.
        maybeDisplayShippingPrice();
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

    /**
	 * Block form fields from being modified by the user.
	 */
	function blockForm() {
		/* Order review. */
		$( '.woocommerce-checkout-review-order-table' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
			},
		} );

		/* Additional checkout fields. */
		$( '.woocommerce-checkout-review-order-table' ).siblings().block({
			message: null,
			overlayCSS: {
				background: '#fff',
			},
		} );
	}

	/**
	 * Unblock form fields.
	 */
	function unblockForm() {
		/* Order review. */
		$( '.woocommerce-checkout-review-order-table' ).unblock();

		/* Additional checkout fields. */
		$( '.woocommerce-checkout-review-order-table' ).siblings().unblock();
	}

    // Change to Collector Checkout payment method
    $(document).on("change", "input[name='payment_method']", function (event) {
        if ( 'yes' !== wc_collector_checkout.is_collector_confirmation ) {
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
        }
    });

    function update_checkout() {
        if( checkout_initiated == 'yes' && wc_collector_checkout.payment_successful == 0 ) {

            if( ! collectorUpdateNeeded ) {
                collectorUpdateNeeded = true;
                return;
            }

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
                        $('div.collector-checkout-thankyou').append('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-version="' + wc_collector_checkout.checkout_version + '" data-lang="' + wc_collector_checkout.locale + '" data-token="' + publicToken + '" data-variant="' + customer_type + '" ' + wc_collector_checkout.data_action_color_button + '>');
                    } else {
                        $('div.collector-checkout-thankyou').prepend('<script src="https://checkout.collector.se/collector-checkout-loader.js" data-version="' + wc_collector_checkout.checkout_version + '" data-lang="' + wc_collector_checkout.locale + '" data-token="' + publicToken + '" data-variant="' + customer_type + '" ' + wc_collector_checkout.data_action_color_button + '>');
                    }
                }
            });
    }

    // Check if we need to post the WC checkout form and save any customer order notes.
    $( document ).ready( function() {

        // Maybe submit WooCommerce checkout form        
        maybe_post_form();

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
                'public_token':  wc_collector_checkout.public_token,
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

    function set_customer_data( data ) {
        console.log( 'set_customer_data', data );
        if (  null !== data.billing_country ) {
            // Billing fields.
            $( '#billing_postcode' ).val( ( ( data.billing_postcode ) ? data.billing_postcode : '' ) );
            $( '#billing_country' ).val( ( ( data.billing_country ) ? data.billing_country.toUpperCase() : '' ) );
            $( '#billing_email' ).val( ( ( data.billing_email ) ? data.billing_email : '' ) );
            $( '#billing_address_1' ).val( ( ( data.billing_address ) ? data.billing_address : '' ) );
            $( '#billing_city' ).val( ( ( data.billing_city ) ? data.billing_city : '' ) );
        }
        
        if ( null !== data.shipping_country ) {
            $( '#ship-to-different-address-checkbox' ).prop( 'checked', true);

            // Shipping fields.
            $( '#shipping_postcode' ).val( ( ( data.shipping_postcode ) ? data.shipping_postcode : '' ) );
            $( '#shipping_country' ).val( ( ( data.shipping_country ) ? data.shipping_country.toUpperCase() : '' ) );
            $( '#shipping_address_1' ).val( ( ( data.shipping_address ) ? data.shipping_address : '' ) );
            $( '#shipping_city' ).val( ( ( data.shipping_city ) ? data.shipping_city : '' ) );
        }

        // Trigger changes.
        $('#billing_email').change();
        $('#billing_email').blur();
    }

    // Display Shipping Price in order review if Display shipping methods in iframe settings is active.
    function maybeDisplayShippingPrice() {
        // Check if we already have set the price. If we have, return.
        if( $('.collector-shipping').length ) {
            return;
        }

        var paymentMethod = $("input[name='payment_method']:checked").val()
        
        if ( 'collector_checkout' === paymentMethod && 'yes' === wc_collector_checkout.delivery_module && 'no' === wc_collector_checkout.is_collector_confirmation ) {
            if ( $('#shipping_method input[type="radio"]').length > 1 ) {
                // Multiple shipping options available.
                $( '#shipping_method input[type="radio"]:checked' ).each( function() {
                    var idVal = $( this ).attr( 'id' );
                    var shippingPrice = $( 'label[for="' + idVal + '"]' ).text();
                    $( '.woocommerce-shipping-totals td' ).html( shippingPrice );
                    $( '.woocommerce-shipping-totals td' ).addClass( 'collector-shipping' );
                });
            } else if ( $('#shipping_method input[type="hidden"]').length === 1) {
                // Only one shipping option available.
                var idVal = $( '#shipping_method input[name="shipping_method[0]"]' ).attr( 'id' );
                var shippingPrice = $( 'label[for="' + idVal + '"]' ).text();
                $( '.woocommerce-shipping-totals td' ).html( shippingPrice );
                $('.woocommerce-shipping-totals td').addClass('collector-shipping');
            } else {
                // No shipping method is available.
                $('.woocommerce-shipping-totals td').html(wc_collector_checkout.no_shipping_message);
            }
        }
    }
    
}(jQuery));