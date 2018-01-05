=== Collector Checkout for WooCommerce ===
Contributors: collectorbank, krokedil, NiklasHogefjord
Tags: ecommerce, e-commerce, woocommerce, collector, checkout
Requires at least: 4.7
Tested up to: 4.9.1
Requires PHP: 5.6
Stable tag: trunk
Requires WooCommerce at least: 3.0
Tested WooCommerce up to: 3.2.6
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
At the moment it's only available for merchants in Sweden. Norway will be added during autumn 2017.

= Where can I find Collector Checkout for WooCommerce documentation? =
For help setting up and configuring Collector Checkout for WooCommerce please refer to our [documentation](http://docs.krokedil.com/documentation/collector-checkout-for-woocommerce/).



== CHANGELOG ==
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