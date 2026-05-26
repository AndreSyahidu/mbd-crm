<?php
/**
 * Stakeholder request handling and detail panel.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Stakeholders;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Manages stakeholders for a lead and renders the stakeholder panel,
 * including a missing-decision-maker warning for hot/warm leads.
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_stakeholder';
	private const NONCE_FIELD  = 'mbd_crm_sknonce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Stakeholder persistence.
	 *
	 * @var StakeholderRepository
	 */
	private StakeholderRepository $repo;

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
		$this->repo  = new StakeholderRepository();
		$this->view  = new View();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_leads_post_action', array( $this, 'handle' ), 10, 2 );
		add_filter( 'mbd_crm_lead_detail_panels', array( $this, 'render_panel' ), 15, 2 );
	}

	/**
	 * Handle a stakeholder action.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		$actions = array( 'add_stakeholder', 'set_primary_stakeholder', 'delete_stakeholder' );
		if ( ! in_array( $action, $actions, true ) ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}
		if ( ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to manage stakeholders for this lead.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;

		if ( 'add_stakeholder' === $action ) {
			$name = $this->post_text( 'name' );
			if ( '' === $name ) {
				$this->redirect( add_query_arg( 'sk_error', 'name', $detail ) );
			}

			$role  = sanitize_key( $this->post_text( 'role' ) );
			$role  = isset( StakeholderRepository::roles()[ $role ] ) ? $role : 'other';
			$power = sanitize_key( $this->post_text( 'decision_power' ) );
			$power = isset( StakeholderRepository::powers()[ $power ] ) ? $power : 'unknown';

			$primary = ! empty( $_POST['is_primary_decision_maker'] );
			if ( $primary ) {
				$this->repo->clear_primary( $lead_id );
			}

			$this->repo->create(
				$lead_id,
				array(
					'name'                      => $name,
					'role'                      => $role,
					'phone'                     => preg_replace( '/\D+/', '', $this->post_text( 'phone' ) ),
					'email'                     => sanitize_email( $this->post_text( 'email' ) ),
					'decision_power'            => $power,
					'relationship_note'         => $this->post_text( 'relationship_note' ),
					'is_primary_decision_maker' => $primary,
				),
				get_current_user_id()
			);

			$this->audit( $lead_id, 'added', $name );
			$this->redirect( add_query_arg( 'sk', 'added', $detail ) );
		}

		$stakeholder_id = isset( $_POST['stakeholder_id'] ) ? absint( wp_unslash( $_POST['stakeholder_id'] ) ) : 0;
		$stakeholder    = $this->repo->find( $stakeholder_id );
		if ( ! $stakeholder || (int) $stakeholder->lead_id !== $lead_id ) {
			$this->redirect( $detail );
		}

		if ( 'set_primary_stakeholder' === $action ) {
			$this->repo->set_primary( $stakeholder_id, $lead_id );
			$this->audit( $lead_id, 'set primary', $stakeholder->name );
			$this->redirect( add_query_arg( 'sk', 'primary', $detail ) );
		}

		// delete_stakeholder.
		$this->repo->delete( $stakeholder_id );
		$this->audit( $lead_id, 'removed', $stakeholder->name );
		$this->redirect( add_query_arg( 'sk', 'removed', $detail ) );
	}

	/**
	 * Render the stakeholder panel.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$stakeholders = $this->repo->for_lead( (int) $lead->id );
		$has_dm       = $this->repo->has_decision_maker( (int) $lead->id );

		// Hot/warm proxy until lead scoring (Task 17): quality A or B.
		$is_hot_warm = in_array( (string) ( $lead->quality ?? '' ), array( 'A', 'B' ), true );

		$panel = $this->view->capture(
			'crm/stakeholders/panel',
			array(
				'lead'         => $lead,
				'stakeholders' => $stakeholders,
				'roles'        => StakeholderRepository::roles(),
				'powers'       => StakeholderRepository::powers(),
				'warn_no_dm'   => $is_hot_warm && ! $has_dm,
				'can_edit'     => Permissions::can_edit( $lead ),
				'nonce_field'  => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action'  => Router::screen_url( 'leads' ),
				'notice'       => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Record a stakeholder audit entry.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $event   Event description.
	 * @param string $name    Stakeholder name.
	 * @return void
	 */
	private function audit( int $lead_id, string $event, string $name ): void {
		Audit::record(
			$lead_id,
			Audit::STAKEHOLDER,
			array(
				'event' => $event,
				'name'  => $name,
			)
		);
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['sk'] ) ) {
			return Components::notice( __( 'Stakeholders updated.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['sk_error'] ) ) {
			return Components::notice( __( 'A stakeholder name is required.', 'mbd-crm' ), 'danger' );
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
