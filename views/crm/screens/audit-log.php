<?php
/**
 * Audit Log screen (restricted to administrators).
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'A read-only record of system and user activity.', 'mbd-crm' ); ?></p>

	<?php
	echo Components::notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'This screen is restricted to administrators.', 'mbd-crm' ),
		'info'
	);

	echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'No audit entries', 'mbd-crm' ),
		__( 'Recorded activity will be listed here.', 'mbd-crm' ),
		'dashicons-list-view'
	);
	?>
</div>
