<?php
/**
 * Deposit automation: audit trail for deposit events.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Deposit;

use MBD\CRM\Leads\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Audits deposit upload, verification, rejection, and override events.
 */
class Automation {

	/**
	 * Hook automation onto deposit events.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'mbd_crm_deposit_changed', array( $this, 'on_change' ), 10, 5 );
	}

	/**
	 * Audit a deposit event.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead    Lead row.
	 * @param object $deposit Deposit row.
	 * @param string $event   Event key (requested|proof_uploaded|verified|rejected|override).
	 * @param string $reason  Reason (for rejection / override).
	 * @return void
	 */
	public function on_change( int $lead_id, object $lead, object $deposit, string $event, string $reason ): void {
		unset( $lead, $deposit );

		Audit::record(
			$lead_id,
			Audit::DEPOSIT,
			array(
				'event'  => $event,
				'reason' => $reason,
			)
		);
	}
}
