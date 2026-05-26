<?php
/**
 * Client approval evidence types.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Approval;

defined( 'ABSPATH' ) || exit;

/**
 * Evidence type vocabulary.
 */
class Options {

	/**
	 * Evidence types.
	 *
	 * @return array<string, string>
	 */
	public static function types(): array {
		return array(
			'whatsapp_screenshot' => __( 'WhatsApp screenshot', 'mbd-crm' ),
			'signed_document'     => __( 'Signed document', 'mbd-crm' ),
			'email'               => __( 'Email', 'mbd-crm' ),
			'manual_note'         => __( 'Manual note', 'mbd-crm' ),
			'other'               => __( 'Other', 'mbd-crm' ),
		);
	}

	/**
	 * Whether a type key is valid.
	 *
	 * @param string $key Type key.
	 * @return bool
	 */
	public static function is_type( string $key ): bool {
		return isset( self::types()[ $key ] );
	}

	/**
	 * Label for a type key.
	 *
	 * @param string $key Type key.
	 * @return string
	 */
	public static function label( string $key ): string {
		return self::types()[ $key ] ?? $key;
	}
}
