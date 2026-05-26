<?php
/**
 * Deposit payment methods and verification statuses.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Deposit;

defined( 'ABSPATH' ) || exit;

/**
 * Vocabularies and chip variants for deposits.
 */
class Options {

	/**
	 * Payment methods.
	 *
	 * @return array<string, string>
	 */
	public static function methods(): array {
		return array(
			'transfer' => __( 'Bank transfer', 'mbd-crm' ),
			'cash'     => __( 'Cash', 'mbd-crm' ),
			'card'     => __( 'Card', 'mbd-crm' ),
			'ewallet'  => __( 'E-wallet', 'mbd-crm' ),
			'other'    => __( 'Other', 'mbd-crm' ),
		);
	}

	/**
	 * Verification statuses.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			'pending'  => __( 'Pending', 'mbd-crm' ),
			'valid'    => __( 'Valid', 'mbd-crm' ),
			'rejected' => __( 'Rejected', 'mbd-crm' ),
		);
	}

	/**
	 * Whether a method key is valid.
	 *
	 * @param string $key Method key.
	 * @return bool
	 */
	public static function is_method( string $key ): bool {
		return isset( self::methods()[ $key ] );
	}

	/**
	 * Label for a method key.
	 *
	 * @param string $key Method key.
	 * @return string
	 */
	public static function method_label( string $key ): string {
		return self::methods()[ $key ] ?? $key;
	}

	/**
	 * Label for a status key.
	 *
	 * @param string $key Status key.
	 * @return string
	 */
	public static function status_label( string $key ): string {
		return self::statuses()[ $key ] ?? $key;
	}

	/**
	 * Chip variant for a status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_variant( string $status ): string {
		$map = array(
			'pending'  => 'warning',
			'valid'    => 'success',
			'rejected' => 'danger',
		);

		return $map[ $status ] ?? 'default';
	}
}
