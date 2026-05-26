<?php
/**
 * Persistence for follow-up log entries.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\FollowUp;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes follow-up log rows.
 */
class FollowUpRepository {

	/**
	 * Log a follow-up entry.
	 *
	 * @param int                  $lead_id Lead ID.
	 * @param array<string, mixed> $data    Sanitised fields.
	 * @param int                  $user_id Logging user ID.
	 * @return int New row ID.
	 */
	public function log( int $lead_id, array $data, int $user_id ): int {
		global $wpdb;

		$wpdb->insert(
			Schema::followups_table(),
			array(
				'lead_id'        => $lead_id,
				'channel'        => $data['channel'],
				'summary'        => $data['summary'],
				'result'         => $data['result'],
				'next_action'    => $data['next_action'],
				'next_follow_up' => ( '' === $data['next_follow_up'] ) ? null : $data['next_follow_up'],
				'created_by'     => $user_id,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find a follow-up by ID.
	 *
	 * @param int $id Follow-up ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		$table = Schema::followups_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		return $row ?: null;
	}

	/**
	 * Earliest follow-up timestamp per lead (lead_id => MIN(created_at)).
	 * Used to compute first-response time.
	 *
	 * @return array<int, string>
	 */
	public function first_contact_map(): array {
		global $wpdb;

		$table = Schema::followups_table();

		$rows = $wpdb->get_results(
			"SELECT lead_id, MIN(created_at) AS first_at FROM {$table} GROUP BY lead_id" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		);

		$map = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$map[ (int) $row->lead_id ] = (string) $row->first_at;
			}
		}

		return $map;
	}

	/**
	 * Follow-up history for a lead, newest first.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, object>
	 */
	public function for_lead( int $lead_id ): array {
		global $wpdb;

		$table = Schema::followups_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}
}
