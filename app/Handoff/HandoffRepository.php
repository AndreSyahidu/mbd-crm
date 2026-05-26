<?php
/**
 * Persistence for project handoffs.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Handoff;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the per-lead project-handoff record.
 */
class HandoffRepository {

	/**
	 * The handoff for a lead, or null.
	 *
	 * @param int $lead_id Lead ID.
	 * @return object|null
	 */
	public function for_lead( int $lead_id ): ?object {
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
	 * Find a handoff by ID.
	 *
	 * @param int $id Handoff ID.
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
	 * Create a handoff record.
	 *
	 * @param array<string, mixed> $data Handoff fields.
	 * @return int Handoff ID.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		$wpdb->insert(
			Schema::table(),
			array(
				'lead_id'     => (int) $data['lead_id'],
				'closing_id'  => (int) ( $data['closing_id'] ?? 0 ),
				'status'      => (string) ( $data['status'] ?? 'draft' ),
				'checklist'   => wp_json_encode( $data['checklist'] ?? Checklist::fresh() ),
				'final_value' => (float) ( $data['final_value'] ?? 0 ),
				'scope'       => (string) ( $data['scope'] ?? '' ),
				'created_by'  => (int) ( $data['created_by'] ?? 0 ),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update handoff fields (allowlisted).
	 *
	 * @param int                  $id     Handoff ID.
	 * @param array<string, mixed> $fields Fields to update.
	 * @return void
	 */
	public function update( int $id, array $fields ): void {
		global $wpdb;

		$allowed = array(
			'status'        => '%s',
			'checklist'     => '%s',
			'pic_user_id'   => '%d',
			'handed_off_at' => '%s',
			'scope'         => '%s',
		);

		$data   = array();
		$format = array();
		foreach ( $fields as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			if ( 'checklist' === $key && is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}
			$data[ $key ] = $value;
			$format[]     = $allowed[ $key ];
		}

		if ( empty( $data ) ) {
			return;
		}

		$data['updated_at'] = current_time( 'mysql' );
		$format[]           = '%s';

		$wpdb->update( Schema::table(), $data, array( 'id' => $id ), $format, array( '%d' ) );
	}

	/**
	 * Decode a handoff's checklist into a normalised state map.
	 *
	 * @param object $handoff Handoff row.
	 * @return array<string, bool>
	 */
	public function checklist( object $handoff ): array {
		$decoded = json_decode( (string) ( $handoff->checklist ?? '' ), true );

		return Checklist::normalize( is_array( $decoded ) ? $decoded : array() );
	}

	/**
	 * All handoffs, newest first (optionally filtered to a set of lead IDs).
	 *
	 * @param array<int, bool>|null $lead_ids Visible lead IDs, or null for all.
	 * @return array<int, object>
	 */
	public function all( ?array $lead_ids = null ): array {
		global $wpdb;

		$table = Schema::table();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows  = is_array( $rows ) ? $rows : array();

		if ( null === $lead_ids ) {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $lead_ids ) {
					return isset( $lead_ids[ (int) $row->lead_id ] );
				}
			)
		);
	}
}
