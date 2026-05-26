<?php
/**
 * Lead scoring and temperature.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Scoring;

use MBD\CRM\FollowUp\FollowUpRepository;
use MBD\CRM\Leads\Sla;
use MBD\CRM\Stakeholders\StakeholderRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Computes a 0-100 fit score from seven weighted components and maps it to
 * a temperature band. The full breakdown is exposed for the detail panel.
 *
 * Components (max 100):
 *  1. Budget fit          (20)
 *  2. Urgency             (15)
 *  3. Location fit        (15)
 *  4. Requirement clarity (15)
 *  5. Decision maker       (15)
 *  6. Responsiveness       (10)
 *  7. Deposit readiness    (10)
 */
class Scorer {

	/**
	 * Service-area keywords that grant full location-fit points.
	 *
	 * @return string[]
	 */
	public static function service_areas(): array {
		/**
		 * Filter the configured service-area keywords (lower-cased substrings
		 * matched against the lead's project location).
		 *
		 * @param string[] $areas Keywords.
		 */
		return array_map( 'strtolower', (array) apply_filters( 'mbd_crm_service_areas', array() ) );
	}

	/**
	 * Compute the score, temperature, and per-component breakdown for a lead.
	 *
	 * @param object $lead Lead row.
	 * @return array{score:int, temperature:string, breakdown:array<int, array{label:string, points:int, max:int}>}
	 */
	public static function evaluate( object $lead ): array {
		$b = array();

		$b[] = self::component( __( 'Budget fit', 'mbd-crm' ), self::budget_points( $lead ), 20 );
		$b[] = self::component( __( 'Urgency', 'mbd-crm' ), self::urgency_points( $lead ), 15 );
		$b[] = self::component( __( 'Location fit', 'mbd-crm' ), self::location_points( $lead ), 15 );
		$b[] = self::component( __( 'Requirement clarity', 'mbd-crm' ), self::clarity_points( $lead ), 15 );
		$b[] = self::component( __( 'Decision maker', 'mbd-crm' ), self::decision_maker_points( $lead ), 15 );
		$b[] = self::component( __( 'Responsiveness', 'mbd-crm' ), self::responsiveness_points( $lead ), 10 );
		$b[] = self::component( __( 'Deposit readiness', 'mbd-crm' ), self::deposit_points( $lead ), 10 );

		$score = 0;
		foreach ( $b as $row ) {
			$score += $row['points'];
		}
		$score = max( 0, min( 100, $score ) );

		return array(
			'score'       => $score,
			'temperature' => self::temperature( $score ),
			'breakdown'   => $b,
		);
	}

	/**
	 * Map a score to a temperature band.
	 *
	 * @param int $score Score 0-100.
	 * @return string
	 */
	public static function temperature( int $score ): string {
		if ( $score >= 80 ) {
			return 'hot';
		}
		if ( $score >= 60 ) {
			return 'warm';
		}
		if ( $score >= 40 ) {
			return 'cold';
		}

		return 'low_fit';
	}

	/**
	 * Human label for a temperature key.
	 *
	 * @param string $temperature Temperature key.
	 * @return string
	 */
	public static function temperature_label( string $temperature ): string {
		$labels = array(
			'hot'     => __( 'Hot lead', 'mbd-crm' ),
			'warm'    => __( 'Warm lead', 'mbd-crm' ),
			'cold'    => __( 'Cold lead', 'mbd-crm' ),
			'low_fit' => __( 'Low fit', 'mbd-crm' ),
		);

		return $labels[ $temperature ] ?? $temperature;
	}

	/**
	 * Chip variant for a temperature.
	 *
	 * @param string $temperature Temperature key.
	 * @return string
	 */
	public static function temperature_variant( string $temperature ): string {
		$map = array(
			'hot'     => 'danger',
			'warm'    => 'warning',
			'cold'    => 'info',
			'low_fit' => 'muted',
		);

		return $map[ $temperature ] ?? 'muted';
	}

