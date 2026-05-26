<?php
/**
 * Follow-up & promise panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object              $lead        Lead row.
 * @var array<int, object>  $history     Follow-up log entries (newest first).
 * @var array<int, object>  $promises    Promise records (newest first).
 * @var bool                $can_edit    Whether the user may add entries.
 * @var string              $nonce_field Pre-rendered nonce field.
 * @var string              $form_action Form post URL.
 * @var string              $notice      Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\FollowUp\Options;

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Follow-ups', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="mbd-next">
		<div>
			<span class="mbd-next__label"><?php esc_html_e( 'Next action', 'mbd-crm' ); ?></span>
			<span class="mbd-next__value"><?php echo esc_html( '' !== $lead->next_action ? $lead->next_action : __( 'None set', 'mbd-crm' ) ); ?></span>
		</div>
		<div>
			<span class="mbd-next__label"><?php esc_html_e( 'Next follow-up', 'mbd-crm' ); ?></span>
			<span class="mbd-next__value"><?php echo esc_html( $lead->next_follow_up ? $lead->next_follow_up : '—' ); ?></span>
		</div>
	</div>

	<?php if ( $can_edit ) : ?>
		<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="log_followup" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />

			<label for="mbd-fu-channel"><?php esc_html_e( 'Channel', 'mbd-crm' ); ?></label>
			<select id="mbd-fu-channel" name="channel">
				<?php foreach ( Options::channels() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="mbd-fu-summary"><?php esc_html_e( 'Summary', 'mbd-crm' ); ?> <span class="mbd-req">*</span></label>
			<textarea id="mbd-fu-summary" name="summary" rows="2"></textarea>

			<label for="mbd-fu-result"><?php esc_html_e( 'Result', 'mbd-crm' ); ?></label>
			<input type="text" id="mbd-fu-result" name="result" />

			<label for="mbd-fu-next-action"><?php esc_html_e( 'Next action', 'mbd-crm' ); ?></label>
			<input type="text" id="mbd-fu-next-action" name="next_action" />

			<label for="mbd-fu-next-date"><?php esc_html_e( 'Next follow-up date', 'mbd-crm' ); ?></label>
			<input type="date" id="mbd-fu-next-date" name="next_follow_up" />

			<button type="submit" class="mbd-btn mbd-btn--primary">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'Log follow-up', 'mbd-crm' ); ?>
			</button>
		</form>
	<?php endif; ?>

	<h4 class="mbd-subhead"><?php esc_html_e( 'History', 'mbd-crm' ); ?></h4>
	<?php if ( empty( $history ) ) : ?>
		<p class="mbd-field__hint"><?php esc_html_e( 'No follow-ups logged yet.', 'mbd-crm' ); ?></p>
	<?php else : ?>
		<ul class="mbd-timeline">
			<?php foreach ( $history as $entry ) : ?>
				<li class="mbd-timeline__item">
					<span class="mbd-timeline__desc">
						<?php echo Components::chip( Options::channel_label( $entry->channel ), Options::channel_variant( $entry->channel ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo esc_html( $entry->summary ); ?>
					</span>
					<?php if ( '' !== trim( (string) $entry->result ) ) : ?>
						<span class="mbd-timeline__meta"><?php echo esc_html( sprintf( /* translators: %s: result. */ __( 'Result: %s', 'mbd-crm' ), $entry->result ) ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $entry->next_action ) ) : ?>
						<span class="mbd-timeline__meta"><?php echo esc_html( sprintf( /* translators: 1: action, 2: date. */ __( 'Next: %1$s (%2$s)', 'mbd-crm' ), $entry->next_action, $entry->next_follow_up ? $entry->next_follow_up : '—' ) ); ?></span>
					<?php endif; ?>
					<span class="mbd-timeline__meta"><?php echo esc_html( (string) $entry->created_at ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<h4 class="mbd-subhead"><?php esc_html_e( 'Promises', 'mbd-crm' ); ?></h4>
	<?php if ( empty( $promises ) ) : ?>
		<p class="mbd-field__hint"><?php esc_html_e( 'No promises recorded.', 'mbd-crm' ); ?></p>
	<?php else : ?>
		<ul class="mbd-list">
			<?php foreach ( $promises as $promise ) : ?>
				<li class="mbd-list__item mbd-promise">
					<div class="mbd-promise__main">
						<span class="mbd-promise__desc"><?php echo esc_html( $promise->description ); ?></span>
						<span class="mbd-timeline__meta"><?php echo esc_html( sprintf( /* translators: %s: due date. */ __( 'Due %s', 'mbd-crm' ), $promise->due_date ? $promise->due_date : '—' ) ); ?></span>
					</div>
					<div class="mbd-promise__status">
						<?php echo Components::chip( Options::promise_label( $promise->status ), Options::promise_variant( $promise->status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php if ( $can_edit ) : ?>
							<form class="mbd-promise__form" method="post" action="<?php echo esc_url( $form_action ); ?>">
								<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<input type="hidden" name="mbd_action" value="update_promise" />
								<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
								<input type="hidden" name="promise_id" value="<?php echo (int) $promise->id; ?>" />
								<select name="status" aria-label="<?php esc_attr_e( 'Promise status', 'mbd-crm' ); ?>">
									<?php foreach ( Options::promise_statuses() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $promise->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Update', 'mbd-crm' ); ?></button>
							</form>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $can_edit ) : ?>
		<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="add_promise" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<label for="mbd-promise-desc"><?php esc_html_e( 'New promise', 'mbd-crm' ); ?></label>
			<input type="text" id="mbd-promise-desc" name="description" placeholder="<?php esc_attr_e( 'What was promised', 'mbd-crm' ); ?>" />
			<label for="mbd-promise-due"><?php esc_html_e( 'Promise due date', 'mbd-crm' ); ?></label>
			<input type="date" id="mbd-promise-due" name="due_date" />
			<button type="submit" class="mbd-btn"><?php esc_html_e( 'Record promise', 'mbd-crm' ); ?></button>
		</form>
	<?php endif; ?>
</section>
