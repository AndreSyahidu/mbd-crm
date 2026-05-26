<?php
/**
 * Closing automation.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Closing;

use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Creates approval-request and outcome tasks and audits closing value and
 * status changes.
 */
class Automation {

	/**
	 * Hook automation onto closing events.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'mbd_crm_closing_changed', array( $this, 'on_change' ), 10, 5 );
	}

	/**
	 * React to a closing event.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead    Lead row.
	 * @param object $closing Closing row.
	 * @param string $event   Event key.
	 * @param string $reason  Reason (for lost).
	 * @return void
	 */
	public function on_change( int $lead_id, object $lead, object $closing, string $event, string $reason ): void {
		unset( $closing );

		Audit::record(
			$lead_id,
			Audit::CLOSING,
			array(
				'event'  => $event,
				'reason' => $reason,
			)
		);

		$assignee = (int) ( $lead->assigned_to ?? 0 );
		if ( ! $assignee ) {
			$assignee = (int) ( $lead->created_by ?? 0 );
		}
		$name = (string) ( $lead->name ?? '' );

		if ( 'approval_requested' === $event ) {
			Tasks::create_task(
				$lead_id,
				$assignee,
				/* translators: %s: lead name. */
				sprintf( __( 'Closing approval requested: %s', 'mbd-crm' ), $name )
			);
		} elseif ( 'approved' === $event ) {
			Tasks::create_task(
				$lead_id,
				$assignee,
				/* translators: %s: lead name. */
				sprintf( __( 'Closing approved — won: %s', 'mbd-crm' ), $name )
			);
		} elseif ( 'rejected' === $event ) {
			Tasks::create_task(
				$lead_id,
				$assignee,
				/* translators: %s: lead name. */
				sprintf( __( 'Closing rejected — renegotiate: %s', 'mbd-crm' ), $name )
			);
		}
	}
}
