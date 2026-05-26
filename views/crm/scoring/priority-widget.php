<?php
/**
 * Priority queue widget (top active leads by score).
 *
 * @package MBD\CRM
 *
 * @var array<int, array{name:string,score:int,temperature:string,url:string}> $rows Top leads.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Scoring\Scorer;

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h2 class="mbd-panel__title">
		<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
		<?php esc_html_e( 'Priority queue', 'mbd-crm' ); ?>
	</h2>

	<?php if ( empty( $rows ) ) : ?>
		<p class="mbd-field__hint"><?php esc_html_e( 'No active leads to prioritise.', 'mbd-crm' ); ?></p>
	<?php else : ?>
		<ul class="mbd-list">
			<?php foreach ( $rows as $row ) : ?>
				<li class="mbd-list__item">
					<a class="mbd-table__primary" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['name'] ); ?></a>
					<span class="mbd-priority__score">
						<strong><?php echo (int) $row['score']; ?></strong>
						<?php echo Components::chip( Scorer::temperature_label( $row['temperature'] ), Scorer::temperature_variant( $row['temperature'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
