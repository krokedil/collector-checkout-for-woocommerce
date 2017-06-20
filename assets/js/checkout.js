(function ($) {
    'use strict';
    var checkout_initiated = false;
    var wc_collector_bank_body_class = function wc_collector_bank_body_class() {
        if ("collector_bank" === $("input[name='payment_method']:checked").val()) {
            $("body").addClass("collector-bank-selected").removeClass("collector-bank-deselected");
        } else {
            $("body").removeClass("collector-bank-selected").addClass("collector-bank-deselected");
        }
    };

    function get_checkout_iframe() {
        var url = window.location.href;
        if (url.indexOf('payment_successful') != -1) {
            if ($('form #billing_first_name').val() != '') {
                // Check Terms checkbox, if it exists
                if ($("form.checkout #terms").length > 0) {
                    $("form.checkout #terms").prop("checked", true);
                }
                $("#place_order").trigger("submit");
            }
        } else {
            var data = {
                'action': 'get_public_token'
            };
            jQuery.post(wc_collector_bank.ajaxurl, data, function (data) {
                if (true === data.success) {
                    // Remove any checkout frame to prevent duplicate
                    $('#collector-checkout-iframe').remove();
                    var publicToken = data.data;
                    $('div.entry-content div.woocommerce').append('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-lang="sv" data-token="' + publicToken + '" >');
                    checkout_initiated = true;
                } else {
                    console.log('error');
                }
            });
        }
    }

    $(document).on('updated_checkout', function () {
        wc_collector_bank_body_class();
        if ("collector_bank" === $("input[name='payment_method']:checked").val()) {
            // Get iframe if not fetched yet
            get_checkout_iframe();
        }
    });

    $(document).on("change", "input[name='payment_method']", function (event) {
        if ("collector_bank" === event.target.value) {
            wc_collector_bank_body_class();
            get_checkout_iframe();
            $("body").trigger("update_checkout");
        } else {
            wc_collector_bank_body_class();
            $('#collector-checkout-iframe').remove();
        }
    });
    $(document).on("updated_checkout", function () {
        update_fees();
    });

    function update_fees() {
        if( checkout_initiated == true ) {
            window.collector.checkout.api.suspend();

            var data = {
                'action': 'update_fees'
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
}(jQuery));
