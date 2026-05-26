<?php
/**
 * Lead task automation.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and reads the follow-up tasks attached to leads.
 */
class Tasks {

	/**
	 * Hook task automation onto lead creation.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'mbd_crm_lead_created', array( $this, 'create_first_task' ), 10, 2 );
	}

	/**
	 * Create the first follow-up task for a newly created lead.
	 *
	 * The task is assigned to the sales rep on the lead, falling back to
	 * its creator, and is due when the response SLA expires.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead    Lead row.
	 * @return void
	 */
	public function create_first_task( int $lead_id, object $lead ): void {
		$assignee = (int) ( $lead->assigned_to ?? 0 );
		if ( ! $assignee ) {
			$assignee = (int) ( $lead->created_by ?? 0 );
		}

		self::create_task(
			$lead_id,
			$assignee,
			sprintf(
				/* translators: %s: lead name. */
				__( 'First contact: %s', 'mbd-crm' ),
				(string) ( $lead->name ?? '' )
			),
			$lead->sla_due_at ?? null
		);
	}

	/**
	 * Insert a task for a lead.
	 *
	 * @param int         $lead_id  Lead ID.
	 * @param int         $assignee User the task is assigned to.
	 * @param string      $title    Task title.
	 * @param string|null $due_at   Optional due datetime.
	 * @return void
	 */
	public static function create_task( int $lead_id, int $assignee, string $title, ?string $due_at = null ): void {
		global $wpdb;

		$wpdb->insert(
			Schema::tasks_table(),
			array(
				'lead_id'     => $lead_id,
				'assigned_to' => $assignee,
				'title'       => $title,
				'status'      => 'open',
				'due_at'      => $due_at,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Open tasks assigned to a user, newest first.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Maximum rows.
	 * @return array<int, object>
	 */
	public static function open_for_user( int $user_id, int $limit = 50 ): array {
		global $wpdb;

		$table = Schema::tasks_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE assigned_to = %d AND status = 'open' ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$limit
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Tasks attached to a lead, newest first.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, object>
	 */
	public static function for_lead( int $lead_id ): array {
		global $wpdb;

		$table = Schema::tasks_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}
}
