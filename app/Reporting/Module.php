<?php
/**
 * Reporting & analytics module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Reporting;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Reports screen and CSV exports. Read-only; this module has no
 * schema of its own — it reports over existing lead/closing data.
 */
class Module {

	/**
	 * Reports screen renderer.
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
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->screen->register();
	}
}
