<?php
/**
 * Import / Export admin page.
 *
 * @package MBD\CRM
 *
 * @var string        $import_action Import form action URL.
 * @var string        $import_nonce  Pre-rendered nonce field.
 * @var callable      $export_url    Builds a nonced export URL for a type.
 * @var bool          $can_import    Whether the user may import.
 * @var bool          $can_audit     Whether the user may export the audit log.
 * @var array|null    $result        Last import result.
 */

defined( 'ABSPATH' ) || exit;

$mbd_exports = array(
	'leads'            => __( 'Leads', 'mbd-crm' ),
	'funnel'           => __( 'Funnel report', 'mbd-crm' ),
	'planning'         => __( 'Planning report', 'mbd-crm' ),
	'closing_forecast' => __( 'Closing forecast', 'mbd-crm' ),
	'lost_reason'      => __( 'Lost reason report', 'mbd-crm' ),
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'MBD CRM — Import / Export', 'mbd-crm' ); ?></h1>

	<?php if ( is_array( $result ) ) : ?>
		<?php if ( isset( $result['error'] ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $result['error'] ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-success">
				<p>
					<?php
					printf(
						/* translators: %d: number of imported rows. */
						esc_html__( 'Imported %d row(s).', 'mbd-crm' ),
						(int) ( $result['imported'] ?? 0 )
					);
					?>
				</p>
			</div>
			<?php if ( ! empty( $result['failed'] ) ) : ?>
				<h2><?php esc_html_e( 'Failed rows', 'mbd-crm' ); ?></h2>
				<table class="widefat striped" style="max-width:600px">
					<thead><tr><th><?php esc_html_e( 'Row', 'mbd-crm' ); ?></th><th><?php esc_html_e( 'Reason', 'mbd-crm' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $result['failed'] as $fail ) : ?>
							<tr><td><?php echo (int) $fail['row']; ?></td><td><?php echo esc_html( $fail['reason'] ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Import CSV', 'mbd-crm' ); ?></h2>
	<?php if ( $can_import ) : ?>
		<form method="post" action="<?php echo esc_url( $import_action ); ?>" enctype="multipart/form-data">
			<?php echo $import_nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="action" value="mbd_crm_import" />
			<p>
				<label for="mbd-import-type"><?php esc_html_e( 'Type', 'mbd-crm' ); ?></label>
				<select id="mbd-import-type" name="import_type">
					<option value="leads"><?php esc_html_e( 'Leads', 'mbd-crm' ); ?></option>
					<option value="customers"><?php esc_html_e( 'Customers', 'mbd-crm' ); ?></option>
					<option value="master_options"><?php esc_html_e( 'Master options', 'mbd-crm' ); ?></option>
				</select>
			</p>
			<p><input type="file" name="csv" accept=".csv" required /></p>
			<p class="description"><?php esc_html_e( 'Leads/customers columns: name, whatsapp, source, project_type, service_type, estimated_budget, urgency, quality, notes. Master options columns: group, key, label. Duplicate leads (same WhatsApp + name) are skipped.', 'mbd-crm' ); ?></p>
			<?php submit_button( __( 'Import', 'mbd-crm' ) ); ?>
		</form>
	<?php else : ?>
		<p><?php esc_html_e( 'You do not have permission to import.', 'mbd-crm' ); ?></p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Export CSV', 'mbd-crm' ); ?></h2>
	<p>
		<?php foreach ( $mbd_exports as $mbd_type => $mbd_label ) : ?>
			<a class="button" href="<?php echo esc_url( call_user_func( $export_url, $mbd_type ) ); ?>"><?php echo esc_html( $mbd_label ); ?></a>
		<?php endforeach; ?>
		<?php if ( $can_audit ) : ?>
			<a class="button" href="<?php echo esc_url( call_user_func( $export_url, 'audit' ) ); ?>"><?php esc_html_e( 'Audit log', 'mbd-crm' ); ?></a>
		<?php endif; ?>
	</p>
</div>
