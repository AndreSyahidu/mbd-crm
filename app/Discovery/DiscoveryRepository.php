<?php
/**
 * Persistence for discovery sessions.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Discovery;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes discovery rows.
 */
class DiscoveryRepository {

	/**
	 * Schedule a discovery session.
	 *
	 * @param int    $lead_id      Lead ID.
	 * @param string $type         Discovery type.
	 * @param string $scheduled_at Scheduled datetime (Y-m-d H:i:s) or empty.
	 * @param int    $user_id      Creating user ID.
	 * @return int New discovery ID.
	 */
	public function schedule( int $lead_id, string $type, string $scheduled_at, int $user_id ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		$wpdb->insert(
			Schema::table(),
			array(
				'lead_id'               => $lead_id,
				'type'                  => $type,
				'status'                => 'scheduled',
				'scheduled_at'          => ( '' === $scheduled_at ) ? null : $scheduled_at,
				'requirement_summary'   => '',
				'survey_summary'        => '',
				'pain_points'           => '',
				'client_expectation'    => '',
				'recommended_next_step' => '',
				'created_by'            => $user_id,
				'created_at'            => $now,
				'updated_at'            => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find a discovery by ID.
	 *
	 * @param int $id Discovery ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		$table = Schema::table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		return $row ?: null;
	}

	/**
	 * Discovery sessions for a lead, newest first.
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

	/**
	 * Reschedule a discovery.
	 *
	 * @param int    $id           Discovery ID.
	 * @param string $scheduled_at New datetime (Y-m-d H:i:s).
	 * @return void
	 */
	public function reschedule( int $id, string $scheduled_at ): void {
		global $wpdb;

		$wpdb->update(
			Schema::table(),
			array(
				'scheduled_at' => ( '' === $scheduled_at ) ? null : $scheduled_at,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Complete a discovery with its summary fields and optional attachment.
	 *
	 * @param int                  $id         Discovery ID.
	 * @param array<string, mixed> $summary    Summary fields.
	 * @param int                  $attach_id  Attachment ID (0 if none).
	 * @param string               $attach_url Attachment URL.
	 * @return void
	 */
	public function complete( int $id, array $summary, int $attach_id, string $attach_url ): void {
		global $wpdb;

		$now = current_time( 'mysql' );

		$data = array(
			'status'                => 'completed',
			'requirement_summary'   => (string) ( $summary['requirement_summary'] ?? '' ),
			'survey_summary'        => (string) ( $summary['survey_summary'] ?? '' ),
			'pain_points'           => (string) ( $summary['pain_points'] ?? '' ),
			'client_expectation'    => (string) ( $summary['client_expectation'] ?? '' ),
			'recommended_next_step' => (string) ( $summary['recommended_next_step'] ?? '' ),
			'updated_at'            => $now,
			'completed_at'          => $now,
		);
		$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $attach_id > 0 ) {
			$data['attachment_id']  = $attach_id;
			$data['attachment_url'] = $attach_url;
			$format[]               = '%d';
			$format[]               = '%s';
		}

		$wpdb->update( Schema::table(), $data, array( 'id' => $id ), $format, array( '%d' ) );
	}

	/**
	 * Cancel a discovery.
	 *
	 * @param int $id Discovery ID.
	 * @return void
	 */
	public function cancel( int $id ): void {
		global $wpdb;

		$wpdb->update(
			Schema::table(),
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}
}
