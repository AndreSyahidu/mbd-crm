<?php
/**
 * Persistence layer for leads.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes lead rows and fires the lifecycle actions that drive
 * automation and the audit trail.
 */
class LeadRepository {

	/**
	 * Editable lead columns (the set the controller may write).
	 */
	private const FIELDS = array(
		'name',
		'whatsapp',
		'source',
		'project_type',
		'service_type',
		'estimated_budget',
		'budget_unknown_reason',
		'urgency',
		'quality',
		'status',
		'assigned_to',
		'next_follow_up',
		'notes',
	);

	/**
	 * Find a lead by ID.
	 *
	 * @param int $id Lead ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		$table = Schema::leads_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		return $row ?: null;
	}

	/**
	 * List leads, optionally scoped to a single user's own records.
	 *
	 * @param array{scope?: string, user_id?: int} $args Query args.
	 * @return array<int, object>
	 */
	public function all( array $args = array() ): array {
		global $wpdb;

		$table   = Schema::leads_table();
		$scope   = $args['scope'] ?? 'all';
		$user_id = (int) ( $args['user_id'] ?? 0 );

		if ( 'own' === $scope ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE assigned_to = %d OR created_by = %d ORDER BY created_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$user_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * List leads with a given qualification status, optionally scoped.
	 *
	 * @param string                                $status Qualification status.
	 * @param array{scope?: string, user_id?: int}  $args   Query args.
	 * @return array<int, object>
	 */
	public function by_qualification( string $status, array $args = array() ): array {
		global $wpdb;

		$table   = Schema::leads_table();
		$scope   = $args['scope'] ?? 'all';
		$user_id = (int) ( $args['user_id'] ?? 0 );

		if ( 'own' === $scope ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE qualification = %s AND ( assigned_to = %d OR created_by = %d ) ORDER BY updated_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$status,
					$user_id,
					$user_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE qualification = %s ORDER BY updated_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$status
				)
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert a new lead, start its response SLA, and announce creation.
	 *
	 * @param array<string, mixed> $data    Sanitised field values.
	 * @param int                  $user_id Creating user ID.
	 * @return int New lead ID, or 0 on failure.
	 */
	public function create( array $data, int $user_id ): int {
		global $wpdb;

		$now = current_time( 'mysql' );
		$row = $this->prepare_row( $data );

		// Creation always starts at "new" and opens the response SLA.
		$row['status']         = 'new';
		$row['created_by']     = $user_id;
		$row['created_at']     = $now;
		$row['updated_at']     = $now;
		$row['sla_started_at'] = $now;
		$row['sla_due_at']     = Sla::compute_due( $now );
		$row['sla_status']     = 'running';

		$wpdb->insert( Schema::leads_table(), $row, $this->formats_for( array_keys( $row ) ) );

		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return 0;
		}

		$lead = $this->find( $id );

		/**
		 * Fires after a lead is created.
		 *
		 * @param int    $id   New lead ID.
		 * @param object $lead Lead row.
		 */
		do_action( 'mbd_crm_lead_created', $id, $lead );

		return $id;
	}

	/**
	 * Update an existing lead, detecting status changes, and announce it.
	 *
	 * @param int                  $id   Lead ID.
	 * @param array<string, mixed> $data Sanitised field values.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$existing = $this->find( $id );
		if ( ! $existing ) {
			return false;
		}

		$row               = $this->prepare_row( $data );
		$row['updated_at'] = current_time( 'mysql' );

		$wpdb->update(
			Schema::leads_table(),
			$row,
			array( 'id' => $id ),
			$this->formats_for( array_keys( $row ) ),
			array( '%d' )
		);

		$changes = $this->diff( $existing, $row );

		if ( isset( $row['status'] ) && $existing->status !== $row['status'] ) {
			/**
			 * Fires when a lead's status changes.
			 *
			 * @param int    $id   Lead ID.
			 * @param string $from Previous status.
			 * @param string $to   New status.
			 */
			do_action( 'mbd_crm_lead_status_changed', $id, $existing->status, $row['status'] );
		}

		/**
		 * Fires after a lead is updated.
		 *
		 * @param int                  $id      Lead ID.
		 * @param array<string, mixed> $changes Changed fields keyed by name.
		 */
		do_action( 'mbd_crm_lead_updated', $id, $changes );

		return true;
	}

