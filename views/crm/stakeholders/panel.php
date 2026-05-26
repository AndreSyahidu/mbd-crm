<?php
/**
 * Stakeholder panel (rendered into the lead detail sidebar).
 *
 * @package MBD\CRM
 *
 * @var object              $lead         Lead row.
 * @var array<int, object>  $stakeholders Stakeholders.
 * @var array<string,string> $roles       Role options.
 * @var array<string,string> $powers      Decision-power options.
 * @var bool                $warn_no_dm   Warn about missing decision maker.
 * @var bool                $can_edit     Whether the user may manage stakeholders.
 * @var string              $nonce_field  Pre-rendered nonce field.
 * @var string              $form_action  Form post URL.
 * @var string              $notice       Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Stakeholders\StakeholderRepository;

defined( 'ABSPATH' ) || exit;
?>
<section class="mbd-panel">
	<h3 class="mbd-panel__title"><?php esc_html_e( 'Stakeholders', 'mbd-crm' ); ?></h3>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( $warn_no_dm ) : ?>
		<?php echo Components::notice( __( 'No primary decision maker identified for this hot/warm lead. Identify the decision maker to improve close rate.', 'mbd-crm' ), 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>

	<?php if ( empty( $stakeholders ) ) : ?>
		<p class="mbd-field__hint"><?php esc_html_e( 'No stakeholders mapped yet.', 'mbd-crm' ); ?></p>
	<?php else : ?>
		<ul class="mbd-list">
			<?php foreach ( $stakeholders as $s ) : ?>
				<li class="mbd-list__item mbd-promise">
					<div class="mbd-promise__main">
						<span class="mbd-promise__desc">
							<?php echo esc_html( $s->name ); ?>
							<?php if ( (int) $s->is_primary_decision_maker === 1 ) : ?>
								<?php echo Components::chip( __( 'Primary DM', 'mbd-crm' ), 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</span>
						<span class="mbd-timeline__meta">
							<?php echo esc_html( ( StakeholderRepository::roles()[ $s->role ] ?? $s->role ) . ' · ' . ( StakeholderRepository::powers()[ $s->decision_power ] ?? $s->decision_power ) ); ?>
							<?php echo '' !== $s->phone ? ' · ' . esc_html( $s->phone ) : ''; ?>
						</span>
						<?php if ( '' !== trim( (string) $s->relationship_note ) ) : ?>
							<span class="mbd-timeline__meta"><?php echo esc_html( $s->relationship_note ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( $can_edit ) : ?>
						<div class="mbd-promise__status">
							<?php if ( (int) $s->is_primary_decision_maker !== 1 ) : ?>
								<form method="post" action="<?php echo esc_url( $form_action ); ?>">
									<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<input type="hidden" name="mbd_action" value="set_primary_stakeholder" />
									<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
									<input type="hidden" name="stakeholder_id" value="<?php echo (int) $s->id; ?>" />
									<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Make primary', 'mbd-crm' ); ?></button>
								</form>
							<?php endif; ?>
							<form method="post" action="<?php echo esc_url( $form_action ); ?>">
								<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<input type="hidden" name="mbd_action" value="delete_stakeholder" />
								<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
								<input type="hidden" name="stakeholder_id" value="<?php echo (int) $s->id; ?>" />
								<button type="submit" class="mbd-btn mbd-btn--small"><?php esc_html_e( 'Remove', 'mbd-crm' ); ?></button>
							</form>
						</div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $can_edit ) : ?>
		<form class="mbd-fu-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="mbd_action" value="add_stakeholder" />
			<input type="hidden" name="lead_id" value="<?php echo (int) $lead->id; ?>" />
			<label><?php esc_html_e( 'Name', 'mbd-crm' ); ?></label>
			<input type="text" name="name" />
			<label><?php esc_html_e( 'Role', 'mbd-crm' ); ?></label>
			<select name="role">
				<?php foreach ( $roles as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label><?php esc_html_e( 'Decision power', 'mbd-crm' ); ?></label>
			<select name="decision_power">
				<?php foreach ( $powers as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"<?php echo 'unknown' === $key ? ' selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label><?php esc_html_e( 'Phone', 'mbd-crm' ); ?></label>
			<input type="text" name="phone" inputmode="numeric" />
			<label><?php esc_html_e( 'Email', 'mbd-crm' ); ?></label>
			<input type="text" name="email" />
			<label><?php esc_html_e( 'Relationship note', 'mbd-crm' ); ?></label>
			<input type="text" name="relationship_note" />
			<label class="mbd-check"><input type="checkbox" name="is_primary_decision_maker" value="1" /> <?php esc_html_e( 'Primary decision maker', 'mbd-crm' ); ?></label>
			<button type="submit" class="mbd-btn mbd-btn--small mbd-btn--primary"><?php esc_html_e( 'Add stakeholder', 'mbd-crm' ); ?></button>
		</form>
	<?php endif; ?>
</section>
