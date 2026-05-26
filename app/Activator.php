<?php
/**
 * Plugin activation routine.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once when the plugin is activated.
 */
class Activator {

	/**
	 * Option key holding plugin settings and metadata.
	 */
	public const OPTION_KEY = 'mbd_crm_settings';

	/**
	 * Activation entry point.
	 *
	 * Seeds default options, registers the rewrite rule, and flushes
	 * rewrite rules so the /crm route works immediately.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::seed_options();

		// Create module tables and CRM roles/capabilities.
		\MBD\CRM\Leads\Module::install();
		\MBD\CRM\Qualification\Module::install();
		\MBD\CRM\FollowUp\Module::install();
		\MBD\CRM\Discovery\Module::install();
		\MBD\CRM\Deposit\Module::install();
		\MBD\CRM\Planning\Module::install();

		// Ensure the route exists before flushing.
		mbd_crm()->router()->add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Create default plugin options if they do not yet exist.
	 *
	 * @return void
	 */
	private static function seed_options(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option(
				self::OPTION_KEY,
				array(
					'version'           => MBD_CRM_VERSION,
					'remove_data'       => false,
					'installed_at_gmt'  => gmdate( 'c' ),
				)
			);
		}
	}
}
