<?php
/**
 * CRM screen registry and capability resolution.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the CRM screens, their navigation metadata,
 * and the capability each one requires.
 */
class Screens {

	/**
	 * Slug of the screen shown when none is requested.
	 */
	public const DEFAULT_SLUG = 'dashboard';

	/**
	 * Return every registered screen keyed by slug.
	 *
	 * Each entry: [ 'label' => string, 'icon' => string, 'cap' => string ].
	 *
	 * @return array<string, array{label: string, icon: string, cap: string}>
	 */
	public static function all(): array {
		$screens = array(
			'dashboard'     => array(
				'label' => __( 'Dashboard', 'mbd-crm' ),
				'icon'  => 'dashicons-dashboard',
				'cap'   => 'read',
			),
			'leads'         => array(
				'label' => __( 'Leads', 'mbd-crm' ),
				'icon'  => 'dashicons-groups',
				'cap'   => 'read',
			),
			'follow-ups'    => array(
				'label' => __( 'Follow-Ups', 'mbd-crm' ),
				'icon'  => 'dashicons-phone',
				'cap'   => 'read',
			),
			'discovery'     => array(
				'label' => __( 'Discovery', 'mbd-crm' ),
				'icon'  => 'dashicons-search',
				'cap'   => 'read',
			),
			'deposits'      => array(
				'label' => __( 'Deposits', 'mbd-crm' ),
				'icon'  => 'dashicons-money-alt',
				'cap'   => 'read',
			),
			'planning'      => array(
				'label' => __( 'Planning', 'mbd-crm' ),
				'icon'  => 'dashicons-calendar-alt',
				'cap'   => 'read',
			),
			'closing'       => array(
				'label' => __( 'Closing', 'mbd-crm' ),
				'icon'  => 'dashicons-yes-alt',
				'cap'   => 'read',
			),
			'my-work'       => array(
				'label' => __( 'My Work', 'mbd-crm' ),
				'icon'  => 'dashicons-clipboard',
				'cap'   => 'read',
			),
			'notifications' => array(
				'label' => __( 'Notifications', 'mbd-crm' ),
				'icon'  => 'dashicons-bell',
				'cap'   => 'read',
			),
			'approvals'     => array(
				'label' => __( 'Approvals', 'mbd-crm' ),
				'icon'  => 'dashicons-thumbs-up',
				'cap'   => 'edit_others_posts',
			),
			'audit-log'     => array(
				'label' => __( 'Audit Log', 'mbd-crm' ),
				'icon'  => 'dashicons-list-view',
				'cap'   => 'manage_options',
			),
		);

		/**
		 * Filter the registered CRM screens.
		 *
		 * @param array<string, array{label: string, icon: string, cap: string}> $screens Screen definitions.
		 */
		return apply_filters( 'mbd_crm_screens', $screens );
	}

	/**
	 * Fetch a single screen's metadata.
	 *
	 * @param string $slug Screen slug.
	 * @return array{slug: string, label: string, icon: string, cap: string}|null
	 */
	public static function get( string $slug ): ?array {
		$screens = self::all();

		if ( ! isset( $screens[ $slug ] ) ) {
			return null;
		}

		return array_merge( array( 'slug' => $slug ), $screens[ $slug ] );
	}

	/**
	 * Resolve the capability required to view a screen.
	 *
	 * Unknown screens resolve to "do_not_allow" so they fail closed.
	 *
	 * @param string $slug Screen slug.
	 * @return string
	 */
	public static function capability( string $slug ): string {
		$screens    = self::all();
		$capability = $screens[ $slug ]['cap'] ?? 'do_not_allow';

		/**
		 * Filter the capability required for a given screen.
		 *
		 * @param string $capability Capability name.
		 * @param string $slug       Screen slug.
		 */
		return (string) apply_filters( 'mbd_crm_screen_capability', $capability, $slug );
	}

	/**
	 * Whether the current user may view a screen.
	 *
	 * @param string $slug Screen slug.
	 * @return bool
	 */
	public static function user_can( string $slug ): bool {
		return current_user_can( self::capability( $slug ) );
	}

	/**
	 * Screens the current user is allowed to see, preserving order.
	 *
	 * @return array<string, array{label: string, icon: string, cap: string}>
	 */
	public static function visible(): array {
		$visible = array();

		foreach ( self::all() as $slug => $meta ) {
			if ( current_user_can( self::capability( $slug ) ) ) {
				$visible[ $slug ] = $meta;
			}
		}

		return $visible;
	}
}
