<?php
/**
 * Closing screen.
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Deals approaching the finish line.', 'mbd-crm' ); ?></p>

	<div class="mbd-chip-row">
		<?php
		echo Components::chip( __( 'In progress', 'mbd-crm' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::chip( __( 'Won', 'mbd-crm' ), 'success' );      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::chip( __( 'Lost', 'mbd-crm' ), 'danger' );      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>

	<?php
	echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'No closings in progress', 'mbd-crm' ),
		__( 'Deals ready to close will be listed here.', 'mbd-crm' ),
		'dashicons-yes-alt'
	);
	?>
</div>
