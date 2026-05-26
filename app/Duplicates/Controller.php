<?php
/**
 * Duplicate detection panel, review screen, and merge handling.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Duplicates;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\Capabilities;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Shows possible duplicates on the lead detail, renders the Owner/Admin
 * duplicate-review screen, and performs audited merges (re-pointing all
 * linked records to the primary lead and archiving the merged lead).
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_merge';
	private const NONCE_FIELD  = 'mbd_crm_mgnonce';

	/**
	 * Linked tables whose lead_id must follow a merge.
	 *
	 * @return string[]
	 */
	private static function linked_tables(): array {
		return array(
			'mbd_crm_tasks',
			'mbd_crm_audit',
			'mbd_crm_stage_history',
			'mbd_crm_qualifications',
			'mbd_crm_followups',
			'mbd_crm_promises',
			'mbd_crm_discoveries',
			'mbd_crm_deposits',
			'mbd_crm_plannings',
			'mbd_crm_deliverables',
			'mbd_crm_revisions',
			'mbd_crm_approvals',
			'mbd_crm_closings',
			'mbd_crm_negotiations',
			'mbd_crm_stakeholders',
		);
	}

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
		add_filter( 'mbd_crm_lead_detail_panels', array( $this, 'render_panel' ), 12, 2 );
		add_filter( 'mbd_crm_leads_post_action', array( $this, 'handle' ), 10, 2 );
		add_filter( 'mbd_crm_screen_content', array( $this, 'render_screen' ), 15, 3 );
	}

	/**
	 * Render the "possible duplicates" warning panel on the lead detail.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$candidates = $this->leads->duplicate_candidates( $lead );
		if ( empty( $candidates ) ) {
			return $html;
		}

		$panel = $this->view->capture(
			'crm/duplicates/panel',
			array(
				'candidates' => $candidates,
				'can_merge'  => current_user_can( Capabilities::MERGE_LEADS ),
				'review_url' => Router::screen_url( 'duplicates' ),
			)
		);

		return $html . $panel;
	}

	/**
	 * Handle a merge submission.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		if ( 'merge_leads' !== $action ) {
			return $result;
		}

		// Rule: only Owner/Admin may merge.
		if ( ! current_user_can( Capabilities::MERGE_LEADS ) ) {
			return Components::blocked( __( 'You do not have permission to merge leads.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$primary_id = isset( $_POST['primary_id'] ) ? absint( wp_unslash( $_POST['primary_id'] ) ) : 0;
		$members    = isset( $_POST['member_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['member_ids'] ) ) : array();

		$primary = $this->leads->find( $primary_id );
		$review  = Router::screen_url( 'duplicates' );

		if ( ! $primary || count( $members ) < 1 ) {
			$this->redirect( add_query_arg( 'merge_error', '1', $review ) );
		}

		$merged = 0;
		foreach ( $members as $secondary_id ) {
			if ( $secondary_id === $primary_id || $secondary_id < 1 ) {
				continue;
			}
			$secondary = $this->leads->find( $secondary_id );
			if ( ! $secondary || 'merged' === $secondary->lifecycle ) {
				continue;
			}
			$this->merge_one( $primary_id, $secondary_id );
			++$merged;
		}

		$this->redirect( add_query_arg( 'merged', $merged, $review ) );
	}

	/**
	 * Merge one secondary lead into the primary: re-point linked records,
	 * archive the secondary, and audit both sides.
	 *
	 * @param int $primary_id   Surviving lead.
	 * @param int $secondary_id Lead being merged away.
	 * @return void
	 */
	private function merge_one( int $primary_id, int $secondary_id ): void {
		global $wpdb;

		foreach ( self::linked_tables() as $name ) {
			$table = $wpdb->prefix . $name;
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET lead_id = %d WHERE lead_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
					$primary_id,
					$secondary_id
				)
			);
		}

		$this->leads->mark_merged( $secondary_id, $primary_id );

		Audit::record(
			$primary_id,
			Audit::MERGE,
			array(
				'from' => $secondary_id,
				'into' => $primary_id,
			)
		);
		Audit::record(
			$secondary_id,
			Audit::MERGE,
			array(
				'from' => $secondary_id,
				'into' => $primary_id,
			)
		);
	}

	/**
	 * Render the duplicate-review screen.
	 *
	 * @param string|null $content Existing content.
	 * @param string      $slug    Active screen slug.
	 * @param array|null  $meta    Screen meta.
	 * @return string|null
	 */
	public function render_screen( $content, string $slug, $meta ) {
		unset( $meta );

		if ( 'duplicates' !== $slug ) {
			return $content;
		}

		if ( ! current_user_can( Capabilities::MERGE_LEADS ) ) {
			return Components::blocked( __( 'Only Owner/Admin can review duplicates.', 'mbd-crm' ) );
		}

		$groups = $this->build_groups();

		return $this->view->capture(
			'crm/duplicates/review',
			array(
				'groups'      => $groups,
				'nonce_field' => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action' => Router::screen_url( 'leads' ),
				'notice'      => $this->flash_notice(),
			)
		);
	}

	/**
	 * Group non-merged leads that share a normalized WhatsApp number.
	 *
	 * @return array<int, array<int, object>>
	 */
	private function build_groups(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'mbd_crm_leads';
		$rows  = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE lifecycle <> 'merged' AND whatsapp_normalized <> '' ORDER BY whatsapp_normalized, id" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$by_key = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$by_key[ $row->whatsapp_normalized ][] = $row;
			}
		}

		$groups = array();
		foreach ( $by_key as $members ) {
			if ( count( $members ) > 1 ) {
				$groups[] = $members;
			}
		}

		return $groups;
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['merged'] ) ) {
			return Components::notice(
				sprintf(
					/* translators: %d: number of merged leads. */
					__( 'Merged %d lead(s) into the primary.', 'mbd-crm' ),
					(int) $_GET['merged']
				),
				'success'
			);
		}
		if ( isset( $_GET['merge_error'] ) ) {
			return Components::notice( __( 'Select a primary lead and at least one duplicate to merge.', 'mbd-crm' ), 'danger' );
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
