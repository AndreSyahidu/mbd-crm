<?php
/**
 * Notifications screen: due promises.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\FollowUp;

use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Surfaces open promises that are due (or overdue) on the Notifications
 * screen for leads the current user may view.
 */
class Notifications {

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Promise persistence.
	 *
	 * @var PromiseRepository
	 */
	private PromiseRepository $promises;

	/**
	 * Template renderer.
	 *
	 * @var View
	 */
	private View $view;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->leads    = new LeadRepository();
		$this->promises = new PromiseRepository();
		$this->view     = new View();
	}

	/**
	 * Provide the notifications screen content.
	 *
	 * @param string|null $content Existing content.
	 * @param string      $slug    Active screen slug.
	 * @param array|null  $meta    Screen meta.
	 * @return string|null
	 */
	public function maybe_render( $content, string $slug, $meta ) {
		unset( $meta );

		if ( 'notifications' !== $slug ) {
			return $content;
		}

		$today    = current_time( 'Y-m-d' );
		$due       = $this->promises->due( $today );
		$rows      = array();

		foreach ( $due as $promise ) {
			$lead = $this->leads->find( (int) $promise->lead_id );
			if ( ! $lead || ! Permissions::can_view( $lead ) ) {
				continue;
			}

			$rows[] = array(
				'lead'        => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
				'description' => (string) $promise->description,
				'due'         => (string) $promise->due_date,
				'overdue'     => $promise->due_date < $today,
				'url'         => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
			);
		}

		return $this->view->capture( 'crm/notifications/index', array( 'rows' => $rows ) );
	}
}
