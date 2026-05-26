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

	delete_option( MBD_CRM_UNINSTALL_OPTION );

	// Placeholder: future data (custom tables, post meta, scheduled events)
	// should be removed here as the CRM grows.
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
