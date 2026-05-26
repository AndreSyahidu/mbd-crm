<?php
/**
 * Database schema for project handoffs.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Handoff;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the project-handoff table — the CRM-to-delivery bridge created when a
 * deal is won.
 */
class Schema {

	/**
	 * Handoffs table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_handoffs';
	}

	/**
	 * Create or upgrade the handoffs table.
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
			closing_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(16) NOT NULL DEFAULT 'draft',
			checklist longtext NOT NULL,
			final_value decimal(15,2) NOT NULL DEFAULT 0,
			scope text NOT NULL,
			pic_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			handed_off_at datetime DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop the handoffs table. Used only during opt-in uninstall.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::table();

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
