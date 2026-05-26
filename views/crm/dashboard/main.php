<?php
/**
 * Role-aware KPI dashboard.
 *
 * @package MBD\CRM
 *
 * @var string              $view       Active view key.
 * @var array<string,string> $tabs      Available views (key => label).
 * @var array<int, array{label:string,value:string}> $kpis KPI cards.
 * @var array<string,int>   $by_source  Leads by source.
 * @var array<string,int>   $funnel      Funnel stage counts.
 * @var string              $bottleneck Funnel bottleneck stage.
 * @var array<string,int>   $lost        Lost-reason breakdown.
 * @var \MBD\CRM\Dashboard\Period $period Active reporting period.
 * @var array<string,string> $periods    Period presets (key => label).
 * @var string              $period_base Base dashboard URL.
 * @var int                 $incomplete  Leads missing a follow-up timestamp.
 * @var array<string,string> $formulas   Metric => formula text.
 * @var string              $widgets     Extra widget HTML (overdue follow-ups).
 */

use MBD\CRM\Router;

defined( 'ABSPATH' ) || exit;

$mbd_funnel_max = $funnel ? max( 1, max( $funnel ) ) : 1;
?>
<div class="mbd-page">
	<?php if ( count( $tabs ) > 1 ) : ?>
		<nav class="mbd-tabs" aria-label="<?php esc_attr_e( 'Dashboards', 'mbd-crm' ); ?>">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a
					class="mbd-tab<?php echo $key === $view ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( $period_base . '?view=' . $key . '&period=' . $period->key ); ?>"
				><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<div class="mbd-dash-toolbar">
		<nav class="mbd-periods" aria-label="<?php esc_attr_e( 'Reporting period', 'mbd-crm' ); ?>">
			<?php foreach ( $periods as $pkey => $plabel ) : ?>
				<a
					class="mbd-period<?php echo $pkey === $period->key ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( $period_base . '?view=' . $view . '&period=' . $pkey ); ?>"
				><?php echo esc_html( $plabel ); ?></a>
			<?php endforeach; ?>
		</nav>
		<span class="mbd-period__range"><?php echo esc_html( $period->range_label() ); ?></span>
	</div>

	<?php if ( $incomplete > 0 ) : ?>
		<p class="mbd-notice mbd-notice--warning" role="status">
			<?php
			printf(
				/* translators: %d: number of leads with no follow-up timestamp. */
				esc_html__( 'Data may be incomplete: %d lead(s) have no follow-up activity, so response-time metrics exclude them.', 'mbd-crm' ),
				(int) $incomplete
			);
			?>
		</p>
	<?php endif; ?>

	<div class="mbd-kpis">
		<?php foreach ( $kpis as $kpi ) : ?>
			<div class="mbd-kpi">
				<span class="mbd-kpi__value"><?php echo esc_html( $kpi['value'] ); ?></span>
				<span class="mbd-kpi__label"><?php echo esc_html( $kpi['label'] ); ?></span>
				<?php if ( ! empty( $kpi['note'] ) ) : ?>
					<span class="mbd-kpi__note"><?php echo esc_html( $kpi['note'] ); ?></span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="mbd-dash-grid">
		<section class="mbd-panel">
			<h2 class="mbd-panel__title"><?php esc_html_e( 'Funnel', 'mbd-crm' ); ?></h2>
			<?php if ( '' !== $bottleneck ) : ?>
				<p class="mbd-notice mbd-notice--warning" role="status">
					<?php
					printf(
						/* translators: %s: stage name. */
						esc_html__( 'Bottleneck: biggest drop into %s.', 'mbd-crm' ),
						esc_html( $bottleneck )
					);
					?>
				</p>
			<?php endif; ?>
			<ul class="mbd-funnel">
				<?php foreach ( $funnel as $label => $count ) : ?>
					<li class="mbd-funnel__row">
						<span class="mbd-funnel__label"><?php echo esc_html( $label ); ?></span>
						<span class="mbd-funnel__bar" style="width: <?php echo (int) round( ( $count / $mbd_funnel_max ) * 100 ); ?>%"></span>
						<span class="mbd-funnel__count"><?php echo (int) $count; ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="mbd-panel">
			<h2 class="mbd-panel__title"><?php esc_html_e( 'Leads by source', 'mbd-crm' ); ?></h2>
			<?php if ( empty( $by_source ) ) : ?>
				<p class="mbd-field__hint"><?php esc_html_e( 'No leads yet.', 'mbd-crm' ); ?></p>
			<?php else : ?>
				<ul class="mbd-list">
					<?php foreach ( $by_source as $label => $count ) : ?>
						<li class="mbd-list__item"><span><?php echo esc_html( $label ); ?></span><strong><?php echo (int) $count; ?></strong></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<section class="mbd-panel">
			<h2 class="mbd-panel__title"><?php esc_html_e( 'Lost reasons', 'mbd-crm' ); ?></h2>
			<?php if ( empty( $lost ) ) : ?>
				<p class="mbd-field__hint"><?php esc_html_e( 'No lost deals.', 'mbd-crm' ); ?></p>
			<?php else : ?>
				<ul class="mbd-list">
					<?php foreach ( $lost as $reason => $count ) : ?>
						<li class="mbd-list__item"><span><?php echo esc_html( $reason ); ?></span><strong><?php echo (int) $count; ?></strong></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>

	<?php echo $widgets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<details class="mbd-formulas">
		<summary><?php esc_html_e( 'How these metrics are calculated', 'mbd-crm' ); ?></summary>
		<dl class="mbd-dl">
			<?php foreach ( $formulas as $name => $formula ) : ?>
				<dt><?php echo esc_html( $name ); ?></dt>
				<dd><?php echo esc_html( $formula ); ?></dd>
			<?php endforeach; ?>
		</dl>
	</details>
</div>
