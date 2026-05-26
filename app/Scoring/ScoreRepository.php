<?php
/**
 * Persistence for lead score and score history.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Scoring;

use MBD\CRM\Leads\Schema as LeadsSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Writes the current score/temperature onto the lead and appends history.
 */
class ScoreRepository {

	/**
	 * Persist score and temperature onto a lead.
	 *
	 * @param int    $lead_id       Lead ID.
	 * @param int    $score         Score 0-100.
	 * @param string $temperature   Temperature key.
	 * @param int    $locked        1 to lock against system recalculation.
	 * @param string $override_note Override reason (when locked by a user).
	 * @return void
	 */
	public function persist( int $lead_id, int $score, string $temperature, int $locked = 0, string $override_note = '' ): void {
		global $wpdb;

		$wpdb->update(
			LeadsSchema::leads_table(),
			array(
				'score'                 => $score,
				'temperature'           => $temperature,
				'score_locked'          => $locked,
				'score_override_reason' => $override_note,
				'updated_at'            => current_time( 'mysql' ),
			),
			array( 'id' => $lead_id ),
			array( '%d', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Append a score-history entry.
	 *
	 * @param int    $lead_id         Lead ID.
	 * @param int    $old_score       Previous score.
	 * @param int    $new_score       New score.
	 * @param string $old_temperature Previous temperature.
	 * @param string $new_temperature New temperature.
	 * @param string $reason          Reason.
	 * @param string $calculated_by   'system' or 'user'.
	 * @param int    $created_by      Acting user ID (0 for system).
	 * @return void
	 */
	public function record( int $lead_id, int $old_score, int $new_score, string $old_temperature, string $new_temperature, string $reason, string $calculated_by, int $created_by ): void {
		global $wpdb;

		$wpdb->insert(
			Schema::table(),
			array(
				'lead_id'         => $lead_id,
				'old_score'       => $old_score,
				'new_score'       => $new_score,
				'old_temperature' => $old_temperature,
				'new_temperature' => $new_temperature,
				'reason'          => $reason,
				'calculated_by'   => $calculated_by,
				'created_by'      => $created_by,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Score history for a lead, newest first.
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
