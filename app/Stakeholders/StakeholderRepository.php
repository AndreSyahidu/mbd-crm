<?php
/**
 * Persistence for stakeholders.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Stakeholders;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes stakeholder rows.
 */
class StakeholderRepository {

	/**
	 * Roles a stakeholder can hold.
	 *
	 * @return array<string, string>
	 */
	public static function roles(): array {
		return array(
			'decision_maker'    => __( 'Decision maker', 'mbd-crm' ),
			'influencer'        => __( 'Influencer', 'mbd-crm' ),
			'spouse'            => __( 'Spouse', 'mbd-crm' ),
			'parent'            => __( 'Parent', 'mbd-crm' ),
			'investor'          => __( 'Investor', 'mbd-crm' ),
			'technical_advisor' => __( 'Technical advisor', 'mbd-crm' ),
			'other'             => __( 'Other', 'mbd-crm' ),
		);
	}

	/**
	 * Decision-power levels.
	 *
	 * @return array<string, string>
	 */
	public static function powers(): array {
		return array(
			'high'    => __( 'High', 'mbd-crm' ),
			'medium'  => __( 'Medium', 'mbd-crm' ),
			'low'     => __( 'Low', 'mbd-crm' ),
			'unknown' => __( 'Unknown', 'mbd-crm' ),
		);
	}

	/**
	 * Stakeholders for a lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return array<int, object>
	 */
	public function for_lead( int $lead_id ): array {
		global $wpdb;

		$table = Schema::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE lead_id = %d ORDER BY is_primary_decision_maker DESC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find a stakeholder by ID.
	 *
	 * @param int $id Stakeholder ID.
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
	 * Whether a lead has a primary decision maker.
	 *
	 * @param int $lead_id Lead ID.
	 * @return bool
	 */
	public function has_decision_maker( int $lead_id ): bool {
		global $wpdb;

		$table = Schema::table();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE lead_id = %d AND is_primary_decision_maker = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$lead_id
			)
		);

		return $count > 0;
	}

	/**
	 * Create a stakeholder.
	 *
	 * @param int                  $lead_id Lead ID.
	 * @param array<string, mixed> $data    Sanitised fields.
	 * @param int                  $user_id Creating user ID.
	 * @return int New stakeholder ID.
	 */
	public function create( int $lead_id, array $data, int $user_id ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		$wpdb->insert(
			Schema::table(),
			array(
				'lead_id'                   => $lead_id,
				'name'                      => $data['name'],
				'role'                      => $data['role'],
				'phone'                     => $data['phone'],
				'email'                     => $data['email'],
				'decision_power'            => $data['decision_power'],
				'relationship_note'         => $data['relationship_note'],
				'is_primary_decision_maker' => ! empty( $data['is_primary_decision_maker'] ) ? 1 : 0,
				'created_by'                => $user_id,
				'created_at'                => $now,
				'updated_at'                => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Clear the primary flag for all stakeholders of a lead (single primary).
	 *
	 * @param int $lead_id Lead ID.
	 * @return void
	 */
	public function clear_primary( int $lead_id ): void {
		global $wpdb;

		$wpdb->update(
			Schema::table(),
			array( 'is_primary_decision_maker' => 0 ),
			array( 'lead_id' => $lead_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Set a stakeholder as the primary decision maker (clears others first).
	 *
	 * @param int $id      Stakeholder ID.
	 * @param int $lead_id Lead ID.
	 * @return void
	 */
	public function set_primary( int $id, int $lead_id ): void {
		global $wpdb;

		$this->clear_primary( $lead_id );

		$wpdb->update(
			Schema::table(),
			array(
				'is_primary_decision_maker' => 1,
				'updated_at'                => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a stakeholder.
	 *
	 * @param int $id Stakeholder ID.
	 * @return void
	 */
	public function delete( int $id ): void {
		global $wpdb;

		$wpdb->delete( Schema::table(), array( 'id' => $id ), array( '%d' ) );
	}
}
