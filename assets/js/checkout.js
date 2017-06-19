(function ($) {
    'use strict';

    var wc_collector_bank_body_class = function wc_collector_bank_body_class() {
        if ("collector_bank" === $("input[name='payment_method']:checked").val()) {
            $("body").addClass("collector-bank-selected").removeClass("collector-bank-deselected");
        } else {
            $("body").removeClass("collector-bank-selected").addClass("collector-bank-deselected");
        }
    };

    function get_checkout_iframe() {
        var data = {
            'action': 'get_public_token'
        };
        jQuery.post(wc_collector_bank.ajaxurl, data, function (data) {
            if (true === data.success) {
                // Remove any checkout frame to prevent duplicate
                $('#collector-checkout-iframe').remove();
                var publicToken = data.data;
                $('div.entry-content div.woocommerce').append('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-lang="sv" data-token="' + publicToken + '" >');
            } else {
                console.log('error');
            }
        });
    }

    $(document).ready(function () {
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
}(jQuery));
