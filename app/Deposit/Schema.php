<?php
/**
 * Database schema for the Deposit Planning module.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Deposit;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the deposits table (one current deposit record per lead).
 */
class Schema {

	/**
	 * Fully-qualified deposits table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_deposits';
	}

	/**
	 * Create or upgrade the deposits table.
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
			required_amount decimal(15,2) DEFAULT NULL,
			paid_amount decimal(15,2) DEFAULT NULL,
			payment_date date DEFAULT NULL,
			payment_method varchar(20) NOT NULL DEFAULT '',
			receipt_number varchar(100) NOT NULL DEFAULT '',
			proof_id bigint(20) unsigned NOT NULL DEFAULT 0,
			proof_url varchar(255) NOT NULL DEFAULT '',
			status varchar(12) NOT NULL DEFAULT 'pending',
			rejection_reason varchar(255) NOT NULL DEFAULT '',
			is_override tinyint(1) NOT NULL DEFAULT 0,
			override_reason varchar(255) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			verified_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			verified_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop the deposits table. Used only during opt-in uninstall.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::table();

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
