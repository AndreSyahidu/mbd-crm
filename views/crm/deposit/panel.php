<?php
/**
 * Deposit panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object      $lead         Lead row.
 * @var object|null $deposit      Deposit row.
 * @var bool        $can_request  Whether the user may save the request.
 * @var bool        $can_verify   Whether the user may verify/reject.
 * @var bool        $can_override Whether the user may override.
 * @var bool        $can_plan     Whether planning is unlocked.
 * @var string      $nonce_field  Pre-rendered nonce field.
 * @var string      $form_action  Form post URL.
 * @var string      $notice       Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Deposit\Options;

defined( 'ABSPATH' ) || exit;

$mbd_status = $deposit ? (string) $deposit->status : 'none';
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Deposit', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="mbd-qual__head">
		<?php
		if ( $deposit ) {
			echo Components::chip( Options::status_label( $deposit->status ), Options::status_variant( $deposit->status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo Components::chip( __( 'No deposit', 'mbd-crm' ), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $deposit && (int) $deposit->is_override === 1 ) {
			echo Components::chip( __( 'Override', 'mbd-crm' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo $can_plan // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			? Components::chip( __( 'Planning unlocked', 'mbd-crm' ), 'success' )
			: Components::chip( __( 'Planning locked', 'mbd-crm' ), 'muted' );
		?>
	</div>

	<?php if ( $deposit ) : ?>
		<dl class="mbd-dl">
			<dt><?php esc_html_e( 'Required', 'mbd-crm' ); ?></dt>
			<dd><?php echo esc_html( null !== $deposit->required_amount ? number_format_i18n( (float) $deposit->required_amount ) : '—' ); ?></dd>
			<dt><?php esc_html_e( 'Paid', 'mbd-crm' ); ?></dt>
			<dd><?php echo esc_html( null !== $deposit->paid_amount ? number_format_i18n( (float) $deposit->paid_amount ) : '—' ); ?></dd>
			<dt><?php esc_html_e( 'Method', 'mbd-crm' ); ?></dt>
			<dd><?php echo esc_html( '' !== $deposit->payment_method ? Options::method_label( $deposit->payment_method ) : '—' ); ?></dd>
			<dt><?php esc_html_e( 'Receipt #', 'mbd-crm' ); ?></dt>
			<dd><?php echo esc_html( '' !== $deposit->receipt_number ? $deposit->receipt_number : '—' ); ?></dd>
			<dt><?php esc_html_e( 'Proof', 'mbd-crm' ); ?></dt>
			<dd>
				<?php if ( '' !== (string) $deposit->proof_url ) : ?>
					<a href="<?php echo esc_url( $deposit->proof_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View proof', 'mbd-crm' ); ?></a>
				<?php else : ?>&mdash;<?php endif; ?>
			</dd>
		</dl>
		<?php if ( 'rejected' === $deposit->status && '' !== $deposit->rejection_reason ) : ?>
			<p class="mbd-field__hint"><strong><?php esc_html_e( 'Rejection reason:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $deposit->rejection_reason ); ?></p>
		<?php endif; ?>
		<?php if ( (int) $deposit->is_override === 1 && '' !== $deposit->override_reason ) : ?>
			<p class="mbd-field__hint"><strong><?php esc_html_e( 'Override reason:', 'mbd-crm' ); ?></strong> <?php echo esc_html( $deposit->override_reason ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $can_request ) : ?>
		<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>" enctype="multipart/form-data">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="request_deposit" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<label><?php esc_html_e( 'Required amount', 'mbd-crm' ); ?></label>
			<input type="text" name="required_amount" inputmode="decimal" value="<?php echo esc_attr( $deposit && null !== $deposit->required_amount ? $deposit->required_amount : '' ); ?>" />
			<label><?php esc_html_e( 'Paid amount', 'mbd-crm' ); ?></label>
			<input type="text" name="paid_amount" inputmode="decimal" value="<?php echo esc_attr( $deposit && null !== $deposit->paid_amount ? $deposit->paid_amount : '' ); ?>" />
			<label><?php esc_html_e( 'Payment date', 'mbd-crm' ); ?></label>
			<input type="date" name="payment_date" value="<?php echo esc_attr( $deposit && $deposit->payment_date ? $deposit->payment_date : '' ); ?>" />
			<label><?php esc_html_e( 'Payment method', 'mbd-crm' ); ?></label>
			<select name="payment_method">
				<option value="">&mdash;</option>
				<?php foreach ( Options::methods() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $deposit ? $deposit->payment_method : '', $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label><?php esc_html_e( 'Receipt number', 'mbd-crm' ); ?></label>
			<input type="text" name="receipt_number" value="<?php echo esc_attr( $deposit ? $deposit->receipt_number : '' ); ?>" />
			<label><?php esc_html_e( 'Upload proof', 'mbd-crm' ); ?></label>
			<input type="file" name="proof" accept="image/*,application/pdf" />
			<button type="submit" class="mbd-btn mbd-btn--primary"><?php esc_html_e( 'Save deposit request', 'mbd-crm' ); ?></button>
		</form>
	<?php endif; ?>

	<?php if ( $can_verify && $deposit && 'valid' !== $deposit->status ) : ?>
		<form class="mbd-promise__form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="verify_deposit" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Mark valid', 'mbd-crm' ); ?></button>
		</form>
		<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="reject_deposit" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<label><?php esc_html_e( 'Rejection reason', 'mbd-crm' ); ?></label>
			<input type="text" name="rejection_reason" />
			<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Reject', 'mbd-crm' ); ?></button>
		</form>
	<?php endif; ?>

	<?php if ( $can_override && ! $can_plan ) : ?>
		<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="override_deposit" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<label><?php esc_html_e( 'Override reason (required)', 'mbd-crm' ); ?></label>
			<input type="text" name="override_reason" />
			<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Override & unlock planning', 'mbd-crm' ); ?></button>
		</form>
	<?php endif; ?>
</section>
