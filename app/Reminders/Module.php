<?php
/**
 * Notifications & reminders module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Reminders;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the in-app SLA/stale notification providers and the scheduled email
 * reminder digest.
 */
class Module {

	/**
	 * In-app notification providers.
	 *
	 * @var Providers
	 */
	private Providers $providers;

	/**
	 * Scheduled reminder digest.
	 *
	 * @var Scheduler
	 */
	private Scheduler $scheduler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->providers = new Providers();
		$this->scheduler = new Scheduler();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->providers->register();
		$this->scheduler->register();
	}

	/**
	 * Schedule the reminder cron. Called from the plugin activator.
	 *
	 * @return void
	 */
	public static function install(): void {
		Scheduler::schedule();
	}

	/**
	 * Clear the reminder cron. Called from deactivation / uninstall.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		Scheduler::unschedule();
	}
}
