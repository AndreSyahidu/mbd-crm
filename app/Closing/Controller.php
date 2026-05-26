<?php
/**
 * Closing & negotiation request handling and detail panel.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Closing;

use MBD\CRM\Approval\Gate;
use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Capabilities;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Processes offer, negotiation, approval, and outcome actions for closing,
 * and renders the closing panel on the lead detail page.
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_closing';
	private const NONCE_FIELD  = 'mbd_crm_clnonce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Closing persistence.
	 *
	 * @var ClosingRepository
	 */
	private ClosingRepository $repo;

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
		$this->repo  = new ClosingRepository();
		$this->view  = new View();
	}

	/**
	 * Handle a closing action submitted from the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		$actions = array( 'save_offer', 'mark_proposal_sent', 'add_negotiation', 'request_closing_approval', 'approve_closing', 'reject_closing', 'fail_closing' );
		if ( ! in_array( $action, $actions, true ) ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		// Rule: closing requires planning approved.
		if ( ! Gate::is_approved( $lead ) ) {
			return Components::blocked( __( 'Closing is blocked until planning is approved by the client.', 'mbd-crm' ) );
		}

		$is_approval = in_array( $action, array( 'approve_closing', 'reject_closing' ), true );

		if ( $is_approval ) {
			// Rule: approval requires an authorised Owner/Approver.
			if ( ! current_user_can( Capabilities::APPROVE_CLOSING ) ) {
				return Components::blocked( __( 'You are not authorised to approve or reject closings.', 'mbd-crm' ) );
			}
		} elseif ( ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to manage closing for this lead.', 'mbd-crm' ) );
		}

		$detail  = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;
		$closing = $this->repo->for_lead( $lead_id );
		if ( ! $closing ) {
			$id      = $this->repo->create( $lead_id, get_current_user_id() );
			$closing = $this->repo->for_lead( $lead_id );
		}
		$closing_id = (int) $closing->id;

		switch ( $action ) {
			case 'save_offer':
				$this->save_offer( $lead, $closing, $detail );
				break;
			case 'mark_proposal_sent':
				$this->repo->update( $closing_id, array( 'proposal_sent' => 1, 'proposal_sent_at' => current_time( 'mysql' ), 'status' => 'negotiating' ) );
				$this->fire( $lead_id, $lead, $closing_id, 'proposal_sent', '' );
				$this->redirect( add_query_arg( 'cl', 'sent', $detail ) );
				break;
			case 'add_negotiation':
				$this->add_negotiation( $lead, $closing_id, $detail );
				break;
			case 'request_closing_approval':
				$this->repo->update( $closing_id, array( 'status' => 'waiting_approval' ) );
				$this->leads->set_stage( $lead_id, array( 'closing_status' => 'waiting' ) );
				$this->fire( $lead_id, $lead, $closing_id, 'approval_requested', '' );
				$this->redirect( add_query_arg( 'cl', 'requested', $detail ) );
				break;
			case 'approve_closing':
				$this->approve( $lead, $closing, $detail );
				break;
			case 'reject_closing':
				$this->repo->update( $closing_id, array( 'status' => 'rejected' ) );
				$this->leads->set_stage( $lead_id, array( 'closing_status' => 'rejected' ) );
				$this->fire( $lead_id, $lead, $closing_id, 'rejected', '' );
				$this->redirect( add_query_arg( 'cl', 'rejected', $detail ) );
				break;
			case 'fail_closing':
				$this->fail( $lead, $closing_id, $detail );
				break;
		}

		$this->redirect( $detail );
	}

	/**
	 * Save the offer/proposal values.
	 *
	 * @param object $lead    Lead row.
	 * @param object $closing Closing row.
	 * @param string $detail  Detail URL.
	 * @return void
	 */
	private function save_offer( object $lead, object $closing, string $detail ): void {
		foreach ( array( 'estimated_value', 'offered_value', 'final_value' ) as $key ) {
			$val = $this->post_text( $key );
			if ( '' !== $val && ( ! is_numeric( $val ) || (float) $val < 0 ) ) {
				$this->redirect( add_query_arg( 'cl_error', 'value', $detail ) );
			}
		}

		$probability = (int) $this->post_text( 'probability' );
		$probability = max( 0, min( 100, $probability ) );

		$fields = array(
			'estimated_value'     => $this->post_text( 'estimated_value' ),
			'offered_value'       => $this->post_text( 'offered_value' ),
			'final_value'         => $this->post_text( 'final_value' ),
			'probability'         => $probability,
			'expected_close_date' => $this->post_text( 'expected_close_date' ),
			'next_action'         => $this->post_text( 'next_action' ),
			'competitor_note'     => $this->post_textarea( 'competitor_note' ),
		);
		if ( 'open' === $closing->status ) {
			$fields['status'] = 'negotiating';
		}

		$this->repo->update( (int) $closing->id, $fields );
		$this->leads->set_stage( (int) $lead->id, array( 'closing_status' => 'negotiating' ) );
		$this->fire( (int) $lead->id, $lead, (int) $closing->id, 'offer_saved', '' );
		$this->redirect( add_query_arg( 'cl', 'saved', $detail ) );
	}

	/**
	 * Add a negotiation-log entry.
	 *
	 * @param object $lead       Lead row.
	 * @param int    $closing_id Closing ID.
	 * @param string $detail     Detail URL.
	 * @return void
	 */
	private function add_negotiation( object $lead, int $closing_id, string $detail ): void {
		$type    = 'objection' === sanitize_key( $this->post_text( 'entry_type' ) ) ? 'objection' : 'note';
		$content = $this->post_textarea( 'content' );

		if ( '' === trim( $content ) ) {
			$this->redirect( add_query_arg( 'cl_error', 'content', $detail ) );
		}

		$this->repo->add_negotiation( $closing_id, (int) $lead->id, $type, $content, get_current_user_id() );
		$this->fire( (int) $lead->id, $lead, $closing_id, 'negotiation', '' );
		$this->redirect( add_query_arg( 'cl', 'negotiation', $detail ) );
	}

	/**
	 * Approve the closing (CRM endpoint) and seed a project draft.
	 *
	 * @param object $lead    Lead row.
	 * @param object $closing Closing row.
	 * @param string $detail  Detail URL.
	 * @return void
	 */
	private function approve( object $lead, object $closing, string $detail ): void {
		$fields = array(
			'status'        => 'approved',
			'project_draft' => 1,
			'approved_at'   => current_time( 'mysql' ),
		);

		// Default the final value to the offered value when not set.
		if ( ( null === $closing->final_value || '' === (string) $closing->final_value ) && null !== $closing->offered_value ) {
			$fields['final_value'] = $closing->offered_value;
		}

		$this->repo->update( (int) $closing->id, $fields );
		$this->leads->set_stage( (int) $lead->id, array( 'closing_status' => 'won' ) );

		/**
		 * Bridge hook: generate a project draft for future ERP / project
		 * management integration.
		 *
		 * @param int    $lead_id Lead ID.
		 * @param object $lead    Lead row.
		 */
		do_action( 'mbd_crm_generate_project_draft', (int) $lead->id, $lead );

		$this->fire( (int) $lead->id, $lead, (int) $closing->id, 'approved', '' );
		$this->redirect( add_query_arg( 'cl', 'approved', $detail ) );
	}

	/**
	 * Mark the closing failed (lost). Requires a lost reason.
	 *
	 * @param object $lead       Lead row.
	 * @param int    $closing_id Closing ID.
	 * @param string $detail     Detail URL.
	 * @return void
	 */
	private function fail( object $lead, int $closing_id, string $detail ): void {
		// Rule: a failed closing requires a lost reason.
		$reason = $this->post_text( 'lost_reason' );
		if ( '' === $reason ) {
			$this->redirect( add_query_arg( 'cl_error', 'lost', $detail ) );
		}

		$this->repo->update( $closing_id, array( 'status' => 'lost', 'lost_reason' => $reason ) );
		$this->leads->set_stage( (int) $lead->id, array( 'closing_status' => 'lost' ) );
		$this->fire( (int) $lead->id, $lead, $closing_id, 'lost', $reason );
		$this->redirect( add_query_arg( 'cl', 'lost', $detail ) );
	}

	/**
	 * Render the closing panel for the lead detail sidebar.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$closing = $this->repo->for_lead( (int) $lead->id );

		$panel = $this->view->capture(
			'crm/closing/panel',
			array(
				'lead'         => $lead,
				'closing'      => $closing,
				'negotiations' => $closing ? $this->repo->negotiations( (int) $closing->id ) : array(),
				'approved'     => Gate::is_approved( $lead ),
				'can_edit'     => Permissions::can_edit( $lead ),
				'can_approve'  => current_user_can( Capabilities::APPROVE_CLOSING ),
				'nonce_field'  => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action'  => Router::screen_url( 'leads' ),
				'notice'       => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Fire the closing-changed action with a fresh closing row.
	 *
	 * @param int    $lead_id    Lead ID.
	 * @param object $lead       Lead row.
	 * @param int    $closing_id Closing ID.
	 * @param string $event      Event key.
	 * @param string $reason     Reason (lost).
	 * @return void
	 */
	private function fire( int $lead_id, object $lead, int $closing_id, string $event, string $reason ): void {
		/**
		 * Fires when a closing record changes.
		 *
		 * @param int    $lead_id    Lead ID.
		 * @param object $lead       Lead row.
		 * @param object $closing    Closing row.
		 * @param string $event      Event key.
		 * @param string $reason     Reason.
		 */
		do_action( 'mbd_crm_closing_changed', $lead_id, $lead, $this->repo->for_lead( $lead_id ), $event, $reason );
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['cl'] ) ) {
			$state = sanitize_key( wp_unslash( $_GET['cl'] ) );
			$map   = array(
				'saved'       => array( __( 'Offer saved.', 'mbd-crm' ), 'success' ),
				'sent'        => array( __( 'Proposal marked as sent.', 'mbd-crm' ), 'success' ),
				'negotiation' => array( __( 'Negotiation entry added.', 'mbd-crm' ), 'success' ),
				'requested'   => array( __( 'Closing approval requested.', 'mbd-crm' ), 'success' ),
				'approved'    => array( __( 'Closing approved — deal won.', 'mbd-crm' ), 'success' ),
				'rejected'    => array( __( 'Closing approval rejected.', 'mbd-crm' ), 'warning' ),
				'lost'        => array( __( 'Closing marked as lost.', 'mbd-crm' ), 'warning' ),
			);
			if ( isset( $map[ $state ] ) ) {
				return Components::notice( $map[ $state ][0], $map[ $state ][1] );
			}
		}
		if ( isset( $_GET['cl_error'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['cl_error'] ) );
			if ( 'lost' === $code ) {
				return Components::notice( __( 'A lost reason is required to fail a closing.', 'mbd-crm' ), 'danger' );
			}
			if ( 'value' === $code ) {
				return Components::notice( __( 'Enter valid project values.', 'mbd-crm' ), 'danger' );
			}
			return Components::notice( __( 'Please complete the required fields.', 'mbd-crm' ), 'danger' );
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
