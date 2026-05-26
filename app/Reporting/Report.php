<?php
/**
 * Reporting & analytics datasets.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Reporting;

use MBD\CRM\Closing\ClosingRepository;
use MBD\CRM\Dashboard\Formulas;
use MBD\CRM\Dashboard\Metrics;
use MBD\CRM\Dashboard\Period;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Options as LeadOptions;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the report datasets (conversion funnel, win/loss, sales performance,
 * source effectiveness). Aggregate numbers route through {@see Metrics} and
 * {@see Formulas} so reports and the dashboard never disagree; the per-owner
 * and per-source breakdowns are computed here from the same scoped, period-
 * filtered lead set.
 */
class Report {

	/**
	 * Aggregate metrics service (shared formulas).
	 *
	 * @var Metrics
	 */
	private Metrics $metrics;

	/**
	 * Reporting period.
	 *
	 * @var Period
	 */
	private Period $period;

	/**
	 * Period-filtered, scoped leads (cached).
	 *
	 * @var array<int, object>|null
	 */
	private ?array $leads = null;

	/**
	 * Scope ('all' or 'own').
	 *
	 * @var string
	 */
	private string $scope;

	/**
	 * Current user ID.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * Closing rows keyed by lead ID (cached).
	 *
	 * @var array<int, object>|null
	 */
	private ?array $closings = null;

	/**
	 * Constructor.
	 *
	 * @param string $scope   'all' or 'own'.
	 * @param int    $user_id Current user ID.
	 * @param Period $period  Reporting period.
	 */
	public function __construct( string $scope, int $user_id, Period $period ) {
		$this->scope   = $scope;
		$this->user_id = $user_id;
		$this->period  = $period;
		$this->metrics = new Metrics( $scope, $user_id, $period );
	}

	/**
	 * Active period.
	 *
	 * @return Period
	 */
	public function period(): Period {
		return $this->period;
	}

	/**
	 * Scoped leads created within the period.
	 *
	 * @return array<int, object>
	 */
	private function leads(): array {
		if ( null === $this->leads ) {
			$all = ( new LeadRepository() )->all(
				array(
					'scope'   => $this->scope,
					'user_id' => $this->user_id,
				)
			);

			$this->leads = $this->period->is_all_time()
				? $all
				: array_values(
					array_filter(
						$all,
						fn( $lead ) => $this->period->contains( $lead->created_at ?? null )
					)
				);
		}

		return $this->leads;
	}

	/**
	 * Closing row for a lead (within the visible set), or null.
	 *
	 * @param int $lead_id Lead ID.
	 * @return object|null
	 */
	private function closing_for( int $lead_id ): ?object {
		if ( null === $this->closings ) {
			$visible = array();
			foreach ( $this->leads() as $lead ) {
				$visible[ (int) $lead->id ] = true;
			}

			$this->closings = array();
			foreach ( ( new ClosingRepository() )->all() as $c ) {
				if ( isset( $visible[ (int) $c->lead_id ] ) ) {
					$this->closings[ (int) $c->lead_id ] = $c;
				}
			}
		}

		return $this->closings[ $lead_id ] ?? null;
	}

	/**
	 * Conversion funnel rows: stage, count, conversion from previous stage,
	 * and share of the top of the funnel.
	 *
	 * @return array<int, array{stage:string, count:int, from_prev:int, of_top:int}>
	 */
	public function funnel(): array {
		$funnel = $this->metrics->funnel();
		$labels = array_keys( $funnel );
		$values = array_values( $funnel );
		$top    = $values[0] ?? 0;

		$rows = array();
		foreach ( $values as $i => $count ) {
			$prev       = $i > 0 ? $values[ $i - 1 ] : $count;
			$rows[]     = array(
				'stage'     => $labels[ $i ],
				'count'     => (int) $count,
				'from_prev' => $i > 0 ? Formulas::percent( $count, $prev ) : 100,
				'of_top'    => Formulas::percent( $count, $top ),
			);
		}

		return $rows;
	}

