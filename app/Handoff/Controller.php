<?php
/**
 * Project handoff: creation on win, the lead-detail panel, and the Projects
 * screen.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Handoff;

use MBD\CRM\Closing\ClosingRepository;
use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Offers\OfferRepository;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a won deal into a project handoff: seeds a handoff record when a
 * closing is approved, drives a completion checklist from the lead detail, and
 * finalises the CRM-to-delivery handoff (firing a bridge action for downstream
 * project/ERP systems).
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_handoff';
	private const NONCE_FIELD  = 'mbd_crm_hononce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Handoff persistence.
	 *
	 * @var HandoffRepository
	 */
	private HandoffRepository $repo;

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
		$this->repo  = new HandoffRepository();
		$this->view  = new View();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'mbd_crm_generate_project_draft', array( $this, 'on_won' ), 10, 2 );
		add_filter( 'mbd_crm_leads_post_action', array( $this, 'handle' ), 10, 2 );
		add_filter( 'mbd_crm_lead_detail_panels', array( $this, 'render_panel' ), 75, 2 );
		add_filter( 'mbd_crm_screens', array( $this, 'register_screen' ) );
		add_filter( 'mbd_crm_screen_content', array( $this, 'maybe_render' ), 20, 3 );
	}

	/**
	 * Seed a handoff record when a deal is won.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead    Lead row.
	 * @return void
	 */
	public function on_won( int $lead_id, object $lead ): void {
		if ( $this->repo->for_lead( $lead_id ) ) {
			return; // Idempotent: already handed off.
		}

		$closing = ( new ClosingRepository() )->for_lead( $lead_id );
		$offer   = ( new OfferRepository() )->current( $lead_id );

		$final = 0.0;
		if ( $closing && null !== $closing->final_value ) {
			$final = (float) $closing->final_value;
		} elseif ( $offer ) {
			$final = (float) $offer->final_value;
		}

		$id = $this->repo->create(
			array(
				'lead_id'     => $lead_id,
				'closing_id'  => $closing ? (int) $closing->id : 0,
				'status'      => 'draft',
				'checklist'   => Checklist::fresh(),
				'final_value' => $final,
				'scope'       => $offer ? (string) $offer->scope : '',
				'created_by'  => get_current_user_id(),
			)
		);

		if ( $id > 0 ) {
			Audit::record( $lead_id, Audit::HANDOFF, array( 'event' => 'created' ) );
		}
	}

	/**
	 * Handle a handoff action submitted through the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		if ( ! in_array( $action, array( 'toggle_handoff_item', 'assign_handoff_pic', 'complete_handoff' ), true ) ) {
			return $result;
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );
		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}
		if ( ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to manage this handoff.', 'mbd-crm' ) );
		}

		$handoff = $this->repo->for_lead( $lead_id );
		if ( ! $handoff ) {
			return Components::blocked( __( 'This lead has no project handoff yet.', 'mbd-crm' ) );
		}

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;

		// A completed handoff is the terminal record; no further edits.
		if ( 'completed' === $handoff->status ) {
			$this->redirect( $detail );
		}

		$checklist = $this->repo->checklist( $handoff );

		if ( 'toggle_handoff_item' === $action ) {
			$key = isset( $_POST['item'] ) ? sanitize_key( wp_unslash( $_POST['item'] ) ) : '';
			if ( array_key_exists( $key, $checklist ) ) {
				$checklist[ $key ] = ! $checklist[ $key ];
				$this->repo->update(
					(int) $handoff->id,
					array(
						'checklist' => $checklist,
						'status'    => $this->derive_status( $checklist ),
					)
				);
			}
			$this->redirect( add_query_arg( 'ho', 'saved', $detail ) );
		}

		if ( 'assign_handoff_pic' === $action ) {
			$checklist['pic_assigned'] = true;
			$this->repo->update(
				(int) $handoff->id,
				array(
					'pic_user_id' => get_current_user_id(),
					'checklist'   => $checklist,
					'status'      => $this->derive_status( $checklist ),
				)
			);
			$this->redirect( add_query_arg( 'ho', 'pic', $detail ) );
		}

		// complete_handoff. Rule: every checklist item must be done.
		if ( ! Checklist::is_complete( $checklist ) ) {
			$this->redirect( add_query_arg( 'ho_error', 'incomplete', $detail ) );
		}

		$this->repo->update(
			(int) $handoff->id,
			array(
				'status'        => 'completed',
				'handed_off_at' => current_time( 'mysql' ),
			)
		);
		Audit::record( $lead_id, Audit::HANDOFF, array( 'event' => 'completed' ) );

		/**
		 * Fires when a won project is fully handed off to delivery — the bridge
		 * point for project-management / ERP integrations.
		 *
		 * @param int    $lead_id Lead ID.
		 * @param object $handoff Handoff row.
		 * @param object $lead    Lead row.
		 */
		do_action( 'mbd_crm_project_handoff_completed', $lead_id, $this->repo->find( (int) $handoff->id ), $lead );

		$this->redirect( add_query_arg( 'ho', 'done', $detail ) );
	}

	/**
	 * Render the handoff panel on the lead detail (won leads only).
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$handoff = $this->repo->for_lead( (int) $lead->id );
		if ( ! $handoff ) {
			return $html;
		}

		$checklist = $this->repo->checklist( $handoff );
		$rows      = array();
		foreach ( Checklist::items() as $key => $label ) {
			$rows[] = array(
				'key'   => $key,
				'label' => $label,
				'done'  => ! empty( $checklist[ $key ] ),
			);
		}

		$pic       = (int) $handoff->pic_user_id;
		$pic_user  = $pic > 0 ? get_userdata( $pic ) : null;

		$panel = $this->view->capture(
			'crm/handoff/panel',
			array(
				'lead'        => $lead,
				'handoff'     => $handoff,
				'items'       => $rows,
				'complete'    => Checklist::is_complete( $checklist ),
				'pic_name'    => $pic_user ? $pic_user->display_name : '',
				'can_edit'    => Permissions::can_edit( $lead ),
				'nonce_field' => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action' => Router::screen_url( 'leads' ),
				'notice'      => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Add the Projects screen to the registry.
	 *
	 * @param array<string, array{label:string, icon:string, cap:string}> $screens Screens.
	 * @return array<string, array{label:string, icon:string, cap:string}>
	 */
	public function register_screen( array $screens ): array {
		$screens['projects'] = array(
			'label' => __( 'Projects', 'mbd-crm' ),
			'icon'  => 'dashicons-hammer',
			'cap'   => \MBD\CRM\Leads\Capabilities::ACCESS_LEADS,
		);

		return $screens;
	}

	/**
	 * Render the Projects screen (won deals + handoff status).
	 *
	 * @param string|null $content Existing content.
	 * @param string      $slug    Active screen slug.
	 * @param array|null  $meta    Screen meta.
	 * @return string|null
	 */
	public function maybe_render( $content, string $slug, $meta ) {
		unset( $meta );

		if ( 'projects' !== $slug ) {
			return $content;
		}
		if ( ! Permissions::can_access() ) {
			return Components::blocked( __( 'You do not have access to projects.', 'mbd-crm' ) );
		}

		$scope   = Permissions::can_view_all() ? 'all' : 'own';
		$visible = array();
		foreach ( $this->leads->all( array( 'scope' => $scope, 'user_id' => get_current_user_id() ) ) as $lead ) {
			$visible[ (int) $lead->id ] = $lead;
		}

		$rows = array();
		foreach ( $this->repo->all( 'all' === $scope ? null : $visible ) as $handoff ) {
			$lead = $visible[ (int) $handoff->lead_id ] ?? ( 'all' === $scope ? $this->leads->find( (int) $handoff->lead_id ) : null );
			if ( ! $lead ) {
				continue;
			}
			$checklist = $this->repo->checklist( $handoff );
			$done      = count( array_filter( $checklist ) );
			$rows[]    = array(
				'name'   => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
				'status' => (string) $handoff->status,
				'done'   => $done,
				'total'  => count( $checklist ),
				'value'  => (float) $handoff->final_value,
				'url'    => Router::screen_url( 'leads' ) . '?lead=' . (int) $handoff->lead_id,
			);
		}

		return $this->view->capture( 'crm/handoff/screen', array( 'rows' => $rows ) );
	}

	/**
	 * Derive status from checklist progress.
	 *
	 * @param array<string, bool> $checklist Checklist state.
	 * @return string
	 */
	private function derive_status( array $checklist ): string {
		$done = count( array_filter( $checklist ) );
		if ( 0 === $done ) {
			return 'draft';
		}

		return Checklist::is_complete( $checklist ) ? 'ready' : 'in_progress';
	}

	/**
	 * Build a flash notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['ho'] ) ) {
			$state = sanitize_key( wp_unslash( $_GET['ho'] ) );
			$map   = array(
				'saved' => array( __( 'Handoff checklist updated.', 'mbd-crm' ), 'success' ),
				'pic'   => array( __( 'You are now the project PIC.', 'mbd-crm' ), 'success' ),
				'done'  => array( __( 'Project handed off to delivery.', 'mbd-crm' ), 'success' ),
			);
			if ( isset( $map[ $state ] ) ) {
				return Components::notice( $map[ $state ][0], $map[ $state ][1] );
			}
		}
		if ( isset( $_GET['ho_error'] ) ) {
			return Components::notice( __( 'Complete every checklist item before handing off.', 'mbd-crm' ), 'danger' );
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
