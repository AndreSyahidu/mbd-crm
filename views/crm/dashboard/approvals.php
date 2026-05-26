<?php
/**
 * Pending Approval dashboard.
 *
 * @package MBD\CRM
 *
 * @var array<int, array{lead:string,what:string,url:string}> $rows Approval queue.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Items waiting on a decision: deposits to verify and closings to approve.', 'mbd-crm' ); ?></p>

	<?php if ( empty( $rows ) ) : ?>
		<?php
		echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			__( 'Nothing awaiting approval', 'mbd-crm' ),
			__( 'Deposit verifications and closing approvals will appear here.', 'mbd-crm' ),
			'dashicons-thumbs-up'
		);
		?>
	<?php else : ?>
		<div class="mbd-table-wrap">
			<table class="mbd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Lead', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Awaiting', 'mbd-crm' ); ?></th>
						<th class="mbd-table__actions"><?php esc_html_e( 'Actions', 'mbd-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><a class="mbd-table__primary" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['lead'] ); ?></a></td>
							<td><?php echo esc_html( $row['what'] ); ?></td>
							<td class="mbd-table__actions"><a href="<?php echo esc_url( $row['url'] ); ?>"><?php esc_html_e( 'Open', 'mbd-crm' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
