<?php
/**
 * Centralised KPI formulas.
 *
 * Every dashboard and export calculation routes through this class so the
 * numbers stay consistent. Each public method documents its exact formula.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Pure (stateless) formula helpers: opportunity value, probability, weighted
 * value, currency/percent formatting, and the pipeline stage mapping.
 */
class Formulas {

	/**
	 * Default probability (%) by pipeline stage, used when an opportunity has
	 * no explicit probability.
	 *
	 * @return array<string, int>
	 */
	public static function default_probabilities(): array {
		$map = array(
			'new'                       => 10,
			'qualified'                 => 15,
			'discovery_selesai'         => 25,
			'deposit_valid'             => 40,
			'planning_berjalan'         => 50,
			'planning_approved'         => 60,
			'penawaran_dikirim'         => 70,
			'negosiasi'                 => 80,
			'menunggu_approval_closing' => 90,
			'closing_approved'          => 100,
			'closing_failed'            => 0,
		);

		/**
		 * Filter the default per-stage probabilities.
		 *
		 * @param array<string, int> $map Stage key => probability percent.
		 */
		return apply_filters( 'mbd_crm_stage_probabilities', $map );
	}

	/**
	 * Resolve the current pipeline stage key for a lead (+ its closing row).
	 *
	 * Derived from the closing status first, then the lead's stage flags.
	 *
	 * @param object      $lead    Lead row.
	 * @param object|null $closing Closing row, if any.
	 * @return string
	 */
	public static function stage_key( object $lead, ?object $closing = null ): string {
		if ( $closing ) {
			switch ( (string) $closing->status ) {
				case 'approved':
					return 'closing_approved';
				case 'lost':
					return 'closing_failed';
				case 'waiting_approval':
					return 'menunggu_approval_closing';
				case 'negotiating':
					return 'negosiasi';
			}
			if ( ! empty( $closing->proposal_sent ) ) {
				return 'penawaran_dikirim';
			}
		}

		if ( isset( $lead->planning_approved ) && (int) $lead->planning_approved === 1 ) {
			return 'planning_approved';
		}
		if ( ( isset( $lead->deposit_status ) && 'valid' === $lead->deposit_status )
			|| ( isset( $lead->deposit_override ) && (int) $lead->deposit_override === 1 ) ) {
			return 'deposit_valid';
		}
		if ( isset( $lead->qualification ) && 'qualified' === $lead->qualification ) {
			return 'qualified';
		}

		return 'new';
	}

	/**
	 * Best available opportunity value for a lead.
	 *
	 * Precedence: closing.final_value → closing.offered_value →
	 * closing.estimated_value → lead.estimated_budget (when numeric).
	 *
	 * @param object      $lead    Lead row.
	 * @param object|null $closing Closing row, if any.
	 * @return float
	 */
	public static function opportunity_value( object $lead, ?object $closing = null ): float {
		if ( $closing ) {
			foreach ( array( 'final_value', 'offered_value', 'estimated_value' ) as $field ) {
				if ( isset( $closing->$field ) && null !== $closing->$field && '' !== (string) $closing->$field ) {
					return (float) $closing->$field;
				}
			}
		}

		if ( isset( $lead->estimated_budget ) && is_numeric( $lead->estimated_budget ) ) {
			return (float) $lead->estimated_budget;
		}

		return 0.0;
	}

	/**
	 * Probability (%) for an opportunity.
	 *
	 * Uses the closing's explicit probability when > 0, otherwise the default
	 * for the opportunity's stage.
	 *
	 * @param object      $lead    Lead row.
	 * @param object|null $closing Closing row, if any.
	 * @return int
	 */
	public static function probability( object $lead, ?object $closing = null ): int {
		if ( $closing && isset( $closing->probability ) && (int) $closing->probability > 0 ) {
			return (int) $closing->probability;
		}

		$defaults = self::default_probabilities();
		$stage    = self::stage_key( $lead, $closing );

		return (int) ( $defaults[ $stage ] ?? 0 );
	}

	/**
	 * Weighted value = opportunity value × probability / 100.
	 *
	 * @param object      $lead    Lead row.
	 * @param object|null $closing Closing row, if any.
	 * @return float
	 */
	public static function weighted_value( object $lead, ?object $closing = null ): float {
		return round( self::opportunity_value( $lead, $closing ) * ( self::probability( $lead, $closing ) / 100 ), 2 );
	}

	/**
	 * Safe percentage = den > 0 ? round(num / den × 100) : 0.
	 *
	 * @param int|float $numerator   Numerator.
	 * @param int|float $denominator Denominator.
	 * @return int
	 */
	public static function percent( $numerator, $denominator ): int {
		$denominator = (float) $denominator;

		if ( $denominator <= 0 ) {
			return 0;
		}

		return (int) round( ( (float) $numerator / $denominator ) * 100 );
	}

	/**
	 * Format a value as IDR (e.g. "Rp 1.250.000").
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function idr( float $amount ): string {
		return 'Rp ' . number_format( round( $amount ), 0, ',', '.' );
	}

	/**
	 * Format a 0-100 integer as a percentage string.
	 *
	 * @param int $value Percent value.
	 * @return string
	 */
	public static function pct( int $value ): string {
		return $value . '%';
	}

	/**
	 * Whole-day difference between two MySQL datetimes (b - a), or null.
	 *
	 * @param string|null $a Earlier datetime.
	 * @param string|null $b Later datetime.
	 * @return float|null Days, or null if either is missing.
	 */
	public static function days_between( ?string $a, ?string $b ): ?float {
		if ( empty( $a ) || empty( $b ) ) {
			return null;
		}

		$ta = strtotime( $a );
		$tb = strtotime( $b );
		if ( false === $ta || false === $tb ) {
			return null;
		}

		return round( ( $tb - $ta ) / DAY_IN_SECONDS, 1 );
	}

	/**
	 * Whole-hour difference between two MySQL datetimes (b - a), or null.
	 *
	 * @param string|null $a Earlier datetime.
	 * @param string|null $b Later datetime.
	 * @return float|null Hours, or null if either is missing.
	 */
	public static function hours_between( ?string $a, ?string $b ): ?float {
		if ( empty( $a ) || empty( $b ) ) {
			return null;
		}

		$ta = strtotime( $a );
		$tb = strtotime( $b );
		if ( false === $ta || false === $tb ) {
			return null;
		}

		return round( ( $tb - $ta ) / HOUR_IN_SECONDS, 1 );
	}
}
