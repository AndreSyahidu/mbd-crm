<?php
/**
 * Lead create / edit form.
 *
 * @package MBD\CRM
 *
 * @var object|null            $existing    Lead being edited.
 * @var array<string, mixed>   $values      Current field values.
 * @var array<string, string>  $errors      Field => error message.
 * @var bool                   $can_assign  Whether the user may pick an assignee.
 * @var array<int, string>     $sales_users Assignable user id => name.
 * @var string                 $nonce_field Pre-rendered nonce field.
 * @var string                 $form_action Form post URL.
 * @var string                 $list_url    Back-to-list URL.
 * @var string                 $mode        'create' or 'update'.
 */

use MBD\CRM\Leads\Options;

defined( 'ABSPATH' ) || exit;

$mbd_err = static function ( $key ) use ( $errors ) {
	return isset( $errors[ $key ] )
		? '<span class="mbd-field__error">' . esc_html( $errors[ $key ] ) . '</span>'
		: '';
};

$mbd_select = static function ( $name, $group, $current, $placeholder ) {
	$out  = '<select id="mbd-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
	$out .= '<option value="">' . esc_html( $placeholder ) . '</option>';
	foreach ( Options::group( $group ) as $key => $label ) {
		$out .= '<option value="' . esc_attr( $key ) . '"' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
	}
	$out .= '</select>';

	return $out;
};
?>
<div class="mbd-page">
	<p class="mbd-page__lead">
		<a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to leads', 'mbd-crm' ); ?></a>
	</p>

	<h2 class="mbd-form__title">
		<?php echo 'update' === $mode ? esc_html__( 'Edit lead', 'mbd-crm' ) : esc_html__( 'New lead', 'mbd-crm' ); ?>
	</h2>

	<?php if ( ! empty( $errors ) ) : ?>
		<p class="mbd-notice mbd-notice--danger" role="alert">
			<?php esc_html_e( 'Please fix the highlighted fields.', 'mbd-crm' ); ?>
		</p>
	<?php endif; ?>

	<form class="mbd-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php
		echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<input type="hidden" name="mbd_action" value="<?php echo esc_attr( $mode ); ?>" />
		<?php if ( $existing ) : ?>
			<input type="hidden" name="lead_id" value="<?php echo (int) $existing->id; ?>" />
		<?php endif; ?>

		<div class="mbd-field">
			<label for="mbd-name"><?php esc_html_e( 'Lead name', 'mbd-crm' ); ?> <span class="mbd-req">*</span></label>
			<input type="text" id="mbd-name" name="name" value="<?php echo esc_attr( $values['name'] ); ?>" required />
			<?php echo $mbd_err( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="mbd-field-row">
			<div class="mbd-field">
				<label for="mbd-whatsapp"><?php esc_html_e( 'WhatsApp number', 'mbd-crm' ); ?></label>
				<input type="text" id="mbd-whatsapp" name="whatsapp" inputmode="numeric" placeholder="628123456789" value="<?php echo esc_attr( $values['whatsapp'] ); ?>" />
			</div>
			<div class="mbd-field">
				<label for="mbd-source"><?php esc_html_e( 'Source', 'mbd-crm' ); ?></label>
				<?php echo $mbd_select( 'source', 'sources', $values['source'], __( 'Select source', 'mbd-crm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>

		<div class="mbd-field-row">
			<div class="mbd-field">
				<label for="mbd-project_type"><?php esc_html_e( 'Project type', 'mbd-crm' ); ?></label>
				<?php echo $mbd_select( 'project_type', 'project_types', $values['project_type'], __( 'Select project type', 'mbd-crm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="mbd-field">
				<label for="mbd-service_type"><?php esc_html_e( 'Service type', 'mbd-crm' ); ?></label>
				<?php echo $mbd_select( 'service_type', 'service_types', $values['service_type'], __( 'Select service type', 'mbd-crm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>

		<div class="mbd-field-row">
			<div class="mbd-field">
				<label for="mbd-estimated_budget"><?php esc_html_e( 'Estimated budget', 'mbd-crm' ); ?></label>
				<input type="text" id="mbd-estimated_budget" name="estimated_budget" inputmode="decimal" value="<?php echo esc_attr( $values['estimated_budget'] ); ?>" />
				<?php echo $mbd_err( 'estimated_budget' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="mbd-field">
				<label for="mbd-budget_unknown_reason"><?php esc_html_e( 'Budget unknown reason', 'mbd-crm' ); ?></label>
				<input type="text" id="mbd-budget_unknown_reason" name="budget_unknown_reason" value="<?php echo esc_attr( $values['budget_unknown_reason'] ); ?>" />
				<?php echo $mbd_err( 'budget_unknown_reason' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>

		<div class="mbd-field-row">
			<div class="mbd-field">
				<label for="mbd-urgency"><?php esc_html_e( 'Urgency', 'mbd-crm' ); ?></label>
				<?php echo $mbd_select( 'urgency', 'urgencies', $values['urgency'], __( 'Select urgency', 'mbd-crm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="mbd-field">
				<label for="mbd-quality"><?php esc_html_e( 'Lead quality', 'mbd-crm' ); ?></label>
				<?php echo $mbd_select( 'quality', 'qualities', $values['quality'], __( 'Select quality', 'mbd-crm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>

		<div class="mbd-field-row">
			<div class="mbd-field">
				<label for="mbd-next_follow_up"><?php esc_html_e( 'Next follow-up date', 'mbd-crm' ); ?></label>
				<input type="date" id="mbd-next_follow_up" name="next_follow_up" value="<?php echo esc_attr( $values['next_follow_up'] ); ?>" />
				<?php echo $mbd_err( 'next_follow_up' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="mbd-field">
				<?php if ( $can_assign ) : ?>
					<label for="mbd-assigned_to"><?php esc_html_e( 'Assign to sales', 'mbd-crm' ); ?></label>
					<select id="mbd-assigned_to" name="assigned_to">
						<option value="0"><?php esc_html_e( 'Unassigned', 'mbd-crm' ); ?></option>
						<?php foreach ( $sales_users as $uid => $uname ) : ?>
							<option value="<?php echo (int) $uid; ?>"<?php selected( (int) $values['assigned_to'], (int) $uid ); ?>>
								<?php echo esc_html( $uname ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<label><?php esc_html_e( 'Assignment', 'mbd-crm' ); ?></label>
					<p class="mbd-field__hint"><?php esc_html_e( 'This lead is assigned to you.', 'mbd-crm' ); ?></p>
					<input type="hidden" name="assigned_to" value="<?php echo (int) $values['assigned_to']; ?>" />
				<?php endif; ?>
			</div>
		</div>

		<?php if ( 'update' === $mode ) : ?>
			<div class="mbd-field">
				<label for="mbd-status"><?php esc_html_e( 'Status', 'mbd-crm' ); ?></label>
				<?php echo $mbd_select( 'status', 'statuses', $values['status'], __( 'Select status', 'mbd-crm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>

		<div class="mbd-field">
			<label for="mbd-notes"><?php esc_html_e( 'Notes', 'mbd-crm' ); ?></label>
			<textarea id="mbd-notes" name="notes" rows="4"><?php echo esc_textarea( $values['notes'] ); ?></textarea>
		</div>

		<div class="mbd-form__actions">
			<button type="submit" class="mbd-btn mbd-btn--primary">
				<?php echo 'update' === $mode ? esc_html__( 'Save changes', 'mbd-crm' ) : esc_html__( 'Create lead', 'mbd-crm' ); ?>
			</button>
			<a class="mbd-btn" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancel', 'mbd-crm' ); ?></a>
		</div>
	</form>
</div>
