<?php
/**
 * Discovery types and status presentation.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Discovery;

defined( 'ABSPATH' ) || exit;

/**
 * Discovery type and status vocabularies plus chip variants.
 */
class Options {

	/**
	 * Discovery types.
	 *
	 * @return array<string, string>
	 */
	public static function types(): array {
		return array(
			'call'        => __( 'Call Discovery', 'mbd-crm' ),
			'online'      => __( 'Online Meeting', 'mbd-crm' ),
			'offline'     => __( 'Offline Meeting', 'mbd-crm' ),
			'site_survey' => __( 'Site Survey', 'mbd-crm' ),
		);
	}

	/**
	 * Discovery statuses.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			'scheduled' => __( 'Scheduled', 'mbd-crm' ),
			'completed' => __( 'Completed', 'mbd-crm' ),
			'cancelled' => __( 'Cancelled', 'mbd-crm' ),
		);
	}

	/**
	 * Whether a type key is valid.
	 *
	 * @param string $key Type key.
	 * @return bool
	 */
	public static function is_type( string $key ): bool {
		return isset( self::types()[ $key ] );
	}

	/**
	 * Label for a type key.
	 *
	 * @param string $key Type key.
	 * @return string
	 */
	public static function type_label( string $key ): string {
		return self::types()[ $key ] ?? $key;
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
	 * Chip variant for a status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_variant( string $status ): string {
		$map = array(
			'scheduled' => 'info',
			'completed' => 'success',
			'cancelled' => 'muted',
		);

		return $map[ $status ] ?? 'default';
	}
}
