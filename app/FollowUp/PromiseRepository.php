<?php
/**
 * Persistence for promise records.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\FollowUp;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes promise rows and answers due-promise queries.
 */
class PromiseRepository {

	/**
	 * Create a promise.
	 *
	 * @param int         $lead_id     Lead ID.
	 * @param string      $description What was promised.
	 * @param string      $due_date    Due date (Y-m-d) or empty.
	 * @param int         $user_id     Creating user ID.
	 * @param int         $followup_id Originating follow-up ID, if any.
	 * @return int New promise ID.
	 */
	public function create( int $lead_id, string $description, string $due_date, int $user_id, int $followup_id = 0 ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		$wpdb->insert(
			Schema::promises_table(),
			array(
				'lead_id'     => $lead_id,
				'followup_id' => $followup_id,
				'description' => $description,
				'due_date'    => ( '' === $due_date ) ? null : $due_date,
				'status'      => 'open',
				'created_by'  => $user_id,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find a promise by ID.
	 *
	 * @param int $id Promise ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		$table = Schema::promises_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		return $row ?: null;
	}

	/**
	 * Update a promise's status.
	 *
	 * @param int    $id     Promise ID.
	 * @param string $status New status.
	 * @return void
	 */
	public function update_status( int $id, string $status ): void {
		global $wpdb;

		$wpdb->update(
			Schema::promises_table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Promises for a lead, newest first.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, object>
	 */
	public function for_lead( int $lead_id ): array {
		global $wpdb;

		$table = Schema::promises_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Open promises due on or before a date.
	 *
	 * @param string $on_or_before Date (Y-m-d).
	 * @return array<int, object>
	 */
	public function due( string $on_or_before ): array {
		global $wpdb;

		$table = Schema::promises_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'open' AND due_date IS NOT NULL AND due_date <= %s ORDER BY due_date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$on_or_before
			)
		);

		return is_array( $rows ) ? $rows : array();
	}
}
