<?php
/**
 * Dashboard widget for overdue follow-ups.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\FollowUp;

use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Renders an "Overdue follow-ups" widget on the dashboard.
 */
class Dashboard {

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Template renderer.
	 *
	 * @var View
	 */
	private View $view;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->leads = new LeadRepository();
		$this->view  = new View();
	}

	/**
	 * Hook the widget onto the dashboard.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_dashboard_widgets', array( $this, 'render' ) );
	}

	/**
	 * Append the overdue follow-ups widget.
	 *
	 * @param string $html Accumulated widget HTML.
	 * @return string
	 */
	public function render( string $html ): string {
		if ( ! Permissions::can_access() ) {
			return $html;
		}

		$scope = Permissions::can_view_all() ? 'all' : 'own';
		$today = current_time( 'Y-m-d' );

		$leads = $this->leads->overdue_followups(
			$today,
			array(
				'scope'   => $scope,
				'user_id' => get_current_user_id(),
			)
		);

		$rows = array();
		foreach ( $leads as $lead ) {
			$rows[] = array(
				'name'        => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
				'due'         => (string) $lead->next_follow_up,
				'next_action' => (string) $lead->next_action,
				'url'         => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
			);
		}

		return $html . $this->view->capture( 'crm/followup/dashboard-overdue', array( 'rows' => $rows ) );
	}
}
