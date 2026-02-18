<?php
/**
 * Class file for the Walley Checkout settings class.
 *
 * @package Collector_Checkout/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Walley Checkout Settings
 */
class Walley_Checkout_Settings {

	/**
	 * Static instance of the settings from the database.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $settings = null;

	/**
	 * Load the settings from the database and set to the static variable.
	 *
	 * @return void
	 */
	public static function load_settings() {
		self::$settings = get_option( 'woocommerce_collector_checkout_settings', array() );
	}

	/**
	 * Get the settings from the database.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		if ( null === self::$settings ) {
			self::load_settings();
		}
		return self::$settings;
	}

	/**
	 * Get the country code to use for settings for the currency and customer type.
	 *
	 * @param string $currency The currency.
	 * @param string $customer_type The customer type.
	 *
	 * @return string
	 */
	public static function get_country_code( $currency, $customer_type ) {
		// Normalize the inputs.
		$currency      = strtoupper( $currency );
		$customer_type = strtolower( $customer_type );

		$country_code = 'se';

		switch ( $currency ) {
			case 'SEK':
				$country_code = 'se';
				break;
			case 'NOK':
				$country_code = 'no';
				break;
			case 'DKK':
				$country_code = 'dk';
				break;
			case 'EUR':
				// If the customer type is b2b, always use FI since EU b2b is not supported.
				if ( 'b2b' === $customer_type ) {
					$country_code = 'fi';
					break;
				}

				$euro_country = walley_get_eur_country();
				// If the country code for Euro is FI, and we have settings credentials for FI, use that.
				if ( 'fi' === $euro_country && ! empty( self::get_merchant_id( 'fi', $customer_type ) ) ) {
					$country_code = 'fi';
					break;
				}

				// Otherwise use EU.
				$country_code = 'eu';
				break;
			default:
				$country_code = 'se';
				break;
		}

		return apply_filters( 'walley_get_country_code', $country_code, $currency, $customer_type );
	}

	/**
	 * Get the merchant id for the country code and customer type.
	 *
	 * @param string $country_code The country code.
	 *
	 * @return string
	 */
	public static function get_merchant_id( $country_code, $customer_type ) {
		// Normalize the inputs.
		$country_code  = strtolower( $country_code );
		$customer_type = strtolower( $customer_type );

		// If the country code is EU and customer type is B2B, return the b2c credentials instead.
		if ( 'eu' === $country_code && 'b2b' === $customer_type ) {
			$customer_type = 'b2c';
		}

		$settings        = self::get_settings();
		$merchant_id_key = "collector_merchant_id_{$country_code}_{$customer_type}";
		return ! empty( $settings[ $merchant_id_key ] ) ? sanitize_text_field( $settings[ $merchant_id_key ] ) : '';
	}

	/**
	 * Get the delivery module for the country code.
	 *
	 * @param string $country_code The country code.
	 *
	 * @return string
	 */
	public static function get_delivery_module( $country_code ) {
		// Normalize the input.
		$country_code = strtolower( $country_code );

		$settings            = self::get_settings();
		$delivery_module_key = "collector_delivery_module_$country_code";
		return ! empty( $settings[ $delivery_module_key ] ) ? sanitize_text_field( $settings[ $delivery_module_key ] ) : '';
	}

	/**
	 * Get the profile for the country code.
	 *
	 * @param string $country_code The country code.
	 *
	 * @return string
	 */
	public static function get_profile( $country_code ) {
		// Normalize the input.
		$country_code = strtolower( $country_code );

		$settings    = self::get_settings();
		$profile_key = "collector_profile_{$country_code}";
		return ! empty( $settings[ $profile_key ] ) ? sanitize_text_field( $settings[ $profile_key ] ) : '';
	}

