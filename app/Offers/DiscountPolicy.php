<?php
/**
 * Discount computation and the approval threshold.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Offers;

defined( 'ABSPATH' ) || exit;

/**
 * Central place for discount math and the rule that decides when an offer's
 * discount is large enough to require Owner/Admin approval before it can be
 * sent.
 */
class DiscountPolicy {

	/**
	 * Maximum discount (percent) a sales rep may send without approval.
	 *
	 * @return float
	 */
	public static function threshold(): float {
		/**
		 * Filter the discount approval threshold, expressed as a percentage.
		 *
		 * @param float $threshold Default 10.0 percent.
		 */
		return (float) apply_filters( 'mbd_crm_discount_threshold', 10.0 );
	}

	/**
	 * Resolve a base price + discount into final value and effective percent.
	 *
	 * @param float  $base  Base (list) price.
	 * @param string $type  'percent' or 'amount'.
	 * @param float  $value Discount value (percent or currency amount).
	 * @return array{final:float, percent:float, amount:float}
	 */
	public static function compute( float $base, string $type, float $value ): array {
		$base  = max( 0.0, $base );
		$value = max( 0.0, $value );

		if ( 'percent' === $type ) {
			$percent = min( 100.0, $value );
			$amount  = round( $base * ( $percent / 100 ), 2 );
		} else {
			$amount  = min( $base, $value );
			$percent = $base > 0 ? round( ( $amount / $base ) * 100, 2 ) : 0.0;
		}

		$final = round( $base - $amount, 2 );

		return array(
			'final'   => max( 0.0, $final ),
			'percent' => $percent,
			'amount'  => $amount,
		);
	}

	/**
	 * Whether a given discount percent requires approval before sending.
	 *
	 * @param float $percent Effective discount percent.
	 * @return bool
	 */
	public static function requires_approval( float $percent ): bool {
		return $percent > self::threshold();
	}
}
