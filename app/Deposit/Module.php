<?php
/**
 * Deposit Planning module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Deposit;

defined( 'ABSPATH' ) || exit;

/**
 * Wires deposit request handling, the detail panel, automation, and the
 * deposit notification provider.
 */
class Module {

	/**
	 * Deposit handler and panel renderer.
	 *
	 * @var Controller
	 */
	private Controller $controller;

	/**
	 * Deposit audit automation.
	 *
	 * @var Automation
	 */
	private Automation $automation;

	/**
	 * Deposit notification provider.
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
		add_filter( 'mbd_crm_lead_detail_panels', array( $this->controller, 'render_panel' ), 40, 2 );
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
