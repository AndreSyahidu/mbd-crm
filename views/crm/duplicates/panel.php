<?php
/**
 * Possible-duplicates panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var array<int, object> $candidates Possible duplicate leads.
 * @var bool               $can_merge  Whether the user may merge.
 * @var string             $review_url Duplicate review screen URL.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Router;

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel mbd-panel--warn">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Possible duplicates', 'mbd-crm' ); ?></h3>

	<?php
	echo Components::notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		sprintf(
			/* translators: %d: number of possible duplicates. */
			_n( '%d possible duplicate found. Review before working this lead.', '%d possible duplicates found. Review before working this lead.', count( $candidates ), 'mbd-crm' ),
			count( $candidates )
		),
		'warning'
	);
	?>

	<ul class="mbd-list">
		<?php foreach ( $candidates as $c ) : ?>
			<li class="mbd-list__item">
				<a class="mbd-table__primary" href="<?php echo esc_url( Router::screen_url( 'leads' ) . '?lead=' . (int) $c->id ); ?>">
					<?php echo esc_html( '' !== $c->name ? $c->name : __( '(no name)', 'mbd-crm' ) ); ?>
				</a>
				<span class="mbd-timeline__meta"><?php echo esc_html( $c->match_reason ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( $can_merge ) : ?>
		<a class="mbd-btn mbd-btn--small" href="<?php echo esc_url( $review_url ); ?>"><?php esc_html_e( 'Open duplicate review', 'mbd-crm' ); ?></a>
	<?php endif; ?>
</section>
