<?php
/**
 * Project handoff panel (rendered on won leads).
 *
 * @package MBD\CRM
 *
 * @var object             $lead        Lead row.
 * @var object             $handoff     Handoff row.
 * @var array<int, array{key:string,label:string,done:bool}> $items Checklist rows.
 * @var bool               $complete    Whether all items are done.
 * @var string             $pic_name    Project PIC display name (or '').
 * @var bool               $can_edit    Whether the user may manage the handoff.
 * @var string             $nonce_field Pre-rendered nonce field.
 * @var string             $form_action Form post URL (leads route).
 * @var string             $notice      Flash notice HTML.
 */

use MBD\CRM\Dashboard\Formulas;
use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;

$mbd_done_status = 'completed' === $handoff->status;
$mbd_variant     = $mbd_done_status ? 'success' : ( 'ready' === $handoff->status ? 'info' : 'warning' );
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Project handoff', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="mbd-qual__head">
		<?php echo Components::chip( ucfirst( str_replace( '_', ' ', $handoff->status ) ), $mbd_variant ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<span class="mbd-qual__score"><?php echo esc_html( Formulas::idr( (float) $handoff->final_value ) ); ?></span>
	</div>

	<dl class="mbd-dl">
		<dt><?php esc_html_e( 'Project PIC', 'mbd-crm' ); ?></dt>
		<dd><?php echo esc_html( '' !== $pic_name ? $pic_name : __( 'Unassigned', 'mbd-crm' ) ); ?></dd>
		<?php if ( $mbd_done_status && ! empty( $handoff->handed_off_at ) ) : ?>
			<dt><?php esc_html_e( 'Handed off', 'mbd-crm' ); ?></dt>
			<dd><?php echo esc_html( (string) $handoff->handed_off_at ); ?></dd>
		<?php endif; ?>
	</dl>
	<?php if ( '' !== trim( (string) $handoff->scope ) ) : ?>
		<p class="mbd-field__hint"><strong><?php esc_html_e( 'Scope:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $handoff->scope ); ?></p>
	<?php endif; ?>

	<ul class="mbd-checklist">
		<?php foreach ( $items as $item ) : ?>
			<li class="mbd-checklist__item <?php echo $item['done'] ? 'is-pass' : 'is-fail'; ?>">
				<span><?php echo esc_html( $item['label'] ); ?></span>
				<?php if ( $can_edit && ! $mbd_done_status ) : ?>
					<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="mbd-inline-form">
						<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<input type="hidden" name="mbd_action" value="toggle_handoff_item" />
						<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
						<input type="hidden" name="item" value="<?php echo esc_attr( $item['key'] ); ?>" />
						<button type="submit" class="mbd-btn mbd-btn--small">
							<?php echo $item['done'] ? esc_html__( 'Undo', 'mbd-crm' ) : esc_html__( 'Mark done', 'mbd-crm' ); ?>
						</button>
					</form>
				<?php else : ?>
					<span><?php echo $item['done'] ? esc_html__( 'Done', 'mbd-crm' ) : esc_html__( 'Pending', 'mbd-crm' ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( $can_edit && ! $mbd_done_status ) : ?>
		<?php if ( (int) $handoff->pic_user_id < 1 ) : ?>
			<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="mbd-promise__form">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="assign_handoff_pic" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Assign me as PIC', 'mbd-crm' ); ?></button>
			</form>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="mbd-promise__form">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="complete_handoff" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary" <?php echo $complete ? '' : 'disabled'; ?>>
				<?php esc_html_e( 'Hand off to delivery', 'mbd-crm' ); ?>
			</button>
		</form>
		<?php if ( ! $complete ) : ?>
			<p class="mbd-field__hint"><?php esc_html_e( 'Complete every checklist item to enable handoff.', 'mbd-crm' ); ?></p>
		<?php endif; ?>
	<?php elseif ( $mbd_done_status ) : ?>
		<?php echo Components::notice( __( 'This project has been handed off to the delivery team.', 'mbd-crm' ), 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
</section>
