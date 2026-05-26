<?php
/**
 * Follow-Ups screen.
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Scheduled touchpoints, sorted by what needs attention first.', 'mbd-crm' ); ?></p>

	<div class="mbd-chip-row">
		<?php
		echo Components::chip( __( 'Due today', 'mbd-crm' ), 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::chip( __( 'Overdue', 'mbd-crm' ), 'danger' );    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::chip( __( 'Done', 'mbd-crm' ), 'success' );      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>

	<?php
	echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'No follow-ups scheduled', 'mbd-crm' ),
		__( 'When follow-ups are due they will be listed here.', 'mbd-crm' ),
		'dashicons-phone'
	);
	?>
</div>
