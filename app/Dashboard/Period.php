<?php
/**
 * Dashboard date-range period.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * A selectable reporting window. Boundaries are site-local MySQL datetimes
 * (or null for an open/all-time range), so all metric comparisons stay in a
 * single, consistent time basis.
 */
class Period {

	/**
	 * Period key.
	 *
	 * @var string
	 */
	public string $key;

	/**
	 * Human label.
	 *
	 * @var string
	 */
	public string $label;

	/**
	 * Inclusive start (Y-m-d H:i:s) or null for unbounded.
	 *
	 * @var string|null
	 */
	public ?string $start;

	/**
	 * Inclusive end (Y-m-d H:i:s) or null for unbounded.
	 *
	 * @var string|null
	 */
	public ?string $end;

	/**
	 * Constructor.
	 *
	 * @param string      $key   Period key.
	 * @param string      $label Label.
	 * @param string|null $start Start datetime.
	 * @param string|null $end   End datetime.
	 */
	public function __construct( string $key, string $label, ?string $start, ?string $end ) {
		$this->key   = $key;
		$this->label = $label;
		$this->start = $start;
		$this->end   = $end;
	}

	/**
	 * Available period presets (key => label).
	 *
	 * @return array<string, string>
	 */
	public static function presets(): array {
		return array(
			'all'        => __( 'All time', 'mbd-crm' ),
			'today'      => __( 'Today', 'mbd-crm' ),
			'this_week'  => __( 'This week', 'mbd-crm' ),
			'this_month' => __( 'This month', 'mbd-crm' ),
			'last_30'    => __( 'Last 30 days', 'mbd-crm' ),
		);
	}

	/**
	 * Build a period from a key (defaults to all-time).
	 *
	 * @param string $key Period key.
	 * @return Period
	 */
	public static function from_key( string $key ): Period {
		$now     = (int) current_time( 'timestamp' );
		$now_str = current_time( 'mysql' );
		$labels  = self::presets();

		switch ( $key ) {
			case 'today':
				return new self( 'today', $labels['today'], gmdate( 'Y-m-d 00:00:00', $now ), $now_str );
			case 'this_week':
				$monday = strtotime( 'monday this week', $now );
				return new self( 'this_week', $labels['this_week'], gmdate( 'Y-m-d 00:00:00', $monday ), $now_str );
			case 'this_month':
				return new self( 'this_month', $labels['this_month'], gmdate( 'Y-m-01 00:00:00', $now ), $now_str );
			case 'last_30':
				return new self( 'last_30', $labels['last_30'], gmdate( 'Y-m-d H:i:s', $now - ( 30 * DAY_IN_SECONDS ) ), $now_str );
		}

		return new self( 'all', $labels['all'], null, null );
	}

	/**
	 * Whether a MySQL datetime falls within this period.
	 *
	 * @param string|null $datetime Datetime to test.
	 * @return bool
	 */
	public function contains( ?string $datetime ): bool {
		if ( empty( $datetime ) ) {
			return false;
		}
		if ( null !== $this->start && $datetime < $this->start ) {
			return false;
		}
		if ( null !== $this->end && $datetime > $this->end ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether this is the unbounded all-time period.
	 *
	 * @return bool
	 */
	public function is_all_time(): bool {
		return null === $this->start && null === $this->end;
	}

	/**
	 * Human-readable range, e.g. "2026-05-01 → now".
	 *
	 * @return string
	 */
	public function range_label(): string {
		if ( $this->is_all_time() ) {
			return __( 'All time', 'mbd-crm' );
		}

		$start = null !== $this->start ? substr( $this->start, 0, 10 ) : '…';
		$end   = null !== $this->end ? substr( $this->end, 0, 10 ) : '…';

		return $start . ' → ' . $end;
	}
}
