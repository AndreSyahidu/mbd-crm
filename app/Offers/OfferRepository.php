<?php
/**
 * Persistence for versioned offers.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Offers;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes versioned offers. Offers are append-only: a change creates
 * a new version and supersedes the prior open one rather than mutating it.
 */
class OfferRepository {

	/**
	 * Non-terminal statuses (a newer version supersedes these).
	 */
	private const OPEN_STATUSES = array( 'draft', 'pending_approval', 'approved', 'sent' );

	/**
	 * All offer versions for a lead, newest first.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, object>
	 */
	public function for_lead( int $lead_id ): array {
		global $wpdb;

		$table = Schema::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY version DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * The latest offer version for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return object|null
	 */
	public function current( int $lead_id ): ?object {
		$all = $this->for_lead( $lead_id );

		return $all[0] ?? null;
	}

	/**
	 * Find a single offer by ID.
	 *
	 * @param int $id Offer ID.
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
	 * Next version number for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return int
	 */
	public function next_version( int $lead_id ): int {
		global $wpdb;

		$table = Schema::table();

		$max = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(version) FROM {$table} WHERE lead_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return $max + 1;
	}

	/**
	 * Mark all open versions of a lead's offers as superseded.
	 *
	 * @param int $lead_id Lead ID.
	 * @return void
	 */
	public function supersede_open( int $lead_id ): void {
		global $wpdb;

		$table        = Schema::table();
		$placeholders = implode( ',', array_fill( 0, count( self::OPEN_STATUSES ), '%s' ) );
		$now          = current_time( 'mysql' );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'superseded', updated_at = %s WHERE lead_id = %d AND status IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $now, $lead_id ), self::OPEN_STATUSES )
			)
		);
	}

	/**
	 * Insert a new offer version.
	 *
	 * @param array<string, mixed> $data Offer fields.
	 * @return int Offer ID.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		$wpdb->insert(
			Schema::table(),
			array(
				'lead_id'           => (int) $data['lead_id'],
				'closing_id'        => (int) ( $data['closing_id'] ?? 0 ),
				'version'           => (int) $data['version'],
				'base_price'        => (float) $data['base_price'],
				'discount_type'     => (string) $data['discount_type'],
				'discount_value'    => (float) $data['discount_value'],
				'discount_percent'  => (float) $data['discount_percent'],
				'final_value'       => (float) $data['final_value'],
				'valid_until'       => ( '' !== (string) ( $data['valid_until'] ?? '' ) ) ? $data['valid_until'] : null,
				'scope'             => (string) ( $data['scope'] ?? '' ),
				'status'            => (string) $data['status'],
				'approval_required' => (int) ( $data['approval_required'] ?? 0 ),
				'created_by'        => (int) $data['created_by'],
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%d', '%d', '%f', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an offer's status and decision metadata.
	 *
	 * @param int                  $id     Offer ID.
	 * @param string               $status New status.
	 * @param array<string, mixed> $extra  Optional approved_by / approved_at / decision_reason.
	 * @return void
	 */
	public function set_status( int $id, string $status, array $extra = array() ): void {
		global $wpdb;

		$data   = array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) );
		$format = array( '%s', '%s' );

		if ( isset( $extra['approved_by'] ) ) {
			$data['approved_by'] = (int) $extra['approved_by'];
			$format[]            = '%d';
		}
		if ( isset( $extra['approved_at'] ) ) {
			$data['approved_at'] = (string) $extra['approved_at'];
			$format[]            = '%s';
		}
		if ( isset( $extra['decision_reason'] ) ) {
			$data['decision_reason'] = (string) $extra['decision_reason'];
			$format[]                = '%s';
		}

		$wpdb->update( Schema::table(), $data, array( 'id' => $id ), $format, array( '%d' ) );
	}
}
