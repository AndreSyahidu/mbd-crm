<?php
/**
 * Qualification panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object               $lead         Lead row.
 * @var array                $eval         FitChecks::evaluate() result.
 * @var string[]             $missing      Labels of failing required checks.
 * @var object|null          $latest       Latest qualification decision.
 * @var bool                 $can_edit     Whether the user may decide.
 * @var bool                 $in_discovery Whether discovery is unlocked.
 * @var string               $nonce_field  Pre-rendered nonce field.
 * @var string               $form_action  Form post URL.
 * @var string               $notice       Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;

$mbd_can_qualify = empty( $missing );
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Qualification', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="mbd-qual__head">
		<?php
		if ( $latest && 'qualified' === $latest->decision ) {
			echo Components::chip( __( 'Qualified', 'mbd-crm' ), 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( $latest && 'not_qualified' === $latest->decision ) {
			echo Components::chip( __( 'Not qualified', 'mbd-crm' ), 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo Components::chip( __( 'Pending', 'mbd-crm' ), 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<span class="mbd-qual__score">
			<?php
			printf(
				/* translators: %d: fit score percentage. */
				esc_html__( 'Fit score: %d%%', 'mbd-crm' ),
				(int) $eval['score']
			);
			?>
		</span>
	</div>

	<ul class="mbd-checklist">
		<?php foreach ( $eval['checks'] as $check ) : ?>
			<li class="mbd-checklist__item <?php echo $check['passed'] ? 'is-pass' : 'is-fail'; ?>">
				<span class="dashicons <?php echo $check['passed'] ? 'dashicons-yes' : 'dashicons-minus'; ?>" aria-hidden="true"></span>
				<?php echo esc_html( $check['label'] ); ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ( $latest && 'not_qualified' === $latest->decision && '' !== $latest->reason ) : ?>
		<p class="mbd-field__hint">
			<strong><?php esc_html_e( 'Reason:', 'mbd-crm' ); ?></strong>
			<?php echo esc_html( $latest->reason ); ?>
		</p>
	<?php endif; ?>

	<?php if ( $can_edit ) : ?>
		<?php if ( ! $mbd_can_qualify ) : ?>
			<?php
			echo Components::notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				sprintf(
					/* translators: %s: comma-separated missing items. */
					__( 'Required before qualifying: %s', 'mbd-crm' ),
					implode( ', ', $missing )
				),
				'muted'
			);
			?>
		<?php endif; ?>

		<form class="mbd-qual__form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="qualify" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<button
				type="submit"
				class="mbd-btn mbd-btn--primary"
				<?php disabled( ! $mbd_can_qualify ); ?>
				<?php echo $mbd_can_qualify ? '' : 'aria-disabled="true"'; ?>
			>
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Mark qualified', 'mbd-crm' ); ?>
			</button>
		</form>

		<form class="mbd-qual__form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="disqualify" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<label for="mbd-unqualified_reason"><?php esc_html_e( 'Reason (required to disqualify)', 'mbd-crm' ); ?></label>
			<textarea id="mbd-unqualified_reason" name="unqualified_reason" rows="2"></textarea>
			<button type="submit" class="mbd-btn">
				<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
				<?php esc_html_e( 'Mark not qualified', 'mbd-crm' ); ?>
			</button>
		</form>
	<?php endif; ?>

	<div class="mbd-qual__discovery">
		<?php if ( $in_discovery ) : ?>
			<?php
			echo Components::notice( __( 'Discovery is unlocked for this lead.', 'mbd-crm' ), 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		<?php else : ?>
			<?php
			echo Components::notice( __( 'Discovery is blocked until this lead is qualified.', 'mbd-crm' ), 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		<?php endif; ?>
	</div>
</section>
