<?php
/**
 * CRM KPI metrics.
 *
 * Every calculation here routes through {@see Formulas} and (where relevant)
 * a {@see Period}. Each method documents its exact formula. Division is
 * always guarded against zero via Formulas::percent().
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Dashboard;

use MBD\CRM\Closing\ClosingRepository;
use MBD\CRM\Deposit\DepositRepository;
use MBD\CRM\Discovery\DiscoveryRepository;
use MBD\CRM\FollowUp\FollowUpRepository;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Options as LeadOptions;
use MBD\CRM\Leads\Tasks;
use MBD\CRM\Planning\PlanningRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Computes pipeline KPIs from the lead and stage tables, scoped to what the
 * current user may see and (optionally) to a reporting period.
 */
class Metrics {

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
	 * Reporting period.
	 *
	 * @var Period
	 */
	private Period $period;

	/**
	 * Cached visible leads.
	 *
	 * @var array<int, object>|null
	 */
	private ?array $leads = null;

	/**
	 * Cached closings keyed by lead ID.
	 *
	 * @var array<int, object>|null
	 */
	private ?array $closings_by_lead = null;

	/**
	 * Cached first-contact map (lead_id => earliest follow-up datetime).
	 *
	 * @var array<int, string>|null
	 */
	private ?array $first_contact = null;

	/**
	 * Constructor.
	 *
	 * @param string      $scope   'all' or 'own'.
	 * @param int         $user_id Current user ID.
	 * @param Period|null $period  Reporting period (defaults to all-time).
	 */
	public function __construct( string $scope, int $user_id, ?Period $period = null ) {
		$this->scope   = $scope;
		$this->user_id = $user_id;
		$this->period  = $period ?? Period::from_key( 'all' );
	}

	/**
	 * Active reporting period.
	 *
	 * @return Period
	 */
	public function period(): Period {
		return $this->period;
	}

	/**
	 * Visible leads (cached).
	 *
	 * @return array<int, object>
	 */
	public function leads(): array {
		if ( null === $this->leads ) {
			$this->leads = ( new LeadRepository() )->all(
				array(
					'scope'   => $this->scope,
					'user_id' => $this->user_id,
				)
			);
		}

		return $this->leads;
	}

	/**
	 * Visible leads created within the period.
	 *
	 * @return array<int, object>
	 */
	private function leads_in_period(): array {
		if ( $this->period->is_all_time() ) {
			return $this->leads();
		}

		return array_values(
			array_filter(
				$this->leads(),
				function ( $lead ) {
					return $this->period->contains( $lead->created_at ?? null );
				}
			)
		);
	}

	/**
	 * Set of visible lead IDs.
	 *
	 * @return array<int, bool>
	 */
	private function visible_ids(): array {
		$ids = array();
		foreach ( $this->leads() as $lead ) {
			$ids[ (int) $lead->id ] = true;
		}

		return $ids;
	}

