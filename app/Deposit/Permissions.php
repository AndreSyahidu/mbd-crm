<?php
/**
 * Deposit permission checks.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Deposit;

use MBD\CRM\Leads\Capabilities;
use MBD\CRM\Leads\Permissions as LeadPermissions;

defined( 'ABSPATH' ) || exit;

/**
 * Permission helpers for deposit requests, verification, and overrides.
 */
class Permissions {

	/**
	 * Whether the current user may create/update the deposit request.
	 *
	 * @param object $lead Lead row.
	 * @return bool
	 */
	public static function can_request( object $lead ): bool {
		return LeadPermissions::can_edit( $lead );
	}

	/**
	 * Whether the current user may verify or reject deposits (Finance).
	 *
	 * @return bool
	 */
	public static function can_verify(): bool {
		return current_user_can( Capabilities::VERIFY_DEPOSITS );
	}

	/**
	 * Whether the current user may override the deposit gate (Owner/Admin).
	 *
	 * @return bool
	 */
	public static function can_override(): bool {
		return current_user_can( Capabilities::OVERRIDE_DEPOSIT );
	}
}
