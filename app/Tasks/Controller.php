<?php
/**
 * Task management: actions, the Tasks screen, and the lead-detail task panel.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Tasks;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Leads\Tasks as Store;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Adds interactive task management: create / complete / reopen tasks from the
 * lead detail and a dedicated Tasks screen listing the user's open work. Task
 * actions post through the leads route (the shared POST dispatcher) and then
 * redirect back to where they were triggered.
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_task';
	private const NONCE_FIELD  = 'mbd_crm_tasknonce';

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
		add_filter( 'mbd_crm_screens', array( $this, 'register_screen' ) );
		add_filter( 'mbd_crm_screen_content', array( $this, 'maybe_render' ), 20, 3 );
		add_filter( 'mbd_crm_lead_detail_panels', array( $this, 'render_panel' ), 85, 2 );
	}

	/**
	 * Add the Tasks screen to the registry.
	 *
	 * @param array<string, array{label:string, icon:string, cap:string}> $screens Screens.
	 * @return array<string, array{label:string, icon:string, cap:string}>
	 */
	public function register_screen( array $screens ): array {
		$screens['tasks'] = array(
			'label' => __( 'Tasks', 'mbd-crm' ),
			'icon'  => 'dashicons-yes',
			'cap'   => \MBD\CRM\Leads\Capabilities::ACCESS_LEADS,
		);

		return $screens;
	}

	/**
	 * Handle a task action submitted through the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		if ( ! in_array( $action, array( 'add_task', 'complete_task', 'reopen_task' ), true ) ) {
			return $result;
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$return = isset( $_POST['return'] ) && 'tasks' === sanitize_key( wp_unslash( $_POST['return'] ) )
			? Router::screen_url( 'tasks' )
			: '';

		if ( 'add_task' === $action ) {
			$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
			$lead    = $this->leads->find( $lead_id );
			$title   = isset( $_POST['title'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['title'] ) ) ) : '';
			$due     = isset( $_POST['due_at'] ) ? sanitize_text_field( wp_unslash( $_POST['due_at'] ) ) : '';

			if ( ! $lead || ! Permissions::can_edit( $lead ) ) {
				return Components::blocked( __( 'You do not have permission to add tasks to this lead.', 'mbd-crm' ) );
			}
			if ( '' === $title ) {
				$this->redirect( add_query_arg( 'task_error', 'title', $this->target( $return, $lead_id ) ) );
			}

			$assignee = (int) $lead->assigned_to > 0 ? (int) $lead->assigned_to : get_current_user_id();
			Store::create_task( $lead_id, $assignee, $title, '' !== $due ? $due . ' 00:00:00' : null );
			Audit::record( $lead_id, Audit::TASK, array( 'event' => 'added', 'title' => $title ) );
			$this->redirect( add_query_arg( 'task', 'added', $this->target( $return, $lead_id ) ) );
		}

		// complete_task / reopen_task.
		$task_id = isset( $_POST['task_id'] ) ? absint( wp_unslash( $_POST['task_id'] ) ) : 0;
		$task    = Store::find( $task_id );
		if ( ! $task ) {
			return Components::error_state( __( 'Task not found', 'mbd-crm' ), __( 'This task no longer exists.', 'mbd-crm' ) );
		}

		$lead = $this->leads->find( (int) $task->lead_id );
		if ( ! $lead || ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to update this task.', 'mbd-crm' ) );
		}

		if ( 'complete_task' === $action ) {
			Store::complete( $task_id );
			Audit::record( (int) $task->lead_id, Audit::TASK, array( 'event' => 'completed', 'title' => (string) $task->title ) );
			$this->redirect( add_query_arg( 'task', 'done', $this->target( $return, (int) $task->lead_id ) ) );
		}

		Store::reopen( $task_id );
		Audit::record( (int) $task->lead_id, Audit::TASK, array( 'event' => 'reopened', 'title' => (string) $task->title ) );
		$this->redirect( add_query_arg( 'task', 'reopened', $this->target( $return, (int) $task->lead_id ) ) );
	}

	/**
	 * Render the Tasks screen (the current user's open work).
	 *
	 * @param string|null $content Existing content.
	 * @param string      $slug    Active screen slug.
	 * @param array|null  $meta    Screen meta.
	 * @return string|null
	 */
	public function maybe_render( $content, string $slug, $meta ) {
		unset( $meta );

		if ( 'tasks' !== $slug ) {
			return $content;
		}
		if ( ! Permissions::can_access() ) {
			return Components::blocked( __( 'You do not have access to tasks.', 'mbd-crm' ) );
		}

		$uid   = get_current_user_id();
		$now   = current_time( 'mysql' );
		$open  = Store::open_for_user( $uid );

		$overdue  = array();
		$upcoming = array();
		foreach ( $open as $task ) {
			$row = array(
				'id'      => (int) $task->id,
				'title'   => (string) $task->title,
				'due'     => (string) ( $task->due_at ?? '' ),
				'lead_id' => (int) $task->lead_id,
				'url'     => Router::screen_url( 'leads' ) . '?lead=' . (int) $task->lead_id,
			);
			if ( ! empty( $task->due_at ) && $task->due_at < $now ) {
				$overdue[] = $row;
			} else {
				$upcoming[] = $row;
			}
		}

		$pickable = array();
		foreach ( $this->leads->active_pipeline( array( 'scope' => 'own', 'user_id' => $uid ) ) as $lead ) {
			$pickable[ (int) $lead->id ] = '' !== $lead->name ? $lead->name : sprintf( /* translators: %d: lead id. */ __( 'Lead #%d', 'mbd-crm' ), (int) $lead->id );
		}

		return $this->view->capture(
			'crm/tasks/screen',
			array(
				'overdue'     => $overdue,
				'upcoming'    => $upcoming,
				'pickable'    => $pickable,
				'nonce_field' => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action' => Router::screen_url( 'leads' ),
				'notice'      => $this->flash_notice(),
			)
		);
	}

	/**
	 * Render the interactive Tasks panel on the lead detail.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$panel = $this->view->capture(
			'crm/tasks/panel',
			array(
				'lead'        => $lead,
				'tasks'       => Store::for_lead( (int) $lead->id ),
				'can_edit'    => Permissions::can_edit( $lead ),
				'nonce_field' => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action' => Router::screen_url( 'leads' ),
				'notice'      => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Build the redirect target for a task action.
	 *
	 * @param string $return  Pre-resolved tasks-screen URL, or '' for the lead.
	 * @param int    $lead_id Lead ID.
	 * @return string
	 */
	private function target( string $return, int $lead_id ): string {
		return '' !== $return ? $return : Router::screen_url( 'leads' ) . '?lead=' . $lead_id;
	}

	/**
	 * Build a flash notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['task'] ) ) {
			$state = sanitize_key( wp_unslash( $_GET['task'] ) );
			$map   = array(
				'added'    => __( 'Task added.', 'mbd-crm' ),
				'done'     => __( 'Task completed.', 'mbd-crm' ),
				'reopened' => __( 'Task reopened.', 'mbd-crm' ),
			);
			if ( isset( $map[ $state ] ) ) {
				return Components::notice( $map[ $state ], 'success' );
			}
		}
		if ( isset( $_GET['task_error'] ) ) {
			return Components::notice( __( 'A task title is required.', 'mbd-crm' ), 'danger' );
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
