<?php
/**
 * Front-end routing for the CRM application.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the /crm rewrite rule and dispatches matching requests.
 */
class Router {

	/**
	 * Query var that flags a CRM request.
	 */
	public const QUERY_VAR = 'mbd_crm_route';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'dispatch' ) );
	}

	/**
	 * Register the rewrite rule that powers the operational /crm route.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^' . MBD_CRM_SLUG . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Make the CRM query var available to WP_Query.
	 *
	 * @param string[] $vars Existing public query vars.
	 * @return string[]
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Dispatch a CRM request when the route matches.
	 *
	 * Access is limited to logged-in users; anonymous visitors are sent
	 * to the login screen and returned to /crm afterwards.
	 *
	 * @return void
	 */
	public function dispatch(): void {
		if ( ! $this->is_crm_request() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		/**
		 * Capability required to access the CRM app.
		 *
		 * @param string $capability Default capability.
		 */
		$capability = apply_filters( 'mbd_crm_access_capability', 'read' );

		if ( ! current_user_can( $capability ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access the CRM.', 'mbd-crm' ),
				esc_html__( 'Access denied', 'mbd-crm' ),
				array( 'response' => 403 )
			);
		}

		status_header( 200 );
		nocache_headers();

		( new View() )->render(
			'crm/index',
			array(
				'title' => __( 'MBD CRM', 'mbd-crm' ),
			)
		);

		exit;
	}

	/**
	 * Whether the current request targets the CRM route.
	 *
	 * @return bool
	 */
	private function is_crm_request(): bool {
		return (bool) get_query_var( self::QUERY_VAR );
	}
}
