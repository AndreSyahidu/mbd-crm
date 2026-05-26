<?php
/**
 * Notifications screen: due promises.
 *
 * @package MBD\CRM
 *
 * @var array<int, array{lead:string,description:string,due:string,overdue:bool,url:string}> $rows Due promises.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Promises that are due or overdue and still open.', 'mbd-crm' ); ?></p>

	<?php if ( empty( $rows ) ) : ?>
		<?php
		echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			__( 'You are all caught up', 'mbd-crm' ),
			__( 'Due promises will appear here so nothing slips.', 'mbd-crm' ),
			'dashicons-bell'
		);
		?>
	<?php else : ?>
		<div class="mbd-table-wrap">
			<table class="mbd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Lead', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Promise', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Due', 'mbd-crm' ); ?></th>
						<th class="mbd-table__actions"><?php esc_html_e( 'Actions', 'mbd-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><a class="mbd-table__primary" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['lead'] ); ?></a></td>
							<td><?php echo esc_html( $row['description'] ); ?></td>
							<td>
								<?php
								echo Components::chip( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									$row['due'],
									$row['overdue'] ? 'danger' : 'warning'
								);
								?>
							</td>
							<td class="mbd-table__actions">
								<a href="<?php echo esc_url( $row['url'] ); ?>"><?php esc_html_e( 'Open', 'mbd-crm' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
