<?php
/**
 * Front-end routing for the CRM application.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Frontend\Screens;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the /crm route (and its /crm/{screen} sub-routes) and renders
 * the CRM application shell for each matching request.
 */
class Router {

	/**
	 * Base path segment for the operational route.
	 */
	public const ROUTE_BASE = 'crm';

	/**
	 * Query var that flags a CRM request.
	 */
	public const QUERY_VAR = 'mbd_crm_route';

	/**
	 * Query var carrying the requested screen slug.
	 */
	public const SCREEN_VAR = 'mbd_crm_screen';

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
	 * Register the rewrite rules that power the /crm app.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		$base = preg_quote( self::ROUTE_BASE, '#' );

		add_rewrite_rule(
			'^' . $base . '/([a-z0-9-]+)/?$',
			'index.php?' . self::QUERY_VAR . '=1&' . self::SCREEN_VAR . '=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Make the CRM query vars available to WP_Query.
	 *
	 * @param string[] $vars Existing public query vars.
	 * @return string[]
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::SCREEN_VAR;

		return $vars;
	}

	/**
	 * Build the absolute URL for a screen.
	 *
	 * @param string $slug Screen slug.
	 * @return string
	 */
	public static function screen_url( string $slug ): string {
		if ( Screens::DEFAULT_SLUG === $slug ) {
			return home_url( '/' . self::ROUTE_BASE );
		}

		return home_url( '/' . self::ROUTE_BASE . '/' . $slug );
	}

	/**
	 * Dispatch a CRM request when the route matches.
	 *
	 * Anonymous visitors are sent to the login screen. Authenticated users
	 * always receive the app shell; the content area resolves to the
	 * requested screen, a blocked panel, or a not-found panel.
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

		nocache_headers();

		$requested = get_query_var( self::SCREEN_VAR );
		$requested = is_string( $requested ) && '' !== $requested
			? sanitize_key( $requested )
			: Screens::DEFAULT_SLUG;

		$meta    = Screens::get( $requested );
		$view    = new View();
		$content = '';

		if ( null === $meta ) {
			status_header( 404 );
			$title   = __( 'Not found', 'mbd-crm' );
			$content = Components::error_state(
				__( 'Screen not found', 'mbd-crm' ),
				__( 'The screen you requested does not exist. Use the navigation to continue.', 'mbd-crm' )
			);
		} elseif ( ! Screens::user_can( $requested ) ) {
			status_header( 403 );
			$title   = $meta['label'];
			$content = Components::blocked(
				__( 'You do not have permission to view this screen. Contact an administrator if you believe this is a mistake.', 'mbd-crm' )
			);
		} else {
			status_header( 200 );
			$title   = $meta['label'];
			$content = $view->capture( 'crm/screens/' . $requested, array( 'screen' => $meta ) );

			if ( '' === $content ) {
				$content = Components::empty_state( $meta['label'], __( 'Nothing here yet.', 'mbd-crm' ) );
			}
		}

		$user = wp_get_current_user();

		$view->render(
			'crm/layout',
			array(
				'app_name'     => __( 'MBD CRM', 'mbd-crm' ),
				'title'        => $title,
				'current_slug' => $requested,
				'current'      => $meta,
				'nav'          => $this->build_nav( $requested ),
				'content'      => $content,
				'user_name'    => $user->display_name,
			)
		);

		exit;
	}

	/**
	 * Build the navigation list from the screens the user may access.
	 *
	 * @param string $current_slug Slug of the active screen.
	 * @return array<int, array{slug: string, label: string, icon: string, url: string, active: bool}>
	 */
	private function build_nav( string $current_slug ): array {
		$nav = array();

		foreach ( Screens::visible() as $slug => $meta ) {
			$nav[] = array(
				'slug'   => $slug,
				'label'  => $meta['label'],
				'icon'   => $meta['icon'],
				'url'    => self::screen_url( $slug ),
				'active' => $slug === $current_slug,
			);
		}

		return $nav;
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
