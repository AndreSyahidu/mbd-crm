<?php
/**
 * Planning request handling and detail panel.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Planning;

use MBD\CRM\Deposit\Gate;
use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Capabilities;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Processes planning create/update, deliverable, and revision actions and
 * renders the planning panel on the lead detail page.
 */
class Controller {

	private const NONCE_ACTION   = 'mbd_crm_planning';
	private const NONCE_FIELD    = 'mbd_crm_plnonce';
	private const SLA_DAYS       = 7;

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Planning persistence.
	 *
	 * @var PlanningRepository
	 */
	private PlanningRepository $repo;

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
		$this->repo  = new PlanningRepository();
		$this->view  = new View();
	}

	/**
	 * Handle a planning action submitted from the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		$actions = array( 'create_planning', 'update_planning', 'add_deliverable', 'add_revision' );
		if ( ! in_array( $action, $actions, true ) ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}
		if ( ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to manage planning for this lead.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;

		if ( 'create_planning' === $action ) {
			// Rule: planning cannot start before deposit valid or override.
			if ( ! Gate::can_plan( $lead ) ) {
				$this->redirect( add_query_arg( 'plan_error', 'gate', $detail ) );
			}
			if ( $this->repo->for_lead( $lead_id ) ) {
				$this->redirect( $detail );
			}

			$planner = isset( $_POST['planner_id'] ) ? absint( wp_unslash( $_POST['planner_id'] ) ) : 0;
			$id      = $this->repo->create(
				$lead_id,
				$planner,
				$this->post_textarea( 'scope' ),
				$this->post_text( 'target_date' ),
				$this->sla_due(),
				get_current_user_id()
			);

			/**
			 * Fires after a planning is created.
			 *
			 * @param int    $lead_id  Lead ID.
			 * @param object $lead     Lead row.
			 * @param object $planning Planning row.
			 */
			do_action( 'mbd_crm_planning_created', $lead_id, $lead, $this->repo->find( $id ) );
			$this->redirect( add_query_arg( 'plan', 'created', $detail ) );
		}

		$planning = $this->repo->for_lead( $lead_id );
		if ( ! $planning ) {
			$this->redirect( $detail );
		}

		if ( 'update_planning' === $action ) {
			$this->handle_update( $lead_id, (int) $planning->id, $detail );
		}

		if ( 'add_deliverable' === $action ) {
			$this->handle_deliverable( $lead_id, (int) $planning->id, $detail );
		}

		// add_revision.
		$note = $this->post_textarea( 'revision_note' );
		if ( '' === trim( $note ) ) {
			$this->redirect( add_query_arg( 'plan_error', 'revision', $detail ) );
		}
		$deliverable_id = isset( $_POST['deliverable_id'] ) ? absint( wp_unslash( $_POST['deliverable_id'] ) ) : 0;
		$this->repo->add_revision( (int) $planning->id, $lead_id, $deliverable_id, $note, get_current_user_id() );
		do_action( 'mbd_crm_planning_event', $lead_id, 'revision added' );
		$this->redirect( add_query_arg( 'plan', 'revision', $detail ) );
	}

	/**
	 * Apply a planning update.
	 *
	 * @param int    $lead_id     Lead ID.
	 * @param int    $planning_id Planning ID.
	 * @param string $detail      Detail URL.
	 * @return void
	 */
	private function handle_update( int $lead_id, int $planning_id, string $detail ): void {
		$status = sanitize_key( $this->post_text( 'status' ) );
		$review = sanitize_key( $this->post_text( 'internal_review' ) );

		if ( '' !== $status && ! Options::is_editable_status( $status ) ) {
			$this->redirect( $detail );
		}
		if ( '' !== $review && ! Options::is_review( $review ) ) {
			$this->redirect( $detail );
		}

		// Rule: planning final requires at least one deliverable.
		if ( 'final' === $status && $this->repo->deliverable_count( $planning_id ) < 1 ) {
			$this->redirect( add_query_arg( 'plan_error', 'deliverable', $detail ) );
		}

		$fields = array(
			'planner_id'  => isset( $_POST['planner_id'] ) ? absint( wp_unslash( $_POST['planner_id'] ) ) : 0,
			'scope'       => $this->post_textarea( 'scope' ),
			'target_date' => $this->post_text( 'target_date' ),
		);
		if ( '' !== $status ) {
			$fields['status'] = $status;
		}
		if ( '' !== $review ) {
			$fields['internal_review'] = $review;
		}

		$this->repo->update( $planning_id, $fields );

		$event = '' !== $status
			? sprintf( /* translators: %s: status. */ __( 'status → %s', 'mbd-crm' ), Options::status_label( $status ) )
			: 'updated';
		do_action( 'mbd_crm_planning_event', $lead_id, $event );

		$this->redirect( add_query_arg( 'plan', 'updated', $detail ) );
	}

	/**
	 * Add a deliverable.
	 *
	 * @param int    $lead_id     Lead ID.
	 * @param int    $planning_id Planning ID.
	 * @param string $detail      Detail URL.
	 * @return void
	 */
	private function handle_deliverable( int $lead_id, int $planning_id, string $detail ): void {
		$type = sanitize_key( $this->post_text( 'deliverable_type' ) );
		if ( ! Options::is_deliverable_type( $type ) ) {
			$type = 'other';
		}

		$title = $this->post_text( 'deliverable_title' );
		if ( '' === $title ) {
			$this->redirect( add_query_arg( 'plan_error', 'title', $detail ) );
		}

		$this->repo->add_deliverable( $planning_id, $lead_id, $type, $title, $this->post_textarea( 'deliverable_notes' ), get_current_user_id() );

		$version = $this->repo->next_version( $planning_id, $type ) - 1;
		do_action(
			'mbd_crm_planning_event',
			$lead_id,
			sprintf(
				/* translators: 1: deliverable type, 2: version. */
				__( 'deliverable added (%1$s v%2$d)', 'mbd-crm' ),
				Options::deliverable_label( $type ),
				$version
			)
		);

		$this->redirect( add_query_arg( 'plan', 'deliverable', $detail ) );
	}

	/**
	 * Render the planning panel for the lead detail sidebar.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$planning = $this->repo->for_lead( (int) $lead->id );

		$panel = $this->view->capture(
			'crm/planning/panel',
			array(
				'lead'         => $lead,
				'planning'     => $planning,
				'deliverables' => $planning ? $this->repo->deliverables( (int) $planning->id ) : array(),
				'revisions'    => $planning ? $this->repo->revisions( (int) $planning->id ) : array(),
				'can_edit'     => Permissions::can_edit( $lead ),
				'can_plan'     => Gate::can_plan( $lead ),
				'planners'     => $this->assignable_users(),
				'nonce_field'  => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action'  => Router::screen_url( 'leads' ),
				'notice'       => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Users who can be assigned as planner.
	 *
	 * @return array<int, string>
	 */
	private function assignable_users(): array {
		$users = get_users(
			array(
				'capability' => Capabilities::EDIT_LEADS,
				'fields'     => array( 'ID', 'display_name' ),
				'number'     => 200,
			)
		);

		$map = array();
		foreach ( $users as $user ) {
			$map[ (int) $user->ID ] = $user->display_name;
		}

		return $map;
	}

	/**
	 * Compute the planning SLA due datetime.
	 *
	 * @return string
	 */
	private function sla_due(): string {
		/**
		 * Filter the planning SLA window, in days.
		 *
		 * @param int $days Default window.
		 */
		$days = (int) apply_filters( 'mbd_crm_planning_sla_days', self::SLA_DAYS );

		return gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['plan'] ) ) {
			return Components::notice( __( 'Planning updated.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['plan_error'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['plan_error'] ) );
			if ( 'gate' === $code ) {
				return Components::notice( __( 'Planning cannot start until the deposit is valid or overridden.', 'mbd-crm' ), 'danger' );
			}
			if ( 'deliverable' === $code ) {
				return Components::notice( __( 'Add at least one deliverable before marking planning final.', 'mbd-crm' ), 'danger' );
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
