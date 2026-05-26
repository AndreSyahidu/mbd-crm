<?php
/**
 * Plugin deactivation routine.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once when the plugin is deactivated.
 */
class Deactivator {

	/**
	 * Deactivation entry point.
	 *
	 * Clears rewrite rules so the /crm route stops resolving. Plugin
	 * data is intentionally preserved here; full cleanup lives in
	 * uninstall.php.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
