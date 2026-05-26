<?php
/**
 * Offer versioning module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Offers;

defined( 'ABSPATH' ) || exit;

/**
 * Wires offer request handling and the offer detail panel.
 */
class Module {

	/**
	 * Offer handler and panel renderer.
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
