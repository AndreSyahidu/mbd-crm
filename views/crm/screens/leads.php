<?php
/**
 * Leads screen.
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<div class="mbd-toolbar">
		<p class="mbd-page__lead"><?php esc_html_e( 'Track incoming leads and move them through your pipeline.', 'mbd-crm' ); ?></p>
		<button type="button" class="mbd-btn mbd-btn--primary" disabled aria-disabled="true">
			<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
			<?php esc_html_e( 'New Lead', 'mbd-crm' ); ?>
		</button>
	</div>

	<?php
	echo Components::notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'Creating leads is not available yet in this foundation build.', 'mbd-crm' ),
		'muted'
	);

	echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'No leads yet', 'mbd-crm' ),
		__( 'Leads will appear here once data sources are connected.', 'mbd-crm' ),
		'dashicons-groups'
	);
	?>
</div>