	/**
	 * Get the profile to use for the given country for the checkout page.
	 *
	 * @param string $country_code The country code.
	 *
	 * @return string
	 */
	public static function get_checkout_profile( $country_code ) {
		// Normalize the input.
		$country_code = strtolower( $country_code );

		$is_subscription = Walley_Subscription::cart_has_subscription();
		$checkout_profile = '';

		$profile         = self::get_profile( $country_code );
		$delivery_module = self::get_delivery_module( $country_code );

		// If we have a profile set for the country, use that.
		if ( ! empty( $profile ) ) {
			$checkout_profile = $profile;
		} elseif ( WC()->cart->needs_shipping() && ! empty( $delivery_module ) ) {
			// If no profile is set, but a delivery module is set and the cart needs shipping, use that.
			$checkout_profile = $delivery_module;
		}

		// If it's a subscription, append '-Recurring' to the profile if it has a value, otherwise use Recurring directly.
		if ( $is_subscription ) {
			if ( ! empty( $checkout_profile ) ) {
				$checkout_profile .= '-Recurring';
			} else {
				$checkout_profile = 'Recurring';
			}
		}

		return $checkout_profile;
	}

	/**
	 * Get the settings form fields array for the WooCommerce settings API.
	 *
	 * @return array
	 */
	public static function get_setting_fields() {
		$form_fields = include COLLECTOR_BANK_PLUGIN_DIR . '/includes/collector-checkout-settings.php';
		return Walley_Checkout_Settings::add_country_form_fields( $form_fields );
	}

	/**
	 * Get a list of available walley countries and the settings that are available for them.
	 *
	 * @return array<string, array>{
	 *   @type string $name     The localized country name.
	 *   @type bool   $b2c      Whether B2C is enabled.
	 *   @type bool   $b2b      Whether B2B is enabled.
	 *   @type bool   $profile  Whether profiles are enabled.
	 * }
	 */
	public static function get_walley_countries() {
		return array(
			'se' => array(
				'name'            => __( 'Sweden', 'collector-checkout-for-woocommerce' ),
				'b2c'             => true,
				'b2b'             => true,
				'profile'         => true,
				'delivery_module' => true,
			),
			'no' => array(
				'name'            => __( 'Norway', 'collector-checkout-for-woocommerce' ),
				'b2c'             => true,
				'b2b'             => true,
				'profile'         => true,
				'delivery_module' => true,
			),
			'fi' => array(
				'name'            => __( 'Finland', 'collector-checkout-for-woocommerce' ),
				'b2c'             => true,
				'b2b'             => true,
				'profile'         => true,
				'delivery_module' => true,
			),
			'dk' => array(
				'name'            => __( 'Denmark', 'collector-checkout-for-woocommerce' ),
				'b2c'             => true,
				'b2b'             => true,
				'profile'         => true,
				'delivery_module' => true,
			),
			'eu' => array(
				'name'            => __( 'EU', 'collector-checkout-for-woocommerce' ),
				'b2c'             => true,
				'b2b'             => false,
				'profile'         => true,
				'delivery_module' => true,
			),
		);
	}

	/**
	 * Add country form fields to the Walley checkout settings.
	 *
	 * @param array $settings The settings from the settings file.
	 *
	 * @return array
	 */
	public static function add_country_form_fields( $settings ) {
		$countries = self::get_walley_countries();

		$country_settings = array();
		foreach ( $countries as $country_code => $params ) {
			$country_settings[] = self::get_country_form_fields( $country_code, $params );
		}

		// Add the settings after the 'display_privacy_policy_text' key in the settings array.
		$position = 0;
		foreach ( $settings as $key => $setting ) {
			if ( 'display_privacy_policy_text' === $key ) {
				$position++;
				break;
			}
			$position++;
		}

		return array_merge(
			array_slice( $settings, 0, $position, true ),
			call_user_func_array( 'array_merge', $country_settings ),
			array_slice( $settings, $position, null, true )
		);
	}

