<?php
/**
 * Database schema for the Qualification module.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Qualification;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the qualifications table, which stores one row per decision so the
 * full qualification history and snapshots are retained.
 */
class Schema {

	/**
	 * Fully-qualified qualifications table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_qualifications';
	}

	/**
	 * Create or upgrade the qualifications table.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::table();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			decision varchar(20) NOT NULL DEFAULT '',
			score smallint(5) unsigned NOT NULL DEFAULT 0,
			fit_checks longtext NOT NULL,
			reason varchar(255) NOT NULL DEFAULT '',
			snapshot longtext NOT NULL,
			decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
			decided_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY decision (decision)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop the qualifications table. Used only during opt-in uninstall.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::table();

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
