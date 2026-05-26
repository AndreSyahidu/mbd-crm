<?php
/**
 * Lifecycle + stage-history panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object             $lead         Lead row.
 * @var string             $stage        Current stage key.
 * @var string             $stage_label  Current stage label.
 * @var string             $aging        Aging label.
 * @var bool               $stale        Whether the lead is stale.
 * @var string             $stale_reason Stale reason.
 * @var array<int, object> $history      Stage history (newest first).
 * @var bool               $can_edit     Whether the user may change lifecycle.
 * @var bool               $has_open     Whether open tasks/promises exist.
 * @var string             $nonce_field  Pre-rendered nonce field.
 * @var string             $form_action  Form post URL.
 * @var string             $notice       Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;

$mbd_lifecycle = isset( $lead->lifecycle ) ? (string) $lead->lifecycle : 'active';
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Stage & lifecycle', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="mbd-qual__head">
		<?php
		echo Components::chip( $stage_label, $stale ? 'danger' : 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::chip( sprintf( /* translators: %s: aging. */ __( 'In stage %s', 'mbd-crm' ), $aging ), $stale ? 'warning' : 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'active' !== $mbd_lifecycle ) {
			echo Components::chip( ucfirst( $mbd_lifecycle ), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
	</div>

	<?php if ( $stale && '' !== $stale_reason ) : ?>
		<?php echo Components::notice( $stale_reason, 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>

	<?php if ( $can_edit ) : ?>
		<?php if ( 'active' === $mbd_lifecycle ) : ?>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="move_on_hold" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Put on hold — reason (required)', 'mbd-crm' ); ?></label>
				<input type="text" name="reason" />
				<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Move to on hold', 'mbd-crm' ); ?></button>
			</form>

			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="archive_lead" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Archive — reason (required)', 'mbd-crm' ); ?></label>
				<input type="text" name="reason" />
				<?php if ( $has_open ) : ?>
					<?php echo Components::notice( __( 'This lead has open tasks or promises. An override reason is required to archive.', 'mbd-crm' ), 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<label><?php esc_html_e( 'Override reason', 'mbd-crm' ); ?></label>
					<input type="text" name="override_reason" />
				<?php endif; ?>
				<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Archive lead', 'mbd-crm' ); ?></button>
			</form>
		<?php endif; ?>

		<?php if ( 'active' !== $mbd_lifecycle || 'lost' === $lead->closing_status ) : ?>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="reactivate_lead" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Reactivate — reason (required)', 'mbd-crm' ); ?></label>
				<input type="text" name="reason" />
				<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Reactivate lead', 'mbd-crm' ); ?></button>
			</form>
		<?php endif; ?>
	<?php endif; ?>

	<h4 class="mbd-subhead"><?php esc_html_e( 'Stage history', 'mbd-crm' ); ?></h4>
	<?php if ( empty( $history ) ) : ?>
		<p class="mbd-field__hint"><?php esc_html_e( 'No stage changes recorded.', 'mbd-crm' ); ?></p>
	<?php else : ?>
		<ul class="mbd-timeline">
			<?php foreach ( $history as $h ) : ?>
				<li class="mbd-timeline__item">
					<span class="mbd-timeline__desc"><?php echo esc_html( ( '' !== $h->from_status ? $h->from_status : '—' ) . ' → ' . $h->to_status ); ?></span>
					<?php if ( '' !== trim( (string) $h->reason ) ) : ?>
						<span class="mbd-timeline__meta"><?php echo esc_html( $h->reason ); ?></span>
					<?php endif; ?>
					<span class="mbd-timeline__meta"><?php echo esc_html( (string) $h->changed_at ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
