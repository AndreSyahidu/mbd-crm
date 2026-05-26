<?php
/**
 * Lead Intake module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Leads controller, automation, and audit into the CRM, and
 * owns the module's install / uninstall routines.
 */
class Module {

	/**
	 * Request handler for the leads screen.
	 *
	 * @var Controller
	 */
	private Controller $controller;

	/**
	 * Audit recorder.
	 *
	 * @var Audit
	 */
	private Audit $audit;

	/**
	 * Task automation.
	 *
	 * @var Tasks
	 */
	private Tasks $tasks;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->controller = new Controller();
		$this->audit      = new Audit();
		$this->tasks      = new Tasks();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->audit->register();
		$this->tasks->register();

		add_filter( 'mbd_crm_screen_content', array( $this->controller, 'maybe_render' ), 10, 3 );
	}

	/**
	 * Create tables and roles. Called from the plugin activator.
	 *
	 * @return void
	 */
	public static function install(): void {
		Schema::create_tables();
		Capabilities::install();
	}

	/**
	 * Drop tables and roles. Called from the opt-in uninstall routine.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		Schema::drop_tables();
		Capabilities::uninstall();
	}
}
