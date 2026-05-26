<?php
/**
 * Qualification request handling and detail panel.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Qualification;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Processes the qualify / disqualify actions submitted from the lead
 * detail page and renders the qualification panel on that page.
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_qualify';
	private const NONCE_FIELD  = 'mbd_crm_qnonce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Qualification persistence.
	 *
	 * @var QualificationRepository
	 */
	private QualificationRepository $repo;

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
		$this->repo  = new QualificationRepository();
		$this->view  = new View();
	}

	/**
	 * Handle a qualify / disqualify submission from the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		if ( ! in_array( $action, array( 'qualify', 'disqualify' ), true ) ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}
		if ( ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to qualify this lead.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;

		if ( 'qualify' === $action ) {
			// Rule: a lead may only be qualified once its required data exists.
			if ( ! empty( FitChecks::missing_required( $lead ) ) ) {
				$this->redirect( add_query_arg( 'qual_error', 'missing', $detail ) );
			}

			$eval = FitChecks::evaluate( $lead );
			$this->repo->record( $lead_id, 'qualified', $eval['score'], $eval['checks'], '', FitChecks::snapshot( $lead ) );
			$this->leads->set_qualification( $lead_id, 'qualified' );

			/**
			 * Fires after a lead is marked qualified.
			 *
			 * @param int    $lead_id Lead ID.
			 * @param object $lead    Lead row.
			 * @param int    $score   Fit score.
			 */
			do_action( 'mbd_crm_lead_qualified', $lead_id, $lead, $eval['score'] );

			$this->redirect( add_query_arg( 'qualified', '1', $detail ) );
		}

		// Rule: a not-qualified decision requires a reason.
		$reason = isset( $_POST['unqualified_reason'] )
			? trim( sanitize_text_field( wp_unslash( $_POST['unqualified_reason'] ) ) )
			: '';

		if ( '' === $reason ) {
			$this->redirect( add_query_arg( 'qual_error', 'reason', $detail ) );
		}

		$eval = FitChecks::evaluate( $lead );
		$this->repo->record( $lead_id, 'not_qualified', $eval['score'], $eval['checks'], $reason, FitChecks::snapshot( $lead ) );
		$this->leads->set_qualification( $lead_id, 'not_qualified' );

		/**
		 * Fires after a lead is marked not qualified.
		 *
		 * @param int    $lead_id Lead ID.
		 * @param object $lead    Lead row.
		 * @param string $reason  Reason given.
		 */
		do_action( 'mbd_crm_lead_disqualified', $lead_id, $lead, $reason );

		$this->redirect( add_query_arg( 'disqualified', '1', $detail ) );
	}

	/**
	 * Render the qualification panel for the lead detail sidebar.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$eval   = FitChecks::evaluate( $lead );
		$latest = $this->repo->latest( (int) $lead->id );

		$panel = $this->view->capture(
			'crm/qualification/panel',
			array(
				'lead'         => $lead,
				'eval'         => $eval,
				'missing'      => FitChecks::missing_required( $lead ),
				'latest'       => $latest,
				'can_edit'     => Permissions::can_edit( $lead ),
				'in_discovery' => Gate::can_enter_discovery( $lead ),
				'nonce_field'  => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action'  => Router::screen_url( 'leads' ),
				'notice'       => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['qualified'] ) ) {
			return Components::notice( __( 'Lead qualified. Discovery is now unlocked and a scheduling task was created.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['disqualified'] ) ) {
			return Components::notice( __( 'Lead marked not qualified.', 'mbd-crm' ), 'warning' );
		}
		if ( isset( $_GET['qual_error'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['qual_error'] ) );
			if ( 'reason' === $code ) {
				return Components::notice( __( 'A reason is required to mark a lead not qualified.', 'mbd-crm' ), 'danger' );
			}
			return Components::notice( __( 'This lead is missing required data and cannot be qualified yet.', 'mbd-crm' ), 'danger' );
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
