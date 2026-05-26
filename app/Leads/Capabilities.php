<?php
/**
 * Lead capabilities and CRM roles.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the lead capabilities and the CRM roles that group them, and
 * installs / removes them on (de)activation and uninstall.
 */
class Capabilities {

	public const ACCESS_LEADS       = 'mbd_crm_access_leads';
	public const CREATE_LEADS       = 'mbd_crm_create_leads';
	public const EDIT_LEADS         = 'mbd_crm_edit_leads';
	public const EDIT_OTHERS_LEADS  = 'mbd_crm_edit_others_leads';
	public const VIEW_ALL_LEADS     = 'mbd_crm_view_all_leads';
	public const ASSIGN_LEADS       = 'mbd_crm_assign_leads';
	public const VERIFY_DEPOSITS    = 'mbd_crm_verify_deposits';
	public const OVERRIDE_DEPOSIT   = 'mbd_crm_override_deposit';
	public const APPROVE_CLOSING    = 'mbd_crm_approve_closing';
	public const MERGE_LEADS        = 'mbd_crm_merge_leads';
	public const OVERRIDE_SCORE     = 'mbd_crm_override_score';
	public const APPROVE_DISCOUNT   = 'mbd_crm_approve_discount';

	/**
	 * Every lead capability.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::ACCESS_LEADS,
			self::CREATE_LEADS,
			self::EDIT_LEADS,
			self::EDIT_OTHERS_LEADS,
			self::VIEW_ALL_LEADS,
			self::ASSIGN_LEADS,
			self::VERIFY_DEPOSITS,
			self::OVERRIDE_DEPOSIT,
			self::APPROVE_CLOSING,
			self::MERGE_LEADS,
			self::OVERRIDE_SCORE,
			self::APPROVE_DISCOUNT,
		);
	}

	/**
	 * Role definitions keyed by role slug.
	 *
	 * - CRM Owner: full lead management.
	 * - CRM Sales: create and manage their own leads.
	 * - CRM Viewer: read-only access to every lead.
	 *
	 * @return array<string, array{name: string, caps: array<string, bool>}>
	 */
	private static function roles(): array {
		$owner = array_fill_keys( self::all(), true );

		$sales = array(
			self::ACCESS_LEADS => true,
			self::CREATE_LEADS => true,
			self::EDIT_LEADS   => true,
		);

		$viewer = array(
			self::ACCESS_LEADS   => true,
			self::VIEW_ALL_LEADS => true,
		);

		$finance = array(
			self::ACCESS_LEADS    => true,
			self::VIEW_ALL_LEADS  => true,
			self::VERIFY_DEPOSITS => true,
		);

		return array(
			'mbd_crm_owner'   => array(
				'name' => __( 'CRM Owner', 'mbd-crm' ),
				'caps' => array_merge( array( 'read' => true ), $owner ),
			),
			'mbd_crm_sales'   => array(
				'name' => __( 'CRM Sales', 'mbd-crm' ),
				'caps' => array_merge( array( 'read' => true ), $sales ),
			),
			'mbd_crm_viewer'  => array(
				'name' => __( 'CRM Viewer', 'mbd-crm' ),
				'caps' => array_merge( array( 'read' => true ), $viewer ),
			),
			'mbd_crm_finance' => array(
				'name' => __( 'CRM Finance', 'mbd-crm' ),
				'caps' => array_merge( array( 'read' => true ), $finance ),
			),
		);
	}

	/**
	 * Create CRM roles and grant every lead capability to administrators.
	 *
	 * @return void
	 */
	public static function install(): void {
		foreach ( self::roles() as $slug => $def ) {
			remove_role( $slug );
			add_role( $slug, $def['name'], $def['caps'] );
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove CRM roles and revoke lead capabilities from administrators.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		foreach ( array_keys( self::roles() ) as $slug ) {
			remove_role( $slug );
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
	}
}
