<?php
/**
 * Planning gate based on deposit verification.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Deposit;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates the rule that planning is blocked until the deposit is
 * valid or an override has been approved.
 */
class Gate {

	/**
	 * Whether planning may start for a lead.
	 *
	 * @param object $lead Lead row.
	 * @return bool
	 */
	public static function can_plan( object $lead ): bool {
		$valid    = isset( $lead->deposit_status ) && 'valid' === $lead->deposit_status;
		$override = isset( $lead->deposit_override ) && (int) $lead->deposit_override === 1;

		return $valid || $override;
	}
}
