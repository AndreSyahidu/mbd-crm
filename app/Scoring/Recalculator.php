<?php
/**
 * Recomputes lead scores when relevant data changes.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Scoring;

use MBD\CRM\Leads\LeadRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Listens to lifecycle/activity actions and recalculates the lead score,
 * recording history when it changes. Manual overrides (score_locked) are
 * respected: system recalculation skips locked leads.
 */
class Recalculator {

	/**
	 * Score persistence.
	 *
	 * @var ScoreRepository
	 */
	private ScoreRepository $repo;

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
		$this->repo  = new ScoreRepository();
		$this->leads = new LeadRepository();
	}

	/**
	 * Hook recalculation onto the actions that change scoring inputs.
	 *
	 * @return void
	 */
	public function register(): void {
		$lead_id_only = array(
			'mbd_crm_lead_created',
			'mbd_crm_lead_updated',
			'mbd_crm_lead_qualified',
			'mbd_crm_lead_disqualified',
			'mbd_crm_followup_logged',
			'mbd_crm_deposit_changed',
			'mbd_crm_lead_rescore',
		);
		foreach ( $lead_id_only as $hook ) {
			add_action( $hook, array( $this, 'on_event' ), 20, 1 );
		}
	}

	/**
	 * Action handler: the first argument is always the lead ID.
	 *
	 * @param int $lead_id Lead ID.
	 * @return void
	 */
	public function on_event( $lead_id ): void {
		$this->recalc( (int) $lead_id, 'system', '', 0 );
	}

	/**
	 * Recalculate and persist a lead's score (history on change).
	 *
	 * @param int    $lead_id       Lead ID.
	 * @param string $calculated_by 'system' or 'user'.
	 * @param string $reason        Reason (for user overrides).
	 * @param int    $created_by    Acting user ID.
	 * @return void
	 */
	public function recalc( int $lead_id, string $calculated_by, string $reason, int $created_by ): void {
		$lead = $this->leads->find( $lead_id );
		if ( ! $lead ) {
			return;
		}

		// Respect a manual override on system recalculation.
		if ( 'system' === $calculated_by && (int) ( $lead->score_locked ?? 0 ) === 1 ) {
			return;
		}

		$eval     = Scorer::evaluate( $lead );
		$old      = (int) ( $lead->score ?? 0 );
		$old_temp = (string) ( $lead->temperature ?? '' );

		if ( $eval['score'] === $old && $eval['temperature'] === $old_temp ) {
			return;
		}

		$this->repo->persist( $lead_id, $eval['score'], $eval['temperature'], 0, '' );
		$this->repo->record( $lead_id, $old, $eval['score'], $old_temp, $eval['temperature'], $reason, $calculated_by, $created_by );
	}
}
