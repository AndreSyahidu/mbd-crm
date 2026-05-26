<?php
/**
 * Client approval automation.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Approval;

use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * On client approval, opens the closing task and audits the evidence.
 * (The planning approval-wait is stopped by the controller when it marks
 * the planning approved.)
 */
class Automation {

	/**
	 * Hook automation onto the approval action.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'mbd_crm_planning_approved', array( $this, 'on_approved' ), 10, 2 );
	}

	/**
	 * Create the closing task and audit the approval.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead    Lead row.
	 * @return void
	 */
	public function on_approved( int $lead_id, object $lead ): void {
		$assignee = (int) ( $lead->assigned_to ?? 0 );
		if ( ! $assignee ) {
			$assignee = (int) ( $lead->created_by ?? 0 );
		}

		Tasks::create_task(
			$lead_id,
			$assignee,
			sprintf(
				/* translators: %s: lead name. */
				__( 'Prepare closing: %s', 'mbd-crm' ),
				(string) ( $lead->name ?? '' )
			)
		);

		Audit::record( $lead_id, Audit::APPROVAL, array() );
	}
}
