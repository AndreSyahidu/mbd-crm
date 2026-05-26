<?php
/**
 * Lead scoring panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object             $lead          Lead row.
 * @var int                $stored_score  Persisted score.
 * @var string             $temperature   Persisted temperature.
 * @var bool               $locked        Whether the score is manually overridden.
 * @var string             $override_note Override reason.
 * @var array              $live          Live Scorer::evaluate() result.
 * @var array<int, object> $history       Score history (newest first).
 * @var bool               $can_override  Whether the user may override.
 * @var string             $nonce_field   Pre-rendered nonce field.
 * @var string             $form_action   Form post URL.
 * @var string             $notice        Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Scoring\Scorer;

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Lead score', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="mbd-qual__head">
		<span class="mbd-kpi__value"><?php echo (int) $stored_score; ?><span class="mbd-score__max">/100</span></span>
		<?php echo Components::chip( Scorer::temperature_label( $temperature ), Scorer::temperature_variant( $temperature ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( $locked ) : ?>
			<?php echo Components::chip( __( 'Manual override', 'mbd-crm' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</div>

	<?php if ( $locked && '' !== $override_note ) : ?>
		<p class="mbd-field__hint"><strong><?php esc_html_e( 'Override reason:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $override_note ); ?></p>
	<?php endif; ?>

	<ul class="mbd-checklist">
		<?php foreach ( $live['breakdown'] as $row ) : ?>
			<li class="mbd-checklist__item <?php echo $row['points'] > 0 ? 'is-pass' : 'is-fail'; ?>">
				<span><?php echo esc_html( $row['label'] ); ?></span>
				<span><?php echo esc_html( $row['points'] . '/' . $row['max'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( $locked ) : ?>
		<p class="mbd-field__hint">
			<?php
			printf(
				/* translators: %d: computed score. */
				esc_html__( 'Computed (system) score would be %d.', 'mbd-crm' ),
				(int) $live['score']
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( $can_override ) : ?>
		<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="override_score" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<label><?php esc_html_e( 'Override score (0-100)', 'mbd-crm' ); ?></label>
			<input type="text" name="score" inputmode="numeric" value="<?php echo (int) $stored_score; ?>" />
			<label><?php esc_html_e( 'Reason (required)', 'mbd-crm' ); ?></label>
			<input type="text" name="reason" />
			<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Override score', 'mbd-crm' ); ?></button>
		</form>
		<?php if ( $locked ) : ?>
			<form method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="reset_score" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Clear override (recompute)', 'mbd-crm' ); ?></button>
			</form>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! empty( $history ) ) : ?>
		<h4 class="mbd-subhead"><?php esc_html_e( 'Score history', 'mbd-crm' ); ?></h4>
		<ul class="mbd-timeline">
			<?php foreach ( array_slice( $history, 0, 8 ) as $h ) : ?>
				<li class="mbd-timeline__item">
					<span class="mbd-timeline__desc"><?php echo esc_html( $h->old_score . ' → ' . $h->new_score . ' (' . $h->calculated_by . ')' ); ?></span>
					<?php if ( '' !== trim( (string) $h->reason ) ) : ?>
						<span class="mbd-timeline__meta"><?php echo esc_html( $h->reason ); ?></span>
					<?php endif; ?>
					<span class="mbd-timeline__meta"><?php echo esc_html( (string) $h->created_at ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
