<?php
/**
 * Closing and Negotiation module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Closing;

defined( 'ABSPATH' ) || exit;

/**
 * Wires closing request handling, the detail panel, automation, and the
 * closing notification provider.
 */
class Module {

	/**
	 * Closing handler and panel renderer.
	 *
	 * @var Controller
	 */
	private Controller $controller;

	/**
	 * Closing automation.
	 *
	 * @var Automation
	 */
	private Automation $automation;

	/**
	 * Closing notification provider.
	 *
	 * @var Notifications
	 */
	private Notifications $notifications;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->controller    = new Controller();
		$this->automation    = new Automation();
		$this->notifications = new Notifications();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->automation->register();
		$this->notifications->register();

		add_filter( 'mbd_crm_leads_post_action', array( $this->controller, 'handle' ), 10, 2 );
		add_filter( 'mbd_crm_lead_detail_panels', array( $this->controller, 'render_panel' ), 70, 2 );
	}

	/**
	 * Create the module's tables. Called from the plugin activator.
	 *
	 * @return void
	 */
	public static function install(): void {
		Schema::create_tables();
	}

	/**
	 * Drop the module's tables. Called from the opt-in uninstall routine.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		Schema::drop_tables();
	}
}
