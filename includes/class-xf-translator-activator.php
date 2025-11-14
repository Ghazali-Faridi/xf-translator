<?php

/**
 * Fired during plugin activation
 *
 * @link       https://xfinitive.co
 * @since      1.0.0
 *
 * @package    Xf_Translator
 * @subpackage Xf_Translator/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Xf_Translator
 * @subpackage Xf_Translator/includes
 * @author     ghazali <shafe_ghazali@xfinitive.co>
 */
class Xf_Translator_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'xf_translate_queue';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			parent_post_id bigint(20) NOT NULL,
			translated_post_id bigint(20) DEFAULT NULL,
			lng varchar(100) NOT NULL,
			status varchar(20) DEFAULT 'pending',
			type varchar(20) DEFAULT 'NEW',
			error_message text DEFAULT NULL,
			edited_fields text DEFAULT NULL,
			created datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY parent_post_id (parent_post_id),
			KEY translated_post_id (translated_post_id),
			KEY lng (lng),
			KEY status (status),
			KEY type (type)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		
		// Add type column if it doesn't exist (for existing installations)
		$column_exists = $wpdb->get_results( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'type'",
			DB_NAME,
			$table_name
		) );
		
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_name ADD COLUMN type varchar(20) DEFAULT 'NEW' AFTER status" );
			$wpdb->query( "ALTER TABLE $table_name ADD INDEX type (type)" );
		}
		
		// Update lng column size if it's still varchar(10) to accommodate language names
		$lng_column = $wpdb->get_row( $wpdb->prepare(
			"SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'lng'",
			DB_NAME,
			$table_name
		) );
		
		if ( $lng_column && isset( $lng_column->COLUMN_TYPE ) && strpos( $lng_column->COLUMN_TYPE, 'varchar(10)' ) !== false ) {
			$wpdb->query( "ALTER TABLE $table_name MODIFY COLUMN lng varchar(100) NOT NULL" );
		}
		
		// Add error_message column if it doesn't exist (for existing installations)
		$error_column_exists = $wpdb->get_row( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'error_message'",
			DB_NAME,
			$table_name
		) );
		
		if ( empty( $error_column_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_name ADD COLUMN error_message text DEFAULT NULL AFTER type" );
		}
		
		// Add edited_fields column if it doesn't exist (for existing installations)
		$edited_fields_column_exists = $wpdb->get_row( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'edited_fields'",
			DB_NAME,
			$table_name
		) );
		
		if ( empty( $edited_fields_column_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_name ADD COLUMN edited_fields text DEFAULT NULL AFTER error_message" );
		}
	}

}
