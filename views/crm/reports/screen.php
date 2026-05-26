<?php
/**
 * Reports & analytics screen.
 *
 * @package MBD\CRM
 *
 * @var \MBD\CRM\Dashboard\Period $period      Active reporting period.
 * @var array<string,string>      $periods     Period presets (key => label).
 * @var string                    $period_base Base reports URL.
 * @var array<int, array{stage:string,count:int,from_prev:int,of_top:int}> $funnel Funnel rows.
 * @var array<string,string>      $win_loss    Win/loss summary (label => value).
 * @var array<string,int>         $lost        Lost-reason breakdown.
 * @var array<int, array{owner:string,leads:int,qualified:int,won:int,win_rate:int,value:float}> $owners Sales performance.
 * @var array<int, array{source:string,leads:int,won:int,conversion:int}> $sources Source effectiveness.
 * @var string                    $export_base Base export URL (with nonce).
 */

use MBD\CRM\Dashboard\Formulas;

defined( 'ABSPATH' ) || exit;

$mbd_funnel_top = ! empty( $funnel ) ? max( 1, (int) $funnel[0]['count'] ) : 1;
?>
<div class="mbd-page">
	<div class="mbd-dash-toolbar">
		<nav class="mbd-periods" aria-label="<?php esc_attr_e( 'Reporting period', 'mbd-crm' ); ?>">
			<?php foreach ( $periods as $pkey => $plabel ) : ?>
				<a
					class="mbd-period<?php echo $pkey === $period->key ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( $period_base . '?period=' . $pkey ); ?>"
				><?php echo esc_html( $plabel ); ?></a>
			<?php endforeach; ?>
		</nav>
		<span class="mbd-period__range"><?php echo esc_html( $period->range_label() ); ?></span>
	</div>

	<div class="mbd-kpis">
		<?php foreach ( $win_loss as $label => $value ) : ?>
			<div class="mbd-kpi">
				<span class="mbd-kpi__value"><?php echo esc_html( $value ); ?></span>
				<span class="mbd-kpi__label"><?php echo esc_html( $label ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<p class="mbd-report__actions">
		<a class="mbd-btn mbd-btn--small" href="<?php echo esc_url( $export_base . '&export=winloss' ); ?>"><?php esc_html_e( 'Export win/loss CSV', 'mbd-crm' ); ?></a>
	</p>

	<div class="mbd-dash-grid">
		<section class="mbd-panel">
			<div class="mbd-panel__head">
				<h2 class="mbd-panel__title"><?php esc_html_e( 'Conversion funnel', 'mbd-crm' ); ?></h2>
				<a class="mbd-btn mbd-btn--small" href="<?php echo esc_url( $export_base . '&export=funnel' ); ?>"><?php esc_html_e( 'CSV', 'mbd-crm' ); ?></a>
			</div>
			<table class="mbd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Stage', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Count', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'From prev.', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Of top', 'mbd-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $funnel as $row ) : ?>
						<tr>
							<td>
								<span class="mbd-funnel__label"><?php echo esc_html( $row['stage'] ); ?></span>
								<span class="mbd-funnel__bar" style="width: <?php echo (int) round( ( $row['count'] / $mbd_funnel_top ) * 100 ); ?>%"></span>
							</td>
							<td><strong><?php echo (int) $row['count']; ?></strong></td>
							<td><?php echo esc_html( Formulas::pct( (int) $row['from_prev'] ) ); ?></td>
							<td><?php echo esc_html( Formulas::pct( (int) $row['of_top'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>

		<section class="mbd-panel">
			<h2 class="mbd-panel__title"><?php esc_html_e( 'Lost reasons', 'mbd-crm' ); ?></h2>
			<?php if ( empty( $lost ) ) : ?>
				<p class="mbd-field__hint"><?php esc_html_e( 'No lost deals in this period.', 'mbd-crm' ); ?></p>
			<?php else : ?>
				<ul class="mbd-list">
					<?php foreach ( $lost as $reason => $count ) : ?>
						<li class="mbd-list__item"><span><?php echo esc_html( $reason ); ?></span><strong><?php echo (int) $count; ?></strong></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>

	<section class="mbd-panel">
		<div class="mbd-panel__head">
			<h2 class="mbd-panel__title"><?php esc_html_e( 'Sales performance', 'mbd-crm' ); ?></h2>
			<a class="mbd-btn mbd-btn--small" href="<?php echo esc_url( $export_base . '&export=owners' ); ?>"><?php esc_html_e( 'CSV', 'mbd-crm' ); ?></a>
		</div>
		<?php if ( empty( $owners ) ) : ?>
			<p class="mbd-field__hint"><?php esc_html_e( 'No leads in this period.', 'mbd-crm' ); ?></p>
		<?php else : ?>
			<table class="mbd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Owner', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Leads', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Qualified', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Won', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Win rate', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Won value', 'mbd-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $owners as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['owner'] ); ?></td>
							<td><?php echo (int) $row['leads']; ?></td>
							<td><?php echo (int) $row['qualified']; ?></td>
							<td><strong><?php echo (int) $row['won']; ?></strong></td>
							<td><?php echo esc_html( Formulas::pct( (int) $row['win_rate'] ) ); ?></td>
							<td><?php echo esc_html( Formulas::idr( (float) $row['value'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<section class="mbd-panel">
		<div class="mbd-panel__head">
			<h2 class="mbd-panel__title"><?php esc_html_e( 'Source effectiveness', 'mbd-crm' ); ?></h2>
			<a class="mbd-btn mbd-btn--small" href="<?php echo esc_url( $export_base . '&export=sources' ); ?>"><?php esc_html_e( 'CSV', 'mbd-crm' ); ?></a>
		</div>
		<?php if ( empty( $sources ) ) : ?>
			<p class="mbd-field__hint"><?php esc_html_e( 'No leads in this period.', 'mbd-crm' ); ?></p>
		<?php else : ?>
			<table class="mbd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Leads', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Won', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Conversion', 'mbd-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sources as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['source'] ); ?></td>
							<td><?php echo (int) $row['leads']; ?></td>
							<td><strong><?php echo (int) $row['won']; ?></strong></td>
							<td><?php echo esc_html( Formulas::pct( (int) $row['conversion'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>
</div>
