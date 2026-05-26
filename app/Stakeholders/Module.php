<?php
/**
 * Stakeholder mapping module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Stakeholders;

defined( 'ABSPATH' ) || exit;

/**
 * Wires stakeholder request handling and the detail panel.
 */
class Module {

	/**
	 * Stakeholder handler and panel renderer.
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

	/**
	 * Create the module's table. Called from the plugin activator.
	 *
	 * @return void
	 */
	public static function install(): void {
		Schema::create_table();
	}

	/**
	 * Drop the module's table. Called from the opt-in uninstall routine.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		Schema::drop_table();
	}
}
