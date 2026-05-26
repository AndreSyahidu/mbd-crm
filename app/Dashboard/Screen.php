<?php
/**
 * Dashboard screen renderer (Owner / Sales / Planning / My Work / Approvals).
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Dashboard;

use MBD\CRM\Closing\ClosingRepository;
use MBD\CRM\Deposit\DepositRepository;
use MBD\CRM\Leads\Capabilities;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Leads\Tasks;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the role-aware KPI dashboards.
 */
class Screen {

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
		$this->view = new View();
	}

	/**
	 * Provide content for the dashboard, my-work, and approvals screens.
	 *
	 * @param string|null $content Existing content.
	 * @param string      $slug    Active screen slug.
	 * @param array|null  $meta    Screen meta.
	 * @return string|null
	 */
	public function maybe_render( $content, string $slug, $meta ) {
		unset( $meta );

		if ( 'dashboard' === $slug ) {
			return $this->render_dashboard();
		}
		if ( 'my-work' === $slug ) {
			return $this->render_my_work();
		}
		if ( 'approvals' === $slug ) {
			return $this->render_approvals();
		}

		return $content;
	}

	/**
	 * Render the main KPI dashboard with role tabs.
	 *
	 * @return string
	 */
	private function render_dashboard(): string {
		$scope   = Permissions::can_view_all() ? 'all' : 'own';
		$metrics = new Metrics( $scope, get_current_user_id() );

		$tabs = $this->available_views();
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		if ( ! isset( $tabs[ $view ] ) ) {
			$view = (string) array_key_first( $tabs );
		}

		return $this->view->capture(
			'crm/dashboard/main',
			array(
				'view'       => $view,
				'tabs'       => $tabs,
				'kpis'       => $this->kpis_for( $view, $metrics ),
				'by_source'  => $metrics->leads_by_source(),
				'funnel'     => $metrics->funnel(),
				'bottleneck' => $metrics->funnel_bottleneck(),
				'lost'       => $metrics->lost_reasons(),
				'widgets'    => apply_filters( 'mbd_crm_dashboard_widgets', '' ),
			)
		);
	}

	/**
	 * Dashboards the current user may view.
	 *
	 * @return array<string, string>
	 */
	private function available_views(): array {
		$tabs = array();

		if ( Permissions::can_view_all() ) {
			$tabs['owner'] = __( 'Owner', 'mbd-crm' );
		}
		if ( Permissions::can_create() || current_user_can( Capabilities::EDIT_LEADS ) ) {
			$tabs['sales'] = __( 'Sales', 'mbd-crm' );
		}
		if ( current_user_can( Capabilities::EDIT_LEADS ) ) {
			$tabs['planning'] = __( 'Planning support', 'mbd-crm' );
		}
		if ( empty( $tabs ) ) {
			$tabs['sales'] = __( 'Overview', 'mbd-crm' );
		}

		return $tabs;
	}

	/**
	 * Build the KPI card set for a dashboard view.
	 *
	 * @param string  $view    View key.
	 * @param Metrics $metrics Metrics service.
	 * @return array<int, array{label:string,value:string}>
	 */
	private function kpis_for( string $view, Metrics $metrics ): array {
		if ( 'planning' === $view ) {
			return array(
				$this->kpi( __( 'Deposit valid', 'mbd-crm' ), $metrics->deposit_valid() ),
				$this->kpi( __( 'Planning in progress', 'mbd-crm' ), $metrics->planning_in_progress() ),
				$this->kpi( __( 'Planning approved', 'mbd-crm' ), $metrics->planning_approved() ),
				$this->kpi( __( 'Discovery completed', 'mbd-crm' ), $metrics->discovery_completed() ),
			);
		}

		if ( 'sales' === $view ) {
			return array(
				$this->kpi( __( 'My leads', 'mbd-crm' ), $metrics->total_leads() ),
				$this->kpi( __( 'Qualified', 'mbd-crm' ), $metrics->qualified() ),
				$this->kpi( __( 'Overdue follow-up', 'mbd-crm' ), $metrics->overdue_followups() ),
				$this->kpi( __( 'Weighted forecast', 'mbd-crm' ), number_format_i18n( $metrics->weighted_forecast() ) ),
				$this->kpi( __( 'Closing rate', 'mbd-crm' ), $metrics->closing_rate() . '%' ),
			);
		}

		// Owner.
		return array(
			$this->kpi( __( 'Total leads', 'mbd-crm' ), $metrics->total_leads() ),
			$this->kpi( __( 'Qualification rate', 'mbd-crm' ), $metrics->qualification_rate() . '%' ),
			$this->kpi( __( 'Response overdue', 'mbd-crm' ), $metrics->response_overdue() ),
			$this->kpi( __( 'Deposit valid', 'mbd-crm' ), $metrics->deposit_valid() ),
			$this->kpi( __( 'Planning approved', 'mbd-crm' ), $metrics->planning_approved() ),
			$this->kpi( __( 'Weighted forecast', 'mbd-crm' ), number_format_i18n( $metrics->weighted_forecast() ) ),
			$this->kpi( __( 'Closing value', 'mbd-crm' ), number_format_i18n( $metrics->closing_value() ) ),
			$this->kpi( __( 'Closing rate', 'mbd-crm' ), $metrics->closing_rate() . '%' ),
			$this->kpi( __( 'Pending approval', 'mbd-crm' ), $metrics->pending_approval() ),
			$this->kpi( __( 'Overdue follow-up', 'mbd-crm' ), $metrics->overdue_followups() ),
		);
	}

	/**
	 * Build a KPI card.
	 *
	 * @param string     $label Label.
	 * @param string|int $value Value.
	 * @return array{label:string,value:string}
	 */
	private function kpi( string $label, $value ): array {
		return array(
			'label' => $label,
			'value' => (string) $value,
		);
	}

	/**
	 * Render the My Work dashboard.
	 *
	 * @return string
	 */
	private function render_my_work(): string {
		$uid     = get_current_user_id();
		$metrics = new Metrics( 'own', $uid );

		$tasks = Tasks::open_for_user( $uid );
		$rows  = array();
		foreach ( $tasks as $task ) {
			$rows[] = array(
				'title' => $task->title,
				'due'   => (string) $task->due_at,
				'url'   => Router::screen_url( 'leads' ) . '?lead=' . (int) $task->lead_id,
			);
		}

		return $this->view->capture(
			'crm/dashboard/my-work',
			array(
				'kpis'  => array(
					$this->kpi( __( 'My leads', 'mbd-crm' ), $metrics->total_leads() ),
					$this->kpi( __( 'Overdue follow-up', 'mbd-crm' ), $metrics->overdue_followups() ),
					$this->kpi( __( 'Open tasks', 'mbd-crm' ), count( $rows ) ),
				),
				'tasks' => $rows,
			)
		);
	}

	/**
	 * Render the Pending Approval dashboard.
	 *
	 * @return string
	 */
	private function render_approvals(): string {
		$leads_repo = new LeadRepository();
		$scope      = Permissions::can_view_all() ? 'all' : 'own';

		$visible = array();
		foreach ( $leads_repo->all( array( 'scope' => $scope, 'user_id' => get_current_user_id() ) ) as $lead ) {
			$visible[ (int) $lead->id ] = $lead;
		}

		$rows = array();

		foreach ( ( new DepositRepository() )->by_status( 'pending' ) as $dep ) {
			if ( isset( $visible[ (int) $dep->lead_id ] ) && (int) $dep->proof_id > 0 ) {
				$rows[] = $this->approval_row( $visible[ (int) $dep->lead_id ], __( 'Deposit proof to verify', 'mbd-crm' ) );
			}
		}
		foreach ( ( new ClosingRepository() )->by_status( 'waiting_approval' ) as $cl ) {
			if ( isset( $visible[ (int) $cl->lead_id ] ) ) {
				$rows[] = $this->approval_row( $visible[ (int) $cl->lead_id ], __( 'Closing awaiting approval', 'mbd-crm' ) );
			}
		}

		return $this->view->capture( 'crm/dashboard/approvals', array( 'rows' => $rows ) );
	}

	/**
	 * Build an approval-queue row.
	 *
	 * @param object $lead Lead row.
	 * @param string $what Description.
	 * @return array{lead:string,what:string,url:string}
	 */
	private function approval_row( object $lead, string $what ): array {
		return array(
			'lead' => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
			'what' => $what,
			'url'  => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
		);
	}
}
