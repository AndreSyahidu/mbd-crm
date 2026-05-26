<?php
/**
 * Offer status labels and chip variants.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Offers;

defined( 'ABSPATH' ) || exit;

/**
 * Presentation helpers for offer statuses.
 */
class Status {

	/**
	 * Human label for a status key.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function label( string $status ): string {
		$labels = array(
			'draft'            => __( 'Draft', 'mbd-crm' ),
			'pending_approval' => __( 'Pending discount approval', 'mbd-crm' ),
			'approved'         => __( 'Approved to send', 'mbd-crm' ),
			'sent'             => __( 'Sent to client', 'mbd-crm' ),
			'accepted'         => __( 'Accepted', 'mbd-crm' ),
			'rejected'         => __( 'Rejected', 'mbd-crm' ),
			'superseded'       => __( 'Superseded', 'mbd-crm' ),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Chip variant for a status key.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function variant( string $status ): string {
		$map = array(
			'draft'            => 'muted',
			'pending_approval' => 'warning',
			'approved'         => 'info',
			'sent'             => 'info',
			'accepted'         => 'success',
			'rejected'         => 'danger',
			'superseded'       => 'muted',
		);

		return $map[ $status ] ?? 'muted';
	}
}
