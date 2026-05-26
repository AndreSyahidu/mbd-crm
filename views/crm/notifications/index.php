<?php
/**
 * Notifications screen.
 *
 * @package MBD\CRM
 *
 * @var array<int, array{icon:string,title:string,meta:string,chip:string,variant:string,url:string}> $items Notification items.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Alerts that need your attention.', 'mbd-crm' ); ?></p>

	<?php if ( empty( $items ) ) : ?>
		<?php
		echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			__( 'You are all caught up', 'mbd-crm' ),
			__( 'Due promises, deposit reviews, and approvals will appear here.', 'mbd-crm' ),
			'dashicons-bell'
		);
		?>
	<?php else : ?>
		<ul class="mbd-notif-list">
			<?php foreach ( $items as $item ) : ?>
				<li class="mbd-notif">
					<span class="mbd-notif__icon dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
					<div class="mbd-notif__body">
						<a class="mbd-table__primary" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
						<span class="mbd-timeline__meta"><?php echo esc_html( $item['meta'] ); ?></span>
					</div>
					<?php echo Components::chip( $item['chip'], $item['variant'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
