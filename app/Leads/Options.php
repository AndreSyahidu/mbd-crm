<?php
/**
 * Controlled vocabularies and presentation maps for leads.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the option lists used across lead forms,
 * lists, and detail views, plus the chip variant mapping for each.
 */
class Options {

	/**
	 * Lead sources.
	 *
	 * @return array<string, string>
	 */
	public static function sources(): array {
		return array(
			'website'  => __( 'Website', 'mbd-crm' ),
			'referral' => __( 'Referral', 'mbd-crm' ),
			'walk_in'  => __( 'Walk-in', 'mbd-crm' ),
			'social'   => __( 'Social media', 'mbd-crm' ),
			'ads'      => __( 'Advertising', 'mbd-crm' ),
			'other'    => __( 'Other', 'mbd-crm' ),
		);
	}

	/**
	 * Project types.
	 *
	 * @return array<string, string>
	 */
	public static function project_types(): array {
		return array(
			'residential' => __( 'Residential', 'mbd-crm' ),
			'commercial'  => __( 'Commercial', 'mbd-crm' ),
			'renovation'  => __( 'Renovation', 'mbd-crm' ),
			'interior'    => __( 'Interior', 'mbd-crm' ),
			'other'       => __( 'Other', 'mbd-crm' ),
		);
	}

	/**
	 * Service types.
	 *
	 * @return array<string, string>
	 */
	public static function service_types(): array {
		return array(
			'design'        => __( 'Design', 'mbd-crm' ),
			'build'         => __( 'Build', 'mbd-crm' ),
			'design_build'  => __( 'Design & Build', 'mbd-crm' ),
			'consultation'  => __( 'Consultation', 'mbd-crm' ),
			'other'         => __( 'Other', 'mbd-crm' ),
		);
	}

	/**
	 * Urgency levels.
	 *
	 * @return array<string, string>
	 */
	public static function urgencies(): array {
		return array(
			'low'    => __( 'Low', 'mbd-crm' ),
			'medium' => __( 'Medium', 'mbd-crm' ),
			'high'   => __( 'High', 'mbd-crm' ),
			'urgent' => __( 'Urgent', 'mbd-crm' ),
		);
	}

	/**
	 * Lead quality grades.
	 *
	 * @return array<string, string>
	 */
	public static function qualities(): array {
		return array(
			'A'       => __( 'A — Hot', 'mbd-crm' ),
			'B'       => __( 'B — Warm', 'mbd-crm' ),
			'C'       => __( 'C — Cold', 'mbd-crm' ),
			'unknown' => __( 'Unknown', 'mbd-crm' ),
		);
	}

	/**
	 * Lead lifecycle statuses.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			'new'       => __( 'New', 'mbd-crm' ),
			'contacted' => __( 'Contacted', 'mbd-crm' ),
			'qualified' => __( 'Qualified', 'mbd-crm' ),
			'proposal'  => __( 'Proposal', 'mbd-crm' ),
			'won'       => __( 'Won', 'mbd-crm' ),
			'lost'      => __( 'Lost', 'mbd-crm' ),
		);
	}

	/**
	 * Whether a value is a valid key within an option group.
	 *
	 * @param string $group Group name (method name).
	 * @param string $key   Candidate key.
	 * @return bool
	 */
	public static function is_valid( string $group, string $key ): bool {
		$list = self::group( $group );

		return isset( $list[ $key ] );
	}

	/**
	 * Human label for a key, falling back to the key itself.
	 *
	 * @param string $group Group name.
	 * @param string $key   Key to label.
	 * @return string
	 */
	public static function label( string $group, string $key ): string {
		$list = self::group( $group );

		return $list[ $key ] ?? $key;
	}

	/**
	 * Resolve a group name to its option list.
	 *
	 * @param string $group Group name.
	 * @return array<string, string>
	 */
	public static function group( string $group ): array {
		switch ( $group ) {
			case 'sources':
				return self::sources();
			case 'project_types':
				return self::project_types();
			case 'service_types':
				return self::service_types();
			case 'urgencies':
				return self::urgencies();
			case 'qualities':
				return self::qualities();
			case 'statuses':
				return self::statuses();
		}

		return array();
	}

	/**
	 * Chip variant for a status key.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_variant( string $status ): string {
		$map = array(
			'new'       => 'info',
			'contacted' => 'default',
			'qualified' => 'info',
			'proposal'  => 'warning',
			'won'       => 'success',
			'lost'      => 'danger',
		);

		return $map[ $status ] ?? 'default';
	}

	/**
	 * Chip variant for a quality grade.
	 *
	 * @param string $quality Quality key.
	 * @return string
	 */
	public static function quality_variant( string $quality ): string {
		$map = array(
			'A'       => 'success',
			'B'       => 'info',
			'C'       => 'warning',
			'unknown' => 'muted',
		);

		return $map[ $quality ] ?? 'muted';
	}

	/**
	 * Chip variant for an urgency level.
	 *
	 * @param string $urgency Urgency key.
	 * @return string
	 */
	public static function urgency_variant( string $urgency ): string {
		$map = array(
			'low'    => 'muted',
			'medium' => 'default',
			'high'   => 'warning',
			'urgent' => 'danger',
		);

		return $map[ $urgency ] ?? 'default';
	}
}
