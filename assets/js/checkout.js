(function ($) {
    'use strict';
    var checkout_initiated = false;

    function get_checkout_iframe( customer = 'b2c' ) {
	    console.log( customer );
        var url = window.location.href;
        if (url.indexOf('payment_successful') != -1) {
            $('.entry-content').css("display", "none");
            if ($('form #billing_first_name').val() != '') {
                // Check Terms checkbox, if it exists
                if ($("form.checkout #terms").length > 0) {
                    $("form.checkout #terms").prop("checked", true);
                }
                collector_post_form();
            }
        } else {
            var data = {
                'action': 'get_public_token',
                'customer_type': customer
            };
            jQuery.post(wc_collector_bank.ajaxurl, data, function (data) {
                if (true === data.success) {
                    // Add class to body
                    $('body').addClass('collector-bank-selected');
                    // Remove any checkout frame to prevent duplicate
                    $('#collector-checkout-iframe').remove();
                    var publicToken = data.data.publicToken;
                    var testmode = data.data.test_mode;
                    console.log('publicToken ' + publicToken);
                    if(testmode === 'yes') {
                        $('#collector-bank-iframe').append('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-lang="' + wc_collector_bank.locale + '" data-token="' + publicToken + '" data-variant="' + customer + '" >');
                    } else {
                        $('#collector-bank-iframe').append('<script src="https://checkout.collector.se/collector-checkout-loader.js" data-lang="' + wc_collector_bank.locale + '" data-token="' + publicToken + '" data-variant="' + customer + '" >');
                    }
                    checkout_initiated = true;
                } else {
                    console.log('error');
                }
            });
        }
    }
    
    // Customer updated - event triggered when customer changes address in Collector iframe
    document.addEventListener("collectorCheckoutCustomerUpdated", function(){
	    window.collector.checkout.api.suspend();
	    $.ajax(
            wc_collector_bank.ajaxurl,
            {
                type: 'POST',
                dataType: 'json',
                data: {
                    action  : 'customer_adress_updated',
                    nonce: wc_collector_bank.collector_nonce
                },
                success: function(response) {
	                console.log(response);
	                if( 'yes' == response.data ) {
		               jQuery(document.body).trigger('update_checkout'); 
	                }
                }
            }
        );
		window.collector.checkout.api.resume();
	});
	
	// Customer change B2B / B2C
	$(document).on('click', '.collector-checkout-tabs li',function() {
       var tab_id = $(this).attr('data-tab');
       console.log(tab_id);
       get_checkout_iframe( tab_id );
    });

    $(document).on('updated_checkout', function () {
        update_checkout();
        if ("collector_bank" === $("input[name='payment_method']:checked").val()) {
            $('#place_order').remove();
            // Refresh the page to load collector bank template instead.

        }
    });

    $(document).on("change", "input[name='payment_method']", function (event) {
        if ("collector_bank" === event.target.value) {
            // Refresh the page to load collector bank template instead.
            //get_checkout_iframe();
            //$("body").trigger("update_checkout");
        } else {
            $('#collector-checkout-iframe').remove();
        }
    });

    function update_checkout() {
        if( checkout_initiated === true ) {
            window.collector.checkout.api.suspend();
            var data = {
                'action': 'update_checkout'
            };
            jQuery.post(wc_collector_bank.ajaxurl, data, function (data) {
                if (true === data.success) {
                    window.collector.checkout.api.resume();
                } else {
                    console.log('error');
                }

            });
        }
    }

    // If customer gets to thank you page
    $( document ).ready( function() {
        var url = window.location.href;
        if (url.indexOf('order-received') != -1) {
            thankyou_page();
        }
    });

    function thankyou_page() {
        $.ajax(
            wc_collector_bank.ajaxurl,
            {
                type: 'POST',
                dataType: 'json',
                data: {
                    action  : 'get_checkout_thank_you',
                },
                success: function(data) {
                    var publicToken = data.data.publicToken;
                    var testmode = data.data.test_mode;
                    var customer_type = data.data.customer_type;
                    if(testmode === 'yes') {
                        $('div.entry-content div.woocommerce').prepend('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-lang="' + wc_collector_bank.locale + '" data-token="' + publicToken + '" data-variant="' + customer_type + '" >');
                    } else {
                        $('div.entry-content div.woocommerce').prepend('<script src="https://checkout.collector.se/collector-checkout-loader.js" data-lang="' + wc_collector_bank.locale + '" data-token="' + publicToken + '" data-variant="' + customer_type + '" >');
                    }
                }
            });
    }

    // Load the iframe on the custom template page, and save any customer order notes.
    $( document ).ready( function() {
        if ($('#collector-bank-iframe').length) {
            get_checkout_iframe();
        }
        $('#order_comments').focusout(function(){
            var text = $('#order_comments').val();
            if( text.length > 0 ) {
                $.ajax(
                    wc_collector_bank.ajaxurl,
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

    function collector_post_form() {
        var data = {
            'action': 'get_customer_data'
        };
        jQuery.post(wc_collector_bank.ajaxurl, data, function (data) {
            if (true === data.success) {
	            if( 'BusinessCustomer' == data.data.customer_data.data.customerType ) {
		            var datastring = 'billing_first_name=' + data.data.customer_data.data.businessCustomer.referencePerson +
                    '&billing_last_name=' + data.data.customer_data.data.businessCustomer.organizationNumber +
                    '&billing_company=' + data.data.customer_data.data.businessCustomer.companyName +
                    '&billing_country=' + data.data.customer_data.data.countryCode +
                    '&billing_address_1=' + data.data.customer_data.data.businessCustomer.invoiceAddress.address +
                    '&billing_postcode=' + data.data.customer_data.data.businessCustomer.invoiceAddress.postalCode +
                    '&billing_city=' + data.data.customer_data.data.businessCustomer.invoiceAddress.city +
                    '&billing_phone=' + data.data.customer_data.data.businessCustomer.mobilePhoneNumber +
                    '&billing_email=' + data.data.customer_data.data.businessCustomer.email +
                    '&shipping_first_name=' + data.data.customer_data.data.businessCustomer.deliveryAddress.firstName +
                    '&shipping_last_name=' + data.data.customer_data.data.businessCustomer.deliveryAddress.lastName +
                    '&shipping_company=' + data.data.customer_data.data.businessCustomer.companyName +
                    '&shipping_country=' + data.data.customer_data.data.countryCode +
                    '&shipping_address_1=' + data.data.customer_data.data.businessCustomer.deliveryAddress.address +
                    '&shipping_postcode=' + data.data.customer_data.data.businessCustomer.deliveryAddress.postalCode +
                    '&shipping_city=' + data.data.customer_data.data.businessCustomer.deliveryAddress.city +
                    '&shipping_method%5B0%5D=' + data.data.shipping +
                    '&ship_to_different_address=1' +
                    '&payment_method=collector_bank&terms=on' +
                    '&terms-field=1&_wpnonce=' + data.data.nonce;
                    
                    if(data.data.customer_data.data.businessCustomer.invoiceAddress.address2 != null) {
	                    datastring = datastring + '&billing_address_2=' + data.data.customer_data.data.businessCustomer.invoiceAddress.address2;
	                }
	                if(data.data.customer_data.data.businessCustomer.deliveryAddress.address2 != null) {
	                    datastring = datastring + '&shipping_address_2=' + data.data.customer_data.data.businessCustomer.deliveryAddress.address2;
	                }
	            } else {
	                var datastring = 'billing_first_name=' + data.data.customer_data.data.customer.billingAddress.firstName +
	                    '&billing_last_name=' + data.data.customer_data.data.customer.billingAddress.lastName +
	                    '&billing_country=' + data.data.customer_data.data.countryCode +
	                    '&billing_address_1=' + data.data.customer_data.data.customer.billingAddress.address +
	                    '&billing_postcode=' + data.data.customer_data.data.customer.billingAddress.postalCode +
	                    '&billing_city=' + data.data.customer_data.data.customer.billingAddress.city +
	                    '&billing_phone=' + data.data.customer_data.data.customer.mobilePhoneNumber +
	                    '&billing_email=' + data.data.customer_data.data.customer.email +
	                    '&shipping_first_name=' + data.data.customer_data.data.customer.deliveryAddress.firstName +
	                    '&shipping_last_name=' + data.data.customer_data.data.customer.deliveryAddress.lastName +
	                    '&shipping_country=' + data.data.customer_data.data.countryCode +
	                    '&shipping_address_1=' + data.data.customer_data.data.customer.deliveryAddress.address +
	                    '&shipping_postcode=' + data.data.customer_data.data.customer.deliveryAddress.postalCode +
	                    '&shipping_city=' + data.data.customer_data.data.customer.deliveryAddress.city +
	                    '&shipping_method%5B0%5D=' + data.data.shipping +
	                    '&ship_to_different_address=1' +
	                    '&payment_method=collector_bank&terms=on' +
	                    '&terms-field=1&_wpnonce=' + data.data.nonce;
						
					if(data.data.customer_data.data.customer.billingAddress.address2 != null) {
	                    datastring = datastring + '&billing_address_2=' + data.data.customer_data.data.customer.billingAddress.address2;
	                }
	                if(data.data.customer_data.data.customer.deliveryAddress.address2 != null) {
	                    datastring = datastring + '&shipping_address_2=' + data.data.customer_data.data.customer.deliveryAddress.address2;
	                }
					
				}
                    
                if(data.data.order_note != 'undefined'){
                    datastring = datastring + '&order_comments=' + data.data.order_note;
                }
                
                    jQuery.ajax({
                    type: 'POST',
                    url: wc_checkout_params.checkout_url,
                    data: datastring,
                    dataType: 'json',
                    success: function (result) {
                        try {
                            if ('success' === result.result) {
                                if (-1 === result.redirect.indexOf('https://') || -1 === result.redirect.indexOf('http://')) {
                                    window.location = result.redirect;
                                } else {
                                    window.location = decodeURI(result.redirect);
                                }
                            } else if ('failure' === result.result) {
                                throw 'Result failure';
                            } else {
                                throw 'Invalid response';
                            }
                        } catch (err) {
                            // Reload page
                            if (true === result.reload) {
                                window.location.reload();
                                return;
                            }
                            // Trigger update in case we need a fresh nonce
                            if (true === result.refresh) {
                                jQuery(document.body).trigger('update_checkout');
                            }
                            // Add new errors
                            if (result.messages) {
                                console.log(result.messages);
                            } else {
                                console.log(wc_checkout_params.i18n_checkout_error);
                            }
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        //wc_checkout_form.submit_error('<div class="woocommerce-error">' + errorThrown + '</div>');
                    }
                });
            } else {
                console.log('error');
            }
        });
    }
}(jQuery));
