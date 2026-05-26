<?php
/**
 * Deposit notification provider.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Deposit;

use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions as LeadPermissions;
use MBD\CRM\Router;

defined( 'ABSPATH' ) || exit;

/**
 * Contributes deposit alerts to the Notifications screen: proof awaiting
 * verification (for Finance) and verified deposits (for Sales).
 */
class Notifications {

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Deposit persistence.
	 *
	 * @var DepositRepository
	 */
	private DepositRepository $deposits;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->leads    = new LeadRepository();
		$this->deposits = new DepositRepository();
	}

	/**
	 * Register the notification provider.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_notifications', array( $this, 'items' ) );
	}

	/**
	 * Contribute deposit notification items for the current user.
	 *
	 * @param array<int, array<string, mixed>> $items Existing items.
	 * @return array<int, array<string, mixed>>
	 */
	public function items( array $items ): array {
		// Finance: proofs awaiting verification.
		if ( Permissions::can_verify() ) {
			foreach ( $this->deposits->pending_with_proof() as $deposit ) {
				$lead = $this->leads->find( (int) $deposit->lead_id );
				if ( ! $lead ) {
					continue;
				}

				$items[] = array(
					'icon'    => 'dashicons-money-alt',
					'title'   => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
					'meta'    => __( 'Deposit proof awaiting verification', 'mbd-crm' ),
					'chip'    => __( 'Verify', 'mbd-crm' ),
					'variant' => 'warning',
					'url'     => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
				);
			}
		}

		// Sales: deposits that have been verified valid.
		foreach ( $this->deposits->by_status( 'valid' ) as $deposit ) {
			$lead = $this->leads->find( (int) $deposit->lead_id );
			if ( ! $lead || ! LeadPermissions::can_view( $lead ) ) {
				continue;
			}

			$items[] = array(
				'icon'    => 'dashicons-yes-alt',
				'title'   => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
				'meta'    => __( 'Deposit verified — ready for planning', 'mbd-crm' ),
				'chip'    => __( 'Valid', 'mbd-crm' ),
				'variant' => 'success',
				'url'     => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
			);
		}

		return $items;
	}
}
