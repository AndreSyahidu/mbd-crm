<?php
/**
 * Asset registration and enqueueing.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Registers front-end and admin assets for the plugin.
 */
class Assets {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Enqueue assets for the front-end CRM route.
	 *
	 * Only loads on the /crm request to keep other pages lean.
	 *
	 * @return void
	 */
	public function enqueue_frontend(): void {
		if ( ! get_query_var( Router::QUERY_VAR ) ) {
			return;
		}

		wp_enqueue_style(
			'mbd-crm-app',
			MBD_CRM_URL . 'assets/css/app.css',
			array( 'dashicons' ),
			MBD_CRM_VERSION
		);

		wp_enqueue_script(
			'mbd-crm-app',
			MBD_CRM_URL . 'assets/js/app.js',
			array(),
			MBD_CRM_VERSION,
			true
		);
	}

	/**
	 * Enqueue assets for the admin settings screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin( string $hook_suffix ): void {
		if ( 'toplevel_page_' . MBD_CRM_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'mbd-crm-admin',
			MBD_CRM_URL . 'assets/css/admin.css',
			array(),
			MBD_CRM_VERSION
		);
	}
}
