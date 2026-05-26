<?php
/**
 * Qualification automation.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Qualification;

use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Reacts to qualification decisions: schedules discovery after a lead is
 * qualified and audits every decision.
 */
class Automation {

	/**
	 * Hook automation onto qualification actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'mbd_crm_lead_qualified', array( $this, 'on_qualified' ), 10, 3 );
		add_action( 'mbd_crm_lead_disqualified', array( $this, 'on_disqualified' ), 10, 3 );
	}

	/**
	 * After a lead is qualified, open a discovery-scheduling task and audit it.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead    Lead row.
	 * @param int    $score   Fit score.
	 * @return void
	 */
	public function on_qualified( int $lead_id, object $lead, int $score ): void {
		$assignee = (int) ( $lead->assigned_to ?? 0 );
		if ( ! $assignee ) {
			$assignee = (int) ( $lead->created_by ?? 0 );
		}

		Tasks::create_task(
			$lead_id,
			$assignee,
			sprintf(
				/* translators: %s: lead name. */
				__( 'Schedule discovery: %s', 'mbd-crm' ),
				(string) ( $lead->name ?? '' )
			)
		);

		Audit::record( $lead_id, Audit::QUALIFIED, array( 'score' => $score ) );
	}

	/**
	 * Audit a not-qualified decision.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead    Lead row.
	 * @param string $reason  Reason given.
	 * @return void
	 */
	public function on_disqualified( int $lead_id, object $lead, string $reason ): void {
		unset( $lead );

		Audit::record( $lead_id, Audit::DISQUALIFIED, array( 'reason' => $reason ) );
	}
}
