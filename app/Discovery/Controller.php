<?php
/**
 * Discovery request handling and detail panel.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Discovery;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Qualification\Gate;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Processes schedule / reschedule / complete / cancel actions for
 * discovery sessions and renders the discovery panel on the lead detail.
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_discovery';
	private const NONCE_FIELD  = 'mbd_crm_dnonce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Discovery persistence.
	 *
	 * @var DiscoveryRepository
	 */
	private DiscoveryRepository $repo;

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
		$this->repo  = new DiscoveryRepository();
		$this->view  = new View();
	}

	/**
	 * Handle a discovery action submitted from the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		$actions = array( 'schedule_discovery', 'reschedule_discovery', 'complete_discovery', 'cancel_discovery' );
		if ( ! in_array( $action, $actions, true ) ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}
		if ( ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to manage discovery for this lead.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;

		if ( 'schedule_discovery' === $action ) {
			// Rule: discovery requires a qualified lead.
			if ( ! Gate::can_enter_discovery( $lead ) ) {
				$this->redirect( add_query_arg( 'disc_error', 'notqualified', $detail ) );
			}

			$type = sanitize_key( $this->post_text( 'type' ) );
			if ( ! Options::is_type( $type ) ) {
				$type = 'call';
			}

			$when = $this->normalize_datetime( $this->post_text( 'scheduled_at' ) );
			if ( '' === $when ) {
				$this->redirect( add_query_arg( 'disc_error', 'when', $detail ) );
			}

			$id = $this->repo->schedule( $lead_id, $type, $when, get_current_user_id() );
			$this->fire( $lead_id, $lead, $id, 'scheduled' );
			$this->redirect( add_query_arg( 'disc', 'scheduled', $detail ) );
		}

		$discovery_id = isset( $_POST['discovery_id'] ) ? absint( wp_unslash( $_POST['discovery_id'] ) ) : 0;
		$discovery    = $this->repo->find( $discovery_id );
		if ( ! $discovery || (int) $discovery->lead_id !== $lead_id ) {
			$this->redirect( $detail );
		}

		if ( 'reschedule_discovery' === $action ) {
			$when = $this->normalize_datetime( $this->post_text( 'scheduled_at' ) );
			if ( '' === $when ) {
				$this->redirect( add_query_arg( 'disc_error', 'when', $detail ) );
			}
			$this->repo->reschedule( $discovery_id, $when );
			$this->fire( $lead_id, $lead, $discovery_id, 'rescheduled' );
			$this->redirect( add_query_arg( 'disc', 'rescheduled', $detail ) );
		}

		if ( 'cancel_discovery' === $action ) {
			$this->repo->cancel( $discovery_id );
			$this->fire( $lead_id, $lead, $discovery_id, 'cancelled' );
			$this->redirect( add_query_arg( 'disc', 'cancelled', $detail ) );
		}

		// complete_discovery.
		$summary = array(
			'requirement_summary'   => $this->post_textarea( 'requirement_summary' ),
			'survey_summary'        => $this->post_textarea( 'survey_summary' ),
			'pain_points'           => $this->post_textarea( 'pain_points' ),
			'client_expectation'    => $this->post_textarea( 'client_expectation' ),
			'recommended_next_step' => $this->post_textarea( 'recommended_next_step' ),
		);

		// Rule: completion requires a summary.
		if ( '' === trim( $summary['requirement_summary'] ) && '' === trim( $summary['survey_summary'] ) ) {
			$this->redirect( add_query_arg( 'disc_error', 'summary', $detail ) );
		}

		list( $attach_id, $attach_url ) = $this->upload_attachment();

		$this->repo->complete( $discovery_id, $summary, $attach_id, $attach_url );
		$this->fire( $lead_id, $lead, $discovery_id, 'completed' );
		$this->redirect( add_query_arg( 'disc', 'completed', $detail ) );
	}

	/**
	 * Render the discovery panel for the lead detail sidebar.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$panel = $this->view->capture(
			'crm/discovery/panel',
			array(
				'lead'        => $lead,
				'qualified'   => Gate::can_enter_discovery( $lead ),
				'sessions'    => $this->repo->for_lead( (int) $lead->id ),
				'can_edit'    => Permissions::can_edit( $lead ),
				'nonce_field' => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action' => Router::screen_url( 'leads' ),
				'notice'      => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Fire the discovery-changed action with a fresh discovery row.
	 *
	 * @param int    $lead_id      Lead ID.
	 * @param object $lead         Lead row.
	 * @param int    $discovery_id Discovery ID.
	 * @param string $event        Event key.
	 * @return void
	 */
	private function fire( int $lead_id, object $lead, int $discovery_id, string $event ): void {
		/**
		 * Fires when a discovery's status changes.
		 *
		 * @param int    $lead_id      Lead ID.
		 * @param object $lead         Lead row.
		 * @param object $discovery    Discovery row.
		 * @param string $event        Event key.
		 */
		do_action( 'mbd_crm_discovery_changed', $lead_id, $lead, $this->repo->find( $discovery_id ), $event );
	}

	/**
	 * Handle an optional document/photo upload.
	 *
	 * @return array{0:int,1:string} [attachment_id, attachment_url].
	 */
	private function upload_attachment(): array {
		if ( empty( $_FILES['attachment']['name'] ) ) {
			return array( 0, '' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attach_id = media_handle_upload( 'attachment', 0 );

		if ( is_wp_error( $attach_id ) ) {
			return array( 0, '' );
		}

		return array( (int) $attach_id, (string) wp_get_attachment_url( $attach_id ) );
	}

	/**
	 * Normalise a datetime-local value to Y-m-d H:i:s, or '' if invalid.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function normalize_datetime( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$value = str_replace( 'T', ' ', $value );
		$ts    = strtotime( $value );

		return false === $ts ? '' : gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['disc'] ) ) {
			$state = sanitize_key( wp_unslash( $_GET['disc'] ) );
			if ( 'completed' === $state ) {
				return Components::notice( __( 'Discovery completed. Deposit follow-up created and deposit SLA started.', 'mbd-crm' ), 'success' );
			}
			return Components::notice( __( 'Discovery updated.', 'mbd-crm' ), 'success' );
		}
		if ( isset( $_GET['disc_error'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['disc_error'] ) );
			if ( 'notqualified' === $code ) {
				return Components::notice( __( 'Discovery requires a qualified lead.', 'mbd-crm' ), 'danger' );
			}
			if ( 'summary' === $code ) {
				return Components::notice( __( 'A requirement or survey summary is required to complete discovery.', 'mbd-crm' ), 'danger' );
			}
			return Components::notice( __( 'Enter a valid schedule date and time.', 'mbd-crm' ), 'danger' );
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
