<?php
/**
 * Lead fit checks and qualification scoring.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Qualification;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates whether a lead has the data needed to qualify, produces a
 * fit-score, and captures a snapshot of the lead at decision time.
 */
class FitChecks {

	/**
	 * Evaluate every fit check against a lead.
	 *
	 * @param object $lead Lead row.
	 * @return array{checks: array<string, array{label: string, passed: bool}>, score: int, passed: int, total: int}
	 */
	public static function evaluate( object $lead ): array {
		$budget_ok = ( null !== $lead->estimated_budget && '' !== (string) $lead->estimated_budget )
			|| '' !== (string) $lead->budget_unknown_reason;

		$checks = array(
			'contact'      => array(
				'label'  => __( 'Has contact (WhatsApp)', 'mbd-crm' ),
				'passed' => '' !== (string) $lead->whatsapp,
			),
			'source'       => array(
				'label'  => __( 'Source identified', 'mbd-crm' ),
				'passed' => '' !== (string) $lead->source,
			),
			'project_type' => array(
				'label'  => __( 'Project type set', 'mbd-crm' ),
				'passed' => '' !== (string) $lead->project_type,
			),
			'service_type' => array(
				'label'  => __( 'Service type set', 'mbd-crm' ),
				'passed' => '' !== (string) $lead->service_type,
			),
			'urgency'      => array(
				'label'  => __( 'Urgency captured', 'mbd-crm' ),
				'passed' => '' !== (string) $lead->urgency,
			),
			'budget'       => array(
				'label'  => __( 'Budget addressed', 'mbd-crm' ),
				'passed' => $budget_ok,
			),
		);

		$total  = count( $checks );
		$passed = count(
			array_filter(
				$checks,
				static function ( $check ) {
					return $check['passed'];
				}
			)
		);

		return array(
			'checks' => $checks,
			'score'  => $total > 0 ? (int) round( ( $passed / $total ) * 100 ) : 0,
			'passed' => $passed,
			'total'  => $total,
		);
	}

	/**
	 * Checks that must pass before a lead may be marked qualified.
	 *
	 * @return string[]
	 */
	public static function required_keys(): array {
		return array( 'contact', 'source', 'project_type', 'service_type', 'urgency', 'budget' );
	}

	/**
	 * Labels of the required checks a lead currently fails.
	 *
	 * @param object $lead Lead row.
	 * @return string[]
	 */
	public static function missing_required( object $lead ): array {
		$eval    = self::evaluate( $lead );
		$missing = array();

		foreach ( self::required_keys() as $key ) {
			if ( empty( $eval['checks'][ $key ]['passed'] ) ) {
				$missing[] = $eval['checks'][ $key ]['label'];
			}
		}

		return $missing;
	}

	/**
	 * Capture the lead fields that make up a qualification snapshot.
	 *
	 * @param object $lead Lead row.
	 * @return array<string, mixed>
	 */
	public static function snapshot( object $lead ): array {
		$fields = array(
			'name',
			'whatsapp',
			'source',
			'project_type',
			'service_type',
			'estimated_budget',
			'budget_unknown_reason',
			'urgency',
			'quality',
			'status',
			'next_follow_up',
		);

		$snapshot = array();
		foreach ( $fields as $field ) {
			$snapshot[ $field ] = $lead->$field ?? null;
		}

		return $snapshot;
	}
}
