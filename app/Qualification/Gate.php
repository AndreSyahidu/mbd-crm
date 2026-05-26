<?php
/**
 * Discovery gating based on qualification.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Qualification;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates the rule that a lead must be qualified before it can enter
 * discovery.
 */
class Gate {

	/**
	 * Whether a lead may enter discovery.
	 *
	 * @param object $lead Lead row.
	 * @return bool
	 */
	public static function can_enter_discovery( object $lead ): bool {
		return isset( $lead->qualification ) && 'qualified' === $lead->qualification;
	}
}
