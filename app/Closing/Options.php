<?php
/**
 * Closing statuses and forecast helpers.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Closing;

defined( 'ABSPATH' ) || exit;

/**
 * Negotiation/closing status vocabulary, chip variants, and the weighted
 * forecast calculation.
 */
class Options {

	/**
	 * Closing statuses.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			'open'             => __( 'Open', 'mbd-crm' ),
			'negotiating'      => __( 'Negotiating', 'mbd-crm' ),
			'waiting_approval' => __( 'Waiting approval', 'mbd-crm' ),
			'approved'         => __( 'Approved (won)', 'mbd-crm' ),
			'rejected'         => __( 'Approval rejected', 'mbd-crm' ),
			'lost'             => __( 'Lost', 'mbd-crm' ),
		);
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
			'open'             => 'muted',
			'negotiating'      => 'info',
			'waiting_approval' => 'warning',
			'approved'         => 'success',
			'rejected'         => 'danger',
			'lost'             => 'danger',
		);

		return $map[ $status ] ?? 'default';
	}

	/**
	 * Weighted forecast value: offered (or estimated) value × probability.
	 *
	 * @param object $closing Closing row.
	 * @return float
	 */
	public static function weighted_forecast( object $closing ): float {
		$value = null !== $closing->offered_value && '' !== (string) $closing->offered_value
			? (float) $closing->offered_value
			: (float) ( $closing->estimated_value ?? 0 );

		$probability = (int) ( $closing->probability ?? 0 );

		return round( $value * ( $probability / 100 ), 2 );
	}
}
