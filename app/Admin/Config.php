<?php
/**
 * Central configuration read-layer.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Admin;

use MBD\CRM\Activator;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the admin-tunable business rules. Stored in the
 * shared {@see Activator::OPTION_KEY} option and fed to the consuming modules
 * through their existing filters, so behaviour is identical to the hard-coded
 * defaults until an administrator changes a value.
 */
class Config {

	public const SLA_HOURS          = 'sla_hours';
	public const DISCOUNT_THRESHOLD = 'discount_threshold';
	public const SERVICE_AREAS      = 'service_areas';
	public const REMINDERS_ENABLED  = 'reminders_enabled';

	/**
	 * Read the stored settings array.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		return (array) get_option( Activator::OPTION_KEY, array() );
	}

	/**
	 * Whether a configuration key has been explicitly set.
	 *
	 * @param string $key Config key.
	 * @return bool
	 */
	public static function has( string $key ): bool {
		return array_key_exists( $key, self::all() );
	}

	/**
	 * Read a single configuration value.
	 *
	 * @param string $key     Config key.
	 * @param mixed  $default Default when unset.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Wire the configured values into the modules' tuning filters. Each filter
	 * only overrides the incoming default when the value has been set, so an
	 * un-configured install keeps its built-in defaults.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter(
			'mbd_crm_sla_hours',
			static function ( $hours ) {
				$value = (int) self::get( self::SLA_HOURS, 0 );

				return $value > 0 ? $value : $hours;
			}
		);

		add_filter(
			'mbd_crm_discount_threshold',
			static function ( $threshold ) {
				return self::has( self::DISCOUNT_THRESHOLD ) ? (float) self::get( self::DISCOUNT_THRESHOLD ) : $threshold;
			}
		);

		add_filter(
			'mbd_crm_service_areas',
			static function ( $areas ) {
				return self::has( self::SERVICE_AREAS ) ? (array) self::get( self::SERVICE_AREAS, array() ) : $areas;
			}
		);

		add_filter(
			'mbd_crm_reminders_enabled',
			static function ( $enabled ) {
				return self::has( self::REMINDERS_ENABLED ) ? (bool) self::get( self::REMINDERS_ENABLED ) : $enabled;
			}
		);
	}
}
