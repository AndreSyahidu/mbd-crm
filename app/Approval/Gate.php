<?php
/**
 * Closing gate based on client planning approval.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Approval;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates the rule that closing is blocked until planning is approved
 * by the client (with evidence).
 */
class Gate {

	/**
	 * Whether the lead's planning has client approval.
	 *
	 * @param object $lead Lead row.
	 * @return bool
	 */
	public static function is_approved( object $lead ): bool {
		return isset( $lead->planning_approved ) && (int) $lead->planning_approved === 1;
	}
}
