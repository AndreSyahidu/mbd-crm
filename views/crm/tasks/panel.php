<?php
/**
 * Lead-detail Tasks panel (interactive: add / complete / reopen).
 *
 * @package MBD\CRM
 *
 * @var object             $lead        Lead row.
 * @var array<int, object> $tasks       Tasks for this lead (newest first).
 * @var bool               $can_edit    Whether the user may manage tasks.
 * @var string             $nonce_field Pre-rendered nonce field.
 * @var string             $form_action Form post URL (leads route).
 * @var string             $notice      Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Tasks', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( empty( $tasks ) ) : ?>
		<p class="mbd-field__hint"><?php esc_html_e( 'No tasks yet.', 'mbd-crm' ); ?></p>
	<?php else : ?>
		<ul class="mbd-list">
			<?php foreach ( $tasks as $task ) : ?>
				<?php $mbd_done = 'open' !== $task->status; ?>
				<li class="mbd-list__item">
					<span class="<?php echo $mbd_done ? 'mbd-task--done' : ''; ?>">
						<?php echo esc_html( $task->title ); ?>
						<?php if ( ! empty( $task->due_at ) ) : ?>
							<span class="mbd-timeline__meta"><?php echo esc_html( substr( (string) $task->due_at, 0, 10 ) ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( $can_edit ) : ?>
						<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="mbd-inline-form">
							<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<input type="hidden" name="mbd_action" value="<?php echo $mbd_done ? 'reopen_task' : 'complete_task'; ?>" />
							<input type="hidden" name="task_id" value="<?php echo (int) $task->id; ?>" />
							<button type="submit" class="mbd-btn mbd-btn--small">
								<?php echo $mbd_done ? esc_html__( 'Reopen', 'mbd-crm' ) : esc_html__( 'Done', 'mbd-crm' ); ?>
							</button>
						</form>
					<?php else : ?>
						<?php echo Components::chip( $task->status, $mbd_done ? 'success' : 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $can_edit ) : ?>
		<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="add_task" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<label><?php esc_html_e( 'New task', 'mbd-crm' ); ?></label>
			<input type="text" name="title" placeholder="<?php esc_attr_e( 'What needs doing?', 'mbd-crm' ); ?>" />
			<label><?php esc_html_e( 'Due date', 'mbd-crm' ); ?></label>
			<input type="date" name="due_at" />
			<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Add task', 'mbd-crm' ); ?></button>
		</form>
	<?php endif; ?>
</section>
