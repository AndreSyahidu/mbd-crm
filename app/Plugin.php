<?php
/**
 * Main plugin orchestrator.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM;

use MBD\CRM\Admin\Settings;
use MBD\CRM\Health\HealthCheck;

defined( 'ABSPATH' ) || exit;

/**
 * Wires together the plugin's components and boots them on demand.
 */
class Plugin {

	/**
	 * Front-end route handler.
	 *
	 * @var Router
	 */
	private Router $router;

	/**
	 * Asset loader.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Admin settings screen.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Site Health check integration.
	 *
	 * @var HealthCheck
	 */
	private HealthCheck $health;

	/**
	 * Lead Intake module.
	 *
	 * @var \MBD\CRM\Leads\Module
	 */
	private \MBD\CRM\Leads\Module $leads;

	/**
	 * Qualification module.
	 *
	 * @var \MBD\CRM\Qualification\Module
	 */
	private \MBD\CRM\Qualification\Module $qualification;

	/**
	 * Follow-Up & Promise module.
	 *
	 * @var \MBD\CRM\FollowUp\Module
	 */
	private \MBD\CRM\FollowUp\Module $followup;

	/**
	 * Discovery / Survey module.
	 *
	 * @var \MBD\CRM\Discovery\Module
	 */
	private \MBD\CRM\Discovery\Module $discovery;

	/**
	 * Deposit Planning module.
	 *
	 * @var \MBD\CRM\Deposit\Module
	 */
	private \MBD\CRM\Deposit\Module $deposit;

	/**
	 * Planning Tracking module.
	 *
	 * @var \MBD\CRM\Planning\Module
	 */
	private \MBD\CRM\Planning\Module $planning;

	/**
	 * Client Approval Evidence module.
	 *
	 * @var \MBD\CRM\Approval\Module
	 */
	private \MBD\CRM\Approval\Module $approval;

	/**
	 * Closing and Negotiation module.
	 *
	 * @var \MBD\CRM\Closing\Module
	 */
	private \MBD\CRM\Closing\Module $closing;

	/**
	 * Dashboard and KPI module.
	 *
	 * @var \MBD\CRM\Dashboard\Module
	 */
	private \MBD\CRM\Dashboard\Module $dashboard;

	/**
	 * Stakeholder mapping module.
	 *
	 * @var \MBD\CRM\Stakeholders\Module
	 */
	private \MBD\CRM\Stakeholders\Module $stakeholders;

	/**
	 * Duplicate detection & merge module.
	 *
	 * @var \MBD\CRM\Duplicates\Module
	 */
	private \MBD\CRM\Duplicates\Module $duplicates;

	/**
	 * Lead scoring module.
	 *
	 * @var \MBD\CRM\Scoring\Module
	 */
	private \MBD\CRM\Scoring\Module $scoring;

	/**
	 * Offer versioning module.
	 *
	 * @var \MBD\CRM\Offers\Module
	 */
	private \MBD\CRM\Offers\Module $offers;

	/**
	 * Reporting & analytics module.
	 *
	 * @var \MBD\CRM\Reporting\Module
	 */
	private \MBD\CRM\Reporting\Module $reporting;

	/**
	 * Notifications & reminders module.
	 *
	 * @var \MBD\CRM\Reminders\Module
	 */
	private \MBD\CRM\Reminders\Module $reminders;

	/**
	 * Task management module.
	 *
	 * @var \MBD\CRM\Tasks\Module
	 */
	private \MBD\CRM\Tasks\Module $tasks;

	/**
	 * Import / Export module.
	 *
	 * @var \MBD\CRM\IO\Module
	 */
	private \MBD\CRM\IO\Module $io;

	/**
	 * Build the component graph.
	 */
	public function __construct() {
		$this->router        = new Router();
		$this->assets        = new Assets();
		$this->settings      = new Settings();
		$this->health        = new HealthCheck();
		$this->leads         = new \MBD\CRM\Leads\Module();
		$this->qualification = new \MBD\CRM\Qualification\Module();
		$this->followup      = new \MBD\CRM\FollowUp\Module();
		$this->discovery     = new \MBD\CRM\Discovery\Module();
		$this->deposit       = new \MBD\CRM\Deposit\Module();
		$this->planning      = new \MBD\CRM\Planning\Module();
		$this->approval      = new \MBD\CRM\Approval\Module();
		$this->closing       = new \MBD\CRM\Closing\Module();
		$this->dashboard     = new \MBD\CRM\Dashboard\Module();
		$this->stakeholders  = new \MBD\CRM\Stakeholders\Module();
		$this->duplicates    = new \MBD\CRM\Duplicates\Module();
		$this->scoring       = new \MBD\CRM\Scoring\Module();
		$this->offers        = new \MBD\CRM\Offers\Module();
		$this->reporting     = new \MBD\CRM\Reporting\Module();
		$this->reminders     = new \MBD\CRM\Reminders\Module();
		$this->tasks         = new \MBD\CRM\Tasks\Module();
		$this->io            = new \MBD\CRM\IO\Module();
	}

	/**
	 * Register hooks for every component.
	 *
	 * Safe to call multiple times; each component guards its own
	 * registration via the singleton accessor in mbd_crm().
	 *
	 * @return void
	 */
	public function boot(): void {
		load_plugin_textdomain( 'mbd-crm', false, dirname( plugin_basename( MBD_CRM_FILE ) ) . '/languages' );

		// Run idempotent schema upgrades for existing installs when the
		// plugin version changes (admin requests only).
		add_action( 'admin_init', array( Migrator::class, 'maybe_upgrade' ) );

		$this->router->register();
		$this->assets->register();
		$this->settings->register();
		$this->health->register();
		$this->leads->register();
		$this->qualification->register();
		$this->followup->register();
		$this->discovery->register();
		$this->deposit->register();
		$this->planning->register();
		$this->approval->register();
		$this->closing->register();
		$this->dashboard->register();
		$this->stakeholders->register();
		$this->duplicates->register();
		$this->scoring->register();
		$this->offers->register();
		$this->reporting->register();
		$this->reminders->register();
		$this->tasks->register();
		$this->io->register();
	}

	/**
	 * Expose the router (used by the activator to seed rewrite rules).
	 *
	 * @return Router
	 */
	public function router(): Router {
		return $this->router;
	}
}
