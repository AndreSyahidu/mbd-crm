<?php
/**
 * Database schema for the Closing and Negotiation module.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Closing;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the closings and negotiations tables.
 */
class Schema {

	/**
	 * Closings table name.
	 *
	 * @return string
	 */
	public static function closings_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_closings';
	}

	/**
	 * Negotiations table name.
	 *
	 * @return string
	 */
	public static function negotiations_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_negotiations';
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
		$closings        = self::closings_table();
		$negotiations    = self::negotiations_table();

		$sql   = array();
		$sql[] = "CREATE TABLE {$closings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'open',
			proposal_sent tinyint(1) NOT NULL DEFAULT 0,
			proposal_sent_at datetime DEFAULT NULL,
			estimated_value decimal(15,2) DEFAULT NULL,
			offered_value decimal(15,2) DEFAULT NULL,
			final_value decimal(15,2) DEFAULT NULL,
			probability smallint(5) unsigned NOT NULL DEFAULT 0,
			expected_close_date date DEFAULT NULL,
			next_action varchar(255) NOT NULL DEFAULT '',
			competitor_note text NOT NULL,
			lost_reason varchar(255) NOT NULL DEFAULT '',
			project_draft tinyint(1) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$negotiations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			closing_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			entry_type varchar(12) NOT NULL DEFAULT 'note',
			content text NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY closing_id (closing_id),
			KEY lead_id (lead_id)
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

		foreach ( array( self::closings_table(), self::negotiations_table() ) as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		}
	}
}
