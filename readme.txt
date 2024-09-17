=== Walley Checkout for WooCommerce ===
Contributors: collectorbank, krokedil, NiklasHogefjord
Tags: ecommerce, e-commerce, woocommerce, collector, checkout, walley
Requires at least: 5.0
Tested up to: 6.6.2
Requires PHP: 7.3
Stable tag: 4.2.2
WC requires at least: 6.0.0
WC tested up to: 9.3.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html


== DESCRIPTION ==
Walley Checkout for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via Collector Banks payment method Walley Checkout.

= Get started =
To get started with Walley Checkout you need to [sign up](https://www.collector.se/foretag/betallosningar/kontakta-oss/offert/) for an account.

More information on how to get started can be found in the [plugin documentation](https://docs.krokedil.com/walley-checkout-for-woocommerce/).


== INSTALLATION	 ==
1. Download the latest release zip file or install it directly via the plugins menu in WordPress Administration.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Unzip and upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your Walley Checkout settings.
6. Read more about the configuration process in the [plugin documentation](https://docs.krokedil.com/walley-checkout-for-woocommerce/).


== Frequently Asked Questions ==
= Which countries does this payment gateway support? =
Collector Checkout is available for merchants and customers in Sweden (B2C & B2B), Norway (B2C & B2B), Finland (B2C & B2B) & Denmark (B2C).

= Where can I find Collector Checkout for WooCommerce documentation? =
For help setting up and configuring Walley Checkout for WooCommerce please refer to our [documentation](https://docs.krokedil.com/walley-checkout-for-woocommerce/).



== CHANGELOG ==
= 2024.09.17    - version 4.2.2 =
* Fix           - Fixed an issue where additional post-purchase order processing would not be triggered when the customer is redirected to the confirmation page. 
* Fix           - Fixed an undefined variable warning.
* Tweak         - Added additional logging related to order creation.

= 2024.08.30    - version 4.2.1 =
* Fix           - Fixed an issue with calling the wrong function when handling callbacks.
* Fix           - Fixed an issue with handling orders with only digital and downloadable products that was reintroduced in version 4.2.0.

= 2024.08.05    - version 4.2.0 =
* Feature       - Added support for WooCommerce's "High-Performance Order Storage" ("HPOS").
* Fix           - Fixed an issue where the Walley order was incorrectly retrieved, preventing orders from being cancelled.
* Fix           - Fixed a critical error when attempting to parse the price in the payment widget.
* Fix           - Removed reference to the deprecated wc_get_log_file_path function.

= 2024.05.27    - version 4.1.1 =
* Fix           - The actual reason for a checkout error will now be displayed as intended instead of the generic "Timeout".
* Fix           - Fix a critical error when attempting to access a property on a missing order on the confirmation/"thank you" page.
* Fix           - Fixed a PHP warning due to type conversion.

= 2024.01.15    - version 4.1.0 =
* Feature       - Add support for Post Purchase Upsell to allow customers to add products to the order on the thankyou page.
* Feature       - Add support for Danish B2B Merchant IDs.
* Feature       - Adds support for Walley Part Payment Widget on the cart and product pages in WooCommerce.
* Fix           - Fixed an issue where orders with only digital and downloadable products would not be automatically completed
* Fix           - Fixed several PHP notices from PHP version 8.2.

= 2023.12.14    - version 4.0.10 =
* Enhancement   - Increased the timeout for placing a WooCommerce order to 29 seconds from 9.5 to match Walleys updated timeout of 30 seconds.

= 2023.11.21    - version 4.0.9 =
* Enhancement   - Ensures logging of any error messages generated during the checkout process.
* Enhancement   - Adds a timeout to the order placement to prevent the checkout from locking.

= 2023.10.06    - version 4.0.8 =
* Fix           - Resolved compatibility issues with Redlight Media's shipping plugins. This, by extension, also solved an issue with "WooCommerce Stripe Gateway" (by WooCommerce) where the shipping options would no longer appear in the order review, resulting in order total discrepancy in the checkout page which would prevent the customer from placing the order.
* Tweak         - Update the plugin URLs.

= 2023.09.20    - version 4.0.7 =
* Fix           - Due to a change introduced in WooCommerce version 8.1, the "thank you" snippet wouldn't be rendered as expected. This has now been fixed.

= 2023.09.11    - version 4.0.6 =
* Fix           - Improve query when trying to get WC order in callback logic. Avoids issues when multiple WC orders are created during one payment session.

= 2023.09.06    - version 4.0.5 =
* Tweak         - Save Custom Fields data to WooCommerce order also during confirm order step (when payment is done).

= 2023.09.01    - version 4.0.4 =
* Tweak         - Do not save _customer_user to order. From WC 8.0 this can result in non logged in users see a login prompt instead of the order received page.
* Fix           - Improve cart total comparison logic in process_payment sequence.
* Fix           - Improve error notice displayed in checkout if process_payment fails.

= 2023.08.31    - version 4.0.3 =
* Fix           - Adds cart total comparison between WooCommerce & Walley when order is created in Woo, before customer can complete payment.
* Fix           - Add billing and shipping company name in get_customer_address so Woo checkout form is populated correctly before placing Woo order.

= 2023.08.31    - version 4.0.2 =
* Tweak         - GitHub deployment to wordpress.org logic tweak.
* Tweak         - Small front-end logging improvement.

= 2023.08.31    - version 4.0.1 =
* Fix           - Save shipment prepared id correctly in WC order when using delivery module via nShift.

= 2023.08.30    - version 4.0.0 =
* Feature       - Major plugin rewrite. Plugin now supports Walley's front end validation JS event. WooCommerce order is created (as a Pending order) when customer clicks purchase button in Walley Checkout.
* Feature       - Add support for handling extra checkout fields in checkout.
* Feature       - Adds support for manually trigger Activate and Cancel order via WooCommerce Order Actions.
* Tweak         - Only send rounding order line to Walley if activated via settings.
* Tweak         - JS checkout file refactoring.
* Tweak         - Adds js front end logging via ajax.
* Tweak         - New API now uses update checkout (as one single request) instead of update cart, update fees and update metadata as separate requests.
* Tweak         - Use walley_confirm function also for old api.
* Tweak         - Force part capture order if order is of type shop_order_delivery (Partial Delivery for WooCommerce plugin).
* Fix           - Saving customer phone and email correctly to Woo order for B2B with new API.
* Fix           - Check Walley Checkout payment session status before trying to trigger update request to Walley.
* Fix           - Update customer address correctly in Woo when walleyCheckoutCustomerUpdated event is triggered.
* Fix           - Make sure to pass order object to update reference request for old api.
* Fix           - Add correct shipping SKU in order management requests if using Walley Delivery module with nShift.
* Fix           - Improvements in logger logic, to avoid potential PHP issue with some plugins.

= 2023.05.31    - version 3.5.6 =
* Fix           - Solve error with update order reference request that in some cases could happen in backup order creation process when using new Walley API.

= 2023.05.04    - version 3.5.5 =
* Fix           - Fixed an issue where the order id was not set when the order is created during backup process.

= 2023.04.26    - version 3.5.4 =
* Fix           - Remove checkout_initiated check in Walley js file used in checkout. This prevents potential issue where Walley Checkout isn't suspended when update request is sent to Walley.

= 2023.04.20    - version 3.5.3 =
* Fix           - Solve error with update order reference request that in some cases could happen when using new Walley API.

= 2023.04.11    - version 3.5.2 =
* Fix           - Do not add rounding order line if diff is 0.

= 2023.03.29    - version 3.5.1 =
* Tweak         - Readme file tweak.

= 2023.03.29    - version 3.5.0 =
* Feature       - Adds support for new Walley Checkout API.
* Feature       - Adds Walley metabox on order pages. Displays information about the Walley order. Available if using new API.
* Feature       - Adds support for reauthorize (update) order. This can be done before order is captured. Available if using new API.
* Feature       - Adds support for CustomFields in Walley Checkout.
* Feature       - Adds filter walley_initialize_checkout_args, walley_update_cart_args, walley_update_fees_args, walley_update_metadata_args & walley_set_order_reference_args so other plugins can modify requests data when new Checkout API is used.
* Tweak         - Move enqueuing of assets files to assets class.
* Tweak         - Move delivery module and update update cart logic to backend instead of being reliant of custom ajax requests.
* Tweak         - Removes fetching of delivery module data from Walley away from calculate_shipping function. This is now done in woocommerce_before_calculate_totals.
* Tweak         - Always do refund by amount in new Management API since rounding order line can have a different amount than what it was in the original order. This causes error in Walley.
* Tweak         - Change log file name from collector_checkout to walley_checkout.
* Tweak         - Remove setting for Walley Checkout 2.0 logic. This is not used anymore.
* Tweak         - Limit product names to 50 characters sent to Walley.
* Fix           - Set correct shipping method name in checkout when using delivery module.
* Fix           - Use wc_format_decimal to confirm limit prices to 2 decimals in update cart and update fee requests.
* Fix           - Make sure unitPrice is sent as a positive number for refunds.
* Fix           - Delete transient walley_checkout_access_token when Walley checkout settings is saved.
* Fix           - PHP 8.1 deprecated notice fix.

= 2023.01.11    - version 3.4.0 =
* Feature       - Adds shipping profile name setting option. Defaults to Shipping if Walley nShift Delivery is used but no profile name is entered.
* Feature       - Adds support for Part activate invoice (activation on order row level). Can be used together with Partial Delivery for WooCommerce plugin.
* Feature       - Adds support for new Walley Management API (used for order management).
* Tweak         - Send shipping cost to Walley first when shipping is available in Woo.
* Tweak         - Limit rounding fee to small amount.
* Tweak         - Adds customer currency to validation url. Used to get correct store id in GET collector session request.
* Fix           - Improve order total calculation in rounding fee logic related to Delivery Module settings.
* Fix           - Try to set customer address and city if possible when collectorCheckoutCustomerUpdated JS event is triggered.
* Fix           - Check if shipping object exists in Woo before trying to trigger update fee request to Walley (that is used to update shipping).
* Fix           - Wait for the delivery module to load before calculating shipping.
* Fix           - Check if session exists before trying to access it in wc_collector_get_selected_customer_type. To avoid PHP error.

= 2022.10.24    - version 3.3.1 =
* Fix           - Fixed a warning due to access before initialization.
* Fix           - Fixed an error due to the session not being available.
* Enhancement   - Implemented the checkout locking mechanism.

= 2022.09.15    - version 3.3.0 =
* Feature       - Added compatibility with Redlight Media's Walley Shipping plugin (thanks @hedqvist!).
* Tweak         - You can now use the 'coc_request_body' hook to filter the request body.
* Tweak         - You can now use the 'coc_update_cart_metadata' filter hook to send additional meta data to Walley.
* Fix           - Fix division by zero.
* Enhancement   - The checkout should now "flicker" less when updating shipping and customer data.

= 2022.08.12    - version 3.2.5 =
* Fix           - Fixed due date not being printed in the order note.
* Tweak         – If the WooCommerce and Walley total amount differs, the merchant will be notified about this.
* Tweak         - Corrected payment method names, and updated translations.

= 2022.06.21    - version 3.2.4 =
* Tweak         - Change how we get the cart totals for the rounding line function.

= 2022.06.17    - version 3.2.3 =
* Fix           - Fixed an issue with the rounding fee with sertain shipping settings.
* Fix           - Fixed adding the rounding fee even when no rounding is needed.

= 2022.06.07    - version 3.2.2 =
* Fix           - PHPCS fixes.

= 2022.05.24    - version 3.2.1 =
* Fix           - Fix rounding error.
* Tweak         - Updated links to legal documents.

= 2022.04.13    - version 3.2.0 =
* Enhancement   - Improved logging on error.
* Tweak         - The customer type switch on the checkout page is now only visible if you sell to more than one customer type.
* Tweak         - Walley Checkout is no longer available as payment option if the cart has only free products and shipping.
* Fix           - Fix various division by zero. This should fix some coupon related issues.
* Fix           - Fix issue with not always properly identifying free items on refund.
* Fix           - Do not check if the cart is available on any page other than the checkout page.

= 2022.03.02    - version 3.1.2 =
* Fix           - Fix PHP 7.x backward compatibility issue.

= 2022.02.28    - version 3.1.1 =
* Enhancement   - PHPCS changes.

= 2022.02.24    - version 3.1.0 =
* Enhancement   - PHPCS changes performed in the entire plugin.

= 2022.02.11    - version 3.0.2 =
* Tweak         - Bumped supported WP & WC versions.

= 2021.11.17    - version 3.0.1 =
* Tweak         - Updated links to the Krokedil Docs.
* Tweak         - Changing Collector Electronic ID to Walley Electronic ID & Collector Delivery Module to Walley Shipping Module.

= 2021.10.13    - version 3.0.0 =
* Feature       - Add support for Walley checkout design v2.0.
* Feature       - Add settings for different checkout layouts.
* Tweak         - Rename Collector to Walley in plugin settings and other backend locations.
* Tweak         - Logging improvements.
* Fix           - Don't try to display checkout iframe on confirmation page.

= 2021.09.27    - version 2.5.0 =
* Feature       - Add unitWeight if it exist on product data sent to Collector.
* Feature       - Add coc_request_body filter to request body for init and update calls.
* Feature       - Add coc_cart_item filter to cart items sent to Collector.
* Tweak         - Bumped required PHP version to 7.0.
* Fix           - Display Delivery Module shipping info in order even if shipping method isn't a pickup location shipping method.
* Fix           - Check if we can retreive WC()->session to avoid potential error in collector_set_not_required function.

= 2021.06.28    - version 2.4.1 =
* Fix           - Fix issue when using Delivery module but not having an invoice fee. Resulted in formatting issue in payload sent to Collector.

= 2021.06.15    - version 2.4.0 =
* Feature       - Add Require Electronic ID signing setting. Possible to require electronic signing in Collector Checkout on a per order line basis.
* Tweak         - Add email & phone number in initialize request to Collector if they exist.
* Tweak         - Improve callback handling. Adds support for (future) pending order notifications.

= 2021.04.21    - version 2.3.1 =
* Tweak         - Adds first version of Finnish translation files. Only a few strings translated.

= 2021.03.22    - version 2.3.0 =
* Tweak         - Adds wc_collector_confirm_order function.
* Tweak         - Adds Pay for order request logic.
* Tweak         - Adds filter collector_checkout_sku.
* Fix           - Improved control in wc_collector_get_order_id_by_private_id to avoid returning wrong order id.
* Fix           - Add check - do not trigger payment_complete if private id already exist in another order.

= 2021.02.25    - version 2.2.3 =
* Tweak         - Improved error response handling in requests. Don't try to create a new Collector session if we get 900, 400 or 423 http responses during update requests.
* Tweak         - Try to redirect to thankyou page if response is Purchase_Completed from Collector and we find a matching order in WooCommerce.
* Fix           - Fix B2B / B2C switcher bug in checkout.

= 2021.02.22    - version 2.2.2 =
* Fix           - Do not try to make a update fees request to Collector if not needed. Could cause multiple init requests and constant reloading of checkout page.

= 2021.02.16    - version 2.2.1 =
* Tweak         - Improved logging.
* Fix           - Set correct product price in backup order creation sequence if coupon is used for purchase.
* Fix           - Set correct WC order status in callback for orders with status Preliminary.

= 2021.02.15    - version 2.2.0 =
* Tweak         - Move confirmation JS to checkout.js file instead of inline rendering.
* Tweak         - Request handling rewrite.
* Tweak         - Log format rewrite.
* Tweak         - Don't load WC checkout form with submit button during confirmation step.
* Fix           - Don't try to make a cancel/activate request if the order hasn't been paid.

= 2021.02.09    - version 2.1.0 =
* Feature       - Handle activations, cancelations and refunds for Swish orders directly from WooCommerce.
* Tweak         - Adds WooCommerce order request class.
* Tweak         - Introduces separate logger class.
* Fix           - Save Delivery module shipping data correct in session in Woo.
* Fix           - Handles Order management response better (activate & cancel order) when API keys are misconfigured.
* Fix           - Fix ternary operators coding standard (for PHP 8.x).

= 2020.11.30    - version 2.0.2 =
* Tweak         - Add separate cart shipping template if Delivery module is active. To avoid displaying fallback/standard Woo shipping methods.
* Fix           - Reduce the amount of update requests sent to Collector when using Delivery module.
* Fix           - Fix validation callback error that could happen when switching between B2B & B2C checkout.
* Fix           - Save Collector payment meta data correctly on order creation triggered during checkout_error event. Could cause duplicate orders in Woo.
* Fix           - Tweak in order totals check (in callback from Collector to Woo) to avoid mismatch when there actually isn't one.

= 2020.11.17    - version 2.0.1 =
* Fix           - Fixed PHP warning in plugin_action_links filter.

= 2020.11.17    - version 2.0.0 =
* Feature       - Add support for Collector Delivery Module.
* Feature       - Add validation callback logic (as an optional feature). Checks for coupon validation, products in stock, user logged in (if needed) and order amount.
* Feature       - Add support for order management for collector_invoice payment method.
* Tweak         - Add separate db table to store data for validation callback handling.
* Tweak         - Use Action scheduler instead of WP cron for queuing notification callback handling.
* Tweak         - Add WC checkout form fields to Collector Checkout template + add email address to form during customer address update callback.
* Tweak         - Trigger change/blur after updating email address field. Adds support with MailChimp abandoned cart functionality.
* Tweak         - Run payment_complete process in process_payment (instead of woocommerce_thankyou).
* Tweak         - Use Collector paymentName instead of paymentMethod to store the payment mehtod used for the purchase.
* Tweak         - Add settings link on plugin page.
* Fix           - Updated depricated add_fee to be using add_item instead for invoice fee.
* Fix           - Remove double trigger of set_order_status function (could cause double order notes regarding "Payment via Collector Checkout...").

= 2020.05.05    - version 1.5.3 =
* Fix           - Correct amount is refunded when some order rows is partially and some completely refunded.

= 2020.04.01    - version 1.5.2 =
* Enhancement   - Collector 1.5.2 tested with WooCommerce 4.0.1.

= 2020.02.20    - version 1.5.1 =
* Fix           - Do not try to call WP function get_current_screen() if it hasn't been defined.

= 2020.02.19    - version 1.5.0 =
* Feature       - Added support for Swish as external payment method in the checkout.
* Tweak         - Trigger payment_complete() ofr Collector orders with the status of "Completed".
* Fix           - Delete sessions related to Collector on order received page, even when Collector isn't the selected payment gateway for the order.
* Fix           - Don't try to save Collector specific info to order if other payment method is used.

= 2020.01.30    - version 1.4.3 =
* Enhancement   - Saving shipping reference to order as post meta. (Support for refunds made on orders with "Table Rate Shipping" as the shipping).
* Fix           - Improved logic for when shipping gets created via API Callback.

= 2019.12.10  	- version 1.4.2 =
* Enhancement   - Added support for partial order line refunds on shipping and fee items.
* Fix           - Prevent function for changing to Collector Checkout payment method from running on the confirmation page. Caused an issue with Google Tag Manager for WordPress by Thomas Geiger.

= 2019.11.04  	- version 1.4.1 =
* Enhancement   - Added support for partial order line refunds.
* Enhancement   - Added support for WooCommerce Smart Coupons.

= 2019.08.14  	- version 1.4.0 =
* Feature       - Added support for English in swedish market and Danish (in Danish market, changed en-DK to da-DK).
* Tweak         - Set orders created via checkout_error to On Hold.
* Fix           - Improved handling/http status respons on fraud callback from Collector.

= 2019.05.08  	- version 1.3.3 =
* Fix           - Fix to use correct address data for logged in users on confirmation page.

= 2019.05.02  	- version 1.3.2 =
* Tweak         - Changed filter to wc_get_template for overriding checkout template.
* Fix           - Added check on update order call to prevent error response redirect to cart if purchase is already completed.

= 2019.03.05  	- version 1.3.1 =
* Tweak         - Improved callback logging.
* Tweak         - Added order total comparison (between Collector & WooCommerce) on fallback creation orders aswell.
* Fixed         - Save shipping address 2 correctly in order in WooCommerce.
* Fix           - Added checks to prevent creation of empty WooCommerce orders on confirmation page.

= 2019.02.19  	- version 1.3.0 =
* Feature       - Added support for changing background color of call to action buttons in Collector Checkout.
* Tweak         - Product title is now sent to Collector correctly for variable products.

= 2019.01.11  	- version 1.2.3 =
* Tweak			- Removed Collectors Instant purchase feature since it's being retired.

= 2018.12.27  	- version 1.2.2 =
* Fix			- Fixed error in communication with Collector when trying to refund product without SKU.
* Tweak			- Plugin WordPress 5.0 compatible.

= 2018.11.22  	- version 1.2.1 =
* Tweak			- Moved collector_wc_show_customer_order_notes & collector_wc_show_another_gateway_button to be displayed in the collector_wc_after_order_review hook.

= 2018.11.15  	- version 1.2.0 =
* Feature		- Added support for partial refunds.
* Feature		- Improved template handling. Template can now be overwritten by adding collector-checkout.php to themes woocommerce folder.
* Tweak			- Added hooks collector_wc_before_order_review & collector_wc_after_order_review to template file.
* Tweak			- Changed class names in html markup in template file.
* Tweak			- Change how order is created in WooCommerce to support enhanced e-commerce tracking via Google Analytics.
* Tweak			- Change ”Please wait while we process your order” so it is added as a modal above all content.
* Tweak			- Added events during Woo order creation to confirmation class.
* Tweak			- Updated Swedish translation.
* Tweak			- Code cleaning & PHP notice fixes.
* Fix			- Add support for orders with 100% dicsount and free shipping.

= 2018.09.13  	- version 1.1.2 =
* Tweak			- Created POT file + started Norwegian translation.
* Fix			- Create new Collector transaction id if response code != 200 on a update request.
* Fix			- Fixed missing text domain. Making Private / Company text in checkout translatable.

= 2018.08.14  	- version 1.1.1 =
* Fix 			- Save Shipping company name correctly in WooCommerce order.
* Fix			- Prevent duplicate orders if Collector confirmation page is reloaded manually by customer during create Woo order process.

= 2018.08.07  	- version 1.1.0 =
* Feature		- Added support for B2C B2B Finland.
* Feature		- Added support for B2C Denmark.
* Feature		- Added setting for displaying privacy policy text in checkout.
* Tweak			- Display response message in frontend if response code isn’t 200 on initialize checkout request.
* Tweak			- Improved http response for anti-fraud control (respons with 404 if Woo order hasn't been created yet).
* Tweak			- Improved error messaging/logging when request to Collector fails.
* Fix			- Maybe define constant WOOCOMMERCE_CHECKOUT in ajax functions.
* Fix			- Check if function get_current_screen() exist before tying to add collector invoice number to WC order number.


= 2018.05.17  	- version 1.0.0 =
* Feature		- Add support for B2B Norway.
* Feature		- Add support for wp_add_privacy_policy_content (for GDPR compliance). More info: https://core.trac.wordpress.org/attachment/ticket/43473/PRIVACY-POLICY-CONTENT-HOOK.md.
* Tweak			- Add order note in fallback order creation describing reason why checkout form submission failed.
* Tweak			- Save Collector field Invoice reference in WooCommerce order for B2B purchases.
* Tweak			- Added wc_collector_get_selected_customer_type() helper function. Used to display current selected customer type in frontend better.
* Fix			- Fixed Instant checkout for Norway.
* Fix			- Use order currency instead of store base currency in GET request to Collector after order is created in Woocommerce.
* Fix			- Store Collector paymentId as _transaction_id even for orders with status On hold.
* Fix			- Add invoice fee in fallback order creation process (if needed).

= 2018.04.19  	- version 0.9.4 =
* Fix			- Change how Collector order activation response is interpret so activations also works for part payment.
* Tweak         - Create new Collector session if currency is changed.

= 2018.04.09  	- version 0.9.3 =
* Fix			- Send WooCommerce fees correctly to Collector.

= 2018.03.21  	- version 0.9.2 =
* Fix			- Update cart in WC & Collector checkout on collectorCheckoutCustomerUpdated event.

= 2018.03.14  	- version 0.9.1 =
* Tweak         - Added collector_wc_before_checkout_form action hook in template file.
* Tweak         - Calculate totals before rendering the Collector Checkout template file.
* Fix           - Added closing scrip tag for Collector js file.
* Fix           - Remove PHP notice when Collector iframe doesn't load correctly.

= 2018.02.22  	- version 0.9.0 =
* Tweak         - Make initialize request to Collector before checkout page is rendered to avoid error/timeout and get a faster loading checkout.
* Tweak         - Improved logging.
* Tweak         - Avoid making update cart request directly after initialize request.
* Fix           - WC 3.3 session bug fix that caused orders not being created correctly in backup order creation (server-to-server).
* Fix           - Create new checkout session in WooCommerce & Collector if update fees/update cart has error.
* Fix           - Determine selected payment method on paymentName returned from Collector since paymentMethod has been debrecated.

= 2018.02.16  	- version 0.8.1 =
* Tweak			- Check order status in WooCommerce and compare order total with Collector on notification callback from Collector to avoid mismatch between the two.
* Tweak			- Improved logging in update cart & update fees request.
* Tweak			- Use order meta data instead of session data when making update reference request to Collector.

= 2018.02.05  	- version 0.8.0 =
* Feature		- Added Collector Status report to be able to count and display number of orders created via API callback.
* Tweak			- Added admin notice if price decimals is set to lower than 2.
* Tweak			- Check order totals between Collector and Woo on API callback order creation. Set order to On hold if mismatch is detected.
* Fix			- CSS change to display B2B/B2C radio button switcher correct.

= 2018.01.30  	- version 0.7.1 =
* Feature		- Automatically tie customer account to order if it exist in WC.
* Tweak			- Improved error logging for initialize checkout request.
* Tweak			- Display error message in checkout page when initialize checkout connection fails.
* Tweak			- Save Collector order meta data (private ID, customer type & public token) earlier.

= 2018.01.23  	- version 0.7.0 =
* Feature		- Added settings to be able to set default customer type (B2B/B2C).
* Feature		- Added support for B2B Part payment ”Signing” order status.
* Tweak			- Simplified backup order creation.
* Tweak			- Backup order creation schedule changed from 30 seconds to 2 minutes (after the notificationUri server callback).
* Tweak			- Query orders 1 month back when checking if backup order creation is needed (on API callback).
* Fix			- Changed spelling of Collector PartPayment status name (for translation purposes).
* Fix			- CSS change to display B2B/B2C radio button switcher correct.

= 2018.01.09  	- version 0.6.4 =
* Tweak			- Logging improvements.
* Tweak			- Settings page content updates (links to Collector terms and logotypes).
* Fix			- Prevent customer_adress_updated function from being executed on thankyou page (to avoid an unnecessary  request to Collector).

= 2018.01.05  	- version 0.6.3 =
* Fix 			- Fallback order creation improvements. Send customer to order received page when redirectPageUri is hit, even if we can’t confirm the order in Collectors system. If then, display a simplified thank you page.
* Fix 			- Logging improvement
* Fix 			- Remove errors when accessing the order received page without order being created.
* Fix 			- Unset Collector sessions correctly on order received page.
* Tweak 		- Increased timeout to 10 seconds when communicating with Collector.
* Tweak 		- Improved logging in anti fraud callback.
* Tweak 		- CSS update to set WC customer details section width the same as Collector iframe in order received page.

= 2018.01.03  	- version 0.6.2 =
* Fix			- Update order reference (WC order number) in Collector for orders created on fallback order creation.
* Tweak			- Support for Sequential order numbers & Sequential order numbers Pro in fallback order creation.
* Tweak			- Change page title (from Checkout to Please wait while we process your order) when processing WC order in checkout.
* Tweak			- Use returned purchase data for payment_id and payment_method instead of stored session data during WC order process.
* Fix			- Add user id to WC order on fallback order creation.
* Fix			- Limit fee id to max 50 characters when sending cart data to Collector.

= 2017.12.26  	- version 0.6.1 =
* Fix			- Moved check if class WC_Payment_Gateway exists before including files. Avoid errors during update process.

= 2017.12.22  	- version 0.6.0 =
* Tweak			- Backup order creation on notificationUri server callback from Collector (scheduled 30 seconds after purchase) if error occur in frontend.
* Tweak			- Only create new Collector public token when needed.
* Tweak			- If payment complete url is triggered and order don't have status PurchaseCompleted in Collectors system - redirect customer to checkout again.
* Fix			- Swap storing of billing and shipping addresses for B2B orders.

= 2017.12.12  	- version 0.5.1 =
* Tweak         - Add function for making all product id/sku’s unique before sending cart data to Collector.
* Fix           - Don’t try to add invoice fee to WC order if no invoice fee product exist (caused 500 error when finalizing order in WC).

= 2017.12.07  	- version 0.5.0 =
* Tweak         - Adds support for create order fallback on failed checkout form submissions.
* Fix           - Change notificationUri to use get_home_url() to support callbacks for WP installed in a subfolder.

= 2017.11.24  	- version 0.4.1 =
* Fix			- Send product variant ID as SKU if no SKU is set in WooCommerce. WC function get_sku() returns main product SKU if on one exists in variant. This causes error in Collector Checkout.

= 2017.11.23  	- version 0.4.0 =
* Tweak			- Improved handling of returned customer data to avoid issues when submitting form/creating order in WooCommerce.
* Tweak			- Send email to admin if customer address is missing from returned address data from Collector.
* Tweak			- Store org no as a separate post meta field and display it in WooCommerce order overview.
* Fix			- Improved check if Collector is the selected gateway (for correct body class).


= 2017.11.07  	- version 0.3.4 =
* Fix			- Prevent order status to be changed to On hold if thankyou page is reloaded and Collector sessions aren't deleted properly.
* Tweak			- Store public token in order to be used when displaying Collector Checkout iframe on thankyou page.

= 2017.11.06  	- version 0.3.3 =
* Fix			- Round shipping cost to only send it to Collector with 2 decimals.
* Fix           - Added support for anonymous card purchases.
* Fix           - Added collector body class on page load if collector is the default gateway.
* Fix           - Fixed variable product SKU being incorrect.
* Fix           - Added error message on checkout page if get public token fails.

= 2017.10.17  	- version 0.3.2 =
* Fix			- Fixed how we detect if we are on thank you page.
* Fix			- Removed default value from JS for IE compatability.


= 2017.10.03  	- version 0.3.1 =
* Tweak			- Added check to only run order management on Collector orders.
* Fix			- Fixed so checkout wouldn't be duplicated when switching between B2B & B2C.

= 2017.09.11  	- version 0.3.0 =
* First release on wordpress.org.
