<?php
/**
 * Closing & negotiation panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object             $lead         Lead row.
 * @var object|null        $closing      Closing row.
 * @var array<int, object> $negotiations Negotiation log.
 * @var bool               $approved     Whether planning is approved (gate).
 * @var bool               $can_edit     Whether the user may manage closing.
 * @var bool               $can_approve  Whether the user may approve/reject.
 * @var string             $nonce_field  Pre-rendered nonce field.
 * @var string             $form_action  Form post URL.
 * @var string             $notice       Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Closing\Options;

defined( 'ABSPATH' ) || exit;

$mbd_status = $closing ? (string) $closing->status : 'open';
$mbd_final  = in_array( $mbd_status, array( 'approved', 'lost' ), true );
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Closing & negotiation', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( ! $approved ) : ?>
		<?php echo Components::notice( __( 'Closing is locked until planning is approved by the client.', 'mbd-crm' ), 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<div class="mbd-qual__head">
			<?php echo Components::chip( Options::status_label( $mbd_status ), Options::status_variant( $mbd_status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $closing ) : ?>
				<span class="mbd-qual__score"><?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Forecast: %s', 'mbd-crm' ), number_format_i18n( Options::weighted_forecast( $closing ) ) ) ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( $closing ) : ?>
			<dl class="mbd-dl">
				<dt><?php esc_html_e( 'Estimated', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( null !== $closing->estimated_value ? number_format_i18n( (float) $closing->estimated_value ) : '—' ); ?></dd>
				<dt><?php esc_html_e( 'Offered', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( null !== $closing->offered_value ? number_format_i18n( (float) $closing->offered_value ) : '—' ); ?></dd>
				<dt><?php esc_html_e( 'Final', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( null !== $closing->final_value ? number_format_i18n( (float) $closing->final_value ) : '—' ); ?></dd>
				<dt><?php esc_html_e( 'Probability', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( (int) $closing->probability . '%' ); ?></dd>
				<dt><?php esc_html_e( 'Expected close', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( $closing->expected_close_date ? $closing->expected_close_date : '—' ); ?></dd>
			</dl>
			<?php if ( '' !== trim( (string) $closing->competitor_note ) ) : ?>
				<p class="mbd-field__hint"><strong><?php esc_html_e( 'Competitor:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $closing->competitor_note ); ?></p>
			<?php endif; ?>
			<?php if ( 'lost' === $mbd_status && '' !== $closing->lost_reason ) : ?>
				<p class="mbd-field__hint"><strong><?php esc_html_e( 'Lost reason:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $closing->lost_reason ); ?></p>
			<?php endif; ?>
			<?php if ( 'approved' === $mbd_status ) : ?>
				<?php echo Components::notice( __( 'Deal won. This closing is the CRM endpoint; a project draft was generated.', 'mbd-crm' ), 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $can_edit && ! $mbd_final ) : ?>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="save_offer" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Estimated value', 'mbd-crm' ); ?></label>
				<input type="text" name="estimated_value" inputmode="decimal" value="<?php echo esc_attr( $closing && null !== $closing->estimated_value ? $closing->estimated_value : '' ); ?>" />
				<label><?php esc_html_e( 'Offered value', 'mbd-crm' ); ?></label>
				<input type="text" name="offered_value" inputmode="decimal" value="<?php echo esc_attr( $closing && null !== $closing->offered_value ? $closing->offered_value : '' ); ?>" />
				<label><?php esc_html_e( 'Final value', 'mbd-crm' ); ?></label>
				<input type="text" name="final_value" inputmode="decimal" value="<?php echo esc_attr( $closing && null !== $closing->final_value ? $closing->final_value : '' ); ?>" />
				<label><?php esc_html_e( 'Probability (%)', 'mbd-crm' ); ?></label>
				<input type="text" name="probability" inputmode="numeric" value="<?php echo esc_attr( $closing ? (int) $closing->probability : '' ); ?>" />
				<label><?php esc_html_e( 'Expected closing date', 'mbd-crm' ); ?></label>
				<input type="date" name="expected_close_date" value="<?php echo esc_attr( $closing && $closing->expected_close_date ? $closing->expected_close_date : '' ); ?>" />
				<label><?php esc_html_e( 'Next action', 'mbd-crm' ); ?></label>
				<input type="text" name="next_action" value="<?php echo esc_attr( $closing ? $closing->next_action : '' ); ?>" />
				<label><?php esc_html_e( 'Competitor note', 'mbd-crm' ); ?></label>
				<textarea name="competitor_note" rows="2"><?php echo esc_textarea( $closing ? $closing->competitor_note : '' ); ?></textarea>
				<button type="submit" class="mbd-btn mbd-btn--primary mbd-btn--small"><?php esc_html_e( 'Save offer', 'mbd-crm' ); ?></button>
			</form>

			<?php if ( ! $closing || ! (int) $closing->proposal_sent ) : ?>
				<form class="mbd-promise__form" method="post" action="<?php echo esc_url( $form_action ); ?>">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="mbd_action" value="mark_proposal_sent" />
					<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
					<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Mark proposal sent', 'mbd-crm' ); ?></button>
				</form>
			<?php endif; ?>
		<?php endif; ?>

		<h4 class="mbd-subhead"><?php esc_html_e( 'Negotiation log', 'mbd-crm' ); ?></h4>
		<?php if ( empty( $negotiations ) ) : ?>
			<p class="mbd-field__hint"><?php esc_html_e( 'No negotiation entries.', 'mbd-crm' ); ?></p>
		<?php else : ?>
			<ul class="mbd-timeline">
				<?php foreach ( $negotiations as $n ) : ?>
					<li class="mbd-timeline__item">
						<span class="mbd-timeline__desc">
							<?php echo Components::chip( 'objection' === $n->entry_type ? __( 'Objection', 'mbd-crm' ) : __( 'Note', 'mbd-crm' ), 'objection' === $n->entry_type ? 'warning' : 'default' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo esc_html( $n->content ); ?>
						</span>
						<span class="mbd-timeline__meta"><?php echo esc_html( (string) $n->created_at ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $can_edit && ! $mbd_final ) : ?>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="add_negotiation" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Entry type', 'mbd-crm' ); ?></label>
				<select name="entry_type">
					<option value="note"><?php esc_html_e( 'Note', 'mbd-crm' ); ?></option>
					<option value="objection"><?php esc_html_e( 'Objection', 'mbd-crm' ); ?></option>
				</select>
				<label><?php esc_html_e( 'Detail', 'mbd-crm' ); ?></label>
				<textarea name="content" rows="2"></textarea>
				<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Add entry', 'mbd-crm' ); ?></button>
			</form>

			<?php if ( 'waiting_approval' !== $mbd_status ) : ?>
				<form class="mbd-promise__form" method="post" action="<?php echo esc_url( $form_action ); ?>">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="mbd_action" value="request_closing_approval" />
					<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
					<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Request closing approval', 'mbd-crm' ); ?></button>
				</form>
			<?php endif; ?>

			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="fail_closing" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Lost reason', 'mbd-crm' ); ?></label>
				<input type="text" name="lost_reason" />
				<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Mark closing lost', 'mbd-crm' ); ?></button>
			</form>
		<?php endif; ?>

		<?php if ( $can_approve && 'waiting_approval' === $mbd_status ) : ?>
			<div class="mbd-approve-row">
				<form method="post" action="<?php echo esc_url( $form_action ); ?>">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="mbd_action" value="approve_closing" />
					<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
					<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Approve closing', 'mbd-crm' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( $form_action ); ?>">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="mbd_action" value="reject_closing" />
					<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
					<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Reject', 'mbd-crm' ); ?></button>
				</form>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</section>
