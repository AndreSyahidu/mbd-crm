<?php
/**
 * Scheduled email reminder digest.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Reminders;

use MBD\CRM\Leads\Capabilities;
use MBD\CRM\Router;

defined( 'ABSPATH' ) || exit;

/**
 * Runs a daily WP-Cron job that emails each CRM operator a digest of the
 * leads needing their attention. Delivery is opt-out via the
 * `mbd_crm_reminders_enabled` option / filter.
 */
class Scheduler {

	/**
	 * Cron hook name.
	 */
	public const HOOK = 'mbd_crm_daily_reminders';

	/**
	 * Digest builder.
	 *
	 * @var Digest
	 */
	private Digest $digest;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->digest = new Digest();
	}

	/**
	 * Hook the cron handler and self-heal the schedule.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );

		// Self-heal: ensure the event exists for installs that predate this module.
		if ( self::is_enabled() && ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	/**
	 * Whether email reminders are enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		/**
		 * Filter whether the daily email reminder runs.
		 *
		 * @param bool $enabled Default from the option (defaults to true).
		 */
		return (bool) apply_filters( 'mbd_crm_reminders_enabled', (bool) get_option( 'mbd_crm_reminders_enabled', true ) );
	}

	/**
	 * Schedule the daily event if not already scheduled.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// First run tomorrow at ~08:00 site time.
			$first = strtotime( 'tomorrow 08:00', (int) current_time( 'timestamp' ) );
			wp_schedule_event( $first, 'daily', self::HOOK );
		}
	}

	/**
	 * Clear the scheduled event.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Cron handler: email a digest to each operator with pending items.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$users = get_users(
			array(
				'capability' => Capabilities::EDIT_LEADS,
				'fields'     => array( 'ID', 'user_email', 'display_name' ),
				'number'     => 500,
			)
		);

		foreach ( $users as $user ) {
			$email = (string) ( $user->user_email ?? '' );
			if ( '' === $email || ! is_email( $email ) ) {
				continue;
			}

			$categories = $this->digest->for_user( (int) $user->ID );
			if ( empty( $categories ) ) {
				continue;
			}

			$this->send( $email, (string) $user->display_name, $categories );
		}
	}

	/**
	 * Send one digest email.
	 *
	 * @param string                                                                              $email      Recipient.
	 * @param string                                                                              $name       Recipient name.
	 * @param array<int, array{key:string, label:string, variant:string, leads:array<int, object>}> $categories Digest categories.
	 * @return void
	 */
	private function send( string $email, string $name, array $categories ): void {
		$total = 0;
		foreach ( $categories as $cat ) {
			$total += count( $cat['leads'] );
		}

		/* translators: %d: number of items. */
		$subject = sprintf( _n( 'MBD CRM: %d item needs your attention', 'MBD CRM: %d items need your attention', $total, 'mbd-crm' ), $total );

		$lines   = array();
		$lines[] = sprintf( /* translators: %s: user name. */ __( 'Hi %s,', 'mbd-crm' ), $name );
		$lines[] = '';
		$lines[] = __( 'Here is what needs your attention in the CRM:', 'mbd-crm' );

		foreach ( $categories as $cat ) {
			$lines[] = '';
			$lines[] = sprintf( '%s (%d):', $cat['label'], count( $cat['leads'] ) );
			foreach ( array_slice( $cat['leads'], 0, 10 ) as $lead ) {
				$name_l  = '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' );
				$lines[] = sprintf( '  - %s  %s', $name_l, Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id );
			}
			if ( count( $cat['leads'] ) > 10 ) {
				$lines[] = sprintf( /* translators: %d: remaining count. */ __( '  …and %d more', 'mbd-crm' ), count( $cat['leads'] ) - 10 );
			}
		}

		$lines[] = '';
		$lines[] = __( 'Open the CRM:', 'mbd-crm' ) . ' ' . Router::screen_url( 'notifications' );

		wp_mail( $email, $subject, implode( "\n", $lines ) );
	}
}
