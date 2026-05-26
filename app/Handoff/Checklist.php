<?php
/**
 * Project-handoff checklist definition.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Handoff;

defined( 'ABSPATH' ) || exit;

/**
 * The list of things that must be true before a won deal is handed to the
 * delivery / project team.
 */
class Checklist {

	/**
	 * Checklist items (key => label), filterable.
	 *
	 * @return array<string, string>
	 */
	public static function items(): array {
		$items = array(
			'contract_signed'       => __( 'Contract signed', 'mbd-crm' ),
			'deposit_cleared'       => __( 'Deposit cleared', 'mbd-crm' ),
			'final_design_approved' => __( 'Final design approved', 'mbd-crm' ),
			'site_survey_done'      => __( 'Site survey completed', 'mbd-crm' ),
			'pic_assigned'          => __( 'Project PIC assigned', 'mbd-crm' ),
			'schedule_shared'       => __( 'Schedule shared with client', 'mbd-crm' ),
		);

		/**
		 * Filter the handoff checklist items.
		 *
		 * @param array<string, string> $items Item key => label.
		 */
		return apply_filters( 'mbd_crm_handoff_checklist', $items );
	}

	/**
	 * A fresh checklist state (every item unchecked).
	 *
	 * @return array<string, bool>
	 */
	public static function fresh(): array {
		return array_fill_keys( array_keys( self::items() ), false );
	}

	/**
	 * Normalise a stored checklist against the current item set.
	 *
	 * @param array<string, mixed> $stored Stored checklist.
	 * @return array<string, bool>
	 */
	public static function normalize( array $stored ): array {
		$out = array();
		foreach ( array_keys( self::items() ) as $key ) {
			$out[ $key ] = ! empty( $stored[ $key ] );
		}

		return $out;
	}

	/**
	 * Whether every checklist item is complete.
	 *
	 * @param array<string, mixed> $checklist Checklist state.
	 * @return bool
	 */
	public static function is_complete( array $checklist ): bool {
		foreach ( self::normalize( $checklist ) as $done ) {
			if ( ! $done ) {
				return false;
			}
		}

		return true;
	}
}
