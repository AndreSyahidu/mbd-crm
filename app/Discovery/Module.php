<?php
/**
 * Discovery / Survey module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Discovery;

defined( 'ABSPATH' ) || exit;

/**
 * Wires discovery request handling, the detail panel, and automation.
 */
class Module {

	/**
	 * Discovery handler and panel renderer.
	 *
	 * @var Controller
	 */
	private Controller $controller;

	/**
	 * Discovery automation.
	 *
	 * @var Automation
	 */
	private Automation $automation;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->controller = new Controller();
		$this->automation = new Automation();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->automation->register();

		add_filter( 'mbd_crm_leads_post_action', array( $this->controller, 'handle' ), 10, 2 );
		add_filter( 'mbd_crm_lead_detail_panels', array( $this->controller, 'render_panel' ), 30, 2 );
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
