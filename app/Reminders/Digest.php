<?php
/**
 * Per-user actionable-item digest.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Reminders;

use MBD\CRM\Closing\ClosingRepository;
use MBD\CRM\Deposit\DepositRepository;
use MBD\CRM\Leads\LeadRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the set of items that need a specific user's attention, scoped to the
 * leads they own or are assigned. Used both by the scheduled email reminder
 * and (potentially) any per-user summary. Computed without relying on the
 * current-user context, so it is safe to call from WP-Cron.
 */
class Digest {

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->leads = new LeadRepository();
	}

	/**
	 * Build the digest categories for a user (empty categories omitted).
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array{key:string, label:string, variant:string, leads:array<int, object>}>
	 */
	public function for_user( int $user_id ): array {
		$args  = array( 'scope' => 'own', 'user_id' => $user_id );
		$leads = $this->leads->all( $args );
		$now   = current_time( 'mysql' );
		$today = current_time( 'Y-m-d' );

		$sla   = array();
		$stale = array();
		foreach ( $leads as $lead ) {
			if ( 'new' === $lead->status && ! empty( $lead->sla_due_at ) && $lead->sla_due_at < $now ) {
				$sla[] = $lead;
			}
			if ( (int) ( $lead->stale_flag ?? 0 ) === 1 ) {
				$stale[] = $lead;
			}
		}

		$overdue           = $this->leads->overdue_followups( $today, $args );
		$pending_approval  = $this->pending_approval( $leads );

		$categories = array(
			array( 'key' => 'sla_breach', 'label' => __( 'Response SLA breached', 'mbd-crm' ), 'variant' => 'danger', 'leads' => $sla ),
			array( 'key' => 'overdue_followup', 'label' => __( 'Overdue follow-ups', 'mbd-crm' ), 'variant' => 'danger', 'leads' => $overdue ),
			array( 'key' => 'stale', 'label' => __( 'Stale leads', 'mbd-crm' ), 'variant' => 'warning', 'leads' => $stale ),
			array( 'key' => 'pending_approval', 'label' => __( 'Awaiting your action', 'mbd-crm' ), 'variant' => 'info', 'leads' => $pending_approval ),
		);

		return array_values(
			array_filter(
				$categories,
				static function ( $cat ) {
					return ! empty( $cat['leads'] );
				}
			)
		);
	}

	/**
	 * Total actionable items for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function count_for_user( int $user_id ): int {
		$total = 0;
		foreach ( $this->for_user( $user_id ) as $cat ) {
			$total += count( $cat['leads'] );
		}

		return $total;
	}

	/**
	 * Closings awaiting approval / deposits awaiting verification among the
	 * given leads.
	 *
	 * @param array<int, object> $leads User's leads.
	 * @return array<int, object>
	 */
	private function pending_approval( array $leads ): array {
		$ids = array();
		foreach ( $leads as $lead ) {
			$ids[ (int) $lead->id ] = $lead;
		}

		$out = array();

		foreach ( ( new ClosingRepository() )->by_status( 'waiting_approval' ) as $closing ) {
			if ( isset( $ids[ (int) $closing->lead_id ] ) ) {
				$out[ (int) $closing->lead_id ] = $ids[ (int) $closing->lead_id ];
			}
		}
		foreach ( ( new DepositRepository() )->by_status( 'pending' ) as $deposit ) {
			if ( isset( $ids[ (int) $deposit->lead_id ] ) && (int) ( $deposit->proof_id ?? 0 ) > 0 ) {
				$out[ (int) $deposit->lead_id ] = $ids[ (int) $deposit->lead_id ];
			}
		}

		return array_values( $out );
	}
}
