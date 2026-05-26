<?php
/**
 * Persistence for qualification decisions.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Qualification;

defined( 'ABSPATH' ) || exit;

/**
 * Stores qualification decisions (with score, fit checks, reason, and a
 * lead snapshot) and reads back the latest decision for a lead.
 */
class QualificationRepository {

	/**
	 * Record a qualification decision.
	 *
	 * @param int                                            $lead_id    Lead ID.
	 * @param string                                         $decision   'qualified' or 'not_qualified'.
	 * @param int                                            $score      Fit score 0-100.
	 * @param array<string, array{label:string,passed:bool}> $fit_checks Evaluated checks.
	 * @param string                                         $reason     Reason (for not qualified).
	 * @param array<string, mixed>                           $snapshot   Lead snapshot.
	 * @return int New row ID.
	 */
	public function record( int $lead_id, string $decision, int $score, array $fit_checks, string $reason, array $snapshot ): int {
		global $wpdb;

		$wpdb->insert(
			Schema::table(),
			array(
				'lead_id'    => $lead_id,
				'decision'   => $decision,
				'score'      => $score,
				'fit_checks' => (string) wp_json_encode( $fit_checks ),
				'reason'     => $reason,
				'snapshot'   => (string) wp_json_encode( $snapshot ),
				'decided_by' => get_current_user_id(),
				'decided_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Latest qualification decision for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return object|null
	 */
	public function latest( int $lead_id ): ?object {
		global $wpdb;

		$table = Schema::table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return $row ?: null;
	}

	/**
	 * Full decision history for a lead, newest first.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, object>
	 */
	public function history( int $lead_id ): array {
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
