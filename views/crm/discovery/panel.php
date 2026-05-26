<?php
/**
 * Discovery panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object             $lead        Lead row.
 * @var bool               $qualified   Whether the lead is qualified.
 * @var array<int, object> $sessions    Discovery sessions (newest first).
 * @var bool               $can_edit    Whether the user may manage discovery.
 * @var string             $nonce_field Pre-rendered nonce field.
 * @var string             $form_action Form post URL.
 * @var string             $notice      Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Discovery\Options;

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Discovery', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( ! $qualified ) : ?>
		<?php
		echo Components::notice( __( 'Discovery requires a qualified lead. Qualify the lead first.', 'mbd-crm' ), 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	<?php else : ?>
		<?php if ( $can_edit ) : ?>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="schedule_discovery" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label for="mbd-disc-type"><?php esc_html_e( 'Type', 'mbd-crm' ); ?></label>
				<select id="mbd-disc-type" name="type">
					<?php foreach ( Options::types() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="mbd-disc-when"><?php esc_html_e( 'Schedule date & time', 'mbd-crm' ); ?></label>
				<input type="datetime-local" id="mbd-disc-when" name="scheduled_at" />
				<button type="submit" class="mbd-btn mbd-btn--primary">
					<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Schedule discovery', 'mbd-crm' ); ?>
				</button>
			</form>
		<?php endif; ?>

		<h4 class="mbd-subhead"><?php esc_html_e( 'Sessions', 'mbd-crm' ); ?></h4>
		<?php if ( empty( $sessions ) ) : ?>
			<p class="mbd-field__hint"><?php esc_html_e( 'No discovery sessions yet.', 'mbd-crm' ); ?></p>
		<?php else : ?>
			<?php foreach ( $sessions as $s ) : ?>
				<div class="mbd-disc">
					<div class="mbd-disc__head">
						<strong><?php echo esc_html( Options::type_label( $s->type ) ); ?></strong>
						<?php echo Components::chip( Options::status_label( $s->status ), Options::status_variant( $s->status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<span class="mbd-timeline__meta"><?php echo esc_html( $s->scheduled_at ? $s->scheduled_at : '—' ); ?></span>

					<?php if ( 'completed' === $s->status ) : ?>
						<?php if ( '' !== trim( (string) $s->requirement_summary ) ) : ?>
							<p class="mbd-disc__field"><strong><?php esc_html_e( 'Requirement:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $s->requirement_summary ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) $s->survey_summary ) ) : ?>
							<p class="mbd-disc__field"><strong><?php esc_html_e( 'Survey:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $s->survey_summary ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) $s->pain_points ) ) : ?>
							<p class="mbd-disc__field"><strong><?php esc_html_e( 'Pain points:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $s->pain_points ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) $s->client_expectation ) ) : ?>
							<p class="mbd-disc__field"><strong><?php esc_html_e( 'Expectation:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $s->client_expectation ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) $s->recommended_next_step ) ) : ?>
							<p class="mbd-disc__field"><strong><?php esc_html_e( 'Next step:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $s->recommended_next_step ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== (string) $s->attachment_url ) : ?>
							<p class="mbd-disc__field"><a href="<?php echo esc_url( $s->attachment_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View attachment', 'mbd-crm' ); ?></a></p>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ( $can_edit && 'scheduled' === $s->status ) : ?>
						<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>" enctype="multipart/form-data">
							<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<input type="hidden" name="mbd_action" value="complete_discovery" />
							<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
							<input type="hidden" name="discovery_id" value="<?php echo (int) $s->id; ?>" />
							<label><?php esc_html_e( 'Requirement summary', 'mbd-crm' ); ?></label>
							<textarea name="requirement_summary" rows="2"></textarea>
							<label><?php esc_html_e( 'Survey summary', 'mbd-crm' ); ?></label>
							<textarea name="survey_summary" rows="2"></textarea>
							<label><?php esc_html_e( 'Pain points', 'mbd-crm' ); ?></label>
							<textarea name="pain_points" rows="2"></textarea>
							<label><?php esc_html_e( 'Client expectation', 'mbd-crm' ); ?></label>
							<textarea name="client_expectation" rows="2"></textarea>
							<label><?php esc_html_e( 'Recommended next step', 'mbd-crm' ); ?></label>
							<textarea name="recommended_next_step" rows="2"></textarea>
							<label><?php esc_html_e( 'Attach document/photo', 'mbd-crm' ); ?></label>
							<input type="file" name="attachment" accept="image/*,application/pdf" />
							<button type="submit" class="mbd-btn mbd-btn--primary"><?php esc_html_e( 'Complete discovery', 'mbd-crm' ); ?></button>
						</form>

						<form class="mbd-promise__form" method="post" action="<?php echo esc_url( $form_action ); ?>">
							<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<input type="hidden" name="mbd_action" value="reschedule_discovery" />
							<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
							<input type="hidden" name="discovery_id" value="<?php echo (int) $s->id; ?>" />
							<input type="datetime-local" name="scheduled_at" />
							<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Reschedule', 'mbd-crm' ); ?></button>
						</form>

						<form method="post" action="<?php echo esc_url( $form_action ); ?>">
							<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<input type="hidden" name="mbd_action" value="cancel_discovery" />
							<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
							<input type="hidden" name="discovery_id" value="<?php echo (int) $s->id; ?>" />
							<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Cancel', 'mbd-crm' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	<?php endif; ?>
</section>
