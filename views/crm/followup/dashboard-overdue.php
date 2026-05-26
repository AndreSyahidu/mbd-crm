<?php
/**
 * Dashboard widget: overdue follow-ups.
 *
 * @package MBD\CRM
 *
 * @var array<int, array{name:string,due:string,next_action:string,url:string}> $rows Overdue leads.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h2 class="mbd-panel__title">
		<span class="dashicons dashicons-clock" aria-hidden="true"></span>
		<?php esc_html_e( 'Overdue follow-ups', 'mbd-crm' ); ?>
	</h2>

	<?php if ( empty( $rows ) ) : ?>
		<p class="mbd-field__hint"><?php esc_html_e( 'Nothing overdue. Nice work.', 'mbd-crm' ); ?></p>
	<?php else : ?>
		<ul class="mbd-list">
			<?php foreach ( $rows as $row ) : ?>
				<li class="mbd-list__item">
					<div>
						<a class="mbd-table__primary" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['name'] ); ?></a>
						<?php if ( '' !== $row['next_action'] ) : ?>
							<span class="mbd-timeline__meta"><?php echo esc_html( $row['next_action'] ); ?></span>
						<?php endif; ?>
					</div>
					<span class="mbd-chip mbd-chip--danger"><?php echo esc_html( sprintf( /* translators: %s: due date. */ __( 'Due %s', 'mbd-crm' ), $row['due'] ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
