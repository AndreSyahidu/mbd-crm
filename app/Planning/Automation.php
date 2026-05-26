<?php
/**
 * Planning automation.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Planning;

use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the planner task on planning creation and audits planning,
 * deliverable, and revision activity.
 */
class Automation {

	/**
	 * Hook automation onto planning actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'mbd_crm_planning_created', array( $this, 'on_created' ), 10, 3 );
		add_action( 'mbd_crm_planning_event', array( $this, 'on_event' ), 10, 2 );
	}

	/**
	 * Open the planner task and audit creation.
	 *
	 * @param int    $lead_id  Lead ID.
	 * @param object $lead     Lead row.
	 * @param object $planning Planning row.
	 * @return void
	 */
	public function on_created( int $lead_id, object $lead, object $planning ): void {
		$planner = (int) ( $planning->planner_id ?? 0 );
		if ( ! $planner ) {
			$planner = (int) ( $lead->assigned_to ?? 0 );
		}

		Tasks::create_task(
			$lead_id,
			$planner,
			sprintf(
				/* translators: %s: lead name. */
				__( 'Prepare planning: %s', 'mbd-crm' ),
				(string) ( $lead->name ?? '' )
			),
			$planning->sla_due ?? null
		);

		Audit::record( $lead_id, Audit::PLANNING, array( 'event' => 'created' ) );
	}

	/**
	 * Audit a planning event.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $event   Event description.
	 * @return void
	 */
	public function on_event( int $lead_id, string $event ): void {
		Audit::record( $lead_id, Audit::PLANNING, array( 'event' => $event ) );
	}
}
