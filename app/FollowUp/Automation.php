<?php
/**
 * Follow-up and promise automation.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\FollowUp;

use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the next follow-up task and audits follow-up / promise activity.
 */
class Automation {

	/**
	 * Hook automation onto follow-up and promise actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'mbd_crm_followup_logged', array( $this, 'on_followup' ), 10, 3 );
		add_action( 'mbd_crm_promise_created', array( $this, 'on_promise_created' ), 10, 3 );
		add_action( 'mbd_crm_promise_status_changed', array( $this, 'on_promise_status' ), 10, 4 );
	}

	/**
	 * After a follow-up is logged, open the next task and audit it.
	 *
	 * @param int    $lead_id  Lead ID.
	 * @param object $lead     Lead row.
	 * @param object $followup Follow-up row.
	 * @return void
	 */
	public function on_followup( int $lead_id, object $lead, object $followup ): void {
		$has_next = '' !== (string) ( $followup->next_action ?? '' )
			|| ! empty( $followup->next_follow_up );

		if ( $has_next ) {
			$assignee = (int) ( $lead->assigned_to ?? 0 );
			if ( ! $assignee ) {
				$assignee = (int) ( $lead->created_by ?? 0 );
			}

			$title = '' !== (string) ( $followup->next_action ?? '' )
				? (string) $followup->next_action
				: sprintf(
					/* translators: %s: lead name. */
					__( 'Follow up: %s', 'mbd-crm' ),
					(string) ( $lead->name ?? '' )
				);

			Tasks::create_task( $lead_id, $assignee, $title, $followup->next_follow_up ?? null );
		}

		Audit::record(
			$lead_id,
			Audit::FOLLOWUP,
			array( 'channel' => Options::channel_label( (string) ( $followup->channel ?? 'other' ) ) )
		);
	}

	/**
	 * Audit a new promise.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead    Lead row.
	 * @param object $promise Promise row.
	 * @return void
	 */
	public function on_promise_created( int $lead_id, object $lead, object $promise ): void {
		unset( $lead );

		Audit::record(
			$lead_id,
			Audit::PROMISE_MADE,
			array(
				'description' => (string) ( $promise->description ?? '' ),
				'due'         => (string) ( $promise->due_date ?? '' ),
			)
		);
	}

	/**
	 * Audit a promise status change.
	 *
	 * @param int    $lead_id    Lead ID.
	 * @param int    $promise_id Promise ID.
	 * @param string $from       Previous status.
	 * @param string $to         New status.
	 * @return void
	 */
	public function on_promise_status( int $lead_id, int $promise_id, string $from, string $to ): void {
		Audit::record(
			$lead_id,
			Audit::PROMISE_STATUS,
			array(
				'promise_id' => $promise_id,
				'from'       => Options::promise_label( $from ),
				'to'         => Options::promise_label( $to ),
			)
		);
	}
}