	/**
	 * Budget fit: numeric budget (20), addressed-unknown (10), else 0.
	 *
	 * @param object $lead Lead row.
	 * @return int
	 */
	private static function budget_points( object $lead ): int {
		if ( isset( $lead->estimated_budget ) && is_numeric( $lead->estimated_budget ) && (float) $lead->estimated_budget > 0 ) {
			return 20;
		}
		if ( '' !== (string) ( $lead->budget_unknown_reason ?? '' ) ) {
			return 10;
		}

		return 0;
	}

	/**
	 * Urgency: urgent 15, high 10, medium 5, else 0.
	 *
	 * @param object $lead Lead row.
	 * @return int
	 */
	private static function urgency_points( object $lead ): int {
		$map = array(
			'urgent' => 15,
			'high'   => 10,
			'medium' => 5,
			'low'    => 0,
		);

		return $map[ (string) ( $lead->urgency ?? '' ) ] ?? 0;
	}

	/**
	 * Location fit: service area 15, any location 8, none 0.
	 *
	 * @param object $lead Lead row.
	 * @return int
	 */
	private static function location_points( object $lead ): int {
		$location = strtolower( trim( (string) ( $lead->project_location ?? '' ) ) );
		if ( '' === $location ) {
			return 0;
		}

		foreach ( self::service_areas() as $area ) {
			if ( '' !== $area && false !== strpos( $location, $area ) ) {
				return 15;
			}
		}

		return 8;
	}

	/**
	 * Requirement clarity: qualified+project/service set 15, partial 8, else 0.
	 *
	 * @param object $lead Lead row.
	 * @return int
	 */
	private static function clarity_points( object $lead ): int {
		$has_types = '' !== (string) ( $lead->project_type ?? '' ) && '' !== (string) ( $lead->service_type ?? '' );
		if ( 'qualified' === (string) ( $lead->qualification ?? '' ) && $has_types ) {
			return 15;
		}
		if ( $has_types || '' !== (string) ( $lead->project_type ?? '' ) ) {
			return 8;
		}

		return 0;
	}

	/**
	 * Decision maker: primary identified 15, any stakeholder 7, none 0.
	 *
	 * @param object $lead Lead row.
	 * @return int
	 */
	private static function decision_maker_points( object $lead ): int {
		$repo = new StakeholderRepository();
		if ( $repo->has_decision_maker( (int) $lead->id ) ) {
			return 15;
		}

		return count( $repo->for_lead( (int) $lead->id ) ) > 0 ? 7 : 0;
	}

	/**
	 * Responsiveness: within SLA 10, late 6, very late 2, no response 0.
	 *
	 * @param object $lead Lead row.
	 * @return int
	 */
	private static function responsiveness_points( object $lead ): int {
		$first = ( new FollowUpRepository() )->first_contact_map()[ (int) $lead->id ] ?? null;
		if ( empty( $first ) ) {
			return 0;
		}
		if ( ! empty( $lead->sla_due_at ) && $first <= $lead->sla_due_at ) {
			return 10;
		}

		$created = (string) ( $lead->created_at ?? '' );
		$hours   = ( '' !== $created ) ? ( strtotime( $first ) - strtotime( $created ) ) / HOUR_IN_SECONDS : 0;
		$window  = Sla::hours();

		return $hours <= ( $window * 4 ) ? 6 : 2;
	}

	/**
	 * Deposit readiness: valid 10, pending 5, else 0.
	 *
	 * @param object $lead Lead row.
	 * @return int
	 */
	private static function deposit_points( object $lead ): int {
		$status = (string) ( $lead->deposit_status ?? '' );
		if ( 'valid' === $status ) {
			return 10;
		}

		return 'pending' === $status ? 5 : 0;
	}

	/**
	 * Build a breakdown component row.
	 *
	 * @param string $label  Component label.
	 * @param int    $points Earned points.
	 * @param int    $max    Maximum points.
	 * @return array{label:string, points:int, max:int}
	 */
	private static function component( string $label, int $points, int $max ): array {
		return array(
			'label'  => $label,
			'points' => $points,
			'max'    => $max,
		);
	}
}
