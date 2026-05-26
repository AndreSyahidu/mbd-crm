<?php
/**
 * Deposits screen.
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Monitor deposit status across active deals.', 'mbd-crm' ); ?></p>

	<div class="mbd-chip-row">
		<?php
		echo Components::chip( __( 'Pending', 'mbd-crm' ), 'muted' );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::chip( __( 'Cleared', 'mbd-crm' ), 'success' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Components::chip( __( 'Failed', 'mbd-crm' ), 'danger' );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>

	<?php
	echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'No deposits recorded', 'mbd-crm' ),
		__( 'Deposit activity will be tracked here.', 'mbd-crm' ),
		'dashicons-money-alt'
	);
	?>
</div>
