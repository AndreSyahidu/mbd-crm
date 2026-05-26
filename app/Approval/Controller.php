<?php
/**
 * Client approval request handling and detail panel.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Approval;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Planning\PlanningRepository;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Records client approval evidence, marks planning approved (which unlocks
 * closing), and renders the approval panel on the lead detail page.
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_approval';
	private const NONCE_FIELD  = 'mbd_crm_apnonce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Approval persistence.
	 *
	 * @var ApprovalRepository
	 */
	private ApprovalRepository $repo;

	/**
	 * Planning persistence.
	 *
	 * @var PlanningRepository
	 */
	private PlanningRepository $plannings;

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
		$this->leads     = new LeadRepository();
		$this->repo      = new ApprovalRepository();
		$this->plannings = new PlanningRepository();
		$this->view      = new View();
	}

	/**
	 * Handle the approval action submitted from the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		if ( 'record_approval' !== $action ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}
		if ( ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to record approval for this lead.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;

		$type = sanitize_key( $this->post_text( 'evidence_type' ) );
		if ( ! Options::is_type( $type ) ) {
			$type = 'other';
		}

		$note = $this->post_textarea( 'approval_note' );
		list( $attach_id, $attach_url ) = $this->upload_evidence();

		// Rule: planning approval requires evidence (a file or a written note).
		if ( 0 === $attach_id && '' === trim( $note ) ) {
			$this->redirect( add_query_arg( 'appr_error', 'evidence', $detail ) );
		}

		$data = array(
			'evidence_type' => $type,
			'approval_note' => $note,
			'client_name'   => $this->post_text( 'client_name' ),
			'approved_date' => $this->post_text( 'approved_date' ),
		);

		$this->repo->record( $lead_id, $data, $attach_id, $attach_url, get_current_user_id() );

		// Mark planning approved: unlock closing and stop the approval wait.
		$this->leads->set_stage( $lead_id, array( 'planning_approved' => 1 ) );
		$planning = $this->plannings->for_lead( $lead_id );
		if ( $planning ) {
			$this->plannings->update( (int) $planning->id, array( 'status' => 'approved' ) );
		}

		/**
		 * Fires after a lead's planning is approved by the client.
		 *
		 * @param int    $lead_id Lead ID.
		 * @param object $lead    Lead row.
		 */
		do_action( 'mbd_crm_planning_approved', $lead_id, $lead );

		$this->redirect( add_query_arg( 'appr', 'approved', $detail ) );
	}

	/**
	 * Render the approval panel for the lead detail sidebar.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$panel = $this->view->capture(
			'crm/approval/panel',
			array(
				'lead'        => $lead,
				'evidence'    => $this->repo->for_lead( (int) $lead->id ),
				'approved'    => Gate::is_approved( $lead ),
				'can_edit'    => Permissions::can_edit( $lead ),
				'nonce_field' => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action' => Router::screen_url( 'leads' ),
				'notice'      => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Handle the optional evidence upload.
	 *
	 * @return array{0:int,1:string} [attachment_id, attachment_url].
	 */
	private function upload_evidence(): array {
		if ( empty( $_FILES['evidence']['name'] ) ) {
			return array( 0, '' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attach_id = media_handle_upload( 'evidence', 0 );

		if ( is_wp_error( $attach_id ) ) {
			return array( 0, '' );
		}

		return array( (int) $attach_id, (string) wp_get_attachment_url( $attach_id ) );
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['appr'] ) ) {
			return Components::notice( __( 'Planning approved. Closing is now unlocked and a closing task was created.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['appr_error'] ) ) {
			return Components::notice( __( 'Approval requires evidence: attach a file or add an approval note.', 'mbd-crm' ), 'danger' );
		}

		return '';
	}

	/**
	 * Read and unslash a scalar POST field as trimmed text.
	 *
	 * @param string $key POST key.
	 * @return string
	 */
	private function post_text( string $key ): string {
		return isset( $_POST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : '';
	}

	/**
	 * Read and unslash a textarea POST field.
	 *
	 * @param string $key POST key.
	 * @return string
	 */
	private function post_textarea( string $key ): string {
		return isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
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
