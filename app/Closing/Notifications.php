<?php
/**
 * Closing notification provider.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Closing;

use MBD\CRM\Leads\Capabilities;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Router;

defined( 'ABSPATH' ) || exit;

/**
 * Notifies authorised approvers of closings awaiting approval.
 */
class Notifications {

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Closing persistence.
	 *
	 * @var ClosingRepository
	 */
	private ClosingRepository $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->leads = new LeadRepository();
		$this->repo  = new ClosingRepository();
	}

	/**
	 * Register the provider.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_notifications', array( $this, 'items' ) );
	}

	/**
	 * Contribute "awaiting approval" items for authorised approvers.
	 *
	 * @param array<int, array<string, mixed>> $items Existing items.
	 * @return array<int, array<string, mixed>>
	 */
	public function items( array $items ): array {
		if ( ! current_user_can( Capabilities::APPROVE_CLOSING ) ) {
			return $items;
		}

		foreach ( $this->repo->by_status( 'waiting_approval' ) as $closing ) {
			$lead = $this->leads->find( (int) $closing->lead_id );
			if ( ! $lead ) {
				continue;
			}

			$items[] = array(
				'icon'    => 'dashicons-thumbs-up',
				'title'   => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
				'meta'    => __( 'Closing awaiting your approval', 'mbd-crm' ),
				'chip'    => __( 'Approve', 'mbd-crm' ),
				'variant' => 'warning',
				'url'     => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
			);
		}

		return $items;
	}
}
