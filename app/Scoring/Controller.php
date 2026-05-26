<?php
/**
 * Scoring panel, manual override, and priority-queue widget.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Scoring;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\Capabilities;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the lead scoring panel + a priority-queue dashboard widget, and
 * handles the Owner/Admin manual score override (reason required, audited).
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_score';
	private const NONCE_FIELD  = 'mbd_crm_scnonce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Score persistence.
	 *
	 * @var ScoreRepository
	 */
	private ScoreRepository $repo;

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
		$this->repo  = new ScoreRepository();
		$this->view  = new View();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_leads_post_action', array( $this, 'handle' ), 10, 2 );
		add_filter( 'mbd_crm_lead_detail_panels', array( $this, 'render_panel' ), 5, 2 );
		add_filter( 'mbd_crm_dashboard_widgets', array( $this, 'render_priority_widget' ) );
	}

	/**
	 * Handle the score override / reset actions.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		if ( ! in_array( $action, array( 'override_score', 'reset_score' ), true ) ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}

		// Rule: only Owner/Admin may override the score.
		if ( ! current_user_can( Capabilities::OVERRIDE_SCORE ) ) {
			return Components::blocked( __( 'You do not have permission to override lead scores.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;

		if ( 'reset_score' === $action ) {
			$eval = Scorer::evaluate( $lead );
			$this->repo->persist( $lead_id, $eval['score'], $eval['temperature'], 0, '' );
			$this->repo->record( $lead_id, (int) $lead->score, $eval['score'], (string) $lead->temperature, $eval['temperature'], __( 'Override cleared', 'mbd-crm' ), 'user', get_current_user_id() );
			$this->redirect( add_query_arg( 'score', 'reset', $detail ) );
		}

		// override_score. Rule: reason required.
		$reason = isset( $_POST['reason'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['reason'] ) ) ) : '';
		if ( '' === $reason ) {
			$this->redirect( add_query_arg( 'score_error', 'reason', $detail ) );
		}

		$value = isset( $_POST['score'] ) ? absint( wp_unslash( $_POST['score'] ) ) : 0;
		$value = max( 0, min( 100, $value ) );
		$temp  = Scorer::temperature( $value );

		$this->repo->persist( $lead_id, $value, $temp, 1, $reason );
		$this->repo->record( $lead_id, (int) $lead->score, $value, (string) $lead->temperature, $temp, $reason, 'user', get_current_user_id() );
		Audit::record(
			$lead_id,
			Audit::SCORE,
			array(
				'score'  => $value,
				'reason' => $reason,
			)
		);

		$this->redirect( add_query_arg( 'score', 'overridden', $detail ) );
	}

	/**
	 * Render the scoring panel on the lead detail.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$eval = Scorer::evaluate( $lead );

		$panel = $this->view->capture(
			'crm/scoring/panel',
			array(
				'lead'         => $lead,
				'stored_score' => (int) ( $lead->score ?? 0 ),
				'temperature'  => (string) ( $lead->temperature ?? 'low_fit' ),
				'locked'       => (int) ( $lead->score_locked ?? 0 ) === 1,
				'override_note' => (string) ( $lead->score_override_reason ?? '' ),
				'live'         => $eval,
				'history'      => $this->repo->history( (int) $lead->id ),
				'can_override' => current_user_can( Capabilities::OVERRIDE_SCORE ),
				'nonce_field'  => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action'  => Router::screen_url( 'leads' ),
				'notice'       => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Append a "Priority queue" widget (top active leads by score).
	 *
	 * @param string $html Accumulated widget HTML.
	 * @return string
	 */
	public function render_priority_widget( string $html ): string {
		if ( ! Permissions::can_access() ) {
			return $html;
		}

		$scope = Permissions::can_view_all() ? 'all' : 'own';
		$leads = $this->leads->active_pipeline( array( 'scope' => $scope, 'user_id' => get_current_user_id() ) );

		usort(
			$leads,
			static function ( $a, $b ) {
				return (int) $b->score <=> (int) $a->score;
			}
		);
		$leads = array_slice( $leads, 0, 10 );

		$rows = array();
		foreach ( $leads as $lead ) {
			$rows[] = array(
				'name'        => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
				'score'       => (int) $lead->score,
				'temperature' => (string) $lead->temperature,
				'url'         => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
			);
		}

		return $html . $this->view->capture( 'crm/scoring/priority-widget', array( 'rows' => $rows ) );
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['score'] ) ) {
			return Components::notice( __( 'Lead score updated.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['score_error'] ) ) {
			return Components::notice( __( 'A reason is required to override the score.', 'mbd-crm' ), 'danger' );
		}

		return '';
	}

	/**
	 * Redirect and stop.
	 *
	 * @param string $url Destination URL.
	 * @return void
	 */
	private function redirect( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}