	/**
	 * Get the form fields for a specific country.
	 *
	 * @param string $country_code The country code.
	 * @param array  $params       {
	 *   @type string $name            The country name.
	 *   @type bool   $b2c             Whether B2C is enabled.
	 *   @type bool   $b2b             Whether B2B is enabled.
	 *   @type bool   $profile         Whether profiles are enabled.
	 *   @type bool   $delivery_module Whether delivery modules are enabled.
	 * }
	 *
	 * @return array
	 */
	private static function get_country_form_fields( $country_code, $params ) {
		$country_fields = array();
		self::add_country_section_form_field( $country_code, $params['name'], $country_fields );

		// If B2C is enabled for the country, add the B2C merchant ID field.
		if ( $params['b2c'] ) {
			self::add_country_merchant_id_form_field( $country_code, $params['name'], 'b2c', $country_fields );
		}

		// If B2B is enabled for the country, add the B2B merchant ID field.
		if ( $params['b2b'] ) {
			self::add_country_merchant_id_form_field( $country_code, $params['name'], 'b2b', $country_fields );
		}

		// If profiles are enabled for the country, add the profile field.
		if ( $params['profile'] ) {
			self::add_country_profile_form_field( $country_code, $params['name'], $country_fields );
		}

		// If delivery modules are enabled for the country, add the delivery module field.
		if ( $params['delivery_module'] ) {
			self::add_country_delivery_module_form_field( $country_code, $params['name'], $country_fields );
		}

		return $country_fields;
	}

	/**
	 * Add the section for the country form fields.
	 *
	 * @param string $country_code     The country code.
	 * @param string $country_name     The localized country name.
	 * @param array  $country_settings The settings array.
	 *
	 * @return void
	 */
	private static function add_country_section_form_field( $country_code, $country_name, &$country_settings ) {
		$title_key = "{$country_code}_settings_title";
		$country_settings[ $title_key ] = array(
			'title' => $country_name,
			'type'  => 'title',
		);
	}

	/**
	 * Add the country merchant id form fields.
	 *
	 * @param string $country_code The country code.
	 * @param string $country_name The localized country name.
	 * @param string $type         The type, either 'b2c' or 'b2b'.
	 * @param array  $country_settings The settings array.
	 *
	 * @return void
	 */
	private static function add_country_merchant_id_form_field( $country_code, $country_name, $type, &$country_settings ) {
		$label = sprintf(
			/* translators: 1: Country name. 2: Type (B2C/B2B). */
			__( 'Merchant ID %1$s %2$s', 'collector-checkout-for-woocommerce' ),
			$country_name,
			strtoupper( $type )
		);
		$description = sprintf(
			/* translators: 1: Country name. 2: Type (B2C/B2B). */
			__( 'Enter your Walley Checkout Merchant ID for %2$s purchases in %1$s', 'collector-checkout-for-woocommerce' ),
			$country_name,
			strtoupper( $type )
		);
		$setting_key = "collector_merchant_id_{$country_code}_{$type}";
		$country_settings[ $setting_key ] = array(
			'title'       => $label,
			'type'        => 'text',
			'description' => $description,
			'default'     => '',
			'desc_tip'    => true,
		);
	}

	/**
	 * Add the country profile form fields.
	 *
	 * @param string $country_code The country code.
	 * @param string $country_name The localized country name.
	 * @param array  $country_settings The settings array.
	 *
	 * @return void
	 */
	private static function add_country_profile_form_field( $country_code, $country_name, &$country_settings ) {
		$label = sprintf(
			/* translators: 1: Country name. */
			__( 'Custom Profile %s', 'collector-checkout-for-woocommerce' ),
			$country_name
		);

		$setting_key                      = "collector_profile_{$country_code}";
		$country_settings[ $setting_key ] = array(
			'title'       => $label,
			'type'        => 'text',
			'description' => __( 'Enter custom profile name or leave empty for no profile.', 'collector-checkout-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		);
	}

	/**
	 * Add the country delivery module form fields.
	 *
	 * @param string $country_code The country code.
	 * @param string $country_name The localized country name.
	 * @param array  $country_settings The settings array.
	 *
	 * @return void
	 */
	public static function add_country_delivery_module_form_field( $country_code, $country_name, &$country_settings ) {
		$label = sprintf(
			/* translators: 1: Country name. */
			__( 'Delivery Module %s', 'collector-checkout-for-woocommerce' ),
			$country_name
		);

		$modules = array(
			''                  => __( 'None', 'collector-checkout-for-woocommerce' ),
			'shipping' => 'nShift Delivery',
			'Redlight' => 'Redlight shipping',
		);

		$setting_key = "collector_delivery_module_{$country_code}";
		$country_settings[ $setting_key ] = array(
			'title'       => $label,
			'type'        => 'select',
			'options'     => $modules,
			'default'     => '',
			'desc_tip'    => true,
		);
	}
}
