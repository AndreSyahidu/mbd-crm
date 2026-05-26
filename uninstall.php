<?php
/**
 * Uninstall routine for MBD CRM.
 *
 * Runs only when the plugin is deleted from the WordPress admin. Data is
 * removed only when the administrator opted in via the "Remove data on
 * uninstall" setting, otherwise everything is preserved.
 *
 * @package MBD\CRM
 */

// Bail unless WordPress invoked this file during an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Option key holding plugin settings. Kept in sync with
 * MBD\CRM\Activator::OPTION_KEY (the autoloader is not available here).
 */
const MBD_CRM_UNINSTALL_OPTION = 'mbd_crm_settings';

/**
 * Perform the safe, opt-in cleanup of plugin data.
 *
 * @return void
 */
function mbd_crm_uninstall() {
	$settings = get_option( MBD_CRM_UNINSTALL_OPTION );

	// Respect the opt-in: do nothing unless the admin asked to remove data.
	if ( ! is_array( $settings ) || empty( $settings['remove_data'] ) ) {
		return;
	}

	global $wpdb;

	delete_option( MBD_CRM_UNINSTALL_OPTION );

	// Drop the Lead Intake module tables. Names are built from the trusted
	// table prefix, not user input.
	$mbd_crm_tables = array(
		'mbd_crm_leads',
		'mbd_crm_tasks',
		'mbd_crm_audit',
		'mbd_crm_qualifications',
		'mbd_crm_followups',
		'mbd_crm_promises',
		'mbd_crm_discoveries',
		'mbd_crm_deposits',
		'mbd_crm_plannings',
		'mbd_crm_deliverables',
		'mbd_crm_revisions',
		'mbd_crm_approvals',
		'mbd_crm_closings',
	);
	foreach ( $mbd_crm_tables as $mbd_crm_table ) {
		$table = $wpdb->prefix . $mbd_crm_table;
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	// Remove the CRM roles created on activation.
	foreach ( array( 'mbd_crm_owner', 'mbd_crm_sales', 'mbd_crm_viewer', 'mbd_crm_finance' ) as $mbd_crm_role ) {
		remove_role( $mbd_crm_role );
	}

	// Revoke lead capabilities from administrators.
	$mbd_crm_admin = get_role( 'administrator' );
	if ( $mbd_crm_admin ) {
		$mbd_crm_caps = array(
			'mbd_crm_access_leads',
			'mbd_crm_create_leads',
			'mbd_crm_edit_leads',
			'mbd_crm_edit_others_leads',
			'mbd_crm_view_all_leads',
			'mbd_crm_assign_leads',
			'mbd_crm_verify_deposits',
			'mbd_crm_override_deposit',
			'mbd_crm_approve_closing',
		);
		foreach ( $mbd_crm_caps as $mbd_crm_cap ) {
			$mbd_crm_admin->remove_cap( $mbd_crm_cap );
		}
	}
}

if ( is_multisite() ) {
	$mbd_crm_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $mbd_crm_site_ids as $mbd_crm_site_id ) {
		switch_to_blog( (int) $mbd_crm_site_id );
		mbd_crm_uninstall();
		restore_current_blog();
	}
} else {
	mbd_crm_uninstall();
}
