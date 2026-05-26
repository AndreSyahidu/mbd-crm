<?php
/**
 * CSV exporters for leads and reports.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\IO;

use MBD\CRM\Closing\ClosingRepository;
use MBD\CRM\Closing\Options as ClosingOptions;
use MBD\CRM\Dashboard\Metrics;
use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Options as LeadOptions;
use MBD\CRM\Planning\Options as PlanningOptions;
use MBD\CRM\Planning\PlanningRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Builds CSV row sets (header + data) for each export type.
 */
class Exporter {

	/**
	 * Build rows for an export type.
	 *
	 * @param string $type Export type.
	 * @return array<int, array<int, string>>
	 */
	public function rows( string $type ): array {
		switch ( $type ) {
			case 'leads':
				return $this->leads();
			case 'funnel':
				return $this->funnel();
			case 'planning':
				return $this->planning();
			case 'closing_forecast':
				return $this->closing_forecast();
			case 'lost_reason':
				return $this->lost_reasons();
			case 'audit':
				return $this->audit();
		}

		return array( array( 'error' ) );
	}

	/**
	 * Leads export.
	 *
	 * @return array<int, array<int, string>>
	 */
	private function leads(): array {
		$rows = array( array( 'ID', 'Name', 'WhatsApp', 'Source', 'Status', 'Quality', 'Qualification', 'Deposit', 'Closing', 'Created' ) );

		foreach ( ( new LeadRepository() )->all() as $l ) {
			$rows[] = array(
				(string) $l->id,
				(string) $l->name,
				(string) $l->whatsapp,
				LeadOptions::label( 'sources', (string) $l->source ),
				LeadOptions::label( 'statuses', (string) $l->status ),
				(string) $l->quality,
				(string) $l->qualification,
				(string) $l->deposit_status,
				(string) $l->closing_status,
				(string) $l->created_at,
			);
		}

		return $rows;
	}

	/**
	 * Funnel report.
	 *
	 * @return array<int, array<int, string>>
	 */
	private function funnel(): array {
		$rows    = array( array( 'Stage', 'Count' ) );
		$metrics = new Metrics( 'all', get_current_user_id() );
		foreach ( $metrics->funnel() as $stage => $count ) {
			$rows[] = array( (string) $stage, (string) $count );
		}

		return $rows;
	}

	/**
	 * Planning report.
	 *
	 * @return array<int, array<int, string>>
	 */
	private function planning(): array {
		$rows = array( array( 'ID', 'Lead ID', 'Status', 'Internal review', 'Target date', 'Created' ) );

		foreach ( ( new PlanningRepository() )->all() as $p ) {
			$rows[] = array(
				(string) $p->id,
				(string) $p->lead_id,
				PlanningOptions::status_label( (string) $p->status ),
				(string) $p->internal_review,
				(string) $p->target_date,
				(string) $p->created_at,
			);
		}

		return $rows;
	}

	/**
	 * Closing forecast report.
	 *
	 * @return array<int, array<int, string>>
	 */
	private function closing_forecast(): array {
		$rows = array( array( 'ID', 'Lead ID', 'Status', 'Offered', 'Probability', 'Weighted forecast', 'Expected close' ) );

		foreach ( ( new ClosingRepository() )->all() as $c ) {
			$rows[] = array(
				(string) $c->id,
				(string) $c->lead_id,
				ClosingOptions::status_label( (string) $c->status ),
				(string) ( $c->offered_value ?? '' ),
				(string) $c->probability,
				(string) ClosingOptions::weighted_forecast( $c ),
				(string) ( $c->expected_close_date ?? '' ),
			);
		}

		return $rows;
	}

	/**
	 * Lost-reason report.
	 *
	 * @return array<int, array<int, string>>
	 */
	private function lost_reasons(): array {
		$rows    = array( array( 'Reason', 'Count' ) );
		$metrics = new Metrics( 'all', get_current_user_id() );
		foreach ( $metrics->lost_reasons() as $reason => $count ) {
			$rows[] = array( (string) $reason, (string) $count );
		}

		return $rows;
	}

	/**
	 * Audit-log export (authorised users only — gated by the caller).
	 *
	 * @return array<int, array<int, string>>
	 */
	private function audit(): array {
		$rows = array( array( 'ID', 'Lead ID', 'User ID', 'Action', 'Detail', 'Date' ) );

		foreach ( Audit::export( 5000 ) as $a ) {
			$rows[] = array(
				(string) $a->id,
				(string) $a->lead_id,
				(string) $a->user_id,
				(string) $a->action,
				(string) $a->detail,
				(string) $a->created_at,
			);
		}

		return $rows;
	}
}
