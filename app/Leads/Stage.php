<?php
/**
 * Pipeline stage derivation, aging, and staleness.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Derives a lead's current pipeline stage from its own columns and computes
 * stage aging and staleness against per-stage thresholds.
 */
class Stage {

	/**
	 * Derive the current stage key from a lead row.
	 *
	 * Lifecycle (on_hold / archived) overrides the pipeline stage. Otherwise
	 * the most advanced reached stage wins.
	 *
	 * @param object $lead Lead row.
	 * @return string
	 */
	public static function key( object $lead ): string {
		$lifecycle = isset( $lead->lifecycle ) ? (string) $lead->lifecycle : 'active';
		if ( 'on_hold' === $lifecycle || 'archived' === $lifecycle || 'merged' === $lifecycle ) {
			return $lifecycle;
		}

		$closing = isset( $lead->closing_status ) ? (string) $lead->closing_status : '';
		switch ( $closing ) {
			case 'won':
				return 'closing_approved';
			case 'lost':
				return 'closing_failed';
			case 'waiting':
			case 'waiting_approval':
				return 'waiting_approval';
			case 'negotiating':
				return 'negotiating';
			case 'rejected':
				return 'negotiating';
		}

		if ( isset( $lead->planning_approved ) && (int) $lead->planning_approved === 1 ) {
			return 'planning_approved';
		}
		if ( ( isset( $lead->deposit_status ) && 'valid' === $lead->deposit_status )
			|| ( isset( $lead->deposit_override ) && (int) $lead->deposit_override === 1 ) ) {
			return 'deposit_valid';
		}
		if ( isset( $lead->deposit_status ) && in_array( $lead->deposit_status, array( 'pending', 'rejected' ), true ) ) {
			return 'deposit_pending';
		}
		if ( isset( $lead->qualification ) && 'qualified' === $lead->qualification ) {
			return 'qualified';
		}
		if ( isset( $lead->status ) && 'new' !== $lead->status ) {
			return 'contacted';
		}

		return 'new';
	}

	/**
	 * Human label for a stage key.
	 *
	 * @param string $key Stage key.
	 * @return string
	 */
	public static function label( string $key ): string {
		$labels = array(
			'new'               => __( 'New (awaiting response)', 'mbd-crm' ),
			'contacted'         => __( 'Contacted', 'mbd-crm' ),
			'qualified'         => __( 'Qualified', 'mbd-crm' ),
			'deposit_pending'   => __( 'Awaiting deposit', 'mbd-crm' ),
			'deposit_valid'     => __( 'Deposit valid', 'mbd-crm' ),
			'planning_approved' => __( 'Planning approved', 'mbd-crm' ),
			'proposal_sent'     => __( 'Proposal sent', 'mbd-crm' ),
			'negotiating'       => __( 'Negotiating', 'mbd-crm' ),
			'waiting_approval'  => __( 'Awaiting closing approval', 'mbd-crm' ),
			'closing_approved'  => __( 'Closing approved', 'mbd-crm' ),
			'closing_failed'    => __( 'Closing failed', 'mbd-crm' ),
			'on_hold'           => __( 'On hold', 'mbd-crm' ),
			'archived'          => __( 'Archived', 'mbd-crm' ),
			'merged'            => __( 'Merged', 'mbd-crm' ),
		);

		return $labels[ $key ] ?? $key;
	}

	/**
	 * Aging threshold (in hours) per active stage. Terminal/paused stages
	 * have no threshold (not aged).
	 *
	 * @return array<string, float>
	 */
	public static function thresholds(): array {
		$map = array(
			'new'               => 0.25,   // 15 minutes to first response.
			'contacted'         => 24,     // ~1 business day to next action.
			'qualified'         => 24,     // 1 day to schedule discovery.
			'deposit_pending'   => 72,     // 3 days to deposit proof.
			'deposit_valid'     => 24,     // 1 day to start planning.
			'planning_approved' => 48,     // 2 days to send offer.
			'proposal_sent'     => 48,     // 2 days to follow up.
			'negotiating'       => 168,    // 7 days without new negotiation.
			'waiting_approval'  => 24,     // 1 day to approval decision.
		);

		/**
		 * Filter the per-stage aging thresholds (hours).
		 *
		 * @param array<string, float> $map Stage key => hours.
		 */
		return apply_filters( 'mbd_crm_stage_thresholds', $map );
	}

	/**
	 * Whole-hour aging of a lead in its current stage.
	 *
	 * Formula: now − last_stage_changed_at (falls back to created_at).
	 *
	 * @param object $lead Lead row.
	 * @return float Hours.
	 */
	public static function aging_hours( object $lead ): float {
		$basis = ! empty( $lead->last_stage_changed_at ) ? $lead->last_stage_changed_at : ( $lead->created_at ?? null );
		if ( empty( $basis ) ) {
			return 0.0;
		}

		$start = strtotime( $basis );
		$now   = (int) current_time( 'timestamp' );
		if ( false === $start ) {
			return 0.0;
		}

		return max( 0.0, round( ( $now - $start ) / HOUR_IN_SECONDS, 1 ) );
	}

	/**
	 * Friendly aging string (e.g. "3d 4h" or "12m").
	 *
	 * @param object $lead Lead row.
	 * @return string
	 */
	public static function aging_label( object $lead ): string {
		$hours = self::aging_hours( $lead );
		if ( $hours < 1 ) {
			return (int) round( $hours * 60 ) . __( 'm', 'mbd-crm' );
		}
		if ( $hours < 24 ) {
			return (int) round( $hours ) . __( 'h', 'mbd-crm' );
		}

		$days = (int) floor( $hours / 24 );
		$rem  = (int) round( $hours - ( $days * 24 ) );

		return $days . __( 'd', 'mbd-crm' ) . ( $rem > 0 ? ' ' . $rem . __( 'h', 'mbd-crm' ) : '' );
	}

	/**
	 * Evaluate staleness for the lead's current stage.
	 *
	 * @param object $lead Lead row.
	 * @return array{stale: bool, reason: string}
	 */
	public static function staleness( object $lead ): array {
		$stage      = self::key( $lead );
		$thresholds = self::thresholds();

		if ( ! isset( $thresholds[ $stage ] ) ) {
			return array(
				'stale'  => false,
				'reason' => '',
			);
		}

		$hours     = self::aging_hours( $lead );
		$threshold = (float) $thresholds[ $stage ];

		if ( $hours <= $threshold ) {
			return array(
				'stale'  => false,
				'reason' => '',
			);
		}

		$reason = sprintf(
			/* translators: 1: stage label, 2: aging label, 3: threshold hours. */
			__( 'No movement in "%1$s" for %2$s (threshold %3$sh).', 'mbd-crm' ),
			self::label( $stage ),
			self::aging_label( $lead ),
			rtrim( rtrim( (string) $threshold, '0' ), '.' )
		);

		return array(
			'stale'  => true,
			'reason' => $reason,
		);
	}
}
