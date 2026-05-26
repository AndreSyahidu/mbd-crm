<?php
/**
 * Idempotent schema migrator.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the table-creation routines for every module when the stored schema
 * version differs from the plugin version. Table creation uses dbDelta, so
 * running it repeatedly is safe and only adds missing columns/tables —
 * existing data is preserved.
 */
class Migrator {

	/**
	 * Option key holding the installed schema version.
	 */
	public const VERSION_OPTION = 'mbd_crm_db_version';

	/**
	 * Run migrations on an admin request when the version changed.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === MBD_CRM_VERSION ) {
			return;
		}

		self::migrate();
		update_option( self::VERSION_OPTION, MBD_CRM_VERSION );
	}

	/**
	 * Create or upgrade every module table (idempotent, additive).
	 *
	 * Roles and capabilities are intentionally NOT touched here so that any
	 * site-level capability customisations are preserved across upgrades.
	 *
	 * @return void
	 */
	public static function migrate(): void {
		Leads\Schema::create_tables();
		Qualification\Schema::create_table();
		FollowUp\Schema::create_tables();
		Discovery\Schema::create_table();
		Deposit\Schema::create_table();
		Planning\Schema::create_tables();
		Approval\Schema::create_table();
		Closing\Schema::create_tables();
		Stakeholders\Schema::create_table();
		Scoring\Schema::create_table();
	}

	/**
	 * Record the current version (used by the activator after a fresh install).
	 *
	 * @return void
	 */
	public static function stamp_version(): void {
		update_option( self::VERSION_OPTION, MBD_CRM_VERSION );
	}
}
