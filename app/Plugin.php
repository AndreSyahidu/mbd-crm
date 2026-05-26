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

		$this->router->register();
		$this->assets->register();
		$this->settings->register();
		$this->health->register();
		$this->leads->register();
		$this->qualification->register();
		$this->followup->register();
		$this->discovery->register();
		$this->deposit->register();
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
