<?php
/**
 * Persistence for client approval evidence.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Approval;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes client approval evidence records.
 */
class ApprovalRepository {

	/**
	 * Record approval evidence.
	 *
	 * @param int                  $lead_id    Lead ID.
	 * @param array<string, mixed> $data       Sanitised fields.
	 * @param int                  $attach_id  Evidence attachment ID.
	 * @param string               $attach_url Evidence URL.
	 * @param int                  $user_id    Acting user ID.
	 * @return int New record ID.
	 */
	public function record( int $lead_id, array $data, int $attach_id, string $attach_url, int $user_id ): int {
		global $wpdb;

		$wpdb->insert(
			Schema::table(),
			array(
				'lead_id'       => $lead_id,
				'evidence_type' => $data['evidence_type'],
				'evidence_id'   => $attach_id,
				'evidence_url'  => $attach_url,
				'approval_note' => $data['approval_note'],
				'client_name'   => $data['client_name'],
				'approved_date' => ( '' === $data['approved_date'] ) ? null : $data['approved_date'],
				'created_by'    => $user_id,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Approval evidence records for a lead, newest first.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, object>
	 */
	public function for_lead( int $lead_id ): array {
		global $wpdb;

		$table = Schema::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}
}
