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
            document.addEventListener( 'walleyCheckoutPurchaseCompleted', function (event) { walleyCheckoutWc.checkOrderWasPlaced() } );

			walleyCheckoutWc.watchForWalley();
			walleyCheckoutWc.registerOnBeforePayment();
		},

		/**
		 * Whether the WooCommerce order was placed from the onBeforePayment handler.
		 */
		orderPlaced: false,

		/**
		 * Records the case where Walley took the payment but no WooCommerce order was placed.
		 * This indicates a serious issue that needs to be investigated.
		 */
		checkOrderWasPlaced: function() {
			if ( walleyCheckoutWc.orderPlaced ) {
				return;
			}

			walleyCheckoutWc.logToFile( 'Walley reported PurchaseCompleted but no WooCommerce order was placed from onBeforePayment (handler registered: ' + walleyCheckoutWc.onBeforePaymentRegistered + '). The customer has been charged without an order being created.' );
		},

		/**
		 * Whether the handler has been handed to Walley.
		 *
		 * The loader stores it as window.walley.checkout._state.onBeforePayment, and re-injecting the
		 * loader keeps the existing _state, so a registration survives the iframe being re-created.
		 * Registering again is a plain re-assignment and cannot result in two orders.
		 */
		onBeforePaymentRegistered: false,
		onBeforePaymentWarningLogged: false,
		walleyWatchInstalled: false,
		onBeforePaymentStartedAt: 0,
		onBeforePaymentPollInterval: 100,
		onBeforePaymentWarnAfter: 15000,
		onBeforePaymentGiveUpAfter: 300000,

		/**
		 * Registers the handler the moment Walley's loader defines window.walley.
		 *
		 */
		watchForWalley: function() {
			
			if ( walleyCheckoutWc.walleyWatchInstalled || typeof window.walley !== 'undefined' ) {
				return;
			}

			try {
				let walley;

				Object.defineProperty( window, 'walley', {
					configurable: true,
					get: function() {
						return walley;
					},
					set: function( value ) {
						walley = value;
						walleyCheckoutWc.registerOnBeforePayment();
					},
				} );

				walleyCheckoutWc.walleyWatchInstalled = true;
			} catch ( error ) {
				// Not fatal, the poll in registerOnBeforePayment still picks the loader up.
				walleyCheckoutWc.logToFile( 'Could not watch for window.walley, falling back to polling | ' + ( ( error && error.message ) || error ) );
			}
		},

		/**
		 * Registers the onBeforePayment handler with Walley.
		 *
		 */
		registerOnBeforePayment: function() {
			if ( walleyCheckoutWc.onBeforePaymentRegistered ) {
				return;
			}

			if ( 0 === walleyCheckoutWc.onBeforePaymentStartedAt ) {
				walleyCheckoutWc.onBeforePaymentStartedAt = Date.now();
			}

			const waited = Date.now() - walleyCheckoutWc.onBeforePaymentStartedAt;
			const api = window.walley && window.walley.checkout ? window.walley.checkout.api : null;

			if ( api && typeof api.onBeforePayment === 'function' ) {
				try {
					api.onBeforePayment( walleyCheckoutWc.onBeforePaymentHandler );
					walleyCheckoutWc.onBeforePaymentRegistered = true;

					if ( waited >= walleyCheckoutWc.onBeforePaymentPollInterval ) {
						walleyCheckoutWc.logToFile( 'onBeforePayment registered after waiting ' + waited + 'ms for window.walley.' );
					}

					return;
				} catch ( error ) {
					// Do not keep retrying a call that throws, but make sure it is not lost silently.
					walleyCheckoutWc.logToFile( 'onBeforePayment registration threw an error | ' + ( ( error && error.message ) || error ) );
					return;
				}
			}

			if ( waited >= walleyCheckoutWc.onBeforePaymentGiveUpAfter ) {
				return;
			}

			// Log once, but keep waiting: the loader can still turn up, and registering late is far
			// better than letting the customer reach the pay button with no handler attached.
			if ( ! walleyCheckoutWc.onBeforePaymentWarningLogged && waited >= walleyCheckoutWc.onBeforePaymentWarnAfter ) {
				walleyCheckoutWc.onBeforePaymentWarningLogged = true;
				walleyCheckoutWc.logToFile( 'onBeforePayment NOT registered - window.walley still unavailable after ' + waited + 'ms. Walley completes a purchase without asking us when no callback is registered, so an order could be paid for without one being created.' );
			}

			setTimeout( walleyCheckoutWc.registerOnBeforePayment, walleyCheckoutWc.onBeforePaymentPollInterval );
		},

		/**
		 * Places the WooCommerce order before Walley completes the payment.
		 *
		 * Rejecting aborts the payment, so every failure path here must reject rather than swallow.
		 *
		 * @return {Promise}
		 */
		onBeforePaymentHandler: async function() {
			walleyCheckoutWc.logToFile( 'onBeforePayment from Walley triggered' );

			// Give up if placing the order takes too long, so the customer gets an error instead of a
			// checkout that never resolves.
			let timeoutId;
			const timeout = new Promise( ( resolve, reject ) => {
				timeoutId = setTimeout( () => {
					reject( {
						title: "Place WooCommerce order issue.",
						message: "Timeout",
					} );
				}, 29000 ); // 29 seconds.
			} );

			try {
				// Race the order placement against the timeout.
				await Promise.race( [ walleyCheckoutWc.placeWalleyOrder(), timeout ] );

				// If we get here, the order was placed successfully. If the timeout wins, an error is thrown and caught below.
				walleyCheckoutWc.orderPlaced = true;
				walleyCheckoutWc.logToFile( 'Successfully placed order.' );
			} catch ( error ) {
				const messages = walleyCheckoutWc.getErrorMessages( error );

				// WooCommerce prints its own notices after the reload it asked for, so do not add one here.
				if ( ! ( error && error.reload ) ) {
					walleyCheckoutWc.failOrder( null, messages );
				}

				// Log the error to the Walley log in WooCommerce.
				walleyCheckoutWc.logToFile( 'Before payment error | ' + messages.join( ', ' ) );

				return Promise.reject( { title: ( error && error.title ) || '', message: messages.join( ' ' ) } );
			} finally {
				clearTimeout( timeoutId );
			}
		},

		/**
		 * Turns whatever the order placement threw into messages that can be shown to the customer.
		 *
		 * WooCommerce returns its notices as an HTML list, but it does not always return one: a failure can
		 * arrive without any message at all. The customer must still be told something.
		 *
		 * @param {*} error The rejected value from the order placement.
		 * @return {string[]} Plain text messages, never empty.
		 */
		getErrorMessages: function( error ) {
			const raw = walleyCheckoutWc.extractErrorMessage( error );
			const messages = [];

			$( $.parseHTML( raw ) || [] ).find( 'li' ).each( ( i, e ) => {
				messages.push( e.textContent.replace( /\s+/g, ' ' ).trim() );
			} );

			// Not a list of notices: use the message as it is, without any markup.
			if ( ! messages.length ) {
				messages.push( raw.replace( /<\/?[^>]+(>|$)/g, '' ).replace( /\s+/g, ' ' ).trim() );
			}

			const found = messages.filter( Boolean );

			// WooCommerce does not always say why the order failed, but the customer still needs to be told something.
			return found.length ? found : [ walleyParams.generic_error_message ];
		},

		extractErrorMessage: function(error) {
			// Check if error is a jqXHR object
			if (error && typeof error.responseText === 'string') {
				// Anything printed before the response, e.g. a PHP notice, makes it unparsable, but the JSON is still in there.
				const json = error.responseText.slice(error.responseText.indexOf('{'), error.responseText.lastIndexOf('}') + 1);
				try {
					const jsonResponse = JSON.parse(json);
					return String(jsonResponse.messages || jsonResponse.data || '');
				} catch {
					// Not JSON at all. The status text ("parsererror") means nothing to the customer.
					return '';
				}
			}

			// An Error, or the plain object the timeout rejects with.
			return (error && typeof error.message === 'string') ? error.message : '';
		},

		placeWalleyOrder: async function() {
			$('.woocommerce-checkout-review-order-table').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			const walleyOrderResponse = await this.getWalleyOrder();
			if (!walleyOrderResponse.success) {
				// The AJAX handler puts the reason in data. It says more than a generic message does.
				throw new Error(walleyOrderResponse.data || 'Failed to get the Walley order.');
			}
			walleyCheckoutWc.setAddressData(walleyOrderResponse.data);

			const submitOrderResponse = await this.submitOrder();
			if (submitOrderResponse.result !== 'success') {
				const error = new Error(submitOrderResponse.messages);

				// WooCommerce keeps its notices in the session and prints them after the reload it is asking for.
				if (true === submitOrderResponse.reload) {
					error.reload = true;
					window.location.reload();
				}

				throw error;
			}
		},

		getWalleyOrder: function () {
			return $.ajax({
				type: 'POST',
				data: { nonce: walleyParams.get_order_nonce, collector_public_token: $('#collector_public_token').val() ?? '' },
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
			// These run on update_checkout, which is bound before WooCommerce's own handler. Throwing
			// here would stop the rest of the checkout from updating, so make sure the API is there.
			if ( window.walley && window.walley.checkout && window.walley.checkout.api ) {
				window.walley.checkout.api.suspend();
			}
		},
		resumeWalleyCheckout: function() {
			console.log('resumeWalleyCheckout');
			if ( window.walley && window.walley.checkout && window.walley.checkout.api ) {
				window.walley.checkout.api.resume();
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
			// In certain situations, Walley Checkout may return only shipping address. In that case, we will copy the shipping address to billing address, as WooCommerce requires both addresses to be filled in.
			if (!addressData.billing_address_1) {
				addressData = {
					...addressData,
					billing_first_name: addressData.shipping_first_name,
					billing_last_name: addressData.shipping_last_name,
					billing_company: addressData.shipping_company,
					billing_address_1: addressData.shipping_address_1,
					billing_address_2: addressData.shipping_address_2,
					billing_address_co: addressData.shipping_address_co,
					billing_city: addressData.shipping_city,
					billing_postcode: addressData.shipping_postcode,
					billing_phone: addressData.billing_phone,
					billing_email: addressData.billing_email,
					billing_country: addressData.shipping_country
				};
			}

			$('#billing_first_name').val(addressData.billing_first_name);
			$('#billing_last_name').val(addressData.billing_last_name);
			$('#billing_company').val(addressData.billing_company);
			$('#billing_address_1').val(addressData.billing_address_1);
			$('#billing_address_2').val(
				addressData.billing_address_co
					? addressData.billing_address_co + (addressData.billing_address_2 ? ' ' + addressData.billing_address_2 : '')
					: addressData.billing_address_2
			);
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
			$('#shipping_address_2').val(
				addressData.shipping_address_co
					? addressData.shipping_address_co + (addressData.shipping_address_2 ? ' ' + addressData.shipping_address_2 : '')
					: addressData.shipping_address_2
			);
			$('#shipping_city').val(addressData.shipping_city);
			$('#shipping_postcode').val(addressData.shipping_postcode);

			const $shippingPhone = $('#shipping_phone');
			if ($shippingPhone.length) {
				$shippingPhone.val(addressData.shipping_phone || addressData.billing_phone);
			}

			const $shippingEmail = $('#shipping_email');
			if ($shippingEmail.length) {
				$shippingEmail.val(addressData.shipping_email || addressData.billing_email);
			}

			$('#shipping_country').val(addressData.shipping_country);
		},

		failOrder: async function( event, messages ) {
			console.log('failOrder', messages);
			walleyCheckoutWc.logToFile( 'Checkout error | Error message: ' + messages.join( ', ' ) );

			const errorClasses = 'woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout';

			// The messages are plain text and must be inserted as text. WooCommerce escapes the values it puts
			// in a notice, and reading them back out of it has turned that escaped markup into markup again.
			const errorList = $( '<ul class="woocommerce-error" role="alert"></ul>' );
			messages.forEach( ( message ) => errorList.append( $( '<li></li>' ).text( message ) ) );

			const errorWrapper = $( '<div></div>' ).addClass( errorClasses ).append( errorList );
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
			$( document.body ).trigger( 'checkout_error', [ messages.join( ' ' ) ] );
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

					// Update the hidden public token field.
					$('#collector_public_token').val(publicToken);

					if(testmode === 'yes') {
						$('#collector-container').append('<script src="https://checkout-uat.collector.se/collector-checkout-loader.js" data-lang="' + walleyParams.locale + '" data-token="' + publicToken + '" data-variant="' + customer + '"' + walleyParams.data_action_color_button + ' >');
					} else {
						$('#collector-container').append('<script src="https://checkout.collector.se/collector-checkout-loader.js" data-lang="' + walleyParams.locale + '" data-token="' + publicToken + '" data-variant="' + customer + '"' + walleyParams.data_action_color_button + ' >');
					}
					checkout_initiated = 'yes';

					// An existing registration is kept by the re-injected loader, so this only matters when
					// the first attempt never got hold of window.walley. Now that a loader is definitely
					// on its way, give it another chance rather than leaving the checkout unprotected.
					walleyCheckoutWc.registerOnBeforePayment();
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
