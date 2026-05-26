<?php
/**
 * Planning panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object             $lead         Lead row.
 * @var object|null        $planning     Planning row.
 * @var array<int, object> $deliverables Deliverables.
 * @var array<int, object> $revisions    Revision log.
 * @var bool               $can_edit     Whether the user may manage planning.
 * @var bool               $can_plan     Whether planning is unlocked.
 * @var array<int, string> $planners     Assignable planner id => name.
 * @var string             $nonce_field  Pre-rendered nonce field.
 * @var string             $form_action  Form post URL.
 * @var string             $notice       Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Planning\Options;

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Planning', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( ! $planning ) : ?>
		<?php if ( ! $can_plan ) : ?>
			<?php echo Components::notice( __( 'Planning is locked until the deposit is valid or overridden.', 'mbd-crm' ), 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php elseif ( $can_edit ) : ?>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="create_planning" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Planner', 'mbd-crm' ); ?></label>
				<select name="planner_id">
					<option value="0"><?php esc_html_e( 'Unassigned', 'mbd-crm' ); ?></option>
					<?php foreach ( $planners as $uid => $uname ) : ?>
						<option value="<?php echo (int) $uid; ?>"><?php echo esc_html( $uname ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><?php esc_html_e( 'Scope', 'mbd-crm' ); ?></label>
				<textarea name="scope" rows="2"></textarea>
				<label><?php esc_html_e( 'Target completion date', 'mbd-crm' ); ?></label>
				<input type="date" name="target_date" />
				<button type="submit" class="mbd-btn mbd-btn--primary"><?php esc_html_e( 'Create planning', 'mbd-crm' ); ?></button>
			</form>
		<?php endif; ?>
	<?php else : ?>
		<div class="mbd-qual__head">
			<?php
			echo Components::chip( Options::status_label( $planning->status ), Options::status_variant( $planning->status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::chip( sprintf( /* translators: %s: review state. */ __( 'Review: %s', 'mbd-crm' ), $planning->internal_review ), 'pending' === $planning->internal_review ? 'muted' : ( 'passed' === $planning->internal_review ? 'success' : 'danger' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<dl class="mbd-dl">
			<dt><?php esc_html_e( 'Planner', 'mbd-crm' ); ?></dt>
			<dd><?php echo esc_html( $planners[ (int) $planning->planner_id ] ?? __( 'Unassigned', 'mbd-crm' ) ); ?></dd>
			<dt><?php esc_html_e( 'Target', 'mbd-crm' ); ?></dt>
			<dd><?php echo esc_html( $planning->target_date ? $planning->target_date : '—' ); ?></dd>
		</dl>
		<?php if ( '' !== trim( (string) $planning->scope ) ) : ?>
			<p class="mbd-field__hint"><strong><?php esc_html_e( 'Scope:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $planning->scope ); ?></p>
		<?php endif; ?>

		<?php if ( $can_edit ) : ?>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="update_planning" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Planner', 'mbd-crm' ); ?></label>
				<select name="planner_id">
					<option value="0"><?php esc_html_e( 'Unassigned', 'mbd-crm' ); ?></option>
					<?php foreach ( $planners as $uid => $uname ) : ?>
						<option value="<?php echo (int) $uid; ?>"<?php selected( (int) $planning->planner_id, (int) $uid ); ?>><?php echo esc_html( $uname ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><?php esc_html_e( 'Scope', 'mbd-crm' ); ?></label>
				<textarea name="scope" rows="2"><?php echo esc_textarea( $planning->scope ); ?></textarea>
				<label><?php esc_html_e( 'Target date', 'mbd-crm' ); ?></label>
				<input type="date" name="target_date" value="<?php echo esc_attr( $planning->target_date ? $planning->target_date : '' ); ?>" />
				<label><?php esc_html_e( 'Status', 'mbd-crm' ); ?></label>
				<select name="status">
					<?php foreach ( Options::editable_statuses() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $planning->status, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><?php esc_html_e( 'Internal review', 'mbd-crm' ); ?></label>
				<select name="internal_review">
					<?php foreach ( Options::reviews() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $planning->internal_review, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="mbd-btn mbd-btn--primary mbd-btn--small"><?php esc_html_e( 'Save planning', 'mbd-crm' ); ?></button>
			</form>
		<?php endif; ?>

		<h4 class="mbd-subhead"><?php esc_html_e( 'Deliverables', 'mbd-crm' ); ?></h4>
		<?php if ( empty( $deliverables ) ) : ?>
			<p class="mbd-field__hint"><?php esc_html_e( 'No deliverables yet.', 'mbd-crm' ); ?></p>
		<?php else : ?>
			<ul class="mbd-list">
				<?php foreach ( $deliverables as $d ) : ?>
					<li class="mbd-list__item">
						<span><?php echo esc_html( Options::deliverable_label( $d->type ) . ' — ' . $d->title ); ?></span>
						<span class="mbd-chip mbd-chip--info"><?php echo esc_html( sprintf( /* translators: %d: version. */ __( 'v%d', 'mbd-crm' ), (int) $d->version ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $can_edit ) : ?>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="add_deliverable" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Deliverable type', 'mbd-crm' ); ?></label>
				<select name="deliverable_type">
					<?php foreach ( Options::deliverable_types() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><?php esc_html_e( 'Title', 'mbd-crm' ); ?></label>
				<input type="text" name="deliverable_title" />
				<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Add deliverable', 'mbd-crm' ); ?></button>
			</form>
		<?php endif; ?>

		<h4 class="mbd-subhead"><?php esc_html_e( 'Revision log', 'mbd-crm' ); ?></h4>
		<?php if ( empty( $revisions ) ) : ?>
			<p class="mbd-field__hint"><?php esc_html_e( 'No revisions logged.', 'mbd-crm' ); ?></p>
		<?php else : ?>
			<ul class="mbd-timeline">
				<?php foreach ( $revisions as $r ) : ?>
					<li class="mbd-timeline__item">
						<span class="mbd-timeline__desc"><?php echo esc_html( $r->note ); ?></span>
						<span class="mbd-timeline__meta"><?php echo esc_html( (string) $r->created_at ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $can_edit ) : ?>
			<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="mbd_action" value="add_revision" />
				<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
				<label><?php esc_html_e( 'Revision note', 'mbd-crm' ); ?></label>
				<textarea name="revision_note" rows="2"></textarea>
				<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Log revision', 'mbd-crm' ); ?></button>
			</form>
		<?php endif; ?>
	<?php endif; ?>
</section>
