<?php
/**
 * Planning screen.
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Plan upcoming activities and key milestones.', 'mbd-crm' ); ?></p>

	<?php
	echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'Nothing planned', 'mbd-crm' ),
		__( 'Scheduled plans and milestones will show up here.', 'mbd-crm' ),
		'dashicons-calendar-alt'
	);
	?>
</div>
