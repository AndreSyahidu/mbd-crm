<?php
/**
 * Discovery screen: qualified leads ready for discovery.
 *
 * @package MBD\CRM
 *
 * @var array<int, array{name: string, url: string}> $rows Qualified leads.
 */

use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Leads enter discovery once they are qualified. Only qualified leads appear here.', 'mbd-crm' ); ?></p>

	<?php if ( empty( $rows ) ) : ?>
		<?php
		echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			__( 'No leads ready for discovery', 'mbd-crm' ),
			__( 'Qualify a lead to unlock discovery and schedule a session.', 'mbd-crm' ),
			'dashicons-search'
		);
		?>
	<?php else : ?>
		<div class="mbd-table-wrap">
			<table class="mbd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Lead', 'mbd-crm' ); ?></th>
						<th class="mbd-table__actions"><?php esc_html_e( 'Actions', 'mbd-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td>
								<a class="mbd-table__primary" href="<?php echo esc_url( $row['url'] ); ?>">
									<?php echo esc_html( $row['name'] ); ?>
								</a>
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
