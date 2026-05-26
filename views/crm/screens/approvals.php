<?php
/**
 * Approvals screen (restricted to approvers).
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Items awaiting your decision.', 'mbd-crm' ); ?></p>

	<div class="mbd-chip-row">
		<?php
		echo Components::chip( __( 'Awaiting', 'mbd-crm' ), 'warning' );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::chip( __( 'Approved', 'mbd-crm' ), 'success' );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::chip( __( 'Rejected', 'mbd-crm' ), 'danger' );   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>

	<?php
	echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'Nothing awaiting approval', 'mbd-crm' ),
		__( 'Requests that need your sign-off will appear here.', 'mbd-crm' ),
		'dashicons-thumbs-up'
	);
	?>
</div>
