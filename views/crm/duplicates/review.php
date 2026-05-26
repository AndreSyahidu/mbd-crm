<?php
/**
 * Duplicate review screen (Owner/Admin).
 *
 * @package MBD\CRM
 *
 * @var array<int, array<int, object>> $groups      Duplicate groups.
 * @var string                         $nonce_field Pre-rendered nonce field.
 * @var string                         $form_action Form post URL.
 * @var string                         $notice      Flash notice HTML.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Stage;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Leads sharing the same WhatsApp number. Choose the primary lead to keep; the others are merged into it and archived.', 'mbd-crm' ); ?></p>

	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( empty( $groups ) ) : ?>
		<?php
		echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			__( 'No duplicates found', 'mbd-crm' ),
			__( 'Leads with matching WhatsApp numbers will appear here for review.', 'mbd-crm' ),
			'dashicons-admin-page'
		);
		?>
	<?php else : ?>
		<?php foreach ( $groups as $gi => $members ) : ?>
			<section class="mbd-panel">
				<h2 class="mbd-panel__title">
					<?php
					printf(
						/* translators: %s: normalized WhatsApp number. */
						esc_html__( 'WhatsApp %s', 'mbd-crm' ),
						esc_html( $members[0]->whatsapp_normalized )
					);
					?>
				</h2>
				<form method="post" action="<?php echo esc_url( $form_action ); ?>">
					<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="mbd_action" value="merge_leads" />
					<table class="mbd-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Primary', 'mbd-crm' ); ?></th>
								<th><?php esc_html_e( 'Lead', 'mbd-crm' ); ?></th>
								<th><?php esc_html_e( 'Stage', 'mbd-crm' ); ?></th>
								<th><?php esc_html_e( 'Created', 'mbd-crm' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $members as $mi => $m ) : ?>
								<tr>
									<td><input type="radio" name="primary_id" value="<?php echo (int) $m->id; ?>"<?php echo 0 === $mi ? ' checked' : ''; ?> /></td>
									<td><?php echo esc_html( '' !== $m->name ? $m->name : __( '(no name)', 'mbd-crm' ) ); ?> (#<?php echo (int) $m->id; ?>)</td>
									<td><?php echo esc_html( Stage::label( Stage::key( $m ) ) ); ?></td>
									<td><?php echo esc_html( (string) $m->created_at ); ?></td>
									<input type="hidden" name="member_ids[]" value="<?php echo (int) $m->id; ?>" />
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<button type="submit" class="mbd-btn mbd-btn--primary mbd-btn--small"><?php esc_html_e( 'Merge into primary', 'mbd-crm' ); ?></button>
				</form>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
