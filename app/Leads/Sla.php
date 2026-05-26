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
			$start = time();
		}

		return gmdate( 'Y-m-d H:i:s', $start + ( self::hours() * HOUR_IN_SECONDS ) );
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

		$due = ! empty( $lead->sla_due_at ) ? strtotime( $lead->sla_due_at ) : false;

		if ( $due && current_time( 'timestamp' ) > $due ) {
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
