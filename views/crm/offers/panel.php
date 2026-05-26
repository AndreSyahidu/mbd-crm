<?php
/**
 * Offer versioning panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object             $lead        Lead row.
 * @var object|null        $current     Latest offer version.
 * @var array<int, object> $history     All offer versions, newest first.
 * @var float              $threshold   Discount approval threshold (percent).
 * @var bool               $approved    Whether planning is approved (gate).
 * @var bool               $can_edit    Whether the user may manage offers.
 * @var bool               $can_approve Whether the user may approve discounts.
 * @var string             $nonce_field Pre-rendered nonce field.
 * @var string             $form_action Form post URL.
 * @var string             $notice      Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Offers\Status;

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Offer & discount', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( ! $approved ) : ?>
		<?php echo Components::notice( __( 'Offers are locked until planning is approved by the client.', 'mbd-crm' ), 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<?php if ( $current ) : ?>
			<div class="mbd-qual__head">
				<span class="mbd-qual__score"><?php echo esc_html( sprintf( /* translators: %d: version number. */ __( 'Version %d', 'mbd-crm' ), (int) $current->version ) ); ?></span>
				<?php echo Components::chip( Status::label( $current->status ), Status::variant( $current->status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<dl class="mbd-dl">
				<dt><?php esc_html_e( 'Base price', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( (float) $current->base_price ) ); ?></dd>
				<dt><?php esc_html_e( 'Discount', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( (float) $current->discount_percent, 2 ) . '%' ); ?></dd>
				<dt><?php esc_html_e( 'Final value', 'mbd-crm' ); ?></dt>
				<dd><strong><?php echo esc_html( number_format_i18n( (float) $current->final_value ) ); ?></strong></dd>
				<dt><?php esc_html_e( 'Valid until', 'mbd-crm' ); ?></dt>
				<dd><?php echo esc_html( $current->valid_until ? $current->valid_until : '—' ); ?></dd>
			</dl>
			<?php if ( '' !== trim( (string) $current->scope ) ) : ?>
				<p class="mbd-field__hint"><strong><?php esc_html_e( 'Scope:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $current->scope ); ?></p>
			<?php endif; ?>
			<?php if ( 'rejected' === $current->status && '' !== (string) $current->decision_reason ) : ?>
				<p class="mbd-field__hint"><strong><?php esc_html_e( 'Decision:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $current->decision_reason ); ?></p>
			<?php endif; ?>

			<?php if ( 'pending_approval' === $current->status ) : ?>
				<?php echo Components::notice( __( 'Discount exceeds the authority threshold. An Owner/Admin must approve it before the offer can be sent.', 'mbd-crm' ), 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( $can_approve ) : ?>
					<div class="mbd-approve-row">
						<form method="post" action="<?php echo esc_url( $form_action ); ?>">
							<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<input type="hidden" name="mbd_action" value="approve_offer" />
							<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
							<input type="hidden" name="offer_id" value="<?php echo (int) $current->id; ?>" />
							<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Approve discount', 'mbd-crm' ); ?></button>
						</form>
					</div>
					<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
						<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<input type="hidden" name="mbd_action" value="reject_offer" />
						<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
						<input type="hidden" name="offer_id" value="<?php echo (int) $current->id; ?>" />
						<label><?php esc_html_e( 'Rejection reason', 'mbd-crm' ); ?></label>
						<input type="text" name="decision_reason" />
						<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Reject discount', 'mbd-crm' ); ?></button>
					</form>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $can_edit && in_array( $current->status, array( 'draft', 'approved' ), true ) ) : ?>
				<form class="mbd-promise__form" method="post" action="<?php echo esc_url( $form_action ); ?>">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="mbd_action" value="send_offer" />
					<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
					<input type="hidden" name="offer_id" value="<?php echo (int) $current->id; ?>" />
					<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Send to client', 'mbd-crm' ); ?></button>
				</form>
			<?php endif; ?>

			<?php if ( $can_edit && 'sent' === $current->status ) : ?>
				<div class="mbd-approve-row">
					<form method="post" action="<?php echo esc_url( $form_action ); ?>">
						<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<input type="hidden" name="mbd_action" value="accept_offer" />
						<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
						<input type="hidden" name="offer_id" value="<?php echo (int) $current->id; ?>" />
						<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Client accepted', 'mbd-crm' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( $form_action ); ?>">
						<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<input type="hidden" name="mbd_action" value="decline_offer" />
						<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
						<input type="hidden" name="offer_id" value="<?php echo (int) $current->id; ?>" />
						<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Client declined', 'mbd-crm' ); ?></button>
					</form>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<p class="mbd-field__hint"><?php esc_html_e( 'No offer drafted yet.', 'mbd-crm' ); ?></p>
		<?php endif; ?>

		<?php if ( $can_edit ) : ?>
			<h4 class="mbd-subhead"><?php esc_html_e( 'New offer version', 'mbd-crm' ); ?></h4>
			<p class="mbd-field__hint">
				<?php
				printf(
					/* translators: %s: threshold percent. */
					esc_html__( 'Discounts above %s%% require Owner/Admin approval before sending.', 'mbd-crm' ),
					esc_html( number_format_i18n( $threshold, 0 ) )
				);
				?>
			</p>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="create_offer" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Base price', 'mbd-crm' ); ?></label>
				<input type="text" name="base_price" inputmode="decimal" value="<?php echo esc_attr( $current ? $current->base_price : '' ); ?>" />
				<label><?php esc_html_e( 'Discount type', 'mbd-crm' ); ?></label>
				<select name="discount_type">
					<option value="amount"><?php esc_html_e( 'Amount', 'mbd-crm' ); ?></option>
					<option value="percent"><?php esc_html_e( 'Percent', 'mbd-crm' ); ?></option>
				</select>
				<label><?php esc_html_e( 'Discount value', 'mbd-crm' ); ?></label>
				<input type="text" name="discount_value" inputmode="decimal" value="0" />
				<label><?php esc_html_e( 'Valid until', 'mbd-crm' ); ?></label>
				<input type="date" name="valid_until" />
				<label><?php esc_html_e( 'Scope / notes', 'mbd-crm' ); ?></label>
				<textarea name="scope" rows="2"></textarea>
				<button type="submit" class="mbd-btn mbd-btn--primary mbd-btn--small"><?php esc_html_e( 'Create offer version', 'mbd-crm' ); ?></button>
			</form>
		<?php endif; ?>

		<?php if ( ! empty( $history ) ) : ?>
			<h4 class="mbd-subhead"><?php esc_html_e( 'Version history', 'mbd-crm' ); ?></h4>
			<ul class="mbd-timeline">
				<?php foreach ( $history as $offer ) : ?>
					<li class="mbd-timeline__item">
						<span class="mbd-timeline__desc">
							<?php echo Components::chip( Status::label( $offer->status ), Status::variant( $offer->status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: version, 2: final value, 3: discount percent. */
									__( 'v%1$d — %2$s (−%3$s%%)', 'mbd-crm' ),
									(int) $offer->version,
									number_format_i18n( (float) $offer->final_value ),
									number_format_i18n( (float) $offer->discount_percent, 2 )
								)
							);
							?>
						</span>
						<span class="mbd-timeline__meta"><?php echo esc_html( (string) $offer->created_at ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</section>
