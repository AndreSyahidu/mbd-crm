<?php
/**
 * My Work screen.
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Everything assigned to you, in one place.', 'mbd-crm' ); ?></p>

	<?php
	echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'Your queue is clear', 'mbd-crm' ),
		__( 'Tasks and items assigned to you will appear here.', 'mbd-crm' ),
		'dashicons-clipboard'
	);
	?>
</div>
