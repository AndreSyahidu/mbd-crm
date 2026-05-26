<?php
/**
 * Dashboard widget: stuck (stale) leads.
 *
 * @package MBD\CRM
 *
 * @var array<int, array{name:string,stage:string,aging:string,reason:string,url:string}> $rows Stale leads.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h2 class="mbd-panel__title">
		<span class="dashicons dashicons-warning" aria-hidden="true"></span>
		<?php
		printf(
			/* translators: %d: number of stuck leads. */
			esc_html__( 'Stuck leads (%d)', 'mbd-crm' ),
			count( $rows )
		);
		?>
	</h2>

	<?php if ( empty( $rows ) ) : ?>
		<p class="mbd-field__hint"><?php esc_html_e( 'No stale leads. The pipeline is moving.', 'mbd-crm' ); ?></p>
	<?php else : ?>
		<ul class="mbd-list">
			<?php foreach ( $rows as $row ) : ?>
				<li class="mbd-list__item">
					<div>
						<a class="mbd-table__primary" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['name'] ); ?></a>
						<span class="mbd-timeline__meta"><?php echo esc_html( $row['stage'] . ' · ' . $row['reason'] ); ?></span>
					</div>
					<span class="mbd-chip mbd-chip--danger"><?php echo esc_html( $row['aging'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
