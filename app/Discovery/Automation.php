<?php
/**
 * Discovery automation.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Discovery;

use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Audits discovery status changes and, on completion, opens the deposit
 * follow-up task and starts the deposit SLA.
 */
class Automation {

	/**
	 * Default deposit-collection SLA window, in days.
	 */
	private const DEPOSIT_SLA_DAYS = 3;

	/**
	 * Hook automation onto discovery events.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'mbd_crm_discovery_changed', array( $this, 'on_change' ), 10, 4 );
	}

	/**
	 * React to a discovery status change.
	 *
	 * @param int    $lead_id   Lead ID.
	 * @param object $lead      Lead row.
	 * @param object $discovery Discovery row.
	 * @param string $event     Event key (scheduled|rescheduled|completed|cancelled).
	 * @return void
	 */
	public function on_change( int $lead_id, object $lead, object $discovery, string $event ): void {
		Audit::record(
			$lead_id,
			Audit::DISCOVERY,
			array(
				'event' => $event,
				'type'  => Options::type_label( (string) $discovery->type ),
			)
		);

		if ( 'completed' !== $event ) {
			return;
		}

		$assignee = (int) ( $lead->assigned_to ?? 0 );
		if ( ! $assignee ) {
			$assignee = (int) ( $lead->created_by ?? 0 );
		}

		$due = $this->deposit_due();

		// Start the deposit SLA and open the deposit follow-up task.
		( new LeadRepository() )->set_deposit_sla( $lead_id, $due );

		Tasks::create_task(
			$lead_id,
			$assignee,
			sprintf(
				/* translators: %s: lead name. */
				__( 'Collect deposit: %s', 'mbd-crm' ),
				(string) ( $lead->name ?? '' )
			),
			$due
		);
	}

	/**
	 * Compute the deposit SLA due datetime.
	 *
	 * @return string
	 */
	private function deposit_due(): string {
		/**
		 * Filter the deposit-collection SLA window, in days.
		 *
		 * @param int $days Default window.
		 */
		$days = (int) apply_filters( 'mbd_crm_deposit_sla_days', self::DEPOSIT_SLA_DAYS );

		return gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
	}
}
