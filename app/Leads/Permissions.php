<?php
/**
 * Runtime permission checks for leads.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Translates lead capabilities and record ownership into yes/no answers
 * the controller and views can act on.
 */
class Permissions {

	/**
	 * Whether the current user may open the Leads area at all.
	 *
	 * @return bool
	 */
	public static function can_access(): bool {
		return current_user_can( Capabilities::ACCESS_LEADS );
	}

	/**
	 * Whether the current user may create leads.
	 *
	 * @return bool
	 */
	public static function can_create(): bool {
		return current_user_can( Capabilities::CREATE_LEADS );
	}

	/**
	 * Whether the current user may see every lead.
	 *
	 * @return bool
	 */
	public static function can_view_all(): bool {
		return current_user_can( Capabilities::VIEW_ALL_LEADS );
	}

	/**
	 * Whether the current user may edit leads they do not own.
	 *
	 * @return bool
	 */
	public static function can_edit_others(): bool {
		return current_user_can( Capabilities::EDIT_OTHERS_LEADS );
	}

	/**
	 * Whether the current user may assign leads to other sales reps.
	 *
	 * @return bool
	 */
	public static function can_assign(): bool {
		return current_user_can( Capabilities::ASSIGN_LEADS );
	}

	/**
	 * Whether the current user owns a lead (creator or assignee).
	 *
	 * @param object $lead Lead row.
	 * @return bool
	 */
	public static function owns( object $lead ): bool {
		$uid = get_current_user_id();

		if ( ! $uid ) {
			return false;
		}

		return (int) $lead->created_by === $uid || (int) $lead->assigned_to === $uid;
	}

	/**
	 * Whether the current user may view a specific lead.
	 *
	 * @param object $lead Lead row.
	 * @return bool
	 */
	public static function can_view( object $lead ): bool {
		return self::can_view_all() || self::owns( $lead );
	}

	/**
	 * Whether the current user may edit a specific lead.
	 *
	 * @param object $lead Lead row.
	 * @return bool
	 */
	public static function can_edit( object $lead ): bool {
		if ( self::can_edit_others() ) {
			return true;
		}

		return current_user_can( Capabilities::EDIT_LEADS ) && self::owns( $lead );
	}

	/**
	 * Whether the current user may edit at least one lead (for UI affordances).
	 *
	 * @return bool
	 */
	public static function can_edit_any(): bool {
		return self::can_create() || self::can_edit_others() || current_user_can( Capabilities::EDIT_LEADS );
	}
}
