<?php
/**
 * Lead scoring module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Scoring;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the scorer recalculation, scoring panel, override handler, and the
 * priority-queue widget.
 */
class Module {

	/**
	 * Scoring panel / override / priority widget.
	 *
	 * @var Controller
	 */
	private Controller $controller;

	/**
	 * Score recalculation listener.
	 *
	 * @var Recalculator
	 */
	private Recalculator $recalculator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->controller   = new Controller();
		$this->recalculator = new Recalculator();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->controller->register();
		$this->recalculator->register();
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
