<?php
/**
 * Lead-response SLA helper.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Computes the response-SLA window and derives a display status from a
 * lead's progress.
 */
class Sla {

	/**
	 * Default number of hours sales has to make first contact.
	 */
	private const DEFAULT_HOURS = 4;

	/**
	 * SLA window length, in hours.
	 *
	 * @return int
	 */
	public static function hours(): int {
		/**
		 * Filter the lead-response SLA window.
		 *
		 * @param int $hours Hours allowed for first response.
		 */
		return (int) apply_filters( 'mbd_crm_sla_hours', self::DEFAULT_HOURS );
	}

	/**
	 * Compute the SLA due timestamp from a starting MySQL datetime.
	 *
	 * @param string $from_mysql Starting datetime (Y-m-d H:i:s).
	 * @return string Due datetime in the same format.
	 */
	public static function compute_due( string $from_mysql ): string {
		$start = strtotime( $from_mysql );
		if ( false === $start ) {
			$start = (int) current_time( 'timestamp' );
		}

		// Use date() (not gmdate()) so the stored due time shares the same
		// site-local basis as current_time('mysql'), which every SLA
		// comparison uses. This avoids a UTC-vs-local skew.
		return date( 'Y-m-d H:i:s', $start + ( self::hours() * HOUR_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
	}

	/**
	 * Derive a human SLA status and chip variant for a lead.
	 *
	 * A lead that has moved beyond "new" counts as responded (met);
	 * otherwise it is running until the due time passes, then breached.
	 *
	 * @param object $lead Lead row.
	 * @return array{label: string, variant: string}
	 */
	public static function display( object $lead ): array {
		if ( ! empty( $lead->status ) && 'new' !== $lead->status ) {
			return array(
				'label'   => __( 'Met', 'mbd-crm' ),
				'variant' => 'success',
			);
		}

		// Compare site-local datetime strings (same basis as compute_due()).
		if ( ! empty( $lead->sla_due_at ) && current_time( 'mysql' ) > $lead->sla_due_at ) {
			return array(
				'label'   => __( 'Breached', 'mbd-crm' ),
				'variant' => 'danger',
			);
		}

		return array(
			'label'   => __( 'Running', 'mbd-crm' ),
			'variant' => 'info',
		);
	}
}