	/**
	 * Filter stage rows down to visible leads.
	 *
	 * @param array<int, object> $rows Rows with a lead_id property.
	 * @return array<int, object>
	 */
	private function visible( array $rows ): array {
		$ids = $this->visible_ids();

		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $ids ) {
					return isset( $ids[ (int) $row->lead_id ] );
				}
			)
		);
	}

	/**
	 * Count visible leads matching a predicate.
	 *
	 * @param callable $predicate Predicate receiving a lead row.
	 * @return int
	 */
	private function count_leads( callable $predicate ): int {
		$n = 0;
		foreach ( $this->leads() as $lead ) {
			if ( $predicate( $lead ) ) {
				++$n;
			}
		}

		return $n;
	}

	/**
	 * Closing row for a lead, or null.
	 *
	 * @param int $lead_id Lead ID.
	 * @return object|null
	 */
	private function closing_for( int $lead_id ): ?object {
		if ( null === $this->closings_by_lead ) {
			$this->closings_by_lead = array();
			foreach ( $this->visible( ( new ClosingRepository() )->all() ) as $c ) {
				$this->closings_by_lead[ (int) $c->lead_id ] = $c;
			}
		}

		return $this->closings_by_lead[ $lead_id ] ?? null;
	}

	/**
	 * Visible closings (array).
	 *
	 * @return array<int, object>
	 */
	private function closings(): array {
		$this->closing_for( 0 ); // Prime the cache.

		return array_values( $this->closings_by_lead );
	}

	/**
	 * First-contact datetime for a lead (earliest follow-up), or null.
	 *
	 * @param int $lead_id Lead ID.
	 * @return string|null
	 */
	private function first_contact_at( int $lead_id ): ?string {
		if ( null === $this->first_contact ) {
			$this->first_contact = ( new FollowUpRepository() )->first_contact_map();
		}

		return $this->first_contact[ $lead_id ] ?? null;
	}

	// -- Volume -----------------------------------------------------------

	/**
	 * Total visible leads (all-time).
	 *
	 * Formula: COUNT(visible leads).
	 *
	 * @return int
	 */
	public function total_leads(): int {
		return count( $this->leads() );
	}

	/**
	 * New leads in the period.
	 *
	 * Formula: COUNT(visible leads WHERE created_at within period).
	 *
	 * @return int
	 */
	public function new_leads(): int {
		return count( $this->leads_in_period() );
	}

	/**
	 * Leads grouped by source (label => count).
	 *
	 * @return array<string, int>
	 */
	public function leads_by_source(): array {
		$out = array();
		foreach ( $this->leads() as $lead ) {
			$label         = '' !== $lead->source ? LeadOptions::label( 'sources', $lead->source ) : __( 'Unknown', 'mbd-crm' );
			$out[ $label ] = ( $out[ $label ] ?? 0 ) + 1;
		}
		arsort( $out );

		return $out;
	}

	// -- Qualification ----------------------------------------------------

	/**
	 * Qualified lead count.
	 *
	 * Formula: COUNT(leads WHERE qualification = 'qualified').
	 *
	 * @return int
	 */
	public function qualified(): int {
		return $this->count_leads(
			static function ( $lead ) {
				return 'qualified' === $lead->qualification;
			}
		);
	}

	/**
	 * Qualification rate.
	 *
	 * Formula: qualified / total leads × 100 (0 when no leads).
	 *
	 * @return int
	 */
	public function qualification_rate(): int {
		return Formulas::percent( $this->qualified(), $this->total_leads() );
	}

	/**
	 * Completed discoveries (visible).
	 *
	 * @return int
	 */
	public function discovery_completed(): int {
		return count( $this->visible( ( new DiscoveryRepository() )->by_status( 'completed' ) ) );
	}

	/**
	 * Valid deposits (visible).
	 *
	 * Formula: COUNT(leads WHERE deposit_status = 'valid').
	 *
	 * @return int
	 */
	public function deposit_valid(): int {
		return $this->count_leads(
			static function ( $lead ) {
				return 'valid' === $lead->deposit_status;
			}
		);
	}

	/**
	 * Plannings in progress (visible).
	 *
	 * @return int
	 */
	public function planning_in_progress(): int {
		return count( $this->visible( ( new PlanningRepository() )->by_status( 'in_progress' ) ) );
	}

	/**
	 * Plannings approved by client (visible).
	 *
	 * @return int
	 */
	public function planning_approved(): int {
		return $this->count_leads(
			static function ( $lead ) {
				return (int) $lead->planning_approved === 1;
			}
		);
	}

	// -- Value ------------------------------------------------------------

	/**
	 * Pipeline value of active (non-closed) opportunities.
	 *
	 * Formula: Σ opportunity_value(lead, closing) for leads whose
	 * closing_status is not won/lost. Opportunity value precedence:
	 * final → offered → estimated closing value → numeric estimated_budget.
	 *
	 * @return float
	 */
	public function pipeline_value(): float {
		$sum = 0.0;
		foreach ( $this->leads() as $lead ) {
			if ( in_array( $lead->closing_status, array( 'won', 'lost' ), true ) ) {
				continue;
			}
			$sum += Formulas::opportunity_value( $lead, $this->closing_for( (int) $lead->id ) );
		}

		return round( $sum, 2 );
	}

	/**
	 * Weighted forecast value across active opportunities.
	 *
	 * Formula: Σ ( opportunity_value × probability / 100 ) for active
	 * (non-closed) opportunities. Probability uses the closing's explicit
	 * value, else the per-stage default.
	 *
	 * @return float
	 */
	public function weighted_forecast(): float {
		$sum = 0.0;
		foreach ( $this->leads() as $lead ) {
			if ( in_array( $lead->closing_status, array( 'won', 'lost' ), true ) ) {
				continue;
			}
			$sum += Formulas::weighted_value( $lead, $this->closing_for( (int) $lead->id ) );
		}

		return round( $sum, 2 );
	}

	/**
	 * Whether an approved closing falls in the period (legacy rows with no
	 * approved_at are included only for all-time).
	 *
	 * @param object $closing Closing row.
	 * @return bool
	 */
	private function approved_in_period( object $closing ): bool {
		if ( $this->period->is_all_time() ) {
			return true;
		}
		if ( empty( $closing->approved_at ) ) {
			return false;
		}

		return $this->period->contains( $closing->approved_at );
	}

	/**
	 * Closing value: Σ final_value for approved closings in the period.
	 *
	 * @return float
	 */
	public function closing_value(): float {
		$sum = 0.0;
		foreach ( $this->closings() as $c ) {
			if ( 'approved' === $c->status && $this->approved_in_period( $c ) ) {
				$sum += (float) ( $c->final_value ?? 0 );
			}
		}

		return round( $sum, 2 );
	}

	/**
	 * Count of approved closings in the period.
	 *
	 * @return int
	 */
	public function closing_approved_count(): int {
		$n = 0;
		foreach ( $this->closings() as $c ) {
			if ( 'approved' === $c->status && $this->approved_in_period( $c ) ) {
				++$n;
			}
		}

		return $n;
	}

	/**
	 * Closing (win) rate.
	 *
	 * Formula: approved closings (period) / new leads (period) × 100.
	 * For all-time this is approved / total leads.
	 *
	 * @return int
	 */
	public function closing_rate(): int {
		return Formulas::percent( $this->closing_approved_count(), $this->new_leads() );
	}

	/**
	 * Lost rate.
	 *
	 * Formula: failed closings / total closing opportunities × 100.
	 * Total closing opportunities = visible closing records.
	 *
	 * @return int
	 */
	public function lost_rate(): int {
		$total = count( $this->closings() );
		$lost  = 0;
		foreach ( $this->closings() as $c ) {
			if ( 'lost' === $c->status ) {
				++$lost;
			}
		}

		return Formulas::percent( $lost, $total );
	}

	// -- Time / SLA -------------------------------------------------------

	/**
	 * Average first-response time, in hours.
	 *
	 * Formula: AVG( first_follow_up.created_at − lead.created_at ) over leads
	 * (in period) that have at least one follow-up. Leads with no follow-up
	 * are excluded from the average (see missing_response()).
	 *
	 * @return float|null Null when no responded leads.
	 */
	public function avg_response_hours(): ?float {
		$sum   = 0.0;
		$count = 0;
		foreach ( $this->leads_in_period() as $lead ) {
			$first = $this->first_contact_at( (int) $lead->id );
			$hours = Formulas::hours_between( $lead->created_at ?? null, $first );
			if ( null !== $hours ) {
				$sum += $hours;
				++$count;
			}
		}

		return $count > 0 ? round( $sum / $count, 1 ) : null;
	}

	/**
	 * Count of leads (in period) with no follow-up recorded.
	 *
	 * @return int
	 */
	public function missing_response(): int {
		$n = 0;
		foreach ( $this->leads_in_period() as $lead ) {
			if ( null === $this->first_contact_at( (int) $lead->id ) ) {
				++$n;
			}
		}

		return $n;
	}

	/**
	 * Average closing time, in days.
	 *
	 * Formula: AVG( closing.approved_at − lead.created_at ) over approved
	 * closings in the period.
	 *
	 * @return float|null Null when no approved closings.
	 */
	public function avg_closing_days(): ?float {
		$leads_by_id = array();
		foreach ( $this->leads() as $lead ) {
			$leads_by_id[ (int) $lead->id ] = $lead;
		}

		$sum   = 0.0;
		$count = 0;
		foreach ( $this->closings() as $c ) {
			if ( 'approved' !== $c->status || ! $this->approved_in_period( $c ) ) {
				continue;
			}
			$lead = $leads_by_id[ (int) $c->lead_id ] ?? null;
			$days = $lead ? Formulas::days_between( $lead->created_at ?? null, $c->approved_at ?? null ) : null;
			if ( null !== $days ) {
				$sum += $days;
				++$count;
			}
		}

		return $count > 0 ? round( $sum / $count, 1 ) : null;
	}

	/**
	 * Response SLA still breached now (new leads past due).
	 *
	 * Formula: COUNT(leads WHERE status = 'new' AND sla_due_at < now).
	 *
	 * @return int
	 */
	public function response_overdue(): int {
		$now = current_time( 'mysql' );

		return $this->count_leads(
			static function ( $lead ) use ( $now ) {
				return 'new' === $lead->status && ! empty( $lead->sla_due_at ) && $lead->sla_due_at < $now;
			}
		);
	}

	/**
	 * Response SLA breach count within the period.
	 *
	 * Formula: COUNT(leads WHERE sla_due_at within period AND first contact
	 * was after the SLA due time, OR no contact yet and now > due).
	 * Computed from the response SLA (no separate SLA-events log yet).
	 *
	 * @return int
	 */
	public function sla_breach_count(): int {
		$now = current_time( 'mysql' );
		$n   = 0;

		foreach ( $this->leads() as $lead ) {
			if ( empty( $lead->sla_due_at ) ) {
				continue;
			}
			if ( ! $this->period->is_all_time() && ! $this->period->contains( $lead->sla_due_at ) ) {
				continue;
			}
			$first    = $this->first_contact_at( (int) $lead->id );
			$breached = null !== $first ? ( $first > $lead->sla_due_at ) : ( $now > $lead->sla_due_at );
			if ( $breached ) {
				++$n;
			}
		}

		return $n;
	}

	// -- Follow-up / approvals -------------------------------------------

	/**
	 * Overdue follow-up tasks.
	 *
	 * Formula: COUNT(open tasks WHERE due_at < now) for visible leads.
	 *
	 * @return int
	 */
	public function overdue_followup_tasks(): int {
		return count( $this->visible( Tasks::open_overdue( current_time( 'mysql' ) ) ) );
	}

	/**
	 * Overdue follow-ups by lead next-follow-up date (legacy widget metric).
	 *
	 * Formula: COUNT(leads WHERE next_follow_up < today AND not won/lost).
	 *
	 * @return int
	 */
	public function overdue_followups(): int {
		$today = current_time( 'Y-m-d' );

		return $this->count_leads(
			static function ( $lead ) use ( $today ) {
				return ! empty( $lead->next_follow_up )
					&& $lead->next_follow_up < $today
					&& ! in_array( $lead->status, array( 'won', 'lost' ), true );
			}
		);
	}

	/**
	 * Pending approvals: closings awaiting approval + deposits awaiting verify.
	 *
	 * @return int
	 */
	public function pending_approval(): int {
		$closings = 0;
		foreach ( $this->closings() as $c ) {
			if ( 'waiting_approval' === $c->status ) {
				++$closings;
			}
		}
		$deposits = count( $this->visible( ( new DepositRepository() )->by_status( 'pending' ) ) );

		return $closings + $deposits;
	}

	/**
	 * Lost-reason breakdown (reason => count) for lost closings.
	 *
	 * @return array<string, int>
	 */
	public function lost_reasons(): array {
		$out = array();
		foreach ( $this->closings() as $c ) {
			if ( 'lost' === $c->status ) {
				$reason         = '' !== $c->lost_reason ? $c->lost_reason : __( 'Unspecified', 'mbd-crm' );
				$out[ $reason ] = ( $out[ $reason ] ?? 0 ) + 1;
			}
		}
		arsort( $out );

		return $out;
	}

	// -- Funnel -----------------------------------------------------------

	/**
	 * Funnel stage counts (label => count), in pipeline order.
	 *
	 * @return array<string, int>
	 */
	public function funnel(): array {
		return array(
			__( 'Leads', 'mbd-crm' )             => $this->total_leads(),
			__( 'Qualified', 'mbd-crm' )         => $this->qualified(),
			__( 'Discovery done', 'mbd-crm' )    => $this->discovery_completed(),
			__( 'Deposit valid', 'mbd-crm' )     => $this->deposit_valid(),
			__( 'Planning approved', 'mbd-crm' ) => $this->planning_approved(),
			__( 'Won', 'mbd-crm' )               => $this->count_leads(
				static function ( $lead ) {
					return 'won' === $lead->closing_status;
				}
			),
		);
	}

	/**
	 * Funnel bottleneck: the stage with the largest conversion drop from the
	 * previous stage.
	 *
	 * Formula: for each adjacent pair, drop = 1 − (stage / previous);
	 * the stage with the largest drop (previous > 0) is the bottleneck.
	 *
	 * @return string Stage label, or '' if no clear bottleneck.
	 */
	public function funnel_bottleneck(): string {
		$funnel = $this->funnel();
		$labels = array_keys( $funnel );
		$values = array_values( $funnel );
		$worst  = '';
		$worst_drop = -1.0;

		for ( $i = 1, $n = count( $values ); $i < $n; $i++ ) {
			$prev = $values[ $i - 1 ];
			if ( $prev <= 0 ) {
				continue;
			}
			$drop = 1 - ( $values[ $i ] / $prev );
			if ( $drop > $worst_drop ) {
				$worst_drop = $drop;
				$worst      = $labels[ $i ];
			}
		}

		return $worst;
	}

	/**
	 * Human-readable formulas for the key metrics (for dashboard help text).
	 *
	 * @return array<string, string>
	 */
	public static function formulas(): array {
		return array(
			__( 'Qualification rate', 'mbd-crm' ) => __( 'Qualified leads ÷ total leads × 100', 'mbd-crm' ),
			__( 'Closing rate', 'mbd-crm' )       => __( 'Approved closings (period) ÷ new leads (period) × 100', 'mbd-crm' ),
			__( 'Lost rate', 'mbd-crm' )          => __( 'Failed closings ÷ total closing opportunities × 100', 'mbd-crm' ),
			__( 'Pipeline value', 'mbd-crm' )     => __( 'Σ best opportunity value of active (non-closed) leads', 'mbd-crm' ),
			__( 'Weighted forecast', 'mbd-crm' )  => __( 'Σ (opportunity value × probability ÷ 100); default probability by stage when missing', 'mbd-crm' ),
			__( 'Closing value', 'mbd-crm' )      => __( 'Σ final value of closings approved within the period', 'mbd-crm' ),
			__( 'Avg response time', 'mbd-crm' )  => __( 'AVG(first follow-up − created) over responded leads; no-response excluded but counted', 'mbd-crm' ),
			__( 'Avg closing time', 'mbd-crm' )   => __( 'AVG(closing approved − created) over approved closings', 'mbd-crm' ),
			__( 'SLA breach', 'mbd-crm' )         => __( 'Leads whose first response was after the SLA due time (or none yet, past due)', 'mbd-crm' ),
		);
	}
}
