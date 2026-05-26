<?php
/**
 * Persistence for closings and the negotiation log.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Closing;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the per-lead closing record and its negotiation log.
 */
class ClosingRepository {

	/**
	 * The closing record for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return object|null
	 */
	public function for_lead( int $lead_id ): ?object {
		global $wpdb;

		$table = Schema::closings_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return $row ?: null;
	}

	/**
	 * Create a closing record (status open).
	 *
	 * @param int $lead_id Lead ID.
	 * @param int $user_id Creating user ID.
	 * @return int Closing ID.
	 */
	public function create( int $lead_id, int $user_id ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		$wpdb->insert(
			Schema::closings_table(),
			array(
				'lead_id'        => $lead_id,
				'status'         => 'open',
				'competitor_note' => '',
				'created_by'     => $user_id,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update closing fields (allowlisted).
	 *
	 * @param int                  $id     Closing ID.
	 * @param array<string, mixed> $fields Fields to update.
	 * @return void
	 */
	public function update( int $id, array $fields ): void {
		global $wpdb;

		$allowed = array(
			'status'            => '%s',
			'proposal_sent'     => '%d',
			'proposal_sent_at'  => '%s',
			'estimated_value'   => '%f',
			'offered_value'     => '%f',
			'final_value'       => '%f',
			'probability'       => '%d',
			'expected_close_date' => '%s',
			'next_action'       => '%s',
			'competitor_note'   => '%s',
			'lost_reason'       => '%s',
			'project_draft'     => '%d',
			'approved_at'       => '%s',
		);
		$nullable = array( 'estimated_value', 'offered_value', 'final_value', 'expected_close_date', 'proposal_sent_at', 'approved_at' );

		$data   = array();
		$format = array();
		foreach ( $fields as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			if ( in_array( $key, $nullable, true ) && ( '' === $value || null === $value ) ) {
				$value = null;
			}
			$data[ $key ] = $value;
			$format[]     = $allowed[ $key ];
		}

		if ( empty( $data ) ) {
			return;
		}

		$data['updated_at'] = current_time( 'mysql' );
		$format[]           = '%s';

		$wpdb->update( Schema::closings_table(), $data, array( 'id' => $id ), $format, array( '%d' ) );
	}

	/**
	 * Add a negotiation-log entry.
	 *
	 * @param int    $closing_id Closing ID.
	 * @param int    $lead_id    Lead ID.
	 * @param string $type       'note' or 'objection'.
	 * @param string $content    Entry content.
	 * @param int    $user_id    Creating user ID.
	 * @return int Entry ID.
	 */
	public function add_negotiation( int $closing_id, int $lead_id, string $type, string $content, int $user_id ): int {
		global $wpdb;

		$wpdb->insert(
			Schema::negotiations_table(),
			array(
				'closing_id' => $closing_id,
				'lead_id'    => $lead_id,
				'entry_type' => $type,
				'content'    => $content,
				'created_by' => $user_id,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Negotiation log for a closing, newest first.
	 *
	 * @param int $closing_id Closing ID.
	 * @return array<int, object>
	 */
	public function negotiations( int $closing_id ): array {
		global $wpdb;

		$table = Schema::negotiations_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE closing_id = %d ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$closing_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Closings in a given status.
	 *
	 * @param string $status Status key.
	 * @return array<int, object>
	 */
	public function by_status( string $status ): array {
		global $wpdb;

		$table = Schema::closings_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY updated_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * All closing records.
	 *
	 * @return array<int, object>
	 */
	public function all(): array {
		global $wpdb;

		$table = Schema::closings_table();

		$rows = $wpdb->get_results( "SELECT * FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? $rows : array();
	}
}
