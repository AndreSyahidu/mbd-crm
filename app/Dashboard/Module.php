<?php
/**
 * CRM Dashboard and KPI module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the role-aware KPI dashboards onto the dashboard, my-work, and
 * approvals screens. This module is read-only; it has no schema.
 */
class Module {

	/**
	 * Dashboard screen renderer.
	 *
	 * @var Screen
	 */
	private Screen $screen;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->screen = new Screen();
	}

	/**
	 * Register hooks. Runs after stage modules so it can override the
	 * default screen templates.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_screen_content', array( $this->screen, 'maybe_render' ), 20, 3 );
	}
}
