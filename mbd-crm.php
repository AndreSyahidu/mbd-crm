<?php
/**
 * Plugin Name:       MBD CRM
 * Plugin URI:        https://github.com/andresyahidu/mbd-crm
 * Description:        Foundation for the MBD CRM application. Provides a front-end /crm route, an admin settings area, and a health check.
 * Version:           0.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            MBD
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mbd-crm
 * Domain Path:       /languages
 *
 * @package MBD\CRM
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'MBD_CRM_VERSION' ) ) {
	return;
}

define( 'MBD_CRM_VERSION', '0.2.0' );
define( 'MBD_CRM_FILE', __FILE__ );
define( 'MBD_CRM_PATH', plugin_dir_path( __FILE__ ) );
define( 'MBD_CRM_URL', plugin_dir_url( __FILE__ ) );
define( 'MBD_CRM_SLUG', 'mbd-crm' );

/**
 * PSR-4 style autoloader scoped to the MBD\CRM namespace.
 *
 * Maps the namespace prefix "MBD\CRM\" to the plugin's app/ directory.
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix   = 'MBD\\CRM\\';
		$base_dir = MBD_CRM_PATH . 'app/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative = substr( $class, $len );
		$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( \MBD\CRM\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \MBD\CRM\Deactivator::class, 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 *
 * @return \MBD\CRM\Plugin
 */
function mbd_crm() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new \MBD\CRM\Plugin();
	}

	return $plugin;
}

add_action( 'plugins_loaded', static fn() => mbd_crm()->boot() );
