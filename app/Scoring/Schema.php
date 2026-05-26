<?php
/**
 * Database schema for lead-score history.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Scoring;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the lead score-history table.
 */
class Schema {

	/**
	 * Score-history table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_lead_score_history';
	}

	/**
	 * Create or upgrade the score-history table.
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
			old_score smallint(5) unsigned NOT NULL DEFAULT 0,
			new_score smallint(5) unsigned NOT NULL DEFAULT 0,
			old_temperature varchar(12) NOT NULL DEFAULT '',
			new_temperature varchar(12) NOT NULL DEFAULT '',
			reason varchar(255) NOT NULL DEFAULT '',
			calculated_by varchar(10) NOT NULL DEFAULT 'system',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop the score-history table. Used only during opt-in uninstall.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::table();

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
