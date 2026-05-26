<?php
/**
 * Planning statuses and deliverable types.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Planning;

defined( 'ABSPATH' ) || exit;

/**
 * Vocabularies and chip variants for planning.
 */
class Options {

	/**
	 * Planning statuses.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			'draft'       => __( 'Draft', 'mbd-crm' ),
			'in_progress' => __( 'In progress', 'mbd-crm' ),
			'revision'    => __( 'Revision', 'mbd-crm' ),
			'final'       => __( 'Final', 'mbd-crm' ),
			'approved'    => __( 'Approved', 'mbd-crm' ),
			'cancelled'   => __( 'Cancelled', 'mbd-crm' ),
		);
	}

	/**
	 * Statuses a user may set manually (approval comes from client evidence).
	 *
	 * @return array<string, string>
	 */
	public static function editable_statuses(): array {
		$all = self::statuses();
		unset( $all['approved'] );

		return $all;
	}

	/**
	 * Internal review states.
	 *
	 * @return array<string, string>
	 */
	public static function reviews(): array {
		return array(
			'pending' => __( 'Pending', 'mbd-crm' ),
			'passed'  => __( 'Passed', 'mbd-crm' ),
			'failed'  => __( 'Failed', 'mbd-crm' ),
		);
	}

	/**
	 * Deliverable types.
	 *
	 * @return array<string, string>
	 */
	public static function deliverable_types(): array {
		return array(
			'concept_design' => __( 'Concept design', 'mbd-crm' ),
			'layout'         => __( 'Layout', 'mbd-crm' ),
			'ded'            => __( 'DED', 'mbd-crm' ),
			'gambar_kerja'   => __( 'Gambar kerja', 'mbd-crm' ),
			'rab'            => __( 'RAB', 'mbd-crm' ),
			'boq'            => __( 'BOQ', 'mbd-crm' ),
			'render_3d'      => __( '3D render', 'mbd-crm' ),
			'timeline'       => __( 'Timeline', 'mbd-crm' ),
			'scope_document' => __( 'Scope document', 'mbd-crm' ),
			'final_document' => __( 'Final document', 'mbd-crm' ),
			'other'          => __( 'Other', 'mbd-crm' ),
		);
	}

	/**
	 * Whether a status key is valid (editable set).
	 *
	 * @param string $key Status key.
	 * @return bool
	 */
	public static function is_editable_status( string $key ): bool {
		return isset( self::editable_statuses()[ $key ] );
	}

	/**
	 * Whether a review key is valid.
	 *
	 * @param string $key Review key.
	 * @return bool
	 */
	public static function is_review( string $key ): bool {
		return isset( self::reviews()[ $key ] );
	}

	/**
	 * Whether a deliverable type is valid.
	 *
	 * @param string $key Type key.
	 * @return bool
	 */
	public static function is_deliverable_type( string $key ): bool {
		return isset( self::deliverable_types()[ $key ] );
	}

	/**
	 * Label for a status key.
	 *
	 * @param string $key Status key.
	 * @return string
	 */
	public static function status_label( string $key ): string {
		return self::statuses()[ $key ] ?? $key;
	}

	/**
	 * Label for a deliverable type.
	 *
	 * @param string $key Type key.
	 * @return string
	 */
	public static function deliverable_label( string $key ): string {
		return self::deliverable_types()[ $key ] ?? $key;
	}

	/**
	 * Chip variant for a status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_variant( string $status ): string {
		$map = array(
			'draft'       => 'muted',
			'in_progress' => 'info',
			'revision'    => 'warning',
			'final'       => 'info',
			'approved'    => 'success',
			'cancelled'   => 'danger',
		);

		return $map[ $status ] ?? 'default';
	}
}
