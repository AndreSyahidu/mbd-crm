<?php
/**
 * Stage-aging sweep and the "Stuck leads" dashboard widget.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Periodically flags stale leads and renders a stuck-lead widget. Staleness
 * is also computed on read, so displays never depend on the sweep running.
 */
class Aging {

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
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_dashboard_widgets', array( $this, 'render' ) );
		add_action( 'admin_init', array( $this, 'maybe_sweep' ) );
	}

	/**
	 * Run the stale sweep at most once per hour (persists stale flags).
	 *
	 * @return void
	 */
	public function maybe_sweep(): void {
		if ( get_transient( 'mbd_crm_stale_sweep' ) ) {
			return;
		}
		set_transient( 'mbd_crm_stale_sweep', 1, HOUR_IN_SECONDS );
		$this->sweep();
	}

	/**
	 * Evaluate staleness for every active pipeline lead and persist the flag.
	 *
	 * @return void
	 */
	public function sweep(): void {
		foreach ( $this->leads->active_pipeline() as $lead ) {
			$state = Stage::staleness( $lead );
			if ( ! empty( $state['stale'] ) ) {
				$this->leads->flag_stale( $lead, $state['reason'] );
			} elseif ( (int) ( $lead->stale_flag ?? 0 ) === 1 ) {
				$this->leads->clear_stale( (int) $lead->id );
			}
		}
	}

	/**
	 * Append the "Stuck leads" widget to the dashboard.
	 *
	 * @param string $html Accumulated widget HTML.
	 * @return string
	 */
	public function render( string $html ): string {
		if ( ! Permissions::can_access() ) {
			return $html;
		}

		$scope = Permissions::can_view_all() ? 'all' : 'own';
		$rows  = array();

		foreach ( $this->leads->active_pipeline( array( 'scope' => $scope, 'user_id' => get_current_user_id() ) ) as $lead ) {
			$state = Stage::staleness( $lead );
			if ( empty( $state['stale'] ) ) {
				continue;
			}
			$rows[] = array(
				'name'   => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
				'stage'  => Stage::label( Stage::key( $lead ) ),
				'aging'  => Stage::aging_label( $lead ),
				'reason' => $state['reason'],
				'url'    => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
			);
		}

		return $html . $this->view->capture( 'crm/lifecycle/stuck-widget', array( 'rows' => $rows ) );
	}
}
