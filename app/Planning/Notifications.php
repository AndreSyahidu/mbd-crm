<?php
/**
 * Planning notification provider.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Planning;

use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;

defined( 'ABSPATH' ) || exit;

/**
 * Notifies Sales when a planning reaches the final stage.
 */
class Notifications {

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Planning persistence.
	 *
	 * @var PlanningRepository
	 */
	private PlanningRepository $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->leads = new LeadRepository();
		$this->repo  = new PlanningRepository();
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
	 * Contribute "planning final" items for leads the user can view.
	 *
	 * @param array<int, array<string, mixed>> $items Existing items.
	 * @return array<int, array<string, mixed>>
	 */
	public function items( array $items ): array {
		foreach ( $this->repo->by_status( 'final' ) as $planning ) {
			$lead = $this->leads->find( (int) $planning->lead_id );
			if ( ! $lead || ! Permissions::can_view( $lead ) ) {
				continue;
			}

			$items[] = array(
				'icon'    => 'dashicons-calendar-alt',
				'title'   => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
				'meta'    => __( 'Planning is final — ready for client approval', 'mbd-crm' ),
				'chip'    => __( 'Final', 'mbd-crm' ),
				'variant' => 'info',
				'url'     => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
			);
		}

		return $items;
	}
}
