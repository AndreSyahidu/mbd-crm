<?php
/**
 * Vocabularies for follow-up channels and promise statuses.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\FollowUp;

defined( 'ABSPATH' ) || exit;

/**
 * Channel and promise-status option lists, plus their chip variants.
 */
class Options {

	/**
	 * Follow-up channels.
	 *
	 * @return array<string, string>
	 */
	public static function channels(): array {
		return array(
			'whatsapp' => __( 'WhatsApp', 'mbd-crm' ),
			'call'     => __( 'Call', 'mbd-crm' ),
			'meeting'  => __( 'Meeting', 'mbd-crm' ),
			'email'    => __( 'Email', 'mbd-crm' ),
			'other'    => __( 'Other', 'mbd-crm' ),
		);
	}

	/**
	 * Promise statuses.
	 *
	 * @return array<string, string>
	 */
	public static function promise_statuses(): array {
		return array(
			'open'      => __( 'Open', 'mbd-crm' ),
			'fulfilled' => __( 'Fulfilled', 'mbd-crm' ),
			'broken'    => __( 'Broken', 'mbd-crm' ),
			'cancelled' => __( 'Cancelled', 'mbd-crm' ),
		);
	}

	/**
	 * Whether a channel key is valid.
	 *
	 * @param string $key Channel key.
	 * @return bool
	 */
	public static function is_channel( string $key ): bool {
		return isset( self::channels()[ $key ] );
	}

	/**
	 * Whether a promise status key is valid.
	 *
	 * @param string $key Status key.
	 * @return bool
	 */
	public static function is_promise_status( string $key ): bool {
		return isset( self::promise_statuses()[ $key ] );
	}

	/**
	 * Label for a channel key.
	 *
	 * @param string $key Channel key.
	 * @return string
	 */
	public static function channel_label( string $key ): string {
		return self::channels()[ $key ] ?? $key;
	}

	/**
	 * Label for a promise status key.
	 *
	 * @param string $key Status key.
	 * @return string
	 */
	public static function promise_label( string $key ): string {
		return self::promise_statuses()[ $key ] ?? $key;
	}

	/**
	 * Chip variant for a channel.
	 *
	 * @param string $channel Channel key.
	 * @return string
	 */
	public static function channel_variant( string $channel ): string {
		$map = array(
			'whatsapp' => 'success',
			'call'     => 'info',
			'meeting'  => 'warning',
			'email'    => 'default',
			'other'    => 'muted',
		);

		return $map[ $channel ] ?? 'default';
	}

	/**
	 * Chip variant for a promise status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function promise_variant( string $status ): string {
		$map = array(
			'open'      => 'info',
			'fulfilled' => 'success',
			'broken'    => 'danger',
			'cancelled' => 'muted',
		);

		return $map[ $status ] ?? 'default';
	}
}
