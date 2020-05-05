=== Collector Checkout for WooCommerce ===
Contributors: collectorbank, krokedil, NiklasHogefjord
Tags: ecommerce, e-commerce, woocommerce, collector, checkout
Requires at least: 4.7
Tested up to: 5.4.1
Requires PHP: 5.6
Stable tag: trunk
WC requires at least: 3.0.0
WC tested up to: 4.0.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html


== DESCRIPTION ==
Collector Checkout for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via Collector Banks payment method Collector Checkout.

= Get started =
To get started with Collector Checkout you need to [sign up](https://www.collector.se/foretag/betallosningar/kontakta-oss/offert/) for an account.

More information on how to get started can be found in the [plugin documentation](http://docs.krokedil.com/documentation/collector-checkout-for-woocommerce/).


== INSTALLATION	 ==
1. Download the latest release zip file or install it directly via the plugins menu in WordPress Administration.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Unzip and upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your Collector Checkout settings.
6. Read more about the configuration process in the [plugin documentation](http://docs.krokedil.com/documentation/collector-checkout-for-woocommerce/).


== Frequently Asked Questions ==
= Which countries does this payment gateway support? =
Collector Checkout is available for merchants and customers in Sweden (B2C & B2B), Norway (B2C & B2B), Finland (B2C & B2B) & Denmark (B2C).

= Where can I find Collector Checkout for WooCommerce documentation? =
For help setting up and configuring Collector Checkout for WooCommerce please refer to our [documentation](http://docs.krokedil.com/documentation/collector-checkout-for-woocommerce/).



== CHANGELOG ==
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