<?php
/**
 * Database schema for the Client Approval Evidence module.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Approval;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the approvals table (client approval evidence records).
 */
class Schema {

	/**
	 * Approvals table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_approvals';
	}

	/**
	 * Create or upgrade the approvals table.
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
			evidence_type varchar(30) NOT NULL DEFAULT 'other',
			evidence_id bigint(20) unsigned NOT NULL DEFAULT 0,
			evidence_url varchar(255) NOT NULL DEFAULT '',
			approval_note text NOT NULL,
			client_name varchar(191) NOT NULL DEFAULT '',
			approved_date date DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop the approvals table. Used only during opt-in uninstall.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::table();

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
