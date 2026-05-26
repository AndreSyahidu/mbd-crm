<?php
/**
 * In-app notification providers for SLA breaches and stale leads.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Reminders;

use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;

defined( 'ABSPATH' ) || exit;

/**
 * Contributes Response-SLA-breach and stale-lead items to the
 * `mbd_crm_notifications` filter for the current user.
 */
class Providers {

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
	 * Register the notification providers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_notifications', array( $this, 'items' ) );
	}

	/**
	 * Add SLA-breach and stale-lead items.
	 *
	 * @param array<int, array<string, mixed>> $items Existing items.
	 * @return array<int, array<string, mixed>>
	 */
	public function items( array $items ): array {
		if ( ! Permissions::can_access() ) {
			return $items;
		}

		$scope = Permissions::can_view_all() ? 'all' : 'own';
		$leads = $this->leads->all( array( 'scope' => $scope, 'user_id' => get_current_user_id() ) );
		$now   = current_time( 'mysql' );

		foreach ( $leads as $lead ) {
			if ( ! Permissions::can_view( $lead ) ) {
				continue;
			}

			$name = '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' );
			$url  = Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id;

			if ( 'new' === $lead->status && ! empty( $lead->sla_due_at ) && $lead->sla_due_at < $now ) {
				$items[] = array(
					'icon'    => 'dashicons-clock',
					'title'   => $name,
					'meta'    => __( 'First response is overdue against the SLA.', 'mbd-crm' ),
					'chip'    => __( 'SLA breach', 'mbd-crm' ),
					'variant' => 'danger',
					'url'     => $url,
				);
			}

			if ( (int) ( $lead->stale_flag ?? 0 ) === 1 ) {
				$items[] = array(
					'icon'    => 'dashicons-warning',
					'title'   => $name,
					'meta'    => '' !== (string) ( $lead->stale_reason ?? '' ) ? (string) $lead->stale_reason : __( 'No recent activity.', 'mbd-crm' ),
					'chip'    => __( 'Stale', 'mbd-crm' ),
					'variant' => 'warning',
					'url'     => $url,
				);
			}
		}

		return $items;
	}
}
