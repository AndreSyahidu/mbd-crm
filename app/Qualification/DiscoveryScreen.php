<?php
/**
 * Discovery screen: leads that have cleared qualification.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Qualification;

use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Discovery screen, which only surfaces qualified leads —
 * reinforcing that discovery is blocked until a lead is qualified.
 */
class DiscoveryScreen {

	/**
	 * Template renderer.
	 *
	 * @var View
	 */
	private View $view;

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
		$this->view  = new View();
		$this->leads = new LeadRepository();
	}

	/**
	 * Provide the discovery screen content.
	 *
	 * @param string|null $content Existing content.
	 * @param string      $slug    Active screen slug.
	 * @param array|null  $meta    Screen meta.
	 * @return string|null
	 */
	public function maybe_render( $content, string $slug, $meta ) {
		unset( $meta );

		if ( 'discovery' !== $slug ) {
			return $content;
		}

		$scope = Permissions::can_view_all() ? 'all' : 'own';
		$leads = $this->leads->by_qualification(
			'qualified',
			array(
				'scope'   => $scope,
				'user_id' => get_current_user_id(),
			)
		);

		$rows = array();
		foreach ( $leads as $lead ) {
			$rows[] = array(
				'name' => '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ),
				'url'  => Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id,
			);
		}

		return $this->view->capture( 'crm/discovery/index', array( 'rows' => $rows ) );
	}
}
