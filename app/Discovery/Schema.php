<?php
/**
 * Database schema for the Discovery / Survey module.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Discovery;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the discoveries table (one row per discovery session).
 */
class Schema {

	/**
	 * Fully-qualified discoveries table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_discoveries';
	}

	/**
	 * Create or upgrade the discoveries table.
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
			type varchar(20) NOT NULL DEFAULT 'call',
			status varchar(12) NOT NULL DEFAULT 'scheduled',
			scheduled_at datetime DEFAULT NULL,
			requirement_summary text NOT NULL,
			survey_summary text NOT NULL,
			pain_points text NOT NULL,
			client_expectation text NOT NULL,
			recommended_next_step text NOT NULL,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			attachment_url varchar(255) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop the discoveries table. Used only during opt-in uninstall.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::table();

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
