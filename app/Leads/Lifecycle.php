<?php
/**
 * Lead lifecycle actions: on-hold, archive, and reactivate.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

use MBD\CRM\FollowUp\PromiseRepository;
use MBD\CRM\Frontend\Components;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the on-hold / archive / reactivate actions (each requiring a
 * reason and audited) and renders the lifecycle + stage-history panel on the
 * lead detail page.
 */
class Lifecycle {

	private const NONCE_ACTION = 'mbd_crm_lifecycle';
	private const NONCE_FIELD  = 'mbd_crm_lcnonce';

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
		add_filter( 'mbd_crm_leads_post_action', array( $this, 'handle' ), 10, 2 );
		add_filter( 'mbd_crm_lead_detail_panels', array( $this, 'render_panel' ), 80, 2 );
	}

	/**
	 * Handle a lifecycle action submitted from the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		$actions = array( 'move_on_hold', 'archive_lead', 'reactivate_lead' );
		if ( ! in_array( $action, $actions, true ) ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}
		if ( ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to change this lead\'s status.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;
		$reason = $this->post_text( 'reason' );

		// Rule: every lifecycle change requires a reason.
		if ( '' === $reason ) {
			$this->redirect( add_query_arg( 'lc_error', 'reason', $detail ) );
		}

		if ( 'move_on_hold' === $action ) {
			$this->leads->set_lifecycle( $lead_id, 'on_hold', array(), $reason );
			$this->audit( $lead_id, 'put on hold', $reason );
			$this->redirect( add_query_arg( 'lc', 'on_hold', $detail ) );
		}

		if ( 'archive_lead' === $action ) {
			$override = $this->post_text( 'override_reason' );

			// Rule: archive is blocked while open tasks/promises exist unless
			// an override reason is supplied.
			if ( $this->has_open_work( $lead_id ) && '' === $override ) {
				$this->redirect( add_query_arg( 'lc_error', 'open_work', $detail ) );
			}

			$full = '' !== $override ? $reason . ' — ' . __( 'override:', 'mbd-crm' ) . ' ' . $override : $reason;
			$this->leads->set_lifecycle( $lead_id, 'archived', array(), $full );
			$this->audit( $lead_id, 'archived', $full );
			$this->redirect( add_query_arg( 'lc', 'archived', $detail ) );
		}

		// reactivate_lead. Allowed from closing_failed, on_hold, or archived.
		$reactivatable = 'lost' === $lead->closing_status
			|| in_array( $lead->lifecycle, array( 'on_hold', 'archived' ), true );

		if ( ! $reactivatable ) {
			$this->redirect( add_query_arg( 'lc_error', 'not_reactivatable', $detail ) );
		}

		$extra = array(
			'reactivation_at'     => current_time( 'mysql' ),
			'reactivation_reason' => $reason,
		);
		// Re-entering the pipeline clears a failed closing state.
		if ( 'lost' === $lead->closing_status ) {
			$extra['closing_status'] = '';
		}

		$this->leads->set_lifecycle( $lead_id, 'active', $extra, $reason );

		Tasks::create_task(
			$lead_id,
			(int) ( $lead->assigned_to ? $lead->assigned_to : $lead->created_by ),
			sprintf(
				/* translators: %s: lead name. */
				__( 'Reactivated lead — re-engage: %s', 'mbd-crm' ),
				(string) $lead->name
			)
		);

		$this->audit( $lead_id, 'reactivated', $reason );
		$this->redirect( add_query_arg( 'lc', 'reactivated', $detail ) );
	}

	/**
	 * Render the lifecycle + stage-history panel.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$staleness = Stage::staleness( $lead );

		$panel = $this->view->capture(
			'crm/lifecycle/panel',
			array(
				'lead'        => $lead,
				'stage'       => Stage::key( $lead ),
				'stage_label' => Stage::label( Stage::key( $lead ) ),
				'aging'       => Stage::aging_label( $lead ),
				'stale'       => ! empty( $staleness['stale'] ) || (int) ( $lead->stale_flag ?? 0 ) === 1,
				'stale_reason' => '' !== (string) ( $lead->stale_reason ?? '' ) ? $lead->stale_reason : ( $staleness['reason'] ?? '' ),
				'history'     => $this->leads->stage_history( (int) $lead->id ),
				'can_edit'    => Permissions::can_edit( $lead ),
				'has_open'    => $this->has_open_work( (int) $lead->id ),
				'nonce_field' => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action' => Router::screen_url( 'leads' ),
				'notice'      => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Whether a lead has open tasks or open promises.
	 *
	 * @param int $lead_id Lead ID.
	 * @return bool
	 */
	private function has_open_work( int $lead_id ): bool {
		foreach ( Tasks::for_lead( $lead_id ) as $task ) {
			if ( 'open' === $task->status ) {
				return true;
			}
		}
		foreach ( ( new PromiseRepository() )->for_lead( $lead_id ) as $promise ) {
			if ( 'open' === $promise->status ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Record a lifecycle audit entry.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $event   Event description.
	 * @param string $reason  Reason.
	 * @return void
	 */
	private function audit( int $lead_id, string $event, string $reason ): void {
		Audit::record(
			$lead_id,
			Audit::LIFECYCLE,
			array(
				'event'  => $event,
				'reason' => $reason,
			)
		);
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['lc'] ) ) {
			return Components::notice( __( 'Lead status updated.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['lc_error'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['lc_error'] ) );
			if ( 'open_work' === $code ) {
				return Components::notice( __( 'This lead has open tasks or promises. Provide an override reason to archive anyway.', 'mbd-crm' ), 'danger' );
			}
			if ( 'not_reactivatable' === $code ) {
				return Components::notice( __( 'Only failed, on-hold, or archived leads can be reactivated.', 'mbd-crm' ), 'danger' );
			}
			return Components::notice( __( 'A reason is required for this action.', 'mbd-crm' ), 'danger' );
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
