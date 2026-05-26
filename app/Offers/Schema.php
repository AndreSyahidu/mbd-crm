<?php
/**
 * Database schema for versioned offers.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Offers;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the offers table (immutable, versioned commercial offers per lead).
 */
class Schema {

	/**
	 * Offers table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_offers';
	}

	/**
	 * Create or upgrade the offers table.
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
			version smallint(5) unsigned NOT NULL DEFAULT 1,
			base_price decimal(15,2) NOT NULL DEFAULT 0,
			discount_type varchar(8) NOT NULL DEFAULT 'amount',
			discount_value decimal(15,2) NOT NULL DEFAULT 0,
			discount_percent decimal(6,2) NOT NULL DEFAULT 0,
			final_value decimal(15,2) NOT NULL DEFAULT 0,
			valid_until date DEFAULT NULL,
			scope text NOT NULL,
			status varchar(16) NOT NULL DEFAULT 'draft',
			approval_required tinyint(1) NOT NULL DEFAULT 0,
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			approved_at datetime DEFAULT NULL,
			decision_reason varchar(255) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY closing_id (closing_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop the offers table. Used only during opt-in uninstall.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::table();

		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
