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
		global $wpdb;

		$assignee = (int) ( $lead->assigned_to ?? 0 );
		if ( ! $assignee ) {
			$assignee = (int) ( $lead->created_by ?? 0 );
		}

		$wpdb->insert(
			Schema::tasks_table(),
			array(
				'lead_id'     => $lead_id,
				'assigned_to' => $assignee,
				'title'       => sprintf(
					/* translators: %s: lead name. */
					__( 'First contact: %s', 'mbd-crm' ),
					(string) ( $lead->name ?? '' )
				),
				'status'      => 'open',
				'due_at'      => $lead->sla_due_at ?? null,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
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
