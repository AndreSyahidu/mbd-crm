<?php
/**
 * CRM KPI metrics.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Dashboard;

use MBD\CRM\Closing\ClosingRepository;
use MBD\CRM\Closing\Options as ClosingOptions;
use MBD\CRM\Deposit\DepositRepository;
use MBD\CRM\Discovery\DiscoveryRepository;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Options as LeadOptions;
use MBD\CRM\Planning\PlanningRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Computes pipeline KPIs from the lead and stage tables, scoped to what
 * the current user may see.
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
	 * Cached visible leads.
	 *
	 * @var array<int, object>|null
	 */
	private ?array $leads = null;

	/**
	 * Constructor.
	 *
	 * @param string $scope   'all' or 'own'.
	 * @param int    $user_id Current user ID.
	 */
	public function __construct( string $scope, int $user_id ) {
		$this->scope   = $scope;
		$this->user_id = $user_id;
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
	 * Total leads.
	 *
	 * @return int
	 */
	public function total_leads(): int {
		return count( $this->leads() );
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

	/**
	 * Leads whose response SLA is breached (still new, past due).
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
	 * Qualified lead count.
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
	 * Qualification rate (0-100).
	 *
	 * @return int
	 */
	public function qualification_rate(): int {
		$total = $this->total_leads();

		return $total > 0 ? (int) round( ( $this->qualified() / $total ) * 100 ) : 0;
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

	/**
	 * Visible closings (cached fetch).
	 *
	 * @return array<int, object>
	 */
	private function closings(): array {
		return $this->visible( ( new ClosingRepository() )->all() );
	}

	/**
	 * Total won closing value (sum of final values of approved closings).
	 *
	 * @return float
	 */
	public function closing_value(): float {
		$sum = 0.0;
		foreach ( $this->closings() as $c ) {
			if ( 'approved' === $c->status ) {
				$sum += (float) ( $c->final_value ?? 0 );
			}
		}

		return $sum;
	}

	/**
	 * Weighted forecast value across active closings.
	 *
	 * @return float
	 */
	public function weighted_forecast(): float {
		$sum    = 0.0;
		$active = array( 'open', 'negotiating', 'waiting_approval' );
		foreach ( $this->closings() as $c ) {
			if ( in_array( $c->status, $active, true ) ) {
				$sum += ClosingOptions::weighted_forecast( $c );
			}
		}

		return round( $sum, 2 );
	}

	/**
	 * Closing rate: won / (won + lost), as 0-100.
	 *
	 * @return int
	 */
	public function closing_rate(): int {
		$won  = $this->count_leads(
			static function ( $lead ) {
				return 'won' === $lead->closing_status;
			}
		);
		$lost = $this->count_leads(
			static function ( $lead ) {
				return 'lost' === $lead->closing_status;
			}
		);

		$decided = $won + $lost;

		return $decided > 0 ? (int) round( ( $won / $decided ) * 100 ) : 0;
	}

	/**
	 * Overdue follow-ups (visible leads past their next follow-up date).
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
		$closings = count(
			array_filter(
				$this->closings(),
				static function ( $c ) {
					return 'waiting_approval' === $c->status;
				}
			)
		);
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
	 * Identify the funnel stage with the largest drop from the prior stage.
	 *
	 * @return string Stage label, or '' if no clear bottleneck.
	 */
	public function funnel_bottleneck(): string {
		$funnel    = $this->funnel();
		$labels    = array_keys( $funnel );
		$values    = array_values( $funnel );
		$worst     = '';
		$worst_drop = -1;

		for ( $i = 1; $i < count( $values ); $i++ ) {
			$prev = $values[ $i - 1 ];
			$drop = $prev - $values[ $i ];
			if ( $prev > 0 && $drop > $worst_drop ) {
				$worst_drop = $drop;
				$worst      = $labels[ $i ];
			}
		}

		return $worst;
	}
}
