<?php
/**
 * Front-end controller for the Leads screen.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Handles every request to /crm/leads: list, detail, create, and edit,
 * enforcing permissions and processing form submissions.
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_lead_save';
	private const NONCE_FIELD  = 'mbd_crm_nonce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $repo;

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
		$this->repo = new LeadRepository();
		$this->view = new View();
	}

	/**
	 * Supply the leads screen content to the router.
	 *
	 * @param string|null $content Existing content (null when unhandled).
	 * @param string      $slug    Active screen slug.
	 * @param array|null  $meta    Screen meta.
	 * @return string|null
	 */
	public function maybe_render( $content, string $slug, $meta ) {
		unset( $meta );

		if ( 'leads' !== $slug ) {
			return $content;
		}

		if ( ! Permissions::can_access() ) {
			return Components::blocked( __( 'You do not have access to the Leads area.', 'mbd-crm' ) );
		}

		if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
			$handled = $this->handle_post();
			if ( null !== $handled ) {
				return $handled;
			}
		}

		return $this->render_current();
	}

	/**
	 * Route a GET request to the right view.
	 *
	 * @return string
	 */
	private function render_current(): string {
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$lead_id = isset( $_GET['lead'] ) ? absint( wp_unslash( $_GET['lead'] ) ) : 0;

		if ( 'new' === $action ) {
			if ( ! Permissions::can_create() ) {
				return Components::blocked( __( 'You do not have permission to create leads.', 'mbd-crm' ) );
			}
			return $this->render_form( null );
		}

		if ( 'edit' === $action && $lead_id ) {
			$lead = $this->repo->find( $lead_id );
			if ( ! $lead ) {
				return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
			}
			if ( ! Permissions::can_edit( $lead ) ) {
				return Components::blocked( __( 'You do not have permission to edit this lead.', 'mbd-crm' ) );
			}
			return $this->render_form( $lead );
		}

		if ( $lead_id ) {
			return $this->render_detail( $lead_id );
		}

		return $this->render_list();
	}

	/**
	 * Process a create/update submission.
	 *
	 * Returns re-rendered form HTML on validation failure, or redirects
	 * (and exits) on success.
	 *
	 * @return string|null
	 */
	private function handle_post(): ?string {
		$action = isset( $_POST['mbd_action'] ) ? sanitize_key( wp_unslash( $_POST['mbd_action'] ) ) : '';

		if ( 'create' === $action ) {
			if ( ! Permissions::can_create() ) {
				return Components::blocked( __( 'You do not have permission to create leads.', 'mbd-crm' ) );
			}
			check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

			list( $data, $errors, $values ) = $this->collect( null );
			if ( $errors ) {
				return $this->render_form( null, $values, $errors );
			}

			$id = $this->repo->create( $data, get_current_user_id() );
			$this->redirect( add_query_arg( 'created', '1', Router::screen_url( 'leads' ) . '?lead=' . $id ) );
		}

		if ( 'update' === $action ) {
			$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
			$lead    = $this->repo->find( $lead_id );

			if ( ! $lead ) {
				return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
			}
			if ( ! Permissions::can_edit( $lead ) ) {
				return Components::blocked( __( 'You do not have permission to edit this lead.', 'mbd-crm' ) );
			}
			check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

			list( $data, $errors, $values ) = $this->collect( $lead );
			if ( $errors ) {
				return $this->render_form( $lead, $values, $errors );
			}

			$this->repo->update( $lead_id, $data );
			$this->redirect( add_query_arg( 'updated', '1', Router::screen_url( 'leads' ) . '?lead=' . $lead_id ) );
		}

		/**
		 * Allow other modules (e.g. Qualification) to handle a lead POST
		 * action. A non-null return short-circuits and is rendered in the
		 * shell; handlers that succeed should redirect and exit.
		 *
		 * @param string|null $handled Handler output.
		 * @param string      $action  Submitted action.
		 */
		return apply_filters( 'mbd_crm_leads_post_action', null, $action );
	}

	/**
	 * Read, sanitise, and validate the submitted lead fields.
	 *
	 * @param object|null $existing Lead being edited, if any.
	 * @return array{0: array<string,mixed>, 1: array<string,string>, 2: array<string,mixed>}
	 */
	private function collect( ?object $existing ): array {
		$v = array();

		$v['name']                  = $this->post_text( 'name' );
		$v['whatsapp']              = preg_replace( '/\D+/', '', $this->post_text( 'whatsapp' ) );
		$v['source']                = sanitize_key( $this->post_text( 'source' ) );
		$v['project_type']          = sanitize_key( $this->post_text( 'project_type' ) );
		$v['service_type']          = sanitize_key( $this->post_text( 'service_type' ) );
		$v['urgency']               = sanitize_key( $this->post_text( 'urgency' ) );
		$v['quality']               = sanitize_key( $this->post_text( 'quality' ) );
		$v['status']                = sanitize_key( $this->post_text( 'status' ) );
		$v['estimated_budget']      = $this->post_text( 'estimated_budget' );
		$v['budget_unknown_reason'] = $this->post_text( 'budget_unknown_reason' );
		$v['next_follow_up']        = $this->post_text( 'next_follow_up' );
		$v['notes']                 = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		if ( '' === $v['quality'] ) {
			$v['quality'] = 'unknown';
		}
		if ( '' === $v['status'] ) {
			$v['status'] = 'new';
		}

		// Assignment is only honoured for users who may assign leads.
		if ( Permissions::can_assign() ) {
			$assigned = isset( $_POST['assigned_to'] ) ? absint( wp_unslash( $_POST['assigned_to'] ) ) : 0;
		} else {
			$assigned = $existing ? (int) $existing->assigned_to : 0;
		}
		if ( ! $assigned ) {
			$assigned = get_current_user_id();
		}
		$v['assigned_to'] = $assigned;

		$errors = $this->validate( $v );

		return array( $v, $errors, $v );
	}

	/**
	 * Validate sanitised values, returning field => message errors.
	 *
	 * @param array<string, mixed> $v Sanitised values.
	 * @return array<string, string>
	 */
	private function validate( array $v ): array {
		$errors = array();

		if ( '' === $v['name'] ) {
			$errors['name'] = __( 'A lead name is required.', 'mbd-crm' );
		}

		$selects = array(
			'source'       => 'sources',
			'project_type' => 'project_types',
			'service_type' => 'service_types',
			'urgency'      => 'urgencies',
			'quality'      => 'qualities',
			'status'       => 'statuses',
		);
		foreach ( $selects as $field => $group ) {
			if ( '' !== $v[ $field ] && ! Options::is_valid( $group, $v[ $field ] ) ) {
				$errors[ $field ] = __( 'Please choose a valid option.', 'mbd-crm' );
			}
		}

		if ( '' !== $v['estimated_budget'] && ( ! is_numeric( $v['estimated_budget'] ) || (float) $v['estimated_budget'] < 0 ) ) {
			$errors['estimated_budget'] = __( 'Enter a valid budget amount.', 'mbd-crm' );
		}

		if ( '' === $v['estimated_budget'] && '' === $v['budget_unknown_reason'] ) {
			$errors['budget_unknown_reason'] = __( 'Provide a budget, or a reason it is unknown.', 'mbd-crm' );
		}

		if ( '' !== $v['next_follow_up'] && ! $this->is_valid_date( $v['next_follow_up'] ) ) {
			$errors['next_follow_up'] = __( 'Use a valid date (YYYY-MM-DD).', 'mbd-crm' );
		}

		return $errors;
	}

	/**
	 * Render the lead list, scoped to what the user may see.
	 *
	 * @return string
	 */
	private function render_list(): string {
		$scope = Permissions::can_view_all() ? 'all' : 'own';
		$leads = $this->repo->all(
			array(
				'scope'   => $scope,
				'user_id' => get_current_user_id(),
			)
		);

		$assignees = array();
		foreach ( $leads as $lead ) {
			$assignees[ (int) $lead->assigned_to ] = true;
		}

		return $this->view->capture(
			'crm/leads/list',
			array(
				'leads'      => $leads,
				'names'      => $this->names_for( array_keys( $assignees ) ),
				'can_create' => Permissions::can_create(),
				'scope'      => $scope,
				'notice'     => $this->flash_notice(),
				'new_url'    => Router::screen_url( 'leads' ) . '?action=new',
			)
		);
	}

	/**
	 * Render a single lead's detail view.
	 *
	 * @param int $lead_id Lead ID.
	 * @return string
	 */
	private function render_detail( int $lead_id ): string {
		$lead = $this->repo->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}
		if ( ! Permissions::can_view( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to view this lead.', 'mbd-crm' ) );
		}

		$names    = $this->names_for( array( (int) $lead->assigned_to, (int) $lead->created_by ) );
		$whatsapp = '' !== $lead->whatsapp ? 'https://wa.me/' . rawurlencode( $lead->whatsapp ) : '';

		return $this->view->capture(
			'crm/leads/detail',
			array(
				'lead'          => $lead,
				'names'         => $names,
				'can_edit'      => Permissions::can_edit( $lead ),
				'sla'           => Sla::display( $lead ),
				'whatsapp_link' => $whatsapp,
				'audit'         => Audit::recent_for_lead( $lead_id ),
				'tasks'         => Tasks::for_lead( $lead_id ),
				'edit_url'      => Router::screen_url( 'leads' ) . '?action=edit&lead=' . $lead_id,
				'list_url'      => Router::screen_url( 'leads' ),
				'notice'        => $this->flash_notice(),
			)
		);
	}

	/**
	 * Render the create/edit form.
	 *
	 * @param object|null               $existing Lead being edited.
	 * @param array<string, mixed>|null $values   Submitted values to repopulate.
	 * @param array<string, string>     $errors   Validation errors.
	 * @return string
	 */
	private function render_form( ?object $existing, ?array $values = null, array $errors = array() ): string {
		$values = $values ?? $this->values_from( $existing );

		return $this->view->capture(
			'crm/leads/form',
			array(
				'existing'    => $existing,
				'values'      => $values,
				'errors'      => $errors,
				'can_assign'  => Permissions::can_assign(),
				'sales_users' => $this->assignable_users(),
				'nonce_field' => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action' => Router::screen_url( 'leads' ),
				'list_url'    => Router::screen_url( 'leads' ),
				'mode'        => $existing ? 'update' : 'create',
			)
		);
	}

	/**
	 * Build the default value set from an existing lead (or blanks).
	 *
	 * @param object|null $existing Lead being edited.
	 * @return array<string, mixed>
	 */
	private function values_from( ?object $existing ): array {
		$defaults = array(
			'name'                  => '',
			'whatsapp'              => '',
			'source'                => '',
			'project_type'          => '',
			'service_type'          => '',
			'urgency'               => '',
			'quality'               => 'unknown',
			'status'                => 'new',
			'estimated_budget'      => '',
			'budget_unknown_reason' => '',
			'next_follow_up'        => '',
			'notes'                 => '',
			'assigned_to'           => get_current_user_id(),
		);

		if ( ! $existing ) {
			return $defaults;
		}

		foreach ( array_keys( $defaults ) as $key ) {
			if ( isset( $existing->$key ) && null !== $existing->$key ) {
				$defaults[ $key ] = $existing->$key;
			}
		}

		return $defaults;
	}

	/**
	 * Users who can be assigned leads.
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
	 * Resolve user IDs to display names.
	 *
	 * @param int[] $ids User IDs.
	 * @return array<int, string>
	 */
	private function names_for( array $ids ): array {
		$names = array();

		foreach ( array_unique( array_filter( array_map( 'intval', $ids ) ) ) as $id ) {
			$user           = get_userdata( $id );
			$names[ $id ]   = $user ? $user->display_name : __( 'Unknown', 'mbd-crm' );
		}

		return $names;
	}

	/**
	 * Build a success notice from PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['created'] ) ) {
			return Components::notice( __( 'Lead created and assigned. First task and response SLA started.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['updated'] ) ) {
			return Components::notice( __( 'Lead updated.', 'mbd-crm' ), 'success' );
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
