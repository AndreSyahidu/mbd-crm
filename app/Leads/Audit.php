<?php
/**
 * Lead audit trail.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Records create / update / status-change events and reads them back for
 * the lead detail view.
 */
class Audit {

	public const CREATED        = 'lead.created';
	public const UPDATED        = 'lead.updated';
	public const STATUS_CHANGED = 'lead.status_changed';
	public const QUALIFIED      = 'lead.qualified';
	public const DISQUALIFIED   = 'lead.disqualified';
	public const FOLLOWUP       = 'lead.followup_logged';
	public const PROMISE_MADE   = 'lead.promise_created';
	public const PROMISE_STATUS = 'lead.promise_status_changed';

	/**
	 * Hook the audit recorder onto lead lifecycle actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'mbd_crm_lead_created', array( $this, 'on_created' ), 10, 2 );
		add_action( 'mbd_crm_lead_updated', array( $this, 'on_updated' ), 10, 2 );
		add_action( 'mbd_crm_lead_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
	}

	/**
	 * Record a lead creation.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead    Lead row.
	 * @return void
	 */
	public function on_created( int $lead_id, object $lead ): void {
		self::record( $lead_id, self::CREATED, array( 'name' => $lead->name ?? '' ) );
	}

	/**
	 * Record a lead update (skips no-op saves).
	 *
	 * @param int                  $lead_id Lead ID.
	 * @param array<string, mixed> $changes Changed field => new value.
	 * @return void
	 */
	public function on_updated( int $lead_id, array $changes ): void {
		if ( empty( $changes ) ) {
			return;
		}

		self::record( $lead_id, self::UPDATED, array( 'fields' => array_keys( $changes ) ) );
	}

	/**
	 * Record a status transition.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $from    Previous status.
	 * @param string $to      New status.
	 * @return void
	 */
	public function on_status_changed( int $lead_id, string $from, string $to ): void {
		self::record(
			$lead_id,
			self::STATUS_CHANGED,
			array(
				'from' => $from,
				'to'   => $to,
			)
		);
	}

	/**
	 * Persist an audit entry.
	 *
	 * @param int                  $lead_id Lead ID.
	 * @param string               $action  Action constant.
	 * @param array<string, mixed> $detail  Structured detail.
	 * @return void
	 */
	public static function record( int $lead_id, string $action, array $detail = array() ): void {
		global $wpdb;

		$wpdb->insert(
			Schema::audit_table(),
			array(
				'lead_id'    => $lead_id,
				'user_id'    => get_current_user_id(),
				'action'     => $action,
				'detail'     => (string) wp_json_encode( $detail ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Most recent audit entries for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @param int $limit   Maximum rows.
	 * @return array<int, object>
	 */
	public static function recent_for_lead( int $lead_id, int $limit = 20 ): array {
		global $wpdb;

		$table = Schema::audit_table();

		// Table name comes from a trusted prefix; values are parameterised.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY created_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id,
				$limit
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Human summary for an audit row.
	 *
	 * @param object $entry Audit row.
	 * @return string
	 */
	public static function describe( object $entry ): string {
		$detail = json_decode( (string) $entry->detail, true );
		$detail = is_array( $detail ) ? $detail : array();

		switch ( $entry->action ) {
			case self::CREATED:
				return __( 'Lead created', 'mbd-crm' );
			case self::STATUS_CHANGED:
				return sprintf(
					/* translators: 1: previous status, 2: new status. */
					__( 'Status changed from %1$s to %2$s', 'mbd-crm' ),
					Options::label( 'statuses', (string) ( $detail['from'] ?? '' ) ),
					Options::label( 'statuses', (string) ( $detail['to'] ?? '' ) )
				);
			case self::UPDATED:
				$fields = isset( $detail['fields'] ) && is_array( $detail['fields'] ) ? implode( ', ', $detail['fields'] ) : '';
				return '' !== $fields
					/* translators: %s: comma-separated field names. */
					? sprintf( __( 'Updated: %s', 'mbd-crm' ), $fields )
					: __( 'Lead updated', 'mbd-crm' );
			case self::QUALIFIED:
				return sprintf(
					/* translators: %d: qualification score. */
					__( 'Marked qualified (score %d)', 'mbd-crm' ),
					(int) ( $detail['score'] ?? 0 )
				);
			case self::DISQUALIFIED:
				$reason = (string) ( $detail['reason'] ?? '' );
				return '' !== $reason
					/* translators: %s: reason. */
					? sprintf( __( 'Marked not qualified: %s', 'mbd-crm' ), $reason )
					: __( 'Marked not qualified', 'mbd-crm' );
			case self::FOLLOWUP:
				return sprintf(
					/* translators: %s: channel. */
					__( 'Follow-up logged (%s)', 'mbd-crm' ),
					(string) ( $detail['channel'] ?? '' )
				);
			case self::PROMISE_MADE:
				return __( 'Promise recorded', 'mbd-crm' );
			case self::PROMISE_STATUS:
				return sprintf(
					/* translators: 1: previous status, 2: new status. */
					__( 'Promise %1$s → %2$s', 'mbd-crm' ),
					(string) ( $detail['from'] ?? '' ),
					(string) ( $detail['to'] ?? '' )
				);
		}

		return $entry->action;
	}
}
