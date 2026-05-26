<?php
/**
 * Discovery screen.
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Capture discovery notes and qualify opportunities.', 'mbd-crm' ); ?></p>

	<?php
	echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		__( 'No discovery records', 'mbd-crm' ),
		__( 'Discovery sessions you capture will appear here.', 'mbd-crm' ),
		'dashicons-search'
	);
	?>
</div>
