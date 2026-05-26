<?php
/**
 * Dashboard screen.
 *
 * @package MBD\CRM
 *
 * @var array $screen Screen meta.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Router;

defined( 'ABSPATH' ) || exit;

$mbd_cards = array(
	array(
		'label' => __( 'Open Leads', 'mbd-crm' ),
		'slug'  => 'leads',
		'chip'  => array( __( 'Live', 'mbd-crm' ), 'info' ),
	),
	array(
		'label' => __( 'Follow-Ups Due', 'mbd-crm' ),
		'slug'  => 'follow-ups',
		'chip'  => array( __( 'Today', 'mbd-crm' ), 'warning' ),
	),
	array(
		'label' => __( 'Deposits Pending', 'mbd-crm' ),
		'slug'  => 'deposits',
		'chip'  => array( __( 'Pending', 'mbd-crm' ), 'muted' ),
	),
	array(
		'label' => __( 'Closings This Month', 'mbd-crm' ),
		'slug'  => 'closing',
		'chip'  => array( __( 'On track', 'mbd-crm' ), 'success' ),
	),
);
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Your CRM at a glance. Metrics populate as data is connected.', 'mbd-crm' ); ?></p>

	<div class="mbd-cards">
		<?php foreach ( $mbd_cards as $mbd_card ) : ?>
			<a class="mbd-card" href="<?php echo esc_url( Router::screen_url( $mbd_card['slug'] ) ); ?>">
				<span class="mbd-card__label"><?php echo esc_html( $mbd_card['label'] ); ?></span>
				<span class="mbd-card__metric" aria-hidden="true">&mdash;</span>
				<span class="mbd-card__footer">
					<?php echo Components::chip( $mbd_card['chip'][0], $mbd_card['chip'][1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>

	<section class="mbd-panel">
		<h2 class="mbd-panel__title"><?php esc_html_e( 'Status legend', 'mbd-crm' ); ?></h2>
		<div class="mbd-chip-row">
			<?php
			echo Components::chip( __( 'New', 'mbd-crm' ), 'info' );         // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::chip( __( 'In progress', 'mbd-crm' ), 'default' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::chip( __( 'Won', 'mbd-crm' ), 'success' );      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::chip( __( 'At risk', 'mbd-crm' ), 'warning' );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::chip( __( 'Lost', 'mbd-crm' ), 'danger' );      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Components::chip( __( 'Archived', 'mbd-crm' ), 'muted' );   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
	</section>
</div>
