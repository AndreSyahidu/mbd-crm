<?php
/**
 * Follow-Up & Promise Tracking module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\FollowUp;

defined( 'ABSPATH' ) || exit;

/**
 * Wires follow-up/promise request handling, the detail panel, automation,
 * the dashboard widget, and the notifications screen into the CRM.
 */
class Module {

	/**
	 * Follow-up / promise handler and panel renderer.
	 *
	 * @var Controller
	 */
	private Controller $controller;

	/**
	 * Task and audit automation.
	 *
	 * @var Automation
	 */
	private Automation $automation;

	/**
	 * Overdue follow-ups dashboard widget.
	 *
	 * @var Dashboard
	 */
	private Dashboard $dashboard;

	/**
	 * Due-promise notifications.
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
		$this->dashboard     = new Dashboard();
		$this->notifications = new Notifications();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->automation->register();
		$this->dashboard->register();

		add_filter( 'mbd_crm_leads_post_action', array( $this->controller, 'handle' ), 10, 2 );
		add_filter( 'mbd_crm_lead_detail_panels', array( $this->controller, 'render_panel' ), 20, 2 );
		add_filter( 'mbd_crm_screen_content', array( $this->notifications, 'maybe_render' ), 10, 3 );
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
