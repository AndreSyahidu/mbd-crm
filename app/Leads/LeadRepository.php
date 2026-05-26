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
		'project_location',
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
	 * Normalise a phone/WhatsApp number to a comparable canonical form.
	 *
	 * Strips non-digits and maps a leading "0" to the Indonesian "62" country
	 * code so 08xx, 62xx and +62 xx variants compare equal.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function normalize_phone( string $raw ): string {
		$digits = preg_replace( '/\D+/', '', $raw );
		if ( '' === $digits ) {
			return '';
		}
		if ( 0 === strpos( $digits, '0' ) ) {
			$digits = '62' . substr( $digits, 1 );
		}

		return $digits;
	}

	/**
	 * Duplicate candidates for a lead: other non-merged leads matching by
	 * normalized WhatsApp, or by name + project location.
	 *
	 * @param object $lead Lead row.
	 * @return array<int, object> Each row gains a `match_reason` property.
	 */
	public function duplicate_candidates( object $lead ): array {
		global $wpdb;

		$table      = Schema::leads_table();
		$id         = (int) $lead->id;
		$normalized = self::normalize_phone( (string) ( $lead->whatsapp ?? '' ) );
		$name       = (string) ( $lead->name ?? '' );
		$location   = (string) ( $lead->project_location ?? '' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE id <> %d AND lifecycle <> 'merged'
				AND (
					( whatsapp_normalized <> '' AND whatsapp_normalized = %s )
					OR ( name <> '' AND name = %s AND project_location <> '' AND project_location = %s )
				)
				ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id,
				$normalized,
				$name,
				$location
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as $row ) {
			if ( '' !== $normalized && $normalized === self::normalize_phone( (string) $row->whatsapp ) ) {
				$row->match_reason = ( (string) $row->whatsapp === (string) $lead->whatsapp )
					? __( 'Exact WhatsApp match', 'mbd-crm' )
					: __( 'Normalized WhatsApp match', 'mbd-crm' );
			} else {
				$row->match_reason = __( 'Same name + project location', 'mbd-crm' );
			}
		}

		return $rows;
	}

	/**
	 * Whether a lead already exists with the same WhatsApp number and name
	 * (used for import de-duplication).
	 *
	 * @param string $whatsapp WhatsApp digits.
	 * @param string $name     Lead name.
	 * @return bool
	 */
	public function exists_by_whatsapp_name( string $whatsapp, string $name ): bool {
		global $wpdb;

		$table = Schema::leads_table();

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE whatsapp = %s AND name = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$whatsapp,
				$name
			)
		);

		return ! empty( $id );
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
		$row['sla_started_at']        = $now;
		$row['sla_due_at']            = Sla::compute_due( $now );
		$row['sla_status']            = 'running';
		$row['stage']                 = 'new';
		$row['last_stage_changed_at'] = $now;
		$row['lifecycle']             = 'active';
		$row['whatsapp_normalized']   = self::normalize_phone( (string) ( $row['whatsapp'] ?? '' ) );

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
		if ( array_key_exists( 'whatsapp', $row ) ) {
			$row['whatsapp_normalized'] = self::normalize_phone( (string) $row['whatsapp'] );
		}

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

		$this->sync_stage( $id );

		return true;
	}

	/**
	 * Recompute the lead's derived stage. When it changes, reset the aging
	 * clock, clear any stale flag, and append a stage-history entry.
	 *
	 * @param int    $id     Lead ID.
	 * @param string $reason Optional reason for the transition.
	 * @return void
	 */
	public function sync_stage( int $id, string $reason = '' ): void {
		global $wpdb;

		$lead = $this->find( $id );
		if ( ! $lead ) {
			return;
		}

		$new = Stage::key( $lead );
		$old = (string) ( $lead->stage ?? '' );
		if ( $new === $old ) {
			return;
		}

		$wpdb->update(
			Schema::leads_table(),
			array(
				'stage'                 => $new,
				'last_stage_changed_at' => current_time( 'mysql' ),
				'stale_flag'            => 0,
				'stale_reason'          => '',
				'stale_since'           => null,
				'updated_at'            => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		$this->record_stage_history( $id, $old, $new, $reason );
	}

	/**
	 * Append a stage-history row.
	 *
	 * @param int                  $lead_id Lead ID.
	 * @param string               $from    Previous stage.
	 * @param string               $to      New stage.
	 * @param string               $reason  Optional reason.
	 * @param array<string, mixed> $meta    Optional metadata.
	 * @return void
	 */
	public function record_stage_history( int $lead_id, string $from, string $to, string $reason = '', array $meta = array() ): void {
		global $wpdb;

		$wpdb->insert(
			Schema::stage_history_table(),
			array(
				'lead_id'       => $lead_id,
				'from_status'   => $from,
				'to_status'     => $to,
				'changed_by'    => get_current_user_id(),
				'changed_at'    => current_time( 'mysql' ),
				'reason'        => $reason,
				'metadata_json' => (string) wp_json_encode( $meta ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Stage history for a lead, newest first.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, object>
	 */
	public function stage_history( int $lead_id ): array {
		global $wpdb;

		$table = Schema::stage_history_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Set a lead's lifecycle (active / on_hold / archived / merged) and
	 * optional related fields, then re-sync the derived stage.
	 *
	 * @param int                  $id        Lead ID.
	 * @param string               $lifecycle Lifecycle value.
	 * @param array<string, mixed> $extra     Extra columns to set.
	 * @param string               $reason    Stage-history reason.
	 * @return void
	 */
	public function set_lifecycle( int $id, string $lifecycle, array $extra = array(), string $reason = '' ): void {
		global $wpdb;

		$data   = array( 'lifecycle' => $lifecycle, 'updated_at' => current_time( 'mysql' ) );
		$format = array( '%s', '%s' );

		$allowed = array(
			'reactivation_at'     => '%s',
			'reactivation_reason' => '%s',
			'closing_status'      => '%s',
			'stale_flag'          => '%d',
			'stale_reason'        => '%s',
			'stale_since'         => '%s',
		);
		foreach ( $extra as $key => $value ) {
			if ( isset( $allowed[ $key ] ) ) {
				$data[ $key ] = $value;
				$format[]     = $allowed[ $key ];
			}
		}

		$wpdb->update( Schema::leads_table(), $data, array( 'id' => $id ), $format, array( '%d' ) );

		$this->sync_stage( $id, $reason );
	}

	/**
	 * Mark a lead stale (idempotent: keeps the original stale_since).
	 *
	 * @param object $lead   Lead row.
	 * @param string $reason Stale reason.
	 * @return void
	 */
	public function flag_stale( object $lead, string $reason ): void {
		global $wpdb;

		$data = array(
			'stale_flag'   => 1,
			'stale_reason' => $reason,
			'updated_at'   => current_time( 'mysql' ),
		);
		$format = array( '%d', '%s', '%s' );

		if ( empty( $lead->stale_since ) ) {
			$data['stale_since'] = current_time( 'mysql' );
			$format[]            = '%s';
		}

		$wpdb->update( Schema::leads_table(), $data, array( 'id' => (int) $lead->id ), $format, array( '%d' ) );
	}

	/**
	 * Clear a lead's stale flag.
	 *
	 * @param int $id Lead ID.
	 * @return void
	 */
	public function clear_stale( int $id ): void {
		global $wpdb;

		$wpdb->update(
			Schema::leads_table(),
			array(
				'stale_flag'   => 0,
				'stale_reason' => '',
				'stale_since'  => null,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark a lead as merged into another and re-sync its stage.
	 *
	 * @param int $secondary_id Lead being merged away.
	 * @param int $primary_id   Surviving lead.
	 * @return void
	 */
	public function mark_merged( int $secondary_id, int $primary_id ): void {
		global $wpdb;

		$wpdb->update(
			Schema::leads_table(),
			array(
				'lifecycle'           => 'merged',
				'merged_into_lead_id' => $primary_id,
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'id' => $secondary_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		$this->sync_stage( $secondary_id, __( 'Merged', 'mbd-crm' ) );
	}

	/**
	 * Active pipeline leads (lifecycle active, not in a terminal stage),
	 * optionally scoped to the current user.
	 *
	 * @param array{scope?: string, user_id?: int} $args Query args.
	 * @return array<int, object>
	 */
	public function active_pipeline( array $args = array() ): array {
		global $wpdb;

		$table   = Schema::leads_table();
		$scope   = $args['scope'] ?? 'all';
		$user_id = (int) ( $args['user_id'] ?? 0 );

		$where = "lifecycle = 'active' AND stage NOT IN ( 'closing_approved', 'closing_failed' )";

		if ( 'own' === $scope ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$where} AND ( assigned_to = %d OR created_by = %d ) ORDER BY last_stage_changed_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$user_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				"SELECT * FROM {$table} WHERE {$where} ORDER BY last_stage_changed_at ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Set a lead's qualification status (written by the Qualification module).

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
	 * Set the deposit-collection SLA due time (written by the Discovery module
	 * when a discovery is completed).
	 *
	 * @param int         $id  Lead ID.
	 * @param string|null $due Due datetime (Y-m-d H:i:s) or null.
	 * @return void
	 */
	public function set_deposit_sla( int $id, ?string $due ): void {
		global $wpdb;

		$wpdb->update(
			Schema::leads_table(),
			array(
				'deposit_sla_due' => ( null === $due || '' === $due ) ? null : $due,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update one or more pipeline stage flags on a lead. Only the
	 * allowlisted stage columns may be written through this method.
	 *
	 * @param int                  $id     Lead ID.
	 * @param array<string, mixed> $fields Column => value.
	 * @return void
	 */
	public function set_stage( int $id, array $fields ): void {
		global $wpdb;

		$allowed = array(
			'deposit_status'    => '%s',
			'deposit_override'  => '%d',
			'planning_approved' => '%d',
			'closing_status'    => '%s',
		);

		$data   = array();
		$format = array();
		foreach ( $fields as $key => $value ) {
			if ( isset( $allowed[ $key ] ) ) {
				$data[ $key ] = $value;
				$format[]     = $allowed[ $key ];
			}
		}

		if ( empty( $data ) ) {
			return;
		}

		$data['updated_at'] = current_time( 'mysql' );
		$format[]           = '%s';

		$wpdb->update( Schema::leads_table(), $data, array( 'id' => $id ), $format, array( '%d' ) );

		$this->sync_stage( $id );
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

		$this->sync_stage( $id );
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
