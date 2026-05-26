<?php
/**
 * My Work dashboard.
 *
 * @package MBD\CRM
 *
 * @var array<int, array{label:string,value:string}>          $kpis  KPI cards.
 * @var array<int, array{title:string,due:string,url:string}> $tasks Open tasks.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Your daily queue: tasks, follow-ups, and assigned leads.', 'mbd-crm' ); ?></p>

	<div class="mbd-kpis">
		<?php foreach ( $kpis as $kpi ) : ?>
			<div class="mbd-kpi">
				<span class="mbd-kpi__value"><?php echo esc_html( $kpi['value'] ); ?></span>
				<span class="mbd-kpi__label"><?php echo esc_html( $kpi['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<section class="mbd-panel">
		<h2 class="mbd-panel__title"><?php esc_html_e( 'Open tasks', 'mbd-crm' ); ?></h2>
		<?php if ( empty( $tasks ) ) : ?>
			<?php
			echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				__( 'Your queue is clear', 'mbd-crm' ),
				__( 'Tasks assigned to you will appear here.', 'mbd-crm' ),
				'dashicons-clipboard'
			);
			?>
		<?php else : ?>
			<ul class="mbd-list">
				<?php foreach ( $tasks as $task ) : ?>
					<li class="mbd-list__item">
						<a class="mbd-table__primary" href="<?php echo esc_url( $task['url'] ); ?>"><?php echo esc_html( $task['title'] ); ?></a>
						<?php if ( '' !== $task['due'] ) : ?>
							<span class="mbd-timeline__meta"><?php echo esc_html( $task['due'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
</div>
