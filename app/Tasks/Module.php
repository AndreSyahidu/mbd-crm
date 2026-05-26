<?php
/**
 * Task management module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Wires interactive task management (lead-detail panel + Tasks screen).
 * Task storage lives in {@see \MBD\CRM\Leads\Tasks}; this module has no schema.
 */
class Module {

	/**
	 * Task controller.
	 *
	 * @var Controller
	 */
	private Controller $controller;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->controller = new Controller();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->controller->register();
	}
}
