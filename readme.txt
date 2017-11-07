=== Collector Checkout for WooCommerce ===
Contributors: collectorbank, krokedil, NiklasHogefjord
Tags: ecommerce, e-commerce, woocommerce, collector, checkout
Requires at least: 4.7
Tested up to: 4.8.3
Requires PHP: 5.6
Stable tag: trunk
Requires WooCommerce at least: 3.0
Tested WooCommerce up to: 3.2.3
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