<?php
/**
 * Database schema for the Lead Intake module.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the custom tables: leads, tasks, and the audit log.
 */
class Schema {

	/**
	 * Fully-qualified leads table name.
	 *
	 * @return string
	 */
	public static function leads_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_leads';
	}

	/**
	 * Fully-qualified tasks table name.
	 *
	 * @return string
	 */
	public static function tasks_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_tasks';
	}

	/**
	 * Fully-qualified audit table name.
	 *
	 * @return string
	 */
	public static function audit_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_audit';
	}

	/**
	 * Create or upgrade the module's tables via dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$leads           = self::leads_table();
		$tasks           = self::tasks_table();
		$audit           = self::audit_table();

		$sql = array();

		$sql[] = "CREATE TABLE {$leads} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			whatsapp varchar(32) NOT NULL DEFAULT '',
			whatsapp_normalized varchar(32) NOT NULL DEFAULT '',
			source varchar(50) NOT NULL DEFAULT '',
			project_type varchar(50) NOT NULL DEFAULT '',
			service_type varchar(50) NOT NULL DEFAULT '',
			project_location varchar(191) NOT NULL DEFAULT '',
			estimated_budget decimal(15,2) DEFAULT NULL,
			budget_unknown_reason varchar(191) NOT NULL DEFAULT '',
			urgency varchar(20) NOT NULL DEFAULT '',
			quality varchar(10) NOT NULL DEFAULT 'unknown',
			status varchar(20) NOT NULL DEFAULT 'new',
			qualification varchar(20) NOT NULL DEFAULT '',
			assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
			next_follow_up date DEFAULT NULL,
			next_action varchar(255) NOT NULL DEFAULT '',
			notes longtext NOT NULL,
			sla_started_at datetime DEFAULT NULL,
			sla_due_at datetime DEFAULT NULL,
			sla_status varchar(12) NOT NULL DEFAULT 'running',
			deposit_sla_due datetime DEFAULT NULL,
			deposit_status varchar(12) NOT NULL DEFAULT 'none',
			deposit_override tinyint(1) NOT NULL DEFAULT 0,
			planning_approved tinyint(1) NOT NULL DEFAULT 0,
			closing_status varchar(12) NOT NULL DEFAULT '',
			stage varchar(40) NOT NULL DEFAULT 'new',
			last_stage_changed_at datetime DEFAULT NULL,
			lifecycle varchar(12) NOT NULL DEFAULT 'active',
			stale_flag tinyint(1) NOT NULL DEFAULT 0,
			stale_reason varchar(255) NOT NULL DEFAULT '',
			stale_since datetime DEFAULT NULL,
			reactivation_at datetime DEFAULT NULL,
			reactivation_reason varchar(255) NOT NULL DEFAULT '',
			merged_into_lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			score smallint(5) unsigned NOT NULL DEFAULT 0,
			temperature varchar(12) NOT NULL DEFAULT 'low_fit',
			score_locked tinyint(1) NOT NULL DEFAULT 0,
			score_override_reason varchar(255) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY assigned_to (assigned_to),
			KEY created_by (created_by),
			KEY status (status),
			KEY stage (stage),
			KEY lifecycle (lifecycle),
			KEY stale_flag (stale_flag),
			KEY whatsapp_normalized (whatsapp_normalized),
			KEY score (score),
			KEY temperature (temperature)
		) {$charset_collate};";

		$stage_history = self::stage_history_table();
		$sql[]         = "CREATE TABLE {$stage_history} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			from_status varchar(40) NOT NULL DEFAULT '',
			to_status varchar(40) NOT NULL DEFAULT '',
			changed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			changed_at datetime DEFAULT NULL,
			reason varchar(255) NOT NULL DEFAULT '',
			metadata_json longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$tasks} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'open',
			due_at datetime DEFAULT NULL,
			created_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY assigned_to (assigned_to),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(40) NOT NULL DEFAULT '',
			detail longtext NOT NULL,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY action (action)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Drop every module table. Used only during opt-in uninstall.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		foreach ( array( self::leads_table(), self::tasks_table(), self::audit_table(), self::stage_history_table() ) as $table ) {
			// Table names are built from a trusted prefix, not user input.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Fully-qualified stage-history table name.
	 *
	 * @return string
	 */
	public static function stage_history_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mbd_crm_stage_history';
	}
}