	/**
	 * Win/loss summary.
	 *
	 * @return array<string, string>
	 */
	public function win_loss(): array {
		$won   = $this->metrics->closing_approved_count();
		$value = $this->metrics->closing_value();

		$lost = 0;
		$open = 0;
		foreach ( $this->leads() as $lead ) {
			$closing = $this->closing_for( (int) $lead->id );
			if ( ! $closing ) {
				continue;
			}
			if ( 'lost' === $closing->status ) {
				++$lost;
			} elseif ( ! in_array( $closing->status, array( 'approved' ), true ) ) {
				++$open;
			}
		}

		$avg_deal    = $won > 0 ? $value / $won : 0.0;
		$avg_days    = $this->metrics->avg_closing_days();

		return array(
			__( 'Won', 'mbd-crm' )            => (string) $won,
			__( 'Lost', 'mbd-crm' )           => (string) $lost,
			__( 'In progress', 'mbd-crm' )    => (string) $open,
			__( 'Win rate', 'mbd-crm' )       => Formulas::pct( $this->metrics->closing_rate() ),
			__( 'Lost rate', 'mbd-crm' )      => Formulas::pct( $this->metrics->lost_rate() ),
			__( 'Closing value', 'mbd-crm' )  => Formulas::idr( $value ),
			__( 'Avg deal size', 'mbd-crm' )  => Formulas::idr( $avg_deal ),
			__( 'Avg closing time', 'mbd-crm' ) => null === $avg_days ? '—' : $avg_days . __( 'd', 'mbd-crm' ),
		);
	}

	/**
	 * Lost-reason breakdown (reason => count).
	 *
	 * @return array<string, int>
	 */
	public function lost_reasons(): array {
		return $this->metrics->lost_reasons();
	}

	/**
	 * Per-owner sales performance, sorted by won deals then leads.
	 *
	 * @return array<int, array{owner:string, leads:int, qualified:int, won:int, win_rate:int, value:float}>
	 */
	public function by_owner(): array {
		$rows = array();
		foreach ( $this->leads() as $lead ) {
			$oid = (int) $lead->assigned_to;
			if ( ! isset( $rows[ $oid ] ) ) {
				$rows[ $oid ] = array(
					'owner'     => $this->owner_name( $oid ),
					'leads'     => 0,
					'qualified' => 0,
					'won'       => 0,
					'win_rate'  => 0,
					'value'     => 0.0,
				);
			}

			++$rows[ $oid ]['leads'];
			if ( 'qualified' === $lead->qualification ) {
				++$rows[ $oid ]['qualified'];
			}
			if ( 'won' === $lead->closing_status ) {
				++$rows[ $oid ]['won'];
				$rows[ $oid ]['value'] += Formulas::opportunity_value( $lead, $this->closing_for( (int) $lead->id ) );
			}
		}

		foreach ( $rows as &$row ) {
			$row['win_rate'] = Formulas::percent( $row['won'], $row['leads'] );
		}
		unset( $row );

		usort(
			$rows,
			static function ( $a, $b ) {
				return array( $b['won'], $b['leads'] ) <=> array( $a['won'], $a['leads'] );
			}
		);

		return $rows;
	}

	/**
	 * Source effectiveness: leads, won, conversion %.
	 *
	 * @return array<int, array{source:string, leads:int, won:int, conversion:int}>
	 */
	public function by_source(): array {
		$rows = array();
		foreach ( $this->leads() as $lead ) {
			$key = '' !== $lead->source ? $lead->source : 'unknown';
			if ( ! isset( $rows[ $key ] ) ) {
				$label        = 'unknown' === $key ? __( 'Unknown', 'mbd-crm' ) : LeadOptions::label( 'sources', $lead->source );
				$rows[ $key ] = array(
					'source' => $label,
					'leads'  => 0,
					'won'    => 0,
					'conversion' => 0,
				);
			}

			++$rows[ $key ]['leads'];
			if ( 'won' === $lead->closing_status ) {
				++$rows[ $key ]['won'];
			}
		}

		foreach ( $rows as &$row ) {
			$row['conversion'] = Formulas::percent( $row['won'], $row['leads'] );
		}
		unset( $row );

		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['leads'] <=> $a['leads'];
			}
		);

		return array_values( $rows );
	}

	/**
	 * Resolve an owner ID to a display name.
	 *
	 * @param int $owner_id Owner (assigned_to) user ID.
	 * @return string
	 */
	private function owner_name( int $owner_id ): string {
		if ( $owner_id <= 0 ) {
			return __( 'Unassigned', 'mbd-crm' );
		}

		$user = get_userdata( $owner_id );

		return $user ? $user->display_name : sprintf( /* translators: %d: user id. */ __( 'User #%d', 'mbd-crm' ), $owner_id );
	}
}
