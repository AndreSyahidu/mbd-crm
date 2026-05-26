<?php
/**
 * Import / Export admin page and handlers.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\IO;

use MBD\CRM\Leads\Capabilities;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Import / Export" admin subpage and processes its import
 * (POST) and export (download) requests with nonce and capability checks.
 */
class AdminPage {

	private const PAGE_SLUG    = 'mbd-crm-io';
	private const IMPORT_NONCE = 'mbd_crm_import';
	private const EXPORT_NONCE = 'mbd_crm_export';

	/**
	 * Export types that any view-all user may run.
	 */
	private const OPEN_EXPORTS = array( 'leads', 'funnel', 'planning', 'closing_forecast', 'lost_reason' );

	/**
	 * Hook the admin menu and post handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 20 );
		add_action( 'admin_post_mbd_crm_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_mbd_crm_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Add the Import / Export subpage under MBD CRM.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_submenu_page(
			MBD_CRM_SLUG,
			__( 'Import / Export', 'mbd-crm' ),
			__( 'Import / Export', 'mbd-crm' ),
			Capabilities::VIEW_ALL_LEADS,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_ALL_LEADS ) ) {
			return;
		}

		$result = get_transient( 'mbd_crm_io_' . get_current_user_id() );
		delete_transient( 'mbd_crm_io_' . get_current_user_id() );

		( new View() )->render(
			'admin/io',
			array(
				'import_action' => admin_url( 'admin-post.php' ),
				'import_nonce'  => wp_nonce_field( self::IMPORT_NONCE, '_wpnonce', true, false ),
				'export_url'    => array( $this, 'export_url' ),
				'can_import'    => current_user_can( Capabilities::CREATE_LEADS ),
				'can_audit'     => current_user_can( 'manage_options' ),
				'result'        => is_array( $result ) ? $result : null,
			)
		);
	}

	/**
	 * Build a nonced export URL for a type.
	 *
	 * @param string $type Export type.
	 * @return string
	 */
	public function export_url( string $type ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=mbd_crm_export&type=' . rawurlencode( $type ) ),
			self::EXPORT_NONCE
		);
	}

	/**
	 * Process a CSV import.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		check_admin_referer( self::IMPORT_NONCE );

		if ( ! current_user_can( Capabilities::CREATE_LEADS ) ) {
			wp_die( esc_html__( 'You are not allowed to import data.', 'mbd-crm' ), '', array( 'response' => 403 ) );
		}

		$type   = isset( $_POST['import_type'] ) ? sanitize_key( wp_unslash( $_POST['import_type'] ) ) : '';
		$parsed = Csv::read_upload( 'csv' );

		if ( is_wp_error( $parsed ) ) {
			$result = array( 'error' => $parsed->get_error_message() );
		} else {
			$result = ( new Importer() )->import( $type, $parsed[1] );
		}

		set_transient( 'mbd_crm_io_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Stream a CSV export.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		check_admin_referer( self::EXPORT_NONCE );

		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';

		// Permission: the audit log is restricted to administrators.
		if ( 'audit' === $type ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to export the audit log.', 'mbd-crm' ), '', array( 'response' => 403 ) );
			}
		} elseif ( ! in_array( $type, self::OPEN_EXPORTS, true ) || ! current_user_can( Capabilities::VIEW_ALL_LEADS ) ) {
			wp_die( esc_html__( 'You are not allowed to run this export.', 'mbd-crm' ), '', array( 'response' => 403 ) );
		}

		$rows = ( new Exporter() )->rows( $type );
		Csv::send_download( 'mbd-crm-' . $type . '-' . gmdate( 'Ymd' ) . '.csv', $rows );
	}
}
