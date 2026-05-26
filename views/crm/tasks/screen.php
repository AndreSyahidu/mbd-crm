<?php
/**
 * Tasks screen: the current user's open work.
 *
 * @package MBD\CRM
 *
 * @var array<int, array{id:int,title:string,due:string,lead_id:int,url:string}> $overdue  Overdue tasks.
 * @var array<int, array{id:int,title:string,due:string,lead_id:int,url:string}> $upcoming Upcoming/undated tasks.
 * @var array<int, string> $pickable    Lead ID => name (for new-task form).
 * @var string             $nonce_field Pre-rendered nonce field.
 * @var string             $form_action Form post URL (leads route).
 * @var string             $notice      Flash notice HTML.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a task list block.
 *
 * @param array<int, array<string,mixed>> $rows        Task rows.
 * @param string                          $nonce_field Nonce field markup.
 * @param string                          $form_action Form action URL.
 * @param bool                            $overdue     Whether these are overdue.
 * @return void
 */
$mbd_render_tasks = static function ( array $rows, string $nonce_field, string $form_action, bool $overdue ) {
	?>
	<ul class="mbd-list">
		<?php foreach ( $rows as $row ) : ?>
			<li class="mbd-list__item">
				<span>
					<a class="mbd-table__primary" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
					<?php if ( '' !== $row['due'] ) : ?>
						<span class="mbd-timeline__meta<?php echo $overdue ? ' mbd-text--danger' : ''; ?>"><?php echo esc_html( substr( $row['due'], 0, 10 ) ); ?></span>
					<?php endif; ?>
				</span>
				<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="mbd-inline-form">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="mbd_action" value="complete_task" />
					<input type="hidden" name="task_id" value="<?php echo (int) $row['id']; ?>" />
					<input type="hidden" name="return" value="tasks" />
					<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Done', 'mbd-crm' ); ?></button>
				</form>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
};
?>
<div class="mbd-page">
	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<section class="mbd-panel">
		<h2 class="mbd-panel__title"><?php esc_html_e( 'Overdue', 'mbd-crm' ); ?></h2>
		<?php if ( empty( $overdue ) ) : ?>
			<p class="mbd-field__hint"><?php esc_html_e( 'Nothing overdue. Nice work.', 'mbd-crm' ); ?></p>
		<?php else : ?>
			<?php $mbd_render_tasks( $overdue, $nonce_field, $form_action, true ); ?>
		<?php endif; ?>
	</section>

	<section class="mbd-panel">
		<h2 class="mbd-panel__title"><?php esc_html_e( 'Upcoming', 'mbd-crm' ); ?></h2>
		<?php if ( empty( $upcoming ) ) : ?>
			<p class="mbd-field__hint"><?php esc_html_e( 'No open tasks.', 'mbd-crm' ); ?></p>
		<?php else : ?>
			<?php $mbd_render_tasks( $upcoming, $nonce_field, $form_action, false ); ?>
		<?php endif; ?>
	</section>

	<?php if ( ! empty( $pickable ) ) : ?>
		<section class="mbd-panel">
			<h2 class="mbd-panel__title"><?php esc_html_e( 'Add a task', 'mbd-crm' ); ?></h2>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="add_task" />
				<input type="hidden" name="return" value="tasks" />
				<label><?php esc_html_e( 'Lead', 'mbd-crm' ); ?></label>
				<select name="lead_id">
					<?php foreach ( $pickable as $id => $name ) : ?>
						<option value="<?php echo (int) $id; ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><?php esc_html_e( 'Task', 'mbd-crm' ); ?></label>
				<input type="text" name="title" placeholder="<?php esc_attr_e( 'What needs doing?', 'mbd-crm' ); ?>" />
				<label><?php esc_html_e( 'Due date', 'mbd-crm' ); ?></label>
				<input type="date" name="due_at" />
				<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Add task', 'mbd-crm' ); ?></button>
			</form>
		</section>
	<?php endif; ?>
</div>
