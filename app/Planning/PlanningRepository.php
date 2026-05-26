<?php
/**
 * Persistence for plannings, deliverables, and revisions.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Planning;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the planning record and its deliverables / revision log.
 */
class PlanningRepository {

	/**
	 * The planning record for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return object|null
	 */
	public function for_lead( int $lead_id ): ?object {
		global $wpdb;

		$table = Schema::plannings_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return $row ?: null;
	}

	/**
	 * Find a planning by ID.
	 *
	 * @param int $id Planning ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		$table = Schema::plannings_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		return $row ?: null;
	}

	/**
	 * Create a planning record.
	 *
	 * @param int    $lead_id     Lead ID.
	 * @param int    $planner_id  Planner user ID.
	 * @param string $scope       Planning scope.
	 * @param string $target_date Target completion date (Y-m-d) or empty.
	 * @param string $sla_due     SLA due datetime.
	 * @param int    $user_id     Creating user ID.
	 * @return int Planning ID.
	 */
	public function create( int $lead_id, int $planner_id, string $scope, string $target_date, string $sla_due, int $user_id ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		$wpdb->insert(
			Schema::plannings_table(),
			array(
				'lead_id'         => $lead_id,
				'planner_id'      => $planner_id,
				'scope'           => $scope,
				'target_date'     => ( '' === $target_date ) ? null : $target_date,
				'status'          => 'draft',
				'internal_review' => 'pending',
				'sla_due'         => ( '' === $sla_due ) ? null : $sla_due,
				'created_by'      => $user_id,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update planning fields.
	 *
	 * @param int                  $id     Planning ID.
	 * @param array<string, mixed> $fields Allowed: planner_id, scope, target_date, status, internal_review.
	 * @return void
	 */
	public function update( int $id, array $fields ): void {
		global $wpdb;

		$allowed = array(
			'planner_id'      => '%d',
			'scope'           => '%s',
			'target_date'     => '%s',
			'status'          => '%s',
			'internal_review' => '%s',
		);

		$data   = array();
		$format = array();
		foreach ( $fields as $key => $value ) {
			if ( isset( $allowed[ $key ] ) ) {
				if ( 'target_date' === $key && '' === $value ) {
					$value = null;
				}
				$data[ $key ] = $value;
				$format[]     = $allowed[ $key ];
			}
		}

		if ( empty( $data ) ) {
			return;
		}

		$data['updated_at'] = current_time( 'mysql' );
		$format[]           = '%s';

		$wpdb->update( Schema::plannings_table(), $data, array( 'id' => $id ), $format, array( '%d' ) );
	}

	/**
	 * Add a deliverable, auto-versioned within its type.
	 *
	 * @param int    $planning_id Planning ID.
	 * @param int    $lead_id     Lead ID.
	 * @param string $type        Deliverable type.
	 * @param string $title       Title.
	 * @param string $notes       Notes.
	 * @param int    $user_id     Creating user ID.
	 * @return int Deliverable ID.
	 */
	public function add_deliverable( int $planning_id, int $lead_id, string $type, string $title, string $notes, int $user_id ): int {
		global $wpdb;

		$wpdb->insert(
			Schema::deliverables_table(),
			array(
				'planning_id' => $planning_id,
				'lead_id'     => $lead_id,
				'type'        => $type,
				'title'       => $title,
				'version'     => $this->next_version( $planning_id, $type ),
				'notes'       => $notes,
				'created_by'  => $user_id,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Next version number for a deliverable type within a planning.
	 *
	 * @param int    $planning_id Planning ID.
	 * @param string $type        Deliverable type.
	 * @return int
	 */
	public function next_version( int $planning_id, string $type ): int {
		global $wpdb;

		$table = Schema::deliverables_table();

		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(version) FROM {$table} WHERE planning_id = %d AND type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$planning_id,
				$type
			)
		);

		return (int) $max + 1;
	}

	/**
	 * Deliverables for a planning.
	 *
	 * @param int $planning_id Planning ID.
	 * @return array<int, object>
	 */
	public function deliverables( int $planning_id ): array {
		global $wpdb;

		$table = Schema::deliverables_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE planning_id = %d ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$planning_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count of deliverables for a planning.
	 *
	 * @param int $planning_id Planning ID.
	 * @return int
	 */
	public function deliverable_count( int $planning_id ): int {
		global $wpdb;

		$table = Schema::deliverables_table();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE planning_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$planning_id
			)
		);
	}

	/**
	 * Add a revision-log entry.
	 *
	 * @param int    $planning_id    Planning ID.
	 * @param int    $lead_id        Lead ID.
	 * @param int    $deliverable_id Related deliverable (0 if none).
	 * @param string $note           Revision note.
	 * @param int    $user_id        Creating user ID.
	 * @return int Revision ID.
	 */
	public function add_revision( int $planning_id, int $lead_id, int $deliverable_id, string $note, int $user_id ): int {
		global $wpdb;

		$wpdb->insert(
			Schema::revisions_table(),
			array(
				'planning_id'    => $planning_id,
				'lead_id'        => $lead_id,
				'deliverable_id' => $deliverable_id,
				'note'           => $note,
				'created_by'     => $user_id,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Revision log for a planning, newest first.
	 *
	 * @param int $planning_id Planning ID.
	 * @return array<int, object>
	 */
	public function revisions( int $planning_id ): array {
		global $wpdb;

		$table = Schema::revisions_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE planning_id = %d ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$planning_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * All planning records.
	 *
	 * @return array<int, object>
	 */
	public function all(): array {
		global $wpdb;

		$table = Schema::plannings_table();

		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Plannings in a given status.
	 *
	 * @param string $status Status key.
	 * @return array<int, object>
	 */
	public function by_status( string $status ): array {
		global $wpdb;

		$table = Schema::plannings_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY updated_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status
			)
		);

		return is_array( $rows ) ? $rows : array();
	}
}
