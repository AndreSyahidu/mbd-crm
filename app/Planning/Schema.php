<?php
/**
 * Database schema for the Planning Tracking module.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Planning;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the plannings, deliverables, and revisions tables.
 */
class Schema {

	/**
	 * Plannings table name.
	 *
	 * @return string
	 */
	public static function plannings_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_plannings';
	}

	/**
	 * Deliverables table name.
	 *
	 * @return string
	 */
	public static function deliverables_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_deliverables';
	}

	/**
	 * Revisions table name.
	 *
	 * @return string
	 */
	public static function revisions_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_revisions';
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
		$plannings       = self::plannings_table();
		$deliverables    = self::deliverables_table();
		$revisions       = self::revisions_table();

		$sql   = array();
		$sql[] = "CREATE TABLE {$plannings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			planner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			scope text NOT NULL,
			target_date date DEFAULT NULL,
			status varchar(12) NOT NULL DEFAULT 'draft',
			internal_review varchar(12) NOT NULL DEFAULT 'pending',
			sla_due datetime DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$deliverables} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			planning_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(30) NOT NULL DEFAULT 'other',
			title varchar(191) NOT NULL DEFAULT '',
			version smallint(5) unsigned NOT NULL DEFAULT 1,
			notes text NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY planning_id (planning_id),
			KEY lead_id (lead_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$revisions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			planning_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			deliverable_id bigint(20) unsigned NOT NULL DEFAULT 0,
			note text NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY planning_id (planning_id)
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

		foreach ( array( self::plannings_table(), self::deliverables_table(), self::revisions_table() ) as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		}
	}
}
