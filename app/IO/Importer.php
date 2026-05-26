<?php
/**
 * CSV importers for leads, customers, and master options.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\IO;

use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Options as LeadOptions;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and imports CSV rows, de-duplicating leads on WhatsApp + name
 * and reporting failed rows. Imported leads are created through the lead
 * repository, so each creation is audited.
 */
class Importer {

	/**
	 * Option storing imported master options.
	 */
	private const MASTER_OPTION = 'mbd_crm_master_options';

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
	 * Import rows of a given type.
	 *
	 * @param string                            $type Import type.
	 * @param array<int, array<string,string>>  $rows Data rows.
	 * @return array{imported:int, failed:array<int, array{row:int, reason:string}>}
	 */
	public function import( string $type, array $rows ): array {
		switch ( $type ) {
			case 'leads':
			case 'customers':
				return $this->import_leads( $rows );
			case 'master_options':
				return $this->import_master_options( $rows );
		}

		return array(
			'imported' => 0,
			'failed'   => array( array( 'row' => 0, 'reason' => __( 'Unknown import type.', 'mbd-crm' ) ) ),
		);
	}

	/**
	 * Import leads / customers.
	 *
	 * @param array<int, array<string,string>> $rows Data rows.
	 * @return array{imported:int, failed:array<int, array{row:int, reason:string}>}
	 */
	private function import_leads( array $rows ): array {
		$imported = 0;
		$failed   = array();
		$uid      = get_current_user_id();

		foreach ( $rows as $index => $row ) {
			$line = $index + 2; // Account for the header row.
			$name = trim( (string) ( $row['name'] ?? '' ) );

			if ( '' === $name ) {
				$failed[] = array( 'row' => $line, 'reason' => __( 'Missing name.', 'mbd-crm' ) );
				continue;
			}

			$whatsapp = preg_replace( '/\D+/', '', (string) ( $row['whatsapp'] ?? '' ) );

			// De-duplicate on WhatsApp + prospect name.
			if ( $this->leads->exists_by_whatsapp_name( $whatsapp, $name ) ) {
				$failed[] = array( 'row' => $line, 'reason' => __( 'Duplicate (WhatsApp + name).', 'mbd-crm' ) );
				continue;
			}

			$source = sanitize_key( (string) ( $row['source'] ?? '' ) );
			if ( '' !== $source && ! LeadOptions::is_valid( 'sources', $source ) ) {
				$source = 'other';
			}

			$data = array(
				'name'                  => $name,
				'whatsapp'              => $whatsapp,
				'source'                => $source,
				'project_type'          => $this->valid_or_blank( 'project_types', (string) ( $row['project_type'] ?? '' ) ),
				'service_type'          => $this->valid_or_blank( 'service_types', (string) ( $row['service_type'] ?? '' ) ),
				'estimated_budget'      => is_numeric( $row['estimated_budget'] ?? '' ) ? $row['estimated_budget'] : '',
				'budget_unknown_reason' => (string) ( $row['budget_unknown_reason'] ?? __( 'Imported', 'mbd-crm' ) ),
				'urgency'               => $this->valid_or_blank( 'urgencies', (string) ( $row['urgency'] ?? '' ) ),
				'quality'               => LeadOptions::is_valid( 'qualities', (string) ( $row['quality'] ?? '' ) ) ? $row['quality'] : 'unknown',
				'status'                => 'new',
				'assigned_to'           => $uid,
				'next_follow_up'        => '',
				'notes'                 => (string) ( $row['notes'] ?? '' ),
			);

			$id = $this->leads->create( $data, $uid );
			if ( $id > 0 ) {
				++$imported;
			} else {
				$failed[] = array( 'row' => $line, 'reason' => __( 'Could not save.', 'mbd-crm' ) );
			}
		}

		return array( 'imported' => $imported, 'failed' => $failed );
	}

	/**
	 * Import master options into a stored option array.
	 *
	 * @param array<int, array<string,string>> $rows Data rows (group, key, label).
	 * @return array{imported:int, failed:array<int, array{row:int, reason:string}>}
	 */
	private function import_master_options( array $rows ): array {
		$imported = 0;
		$failed   = array();
		$store    = get_option( self::MASTER_OPTION, array() );
		$store    = is_array( $store ) ? $store : array();
		$groups   = array( 'sources', 'project_types', 'service_types', 'urgencies' );

		foreach ( $rows as $index => $row ) {
			$line  = $index + 2;
			$group = sanitize_key( (string) ( $row['group'] ?? '' ) );
			$key   = sanitize_key( (string) ( $row['key'] ?? '' ) );
			$label = trim( (string) ( $row['label'] ?? '' ) );

			if ( ! in_array( $group, $groups, true ) || '' === $key || '' === $label ) {
				$failed[] = array( 'row' => $line, 'reason' => __( 'Invalid group, key, or label.', 'mbd-crm' ) );
				continue;
			}

			$store[ $group ][ $key ] = $label;
			++$imported;
		}

		update_option( self::MASTER_OPTION, $store );

		return array( 'imported' => $imported, 'failed' => $failed );
	}

	/**
	 * Return a value if valid within a lead option group, else blank.
	 *
	 * @param string $group Group name.
	 * @param string $value Candidate value.
	 * @return string
	 */
	private function valid_or_blank( string $group, string $value ): string {
		$value = sanitize_key( $value );

		return LeadOptions::is_valid( $group, $value ) ? $value : '';
	}
}
