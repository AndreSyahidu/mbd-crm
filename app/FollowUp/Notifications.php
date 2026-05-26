<?php
/**
 * Notifications screen: aggregated alerts from every module.
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
 * Renders the Notifications screen by collecting items from the
 * `mbd_crm_notifications` filter, and contributes due-promise items.
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
	 * Register the screen handler and the promise provider.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_screen_content', array( $this, 'maybe_render' ), 10, 3 );
		add_filter( 'mbd_crm_notifications', array( $this, 'promise_items' ) );
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

		/**
		 * Collect notification items for the current user.
		 *
		 * @param array<int, array<string, mixed>> $items Notification items.
		 */
		$items = apply_filters( 'mbd_crm_notifications', array() );

		return $this->view->capture( 'crm/notifications/index', array( 'items' => $items ) );
	}

	/**
	 * Contribute due / overdue open promise items.
	 *
	 * @param array<int, array<string, mixed>> $items Existing items.
	 * @return array<int, array<string, mixed>>
	 */
	public function promise_items( array $items ): array {
		$today = current_time( 'Y-m-d' );

		foreach ( $this->promises->due( $today ) as $promise ) {
			$lead = $this->leads->find( (int) $promise->lead_id );
			if ( ! $lead || ! Permissions::can_view( $lead ) ) {
				continue;
			}

			$overdue  = $promise->due_date < $today;
			$items[] = array(
				'icon'    => 'dashicons-clipboard',
				'title'   => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
				'meta'    => (string) $promise->description,
				'chip'    => sprintf( /* translators: %s: due date. */ __( 'Promise due %s', 'mbd-crm' ), (string) $promise->due_date ),
				'variant' => $overdue ? 'danger' : 'warning',
				'url'     => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
			);
		}

		return $items;
	}
}
