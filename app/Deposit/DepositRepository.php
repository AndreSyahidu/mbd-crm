<?php
/**
 * Persistence for deposit records.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Deposit;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the per-lead deposit record.
 */
class DepositRepository {

	/**
	 * The current deposit record for a lead.
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
	 * Find a deposit by ID.
	 *
	 * @param int $id Deposit ID.
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
	 * Create or update the deposit request, resetting it to pending.
	 *
	 * @param int                  $lead_id    Lead ID.
	 * @param array<string, mixed> $data       Sanitised fields.
	 * @param int                  $attach_id  Proof attachment ID (0 if none).
	 * @param string               $attach_url Proof URL.
	 * @param int                  $user_id    Acting user ID.
	 * @return int Deposit ID.
	 */
	public function request( int $lead_id, array $data, int $attach_id, string $attach_url, int $user_id ): int {
		global $wpdb;

		$now      = current_time( 'mysql' );
		$existing = $this->for_lead( $lead_id );

		$fields = array(
			'required_amount' => ( '' === $data['required_amount'] ) ? null : $data['required_amount'],
			'paid_amount'     => ( '' === $data['paid_amount'] ) ? null : $data['paid_amount'],
			'payment_date'    => ( '' === $data['payment_date'] ) ? null : $data['payment_date'],
			'payment_method'  => $data['payment_method'],
			'receipt_number'  => $data['receipt_number'],
			'status'          => 'pending',
			'rejection_reason' => '',
			'updated_at'      => $now,
		);
		$format = array( '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $attach_id > 0 ) {
			$fields['proof_id']  = $attach_id;
			$fields['proof_url'] = $attach_url;
			$format[]            = '%d';
			$format[]            = '%s';
		}

		if ( $existing ) {
			$wpdb->update( Schema::table(), $fields, array( 'id' => (int) $existing->id ), $format, array( '%d' ) );
			return (int) $existing->id;
		}

		$fields['lead_id']    = $lead_id;
		$fields['created_by'] = $user_id;
		$fields['created_at'] = $now;
		$format[]             = '%d';
		$format[]             = '%d';
		$format[]             = '%s';

		$wpdb->insert( Schema::table(), $fields, $format );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Mark a deposit valid.
	 *
	 * @param int $id      Deposit ID.
	 * @param int $user_id Verifying user ID.
	 * @return void
	 */
	public function verify( int $id, int $user_id ): void {
		global $wpdb;

		$wpdb->update(
			Schema::table(),
			array(
				'status'      => 'valid',
				'verified_by' => $user_id,
				'verified_at' => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark a deposit rejected with a reason.
	 *
	 * @param int    $id     Deposit ID.
	 * @param string $reason Rejection reason.
	 * @return void
	 */
	public function reject( int $id, string $reason ): void {
		global $wpdb;

		$wpdb->update(
			Schema::table(),
			array(
				'status'           => 'rejected',
				'rejection_reason' => $reason,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Record an owner/admin override with a reason.
	 *
	 * @param int    $id     Deposit ID.
	 * @param string $reason Override reason.
	 * @return void
	 */
	public function set_override( int $id, string $reason ): void {
		global $wpdb;

		$wpdb->update(
			Schema::table(),
			array(
				'is_override'     => 1,
				'override_reason' => $reason,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Deposits in a given status.
	 *
	 * @param string $status Status key.
	 * @return array<int, object>
	 */
	public function by_status( string $status ): array {
		global $wpdb;

		$table = Schema::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY updated_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Pending deposits that have a proof attached (awaiting verification).
	 *
	 * @return array<int, object>
	 */
	public function pending_with_proof(): array {
		global $wpdb;

		$table = Schema::table();

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'pending' AND proof_id > 0 ORDER BY updated_at DESC, id DESC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return is_array( $rows ) ? $rows : array();
	}
}
