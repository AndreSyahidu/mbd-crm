<?php
/**
 * Database schema for the Follow-Up & Promise module.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\FollowUp;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the follow-up log and promise tables.
 */
class Schema {

	/**
	 * Fully-qualified follow-ups table name.
	 *
	 * @return string
	 */
	public static function followups_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_followups';
	}

	/**
	 * Fully-qualified promises table name.
	 *
	 * @return string
	 */
	public static function promises_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_promises';
	}

	/**
	 * Create or upgrade the module's tables.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$followups       = self::followups_table();
		$promises        = self::promises_table();

		$sql   = array();
		$sql[] = "CREATE TABLE {$followups} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			channel varchar(20) NOT NULL DEFAULT 'other',
			summary text NOT NULL,
			result text NOT NULL,
			next_action varchar(255) NOT NULL DEFAULT '',
			next_follow_up date DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$promises} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			followup_id bigint(20) unsigned NOT NULL DEFAULT 0,
			description varchar(255) NOT NULL DEFAULT '',
			due_date date DEFAULT NULL,
			status varchar(12) NOT NULL DEFAULT 'open',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY status (status)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Drop the module's tables. Used only during opt-in uninstall.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		foreach ( array( self::followups_table(), self::promises_table() ) as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		}
	}
}
