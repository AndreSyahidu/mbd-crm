<?php
/**
 * Database schema for stakeholder mapping.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Stakeholders;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the stakeholders table (decision makers / influencers per lead).
 */
class Schema {

	/**
	 * Stakeholders table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_stakeholders';
	}

	/**
	 * Create or upgrade the stakeholders table.
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
			name varchar(191) NOT NULL DEFAULT '',
			role varchar(30) NOT NULL DEFAULT 'other',
			phone varchar(32) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			decision_power varchar(10) NOT NULL DEFAULT 'unknown',
			relationship_note varchar(255) NOT NULL DEFAULT '',
			is_primary_decision_maker tinyint(1) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop the stakeholders table. Used only during opt-in uninstall.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::table();

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
