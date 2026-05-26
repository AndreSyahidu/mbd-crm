<?php
/**
 * Client approval panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object             $lead        Lead row.
 * @var array<int, object> $evidence    Approval evidence records.
 * @var bool               $approved    Whether planning is approved.
 * @var bool               $can_edit    Whether the user may record approval.
 * @var string             $nonce_field Pre-rendered nonce field.
 * @var string             $form_action Form post URL.
 * @var string             $notice      Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Approval\Options;

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Client approval', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="mbd-qual__head">
		<?php
		echo $approved // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			? Components::chip( __( 'Approved', 'mbd-crm' ), 'success' )
			: Components::chip( __( 'Awaiting approval', 'mbd-crm' ), 'warning' );
		echo $approved // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			? Components::chip( __( 'Closing unlocked', 'mbd-crm' ), 'success' )
			: Components::chip( __( 'Closing locked', 'mbd-crm' ), 'muted' );
		?>
	</div>

	<?php if ( ! empty( $evidence ) ) : ?>
		<ul class="mbd-timeline">
			<?php foreach ( $evidence as $e ) : ?>
				<li class="mbd-timeline__item">
					<span class="mbd-timeline__desc">
						<?php echo Components::chip( Options::label( $e->evidence_type ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo esc_html( '' !== $e->client_name ? $e->client_name : __( 'Client', 'mbd-crm' ) ); ?>
					</span>
					<?php if ( '' !== trim( (string) $e->approval_note ) ) : ?>
						<span class="mbd-timeline__meta"><?php echo esc_html( $e->approval_note ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== (string) $e->evidence_url ) : ?>
						<span class="mbd-timeline__meta"><a href="<?php echo esc_url( $e->evidence_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View evidence', 'mbd-crm' ); ?></a></span>
					<?php endif; ?>
					<span class="mbd-timeline__meta"><?php echo esc_html( $e->approved_date ? $e->approved_date : (string) $e->created_at ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $can_edit && ! $approved ) : ?>
		<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>" enctype="multipart/form-data">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="record_approval" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<label><?php esc_html_e( 'Evidence type', 'mbd-crm' ); ?></label>
			<select name="evidence_type">
				<?php foreach ( Options::types() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label><?php esc_html_e( 'Upload evidence', 'mbd-crm' ); ?></label>
			<input type="file" name="evidence" accept="image/*,application/pdf" />
			<label><?php esc_html_e( 'Approval note', 'mbd-crm' ); ?></label>
			<textarea name="approval_note" rows="2"></textarea>
			<label><?php esc_html_e( 'Approved by (client name)', 'mbd-crm' ); ?></label>
			<input type="text" name="client_name" />
			<label><?php esc_html_e( 'Approved date', 'mbd-crm' ); ?></label>
			<input type="date" name="approved_date" />
			<button type="submit" class="mbd-btn mbd-btn--primary"><?php esc_html_e( 'Mark planning approved', 'mbd-crm' ); ?></button>
		</form>
	<?php endif; ?>
</section>
