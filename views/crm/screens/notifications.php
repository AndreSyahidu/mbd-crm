<?php
/**
 * Notifications screen.
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Alerts and updates relevant to you.', 'mbd-crm' ); ?></p>

	<?php
	echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'You are all caught up', 'mbd-crm' ),
		__( 'New notifications will show up here.', 'mbd-crm' ),
		'dashicons-bell'
	);
	?>
</div>
