<?php
/**
 * Lead detail view.
 *
 * @package MBD\CRM
 *
 * @var object               $lead          Lead row.
 * @var array<int, string>   $names         User id => display name.
 * @var bool                 $can_edit      Whether the user may edit this lead.
 * @var array{label:string,variant:string} $sla SLA display.
 * @var string               $whatsapp_link WhatsApp deep link (may be empty).
 * @var array<int, object>   $audit         Audit entries (newest first).
 * @var array<int, object>   $tasks         Tasks for this lead.
 * @var string               $edit_url      Edit form URL.
 * @var string               $list_url      Back-to-list URL.
 * @var string               $notice        Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\Options;

defined( 'ABSPATH' ) || exit;

$mbd_assignee = $names[ (int) $lead->assigned_to ] ?? __( 'Unassigned', 'mbd-crm' );
$mbd_creator  = $names[ (int) $lead->created_by ] ?? __( 'Unknown', 'mbd-crm' );
$mbd_budget   = ( null !== $lead->estimated_budget && '' !== $lead->estimated_budget )
	? number_format_i18n( (float) $lead->estimated_budget )
	: '';
?>
<div class="mbd-page">
	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="mbd-toolbar">
		<p class="mbd-page__lead">
			<a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to leads', 'mbd-crm' ); ?></a>
		</p>
		<?php if ( $can_edit ) : ?>
			<a class="mbd-btn mbd-btn--primary" href="<?php echo esc_url( $edit_url ); ?>">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<?php esc_html_e( 'Edit lead', 'mbd-crm' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<div class="mbd-detail__head">
		<h2 class="mbd-detail__name"><?php echo esc_html( '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ) ); ?></h2>
		<div class="mbd-chip-row">
			<?php
			echo Components::chip( Options::label( 'statuses', $lead->status ), Options::status_variant( $lead->status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::chip( Options::label( 'qualities', $lead->quality ), Options::quality_variant( $lead->quality ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::chip( sprintf( /* translators: %s: SLA state. */ __( 'SLA: %s', 'mbd-crm' ), $sla['label'] ), $sla['variant'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
	</div>

	<div class="mbd-detail__grid">
		<section class="mbd-panel">
			<h3 class="mbd-panel__title"><?php esc_html_e( 'Details', 'mbd-crm' ); ?></h3>
			<dl class="mbd-dl">
				<dt><?php esc_html_e( 'Source', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( '' !== $lead->source ? Options::label( 'sources', $lead->source ) : '—' ); ?></dd>

				<dt><?php esc_html_e( 'Project type', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( '' !== $lead->project_type ? Options::label( 'project_types', $lead->project_type ) : '—' ); ?></dd>

				<dt><?php esc_html_e( 'Service type', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( '' !== $lead->service_type ? Options::label( 'service_types', $lead->service_type ) : '—' ); ?></dd>

				<dt><?php esc_html_e( 'Urgency', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( '' !== $lead->urgency ? Options::label( 'urgencies', $lead->urgency ) : '—' ); ?></dd>

				<dt><?php esc_html_e( 'Estimated budget', 'mbd-crm' ); ?></dt>
				<dd>
					<?php
					if ( '' !== $mbd_budget ) {
						echo esc_html( $mbd_budget );
					} else {
						printf(
							/* translators: %s: reason the budget is unknown. */
							esc_html__( 'Unknown — %s', 'mbd-crm' ),
							esc_html( '' !== $lead->budget_unknown_reason ? $lead->budget_unknown_reason : __( 'no reason given', 'mbd-crm' ) )
						);
					}
					?>
				</dd>

				<dt><?php esc_html_e( 'WhatsApp', 'mbd-crm' ); ?></dt>
				<dd>
					<?php if ( '' !== $whatsapp_link ) : ?>
						<a class="mbd-btn mbd-btn--whatsapp" href="<?php echo esc_url( $whatsapp_link ); ?>" target="_blank" rel="noopener noreferrer">
							<span class="dashicons dashicons-whatsapp" aria-hidden="true"></span>
							<?php esc_html_e( 'Open WhatsApp', 'mbd-crm' ); ?>
						</a>
					<?php else : ?>
						&mdash;
					<?php endif; ?>
				</dd>

				<dt><?php esc_html_e( 'Next follow-up', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( $lead->next_follow_up ? $lead->next_follow_up : '—' ); ?></dd>

				<dt><?php esc_html_e( 'Assigned to', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( $mbd_assignee ); ?></dd>

				<dt><?php esc_html_e( 'Created by', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( $mbd_creator ); ?></dd>
			</dl>

			<?php if ( '' !== trim( (string) $lead->notes ) ) : ?>
				<h3 class="mbd-panel__title"><?php esc_html_e( 'Notes', 'mbd-crm' ); ?></h3>
				<p class="mbd-detail__notes"><?php echo nl2br( esc_html( $lead->notes ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php endif; ?>
		</section>

		<div class="mbd-detail__side">
			<?php
			/**
			 * Lets modules append panels (e.g. Qualification) to the lead
			 * detail sidebar.
			 *
			 * @param string $html Accumulated panel HTML.
			 * @param object $lead Lead row.
			 */
			echo apply_filters( 'mbd_crm_lead_detail_panels', '', $lead ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>

			<section class="mbd-panel">
				<h3 class="mbd-panel__title"><?php esc_html_e( 'Tasks', 'mbd-crm' ); ?></h3>
				<?php if ( empty( $tasks ) ) : ?>
					<p class="mbd-field__hint"><?php esc_html_e( 'No tasks yet.', 'mbd-crm' ); ?></p>
				<?php else : ?>
					<ul class="mbd-list">
						<?php foreach ( $tasks as $task ) : ?>
							<li class="mbd-list__item">
								<span><?php echo esc_html( $task->title ); ?></span>
								<?php echo Components::chip( $task->status, 'open' === $task->status ? 'warning' : 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>

			<section class="mbd-panel">
				<h3 class="mbd-panel__title"><?php esc_html_e( 'Audit trail', 'mbd-crm' ); ?></h3>
				<?php if ( empty( $audit ) ) : ?>
					<p class="mbd-field__hint"><?php esc_html_e( 'No activity recorded.', 'mbd-crm' ); ?></p>
				<?php else : ?>
					<ul class="mbd-timeline">
						<?php
						foreach ( $audit as $entry ) :
							$actor = get_userdata( (int) $entry->user_id );
							?>
							<li class="mbd-timeline__item">
								<span class="mbd-timeline__desc"><?php echo esc_html( Audit::describe( $entry ) ); ?></span>
								<span class="mbd-timeline__meta">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: user name, 2: datetime. */
											__( '%1$s · %2$s', 'mbd-crm' ),
											$actor ? $actor->display_name : __( 'System', 'mbd-crm' ),
											(string) $entry->created_at
										)
									);
									?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		</div>
	</div>
</div>
