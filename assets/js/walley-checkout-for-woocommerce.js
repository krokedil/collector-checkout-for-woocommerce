/**
 * @var walleyParams
 */
jQuery( function( $ ) {
	if ( typeof walleyParams === 'undefined' ) {
		return false;
	}
	var walleyCheckoutWc = {
		bodyEl: $('body'),
		checkoutFormSelector: 'form.checkout',
		preventPaymentMethodChange: false,
		selectAnotherSelector: '#collector_change_payment_method',
		paymentMethodEl: $('input[name="payment_method"]'),
		customerTypeSelector: '.collector-checkout-tabs li',

		init: function () {
			$( document ).ready( walleyCheckoutWc.documentReady );

			// In thank you page we only want to display the Walley Checkout.
			if ( walleyParams.is_thank_you_page === 'yes' ) {
				return;
			}

			walleyCheckoutWc.bodyEl.on( 'change', 'input[name="payment_method"]', walleyCheckoutWc.maybeChangeToWalleyCheckout );
			walleyCheckoutWc.bodyEl.on( 'click', walleyCheckoutWc.selectAnotherSelector, walleyCheckoutWc.changeFromWalleyCheckout );
			walleyCheckoutWc.bodyEl.on( 'click', walleyCheckoutWc.customerTypeSelector, walleyCheckoutWc.changeCustomerType );
			walleyCheckoutWc.bodyEl.on('update_checkout', walleyCheckoutWc.suspendWalleyCheckout);
			walleyCheckoutWc.bodyEl.on('updated_checkout', walleyCheckoutWc.resumeWalleyCheckout);
			walleyCheckoutWc.bodyEl.on( 'updated_checkout', walleyCheckoutWc.maybeDisplayShippingPrice );

            document.addEventListener( 'walleyCheckoutCustomerUpdated', function (event) { walleyCheckoutWc.updateAddress(event) } );
            document.addEventListener( 'walleyCheckoutLocked', function (event) { walleyCheckoutWc.blockForm() } );
            document.addEventListener( 'walleyCheckoutUnlocked', function (event) { walleyCheckoutWc.unblockForm() } );
            document.addEventListener( 'walleyCheckoutShippingUpdated', function (event) { walleyCheckoutWc.shippingMethodChanged() } );

			if( window.walley ) {
				window.walley.checkout.api.onBeforePayment(async function() {
					walleyCheckoutWc.logToFile( 'onBeforePayment from Walley triggered' );

					// Setup a timeout that will be used if the onBeforePaymentHandler takes too long to return a rejected promise.
					const timeout = new Promise((resolve, reject) => {
					setTimeout(() => {
						reject({
							title: "Place WooCommerce order issue.",
							message: "Timeout",
						});
						}, 29000); // 29 seconds
					});

					try {
						// Setup a handler that will be used to place the order.
						const handler = new Promise(async (resolve, reject) => {
							try {
							await walleyCheckoutWc.placeWalleyOrder();
							} catch (error) {
								reject(error);
							}
							clearTimeout(timeout);
							resolve();
						});

						// Race the timeout against the onBeforePaymentHandler.
						await Promise.race([handler, timeout])

						// If we get here, the order was placed successfully. If the timeout wins, an error is thrown and caught below.
						walleyCheckoutWc.logToFile('Successfully placed order.');
					} catch (error) {
						clearTimeout(timeout);
						let message = ''
						$(error.message.replace(/(\t|\n)/gm, "")).find('li').filter(e => e !== undefined).each((i, e) => {
							message += `<li>${e.textContent.replace(/<\/?[^>]+(>|$)/g, "")}</li>`
						})

						// If we could not extract any HTML from the error, use the original error message.
						if (!message) {
							message = error.message.replace(/<\/?[^>]+(>|$)/g, "").replace(/(\t|\n)/gm, "") ?? 'Something went wrong.'
						}

						let title = ''
						if (error.title) {
							title = error.title.replace(/<\/?[^>]+(>|$)/g, "").replace(/(\t|\n)/gm, "") ?? '';
						}

						// Do not modify the original message as it will be sent separately to Walley.
						const message_to_customer = (title) ? `${message}: ${title}` : message;
						walleyCheckoutWc.failOrder( null, message_to_customer );
						
						// Log the error to the Walley log in WooCommerce.
						let logMessage = message.replace( /<li>/g, "" ).replace( /<\/li>/g, ", " ).replace( /, $/, "" );
						walleyCheckoutWc.logToFile( 'Before payment error | ' + logMessage );

						return Promise.reject({title: title, message: message});
					}
				});
			}
		},

		extractErrorMessage: function(error) {
			// Check if error is a jqXHR object
			if (error && error.responseText) {
				try {
					// Attempt to parse JSON response
					let jsonResponse = JSON.parse(error.responseText);
					return jsonResponse.message || jsonResponse.error || 'Unknown AJAX error';
				} catch {
					// Fallback for non-JSON response
					return error.statusText || 'Unknown AJAX error';
				}
			} else if (error instanceof Error) {
				// Standard Error object
				return error.message;
			} else {
				// Fallback for other types of errors
				return 'Unknown error';
			}
		},

		placeWalleyOrder: async function() {
			$('.woocommerce-checkout-review-order-table').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			try {
				const walleyOrderResponse = await this.getWalleyOrder();
				if (!walleyOrderResponse.success) {
					throw new Error('Failed to get the Walley order.');
				}
				walleyCheckoutWc.setAddressData(walleyOrderResponse.data);

				const submitOrderResponse = await this.submitOrder();
				if (submitOrderResponse.result !== 'success') {
					throw new Error(submitOrderResponse.messages);
				}
			} catch (error) {
				// Extract and log the error message
				let errorMessage = this.extractErrorMessage(error);
				throw new Error(errorMessage);
			}
		},

		getWalleyOrder: function () {
			return $.ajax({
				type: 'POST',
				data: { nonce: walleyParams.get_order_nonce },
				dataType: 'json',
				url: walleyParams.get_order_url,
			});
		},

		submitOrder: function () {
			return $.ajax({
				type: 'POST',
				url: walleyParams.submitOrder,
				data: $('form.checkout').serialize(),
				dataType: 'json',
			});
		},

		/**
		 * Triggers on document ready.
		 */
		documentReady: function() {
			console.log('walley documentReady');
			if ( 0 < $('input[name="payment_method"]').length ) {
				walleyCheckoutWc.paymentMethod = $('input[name="payment_method"]').filter( ':checked' ).val();
			} else {
				walleyCheckoutWc.paymentMethod = 'collector_checkout';
			}

			if( ! walleyParams.payForOrder && walleyCheckoutWc.paymentMethod === 'collector_checkout' ) {
				walleyCheckoutWc.moveExtraCheckoutFields();
			}

			walleyCheckoutWc.setCurrentCustomerType();

			if (walleyParams.is_thank_you_page === 'yes') {
				console.log('Thankyou page');
				walleyCheckoutWc.thankyouPage();
			}
		},

		changeCustomerType: function() {
			var tab_id = $(this).attr('data-tab');
			console.log(tab_id);
			walleyCheckoutWc.getNewCheckoutIframe( tab_id );
			$('.collector-checkout-tabs li').removeClass('current');
			$(this).addClass('current');
		},

		setCurrentCustomerType: function() {
			$('.collector-checkout-tabs li').removeClass('current');
			$('li[data-tab="' + walleyParams.selected_customer_type + '"]').addClass('current');
		},

		suspendWalleyCheckout: function() {
			console.log('suspendWalleyCheckout');
			if(window.walley !== undefined) {
				window.collector.checkout.api.suspend()
			}
		},
		resumeWalleyCheckout: function() {
			console.log('resumeWalleyCheckout');
			if(window.walley !== undefined) {
				window.collector.checkout.api.resume()
			}
		},
        blockForm: function() {
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
		},
        unblockForm: function() {
			/* Order review. */
            $( '.woocommerce-checkout-review-order-table' ).unblock();

            /* Additional checkout fields. */
            $( '.woocommerce-checkout-review-order-table' ).siblings().unblock();
		},
		shippingMethodChanged: function (shipping) {
			// $('#qoc_shipping_data').val(JSON.stringify(shipping));
            console.log('walley_shipping_option_changed', shipping);
			$( 'body' ).trigger( 'walley_shipping_option_changed', [ shipping ]);
			$( 'body' ).trigger( 'update_checkout' );
		},
		/**
		 * When the customer changes from Walley to other payment methods.
		 * @param {Event} e
		 */
		changeFromWalleyCheckout: function( e ) {
			e.preventDefault();
			$( walleyCheckoutWc.checkoutFormSelector ).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.ajax({
				type: 'POST',
				dataType: 'json',
				data: {
					collector_checkout: false,
					nonce: walleyParams.change_payment_method_nonce
				},
				url: walleyParams.change_payment_method_url,
				success: function( data ) {},
				error: function( data ) {},
				complete: function( data ) {
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},
		/**
		 * When the customer changes to Walley from other payment methods.
		 */
		maybeChangeToWalleyCheckout: function() {
			if ( ! walleyCheckoutWc.preventPaymentMethodChange ) {
				if ( 'collector_checkout' === $( this ).val() ) {
					$( '.woocommerce-info' ).remove();
					$( walleyCheckoutWc.checkoutFormSelector ).block({
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					});
					$.ajax({
						type: 'POST',
						data: {
							collector_checkout: true,
							nonce: walleyParams.change_payment_method_nonce
						},
						dataType: 'json',
						url: walleyParams.change_payment_method_url,
						success: function( data ) {},
						error: function( data ) {},
						complete: function( data ) {
							console.log('maybeChangeToWalleyCheckout', data);
							window.location.href = data.responseJSON.data.redirect;
						}
					});
				}
			}
		},
		/**
		 * Display Shipping Price in order review if Display shipping methods in iframe settings is active.
		 */
		maybeDisplayShippingPrice: function() {
            // Check if we already have set the price. If we have, return.
            if( $('.collector-shipping').length ) {
                return;
            }
            if ( 'collector_checkout' === walleyCheckoutWc.paymentMethod && 'yes' === walleyParams.delivery_module ) {
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
                    $('.woocommerce-shipping-totals td').html(walleyParams.no_shipping_message);
                }
            }
        },

		/**
		 * Moves all non standard fields to the extra checkout fields.
		 */
		moveExtraCheckoutFields: function() {
			// Move order comments.
			$('.woocommerce-additional-fields').appendTo('#walley-extra-checkout-fields');

			let form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
			for (var i = 0; i < form.length; i++ ) {
				let name = form[i].name;
				// Check if field is inside the order review.
				if( $( 'table.woocommerce-checkout-review-order-table' ).find( form[i] ).length ) {
					continue;
				}

				// Check if this is a standard field.
				if ( -1 === $.inArray( name, walleyParams.standardWooCheckoutFields ) ) {
					// This is not a standard Woo field, move to our div.
					if ( 0 < $( 'p#' + name + '_field' ).length ) {
						$( 'p#' + name + '_field' ).appendTo( '#walley-extra-checkout-fields' );
					} else {
						$( 'input[name="' + name + '"]' ).closest( 'p' ).appendTo( '#walley-extra-checkout-fields' );
					}
				}
			}
		},
		updateAddress: function (customerInfo) {
            console.log('customerInfo', customerInfo);
            /*
			var email = (('email' in customerInfo) ? customerInfo.email : null);
			var phone  = (('mobileNumber' in customerInfo) ? customerInfo.mobileNumber : null);
			var firstName = (('firstName' in customerInfo.address) ? customerInfo.address.firstName : null);
			var lastName = (('lastName' in customerInfo.address) ? customerInfo.address.lastName : null);
			var street = (('street' in customerInfo.address) ? street : null);
			var postalCode = (('postalCode' in customerInfo.address) ? customerInfo.address.postalCode : null);
			var city = (('city' in customerInfo.address) ? customerInfo.address.city : null);
			*/
			// Check if shipping fields or billing fields are to be used.
			if( ! $('#ship-to-different-address-checkbox').is(":checked") ) {
                /*
				(email !== null && email !== undefined) ? $('#billing_email').val(email) : null;
				(phone !== null && phone !== undefined) ? $('#billing_phone').val(phone) : null;
				(firstName !== null && firstName !== undefined) ? $('#billing_first_name').val(firstName) : null;
				(lastName !== null && lastName !== undefined) ? $('#billing_last_name').val(lastName) : null;
				(street !== null && street !== undefined) ? $('#billing_address_1').val(street) : null;
				(postalCode !== null && postalCode !== undefined) ? $('#billing_postcode').val(postalCode) : null;
				(city !== null && city !== undefined) ? $('#billing_city').val(city) : null;
                */
				$("form.checkout").trigger('update_checkout');
				$('#billing_email').change();
				$('#billing_email').blur();
			} else {
                /*
				(email !== null && email !== undefined) ? $('#shipping_email').val(email) : null;
				(phone !== null && phone !== undefined) ? $('#shipping_phone').val(phone) : null;
				(firstName !== null && firstName !== undefined) ? $('#shipping_first_name').val(firstName) : null;
				(lastName !== null && lastName !== undefined) ? $('#shipping_last_name').val(lastName) : null;
				(street !== null && street !== undefined) ? $('#shipping_address_1').val(street) : null;
				(postalCode !== null && postalCode !== undefined) ? $('#shipping_postcode').val(postalCode) : null;
				(city !== null && city !== undefined) ? $('#shipping_city').val(city) : null;
                */
				$("form.checkout").trigger('update_checkout');
				$('#shipping_email').change();
				$('#shipping_email').blur();
			}
		},

		/*
		 * Sets the WooCommerce form field data.
		 */
		setAddressData: function (addressData) {
			if (0 < $('form.checkout #terms').length) {
				$('form.checkout #terms').prop('checked', true);
			}
			// console.log( addressData );

			// Billing fields.
			$('#billing_first_name').val(addressData.billing_first_name);
			$('#billing_last_name').val(addressData.billing_last_name);
			$('#billing_company').val(addressData.billing_company);
			$('#billing_address_1').val(addressData.billing_address_1);
			$('#billing_address_2').val(addressData.billing_address_2);
			$('#billing_city').val(addressData.billing_city);
			$('#billing_postcode').val(addressData.billing_postcode);
			$('#billing_phone').val(addressData.billing_phone);
			$('#billing_email').val(addressData.billing_email);
			$('#billing_country').val(addressData.billing_country);

			// Shipping fields.
			$('#ship-to-different-address-checkbox').prop( 'checked', true);
			$('#shipping_first_name').val(addressData.shipping_first_name);
			$('#shipping_last_name').val(addressData.shipping_last_name);
			$('#shipping_company').val(addressData.shipping_company);
			$('#shipping_address_1').val(addressData.shipping_address_1);
			$('#shipping_address_2').val(addressData.shipping_address_2);
			$('#shipping_city').val(addressData.shipping_city);
			$('#shipping_postcode').val(addressData.shipping_postcode);
			$('#shipping_phone').val(addressData.shipping_phone);
			$('#shipping_country').val(addressData.shipping_country);
		},

		failOrder: async function( event, errorMessage ) {
			console.log('failOrder', errorMessage);
			walleyCheckoutWc.logToFile( 'Checkout error | Error message: ' + errorMessage );

			const errorClasses = 'woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout';
			const errorWrapper = `<div class="${ errorClasses }"><ul class="woocommerce-error" role="alert"><li>${ errorMessage }</li></ul></div>`;
			// Re-enable the form.
			$( 'body' ).trigger( 'updated_checkout' );

			$( walleyCheckoutWc.checkoutFormSelector ).removeClass( 'processing' );
			$( walleyCheckoutWc.checkoutFormSelector ).unblock();
			$( '.woocommerce-checkout-review-order-table' ).unblock();

			// Print error messages, and trigger checkout_error, and scroll to notices.
			$( '.woocommerce-NoticeGroup-checkout,' +
				'.woocommerce-error,' +
				'.woocommerce-message'
			).remove();

			$( walleyCheckoutWc.checkoutFormSelector ).prepend( errorWrapper );
			$( walleyCheckoutWc.checkoutFormSelector )
				.find( '.input-text, select, input:checkbox' )
				.trigger( 'validate' )
				.blur();
			$( document.body ).trigger( 'checkout_error', [ errorMessage ] );
			$( 'html, body' ).animate(
				{
					scrollTop:
						$( walleyCheckoutWc.checkoutFormSelector ).offset()
							.top - 100,
				},
				1000
			);
		},

		thankyouPage: function() {
			$.ajax(
				walleyParams.get_checkout_thank_you_url,
				{
					type: 'POST',
					dataType: 'json',
					data: {
						action  : 'get_checkout_thank_you',
						order_id : walleyParams.order_id,
						purchase_status : walleyParams.purchase_status,
						public_token: walleyParams.public_token
					},
					success: function(data) {
						var publicToken = data.data.publicToken;
						var testmode = data.data.test_mode;
						var customer_type = data.data.customer_type;
						if(testmode === 'yes') {
							$('div.collector-checkout-thankyou').append('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-lang="' + walleyParams.locale + '" data-token="' + publicToken + '" data-variant="' + customer_type + '" ' + walleyParams.data_action_color_button + '>');
						} else {
							$('div.collector-checkout-thankyou').prepend('<script src="https://checkout.collector.se/collector-checkout-loader.js" data-lang="' + walleyParams.locale + '" data-token="' + publicToken + '" data-variant="' + customer_type + '" ' + walleyParams.data_action_color_button + '>');
						}
					}
				});
		},
		getNewCheckoutIframe: function( customer ) {
			console.log( 'getNewCheckoutIframe', customer );

			var data = {
				'action': 'get_public_token',
				'customer_type': customer
			};
			jQuery.post(walleyParams.get_public_token_url, data, function (data) {
				if (true === data.success) {
					// Add class to body
					$('body').addClass('collector-checkout-selected');
					// Empty any checkout content to prevent duplicate
					$('#collector-container').empty();

					var publicToken = data.data.publicToken;
					var testmode = data.data.test_mode;
					console.log('checkout initiated ' + JSON.stringify(data.data));

					if(testmode === 'yes') {
						$('#collector-container').append('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-lang="' + walleyParams.locale + '" data-token="' + publicToken + '" data-variant="' + customer + '"' + walleyParams.data_action_color_button + ' >');
					} else {
						$('#collector-container').append('<script src="https://checkout.collector.se/collector-checkout-loader.js" data-lang="' + walleyParams.locale + '" data-token="' + publicToken + '" data-variant="' + customer + '"' + walleyParams.data_action_color_button + ' >');
					}
					checkout_initiated = 'yes';
				} else {
					$('#collector-container').empty();
					$('#collector-container').append('<ul class="woocommerce-error"><li>' + data.data + '</li></ul>');
					console.log('error');
					console.log(data.data);
				}
			});
		},
		/**
		 * Logs the message to the Walley log in WooCommerce.
		 * @param {string} message
		 */
		logToFile: function( message ) {
			$.ajax(
				{
					url: walleyParams.log_to_file_url,
					type: 'POST',
					dataType: 'json',
					data: {
						message: message,
						nonce: walleyParams.log_to_file_nonce
					}
				}
			);
		},
	};
	walleyCheckoutWc.init();
});
