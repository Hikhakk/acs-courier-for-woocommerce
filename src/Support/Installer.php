<?php
/**
 * Creates and maintains the plugin's database table.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Support;

/**
 * Creates and maintains the plugin's database table.
 *
 * Pickup points live in their own table rather than an option: there are around
 * 1,600 of them, and an autoloaded option that size would be a site-wide
 * performance regression on every request.
 */
final class Installer {

	/**
	 * Schema version, bumped whenever the table changes.
	 */
	public const DB_VERSION = '1';

	/**
	 * Option holding the installed schema version.
	 */
	public const DB_VERSION_OPTION = 'acs_wc_db_version';

	/**
	 * Returns the pickup point table name.
	 *
	 * @return string
	 */
	public static function tableName(): string {
		global $wpdb;
		return $wpdb->prefix . 'acs_wc_pickup_points';
	}

	/**
	 * Creates or updates the schema when the version has changed.
	 *
	 * @return void
	 */
	public static function maybeInstall(): void {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::tableName();
		$collate = $wpdb->get_charset_collate();

		// Indexed on the columns the checkout selector actually filters by.
		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			point_id VARCHAR(32) NOT NULL,
			station_id VARCHAR(16) NOT NULL,
			branch_id INT NOT NULL DEFAULT 0,
			country CHAR(2) NOT NULL,
			kind SMALLINT NOT NULL DEFAULT 0,
			name VARCHAR(255) NOT NULL DEFAULT '',
			address VARCHAR(255) NOT NULL DEFAULT '',
			postcode VARCHAR(16) NOT NULL DEFAULT '',
			hours VARCHAR(128) NOT NULL DEFAULT '',
			lat DECIMAL(10,7) NULL,
			lng DECIMAL(10,7) NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY point_id (point_id),
			KEY country_kind (country, kind),
			KEY country_postcode (country, postcode)
		) {$collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}
}
