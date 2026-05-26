<?php
/**
 * Follow-up & promise request handling and detail panel.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\FollowUp;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Processes follow-up logging and promise actions submitted from the lead
 * detail page, and renders the follow-up panel there.
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_followup';
	private const NONCE_FIELD  = 'mbd_crm_funonce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Follow-up persistence.
	 *
	 * @var FollowUpRepository
	 */
	private FollowUpRepository $followups;

	/**
	 * Promise persistence.
	 *
	 * @var PromiseRepository
	 */
	private PromiseRepository $promises;

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
		$this->followups = new FollowUpRepository();
		$this->promises  = new PromiseRepository();
		$this->view      = new View();
	}

	/**
	 * Handle a follow-up / promise submission from the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		if ( ! in_array( $action, array( 'log_followup', 'add_promise', 'update_promise' ), true ) ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}
		if ( ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to update this lead.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;

		if ( 'log_followup' === $action ) {
			$this->handle_log( $lead, $detail );
		}

		if ( 'add_promise' === $action ) {
			$this->handle_add_promise( $lead, $detail );
		}

		$this->handle_update_promise( $lead, $detail );
	}

	/**
	 * Log a follow-up entry.
	 *
	 * @param object $lead   Lead row.
	 * @param string $detail Detail URL.
	 * @return void
	 */
	private function handle_log( object $lead, string $detail ): void {
		$channel = sanitize_key( $this->post_text( 'channel' ) );
		if ( ! Options::is_channel( $channel ) ) {
			$channel = 'other';
		}

		$summary = isset( $_POST['summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['summary'] ) ) : '';
		if ( '' === trim( $summary ) ) {
			$this->redirect( add_query_arg( 'fu_error', 'summary', $detail ) );
		}

		$next_follow_up = $this->post_text( 'next_follow_up' );
		if ( '' !== $next_follow_up && ! $this->is_valid_date( $next_follow_up ) ) {
			$this->redirect( add_query_arg( 'fu_error', 'date', $detail ) );
		}

		$data = array(
			'channel'        => $channel,
			'summary'        => $summary,
			'result'         => isset( $_POST['result'] ) ? sanitize_textarea_field( wp_unslash( $_POST['result'] ) ) : '',
			'next_action'    => $this->post_text( 'next_action' ),
			'next_follow_up' => $next_follow_up,
		);

		$id = $this->followups->log( (int) $lead->id, $data, get_current_user_id() );

		// Surface the latest next action / date on the lead itself.
		$this->leads->set_next( (int) $lead->id, $data['next_action'], $next_follow_up );

		/**
		 * Fires after a follow-up is logged.
		 *
		 * @param int    $lead_id  Lead ID.
		 * @param object $lead     Lead row.
		 * @param object $followup Follow-up row.
		 */
		do_action( 'mbd_crm_followup_logged', (int) $lead->id, $lead, $this->followups->find( $id ) );

		$this->redirect( add_query_arg( 'fu', '1', $detail ) );
	}

	/**
	 * Record a promise.
	 *
	 * @param object $lead   Lead row.
	 * @param string $detail Detail URL.
	 * @return void
	 */
	private function handle_add_promise( object $lead, string $detail ): void {
		$description = $this->post_text( 'description' );
		if ( '' === $description ) {
			$this->redirect( add_query_arg( 'promise_error', 'desc', $detail ) );
		}

		$due = $this->post_text( 'due_date' );
		if ( '' === $due || ! $this->is_valid_date( $due ) ) {
			$this->redirect( add_query_arg( 'promise_error', 'due', $detail ) );
		}

		$id = $this->promises->create( (int) $lead->id, $description, $due, get_current_user_id() );

		/**
		 * Fires after a promise is created.
		 *
		 * @param int    $lead_id Lead ID.
		 * @param object $lead    Lead row.
		 * @param object $promise Promise row.
		 */
		do_action( 'mbd_crm_promise_created', (int) $lead->id, $lead, $this->promises->find( $id ) );

		$this->redirect( add_query_arg( 'promise', '1', $detail ) );
	}

	/**
	 * Update a promise's status.
	 *
	 * @param object $lead   Lead row.
	 * @param string $detail Detail URL.
	 * @return void
	 */
	private function handle_update_promise( object $lead, string $detail ): void {
		$promise_id = isset( $_POST['promise_id'] ) ? absint( wp_unslash( $_POST['promise_id'] ) ) : 0;
		$promise    = $this->promises->find( $promise_id );

		if ( ! $promise || (int) $promise->lead_id !== (int) $lead->id ) {
			$this->redirect( $detail );
		}

		$status = sanitize_key( $this->post_text( 'status' ) );
		if ( ! Options::is_promise_status( $status ) ) {
			$this->redirect( add_query_arg( 'promise_error', 'status', $detail ) );
		}

		$from = (string) $promise->status;
		if ( $from !== $status ) {
			$this->promises->update_status( $promise_id, $status );

			/**
			 * Fires when a promise's status changes.
			 *
			 * @param int    $lead_id    Lead ID.
			 * @param int    $promise_id Promise ID.
			 * @param string $from       Previous status.
			 * @param string $to         New status.
			 */
			do_action( 'mbd_crm_promise_status_changed', (int) $lead->id, $promise_id, $from, $status );
		}

		$this->redirect( add_query_arg( 'promise_updated', '1', $detail ) );
	}

	/**
	 * Render the follow-up & promise panel for the lead detail sidebar.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$panel = $this->view->capture(
			'crm/followup/panel',
			array(
				'lead'        => $lead,
				'history'     => $this->followups->for_lead( (int) $lead->id ),
				'promises'    => $this->promises->for_lead( (int) $lead->id ),
				'can_edit'    => Permissions::can_edit( $lead ),
				'nonce_field' => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action' => Router::screen_url( 'leads' ),
				'notice'      => $this->flash_notice(),
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
		if ( isset( $_GET['fu'] ) ) {
			return Components::notice( __( 'Follow-up logged and next task created.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['promise'] ) ) {
			return Components::notice( __( 'Promise recorded.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['promise_updated'] ) ) {
			return Components::notice( __( 'Promise status updated.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['fu_error'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['fu_error'] ) );
			return Components::notice(
				'date' === $code
					? __( 'Enter a valid next follow-up date.', 'mbd-crm' )
					: __( 'A summary is required to log a follow-up.', 'mbd-crm' ),
				'danger'
			);
		}
		if ( isset( $_GET['promise_error'] ) ) {
			return Components::notice( __( 'A promise needs a description and a valid due date.', 'mbd-crm' ), 'danger' );
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
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		return trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
	}

	/**
	 * Strict Y-m-d date validation.
	 *
	 * @param string $value Candidate date.
	 * @return bool
	 */
	private function is_valid_date( string $value ): bool {
		$date = \DateTime::createFromFormat( 'Y-m-d', $value );

		return $date instanceof \DateTime && $date->format( 'Y-m-d' ) === $value;
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