	/**
	 * Update the lead's "next action" and "next follow-up" surface fields
	 * (written by the Follow-Up module when a follow-up is logged).
	 *
	 * @param int         $id          Lead ID.
	 * @param string      $next_action Next action text.
	 * @param string|null $next_date   Next follow-up date (Y-m-d) or null.
	 * @return void
	 */
	public function set_next( int $id, string $next_action, ?string $next_date ): void {
		global $wpdb;

		$wpdb->update(
			Schema::leads_table(),
			array(
				'next_action'    => $next_action,
				'next_follow_up' => ( null === $next_date || '' === $next_date ) ? null : $next_date,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Leads whose next follow-up is overdue, optionally scoped.
	 *
	 * @param string                                $today Today's date (Y-m-d).
	 * @param array{scope?: string, user_id?: int}  $args  Query args.
	 * @return array<int, object>
	 */
	public function overdue_followups( string $today, array $args = array() ): array {
		global $wpdb;

		$table   = Schema::leads_table();
		$scope   = $args['scope'] ?? 'all';
		$user_id = (int) ( $args['user_id'] ?? 0 );

		if ( 'own' === $scope ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE next_follow_up IS NOT NULL AND next_follow_up < %s AND status NOT IN ( 'won', 'lost' ) AND ( assigned_to = %d OR created_by = %d ) ORDER BY next_follow_up ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$today,
					$user_id,
					$user_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE next_follow_up IS NOT NULL AND next_follow_up < %s AND status NOT IN ( 'won', 'lost' ) ORDER BY next_follow_up ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$today
				)
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Set a lead's qualification status (written by the Qualification module).
	 *
	 * @param int    $id     Lead ID.
	 * @param string $status Qualification status ('qualified', 'not_qualified', '').
	 * @return void
	 */
	public function set_qualification( int $id, string $status ): void {
		global $wpdb;

		$wpdb->update(
			Schema::leads_table(),
			array(
				'qualification' => $status,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Reduce arbitrary input to the known, writable columns. Empty budget
	 * and follow-up values become NULL.
	 *
	 * @param array<string, mixed> $data Field values.
	 * @return array<string, mixed>
	 */
	private function prepare_row( array $data ): array {
		$row      = array();
		$nullable = array( 'estimated_budget', 'next_follow_up' );

		foreach ( self::FIELDS as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}

			$value = $data[ $field ];

			if ( in_array( $field, $nullable, true ) && ( '' === $value || null === $value ) ) {
				$value = null;
			}

			$row[ $field ] = $value;
		}

		return $row;
	}

	/**
	 * Build the $wpdb format list matching a set of columns, in order.
	 *
	 * @param string[] $columns Column names.
	 * @return string[]
	 */
	private function formats_for( array $columns ): array {
		return array_map( array( $this, 'format_for_column' ), $columns );
	}

	/**
	 * $wpdb placeholder for a single column.
	 *
	 * @param string $column Column name.
	 * @return string
	 */
	private function format_for_column( string $column ): string {
		if ( 'estimated_budget' === $column ) {
			return '%f';
		}

		if ( in_array( $column, array( 'assigned_to', 'created_by' ), true ) ) {
			return '%d';
		}

		return '%s';
	}

	/**
	 * Compare an existing row to incoming values.
	 *
	 * @param object               $existing Stored lead.
	 * @param array<string, mixed> $row      Incoming values.
	 * @return array<string, mixed> Changed fields keyed by name.
	 */
	private function diff( object $existing, array $row ): array {
		$changes = array();

		foreach ( $row as $field => $value ) {
			if ( 'updated_at' === $field ) {
				continue;
			}

			$before = isset( $existing->$field ) ? (string) $existing->$field : '';

			if ( $before !== (string) $value ) {
				$changes[ $field ] = $value;
			}
		}

		return $changes;
	}
}
