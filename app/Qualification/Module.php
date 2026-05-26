<?php
/**
 * Qualification module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Qualification;

defined( 'ABSPATH' ) || exit;

/**
 * Wires qualification request handling, the detail panel, automation, and
 * the discovery screen into the CRM, and owns the module's schema.
 */
class Module {

	/**
	 * Qualify / disqualify handler and panel renderer.
	 *
	 * @var Controller
	 */
	private Controller $controller;

	/**
	 * Post-decision automation.
	 *
	 * @var Automation
	 */
	private Automation $automation;

	/**
	 * Discovery screen renderer.
	 *
	 * @var DiscoveryScreen
	 */
	private DiscoveryScreen $discovery;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->controller = new Controller();
		$this->automation = new Automation();
		$this->discovery  = new DiscoveryScreen();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->automation->register();

		add_filter( 'mbd_crm_leads_post_action', array( $this->controller, 'handle' ), 10, 2 );
		add_filter( 'mbd_crm_lead_detail_panels', array( $this->controller, 'render_panel' ), 10, 2 );
		add_filter( 'mbd_crm_screen_content', array( $this->discovery, 'maybe_render' ), 10, 3 );
	}

	/**
	 * Create the qualifications table. Called from the plugin activator.
	 *
	 * @return void
	 */
	public static function install(): void {
		Schema::create_table();
	}

	/**
	 * Drop the qualifications table. Called from the opt-in uninstall routine.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		Schema::drop_table();
	}
}
