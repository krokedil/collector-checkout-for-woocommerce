<?php
/**
 * Class for handling the Database table
 *
 * @package collector-checkout-for-woocommerce/Classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for handling the Database table
 */
class Collector_Checkout_DB {
	/**
	 * The name of the database table.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private static $table_name = 'collector_data';

	/**
	 * Setup table.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function setup_table() {
		global $wpdb;
		$prefix     = $wpdb->prefix;
		$table_name = $prefix . self::$table_name;

		if ( ! self::create_table( $table_name ) ) {
			return false;
		}
		// Using this option in main plugin file to create db table or not.
		update_option( 'collector_db_version', COLLECTOR_DB_VERSION );

		return true;
	}

	/**
	 * Removes the database table.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function remove_table() {
		global $wpdb;
		$prefix     = $wpdb->prefix;
		$table_name = $prefix . self::$table_name;
		if ( self::table_exists( $table_name ) ) {
            return $wpdb->query( 'DROP TABLE IF EXISTS ' . $table_name ); //phpcs:ignore
		}
		delete_option( 'collector_db_version' );
	}


	/**
	 * Checks if the table exists in the database already.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table_name The name of the table.
	 * @return bool
	 */
	private static function table_exists( $table_name ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) === $table_name;//phpcs:ignore
	}

	/**
	 * Creates the table in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table_name The name of the new table.
	 * @return bool
	 */
	private static function create_table( $table_name ) {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			`id` VARCHAR(128) NOT NULL,
			`data` MEDIUMTEXT NOT NULL,
			`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return true;
	}

	/**
	 * Creates a data entry for the table.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args The data for the row.
	 * @return int
	 */
	public static function create_data_entry( $args = array() ) {
		if ( empty( $args ) || ! is_array( $args ) ) {
			return false;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . self::$table_name;

		$wpdb->insert( // phpcs:ignore
			$table_name,
			array(
				'id'         => $args['private_id'],
				'data'       => wp_json_encode( $args['data'] ),
				'created_at' => gmdate( 'Y-m-d H:i:s', time() ),
			),
			array(
				'%s',
			)
		);

		return $wpdb->insert_id;
	}

	/**
	 * Gets the data entry for the id.
	 *
	 * @since 1.0.0
	 *
	 * @param string $data_id The database row id.
	 * @return object
	 */
	public static function get_data_entry( $data_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		$query      = $wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE `id` = %s LIMIT 1", $data_id ); //phpcs:ignore
		$data       = $wpdb->get_results( $query ); // phpcs:ignore
		if ( empty( $data ) ) {
			return null;
		} else {
			return json_decode( $data[0]->data );
		}
	}

	/**
	 * Deletes one week old data entry.
	 *
	 * @param string $current_date Current date.
	 * @return void
	 */
	public static function delete_old_data_entry( $current_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		$query      = $wpdb->prepare( "DELETE FROM `{$table_name}` WHERE DATEDIFF(%s, `created_at`) >= 7", $current_date ); //phpcs:ignore
		$data       = $wpdb->get_results( $query ); // phpcs:ignore
	}

	/**
	 * Deletes the data entry for the id.
	 *
	 * @since 1.0.0
	 *
	 * @param string $data_id The database row id.
	 * @return void
	 */
	public static function delete_data_entry( $data_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		$query      = $wpdb->prepare( "DELETE FROM `{$table_name}` WHERE `id` = %s LIMIT 1", $data_id ); //phpcs:ignore
		$data       = $wpdb->get_results( $query ); // phpcs:ignore
		// TODO maybe add return statement and return $data value.
	}


	/**
	 * Updates the data column for the data entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args The data.
	 * @return void
	 */
	public static function update_data( $args ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::$table_name;
		$wpdb->update( // phpcs:ignore
			$table_name,
			array( 'data' => wp_json_encode( $args['data'] ) ),
			array( 'id' => $args['private_id'] )
		);
	}
}
